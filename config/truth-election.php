<?php

use TruthElection\Pipes\RenderElectionReturnPayloadPdf;
use TruthElection\Pipes\GenerateElectionReturnQRCodes;
use TruthElection\Pipes\GenerateElectionReturnPayload;
use TruthElection\Pipes\PersistElectionReturnJson;
use TruthElection\Pipes\EncodeElectionReturnLines;
use TruthElection\Pipes\RenderElectionReturnPdf;
use TruthElection\Pipes\PersistERJson;

return [
    'finalize_election_return' => [
        'pipes' => [
            PersistElectionReturnJson::class,
            PersistERJson::class,
            EncodeElectionReturnLines::class,
            GenerateElectionReturnQRCodes::class,
            GenerateElectionReturnPayload::class,
            RenderElectionReturnPdf::class,
            RenderElectionReturnPayloadPdf::class,
        ],

        // QR encoding configuration
        'qr_encoding' => [
            'strategy' => env('TRUTH_QR_STRATEGY', 'count'),
            'chunk_count' => (int) env('TRUTH_QR_CHUNK_COUNT', 4),
            'chunk_size' => (int) env('TRUTH_QR_CHUNK_SIZE', 1200),
            'writer' => [
                'format' => env('TRUTH_QR_FORMAT', 'svg'),
                'size' => (int) env('TRUTH_QR_SIZE', 512),
                'margin' => (int) env('TRUTH_QR_MARGIN', 16),
            ],
        ],
    ],
    'storage' => [
        'disk' => env('TRUTH_ELECTION_DISK', 'local'),
    ],
    'store' => \TruthElectionDb\Support\DatabaseElectionStore::class,
];
