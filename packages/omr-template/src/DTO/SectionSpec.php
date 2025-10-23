<?php

namespace LBHurtado\OMRTemplate\DTO;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class SectionSpec extends Data
{
    public function __construct(
        public string $type,
        public string $code,
        public string $title,
        public ?string $layout = null,
        public ?int $maxSelections = null,
        public ?string $question = null,
        public ?array $scale = null,
        #[DataCollectionOf(ChoiceSpec::class)]
        public ?DataCollection $choices = null,
    ) {}
}
