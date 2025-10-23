<?php

namespace LBHurtado\OMRTemplate\Services;

use TCPDF;

class BarcodeGenerator
{
    /**
     * Generate a barcode using TCPDF and return as SVG string
     * TCPDF supports various 1D and 2D barcodes natively
     */
    public function generateBarcodeSvg(string $content, string $type = 'C128'): string
    {
        try {
            // Map common barcode type names to TCPDF types
            $tcpdfType = match (strtoupper($type)) {
                'C39', 'CODE39' => 'C39',
                'C128', 'CODE128' => 'C128',
                'PDF417' => 'PDF417',
                'QRCODE', 'QR' => 'QRCODE,H',
                'DATAMATRIX' => 'DATAMATRIX',
                default => 'C128',
            };

            // For 1D barcodes
            if (in_array(strtoupper($type), ['C39', 'CODE39', 'C128', 'CODE128'])) {
                $barcodeObj = new \TCPDFBarcode($content, $tcpdfType);
                return $barcodeObj->getBarcodeSVGcode(2, 40, 'black');
            }

            // For 2D barcodes
            $barcodeObj = new \TCPDF2DBarcode($content, $tcpdfType);
            return $barcodeObj->getBarcodeSVGcode(2, 2, 'black');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Generate a Code 128 barcode as SVG
     */
    public function generateCode128(string $content, int $widthScale = 2, int $height = 40): string
    {
        try {
            $barcodeObj = new \TCPDFBarcode($content, 'C128');
            return $barcodeObj->getBarcodeSVGcode($widthScale, $height, 'black');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Generate a Code 39 barcode as SVG
     */
    public function generateCode39(string $content, int $widthScale = 2, int $height = 40): string
    {
        try {
            $barcodeObj = new \TCPDFBarcode($content, 'C39');
            return $barcodeObj->getBarcodeSVGcode($widthScale, $height, 'black');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Generate a PDF417 2D barcode as SVG
     */
    public function generatePDF417(string $content, int $width = 2, int $height = 2): string
    {
        try {
            $barcodeObj = new \TCPDF2DBarcode($content, 'PDF417');
            return $barcodeObj->getBarcodeSVGcode($width, $height, 'black');
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

    /**
     * Generate barcode as PNG image data URL
     */
    public function generatePngDataUrl(string $content, string $type = 'C128', int $width = 2, int $height = 40): string
    {
        try {
            $barcodeObj = match (strtoupper($type)) {
                'PDF417', 'QRCODE' => new \TCPDF2DBarcode($content, strtoupper($type)),
                default => new \TCPDFBarcode($content, strtoupper($type)),
            };

            $png = $barcodeObj->getBarcodePngData($width, $height);
            return 'data:image/png;base64,' . base64_encode($png);
        } catch (\Exception $e) {
            return '';
        }
    }
}
