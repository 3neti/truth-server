<?php

namespace LBHurtado\OMRTemplate\Services;

class FiducialHelper
{
    /**
     * Page dimensions in pixels at 300 DPI
     */
    protected const PAGE_DIMENSIONS = [
        'A4' => ['width' => 2480, 'height' => 3508],
        'Letter' => ['width' => 2550, 'height' => 3300],
    ];

    /**
     * Default fiducial size in pixels (6mm at 300 DPI ≈ 71 pixels)
     */
    protected const FIDUCIAL_SIZE_PX = 71;

    /**
     * Default margin from edge in pixels (10mm at 300 DPI ≈ 118 pixels)
     */
    protected const MARGIN_PX = 118;

    /**
     * Generate default fiducial markers for a given page size and DPI
     */
    public function generateFiducials(string $pageSize = 'A4', int $dpi = 300): array
    {
        $dimensions = $this->getPageDimensions($pageSize, $dpi);
        $size = $this->scaleFiducialSize($dpi);
        $margin = $this->scaleMargin($dpi);

        return [
            [
                'id' => 'top_left',
                'x' => $margin,
                'y' => $margin,
                'width' => $size,
                'height' => $size,
            ],
            [
                'id' => 'top_right',
                'x' => $dimensions['width'] - $margin - $size,
                'y' => $margin,
                'width' => $size,
                'height' => $size,
            ],
            [
                'id' => 'bottom_left',
                'x' => $margin,
                'y' => $dimensions['height'] - $margin - $size,
                'width' => $size,
                'height' => $size,
            ],
            [
                'id' => 'bottom_right',
                'x' => $dimensions['width'] - $margin - $size,
                'y' => $dimensions['height'] - $margin - $size,
                'width' => $size,
                'height' => $size,
            ],
        ];
    }

    /**
     * Get page dimensions in pixels for a given DPI
     */
    protected function getPageDimensions(string $pageSize, int $dpi): array
    {
        if (! isset(self::PAGE_DIMENSIONS[$pageSize])) {
            throw new \InvalidArgumentException("Unsupported page size: {$pageSize}");
        }

        $baseDimensions = self::PAGE_DIMENSIONS[$pageSize];
        $scale = $dpi / 300;

        return [
            'width' => (int) round($baseDimensions['width'] * $scale),
            'height' => (int) round($baseDimensions['height'] * $scale),
        ];
    }

    /**
     * Scale fiducial size based on DPI
     */
    protected function scaleFiducialSize(int $dpi): int
    {
        return (int) round(self::FIDUCIAL_SIZE_PX * ($dpi / 300));
    }

    /**
     * Scale margin based on DPI
     */
    protected function scaleMargin(int $dpi): int
    {
        return (int) round(self::MARGIN_PX * ($dpi / 300));
    }

    /**
     * Convert pixel coordinates to millimeters
     */
    public function pixelsToMm(int $pixels, int $dpi = 300): float
    {
        return round($pixels / ($dpi / 25.4), 2);
    }

    /**
     * Convert millimeters to pixel coordinates
     */
    public function mmToPixels(float $mm, int $dpi = 300): int
    {
        return (int) round($mm * ($dpi / 25.4));
    }
}
