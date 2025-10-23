<?php

namespace LBHurtado\OMRTemplate\Services;

use TCPDF;

class OMRTemplateGenerator
{
    public function generate(array $data): string
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins to 0
        $pdf->SetMargins(0, 0, 0, true);
        $pdf->SetAutoPageBreak(false, 0);
        
        $pdf->AddPage();

        // --- Fiducial Markers (Anchor Squares) ---
        $pdf->SetFillColor(0, 0, 0); // Black
        $pdf->Rect(10, 10, 10, 10, 'F');     // Top-left
        $pdf->Rect(190, 10, 10, 10, 'F');    // Top-right
        $pdf->Rect(10, 277, 10, 10, 'F');    // Bottom-left
        $pdf->Rect(190, 277, 10, 10, 'F');   // Bottom-right

        // --- Unique Document Identifier (PDF417 Barcode) ---
        $pdf->write2DBarcode($data['identifier'], 'PDF417', 10, 260, 80, 20);

        // --- OMR Bubbles ---
        if (isset($data['bubbles']) && is_array($data['bubbles'])) {
            $pdf->SetDrawColor(0, 0, 0); // Black outline
            $pdf->SetLineWidth(0.3);
            
            foreach ($data['bubbles'] as $bubble) {
                [$x, $y] = [$bubble['x'], $bubble['y']];
                $pdf->Circle($x, $y, 2.5, 0, 360, 'D'); // Hollow circle
            }
        }

        // --- Optional: Add labels/text ---
        if (isset($data['text_elements']) && is_array($data['text_elements'])) {
            foreach ($data['text_elements'] as $text) {
                $pdf->SetFont($text['font'] ?? 'helvetica', $text['style'] ?? '', $text['size'] ?? 10);
                $pdf->Text($text['x'], $text['y'], $text['content']);
            }
        }

        // Save to disk
        $directory = $this->getStoragePath("app/ballots");
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $path = $this->getStoragePath("app/ballots/{$data['identifier']}.pdf");
        $pdf->Output($path, 'F');

        return $path;
    }

    /**
     * Generate PDF with custom configuration
     */
    public function generateWithConfig(array $data, array $config = []): string
    {
        $orientation = $config['orientation'] ?? 'P';
        $unit = $config['unit'] ?? 'mm';
        $format = $config['format'] ?? 'A4';
        
        $pdf = new TCPDF($orientation, $unit, $format, true, 'UTF-8', false);

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $margins = $config['margins'] ?? [0, 0, 0];
        $pdf->SetMargins($margins[0], $margins[1], $margins[2], true);
        $pdf->SetAutoPageBreak($config['auto_page_break'] ?? false, 0);
        
        $pdf->AddPage();

        // Apply custom rendering logic from data
        $this->renderContent($pdf, $data, $config);

        // Save to disk
        $directory = $config['output_dir'] ?? $this->getStoragePath("app/ballots");
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $path = "{$directory}/{$data['identifier']}.pdf";
        $pdf->Output($path, 'F');

        return $path;
    }

    /**
     * Render content based on data configuration
     */
    protected function renderContent(TCPDF $pdf, array $data, array $config): void
    {
        // Fiducial markers
        if (isset($data['fiducials']) && is_array($data['fiducials'])) {
            $pdf->SetFillColor(0, 0, 0);
            foreach ($data['fiducials'] as $fiducial) {
                $pdf->Rect(
                    $fiducial['x'],
                    $fiducial['y'],
                    $fiducial['width'] ?? 10,
                    $fiducial['height'] ?? 10,
                    'F'
                );
            }
        }

        // Barcode
        if (isset($data['barcode'])) {
            $barcode = $data['barcode'];
            $pdf->write2DBarcode(
                $barcode['content'] ?? $data['identifier'],
                $barcode['type'] ?? 'PDF417',
                $barcode['x'] ?? 10,
                $barcode['y'] ?? 260,
                $barcode['width'] ?? 80,
                $barcode['height'] ?? 20
            );
        }

        // Bubbles
        if (isset($data['bubbles']) && is_array($data['bubbles'])) {
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth($config['bubble_line_width'] ?? 0.3);
            
            foreach ($data['bubbles'] as $bubble) {
                $pdf->Circle(
                    $bubble['x'],
                    $bubble['y'],
                    $bubble['radius'] ?? 2.5,
                    0,
                    360,
                    'D'
                );
            }
        }

        // Text elements
        if (isset($data['text_elements']) && is_array($data['text_elements'])) {
            foreach ($data['text_elements'] as $text) {
                $pdf->SetFont(
                    $text['font'] ?? 'helvetica',
                    $text['style'] ?? '',
                    $text['size'] ?? 10
                );
                $pdf->Text($text['x'], $text['y'], $text['content']);
            }
        }
    }

    /**
     * Get PDF output as string without saving
     */
    public function generatePdfOutput(array $data): string
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0, true);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        $this->renderContent($pdf, $data, []);

        return $pdf->Output('', 'S');
    }

    /**
     * Get storage path, works both in Laravel and standalone
     */
    protected function getStoragePath(string $path): string
    {
        // Try Laravel's storage_path() if available
        try {
            if (function_exists('app') && app()->bound('path.storage')) {
                return storage_path($path);
            }
        } catch (\Exception $e) {
            // Fall through to standalone mode
        }

        // Fallback to local directory for standalone usage
        $baseDir = __DIR__ . '/../../storage';
        return realpath($baseDir) ? realpath($baseDir) . '/' . ltrim($path, '/') : $baseDir . '/' . ltrim($path, '/');
    }
}
