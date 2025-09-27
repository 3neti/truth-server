<?php

use TruthRenderer\Http\Controllers\PdfRenderController;
use Symfony\Component\HttpFoundation\Response;
use TruthRenderer\Template\TemplateRegistry;
use TruthRenderer\Engine\HandlebarsEngine;
use TruthRenderer\Validation\Validator;
use Illuminate\Support\Facades\Route;
use TruthRenderer\Renderer;
use Dompdf\Options;

beforeEach(function () {
    Route::post('/render', PdfRenderController::class);

    $this->renderer = new Renderer(
        engine: new HandlebarsEngine(),
        validator: new Validator(),
        dompdfOptions: (new Options())
            ->set('isRemoteEnabled', true)
            ->set('isHtml5ParserEnabled', true)
            ->set('defaultFont', 'DejaVu Sans')
    );

    $this->app->bind(Renderer::class, fn () => $this->renderer);
});

it('can resolve the TemplateRegistry', function () {
    $registry = app(TemplateRegistry::class);
    expect($registry)->toBeInstanceOf(TemplateRegistry::class);
});

it('renders a simple PDF with variable replacement', function () {
    $payload = [
        'template' => '<h1>Hello, {{ name }}!</h1>',
        'data' => ['name' => 'World'],
    ];

    $response = $this->postJson('/render', $payload);

    $response->assertStatus(Response::HTTP_OK);
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader('Content-Disposition', 'attachment; filename="truth-output.pdf"');

    expect($response->getContent())->toStartWith('%PDF');
})->skip();

it('renders inline HTML templates without registry lookup', function () {
    $payload = [
        'template' => '<h1>Hello {{name}}</h1>',
        'data' => ['name' => 'Lester'],
        'format' => 'html',
    ];

    $response = $this->postJson('/render', $payload);

    $response->assertOk();
    expect($response->getContent())->toContain('Hello Lester');
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
        ['name' => 'Item A', 'qty' => 2, 'price' => 10.0], // 20
        ['name' => 'Item B', 'qty' => 1, 'price' => 20.0], // 20
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

    $response = $this->postJson('/render', $payload);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader('Content-Disposition', 'attachment; filename="truth-output.pdf"');

    expect($response->getContent())->toStartWith('%PDF');
    file_put_contents(base_path('test-output.pdf'), $response->getContent());
    dump('PDF saved to:', base_path('test-output.pdf'));
});

function truthTemplatePath(string $namespace = 'core'): string
{
    return __DIR__ . "/resources/truth-templates";
}

it('renders the core:invoice/basic template using template registry', function () {
    $path = __DIR__ . '/resources/truth-templates';
    config()->set(['truth-renderer.paths.core' => truthTemplatePath()]);

    $registry = new TemplateRegistry( [
        'core' => truthTemplatePath(),
    ]);

    expect($registry->has('core:invoice/basic/template'))->toBeTrue();

    $items = [
        ['name' => 'Item A', 'qty' => 2, 'price' => 10], // 20
        ['name' => 'Item B', 'qty' => 1, 'price' => 30], // 30
    ];

    $total = collect($items)->reduce(fn($carry, $item) => $carry + ($item['qty'] * $item['price']), 0); // 50

    $payload = [
        'template' => 'core:invoice/basic/template',
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

    $response = $this->postJson('/render', $payload);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader('Content-Disposition', 'attachment; filename="truth-output.pdf"');

    // Check for correct PDF output
    expect($response->getContent())->toStartWith('%PDF');

    // Save to disk for visual verification
    file_put_contents(base_path('test-core-invoice-basic.pdf'), $response->getContent());
    dump('PDF saved to:', base_path('test-core-invoice-basic.pdf'));
});
