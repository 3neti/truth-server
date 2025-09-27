<?php

namespace TruthElection\Actions;

use TruthElection\Support\ElectionStoreInterface;
use TruthElection\Support\PrecinctContext;
use TruthElection\Support\MappingContext;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\ActionRequest;
use TruthElection\Data\BallotData;

class ReadVote
{
    use AsAction;

    public function __construct(
        protected ElectionStoreInterface $store,
        protected PrecinctContext $precinctContext,
    ) {}

    public function handle(string $ballotCode, string $key): BallotData
    {
        // Validate the mark key exists using MappingContext
        $context = new MappingContext($this->store);

        // Will throw if the mark is invalid 🚨
        $context->getMark($key);

        // Add the mark after validation
        $this->store->addBallotMark($ballotCode, $key);

        // Resolve and return the ballot data
        return $context->resolveBallot($ballotCode);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'key' => ['required', 'string'],
        ];
    }

    //TODO: move this to truth-election-db (maybe change the name of the action and test it there)
    public function asController(ActionRequest $request): BallotData
    {
        $validated = $request->validated();

        return $this->handle($validated['code'], $validated['key']);
    }
}
