<?php

use TruthElection\Pipes\RenderElectionReturnPayloadPdf;
use TruthElection\Pipes\GenerateElectionReturnQRCodes;
use TruthElection\Pipes\GenerateElectionReturnPayload;
use TruthElection\Pipes\PersistElectionReturnJson;
use TruthElection\Pipes\EncodeElectionReturnLines;
use TruthElection\Pipes\RenderElectionReturnPdf;

return [
    'finalize_election_return' => [
        'pipes' => [
            PersistElectionReturnJson::class,
            EncodeElectionReturnLines::class,
            GenerateElectionReturnQRCodes::class,
            GenerateElectionReturnPayload::class,
            RenderElectionReturnPdf::class,
            RenderElectionReturnPayloadPdf::class,
        ],
    ],
    'storage' => [
        'disk' => env('TRUTH_ELECTION_DISK', 'local'),
    ],
    'store' => \TruthElectionDb\Support\DatabaseElectionStore::class,
];
