<?php

namespace TruthElection\Data;

use Spatie\LaravelData\Data;

class SignPayloadData extends Data
{
    public function __construct(
        public string $id,         // Inspector ID
        public string $signature,  // Base64 or image path
    ) {}

    public static function fromQrString(string $payload): self
    {
        if (!str_starts_with($payload, 'BEI:')) {
            abort(400, 'Invalid QR code prefix.');
        }

        [$prefix, $id, $signature] = explode(':', $payload, 3);

        return new self(
            id: $id,
            signature: $signature,
        );
    }
}
