<?php

namespace LBHurtado\OMRTemplate\Support;

class TextWrap
{
    /**
     * Calculate the number of lines required for text at given width.
     *
     * @param mixed $pdf TCPDF instance
     * @param string $text
     * @param float $width
     * @return int
     */
    public static function calculateLines($pdf, string $text, float $width): int
    {
        $lines = $pdf->getNumLines($text, $width);
        return $lines;
    }

    /**
     * Calculate the height required for text at given width.
     *
     * @param mixed $pdf TCPDF instance
     * @param string $text
     * @param float $width
     * @param float $lineHeight
     * @return float
     */
    public static function calculateHeight($pdf, string $text, float $width, float $lineHeight = 5): float
    {
        $lines = self::calculateLines($pdf, $text, $width);
        return $lines * $lineHeight;
    }

    /**
     * Wrap text to fit within width, returning an array of lines.
     *
     * @param string $text
     * @param int $maxCharsPerLine
     * @return array
     */
    public static function wrapText(string $text, int $maxCharsPerLine): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
            
            if (strlen($testLine) <= $maxCharsPerLine) {
                $currentLine = $testLine;
            } else {
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return $lines;
    }

    /**
     * Truncate text with ellipsis if it exceeds max length.
     *
     * @param string $text
     * @param int $maxLength
     * @return string
     */
    public static function truncate(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength - 3) . '...';
    }
}
