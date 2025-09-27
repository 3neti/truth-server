<?php

namespace TruthElectionDb\Console\Commands;

use TruthElection\Support\ParseCompactBallotFormat;
use TruthElectionDb\Actions\CastBallot;
use Illuminate\Support\Facades\File;
use Illuminate\Console\Command;

class CastBallotCommand extends Command
{
    protected $signature = 'election:cast-ballot
        {lines?* : One or more ballot lines in CODE|POS1:CANDA,CANDB;POS2:... format. If omitted, read from STDIN.}
        {--input= : Path to the ballot JSON file}
        {--json= : Raw ballot JSON string}';

    protected $description = 'Cast a ballot from JSON or compact format using the CastBallot action.';

    public function handle(): int
    {
        $data = $this->resolveInput();

        if (is_null($data)) {
            $this->error('❌ No valid input. Please use --json, --input, or compact lines.');
            return self::FAILURE;
        }

        try {
            $ballot = CastBallot::make()->run(
                ballotCode: $data['ballot_code'] ?? null,
                votes: collect($data['votes'] ?? [])
            );

            $this->info('✅ Ballot successfully cast:');
            $this->line("Ballot Code: {$ballot->code}");
            $this->line("Precinct: {$ballot->getPrecinctCode()}");
            $this->line("Votes: " . $ballot->votes->count());

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Error casting ballot: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function resolveInput(): ?array
    {
        if ($json = $this->option('json')) {
            return $this->parseJson($json);
        }

        if ($file = $this->option('input')) {
            return $this->parseFile($file);
        }

        return $this->parseCompactInput();
    }

    protected function parseJson(string $json): ?array
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('❌ Failed to parse JSON: ' . json_last_error_msg());
            return null;
        }

        return $data;
    }

    protected function parseFile(string $path): ?array
    {
        if (!File::exists($path)) {
            $this->error("❌ File not found: $path");
            return null;
        }

        $contents = File::get($path);
        return $this->parseJson($contents);
    }

    protected function parseCompactInput(): ?array
    {
        $lines = $this->argument('lines') ?? [];

        if (empty($lines)) {
            while (!feof(STDIN)) {
                $chunk = fgets(STDIN);
                if ($chunk === false) break;
                $lines[] = trim($chunk);
            }
        }

        $lines = array_filter($lines); // Remove empty

        if (empty($lines)) return null;

        // 💡 Refactor this to return an array of ballot data (for now, take the first)
        return json_decode(app(ParseCompactBallotFormat::class)->__invoke($lines[0], 'CURRIMAO-001'), true);
    }
}
