<?php

use TruthCodec\Transport\Base64UrlTransport;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Envelope\EnvelopeV1Url;
use TruthCodec\Contracts\Envelope;

return [
    // Which writer to bind by default: 'null' | 'bacon'
    'driver' => env('TRUTH_QR_DRIVER', 'null'), /** @deprecated */

    // Default output format for writers that support multiple formats.
    // bacon: 'svg' (no deps) or 'png' (requires GD/Imagick)
    'default_format' => env('TRUTH_QR_FORMAT', 'png'), /** @deprecated */

    // Bacon settings
    'bacon' => [
        'size'    => (int) env('TRUTH_QR_SIZE', 512),     // pixels
        'margin'  => (int) env('TRUTH_QR_MARGIN', 16),    // quiet zone
        'level'   => env('TRUTH_QR_ECLEVEL', 'M'),        // L | M | Q | H
    ], /** @deprecated */
    /*
    |--------------------------------------------------------------------------
    | Default QR size & margin
    |--------------------------------------------------------------------------
    */
    'size'   => 512,
    'margin' => 16,

    'store' => env('TRUTH_QR_STORE', 'array'), // 'array' | 'redis'

    'stores' => [
        'array' => [
            'ttl' => 0, // no expiry
        ],
        'redis' => [
            'connection' => env('TRUTH_QR_REDIS_CONNECTION', null), // null = default
            'key_prefix' => env('TRUTH_QR_REDIS_PREFIX', 'truth:qr:'),
            'ttl'        => env('TRUTH_QR_TTL', 86400),
        ],
    ],
    // Keep false so the package doesn’t auto-wire routes unless asked to
    'auto_routes' => false,

    // Used only when auto_routes=true
    'routes' => [
        'prefix' => 'truth',
        'middleware' => ['web'],
    ],

    /*
|--------------------------------------------------------------------------
| Core codec collaborators
|--------------------------------------------------------------------------
| You can point these at alternative implementations (FQCN strings).
| They MUST implement the corresponding TruthCodec\Contracts interfaces.
*/
    'serializer' => JsonSerializer::class,           // implements PayloadSerializer
    'transport'  => Base64UrlTransport::class,      // implements TransportCodec
    'envelope'   => app(Envelope::class)::class,           // implements TruthCodec\Contracts\Envelope

    /*
    |--------------------------------------------------------------------------
    | Default QR writer
    |--------------------------------------------------------------------------
    | Choose which writer to bind to TruthQr\Contracts\TruthQrWriter.
    | driver: 'bacon' | 'null'
    | format: 'svg' (default), 'png'
    */
    'writer' => [
        'driver' => env('TRUTH_QR_WRITER', 'bacon'),
        'format' => env('TRUTH_QR_FORMAT', 'svg'),

        // BaconQR defaults
        'bacon' => [
            'size'   => (int) env('TRUTH_QR_SIZE', 512),
            'margin' => (int) env('TRUTH_QR_MARGIN', 16),
        ],
        'endroid' => [
            'size'   => (int) env('TRUTH_QR_SIZE', 512),
            'margin' => (int) env('TRUTH_QR_MARGIN', 16),
            'writer_options' => [
                \Endroid\QrCode\Writer\SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Publish strategy defaults
    |--------------------------------------------------------------------------
    | How TruthQrPublisher splits payloads into chunks for envelopes/QRs.
    | strategy: 'count' (N equal-ish parts) or 'size' (max chars per part)
    */
    'publish' => [
        'strategy' => env('TRUTH_QR_PUBLISH_STRATEGY', 'count'), // 'count'|'size'
        'count'    => (int) env('TRUTH_QR_PUBLISH_COUNT', 3),
        'size'     => (int) env('TRUTH_QR_PUBLISH_SIZE', 800),
    ],
];
