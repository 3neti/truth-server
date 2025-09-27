<?php

namespace TruthElection\Tests;

use TruthElection\Support\ElectionStoreInterface;

trait ResetsElectionStore
{
    protected function resetElectionStore(): void
    {
        app(ElectionStoreInterface::class)->reset();
    }
}
