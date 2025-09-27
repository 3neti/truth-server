<?php

namespace TruthQr\Writer;

use TruthCodec\Writer\Contracts\TruthWriter;
use TruthQr\Writer\Dto\{QrWriteResult};
use TruthQr\Writer\Dto\QrWriteOptions;

/** deprecated */
final class TruthWriterEndroid implements TruthWriter
{
    public function renderSet(array $lines, QrWriteOptions $options): QrWriteResult
    {
        // TODO: implement in Phase 3 (create PNG/SVG in-memory per line)
        return new QrWriteResult(chunks: []);
    }

    public function writePngSet(array $lines, QrWriteOptions $options, string $outputDir): QrWriteResult
    {
        // TODO: implement in Phase 3 (loop, write PNGs, return paths)
        return new QrWriteResult(chunks: []);
    }

    public function writePdf(array $lines, QrWriteOptions $options, string $pdfPath): QrWriteResult
    {
        // TODO: implement in Phase 3 (layout to PDF)
        return new QrWriteResult(chunks: [], pdfPath: $pdfPath);
    }
}
