<?php

namespace LBHurtado\OMRAppreciation\Services;

class AppreciationService
{
    public function __construct(
        protected FiducialDetector $fiducialDetector,
        protected ImageAligner $imageAligner,
        protected MarkDetector $markDetector,
    ) {}
    
    /**
     * Appreciate (detect marks on) a scanned document
     *
     * @param string $imagePath Path to scanned image
     * @param array $templateData Template JSON data with zones and fiducials
     * @return array Appreciation results
     */
    public function appreciate(string $imagePath, array $templateData): array
    {
        // Load image
        $image = $this->loadImage($imagePath);
        
        if (!$image) {
            throw new \RuntimeException("Failed to load image: {$imagePath}");
        }
        
        try {
            // Detect fiducial markers
            $detectedFiducials = $this->fiducialDetector->detectFiducials($image);
            
            // Align image (basic scaling for now)
            $alignedImage = $this->alignImage($image, $detectedFiducials, $templateData);
            
            // Detect marks in zones
            $zones = $templateData['zones'] ?? [];
            $detections = $this->markDetector->detectMarks($alignedImage, $zones);
            
            // Build result
            $result = [
                'document_id' => $templateData['document_id'] ?? null,
                'template_id' => $templateData['template_id'] ?? null,
                'fiducials_detected' => $detectedFiducials,
                'marks' => $detections,
                'summary' => $this->buildSummary($detections),
            ];
            
            // Cleanup
            imagedestroy($image);
            imagedestroy($alignedImage);
            
            return $result;
            
        } catch (\Exception $e) {
            imagedestroy($image);
            throw $e;
        }
    }
    
    /**
     * Load image from file
     */
    protected function loadImage(string $path)
    {
        $imageInfo = getimagesize($path);
        
        if (!$imageInfo) {
            return false;
        }
        
        return match ($imageInfo[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_GIF => imagecreatefromgif($path),
            default => false,
        };
    }
    
    /**
     * Align image using detected fiducials
     */
    protected function alignImage($image, array $detectedFiducials, array $templateData)
    {
        $templateFiducials = $templateData['fiducials'] ?? null;
        
        if ($templateFiducials) {
            // Normalize fiducials to associative array if they're a list
            $normalizedFiducials = $this->normalizeFiducials($templateFiducials);
            return $this->imageAligner->align($image, $detectedFiducials, $normalizedFiducials);
        }
        
        // Fallback: simple scaling to template size
        $size = $templateData['size'] ?? 'A4';
        $dpi = $templateData['dpi'] ?? 300;
        
        [$width, $height] = $this->getTemplateDimensions($size, $dpi);
        
        return $this->imageAligner->simpleAlign($image, $width, $height);
    }
    
    /**
     * Normalize fiducials from array format to associative array
     */
    protected function normalizeFiducials(array $fiducials): array
    {
        // If already associative (has string keys), return as-is
        if (isset($fiducials['top_left'])) {
            return $fiducials;
        }
        
        // Convert from array format to associative
        $normalized = [];
        foreach ($fiducials as $fiducial) {
            $id = $fiducial['id'] ?? null;
            if ($id) {
                $normalized[$id] = $fiducial;
            }
        }
        
        return $normalized;
    }
    
    /**
     * Get template dimensions based on paper size and DPI
     */
    protected function getTemplateDimensions(string $size, int $dpi): array
    {
        // A4 dimensions in inches
        $dimensions = match (strtoupper($size)) {
            'A4' => [8.27, 11.69],
            'LETTER' => [8.5, 11],
            default => [8.27, 11.69],
        };
        
        return [
            (int) ($dimensions[0] * $dpi),
            (int) ($dimensions[1] * $dpi),
        ];
    }
    
    /**
     * Build summary of marked zones
     */
    protected function buildSummary(array $detections): array
    {
        $filled = array_filter($detections, fn($d) => $d['filled']);
        $unfilled = array_filter($detections, fn($d) => !$d['filled']);
        
        $avgConfidence = count($detections) > 0
            ? array_sum(array_column($detections, 'confidence')) / count($detections)
            : 0;
        
        return [
            'total_zones' => count($detections),
            'filled_count' => count($filled),
            'unfilled_count' => count($unfilled),
            'average_confidence' => round($avgConfidence, 3),
        ];
    }
}
