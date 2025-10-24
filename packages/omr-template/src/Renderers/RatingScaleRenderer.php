<?php

namespace LBHurtado\OMRTemplate\Renderers;

use LBHurtado\OMRTemplate\Contracts\SectionRenderer;
use LBHurtado\OMRTemplate\Engine\OMRDrawer;
use LBHurtado\OMRTemplate\Support\Measure;

class RatingScaleRenderer implements SectionRenderer
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
        $startY = $context['currentY'];
        $marginLeft = $context['marginLeft'];
        $contentWidth = $context['contentWidth'];
        
        // Extract section data
        $title = $section['title'] ?? 'Untitled Section';
        $code = $section['code'] ?? 'UNKNOWN';
        $question = $section['question'] ?? '';
        $scale = $section['scale'] ?? [1, 2, 3, 4, 5];
        
        // Render section title
        $fontConfig = $this->config['fonts']['header'];
        $pdf->SetFont($fontConfig['family'], $fontConfig['style'], $fontConfig['size']);
        $pdf->SetXY($marginLeft, $startY);
        $pdf->Cell(0, 8, $title, 0, 1, 'L');
        $currentY = $pdf->GetY() + 2;
        
        // Render question
        if (!empty($question)) {
            $fontConfig = $this->config['fonts']['body'];
            $pdf->SetFont($fontConfig['family'], $fontConfig['style'], $fontConfig['size']);
            $pdf->SetXY($marginLeft, $currentY);
            $pdf->Cell(0, 6, $question, 0, 1, 'L');
            $currentY = $pdf->GetY() + 3;
        }
        
        // Render scale bubbles horizontally
        $bubbleDiameter = Measure::mmToPoints($this->config['omr']['bubble']['diameter_mm'] ?? 4.0);
        $spacing = $bubbleDiameter + 10;
        
        $fontConfig = $this->config['fonts']['small'];
        $pdf->SetFont($fontConfig['family'], $fontConfig['style'], $fontConfig['size']);
        
        $x = $marginLeft;
        foreach ($scale as $value) {
            $bubbleId = "{$code}_{$value}";
            $this->omrDrawer->drawBubble($x, $currentY, $bubbleId);
            
            // Draw scale value below bubble
            $pdf->SetXY($x, $currentY + $bubbleDiameter + 1);
            $pdf->Cell($bubbleDiameter, 4, (string)$value, 0, 0, 'C');
            
            $x += $spacing;
        }
        
        $sectionSpacing = $this->config['section_spacing'] ?? 10;
        $consumedHeight = ($currentY - $startY) + $bubbleDiameter + 5 + $sectionSpacing;
        
        return $consumedHeight;
    }

    public function canRender(string $type): bool
    {
        return $type === 'rating_scale';
    }
}
