<?php

namespace LBHurtado\OMRTemplate\DTO;

use Spatie\LaravelData\Data;

class ChoiceSpec extends Data
{
    public function __construct(
        public string $code,
        public string $label,
    ) {}
}
