<?php

namespace LBHurtado\OMRAppreciation\Services;

class FiducialDetector
{
    /**
     * Detect fiducial markers (black squares) in an image
     *
     * @param resource $image GD image resource
     * @return array Array of fiducial positions: ['top_left' => [...], 'top_right' => [...], 'bottom_left' => [...], 'bottom_right' => [...]]
     */
    public function detectFiducials($image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Convert to grayscale and threshold
        $gray = imagecreatetruecolor($width, $height);
        imagecopy($gray, $image, 0, 0, 0, 0, $width, $height);
        imagefilter($gray, IMG_FILTER_GRAYSCALE);
        
        // Find potential fiducial markers
        $candidates = $this->findBlackSquares($gray, $width, $height);
        
        // Sort and identify corners
        return $this->identifyCorners($candidates, $width, $height);
    }
    
    /**
     * Find black square regions in the image
     */
    protected function findBlackSquares($image, int $width, int $height): array
    {
        $candidates = [];
        $scanStep = 10; // Scan every 10 pixels for performance
        $minSize = 15;  // Minimum fiducial size
        $maxSize = 100; // Maximum fiducial size
        
        for ($y = 0; $y < $height; $y += $scanStep) {
            for ($x = 0; $x < $width; $x += $scanStep) {
                $rgb = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $rgb);
                
                // Check if pixel is dark (potential fiducial)
                $brightness = ($colors['red'] + $colors['green'] + $colors['blue']) / 3;
                
                if ($brightness < 50) { // Very dark pixel
                    // Check if this forms a square region
                    $size = $this->measureSquareSize($image, $x, $y, $width, $height);
                    
                    if ($size >= $minSize && $size <= $maxSize) {
                        $candidates[] = [
                            'x' => $x,
                            'y' => $y,
                            'size' => $size,
                            'center_x' => $x + ($size / 2),
                            'center_y' => $y + ($size / 2),
                        ];
                    }
                }
            }
        }
        
        // Remove duplicates (same marker detected multiple times)
        return $this->removeDuplicates($candidates);
    }
    
    /**
     * Measure the size of a dark square region
     */
    protected function measureSquareSize($image, int $startX, int $startY, int $maxWidth, int $maxHeight): int
    {
        $size = 0;
        
        // Scan horizontally to find width
        for ($x = $startX; $x < min($startX + 100, $maxWidth); $x++) {
            $rgb = imagecolorat($image, $x, $startY);
            $colors = imagecolorsforindex($image, $rgb);
            $brightness = ($colors['red'] + $colors['green'] + $colors['blue']) / 3;
            
            if ($brightness >= 50) {
                break;
            }
            $size++;
        }
        
        return $size;
    }
    
    /**
     * Remove duplicate detections of the same fiducial
     */
    protected function removeDuplicates(array $candidates): array
    {
        $filtered = [];
        $threshold = 50; // Pixels
        
        foreach ($candidates as $candidate) {
            $isDuplicate = false;
            
            foreach ($filtered as $existing) {
                $distance = sqrt(
                    pow($candidate['center_x'] - $existing['center_x'], 2) +
                    pow($candidate['center_y'] - $existing['center_y'], 2)
                );
                
                if ($distance < $threshold) {
                    $isDuplicate = true;
                    break;
                }
            }
            
            if (!$isDuplicate) {
                $filtered[] = $candidate;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Identify which corner each fiducial belongs to
     */
    protected function identifyCorners(array $candidates, int $width, int $height): array
    {
        if (count($candidates) < 4) {
            throw new \RuntimeException('Could not detect all 4 fiducial markers. Found: ' . count($candidates));
        }
        
        // Sort candidates by distance from corners
        $corners = [
            'top_left' => null,
            'top_right' => null,
            'bottom_left' => null,
            'bottom_right' => null,
        ];
        
        $cornerPositions = [
            'top_left' => ['x' => 0, 'y' => 0],
            'top_right' => ['x' => $width, 'y' => 0],
            'bottom_left' => ['x' => 0, 'y' => $height],
            'bottom_right' => ['x' => $width, 'y' => $height],
        ];
        
        foreach ($cornerPositions as $corner => $idealPos) {
            $minDistance = PHP_FLOAT_MAX;
            $closestCandidate = null;
            
            foreach ($candidates as $candidate) {
                $distance = sqrt(
                    pow($candidate['center_x'] - $idealPos['x'], 2) +
                    pow($candidate['center_y'] - $idealPos['y'], 2)
                );
                
                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    $closestCandidate = $candidate;
                }
            }
            
            $corners[$corner] = $closestCandidate;
        }
        
        return $corners;
    }
}
