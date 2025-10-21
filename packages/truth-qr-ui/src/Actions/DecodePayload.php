<?php

namespace TruthQrUi\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\ActionRequest;
use TruthQr\Assembly\TruthAssembler;
use TruthQr\Classify\Classify;
use TruthQr\Stores\ArrayTruthStore;
use TruthCodec\Contracts\Envelope;
use TruthCodec\Contracts\PayloadSerializer;
use TruthCodec\Contracts\TransportCodec;
use TruthQrUi\Support\CodecAliasFactory;
use TruthElection\Data\ERData;
use TruthElection\Data\ElectionReturnData;

final class DecodePayload
{
    use AsAction;

    /**
     * Handle: ingest chunk lines and return the decoded payload (if complete)
     * plus helpful status.
     *
     * @param string[]          $lines  One line / URL per element (ER|v1|... or truth://v1/...)
     * @param Envelope          $envelope
     * @param TransportCodec    $transport
     * @param PayloadSerializer $serializer
     * @return array{
     *   code?:string,total?:int,received?:int,missing?:int[],
     *   complete:bool,
     *   payload?:array<string,mixed>,
     *   artifact?:array{mime:string,body:string},
     *   transformed?:bool,
     *   transformation?:array{from:string,to:string,compression:string}
     * }
     */
    public function handle(
        array $lines,
        Envelope $envelope,
        TransportCodec $transport,
        PayloadSerializer $serializer
    ): array {
        $asm = new TruthAssembler(
            store: new ArrayTruthStore(),
            envelope: $envelope,
            transport: $transport,
            serializer: $serializer
        );

        $classify = new Classify($asm);
        $sess     = $classify->newSession();
        $sess->addLines($lines);

        $st = $sess->status(); // ['code','total','received','missing'=>[]]
        $complete = $sess->isComplete();

        $out = [
                'complete' => $complete,
            ] + $st;

        if ($complete) {
            try {
                // Attempt to assemble the full payload. If a conflicting/tainted
                // piece slipped through (e.g., duplicate with altered payload),
                // downstream decoding may throw. Normalize that to a 422.
                $payload = $sess->assemble(); // decoded array
            } catch (\Throwable $e) {
                abort(422, 'Failed to assemble payload (corrupted or conflicting chunks): ' . $e->getMessage());
            }

            // Auto-detect and transform ERData to full ElectionReturnData
            $originalPayload = $payload;
            $transformed = false;
            $transformationInfo = null;
            
            if ($this->isERData($payload)) {
                try {
                    $originalSize = strlen(json_encode($payload));
                    
                    // Transform minified ERData to full ElectionReturnData
                    $erData = ERData::from($payload);
                    $expandedElectionReturn = ElectionReturnData::fromERData($erData);
                    $payload = $expandedElectionReturn->toArray();
                    
                    $expandedSize = strlen(json_encode($payload));
                    $compressionRatio = $originalSize > 0 ? ($expandedSize / $originalSize) : 1;
                    
                    $transformed = true;
                    $transformationInfo = [
                        'from' => 'ERData (minified)',
                        'to' => 'ElectionReturnData (full)',
                        'compression' => sprintf('%.1f:1 expansion (%s → %s)', 
                            $compressionRatio,
                            $this->formatBytes($originalSize),
                            $this->formatBytes($expandedSize)
                        )
                    ];
                    
                    \Log::info('ERData automatically transformed to ElectionReturnData', [
                        'code' => $st['code'] ?? 'unknown',
                        'original_size' => $originalSize,
                        'expanded_size' => $expandedSize,
                        'compression_ratio' => $compressionRatio
                    ]);
                    
                } catch (\Throwable $e) {
                    // If transformation fails, use original payload and log the error
                    \Log::warning('ERData transformation failed, using original payload', [
                        'error' => $e->getMessage(),
                        'code' => $st['code'] ?? 'unknown'
                    ]);
                    $payload = $originalPayload;
                }
            }

            $out['payload'] = $payload;
            
            if ($transformed) {
                $out['transformed'] = true;
                $out['transformation'] = $transformationInfo;
            }

            if (method_exists($asm, 'artifact')) {
                $art = $asm->artifact($st['code']);
                if (is_array($art)) {
                    $out['artifact'] = $art; // ['mime' => ..., 'body' => ...]
                }
            }
        }

        return $out;

//        $st = $sess->status(); // ['code','total','received','missing'=>[]]
//        $complete = $sess->isComplete();
//
//        $out = [
//                'complete' => $complete,
//            ] + $st;
//
//        if ($complete) {
//            $payload = $sess->assemble(); // decoded array
//            $out['payload'] = $payload;
//
//            if (method_exists($asm, 'artifact')) {
//                $art = $asm->artifact($st['code']);
//                if (is_array($art)) {
//                    $out['artifact'] = $art; // ['mime' => ..., 'body' => ...]
//                }
//            }
//        }
//
//        return $out;
    }

    /**
     * Slim HTTP controller: resolves collaborators (aliases or FQCN),
     * normalizes input lines, calls handle(), and returns JSON.
     *
     * Body accepts either:
     *   - "lines":  ["ER|v1|...","truth://v1/..."]
     *   - "chunks": [{"text":"..."}]
     *
     * And codec specifiers by alias (preferred) or FQCN:
     *   - envelope:  v1line | v1url         (alias)
     *   - transport: base64url | base64url+deflate | base64url+gzip (alias)
     *   - serializer: json | yaml | auto    (alias)
     * or:
     *   - envelope_fqcn, transport_fqcn, serializer_fqcn (FQCNs)
     */
    public function asController(ActionRequest $request)
    {
        // 1) Resolve collaborators (aliases first, FQCN fallbacks)
        [$envelope, $transport, $serializer] = $this->resolveCollaborators($request);

        // 2) Normalize input lines (supports 'lines' OR 'chunks')
        $lines = $this->extractLines($request->input('lines'), $request->input('chunks'));

        // 3) Execute core
        $res = $this->handle($lines, $envelope, $transport, $serializer);

        // 4) Return raw result; shape already friendly for Decode
        return response()->json($res);
    }

    /** @return array{0:Envelope,1:TransportCodec,2:PayloadSerializer} */
    private function resolveCollaborators(ActionRequest $request): array
    {
        // Aliases (preferred)
        $envAlias = $request->input('envelope');   // 'v1line' | 'v1url'
        $txAlias  = $request->input('transport');  // 'base64url' | 'base64url+deflate' | 'base64url+gzip'
        $serAlias = $request->input('serializer'); // 'json' | 'yaml' | 'auto'

        // FQCN fallbacks (only used if alias not supplied)
        $envFqcn = (string) $request->input('envelope_fqcn',  \TruthCodec\Envelope\EnvelopeV1Url::class);
        $txFqcn  = (string) $request->input('transport_fqcn', \TruthCodec\Transport\Base64UrlDeflateTransport::class);
        $serFqcn = (string) $request->input('serializer_fqcn',\TruthCodec\Serializer\JsonSerializer::class);

        /** @var Envelope $envelope */
        $envelope = is_string($envAlias) && $envAlias !== ''
            ? CodecAliasFactory::makeEnvelope($envAlias)
            : $this->new($envFqcn);

        /** @var TransportCodec $transport */
        $transport = is_string($txAlias) && $txAlias !== ''
            ? CodecAliasFactory::makeTransport($txAlias)
            : $this->new($txFqcn);

        /** @var PayloadSerializer $serializer */
        $serializer = is_string($serAlias) && $serAlias !== ''
            ? CodecAliasFactory::makeSerializer($serAlias)
            : $this->new($serFqcn);

        return [$envelope, $transport, $serializer];
    }

    /**
     * Accept either:
     *  - lines:  ["ER|v1|...","truth://v1/..."]
     *  - chunks: [{"text":"..."}]
     *
     * @param mixed $lines
     * @param mixed $chunks
     * @return string[]
     */
    private function extractLines(mixed $lines, mixed $chunks): array
    {
        if (is_array($lines) && !empty($lines)) {
            return array_map(static fn($v) => (string) $v, array_values($lines));
        }

        if (is_array($chunks) && !empty($chunks)) {
            return array_map(
                static fn($c) => (string) ($c['text'] ?? ''),
                array_values($chunks)
            );
        }

        abort(422, 'Provide either "lines": string[] or "chunks": [{"text": "..."}].');
    }

    /**
     * @template T
     * @param class-string<T> $fqcn
     * @return T
     */
    private function new(string $fqcn)
    {
        if (!class_exists($fqcn)) {
            throw new \InvalidArgumentException("Class not found: {$fqcn}");
        }
        return new $fqcn();
    }
    
    /**
     * Detect if the decoded payload is ERData (minified) or ElectionReturnData (full)
     * 
     * ERData characteristics:
     * - tallies: associative array/object (candidate_code => count)
     * - signatures: array of objects with id, signature, signed_at (no name/role)
     * - Missing: precinct, ballots arrays
     * 
     * ElectionReturnData characteristics:
     * - tallies: array of objects with position_code, candidate_code, candidate_name, count
     * - signatures: array of objects with id, name, role, signature, signed_at
     * - Has: precinct object, ballots array
     */
    private function isERData(array $payload): bool
    {
        // Must have the basic ERData structure
        if (!isset($payload['tallies']) || !isset($payload['signatures'])) {
            return false;
        }
        
        // Check tallies structure - ERData has key-value pairs (candidate_code => count)
        // ElectionReturnData has array of objects with position_code, candidate_code, etc.
        $tallies = $payload['tallies'];
        
        if (is_array($tallies) && !empty($tallies)) {
            // If tallies is an associative array with string keys and integer values,
            // it's likely ERData format (candidate_code => count)
            $firstKey = array_key_first($tallies);
            $firstValue = $tallies[$firstKey];
            
            if (is_string($firstKey) && is_int($firstValue)) {
                // Additional check: ERData shouldn't have precinct or ballots
                $hasPrecinctObject = isset($payload['precinct']) && is_array($payload['precinct']) && 
                    isset($payload['precinct']['code']);
                $hasBallotsArray = isset($payload['ballots']) && is_array($payload['ballots']);
                
                // ERData typically doesn't have full precinct object or ballots array
                if (!$hasPrecinctObject && !$hasBallotsArray) {
                    return true;
                }
                
                // If it has precinct but tallies are in key-value format, still likely ERData
                return true;
            }
            
            // If tallies is array of objects with position_code, it's ElectionReturnData
            if (is_array($firstValue) && isset($firstValue['position_code'])) {
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Format bytes into human readable format
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . 'B';
        }
        
        $units = ['B', 'K', 'M', 'G'];
        $factor = floor(log($bytes, 1024));
        $factor = min($factor, count($units) - 1);
        
        $size = $bytes / pow(1024, $factor);
        $precision = $factor > 0 ? 1 : 0;
        
        return round($size, $precision) . $units[$factor];
    }
}
