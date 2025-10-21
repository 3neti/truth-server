<?php

namespace TruthElection\Data;

use Spatie\LaravelData\Data;

class FinalizeErContext extends Data
{
    public function __construct(
        public PrecinctData $precinct,
        public ElectionReturnData|null $er,
        public string $disk,
        public string $folder,
        public string $payload,
        public int $maxChars,
        public bool $force,
        public ?string $qrPersistedAbs = null,

        // QR encoding preferences
        public string $encodingStrategy = 'count',  // 'size' | 'count'
        public int $chunkCount = 4,                 // when strategy = 'count'
        public int $chunkSize = 1200,              // when strategy = 'size'
        public string $qrWriterFormat = 'svg',
        public int $qrWriterSize = 512,
        public int $qrWriterMargin = 16,
    ) {}

    /**
     * Get encoding options for EncodePayload based on the configured strategy.
     *
     * @return array{by: string, count?: int, size?: int}
     */
    public function getEncodeOptions(): array
    {
        return $this->encodingStrategy === 'count'
            ? ['by' => 'count', 'count' => $this->chunkCount]
            : ['by' => 'size', 'size' => $this->chunkSize];
    }

    public function getMinifiedElectionReturn(): ERData
    {
        return ERData::fromElectionReturnData($this->er);
    }
}
