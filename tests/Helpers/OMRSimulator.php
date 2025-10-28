<?php

namespace Tests\Helpers;

use Imagick;
use ImagickDraw;
use ImagickPixel;

class OMRSimulator
{
    /**
     * Convert PDF to PNG for processing
     * 
     * @param string $pdfPath Path to PDF file
     * @param int $dpi Resolution in DPI (default: 300)
     * @return string Path to generated PNG
     */
    public static function pdfToPng(string $pdfPath, int $dpi = 300): string
    {
        $imagick = new Imagick();
        $imagick->setResolution($dpi, $dpi);
        $imagick->readImage($pdfPath);
        $imagick->setImageFormat('png');
        
        $pngPath = str_replace('.pdf', '.png', $pdfPath);
        // Force regeneration by deleting old file
        if (file_exists($pngPath)) {
            unlink($pngPath);
        }
        $imagick->writeImage($pngPath);
        $imagick->clear();
        $imagick->destroy();
        
        return $pngPath;
    }
    
    /**
     * Alias for pdfToPng with named parameters
     */
    public static function convertPdfToPng(string $pdfPath, int $dpi = 300): string
    {
        return self::pdfToPng($pdfPath, $dpi);
    }

    /**
     * Simulate filled bubbles on blank sheet
     * 
     * @param string $imagePath Path to blank PNG
     * @param array $bubblesToFill Bubble IDs to fill ['PRESIDENT_LD_001', ...]
     * @param array $coordinates Full coordinates JSON from CoordinatesRegistry
     * @param int $dpi DPI used for conversion (default: 300)
     * @param float $fillIntensity 0.0 (white) to 1.0 (black) - simulates mark darkness
     * @return string Path to filled image
     */
    public static function fillBubbles(
        string $imagePath, 
        array $bubblesToFill, 
        array $coordinates,
        int $dpi = 300,
        float $fillIntensity = 1.0
    ): string {
        $imagick = new Imagick($imagePath);
        $draw = new ImagickDraw();
        
        // Calculate gray level based on fill intensity
        // 1.0 = black (0,0,0), 0.5 = mid-gray, 0.0 = white
        $grayValue = (int)(255 * (1.0 - $fillIntensity));
        $draw->setFillColor(new ImagickPixel(sprintf('rgb(%d,%d,%d)', $grayValue, $grayValue, $grayValue)));
        
        // Convert mm to pixels: 1 inch = 25.4mm, so pixels = mm * (dpi / 25.4)
        $mmToPixels = $dpi / 25.4;
        
        foreach ($bubblesToFill as $bubbleId) {
            // Look for bubble in the zones structure
            $bubble = self::findBubbleInCoordinates($coordinates, $bubbleId);
            
            if (!$bubble) {
                error_log("Warning: Bubble '$bubbleId' not found in coordinates");
                continue;
            }
            
            $x = $bubble['x_mm'] * $mmToPixels;
            $y = $bubble['y_mm'] * $mmToPixels;
            $r = ($bubble['diameter_mm'] / 2) * $mmToPixels;
            
            // Draw filled circle (ellipse with equal width/height)
            $draw->ellipse($x, $y, $r, $r, 0, 360);
        }
        
        $imagick->drawImage($draw);
        
        $filledPath = str_replace('.png', '_filled.png', $imagePath);
        $imagick->writeImage($filledPath);
        $imagick->clear();
        $imagick->destroy();
        
        return $filledPath;
    }

    /**
     * Find candidate name from bubble ID using questionnaire data
     * 
     * @param string $bubbleId Bubble ID like 'PRESIDENT_LD_001'
     * @param array|null $questionnaireData Questionnaire JSON with positions and candidates
     * @return string|null Candidate name or null if not found
     */
    protected static function findCandidateName(string $bubbleId, ?array $questionnaireData): ?string
    {
        if (!$questionnaireData || !isset($questionnaireData['positions'])) {
            return null;
        }
        
        // Parse bubble ID: PRESIDENT_LD_001 -> position=PRESIDENT, code=LD_001
        $parts = explode('_', $bubbleId, 2);
        if (count($parts) < 2) {
            return null;
        }
        
        $positionCode = $parts[0];
        $candidateCode = $parts[1];
        
        // Find position in questionnaire data
        foreach ($questionnaireData['positions'] as $position) {
            if ($position['code'] === $positionCode) {
                // Find candidate in this position
                foreach ($position['candidates'] as $candidate) {
                    if ($candidate['code'] === $candidateCode) {
                        return $candidate['name'];
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Find bubble coordinates in the coordinates structure
     */
    protected static function findBubbleInCoordinates(array $coordinates, string $bubbleId): ?array
    {
        // Check for 'bubble' key (our actual format)
        if (isset($coordinates['bubble'][$bubbleId])) {
            $bubble = $coordinates['bubble'][$bubbleId];
            // Normalize to expected format
            return [
                'x_mm' => $bubble['center_x'],
                'y_mm' => $bubble['center_y'],
                'diameter_mm' => $bubble['diameter'],
            ];
        }
        
        // Check if coordinates has a flat 'bubbles' key (fallback)
        if (isset($coordinates['bubbles'][$bubbleId])) {
            return $coordinates['bubbles'][$bubbleId];
        }
        
        // Otherwise, search in zones (another fallback)
        if (isset($coordinates['zones'])) {
            foreach ($coordinates['zones'] as $zone) {
                if (isset($zone['bubbles'])) {
                    foreach ($zone['bubbles'] as $bubble) {
                        if ($bubble['id'] === $bubbleId) {
                            return $bubble;
                        }
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Create visual overlay showing detected marks with color coding
     * 
     * @param string $basePath Path to base image
     * @param array $detectedMarks Array of detected marks from appreciation
     * @param array $coordinates Coordinates JSON
     * @param array $options Display options (output_path, dpi, scenario, etc.)
     * @return string Path to overlay image
     */
    public static function createOverlay(
        string $basePath,
        array $detectedMarks,
        array $coordinates,
        array $options = []
    ): string {
        // Load config with options override
        $config = config('omr-template.overlay', []);
        
        $dpi = $options['dpi'] ?? 300;
        $scenario = $options['scenario'] ?? 'normal';
        $contestLimits = $options['contest_limits'] ?? [];
        $showUnfilled = $options['show_unfilled'] ?? false;
        $showLegend = $options['show_legend'] ?? ($config['legend']['enabled'] ?? true);
        $outputPath = $options['output_path'] ?? null;
        $questionnaireData = $options['questionnaire'] ?? null;
        
        // Detect overvotes if contest limits provided
        if (!empty($contestLimits)) {
            $detectedMarks = self::detectOvervotes($detectedMarks, $contestLimits);
        }
        $imagick = new Imagick($basePath);
        $draw = new ImagickDraw();
        
        $mmToPixels = $dpi / 25.4;
        $stats = ['valid' => 0, 'overvote' => 0, 'ambiguous' => 0, 'unfilled' => 0];
        
        foreach ($detectedMarks as $mark) {
            $bubbleId = $mark['bubble_id'] ?? $mark['id'] ?? null;
            if (!$bubbleId) continue;
            
            // Skip unfilled marks unless explicitly requested
            if (!($mark['filled'] ?? false) && !$showUnfilled) continue;
            
            $bubble = self::findBubbleInCoordinates($coordinates, $bubbleId);
            if (!$bubble) continue;
            
            $x = $bubble['x_mm'] * $mmToPixels;
            $y = $bubble['y_mm'] * $mmToPixels;
            $r = ($bubble['diameter_mm'] / 2) * $mmToPixels;
            
            // Determine mark color and style
            $style = self::getMarkStyle($mark);
            $stats[$style['category']]++;
            
            // Draw circle with color coding
            $radiusOffset = $config['circles']['radius_offset'] ?? 5;
            $draw->setStrokeColor(new ImagickPixel($style['color']));
            $draw->setStrokeWidth($style['thickness']);
            $draw->setFillOpacity(0);
            $draw->ellipse($x, $y, $r + $radiusOffset, $r + $radiusOffset, 0, 360);
            
            // Build annotation text horizontally: percentage | status | candidate name
            $fontPath = $config['font_path'] ?? '/System/Library/Fonts/Supplemental/Arial.ttf';
            if (file_exists($fontPath)) {
                $draw->setFont($fontPath);
            }
            
            $textParts = [];
            
            // Confidence percentage (size 12)
            if (isset($mark['confidence']) || isset($mark['fill_ratio'])) {
                $value = $mark['fill_ratio'] ?? $mark['confidence'];
                $textParts[] = sprintf('%.0f%%', $value * 100);
            }
            
            // Status label (if applicable, size 12)
            if (!empty($style['label'])) {
                $textParts[] = $style['label'];
            }
            
            // Candidate name for valid marks (size 14 - larger!)
            $candidateName = null;
            if ($questionnaireData && $style['category'] === 'valid') {
                $candidateName = self::findCandidateName($bubbleId, $questionnaireData);
                if ($candidateName) {
                    $textParts[] = $candidateName;
                }
            }
            
            // Draw all parts horizontally in one line
            if (!empty($textParts)) {
                $separator = $config['layout']['separator'] ?? ' | ';
                $text = implode($separator, $textParts);
                
                // Use font sizes from config
                $fontSize = $candidateName 
                    ? ($config['fonts']['valid_marks'] ?? 40)
                    : ($config['fonts']['other_marks'] ?? 35);
                $draw->setFontSize($fontSize);
                $draw->setFillColor(new ImagickPixel($style['color']));
                
                // Position from config
                $offsetX = $config['layout']['text_offset_x'] ?? 12;
                $offsetY = $config['layout']['text_offset_y'] ?? 5;
                $draw->annotation($x + $r + $offsetX, $y + $offsetY, $text);
            }
        }
        
        // Draw legend
        if ($showLegend) {
            self::drawLegend($draw, $imagick, $stats, $scenario);
        }
        
        $imagick->drawImage($draw);
        
        // Use custom output path if provided, otherwise auto-generate
        $overlayPath = $outputPath ?? str_replace('.png', '_overlay.png', $basePath);
        $imagick->writeImage($overlayPath);
        $imagick->clear();
        $imagick->destroy();
        
        return $overlayPath;
    }

    /**
     * Determine visual style for a mark based on its properties
     */
    protected static function getMarkStyle(array $mark): array
    {
        $config = config('omr-template.overlay', []);
        $colors = $config['colors'] ?? [];
        $circles = $config['circles'] ?? [];
        
        // Red for overvotes
        if ($mark['is_overvote'] ?? false) {
            return [
                'color' => $colors['overvote'] ?? 'red',
                'thickness' => $circles['overvote_thickness'] ?? 4,
                'category' => 'overvote',
                'label' => 'OVERVOTE',
            ];
        }
        
        // Orange/Yellow for ambiguous marks
        if (in_array('ambiguous', $mark['warnings'] ?? [])) {
            return [
                'color' => $colors['ambiguous'] ?? 'orange',
                'thickness' => $circles['other_thickness'] ?? 3,
                'category' => 'ambiguous',
                'label' => '⚠ AMBIGUOUS',
            ];
        }
        
        // Green for valid filled marks
        if (($mark['filled'] ?? false) && ($mark['fill_ratio'] ?? 0) >= 0.95) {
            return [
                'color' => $colors['valid'] ?? 'lime',
                'thickness' => $circles['valid_thickness'] ?? 4,
                'category' => 'valid',
                'label' => '',
            ];
        }
        
        // Yellow for filled but lower confidence
        if ($mark['filled'] ?? false) {
            return [
                'color' => $colors['low_confidence'] ?? 'yellow',
                'thickness' => $circles['other_thickness'] ?? 3,
                'category' => 'ambiguous',
                'label' => 'LOW CONF',
            ];
        }
        
        // Orange for marks that are faint but still visible
        // Marks with fill_ratio 0.16-0.45 (above noise floor ~0.14 but below threshold)
        // Background noise is typically 0.13-0.15, actual faint marks are 0.16+
        $fillRatio = $mark['fill_ratio'] ?? null;
        if ($fillRatio !== null && $fillRatio >= 0.16 && $fillRatio < 0.45) {
            return [
                'color' => $colors['faint'] ?? 'orange',
                'thickness' => $circles['unfilled_thickness'] ?? 2,
                'category' => 'ambiguous',
                'label' => 'TOO FAINT',
            ];
        }
        
        // Gray for unfilled (no mark detected)
        return [
            'color' => $colors['unfilled'] ?? 'gray',
            'thickness' => $circles['unfilled_thickness'] ?? 2,
            'category' => 'unfilled',
            'label' => '',
        ];
    }
    
    /**
     * Detect overvotes based on contest limits
     */
    protected static function detectOvervotes(array $marks, array $contestLimits): array
    {
        // Group marks by contest
        $grouped = [];
        foreach ($marks as $key => $mark) {
            $bubbleId = $mark['id'] ?? '';
            $parts = explode('_', $bubbleId);
            $contest = $parts[0] ?? '';
            
            if (!isset($grouped[$contest])) {
                $grouped[$contest] = [];
            }
            $grouped[$contest][] = $key;
        }
        
        // Check each contest for overvotes
        foreach ($grouped as $contest => $markKeys) {
            $limit = $contestLimits[$contest] ?? 1;
            $filledMarks = [];
            
            foreach ($markKeys as $key) {
                if ($marks[$key]['filled'] ?? false) {
                    $filledMarks[] = $key;
                }
            }
            
            // Mark all as overvotes if exceeds limit
            if (count($filledMarks) > $limit) {
                foreach ($filledMarks as $key) {
                    $marks[$key]['is_overvote'] = true;
                }
            }
        }
        
        return $marks;
    }
    
    /**
     * Draw legend box with color meanings and statistics
     */
    protected static function drawLegend(
        ImagickDraw $draw,
        Imagick $imagick,
        array $stats,
        string $scenario
    ): void {
        $config = config('omr-template.overlay.legend', []);
        $colors = config('omr-template.overlay.colors', []);
        $fonts = config('omr-template.overlay.fonts', []);
        
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();
        
        // Legend position from config
        $legendX = $width - ($config['margin_x'] ?? 280);
        $legendY = $config['margin_y'] ?? 20;
        $legendWidth = $config['width'] ?? 260;
        $legendHeight = $config['height'] ?? 140;
        
        // Draw semi-transparent background
        $bgColor = $config['background'] ?? 'rgba(255, 255, 255, 0.9)';
        $borderColor = $config['border_color'] ?? 'black';
        $borderWidth = $config['border_width'] ?? 2;
        
        $draw->setFillColor(new ImagickPixel($bgColor));
        $draw->setStrokeColor(new ImagickPixel($borderColor));
        $draw->setStrokeWidth($borderWidth);
        $draw->rectangle($legendX, $legendY, $legendX + $legendWidth, $legendY + $legendHeight);
        
        // Title
        $fontPath = config('omr-template.overlay.font_path', '/System/Library/Fonts/Supplemental/Arial.ttf');
        if (file_exists($fontPath)) {
            $draw->setFont($fontPath);
        }
        $draw->setFontSize($fonts['legend_title'] ?? 14);
        $draw->setFillColor(new ImagickPixel('black'));
        $draw->annotation($legendX + 10, $legendY + 25, 'Scenario: ' . ucfirst($scenario));
        
        // Color legend
        $draw->setFontSize($fonts['legend_text'] ?? 11);
        $yOffset = 50;
        
        $items = [
            ['color' => $colors['valid'] ?? 'lime', 'text' => "✓ Valid: {$stats['valid']}"],
            ['color' => $colors['overvote'] ?? 'red', 'text' => "✗ Overvote: {$stats['overvote']}"],
            ['color' => $colors['ambiguous'] ?? 'orange', 'text' => "⚠ Ambiguous: {$stats['ambiguous']}"],
            ['color' => $colors['unfilled'] ?? 'gray', 'text' => "○ Unfilled: {$stats['unfilled']}"],
        ];
        
        foreach ($items as $item) {
            // Draw color circle
            $draw->setFillColor(new ImagickPixel($item['color']));
            $draw->setStrokeColor(new ImagickPixel($item['color']));
            $draw->setStrokeWidth(2);
            $draw->ellipse($legendX + 20, $legendY + $yOffset, 8, 8, 0, 360);
            
            // Draw text
            $draw->setFillColor(new ImagickPixel('black'));
            $draw->annotation($legendX + 40, $legendY + $yOffset + 4, $item['text']);
            
            $yOffset += 20;
        }
    }
    
    /**
     * Add noise to simulate real-world scanning conditions
     * 
     * @param string $imagePath Path to image
     * @param int $noiseDots Number of random noise dots to add
     * @return string Path to noisy image
     */
    public static function addNoise(string $imagePath, int $noiseDots = 50): string
    {
        $imagick = new Imagick($imagePath);
        $draw = new ImagickDraw();
        
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();
        
        $draw->setFillColor(new ImagickPixel('black'));
        
        for ($i = 0; $i < $noiseDots; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $r = rand(1, 3); // Small dots
            
            $draw->ellipse($x, $y, $r, $r, 0, 360);
        }
        
        $imagick->drawImage($draw);
        
        $noisyPath = str_replace('.png', '_noisy.png', $imagePath);
        $imagick->writeImage($noisyPath);
        $imagick->clear();
        $imagick->destroy();
        
        return $noisyPath;
    }
}
