<?php

namespace LBHurtado\OMRTemplate\Renderers;

use LBHurtado\OMRTemplate\Contracts\SectionRenderer;
use LBHurtado\OMRTemplate\Engine\LayoutContext;
use LBHurtado\OMRTemplate\Engine\OMRDrawer;
use LBHurtado\OMRTemplate\Support\Measure;

class MultipleChoiceRenderer implements SectionRenderer
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
        $choices = $section['choices'] ?? [];
        $layout = $section['layout'] ?? '2-col';
        
        // Get layout configuration
        $layoutConfig = $this->config['layouts'][$layout] ?? $this->config['layouts']['2-col'];
        $numCols = $layoutConfig['cols'];
        $gutter = $layoutConfig['gutter'];
        $rowGap = $layoutConfig['row_gap'];
        
        // Render section title
        $fontConfig = $this->config['fonts']['header'];
        $pdf->SetFont($fontConfig['family'], $fontConfig['style'], $fontConfig['size']);
        $pdf->SetXY($marginLeft, $startY);
        $pdf->Cell(0, 8, $title, 0, 1, 'L');
        $currentY = $pdf->GetY() + 3;
        
        // Set body font for choices
        $fontConfig = $this->config['fonts']['body'];
        $pdf->SetFont($fontConfig['family'], $fontConfig['style'], $fontConfig['size']);
        
        // Calculate column width
        $colWidth = ($contentWidth - ($gutter * ($numCols - 1))) / $numCols;
        $bubbleDiameter = Measure::mmToPoints($this->config['omr']['bubble']['diameter_mm'] ?? 4.0);
        $labelGap = Measure::mmToPoints($this->config['omr']['bubble']['label_gap_mm'] ?? 2.0);
        
        // Render choices in columns
        $choicesPerCol = ceil(count($choices) / $numCols);
        $choiceIndex = 0;
        
        foreach ($choices as $choice) {
            $col = intval($choiceIndex / $choicesPerCol);
            $row = $choiceIndex % $choicesPerCol;
            
            $x = $marginLeft + ($col * ($colWidth + $gutter));
            $y = $currentY + ($row * ($bubbleDiameter + $rowGap + 2));
            
            // Draw bubble
            $bubbleId = "{$code}_{$choice['code']}";
            $this->omrDrawer->drawBubble($x, $y, $bubbleId);
            
            // Draw label
            $labelX = $x + $bubbleDiameter + $labelGap;
            $pdf->SetXY($labelX, $y);
            $pdf->Cell($colWidth - $bubbleDiameter - $labelGap, $bubbleDiameter, $choice['label'], 0, 0, 'L');
            
            $choiceIndex++;
        }
        
        // Calculate consumed height
        $rows = $choicesPerCol;
        $sectionSpacing = $this->config['section_spacing'] ?? 5;
        $choicesHeight = $rows * ($bubbleDiameter + $rowGap + 2);
        $consumedHeight = ($currentY - $startY) + $choicesHeight + $sectionSpacing;
        
        return $consumedHeight;
    }

    public function canRender(string $type): bool
    {
        return $type === 'multiple_choice';
    }
}
