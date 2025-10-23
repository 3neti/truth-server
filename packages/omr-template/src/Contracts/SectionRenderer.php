<?php

namespace LBHurtado\OMRTemplate\Contracts;

interface SectionRenderer
{
    /**
     * Render a section on the PDF.
     *
     * @param mixed $pdf The TCPDF instance
     * @param array $section The section specification
     * @param array $context The layout context
     * @return float The height consumed by the section
     */
    public function render($pdf, array $section, array $context): float;

    /**
     * Check if the renderer can handle the given section type.
     *
     * @param string $type The section type
     * @return bool
     */
    public function canRender(string $type): bool;
}
