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
     * Create visual overlay showing detected marks
     * 
     * @param string $basePath Path to base image
     * @param array $detectedMarks Array of detected marks from appreciation
     * @param array $coordinates Coordinates JSON
     * @param int $dpi DPI used (default: 300)
     * @return string Path to overlay image
     */
    public static function createOverlay(
        string $basePath,
        array $detectedMarks,
        array $coordinates,
        int $dpi = 300
    ): string {
        $imagick = new Imagick($basePath);
        $draw = new ImagickDraw();
        
        // Green circles for detected marks
        $draw->setStrokeColor(new ImagickPixel('lime'));
        $draw->setStrokeWidth(3);
        $draw->setFillOpacity(0);
        
        $mmToPixels = $dpi / 25.4;
        
        foreach ($detectedMarks as $mark) {
            $bubbleId = $mark['bubble_id'] ?? $mark['id'] ?? null;
            if (!$bubbleId) continue;
            
            $bubble = self::findBubbleInCoordinates($coordinates, $bubbleId);
            if (!$bubble) continue;
            
            $x = $bubble['x_mm'] * $mmToPixels;
            $y = $bubble['y_mm'] * $mmToPixels;
            $r = ($bubble['diameter_mm'] / 2) * $mmToPixels;
            
            // Draw circle with some padding
            $draw->ellipse($x, $y, $r + 5, $r + 5, 0, 360);
            
            // Add confidence text if available
            if (isset($mark['confidence'])) {
                $confidence = round($mark['confidence'], 2);
                // Use system font path for macOS/Linux compatibility
                $fontPath = '/System/Library/Fonts/Supplemental/Arial.ttf';
                if (file_exists($fontPath)) {
                    $draw->setFont($fontPath);
                }
                $draw->setFontSize(12);
                $draw->setFillColor(new ImagickPixel('lime'));
                $draw->annotation($x + $r + 10, $y, sprintf('%.0f%%', $confidence * 100));
            }
        }
        
        $imagick->drawImage($draw);
        
        $overlayPath = str_replace('.png', '_overlay.png', $basePath);
        $imagick->writeImage($overlayPath);
        $imagick->clear();
        $imagick->destroy();
        
        return $overlayPath;
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
