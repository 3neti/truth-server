<?php

namespace TruthQrUi\Http\Controllers;

use Illuminate\Http\Response;
use ZipArchive;

final class StubDownloadController
{
    public function __invoke()
    {
        $tmp = tempnam(sys_get_temp_dir(), 'truth-qr-ui-stubs-');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE);

        $base = realpath(__DIR__ . '/../../stubs/inertia');
        $add = function (string $path) use ($zip, $base) {
            $abs = $base . '/' . $path;
            if (is_file($abs)) {
                $zip->addFile($abs, $path);
            }
        };

        $add('Pages/TruthQrUi/Playground.vue');
        $add('components/TruthQrForm.vue');
        $add('composables/useTruthQr.ts');

        $zip->close();

        return response()->streamDownload(function () use ($tmp) {
            readfile($tmp);
            @unlink($tmp);
        }, 'truth-qr-ui-stubs.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }
}
