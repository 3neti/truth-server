<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use TruthRenderer\Engine\HandlebarsEngine;
use TruthRenderer\Http\Controllers\TruthTemplateUploadController;
use TruthRenderer\Template\TemplateRegistry;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Storage::fake('local');
    File::deleteDirectory(storage_path('app/truth-templates')); // ensure clean state
    Config::set('truth-renderer.upload.default_namespace', 'core');
    Config::set('truth-renderer.paths', [
        'core' => storage_path('app/truth-templates'),
    ]);
    Route::middleware('api')->group(function () {
        Route::post('/truth/templates/upload', [TruthTemplateUploadController::class, 'upload'])->name('truth-template.upload');
    });
});

test('uploads basic template manually', function () {
    $slug = 'sample/basic';
    $template = <<<HBS
        Hello, {{name}}!
    HBS;

    $schema = json_encode(['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]);

    $response = $this->postJson(route('truth-template.upload'), [
        'slug' => $slug,
        'template' => $template,
        'schema' => $schema,
        'dryRun' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('templateName', 'core:sample/basic/template');

    $basePath = storage_path("app/truth-templates/{$slug}");
    expect(file_get_contents("{$basePath}/template.hbs"))->toContain('{{name}}');
    expect(file_get_contents("{$basePath}/schema.json"))->toContain('"type"');
});

test('fails if slug is missing', function () {
    $response = $this->postJson(route('truth-template.upload'), [
        'template' => 'Hello {{name}}',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error', 'Missing or invalid slug');
});

test('rejects invalid JSON schema', function () {
    $response = $this->postJson(route('truth-template.upload'), [
        'slug' => 'invalid/schema',
        'template' => 'Hi!',
        'schema' => '{ bad json }',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'Provided schema is not valid JSON');
});

test('can overwrite existing template when flag is true', function () {
    $slug = 'overwrite/test';
    $basePath = storage_path("app/truth-templates/{$slug}");
    File::makeDirectory($basePath, 0775, true);
    File::put("{$basePath}/template.hbs", 'Old content');

    $response = $this->postJson(route('truth-template.upload'), [
        'slug' => $slug,
        'template' => 'New content {{hello}}',
        'overwrite' => true,
        'dryRun' => false,
    ]);

    $response->assertCreated();
    expect(file_get_contents("{$basePath}/template.hbs"))->toContain('{{hello}}');
});

test('rejects upload when target exists and overwrite is false', function () {
    $slug = 'existing/template';
    $basePath = storage_path("app/truth-templates/{$slug}");
    File::makeDirectory($basePath, 0775, true);
    File::put("{$basePath}/template.hbs", 'Old content');

    $response = $this->postJson(route('truth-template.upload'), [
        'slug' => $slug,
        'template' => 'New content',
        'overwrite' => false,
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error', "Target exists: core:{$slug}. Use overwrite=true to replace.");
});

test('uploads via ZIP archive', function () {
    $slug = 'zip/test';

    // Create a fake ZIP file with template.hbs and schema.json
    $tmpDir = sys_get_temp_dir() . '/test_zip_' . Str::random(5);
    File::makeDirectory($tmpDir, 0775, true);
    File::put("{$tmpDir}/template.hbs", 'Zip Template {{hello}}');
    File::put("{$tmpDir}/schema.json", json_encode(['type' => 'object']));

    $zipPath = "{$tmpDir}.zip";
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFile("{$tmpDir}/template.hbs", 'template.hbs');
    $zip->addFile("{$tmpDir}/schema.json", 'schema.json');
    $zip->close();

    $uploaded = new UploadedFile($zipPath, 'template_bundle.zip', null, null, true);

    $response = $this->postJson(route('truth-template.upload'), [
        'slug' => $slug,
        'zip' => $uploaded,
        'overwrite' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('templateName', 'core:zip/test/template');
});

test('fails dry-run compilation if template has invalid syntax', function () {
    $response = $this->postJson(route('truth-template.upload'), [
        'slug' => 'compile/fail',
        'template' => '{{#if}} Invalid block {{/if}}',
        'dryRun' => true,
    ]);

    $response->assertStatus(422)
        ->assertJson(fn($json) =>
        $json->has('error')
            ->where('error', fn($err) => str($err)->startsWith('Dry-run compile failed'))
        );
});

test('uploads ZIP archive with partials', function () {
    $slug = 'zip/with-partials';

    $tmpDir = sys_get_temp_dir() . '/test_zip_' . Str::random(5);
    File::makeDirectory("{$tmpDir}/partials", 0775, true);
    File::put("{$tmpDir}/template.hbs", 'Main {{> header}}');
    File::put("{$tmpDir}/partials/header.hbs", '<h1>Hello</h1>');

    $zipPath = "{$tmpDir}.zip";
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFile("{$tmpDir}/template.hbs", 'template.hbs');
    $zip->addFile("{$tmpDir}/partials/header.hbs", 'partials/header.hbs');
    $zip->close();

    $uploaded = new UploadedFile($zipPath, 'bundle_with_partials.zip', null, null, true);

    $response = $this->postJson(route('truth-template.upload'), [
        'slug' => $slug,
        'zip' => $uploaded,
        'overwrite' => true,
        'dryRun' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('templateName', 'core:zip/with-partials/template');

    $partialPath = storage_path("app/truth-templates/{$slug}/partials/header.hbs");
    expect(file_get_contents($partialPath))->toContain('<h1>Hello</h1>');
});

test('uploads manual template with multiple partials[]', function () {
    $slug = 'manual/with-partials';

    // Create temporary partial files
    $headPath = tempnam(sys_get_temp_dir(), 'head_') . '.hbs';
    $footPath = tempnam(sys_get_temp_dir(), 'foot_') . '.hbs';

    file_put_contents($headPath, '<head>Header</head>');
    file_put_contents($footPath, '<footer>Footer</footer>');

    $response = $this->postJson(route('truth-template.upload'), [
        'slug' => $slug,
        'template' => 'Hello {{> head}} and {{> foot}}',
        'partials' => [
            new UploadedFile($headPath, 'head.hbs', null, null, true),
            new UploadedFile($footPath, 'foot.hbs', null, null, true),
        ],
        'overwrite' => true,
        'dryRun' => true,
    ]);

    $response->assertCreated();

    expect(file_get_contents(storage_path("app/truth-templates/{$slug}/partials/head.hbs")))->toContain('Header');
    expect(file_get_contents(storage_path("app/truth-templates/{$slug}/partials/foot.hbs")))->toContain('Footer');
});

test('fails ZIP upload if template.hbs is missing', function () {
    $slug = 'zip/missing-template';

    $tmpDir = sys_get_temp_dir() . '/test_zip_' . Str::random(5);
    File::makeDirectory($tmpDir, 0775, true);
    File::put("{$tmpDir}/README.txt", 'This is not a template');

    $zipPath = "{$tmpDir}.zip";
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFile("{$tmpDir}/README.txt", 'README.txt');
    $zip->close();

    $uploaded = new UploadedFile($zipPath, 'no_template.zip', null, null, true);

    $response = $this->postJson(route('truth-template.upload'), [
        'slug' => $slug,
        'zip' => $uploaded,
        'overwrite' => true,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'template.hbs or template.html not found in ZIP');
});

test('slug is sanitized to prevent directory traversal', function () {
    $response = $this->postJson(route('truth-template.upload'), [
        'slug' => '../../etc/passwd',
        'template' => 'Still works',
    ]);

    $response->assertCreated()
        ->assertJsonPath('templateName', 'core:etc/passwd/template');

    $basePath = storage_path('app/truth-templates/etc/passwd/template.hbs');
    expect(File::exists($basePath))->toBeTrue();
});

test('fails if template content is empty', function () {
    $response = $this->postJson(route('truth-template.upload'), [
        'slug' => 'empty/template',
        'template' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'Missing template content');
});

test('rejects invalid schema uploaded as file', function () {
    $slug = 'bad-schema';

    $path = sys_get_temp_dir() . '/bad-schema.json';
    File::put($path, '{ this is bad }');

    $response = $this->postJson(route('truth-template.upload'), [
        'slug' => $slug,
        'template' => 'Hello {{name}}',
        'schema' => new UploadedFile($path, 'schema.json', null, null, true),
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'Provided schema is not valid JSON');
});
