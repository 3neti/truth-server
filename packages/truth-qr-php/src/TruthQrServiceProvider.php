<?php

namespace TruthQr;

use TruthCodec\Contracts\PayloadSerializer;
use TruthCodec\Contracts\TransportCodec;
use TruthQr\Assembly\Contracts\TruthAssemblerContract;
use Illuminate\Support\ServiceProvider;
use TruthCodec\Contracts\Envelope;
use TruthCodec\Envelope\EnvelopeV1Url;   // pulled from truth-codec-php
use TruthQr\Classify\Classify;
use TruthQr\Contracts\TruthQrWriter;
use TruthQr\Writers\NullQrWriter;
use TruthQr\Writers\{BaconQrWriter, EndroidQrWriter};
use TruthQr\Contracts\TruthStore;
use TruthQr\Stores\ArrayTruthStore;
use TruthQr\Stores\RedisTruthStore;
use TruthQr\Assembly\TruthAssembler;
use Illuminate\Support\Facades\Route;
use TruthQr\Support\RouteRegistrar;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Transport\Base64UrlTransport;
use TruthQr\TruthQrPublisher;
use TruthQr\Publishing\TruthQrPublisherFactory;

class TruthQrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge default config
        $this->mergeConfigFrom(__DIR__ . '/../config/truth-qr.php', 'truth-qr');

//        // Bind the Envelope used for URL generation (configurable)
//        $this->app->bind(Envelope::class, function ($app) {
//            // You can switch to EnvelopeV1Line if you prefer line style
//            // or read a custom class name from config later.
//            return new EnvelopeV1Url();
//        });

//        $this->app->bind(TruthQrWriter::class, function ($app) {
//            $driver = config('truth-qr.driver', 'bacon');
//            $fmt    = config('truth-qr.default_format', 'svg');
//
//            if ($driver === 'bacon') {
//                $cfg = config('truth-qr.bacon', []);
//                return new BaconQrWriter(
//                    fmt:    $fmt,
//                    size:   (int)($cfg['size']   ?? 512),
//                    margin: (int)($cfg['margin'] ?? 16)
//                );
//            }
//
//            // Fallback: null writer
//            return new NullQrWriter($fmt);
//        });

        // TruthStore binding (configurable)
        $this->app->bind(TruthStore::class, function ($app) {
            $driver = config('truth-qr.store', 'array');

            if ($driver === 'redis') {
                $cfg = config('truth-qr.stores.redis', []);
                return new RedisTruthStore(
                    keyPrefix: $cfg['key_prefix'] ?? 'truth:qr:',
                    defaultTtl: (int) ($cfg['ttl'] ?? 86400),
                    connection: $cfg['connection'] ?? null
                );
            }

            $cfg = config('truth-qr.stores.array', []);
            return new ArrayTruthStore(
                defaultTtl: (int) ($cfg['ttl'] ?? 0)
            );
        });

        $this->app->singleton(TruthAssemblerContract::class, function ($app) {
            return $app->make(TruthAssembler::class);
        });

        $this->app->bind(TruthAssembler::class, function ($app) {
            return new TruthAssembler(
                store: $app->make(\TruthQr\Contracts\TruthStore::class),
                envelope: $app->make(\TruthCodec\Contracts\Envelope::class),
                transport: $app->make(\TruthCodec\Contracts\TransportCodec::class),
                serializer: $app->make(\TruthCodec\Contracts\PayloadSerializer::class),
            );
        });

        // Bind TruthQrWriter via config
        $this->app->singleton(TruthQrWriter::class, function ($app) {
            $cfg    = (array) config('truth-qr.writer', []);
            $driver = strtolower((string) ($cfg['driver'] ?? 'bacon'));
            $format = strtolower((string) ($cfg['format'] ?? 'svg'));

            $bacon  = (array) ($cfg['bacon']   ?? []);
            $endrd  = (array) ($cfg['endroid'] ?? []);

            return match ($driver) {
                'null' => new NullQrWriter($format),

                'bacon' => (function () use ($format, $bacon) {
                    if (!in_array($format, ['svg','png','eps'], true)) {
                        throw new \InvalidArgumentException("BaconQrWriter does not support format '{$format}'.");
                    }
                    if (!class_exists(\BaconQrCode\Writer::class)) {
                        throw new \RuntimeException("bacon/qr-code not installed but 'bacon' writer selected.");
                    }

                    return new BaconQrWriter(
                        fmt:    $format,
                        size:   (int) ($bacon['size']   ?? 512),
                        margin: (int) ($bacon['margin'] ?? 16),
                    );
                })(),

                'endroid' => (function () use ($format, $endrd) {
                    if (!in_array($format, ['svg','png'], true)) {
                        throw new \InvalidArgumentException("EndroidQrWriter supports only 'svg' or 'png', got '{$format}'.");
                    }
                    if (!class_exists(\Endroid\QrCode\Builder\Builder::class)) {
                        throw new \RuntimeException("endroid/qr-code not installed but 'endroid' writer selected.");
                    }

                    // NEW: pass writer options through to Endroid (e.g., SVG flags)
                    $writerOptions = (array) ($endrd['writer_options'] ?? []);

                    return new EndroidQrWriter(
                        fmt:           $format,
                        size:          (int) ($endrd['size']   ?? 512),
                        margin:        (int) ($endrd['margin'] ?? 16),
                        writerOptions: $writerOptions,
                    );
                })(),

                default => throw new \InvalidArgumentException("Unknown TruthQr writer driver: {$driver}"),
            };
        });
//        $this->app->bind(TruthQrWriter::class, function ($app) {
//            $cfg = (array) config('truth-qr.writer', []);
//            $driver = $cfg['driver'] ?? 'bacon';
//            $format = $cfg['format'] ?? 'svg';
//
//            return match ($driver) {
//                'null'  => new NullQrWriter($format),
//                'bacon' => new BaconQrWriter(
//                    fmt: $format,
//                    size: (int) ($cfg['bacon']['size']   ?? 512),
//                    margin: (int) ($cfg['bacon']['margin'] ?? 16),
//                ),
//                'endroid' => new EndroidQrWriter(
//                    fmt: $format,
//                    size: (int) ($cfg['endroid']['size']   ?? 512),
//                    margin: (int) ($cfg['endroid']['margin'] ?? 16),
//                ),
//                default => throw new \InvalidArgumentException("Unknown TruthQr writer driver: {$driver}"),
//            };
//        });

        // Bind TruthQrPublisher using config-driven collaborators
        $this->app->singleton(TruthQrPublisher::class, function ($app) {
            $serializerFqcn = (string) config('truth-qr.serializer');
            $transportFqcn  = (string) config('truth-qr.transport');
            $envelopeFqcn   = (string) config('truth-qr.envelope');

            /** @var PayloadSerializer $serializer */
            $serializer = $app->make($serializerFqcn);
            /** @var TransportCodec $transport */
            $transport  = $app->make($transportFqcn);
            /** @var Envelope $envelope */
            $envelope   = $app->make($envelopeFqcn);

            return new TruthQrPublisher(
                serializer: $serializer,
                transport:  $transport,
                envelope:   $envelope,
            );
        });

        $this->app->singleton(TruthQrPublisherFactory::class, function ($app) {
            /** @var TruthQrPublisher $publisher */
            $publisher = $app->make(TruthQrPublisher::class);

            $pubCfg = (array) config('truth-qr.publish', []);
            $defaults = [
                'strategy' => $pubCfg['strategy'] ?? 'count', // 'count' | 'size'
                'count'    => (int) ($pubCfg['count'] ?? 3),
                'size'     => (int) ($pubCfg['size']  ?? 800),
            ];

            return new TruthQrPublisherFactory($publisher, $defaults);
        });

        $this->app->singleton(Classify::class, function ($app) {
            return new Classify(
                $app->make(TruthAssemblerContract::class)
            );
        });
    }

    public function boot(): void
    {
        // Allow publishing config
        $this->publishes([
            __DIR__ . '/../config/truth-qr.php' => config_path('truth-qr.php'),
        ], 'truth-qr-config');

        // Load package routes
//        $this->loadRoutesFrom(__DIR__ . '/../routes/truth-qr.php');

        // Optional route macro so host apps can do: Route::truthQr([...]);
        if (method_exists(Route::class, 'macro')) {
            Route::macro('truthQr', function (array $options = []) {
                RouteRegistrar::register($options);
            });
        }

        // (Optional) Auto-register if user opts in via config
        if (config('truth-qr.auto_routes', false)) {
            RouteRegistrar::register([
                'prefix' => config('truth-qr.routes.prefix', 'truth'),
                'middleware' => config('truth-qr.routes.middleware', ['web']),
            ]);
        }

        // Register CLI command(s)
        if ($this->app->runningInConsole()) {
            $this->commands([
                \TruthQr\Console\TruthIngestFileCommand::class,
            ]);
        }
    }
}
