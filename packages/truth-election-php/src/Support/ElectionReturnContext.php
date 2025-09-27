<?php

namespace TruthElection\Support;

use TruthElection\Data\ElectionReturnData;
use TruthElection\Data\ElectoralInspectorData;

class ElectionReturnContext
{
    protected ?ElectionReturnData $election_return = null;

    public function __construct(
        protected ElectionStoreInterface $store,
        ?string $electionReturnCode = null,
    ) {
        $this->election_return = $store->getElectionReturn($electionReturnCode);
    }

    public function getElectionReturn(): ?ElectionReturnData
    {
        return $this->election_return;
    }

    public function code(): ?string
    {
        return $this->election_return?->code;
    }

    // Optional helper
    public function __get(string $key)
    {
        return $this->election_return?->$key;
    }

    public function findInspector(string $id): ?ElectoralInspectorData
{
        return $this->store->findInspector($this->getElectionReturn(), $id);
    }

    public function replaceElectionReturn(ElectionReturnData $er): void
    {
        $this->store->replaceElectionReturn($this->election_return = $er);
    }
}
