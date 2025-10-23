<?php

namespace LBHurtado\OMRTemplate\Renderers;

use LBHurtado\OMRTemplate\Contracts\SectionRenderer;
use LBHurtado\OMRTemplate\Engine\OMRDrawer;

class MatrixRenderer implements SectionRenderer
{
    protected OMRDrawer $omrDrawer;
    protected array $config;

    public function __construct(OMRDrawer $omrDrawer, array $config)
    {
        $this->omrDrawer = $omrDrawer;
        $this->config = $config;
    }

    public function render($pdf, array $section, array $context): float
    {
        // Stub implementation for matrix/grid questions
        // TODO: Implement full matrix rendering logic
        $startY = $context['currentY'];
        $marginLeft = $context['marginLeft'];
        
        $title = $section['title'] ?? 'Matrix Section';
        
        $fontConfig = $this->config['fonts']['header'];
        $pdf->SetFont($fontConfig['family'], $fontConfig['style'], $fontConfig['size']);
        $pdf->SetXY($marginLeft, $startY);
        $pdf->Cell(0, 8, $title . ' (Matrix - Not yet implemented)', 0, 1, 'L');
        
        return 15;
    }

    public function canRender(string $type): bool
    {
        return $type === 'matrix';
    }
}
