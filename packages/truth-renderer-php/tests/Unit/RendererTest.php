<?php

use TruthRenderer\Renderer;
use TruthRenderer\DTO\RenderRequest;
use TruthRenderer\DTO\RenderResult;
use TruthRenderer\Engine\HandlebarsEngine;
use TruthRenderer\Validation\Validator;
use Dompdf\Options;

beforeEach(function () {
    // You can pass real instances (integration-style) or mocks if desired
    $this->renderer = new Renderer(
        engine: new HandlebarsEngine(),
        validator: new Validator(),
        dompdfOptions: (new Options())
            ->set('isRemoteEnabled', true)
            ->set('isHtml5ParserEnabled', true)
            ->set('defaultFont', 'DejaVu Sans')
    );
});

/**
 * Helper to build a basic RenderRequest quickly.
 * Adjust if your RenderRequest constructor signature differs.
 */
function rr(array $overrides = []): RenderRequest {
    // Minimal valid defaults
    $base = [
        'template'      => '<h1>Hello, {{name}}!</h1>',
        'data'          => ['name' => 'World'],
        'schema'        => null,
        'partials'      => [],
        'engineFlags'   => [],
        'format'        => 'html',
        'paperSize'     => 'A4',
        'orientation'   => 'portrait',
        'assetsBaseUrl' => null, // Tip: use a local dir when using Dompdf assets
    ];

    $payload = array_replace($base, $overrides);

    // If your RenderRequest has a constructor with named params:
    return new RenderRequest(
        template:      $payload['template'],
        data:          $payload['data'],
        schema:        $payload['schema'],
        partials:      $payload['partials'],
        engineFlags:   $payload['engineFlags'],
        format:        $payload['format'],
        paperSize:     $payload['paperSize'],
        orientation:   $payload['orientation'],
        assetsBaseUrl: $payload['assetsBaseUrl'],
    );
}

it('renders HTML', function () {
    $req = rr([
        'template' => '<p>Hi, <strong>{{name}}</strong></p>',
        'data'     => ['name' => 'Ada'],
        'format'   => 'html',
    ]);

    $res = $this->renderer->render($req);
    expect($res)->toBeInstanceOf(RenderResult::class);
    expect($res->format)->toBe('html');
    expect($res->content)->toContain('<strong>Ada</strong>');
});

it('renders PDF', function () {
    $req = rr([
        'template' => '<h1>Invoice #{{code}}</h1>',
        'data'     => ['code' => 'INV-123'],
        'format'   => 'pdf',
    ]);

    $res = $this->renderer->render($req);
    expect($res->format)->toBe('pdf');;
    // Dompdf output should start with %PDF
    expect(substr($res->content, 0, 4))->toBe('%PDF');
});

it('renders Markdown (basic down-conversion)', function () {
    $req = rr([
        'template' => '<h1>Title</h1><p>Hello <em>world</em></p>',
        'format'   => 'md',
    ]);

    $res = $this->renderer->render($req);
    expect($res->format)->toBe('md');
    // Very basic check; core uses a minimal html→text fallback
    expect($res->content)->toContain('Title');
    expect($res->content)->toContain('Hello world');
});

it('supports partials', function () {
    $req = rr([
        'template' => '<div>{{> header}}</div><p>{{body}}</p>',
        'partials' => [
            'header' => '<h1>{{title}}</h1>',
        ],
        'data'     => ['title' => 'Welcome', 'body' => 'Body text'],
        'format'   => 'html',
    ]);

    $res = $this->renderer->render($req);
    expect($res->content)->toContain('<h1>Welcome</h1>');
    expect($res->content)->toContain('<p>Body text</p>');
});

it('accepts custom helpers via engineFlags', function () {
    $req = rr([
        'template'    => 'Triple: {{triple 7}}',
        'engineFlags' => [
            // Per-engine guard: closures or "Class::method" strings are OK; array-callables are rejected.
            'helpers' => [
                'triple' => function ($v) { return (string)((int)$v * 3); },
            ],
        ],
        'format'   => 'html',
    ]);

    $res = $this->renderer->render($req);
    expect($res->content)->toBe('Triple: 21');
});

it('validates data against JSON Schema (failure case)', function () {
    $schema = [
        '$schema'   => 'http://json-schema.org/draft-07/schema#',
        'type'      => 'object',
        'required'  => ['code', 'amount'],
        'properties'=> [
            'code'   => ['type' => 'string'],
            'amount' => ['type' => 'number'],
        ],
    ];

    $req = rr([
        'template' => 'Unused on error',
        'data'     => ['code' => 'INV-001'], // missing amount
        'schema'   => $schema,
    ]);

    $call = fn () => $this->renderer->render($req);
    expect($call)->toThrow(\RuntimeException::class);
});

it('writes to file via renderToFile', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'render_');
    if ($tmp === false) {
        $this->fail('Failed to create temp file');
    }
    // We’ll overwrite this path; ensure directory exists
    $path = $tmp . '.pdf';
    @unlink($path);

    $req = rr([
        'template' => '<h1>PDF Export</h1>',
        'format'   => 'pdf',
    ]);

    $res = $this->renderer->renderToFile($req, $path);

    expect(is_file($path))->toBeTrue();
    expect(filesize($path))->toBeGreaterThan(100);
    expect(substr(file_get_contents($path), 0, 4))->toBe('%PDF');

    @unlink($path);
    @unlink($tmp);
});

it('honors paper size and orientation', function () {
    $req = rr([
        'template'    => '<div style="height:100px">Sized</div>',
        'format'      => 'pdf',
        'paperSize'   => 'letter',
        'orientation' => 'landscape',
    ]);

    $res = $this->renderer->render($req);
    expect(substr($res->content, 0, 4))->toBe('%PDF');
});

it('can resolve local assets when assetsBaseUrl points to a directory', function () {
    // Create a tiny local asset and reference it with a relative path
    $dir = sys_get_temp_dir() . '/truth_assets_' . uniqid();
    @mkdir($dir);
    file_put_contents($dir . '/demo.css', 'h1{color:#333}');

    $req = rr([
        'template'      => '<html><head><link rel="stylesheet" href="demo.css"></head><body><h1>Styled</h1></body></html>',
        'format'        => 'pdf',
        // Dompdf chroot is a filesystem directory. For remote URLs use isRemoteEnabled + absolute URLs.
        'assetsBaseUrl' => $dir,
    ]);

    $res = $this->renderer->render($req);
    expect(substr($res->content, 0, 4))->toBe('%PDF');

    // Cleanup
    @unlink($dir . '/demo.css');
    @rmdir($dir);
});


use TruthRenderer\Contracts\TemplateRegistryInterface;

/**
 * Tiny fake registry for tests
 */
class FakeRegistry implements TemplateRegistryInterface
{
    /** @var array<string,string> */
    public function __construct(private array $map = [])
    {
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->map);
    }

    public function get(string $name): string
    {
        if (!$this->has($name)) {
            throw new \RuntimeException("Template not found: {$name}");
        }
        return $this->map[$name];
    }

    public function list(): array
    {
        return array_keys($this->map);
    }

    public function set(string $name, string $source): void
    {
        // TODO: Implement set() method.
    }

    public function resolveFile(string $name): ?string
    {
        // TODO: Implement resolveFile() method.
    }

    public function resolveDir(string $name): ?string
    {
        // TODO: Implement resolveDir() method.
    }
}

it('uses a registry template when the template name matches', function () {
    $registry = new FakeRegistry([
        // name => actual handlebars source
        'invoice.header' => '<h1>Invoice #{{code}}</h1>',
    ]);

    $renderer = new Renderer(
        engine: new HandlebarsEngine(),
        validator: new Validator(),
        dompdfOptions: (new Options())
            ->set('isRemoteEnabled', true)
            ->set('isHtml5ParserEnabled', true)
            ->set('defaultFont', 'DejaVu Sans'),
        registry: $registry
    );

    // Note: template is the NAME, not inline source
    $req = rr([
        'template' => 'invoice.header',
        'data'     => ['code' => 'INV-007'],
        'format'   => 'html',
    ]);

    $res = $renderer->render($req);
    expect($res)->toBeInstanceOf(RenderResult::class);
    expect($res->format)->toBe('html');
    expect($res->content)->toContain('<h1>Invoice #INV-007</h1>');
});

it('falls back to inline template when registry does not have the key', function () {
    $registry = new FakeRegistry([
        'known' => '<p>Known</p>',
    ]);

    $renderer = new Renderer(
        engine: new HandlebarsEngine(),
        validator: new Validator(),
        dompdfOptions: (new Options())
            ->set('isRemoteEnabled', true)
            ->set('isHtml5ParserEnabled', true)
            ->set('defaultFont', 'DejaVu Sans'),
        registry: $registry
    );

    // This key is NOT in the registry → renderer should treat it as inline
    $req = rr([
        'template' => '<div>{{greet}}, {{name}}!</div>',
        'data'     => ['greet' => 'Hi', 'name' => 'Grace'],
        'format'   => 'html',
    ]);

    $res = $renderer->render($req);
    expect($res->format)->toBe('html');
    expect($res->content)->toBe('<div>Hi, Grace!</div>');
});

it('resolves relative CSS under assetsBaseUrl with registry template', function () {
    $dir = sys_get_temp_dir() . '/truth_assets_' . uniqid();
    @mkdir($dir);
    file_put_contents($dir . '/style.css', 'h1{font-size:20px}');

    $registry = new FakeRegistry([
        'styled' => '<html><head><link rel="stylesheet" href="style.css"></head><body><h1>Styled</h1></body></html>',
    ]);

    $renderer = new Renderer(
        engine: new HandlebarsEngine(),
        validator: new Validator(),
        dompdfOptions: (new Options())
            ->set('isRemoteEnabled', true)
            ->set('isHtml5ParserEnabled', true)
            ->set('defaultFont', 'DejaVu Sans'),
        registry: $registry
    );

    $req = rr([
        'template'      => 'styled',
        'format'        => 'pdf',
        'assetsBaseUrl' => $dir,
    ]);

    $res = $renderer->render($req);
    expect(substr($res->content, 0, 4))->toBe('%PDF');

    @unlink($dir . '/style.css');
    @rmdir($dir);
});

it('renders grouped items using groupBy helper', function () {
    $req = rr([
        'template' => <<<HBS
{{#groupBy items key="category"}}
<h3>Category: {{key}}</h3>
<ul>
{{#each items}}
  <li>{{name}} ({{category}})</li>
{{/each}}
</ul>
{{/groupBy}}
HBS,
        'data' => [
            'items' => [
                ['name' => 'Apple', 'category' => 'Fruit'],
                ['name' => 'Banana', 'category' => 'Fruit'],
                ['name' => 'Carrot', 'category' => 'Vegetable'],
                ['name' => 'Broccoli', 'category' => 'Vegetable'],
            ],
        ],
        'format' => 'html',
    ]);

    $res = $this->renderer->render($req);

    expect($res)->toBeInstanceOf(RenderResult::class);
    expect($res->format)->toBe('html');
    expect($res->content)->toContain('Category: Fruit');
    expect($res->content)->toContain('Apple (Fruit)');
    expect($res->content)->toContain('Category: Vegetable');
    expect($res->content)->toContain('Broccoli (Vegetable)');
});

it('renders QR codes independently', function () {
    $tpl = <<<HBS
<h2>QR Code Sheet</h2>

<p>Code: {{qrMeta.code}}</p>
<p>Split By: {{qrMeta.by}}</p>

<div style="display: flex; flex-wrap: wrap; gap: 2em; margin-top: 1em;">
  {{#each qrMeta.qr}}
    <div style="text-align: center;">
      <div style="width: 256px; height: 256px;">
        {{{this}}}
      </div>
      <p>QR {{inc @index}}</p>
    </div>
  {{/each}}
</div>
HBS;

    $data = [
        'qrMeta' => [
            'code' => 'DEMO-001',
            'by'   => 'count',
            'urls' => [
                'https://example.com/1',
                'https://example.com/2',
                'https://example.com/3',
            ],
            'qr' => [
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#000"/></svg>',
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="50" fill="#000"/></svg>',
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text x="10" y="50" font-size="12" fill="#000">QR 3</text></svg>',
            ],
        ],
    ];

    $html = (new \TruthRenderer\Engine\HandlebarsEngine())->render($tpl, $data);
    expect($html)->toContain('<svg');
    expect($html)->toContain('QR 1');
});

it('renders the top-level format variable outside of the qrMeta block', function () {
    $tpl = <<<HBS
<h2>QR Format Debug</h2>

<p><strong>Format:</strong> {{format}}</p>

<div>
  {{#each qrMeta.qr}}
    <div>QR: {{this}}</div>
  {{/each}}
</div>
HBS;

    $data = [
        'format' => 'png',
        'qrMeta' => [
            'qr' => ['One', 'Two', 'Three'],
        ],
    ];

    $html = (new \TruthRenderer\Engine\HandlebarsEngine())->render($tpl, $data);
    $normalized = trim(preg_replace('/\s+/', ' ', $html));

    expect($normalized)->toContain('<strong>Format:</strong> png');
    expect($normalized)->toContain('QR: One');
    expect($normalized)->toContain('QR: Two');
});

it('renders format nested under qrMeta', function () {
    $tpl = <<<HBS
<h2>QR Code Format Test</h2>

<p>Top-Level Format: {{format}}</p>
<p>Nested Format: {{qrMeta.format}}</p>

<div>
  {{#each qrMeta.qr}}
    <div>QR: {{this}}</div>
  {{/each}}
</div>
HBS;

    $data = [
        'format' => 'html', // intentionally different to confirm scoping
        'qrMeta' => [
            'format' => 'png',
            'by'     => 'size',
            'urls'   => [
                'truth://v1/TRUTH/DEMO-001/1/1?c=xyz'
            ],
            'qr' => [
                'QR-IMAGE-1',
                'QR-IMAGE-2',
                'QR-IMAGE-3',
            ],
        ],
    ];

    $html = (new \TruthRenderer\Engine\HandlebarsEngine())->render($tpl, $data);
    $normalized = trim(preg_replace('/\s+/', ' ', $html));

    expect($normalized)->toContain('Top-Level Format: html');
    expect($normalized)->toContain('Nested Format: png');
    expect($normalized)->toContain('QR: QR-IMAGE-1');
    expect($normalized)->toContain('QR: QR-IMAGE-3');
});

test('Handlebars startsWith and includes helpers work', function () {
    $tpl = <<<HBS
        {{#if (startsWith value "hello")}}startsWith: Yes{{else}}startsWith: No{{/if}}
        {{#if (includes value "world")}}includes: Yes{{else}}includes: No{{/if}}
    HBS;

    $engine = new HandlebarsEngine();
    $html = $engine->render($tpl, ['value' => 'hello world']);

    expect($html)->toContain('startsWith: Yes');
    expect($html)->toContain('includes: Yes');
});
