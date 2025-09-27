<?php

namespace TruthElection\Actions;

use TruthElection\Support\PrecinctContext;
use Lorisleiva\Actions\Concerns\AsAction;
use TruthElection\Events\BallotSubmitted;
use Spatie\LaravelData\DataCollection;
use Illuminate\Support\Collection;
use TruthElection\Data\BallotData;
use TruthElection\Data\VoteData;

class SubmitBallot
{
    use AsAction;

    public function __construct(
        protected PrecinctContext $precinctContext
    ) {}

    public function handle(string|BallotData $ballotCode, ?Collection $votes = null): BallotData
    {
        $data = $ballotCode instanceof BallotData
            ? $this->handleBallotData($ballotCode)
            : $this->handleFromParts($ballotCode, $votes);

        BallotSubmitted::dispatch($data);

        return $data;
    }

    protected function handleFromParts(string $ballotCode, Collection $votes): BallotData
    {
        $ballot = new BallotData(
            code: $ballotCode,
            votes: new DataCollection(VoteData::class, $votes->all()),
        );

        return $this->handleBallotData($ballot);
    }

    protected function handleBallotData(BallotData $ballot): BallotData
    {
        $precinct = $this->precinctContext->getPrecinct();

        if (!$precinct) {
            throw new \RuntimeException("Precinct not found.");
        }

        $ballot->setPrecinctCode($precinct->code);

        $this->precinctContext->putBallot($ballot);

        return $ballot;
    }
}
