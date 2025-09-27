<?php

use TruthRenderer\Contracts\TemplateRegistryInterface;
use TruthRenderer\Contracts\RendererInterface;
use TruthRenderer\Template\TemplateRegistry;
use TruthRenderer\Renderer;
use Dompdf\Options;

it('binds Renderer as a singleton and aliases RendererInterface', function () {
    $a = $this->app->make(Renderer::class);
    $b = $this->app->make(RendererInterface::class);

    expect($a)->toBeInstanceOf(Renderer::class);
    expect($b)->toBeInstanceOf(Renderer::class);
    expect($a)->toBe($b); // same singleton instance
});

it('binds TemplateRegistry as a singleton and aliases TemplateRegistryInterface', function () {
    $a = $this->app->make(TemplateRegistry::class);
    $b = $this->app->make(TemplateRegistryInterface::class);

    expect($a)->toBeInstanceOf(TemplateRegistry::class);
    expect($b)->toBeInstanceOf(TemplateRegistry::class);
    expect($a)->toBe($b); // same singleton instance
});

it('uses config paths for TemplateRegistry', function () {
    // Override config before resolving
    config()->set('truth-renderer.paths', [
        'core' => base_path('tests/Fixtures/templates'),
        'alt'  => base_path('tests/Fixtures/alt-templates'),
    ]);

    $reg = $this->app->make(TemplateRegistry::class);

    // We can’t read private props, but we can test behavior:
    // Create a fake template file and see if registry finds it.
    $dir = base_path('tests/Fixtures/templates');
    @mkdir($dir, 0777, true);
    file_put_contents($dir . '/hello.hbs', 'Hi {{name}}');

    $src = $reg->get('core:hello');
    expect($src)->toBe('Hi {{name}}');

    // cleanup
    @unlink($dir . '/hello.hbs');
    @rmdir($dir);
});

it('shares Dompdf Options as singleton', function () {
    $a = $this->app->make(Options::class);
    $b = $this->app->make(Options::class);

    expect($a)->toBeInstanceOf(Options::class);
    expect($a)->toBe($b); // same instance
});
