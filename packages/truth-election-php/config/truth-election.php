<?php

use TruthElection\Pipes\RenderElectionReturnPayloadPdf;
use TruthElection\Pipes\GenerateElectionReturnQRCodes;
use TruthElection\Pipes\GenerateElectionReturnPayload;
use TruthElection\Pipes\PersistElectionReturnJson;
use TruthElection\Pipes\EncodeElectionReturnLines;
use TruthElection\Pipes\RenderElectionReturnPdf;
use TruthElection\Pipes\EncodeERLines;
use TruthElection\Pipes\PersistERJson;

return [
    'finalize_election_return' => [
        'pipes' => [
            PersistElectionReturnJson::class,
            PersistERJson::class,
            EncodeElectionReturnLines::class,
            EncodeERLines::class,
            GenerateElectionReturnQRCodes::class,
            GenerateElectionReturnPayload::class,
            RenderElectionReturnPdf::class,
            RenderElectionReturnPayloadPdf::class,
        ],
    ],
    'storage' => [
        'disk' => env('TRUTH_ELECTION_DISK', 'local'),
    ],
//    'store' => \TruthElection\Support\InMemoryElectionStore::class,
];
