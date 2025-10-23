<?php

namespace LBHurtado\OMRTemplate\Support;

class Measure
{
    /**
     * Convert millimeters to points (used by TCPDF).
     *
     * @param float $mm
     * @return float
     */
    public static function mmToPoints(float $mm): float
    {
        return $mm * 2.83465;
    }

    /**
     * Convert points to millimeters.
     *
     * @param float $points
     * @return float
     */
    public static function pointsToMm(float $points): float
    {
        return $points / 2.83465;
    }

    /**
     * Convert millimeters to pixels at given DPI.
     *
     * @param float $mm
     * @param int $dpi
     * @return float
     */
    public static function mmToPixels(float $mm, int $dpi = 300): float
    {
        return $mm * ($dpi / 25.4);
    }

    /**
     * Convert pixels to millimeters at given DPI.
     *
     * @param float $pixels
     * @param int $dpi
     * @return float
     */
    public static function pixelsToMm(float $pixels, int $dpi = 300): float
    {
        return $pixels * (25.4 / $dpi);
    }

    /**
     * Convert inches to millimeters.
     *
     * @param float $inches
     * @return float
     */
    public static function inchesToMm(float $inches): float
    {
        return $inches * 25.4;
    }

    /**
     * Convert millimeters to inches.
     *
     * @param float $mm
     * @return float
     */
    public static function mmToInches(float $mm): float
    {
        return $mm / 25.4;
    }
}
