<?php

namespace TruthElectionDb\Traits;

trait HandlesStdinInput
{
    /**
     * Read a single trimmed line from STDIN, with timeout fallback.
     */
    protected function readLineFromStdin(): ?string
    {
        $stdin = fopen('php://stdin', 'r');
        $read = [$stdin];
        $write = $except = null;

        // Wait for input (100ms timeout)
        $ready = stream_select($read, $write, $except, 0, 100000);

        if ($ready === false || $ready === 0) {
            return null;
        }

        $line = fgets($stdin);
        return $line !== false ? trim($line) : null;
    }

    /**
     * Read all non-empty lines from STDIN until EOF.
     */
    protected function readLinesFromStdin(): array
    {
        $lines = [];

        while (!feof(STDIN)) {
            $line = fgets(STDIN);
            if ($line === false) break;

            $line = trim($line);
            if (!empty($line)) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
