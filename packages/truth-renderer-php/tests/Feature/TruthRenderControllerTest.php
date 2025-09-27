<?php

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Route;
use TruthRenderer\TruthRendererServiceProvider;
use TruthRenderer\Http\Controllers\TruthRenderController;
use TruthRenderer\Template\TemplateRegistry;

beforeEach(function () {
    // Register provider
    $this->app->register(TruthRendererServiceProvider::class);

    // Override config to point registry to a temp directory we control
    $this->tmpDir = base_path('tests/Fixtures/templates_' . uniqid());
    @mkdir($this->tmpDir, 0777, true);
    config()->set('truth-renderer.paths', [
        'core' => $this->tmpDir,
    ]);

    // Seed a simple template file
    file_put_contents($this->tmpDir . '/hello.hbs', '<h1>Hello, {{name}}!</h1>');

    // Bind routes just for tests
    Route::middleware('api')->group(function () {
        Route::get('/truth/templates', [TruthRenderController::class, 'listTemplates']);
        Route::post('/truth/render',  [TruthRenderController::class, 'render']);
    });
});

afterEach(function () {
    // Cleanup temp files
    @unlink($this->tmpDir . '/hello.hbs');
    @rmdir($this->tmpDir);
});

it('lists templates from registry', function () {
    $res = $this->getJson('/truth/templates')
        ->assertOk()
        ->json();

    expect($res)->toHaveKey('templates');
    expect($res['templates'])->toBeArray();
    // Should include our seeded template as "core:hello"
    expect($res['templates'])->toContain('core:hello');
});

it('renders HTML via templateName', function () {
    $payload = [
        'templateName' => 'core:hello',
        'data'         => ['name' => 'Ada'],
        'format'       => 'html',
    ];

    $this->postJson('/truth/render', $payload)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('<h1>Hello, Ada!</h1>', escape:false)
    ;
})->skip();

it('renders Markdown from raw template', function () {
    $payload = [
        'template' => '<h2>Title</h2><p>Body</p>',
        'data'     => [],
        'format'   => 'md',
    ];

    $res = $this->postJson('/truth/render', $payload)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->getContent();

    expect($res)->toContain('Title');
    expect($res)->toContain('Body');
});

it('returns 422 when neither template nor templateName is provided', function () {
    $this->postJson('/truth/render', [
        'data'   => ['name' => 'Bob'],
        'format' => 'html',
    ])->assertStatus(422);
});
