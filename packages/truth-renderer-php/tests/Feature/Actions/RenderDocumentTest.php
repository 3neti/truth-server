<?php

use Illuminate\Support\Facades\App;
use TruthRenderer\Actions\RenderDocument;
use TruthRenderer\DTO\RenderResult;
use TruthRenderer\Exceptions\TemplateNotFoundException;
use TruthRenderer\Template\TemplateRegistry;
use TruthRenderer\TruthRendererServiceProvider;

beforeEach(function () {
    $this->app->register(TruthRendererServiceProvider::class);

    $this->tmpDir = base_path('tests/Fixtures/templates_' . uniqid());
    @mkdir($this->tmpDir, 0777, true);

    config()->set('truth-renderer.paths', [
        'core' => $this->tmpDir,
    ]);

    file_put_contents($this->tmpDir . '/hello.hbs', '<h1>Hello, {{name}}!</h1>');
});

afterEach(function () {
    @unlink($this->tmpDir . '/hello.hbs');
    @rmdir($this->tmpDir);
});

it('renders HTML via templateName using the action', function () {
    $action = App::make(RenderDocument::class);

    $result = $action->handle([
        'templateName' => 'core:hello',
        'data'         => ['name' => 'Ada'],
        'format'       => 'html',
    ]);

    expect($result)->toBeInstanceOf(RenderResult::class);
    expect($result->format)->toBe('html');
    expect($result->content)->toContain('<h1>Hello, Ada!</h1>');
});

it('renders Markdown from raw template using the action', function () {
    $action = App::make(RenderDocument::class);

    $result = $action->handle([
        'template' => '<h2>Title</h2><p>Body</p>',
        'data'     => [],
        'format'   => 'md',
    ]);

    expect($result)->toBeInstanceOf(RenderResult::class);
    expect($result->format)->toBe('md');
    expect($result->content)->toContain('Title');
    expect($result->content)->toContain('Body');
});

it('throws TemplateNotFoundException when neither template nor templateName is provided', function () {
    $action = App::make(RenderDocument::class);

    expect(fn () => $action->handle([
        'data'   => ['name' => 'Bob'],
        'format' => 'html',
    ]))->toThrow(TemplateNotFoundException::class);
});

it('renders PDF using partials and math helpers', function () {
    $template = <<<HBS
<h1>Invoice {{code}}</h1>
<p>Date: {{date date "Y-m-d"}}</p>

<table border="1" cellspacing="0" cellpadding="4">
    <thead>
        <tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
    </thead>
    <tbody>
        {{#each items}}
            {{> itemRow }}
        {{/each}}
        {{> summary }}
    </tbody>
</table>
HBS;

    $items = [
        ['name' => 'Item A', 'qty' => 2, 'price' => 10.0],
        ['name' => 'Item B', 'qty' => 1, 'price' => 20.0],
    ];

    $total = collect($items)->reduce(fn($carry, $item) => $carry + ($item['qty'] * $item['price']), 0);

    $payload = [
        'template' => $template,
        'partials' => [
            'itemRow' => '<tr><td>{{name}}</td><td>{{qty}}</td><td>{{price}}</td><td>{{math "multiply" qty price}}</td></tr>',
            'summary' => '<tr><td colspan="3">Total</td><td>{{total}}</td></tr>',
        ],
        'data' => [
            'code' => 'INV-001',
            'date' => now()->toDateString(),
            'items' => $items,
            'total' => $total,
        ],
        'format' => 'pdf',
        'paperSize' => 'A4',
        'orientation' => 'portrait',
    ];

    $result = RenderDocument::run($payload);

    expect($result)->toBeInstanceOf(RenderResult::class);
    expect($result->format)->toBe('pdf');
    expect($result->content)->toStartWith('%PDF');
});
