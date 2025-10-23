<?php

namespace LBHurtado\OMRAppreciation\Services;

class ImageAligner
{
    /**
     * Apply basic alignment/scaling based on detected fiducials
     * Note: For full perspective correction, external tools like ImageMagick or OpenCV are needed
     * This provides basic scaling/cropping alignment
     *
     * @param resource $image GD image resource
     * @param array $detectedFiducials Actual fiducial positions from scan
     * @param array $templateFiducials Expected fiducial positions from template
     * @return resource Aligned image
     */
    public function align($image, array $detectedFiducials, array $templateFiducials)
    {
        // Calculate scaling factors based on fiducial distances
        $scaleX = $this->calculateScaleX($detectedFiducials, $templateFiducials);
        $scaleY = $this->calculateScaleY($detectedFiducials, $templateFiducials);
        
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);
        
        $newWidth = (int) ($originalWidth * $scaleX);
        $newHeight = (int) ($originalHeight * $scaleY);
        
        // Create scaled image
        $scaled = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled(
            $scaled, $image,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );
        
        return $scaled;
    }
    
    /**
     * Calculate horizontal scaling factor
     */
    protected function calculateScaleX(array $detected, array $template): float
    {
        $detectedWidth = $detected['top_right']['center_x'] - $detected['top_left']['center_x'];
        $templateWidth = $template['top_right']['x'] - $template['top_left']['x'];
        
        return $templateWidth / $detectedWidth;
    }
    
    /**
     * Calculate vertical scaling factor
     */
    protected function calculateScaleY(array $detected, array $template): float
    {
        $detectedHeight = $detected['bottom_left']['center_y'] - $detected['top_left']['center_y'];
        $templateHeight = $template['bottom_left']['y'] - $template['top_left']['y'];
        
        return $templateHeight / $detectedHeight;
    }
    
    /**
     * Simple alignment without perspective correction
     * Just scales and crops to match template dimensions
     */
    public function simpleAlign($image, int $templateWidth, int $templateHeight)
    {
        $aligned = imagecreatetruecolor($templateWidth, $templateHeight);
        
        imagecopyresampled(
            $aligned, $image,
            0, 0, 0, 0,
            $templateWidth, $templateHeight,
            imagesx($image), imagesy($image)
        );
        
        return $aligned;
    }
}
