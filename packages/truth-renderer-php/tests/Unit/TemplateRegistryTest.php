<?php

use TruthRenderer\Template\TemplateRegistry;

beforeEach(function () {
    // Create a temp folder structure for namespaced templates
    $this->root = sys_get_temp_dir() . '/tr_templates_' . bin2hex(random_bytes(5));
    $this->nsCore = $this->root . '/core';
    $this->nsExtra = $this->root . '/extra';

    @mkdir($this->nsCore, 0777, true);
    @mkdir($this->nsExtra, 0777, true);

    // Seed some files
    file_put_contents($this->nsCore . '/invoice.hbs', '<h1>Invoice #{{code}}</h1>');
    file_put_contents($this->nsCore . '/receipt.html', '<p>Receipt {{number}}</p>');
    file_put_contents($this->nsExtra . '/ballot.hbs', '<div>Ballot for {{voter}}</div>');

    $this->registry = new TemplateRegistry([
        'core'  => $this->nsCore,
        'extra' => $this->nsExtra,
    ]);
});

afterEach(function () {
    // Cleanup
    $paths = [
        $this->nsCore . '/invoice.hbs',
        $this->nsCore . '/receipt.html',
        $this->nsExtra . '/ballot.hbs',
        $this->nsCore,
        $this->nsExtra,
        $this->root,
    ];
    foreach ($paths as $p) {
        if (@is_file($p)) @unlink($p);
        if (@is_dir($p)) @rmdir($p);
    }
});

it('returns in-memory templates via set/get', function () {
    $this->registry->set('welcome', '<h1>Hi {{name}}</h1>');

    $tpl = $this->registry->get('welcome');
    expect($tpl)->toBe('<h1>Hi {{name}}</h1>');
});

it('loads namespaced .hbs from filesystem', function () {
    $tpl = $this->registry->get('core:invoice');
    expect($tpl)->toBe('<h1>Invoice #{{code}}</h1>');
});

it('loads namespaced .html from filesystem', function () {
    $tpl = $this->registry->get('core:receipt');
    expect($tpl)->toBe('<p>Receipt {{number}}</p>');
});

it('loads from another namespace', function () {
    $tpl = $this->registry->get('extra:ballot');
    expect($tpl)->toBe('<div>Ballot for {{voter}}</div>');
});

it('searches all namespaces when no namespace provided', function () {
    // Un-namespaced lookup should scan all configured dirs in insertion order
    // We didn’t add "receipt" in memory, so it should hit core/receipt.html
    $tpl = $this->registry->get('receipt');
    expect($tpl)->toBe('<p>Receipt {{number}}</p>');
});

it('prefers in-memory over filesystem when keys collide', function () {
    // Override existing filesystem key with memory
    $this->registry->set('core:invoice', 'MEMORY {{code}}');
    $tpl = $this->registry->get('core:invoice');
    expect($tpl)->toBe('MEMORY {{code}}');
});

it('lists memory + filesystem names (normalized)', function () {
    $this->registry->set('welcome', 'x');
    $this->registry->set('core:invoice', 'y'); // will appear as namespaced entry

    $names = $this->registry->list();

    // Should contain memory keys
    expect($names)->toContain('welcome');
    expect($names)->toContain('core:invoice');

    // Should contain filesystem-discovered keys
    expect($names)->toContain('core:receipt');
    expect($names)->toContain('extra:ballot');
});

it('throws a clear error when template is missing', function () {
    $call = fn () => $this->registry->get('nope');
    expect($call)->toThrow(\RuntimeException::class, 'Template not found: nope');
});
