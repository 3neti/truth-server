<?php

use TruthQr\Assembly\Contracts\TruthAssemblerContract;
use TruthQr\Tests\Support\FakeTruthAssembler;
use TruthQr\Console\TruthIngestFileCommand;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    // Bind fake to the contract
    $this->app->singleton(TruthAssemblerContract::class, fn () => new FakeTruthAssembler());

    // Make sure any previously-resolved command gets forgotten so it’s rebuilt with the new binding
    $this->app->forgetInstance(TruthIngestFileCommand::class);
});

it('ingests a file and writes artifact when complete (fake)', function () {
    $dir  = base_path('tests');
    @mkdir($dir, 0777, true);

    $path = $dir . '/tmp-lines.txt';
    file_put_contents($path, "ER|v1|XYZ|1/2|A\nER|v1|XYZ|2/2|B\n");

    $out  = $dir . '/artifact.json';
    @unlink($out);

    $exit = Artisan::call('truth:ingest-file', [
        'path'    => $path,
        '--print' => true,
        '--out'   => $out,
    ]);

    expect($exit)->toBe(0);
    expect(file_exists($out))->toBeTrue();

    $txt = file_get_contents($out);
    expect($txt)->toBe('{"ok":true,"code":"XYZ"}');
});
