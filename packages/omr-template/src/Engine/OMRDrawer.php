<?php

namespace LBHurtado\OMRTemplate\Engine;

use LBHurtado\OMRTemplate\Support\Measure;

class OMRDrawer
{
    protected $pdf;
    protected CoordinatesRegistry $registry;
    protected array $config;

    public function __construct($pdf, CoordinatesRegistry $registry, array $config)
    {
        $this->pdf = $pdf;
        $this->registry = $registry;
        $this->config = $config;
    }

    public function drawFiducials(): void
    {
        $fiducialConfig = $this->config['omr']['fiducials'] ?? [];
        
        if (!($fiducialConfig['enable'] ?? false)) {
            return;
        }

        $size = $fiducialConfig['size_mm'] ?? 5.0;
        $sizePoints = Measure::mmToPoints($size);
        $positions = $fiducialConfig['positions'] ?? ['tl', 'tr', 'bl', 'br'];
        $marginMm = $fiducialConfig['margin_mm'] ?? 5.0; // Configurable margin

        // Get page dimensions
        $pageWidth = $this->pdf->getPageWidth();
        $pageHeight = $this->pdf->getPageHeight();
        $margin = Measure::mmToPoints($marginMm);

        $coords = [
            'tl' => ['x' => $margin, 'y' => $margin],
            'tr' => ['x' => $pageWidth - $margin - $sizePoints, 'y' => $margin],
            'bl' => ['x' => $margin, 'y' => $pageHeight - $margin - $sizePoints],
            'br' => ['x' => $pageWidth - $margin - $sizePoints, 'y' => $pageHeight - $margin - $sizePoints],
        ];

        $this->pdf->SetFillColor(0, 0, 0);
        
        foreach ($positions as $pos) {
            if (isset($coords[$pos])) {
                $x = $coords[$pos]['x'];
                $y = $coords[$pos]['y'];
                
                $this->pdf->Rect($x, $y, $sizePoints, $sizePoints, 'F');
                
                $this->registry->register('fiducial', $pos, [
                    'x' => $x,
                    'y' => $y,
                    'width' => $sizePoints,
                    'height' => $sizePoints,
                ]);
            }
        }
    }

    public function drawTimingMarks(): void
    {
        $timingConfig = $this->config['omr']['timing_marks'] ?? [];
        
        if (!($timingConfig['enable'] ?? false)) {
            return;
        }

        $pitch = Measure::mmToPoints($timingConfig['pitch_mm'] ?? 5.0);
        $size = Measure::mmToPoints($timingConfig['size_mm'] ?? 1.5);
        $edges = $timingConfig['edges'] ?? ['left', 'bottom'];

        $pageWidth = $this->pdf->getPageWidth();
        $pageHeight = $this->pdf->getPageHeight();

        $this->pdf->SetFillColor(0, 0, 0);

        foreach ($edges as $edge) {
            $count = 0;
            switch ($edge) {
                case 'left':
                    for ($y = $pitch; $y < $pageHeight - $pitch; $y += $pitch) {
                        $this->pdf->Rect(0, $y, $size, $size, 'F');
                        $this->registry->register('timing_mark', "left_{$count}", [
                            'x' => 0,
                            'y' => $y,
                            'width' => $size,
                            'height' => $size,
                        ]);
                        $count++;
                    }
                    break;
                    
                case 'bottom':
                    for ($x = $pitch; $x < $pageWidth - $pitch; $x += $pitch) {
                        $this->pdf->Rect($x, $pageHeight - $size, $size, $size, 'F');
                        $this->registry->register('timing_mark', "bottom_{$count}", [
                            'x' => $x,
                            'y' => $pageHeight - $size,
                            'width' => $size,
                            'height' => $size,
                        ]);
                        $count++;
                    }
                    break;
            }
        }
    }

    public function drawBubble(float $x, float $y, string $id): void
    {
        $bubbleConfig = $this->config['omr']['bubble'] ?? [];
        $diameter = Measure::mmToPoints($bubbleConfig['diameter_mm'] ?? 4.0);
        $radius = $diameter / 2;

        $this->pdf->SetLineWidth($bubbleConfig['stroke'] ?? 0.2);
        $this->pdf->SetDrawColor(0, 0, 0);
        
        if ($bubbleConfig['fill'] ?? false) {
            $this->pdf->SetFillColor(255, 255, 255);
            $this->pdf->Circle($x + $radius, $y + $radius, $radius, 0, 360, 'FD');
        } else {
            $this->pdf->Circle($x + $radius, $y + $radius, $radius, 0, 360, 'D');
        }

        $this->registry->register('bubble', $id, [
            'x' => $x,
            'y' => $y,
            'center_x' => $x + $radius,
            'center_y' => $y + $radius,
            'radius' => $radius,
            'diameter' => $diameter,
        ]);
    }

    public function drawBarcode(string $data, float $x, float $y): void
    {
        $barcodeConfig = $this->config['omr']['barcode'] ?? [];
        
        if (!($barcodeConfig['enable'] ?? false)) {
            return;
        }

        $type = $barcodeConfig['type'] ?? 'PDF417';
        $height = Measure::mmToPoints($barcodeConfig['height_mm'] ?? 10.0);

        // Use write2DBarcode for 2D barcodes like PDF417, QR codes
        if (in_array($type, ['PDF417', 'QRCODE', 'DATAMATRIX'])) {
            $this->pdf->write2DBarcode($data, $type, $x, $y, 0, $height);
        } else {
            // Use write1DBarcode for 1D barcodes
            $this->pdf->write1DBarcode($data, $type, $x, $y, '', $height);
        }

        $this->registry->register('barcode', 'document_barcode', [
            'x' => $x,
            'y' => $y,
            'type' => $type,
            'data' => $data,
        ]);
    }
}
