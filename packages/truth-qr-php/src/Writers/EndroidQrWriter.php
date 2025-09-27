<?php

namespace TruthQr\Writers;

use TruthQr\Contracts\TruthQrWriter;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

final class EndroidQrWriter implements TruthQrWriter
{
    /**
     * @param string $fmt    'svg' (default) or 'png'
     * @param int    $size   image size in pixels
     * @param int    $margin quiet zone in pixels
     * @param array  $writerOptions options passed to the underlying Endroid writer
     *                              (e.g., for SVG: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true])
     */
    public function __construct(
        private readonly string $fmt = 'svg',
        private readonly int $size = 512,
        private readonly int $margin = 16,
        private readonly array $writerOptions = [],
    ) {}

    /** @param array<int|string,string> $lines */
    public function write(array $lines): array
    {
        $out = [];
        foreach ($lines as $k => $line) {
            $out[$k] = $this->renderOne($line);
        }
        return $out;
    }

    public function format(): string
    {
        return strtolower($this->fmt);
    }

    private function renderOne(string $data): string
    {
        $fmt = $this->format();
        $writer = $fmt === 'png' ? new PngWriter() : new SvgWriter();

        // For SVG we can avoid pixel rounding; for raster keep round-to-margin
        $rounding = $fmt === 'svg'
            ? RoundBlockSizeMode::None
            : RoundBlockSizeMode::Margin;

        $builder = new Builder(
            writer: $writer,
            writerOptions: $this->writerOptionsFor($fmt),
            validateResult: false,
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $this->size,
            margin: $this->margin,
            roundBlockSizeMode: $rounding,
        );

        $result = $builder->build();
        return $result->getString();
    }

    /** Add sane defaults per format; merge with user-supplied writer options. */
    private function writerOptionsFor(string $fmt): array
    {
        $defaults = [];

        // You can flip this to true if you prefer no XML declaration in tests
        if ($fmt === 'svg' && class_exists(SvgWriter::class)) {
            $defaults[SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION] = false;
        }

        return $defaults + $this->writerOptions;
    }
}
