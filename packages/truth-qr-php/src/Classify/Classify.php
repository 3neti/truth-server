<?php

namespace TruthQr\Classify;

use TruthQr\Assembly\Contracts\TruthAssemblerContract;

/**
 * Classify
 *
 * Stateless factory/manager that produces per-code ClassifySession
 * instances backed by the configured TruthAssemblerContract.
 */
final class Classify
{
    public function __construct(
        private readonly TruthAssemblerContract $assembler
    ) {}

    /**
     * Create a new classification session.
     *
     * @param string|null $code Optional fixed code. If null, the first accepted
     *                          line in the session determines the code.
     */
    public function newSession(?string $code = null): ClassifySession
    {
        return new ClassifySession($this->assembler, $code);
    }

    /**
     * Ingest a mixed batch of lines (possibly multiple codes).
     * Returns per-code last status and any errors encountered.
     *
     * @param iterable<string> $lines
     * @return array{
     *   per_code: array<string, array{code:string,total:int,received:int,missing:int[]}>,
     *   errors:   array<int,string>
     * }
     */
    public function ingestLines(iterable $lines): array
    {
        $perCode = [];
        $errors  = [];

        foreach ($lines as $idx => $line) {
            try {
                // Peek code via a throwaway session with no fixed code
                $tmp = $this->newSession();
                $status = $tmp->addLine((string) $line);
                $code   = $status['code'];

                // Re-route to an existing per-code session if desired.
                // For now we just capture last status per code.
                $perCode[$code] = $status;
            } catch (\Throwable $e) {
                $errors[(int) $idx] = $e->getMessage();
            }
        }

        return ['per_code' => $perCode, 'errors' => $errors];
    }
}
