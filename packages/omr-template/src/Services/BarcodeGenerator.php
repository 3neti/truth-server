<?php

namespace LBHurtado\OMRTemplate\Services;

use Milon\Barcode\DNS1D;
use Milon\Barcode\DNS2D;

class BarcodeGenerator
{
    /**
     * Generate a Code 128 barcode as HTML (better for DOMPDF)
     */
    public function generateCode128(string $content, int $widthScale = 2, int $height = 40): string
    {
        try {
            $generator = new DNS1D();
            $generator->setStorPath(storage_path('app/barcodes'));
            return $generator->getBarcodeHTML($content, 'C128', $widthScale, $height, 'black', 0);
        } catch (\Exception $e) {
            // Return empty string if barcode generation fails
            return '';
        }
    }

    /**
     * Generate a Code 39 barcode as HTML (better for DOMPDF)
     */
    public function generateCode39(string $content, int $widthScale = 2, int $height = 40): string
    {
        try {
            $generator = new DNS1D();
            $generator->setStorPath(storage_path('app/barcodes'));
            return $generator->getBarcodeHTML($content, 'C39', $widthScale, $height, 'black', 0);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Generate a PDF417 2D barcode as HTML (better for DOMPDF)
     * For PDF417, width and height are per-cell dimensions, not total size
     */
    public function generatePDF417(string $content, int $width = 2, int $height = 2): string
    {
        try {
            $generator = new DNS2D();
            $generator->setStorPath(storage_path('app/barcodes'));
            return $generator->getBarcodeHTML($content, 'PDF417', $width, $height, 'black');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Generate barcode based on configured type
     */
    public function generate(
        string $content,
        ?string $type = null,
        ?int $widthScale = null,
        ?int $height = null
    ): string {
        $type = $type ?? config('omr-template.barcode.type', 'C128');
        $widthScale = $widthScale ?? config('omr-template.barcode.width_scale', 2);
        $height = $height ?? config('omr-template.barcode.height', 40);

        return match (strtoupper($type)) {
            'C39', 'CODE39' => $this->generateCode39($content, $widthScale, $height),
            'PDF417' => $this->generatePDF417($content, $widthScale, $height),
            default => $this->generateCode128($content, $widthScale, $height),
        };
    }
}
