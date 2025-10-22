<?php

namespace LBHurtado\OMRTemplate\Services;

use Picqer\Barcode\BarcodeGeneratorPNG;

class BarcodeGenerator
{
    /**
     * Generate a Code 128 barcode as base64 data URI
     */
    public function generateCode128(string $content, int $widthScale = 2, int $height = 40): string
    {
        try {
            $generator = new BarcodeGeneratorPNG;
            $barcode = $generator->getBarcode($content, $generator::TYPE_CODE_128, $widthScale, $height);
            
            return 'data:image/png;base64,' . base64_encode($barcode);
        } catch (\Exception $e) {
            // Return empty string if barcode generation fails
            return '';
        }
    }

    /**
     * Generate a Code 39 barcode as base64 data URI
     */
    public function generateCode39(string $content, int $widthScale = 2, int $height = 40): string
    {
        try {
            $generator = new BarcodeGeneratorPNG;
            $barcode = $generator->getBarcode($content, $generator::TYPE_CODE_39, $widthScale, $height);
            
            return 'data:image/png;base64,' . base64_encode($barcode);
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
            default => $this->generateCode128($content, $widthScale, $height),
        };
    }
}
