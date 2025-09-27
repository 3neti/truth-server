<?php

return [
    'finalize_election_return' => [
        'pipes' => [
            // any custom pipe
        ],
    ],
//    'store' => \TruthElectionDb\Support\DatabaseElectionStore::class,
    'ballot' => [
        'expected_vote_segments' => 2, // or 3 if needed
    ],
];
