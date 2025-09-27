<?php

namespace TruthElection\Actions;

use TruthElection\Support\ElectionStoreInterface;
use TruthElection\Support\PrecinctContext;
use TruthElection\Support\MappingContext;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Facades\Log;
use TruthElection\Data\BallotData;

class FinalizeBallot
{
    use AsAction;

    public function __construct(
        protected ElectionStoreInterface $store,
        protected PrecinctContext $precinctContext,
    ) {}

    public function handle(string $ballotCode): BallotData
    {
        Log::info("[FinalizeBallot] Resolving ballot marks for: {$ballotCode}");

        $mappingContext = new MappingContext($this->store);
        $resolved = $mappingContext->resolveBallot($ballotCode);

        Log::info("[FinalizeBallot] Submitting resolved ballot", [
            'ballot_code' => $resolved->code,
            'vote_count' => $resolved->votes->count(),
        ]);

        return SubmitBallot::run($resolved);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }


    //TODO: move this to truth-election-db (maybe change the name of the action and test it there)
    public function asController(ActionRequest $request): BallotData
    {
        $validated = $request->validated();

        return $this->handle($validated['code']);
    }
}
