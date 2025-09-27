<?php

namespace TruthQr\Console;

use Illuminate\Console\Command;
use TruthQr\Assembly\Contracts\TruthAssemblerContract;

/**
 * Ingest TRUTH lines/URLs from a text file (one per line) and assemble
 * the payload once complete. Supports printing the decoded payload and/or
 * writing the serialized artifact to disk.
 *
 * Usage:
 *  php artisan truth:ingest-file storage/qr-lines.txt --print --out=storage/artifact.json
 */
final class TruthIngestFileCommand extends Command
{
    protected $signature = 'truth:ingest-file
        {path : Path to a text file containing one TRUTH line/URL per line}
        {--code= : Expected code (optional; warns if different is seen)}
        {--out= : If complete, write assembled artifact to this path}
        {--print : Print decoded payload to stdout (JSON)}';

    protected $description = 'Ingest TRUTH lines/URLs from a file and assemble the payload when complete.';

    public function __construct(private readonly TruthAssemblerContract $assembler)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (!is_file($path) || !is_readable($path)) {
            $this->error("Input file not readable: {$path}");
            return self::FAILURE;
        }

        $expectedCode = $this->option('code') ? (string) $this->option('code') : null;

        $contents = (string) file_get_contents($path);
        $lines = preg_split('/\R/u', $contents) ?: [];

        $lastCode   = null;
        $lastStatus = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            try {
                // ingestLine() returns status: ['code','total','received','missing'=>[]]
                $status = $this->assembler->ingestLine($line);
            } catch (\Throwable $e) {
                $this->error('Ingest error: ' . $e->getMessage());
                return self::FAILURE;
            }

            $lastStatus = $status;
            $lastCode   = $status['code'] ?? $lastCode;

            if ($expectedCode && $lastCode && $lastCode !== $expectedCode) {
                $this->warn("Warning: seen code '{$lastCode}', expected '{$expectedCode}'.");
            }

            $missing = isset($status['missing']) ? implode(',', $status['missing']) : '';
            $this->line(sprintf(
                "code=%s total=%d received=%d missing=[%s]",
                $status['code'] ?? '?',
                (int) ($status['total'] ?? 0),
                (int) ($status['received'] ?? 0),
                $missing
            ));
        }

        if (!$lastStatus || !$lastCode) {
            $this->error('No lines ingested.');
            return self::FAILURE;
        }

        if (!$this->assembler->isComplete($lastCode)) {
            $this->warn("Not complete for code '{$lastCode}'.");
            return self::SUCCESS; // success; just incomplete input
        }

        // Assemble (also caches the artifact inside the assembler)
        try {
            $payload = $this->assembler->artifact($lastCode);
        } catch (\Throwable $e) {
            $this->error('Assemble error: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Assembled payload for code '{$lastCode}'.");

        // Print decoded payload as JSON if requested
        if ($this->option('print')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }

        // Optionally write the cached artifact (serialized blob) to disk
        if ($out = $this->option('out')) {
            $artifact = $this->assembler->artifact($lastCode);
            if (!$artifact) {
                $this->warn('No artifact cached (unexpected).');
                return self::SUCCESS;
            }

            // New shape: ['mime' => 'application/json', 'body' => '...serialized...']
            $body = $artifact['body'] ?? '';
            $mime = $artifact['mime'] ?? 'text/plain';

            $dir = dirname($out);
            if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
                $this->error("Cannot create output directory: {$dir}");
                return self::FAILURE;
            }

            if (@file_put_contents($out, $body) === false) {
                $this->error("Failed writing artifact to: {$out}");
                return self::FAILURE;
            }

            $this->info("Wrote artifact to: {$out} ({$mime}, " . strlen($body) . " bytes)");
        }

        return self::SUCCESS;
    }
}
