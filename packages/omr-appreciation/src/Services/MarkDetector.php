<?php

namespace LBHurtado\OMRAppreciation\Services;

class MarkDetector
{
    protected float $fillThreshold;
    
    public function __construct(float $fillThreshold = 0.3)
    {
        $this->fillThreshold = $fillThreshold;
    }
    
    /**
     * Detect if a zone is marked/filled
     *
     * @param resource $image GD image resource (should be aligned/corrected)
     * @param array $zone Zone definition with x, y, width, height
     * @return array ['filled' => bool, 'confidence' => float, 'fill_ratio' => float]
     */
    public function detectMark($image, array $zone): array
    {
        $x = (int) $zone['x'];
        $y = (int) $zone['y'];
        $width = (int) $zone['width'];
        $height = (int) $zone['height'];
        
        // Extract the region of interest
        $roi = imagecreatetruecolor($width, $height);
        imagecopy($roi, $image, 0, 0, $x, $y, $width, $height);
        
        // Convert to grayscale
        imagefilter($roi, IMG_FILTER_GRAYSCALE);
        
        // Count dark pixels
        $totalPixels = $width * $height;
        $darkPixels = 0;
        
        for ($py = 0; $py < $height; $py++) {
            for ($px = 0; $px < $width; $px++) {
                $rgb = imagecolorat($roi, $px, $py);
                $colors = imagecolorsforindex($roi, $rgb);
                $brightness = ($colors['red'] + $colors['green'] + $colors['blue']) / 3;
                
                // Consider pixels with brightness < 127 as "dark" (marked)
                if ($brightness < 127) {
                    $darkPixels++;
                }
            }
        }
        
        imagedestroy($roi);
        
        $fillRatio = $darkPixels / $totalPixels;
        $filled = $fillRatio >= $this->fillThreshold;
        
        // Calculate confidence based on how far from threshold
        $confidence = $filled 
            ? min(1.0, ($fillRatio - $this->fillThreshold) / (1.0 - $this->fillThreshold))
            : min(1.0, ($this->fillThreshold - $fillRatio) / $this->fillThreshold);
        
        return [
            'filled' => $filled,
            'confidence' => round($confidence, 3),
            'fill_ratio' => round($fillRatio, 3),
        ];
    }
    
    /**
     * Detect marks in multiple zones
     *
     * @param resource $image
     * @param array $zones
     * @return array
     */
    public function detectMarks($image, array $zones): array
    {
        $results = [];
        
        foreach ($zones as $zone) {
            $detection = $this->detectMark($image, $zone);
            $results[] = array_merge($zone, $detection);
        }
        
        return $results;
    }
    
    /**
     * Set the fill threshold
     */
    public function setFillThreshold(float $threshold): void
    {
        $this->fillThreshold = $threshold;
    }
}
