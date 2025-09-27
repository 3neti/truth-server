<?php

namespace TruthQr\Classify;

use TruthQr\Assembly\Contracts\TruthAssemblerContract;

/**
 * ClassifySession
 *
 * Stateful per-code ingest/assemble workflow.
 * - If constructed with $code = null, the first ingested line establishes the code.
 * - Further lines for a different code are rejected (recorded in errors()).
 */
final class ClassifySession
{
    private ?string $code;
    /** @var array{code:string,total:int,received:int,missing:int[]} */
    private array $lastStatus = ['code' => '', 'total' => 0, 'received' => 0, 'missing' => []];
    /** @var string[] */
    private array $errors = [];

    public function __construct(
        private readonly TruthAssemblerContract $assembler,
        ?string $code = null
    ) {
        $this->code = $code;
    }

    /**
     * Ingest a single TRUTH line/URL.
     *
     * @return array{code:string,total:int,received:int,missing:int[]}
     */
    public function addLine(string $line): array
    {
        try {
            $status = $this->assembler->ingestLine($line);

            // Lock or verify code
            $lineCode = $status['code'] ?? '';
            if ($this->code === null) {
                $this->code = $lineCode;
            } elseif ($lineCode !== $this->code) {
                throw new \InvalidArgumentException("Mismatched code '{$lineCode}' for session code '{$this->code}'.");
            }

            $this->lastStatus = $status;
            return $status;
        } catch (\Throwable $e) {
            $this->errors[] = $e->getMessage();
            throw $e;
        }
    }

    /**
     * Ingest many lines. Returns the *last* status (if any).
     *
     * @param iterable<string> $lines
     * @return array{code:string,total:int,received:int,missing:int[]}
     */
    public function addLines(iterable $lines): array
    {
        $last = $this->lastStatus;
        foreach ($lines as $line) {
            try {
                $last = $this->addLine((string) $line);
            } catch (\Throwable $e) {
                // error already recorded; continue with next line
            }
        }
        return $last;
    }

    /**
     * Latest known status snapshot.
     *
     * @return array{code:string,total:int,received:int,missing:int[]}
     */
    public function status(): array
    {
        if ($this->code !== null) {
            // Ask assembler for fresher status (in case parallel ingests happened)
            try {
                return $this->assembler->status($this->code);
            } catch (\Throwable) {
                // fall back to lastStatus
            }
        }
        return $this->lastStatus;
    }

    public function isComplete(): bool
    {
        return $this->code !== null && $this->assembler->isComplete($this->code);
    }

    /**
     * Assemble and return decoded payload (array).
     *
     * @return array<string,mixed>
     */
    public function assemble(): array
    {
        if ($this->code === null) {
            throw new \RuntimeException('No code set: nothing ingested yet.');
        }
        return $this->assembler->assemble($this->code);
    }

    /**
     * Cached artifact if present: ['mime' => string, 'body' => string]
     *
     * @return array{mime:string,body:string}|null
     */
    public function artifact(): ?array
    {
        if ($this->code === null) return null;
        return $this->assembler->artifact($this->code);
    }

    /** The code for this session, if established. */
    public function code(): ?string
    {
        return $this->code;
    }

    /** @return string[] accumulated errors during addLines/addLine */
    public function errors(): array
    {
        return $this->errors;
    }
}
