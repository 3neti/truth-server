<?php

namespace LBHurtado\OMRTemplate\DTO;

use Spatie\LaravelData\Data;

class DocumentSpec extends Data
{
    public function __construct(
        public string $title,
        public string $unique_id,
        public string $layout = '2-col',
        public ?string $locale = null,
    ) {}
}
