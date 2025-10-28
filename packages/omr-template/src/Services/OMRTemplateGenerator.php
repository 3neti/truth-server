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
            $fiducialMode = $this->getConfigValue('fiducials.mode', 'black_square');
            
            if ($fiducialMode === 'aruco') {
                $this->renderArucoMarkers($pdf, $data['fiducials']);
            } else {
                // Default: black square markers
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

    /**
     * Get configuration value (works in Laravel and standalone)
     */
    protected function getConfigValue(string $key, $default = null)
    {
        // Try Laravel config (only if app is properly bootstrapped)
        if (function_exists('config')) {
            try {
                if (function_exists('app') && app()->bound('config')) {
                    return config("omr-template.{$key}", $default);
                }
            } catch (\Exception $e) {
                // Fall through to file-based config
            }
        }

        // Fallback: load config file directly with standalone helpers
        $config = $this->loadConfigFile();
        if ($config) {
            $keys = explode('.', $key);
            $value = $config;
            foreach ($keys as $k) {
                if (isset($value[$k])) {
                    $value = $value[$k];
                } else {
                    return $default;
                }
            }
            return $value;
        }

        return $default;
    }

    /**
     * Load config file with standalone helper functions
     */
    protected function loadConfigFile(): ?array
    {
        $configPath = __DIR__ . '/../../config/omr-template.php';
        if (!file_exists($configPath)) {
            return null;
        }

        // Read config file content and replace Laravel helper calls
        $configContent = file_get_contents($configPath);
        
        // Replace resource_path() and storage_path() with hardcoded paths
        $resourcePath = __DIR__ . '/../../resources';
        $storagePath = __DIR__ . '/../../storage';
        
        // Replace function calls with actual paths
        $configContent = preg_replace(
            '/resource_path\(([^)]+)\)/',
            "'" . $resourcePath . "' . $1",
            $configContent
        );
        $configContent = preg_replace(
            '/storage_path\(([^)]+)\)/',
            "'" . $storagePath . "' . $1",
            $configContent
        );
        $configContent = preg_replace(
            '/env\([^)]+,\s*([^)]+)\)/',
            '$1',
            $configContent
        );
        
        try {
            // Evaluate the modified config
            $config = eval('?>' . $configContent);
            return is_array($config) ? $config : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Render ArUco markers by embedding PNG images
     * 
     * @param TCPDF $pdf PDF instance
     * @param array $fiducials Array of fiducial marker positions
     */
    protected function renderArucoMarkers(TCPDF $pdf, array $fiducials): void
    {
        $arucoConfig = $this->getConfigValue('fiducials.aruco', []);
        $cornerIds = $arucoConfig['corner_ids'] ?? [101, 102, 103, 104];
        $resourcePath = $arucoConfig['marker_resource_path'] ?? 'fiducials/aruco';
        
        // Map positions to marker IDs
        $positionToId = [
            'top_left' => $cornerIds[0],
            'top_right' => $cornerIds[1],
            'bottom_right' => $cornerIds[2],
            'bottom_left' => $cornerIds[3],
        ];
        
        foreach ($fiducials as $fiducial) {
            $position = $fiducial['position'] ?? null;
            if (!$position || !isset($positionToId[$position])) {
                continue;
            }
            
            $markerId = $positionToId[$position];
            $markerPath = $this->getResourcePath("{$resourcePath}/marker_{$markerId}.png");
            
            if (!file_exists($markerPath)) {
                // Fallback to black square if ArUco marker not found
                $pdf->SetFillColor(0, 0, 0);
                $pdf->Rect(
                    $fiducial['x'],
                    $fiducial['y'],
                    $fiducial['width'] ?? 10,
                    $fiducial['height'] ?? 10,
                    'F'
                );
                continue;
            }
            
            // Embed ArUco marker PNG
            $pdf->Image(
                $markerPath,
                $fiducial['x'],
                $fiducial['y'],
                $fiducial['width'] ?? 10,
                $fiducial['height'] ?? 10,
                'PNG'
            );
        }
    }
    
    /**
     * Get resource path (works in Laravel and standalone)
     */
    protected function getResourcePath(string $path): string
    {
        // Try Laravel's resource_path() if available
        if (function_exists('resource_path')) {
            try {
                return resource_path($path);
            } catch (\Exception $e) {
                // Fall through
            }
        }
        
        // Fallback for standalone
        $baseDir = __DIR__ . '/../../resources';
        return $baseDir . '/' . ltrim($path, '/');
    }
    
    /**
     * Get fiducials for a specific layout
     * 
     * @param string $layout Layout name ('default', 'asymmetrical_right', 'asymmetrical_diagonal')
     * @return array Array of fiducial markers with positions
     */
    public function getFiducialsForLayout(string $layout = 'default'): array
    {
        $fiducials = $this->getConfigValue("fiducials.{$layout}");
        $markerSize = $this->getConfigValue('marker_size', 10);

        if (!$fiducials) {
            // Return default fiducials
            return [
                ['x' => 10, 'y' => 10, 'width' => $markerSize, 'height' => $markerSize, 'position' => 'top_left'],
                ['x' => 190, 'y' => 10, 'width' => $markerSize, 'height' => $markerSize, 'position' => 'top_right'],
                ['x' => 10, 'y' => 277, 'width' => $markerSize, 'height' => $markerSize, 'position' => 'bottom_left'],
                ['x' => 190, 'y' => 277, 'width' => $markerSize, 'height' => $markerSize, 'position' => 'bottom_right'],
            ];
        }

        // Convert config format to array format
        $result = [];
        foreach ($fiducials as $position => $coords) {
            $result[] = [
                'x' => $coords['x'],
                'y' => $coords['y'],
                'width' => $markerSize,
                'height' => $markerSize,
                'position' => $position,
            ];
        }

        return $result;
    }

    /**
     * Generate PDF with specific fiducial layout
     * 
     * @param array $data PDF data
     * @param string $fiducialLayout Fiducial layout name
     * @param array $config Additional config
     * @return string Path to generated PDF
     */
    public function generateWithFiducialLayout(array $data, string $fiducialLayout = 'default', array $config = []): string
    {
        // Get fiducials for the specified layout
        $fiducials = $this->getFiducialsForLayout($fiducialLayout);
        
        // Merge fiducials into data
        $data['fiducials'] = $fiducials;
        
        return $this->generateWithConfig($data, $config);
    }
}
