<?php

namespace TruthQr\Contracts;

interface TruthQrWriter
{
    /**
     * @param string[] $lines QR contents (one line per QR)
     * @return array<int,string> opaque results (data URIs, binary, file paths depending on impl)
     */
    public function write(array $lines): array;

    /** e.g. 'png', 'svg', 'eps', 'raw' */
    public function format(): string;
}
