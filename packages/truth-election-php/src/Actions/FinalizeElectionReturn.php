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

        $ctx = new FinalizeErContext(
            precinct: $precinct,
            er: $er,
            disk: $disk,
            folder: "ER-{$er->code}/{$dir}",
            payload: $payload,
            maxChars: $maxChars,
            force: $force,
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
        );
    }
}
