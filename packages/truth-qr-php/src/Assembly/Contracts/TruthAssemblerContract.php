<?php

namespace TruthQr\Assembly\Contracts;

/**
 * TruthAssemblerContract
 *
 * A small façade around ingesting TRUTH envelope lines and tracking assembly
 * status per code, exposing a ready-to-serve artifact once complete.
 */
interface TruthAssemblerContract
{
    /**
     * Ingest a new transport line (pipe or URL envelope).
     * Returns current status for the line’s code.
     *
     * @return array{
     *   code:string,
     *   total:int|null,
     *   received:int,
     *   missing:int[],
     *   complete:bool
     * }
     */
    public function ingestLine(string $line): array;

    /**
     * Get current status by code.
     *
     * @return array{
     *   code:string,
     *   total:int|null,
     *   received:int,
     *   missing:int[],
     *   complete:bool
     * }
     */
    public function status(string $code): array;

    /** True when all parts for $code are present. */
    public function isComplete(string $code): bool;

    /**
     * Return a ready artifact for $code.
     * Must only succeed when complete() is true.
     *
     * @return array{mime:string, body:string}
     */
    public function artifact(string $code): array;
}
