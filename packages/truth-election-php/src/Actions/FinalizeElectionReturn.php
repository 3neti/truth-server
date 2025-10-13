<?php

namespace TruthElection\Actions;

use TruthElection\Data\{ElectionReturnData, FinalizeErContext, PrecinctData};
use TruthElection\Support\{ElectionReturnContext, PrecinctContext};
use TruthElection\Pipes\ValidateSignatures;
use Lorisleiva\Actions\Concerns\AsAction;
use TruthElection\Pipes\CloseBalloting;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Arr;
use RuntimeException;

class FinalizeElectionReturn
{
    use AsAction;

    public function __construct(
        protected PrecinctContext $precinctContext,
        protected ElectionReturnContext $electionReturnContext
    ) {}

    public function handle(
        string $disk = 'local',
        string $payload = 'minimal',
        int $maxChars = 1200,
        string $dir = 'final',
        bool $force = false,
        // QR encoding parameters
        ?string $encodingStrategy = null,
        ?int $chunkCount = null,
        ?int $chunkSize = null,
        ?string $qrWriterFormat = null,
        ?int $qrWriterSize = null,
        ?int $qrWriterMargin = null,
    ): ElectionReturnData {
        $precinct = $this->precinctContext->getPrecinct();

        if (! $precinct instanceof PrecinctData) {
            throw new RuntimeException("Precinct [{$precinct->code}] not found.");
        }

        if (! $force && ($precinct->meta['balloting_open'] ?? true) === false) {
            throw new RuntimeException("Balloting already closed. Nothing to do.");
        }

        $er = $this->electionReturnContext->getElectionReturn();
        if (! $er instanceof ElectionReturnData) {
            throw new RuntimeException("Election Return for [{$precinct->code}] not found.");
        }

        // Get QR encoding configuration defaults
        $qrConfig = config('truth-election.finalize_election_return.qr_encoding', []);
        
        $ctx = new FinalizeErContext(
            precinct: $precinct,
            er: $er,
            disk: $disk,
            folder: "ER-{$er->code}/{$dir}",
            payload: $payload,
            maxChars: $maxChars,
            force: $force,
            qrPersistedAbs: null,
            
            // QR encoding with config defaults
            encodingStrategy: $encodingStrategy ?? $qrConfig['strategy'] ?? 'count',
            chunkCount: $chunkCount ?? $qrConfig['chunk_count'] ?? 4,
            chunkSize: $chunkSize ?? $qrConfig['chunk_size'] ?? 1200,
            qrWriterFormat: $qrWriterFormat ?? $qrConfig['writer']['format'] ?? 'svg',
            qrWriterSize: $qrWriterSize ?? $qrConfig['writer']['size'] ?? 512,
            qrWriterMargin: $qrWriterMargin ?? $qrConfig['writer']['margin'] ?? 16,
        );

        $configuredPipes = config('truth-election.finalize_election_return.pipes', []);

        app(Pipeline::class)
            ->send($ctx)
            ->through(array_merge(
                [ValidateSignatures::class],
                $configuredPipes,
                [CloseBalloting::class]
            ))
            ->thenReturn();

        return $ctx->er;
    }

    public function rules(): array
    {
        return [
            'disk' => ['nullable', 'string'],
            'payload' => ['nullable', 'string'],
            'maxChars' => ['nullable', 'integer'],
            'dir' => ['nullable', 'string'],
            'force' => ['nullable', 'boolean'],
            // QR encoding parameters
            'encodingStrategy' => ['nullable', 'string', 'in:size,count'],
            'chunkCount' => ['nullable', 'integer', 'min:1'],
            'chunkSize' => ['nullable', 'integer', 'min:100'],
            'qrWriterFormat' => ['nullable', 'string', 'in:svg,png,eps'],
            'qrWriterSize' => ['nullable', 'integer', 'min:128'],
            'qrWriterMargin' => ['nullable', 'integer', 'min:0'],
        ];
    }
    public function asController(ActionRequest $request): ElectionReturnData
    {
        $validated = $request->validated();

        return $this->handle(
            disk: Arr::get($validated, 'disk', 'local'),
            payload: Arr::get($validated, 'payload', 'minimal'),
            maxChars: (int) Arr::get($validated, 'maxChars', 1200),
            dir: Arr::get($validated, 'dir', 'final'),
            force: (bool) Arr::get($validated, 'force', false),
            // QR encoding parameters
            encodingStrategy: Arr::get($validated, 'encodingStrategy'),
            chunkCount: isset($validated['chunkCount']) ? (int) $validated['chunkCount'] : null,
            chunkSize: isset($validated['chunkSize']) ? (int) $validated['chunkSize'] : null,
            qrWriterFormat: Arr::get($validated, 'qrWriterFormat'),
            qrWriterSize: isset($validated['qrWriterSize']) ? (int) $validated['qrWriterSize'] : null,
            qrWriterMargin: isset($validated['qrWriterMargin']) ? (int) $validated['qrWriterMargin'] : null,
        );
    }
}
