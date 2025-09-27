<?php

namespace TruthElection\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\Channel;
use TruthElection\Data\BallotData;

class BallotSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public bool $afterCommit = true;

    public BallotData $ballot;

    public function __construct(BallotData $ballot)
    {
        $this->ballot = $ballot;
    }

    public function broadcastOn(): array
    {
        return [ new Channel("precinct.{$this->ballot->getPrecinctCode()}") ];
    }

    public function broadcastWith(): array
    {
        return [
            'ballot' => $this->ballot->toArray(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ballot.submitted';
    }
}
