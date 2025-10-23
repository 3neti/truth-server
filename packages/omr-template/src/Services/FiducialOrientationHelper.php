<?php

namespace LBHurtado\OMRTemplate\Services;

/**
 * FiducialOrientationHelper
 * 
 * Helps determine page orientation based on fiducial marker positions.
 * Provides utilities for OpenCV-based orientation detection.
 */
class FiducialOrientationHelper
{
    /**
     * Convert mm coordinates to pixels at given DPI
     * 
     * @param float $mm Millimeters
     * @param int $dpi Dots per inch (default 300)
     * @return int Pixels
     */
    public function mmToPixels(float $mm, int $dpi = 300): int
    {
        return (int)round($mm * ($dpi / 25.4));
    }

    /**
     * Convert pixels to mm at given DPI
     * 
     * @param int $pixels Pixels
     * @param int $dpi Dots per inch (default 300)
     * @return float Millimeters
     */
    public function pixelsToMm(int $pixels, int $dpi = 300): float
    {
        return $pixels * (25.4 / $dpi);
    }

    /**
     * Get expected pixel positions for fiducials from config layout
     * 
     * @param array $fiducials Fiducials in mm coordinates
     * @param int $dpi DPI for conversion
     * @return array Fiducials in pixel coordinates
     */
    public function getFiducialsInPixels(array $fiducials, int $dpi = 300): array
    {
        $result = [];
        
        foreach ($fiducials as $fiducial) {
            $result[] = [
                'x' => $this->mmToPixels($fiducial['x'], $dpi),
                'y' => $this->mmToPixels($fiducial['y'], $dpi),
                'width' => $this->mmToPixels($fiducial['width'] ?? 10, $dpi),
                'height' => $this->mmToPixels($fiducial['height'] ?? 10, $dpi),
                'position' => $fiducial['position'] ?? null,
            ];
        }
        
        return $result;
    }

    /**
     * Sort detected fiducials by position (top-left, top-right, bottom-left, bottom-right)
     * 
     * @param array $detectedPoints Array of [x, y] coordinates
     * @return array Sorted as ['top_left', 'top_right', 'bottom_left', 'bottom_right']
     */
    public function sortFiducialsByPosition(array $detectedPoints): array
    {
        if (count($detectedPoints) !== 4) {
            throw new \RuntimeException("Expected 4 fiducial markers, got " . count($detectedPoints));
        }

        // Sort by y coordinate first (top to bottom)
        usort($detectedPoints, function($a, $b) {
            return $a['y'] <=> $b['y'];
        });

        // Split into top and bottom pairs
        $topPair = array_slice($detectedPoints, 0, 2);
        $bottomPair = array_slice($detectedPoints, 2, 2);

        // Sort each pair by x coordinate (left to right)
        usort($topPair, function($a, $b) {
            return $a['x'] <=> $b['x'];
        });
        usort($bottomPair, function($a, $b) {
            return $a['x'] <=> $b['x'];
        });

        return [
            'top_left' => $topPair[0],
            'top_right' => $topPair[1],
            'bottom_left' => $bottomPair[0],
            'bottom_right' => $bottomPair[1],
        ];
    }

    /**
     * Determine orientation angle from fiducial positions
     * 
     * @param array $sortedFiducials Sorted fiducials from sortFiducialsByPosition()
     * @return int Rotation angle (0, 90, 180, 270)
     */
    public function determineOrientation(array $sortedFiducials): int
    {
        $tl = $sortedFiducials['top_left'];
        $tr = $sortedFiducials['top_right'];
        $bl = $sortedFiducials['bottom_left'];
        $br = $sortedFiducials['bottom_right'];

        // Calculate vector from top-left to top-right
        $dx = $tr['x'] - $tl['x'];
        $dy = $tr['y'] - $tl['y'];

        // Calculate angle in degrees
        $angle = atan2($dy, $dx) * (180 / M_PI);

        // Normalize to 0-360
        if ($angle < 0) {
            $angle += 360;
        }

        // Round to nearest 90 degrees
        if ($angle >= 315 || $angle < 45) {
            return 0;   // No rotation needed
        } elseif ($angle >= 45 && $angle < 135) {
            return 90;  // Rotated 90° clockwise
        } elseif ($angle >= 135 && $angle < 225) {
            return 180; // Rotated 180°
        } else {
            return 270; // Rotated 270° clockwise (or 90° counter-clockwise)
        }
    }

    /**
     * Calculate centroid of a set of points
     * 
     * @param array $points Array of ['x' => int, 'y' => int]
     * @return array Centroid ['x' => float, 'y' => float]
     */
    public function calculateCentroid(array $points): array
    {
        $sumX = 0;
        $sumY = 0;
        $count = count($points);

        foreach ($points as $point) {
            $sumX += $point['x'];
            $sumY += $point['y'];
        }

        return [
            'x' => $sumX / $count,
            'y' => $sumY / $count,
        ];
    }

    /**
     * Check if fiducial pattern is asymmetric (for orientation detection)
     * 
     * @param array $fiducials Fiducial markers
     * @return bool True if asymmetric pattern detected
     */
    public function isAsymmetricPattern(array $fiducials): bool
    {
        if (count($fiducials) !== 4) {
            return false;
        }

        // Extract x and y coordinates
        $xs = array_map(fn($f) => $f['x'], $fiducials);
        $ys = array_map(fn($f) => $f['y'], $fiducials);

        // Check if x coordinates have variation beyond symmetry
        $xUnique = array_unique($xs);
        $yUnique = array_unique($ys);

        // For asymmetric pattern, we expect more than 2 unique x or y values
        return count($xUnique) > 2 || count($yUnique) > 2;
    }

    /**
     * Generate Python OpenCV calibration data
     * 
     * @param array $fiducials Fiducial markers in mm
     * @param int $dpi DPI for conversion
     * @return array Python-compatible calibration data
     */
    public function generateCalibrationData(array $fiducials, int $dpi = 300): array
    {
        $pixelFiducials = $this->getFiducialsInPixels($fiducials, $dpi);

        $calibration = [
            'dpi' => $dpi,
            'conversion_factor' => $dpi / 25.4,
            'fiducials_mm' => [],
            'fiducials_px' => [],
        ];

        foreach ($fiducials as $fiducial) {
            $position = $fiducial['position'] ?? 'unknown';
            $calibration['fiducials_mm'][$position] = [
                'x' => $fiducial['x'],
                'y' => $fiducial['y'],
            ];
        }

        foreach ($pixelFiducials as $fiducial) {
            $position = $fiducial['position'] ?? 'unknown';
            $calibration['fiducials_px'][$position] = [
                'x' => $fiducial['x'],
                'y' => $fiducial['y'],
            ];
        }

        return $calibration;
    }

    /**
     * Export calibration data as JSON for Python
     * 
     * @param array $fiducials Fiducial markers
     * @param int $dpi DPI
     * @return string JSON string
     */
    public function exportCalibrationJson(array $fiducials, int $dpi = 300): string
    {
        $data = $this->generateCalibrationData($fiducials, $dpi);
        return json_encode($data, JSON_PRETTY_PRINT);
    }
}
