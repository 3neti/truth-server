<?php

namespace TruthRenderer\DTO;

class RenderResult
{
    public function __construct(
        public string $format,
        public string $content,
        public ?string $filename = null,
    ) {}

    public function toArray(): array
    {
        return [
            'format' => $this->format,
            'content' => $this->content,
            'filename' => $this->filename,
        ];
    }
}
