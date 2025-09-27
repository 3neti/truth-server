<?php

namespace TruthQr\Contracts;

/**
 * Persistence API for TRUTH chunk ingestion & assembly.
 *
 * Implementations persist partial chunks keyed by a group "code"
 * (e.g., ER code), report progress, and optionally cache the final
 * assembled artifact (JSON/YAML string or rendered HTML/PDF).
 */
interface TruthStore
{
    /**
     * Initialize the record if it doesn't exist yet.
     * Idempotent: calling again should NOT reset existing chunks.
     *
     * @param string $code  group code (e.g., ER code)
     * @param int    $total expected number of chunks (N)
     * @param int    $ttl   time-to-live in seconds (0 = no expiry)
     */
    public function initIfMissing(string $code, int $total, int $ttl = 0): void;

    /**
     * Persist one chunk fragment for the given code.
     *
     * @param string $code
     * @param int    $index 1-based index i
     * @param int    $total expected total N (may be used on first write)
     * @param string $payloadFragment raw fragment (already envelope payload part)
     */
    public function putChunk(string $code, int $index, int $total, string $payloadFragment): void;

    /**
     * Retrieve all currently stored chunk fragments (index => fragment).
     *
     * @return array<int,string>
     */
    public function getChunks(string $code): array;

    /**
     * Return a progress snapshot.
     *
     * @return array{code:string,total:int,received:int,missing:int[]}
     */
    public function status(string $code): array;

    /**
     * Convenience: whether we have all expected chunks.
     */
    public function isComplete(string $code): bool;

    /**
     * setArtifact stores a serialized artifact for later streaming.
     *
     * @param string $code
     * @param string $content      Serialized payload (JSON/YAML)
     * @param string $contentType  MIME type (e.g., application/json)
     *
     * Implementations MUST persist with keys:
     *   - content_type
     *   - content
     */
    public function setArtifact(string $code, string $content, string $contentType): void;

    /**
     * @return array{content_type:string, content:string}|null
     */
    public function getArtifact(string $code): ?array;

    /**
     * Optional cleanup.
     */
    public function forget(string $code): void;
}
