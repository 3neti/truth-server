<?php

declare(strict_types=1);

namespace Tests\Unit;

use TruthRenderer\Engine\HandlebarsEngine;

uses()->group('engine');

beforeEach(function () {
    $this->engine = new HandlebarsEngine();
});

it('renders simple variables', function () {
    $tpl  = 'Hello, {{name}}!';
    $html = $this->engine->render($tpl, ['name' => 'World']);

    expect($html)->toBe('Hello, World!');
});

it('renders with built-in helpers', function () {
    $tpl = implode("\n", [
        '{{upper title}}',
        '{{lower title}}',
        '{{currency price}}',
        '{{date created_at "Y/m/d"}}',
        '{{multiply qty unit_price}}',
    ]);

    $out = $this->engine->render($tpl, [
        'title'      => 'Hello There',
        'price'      => 1234.5,
        'created_at' => '2024-12-25T10:30:00Z',
        'qty'        => 3,
        'unit_price' => 2.5,
    ]);

    [$l1, $l2, $l3, $l4, $l5] = explode("\n", trim($out));

    expect($l1)->toBe('HELLO THERE');
    expect($l2)->toBe('hello there');
    expect($l3)->toBe('$1,234.50');
    expect($l4)->toBe('2024/12/25');
    expect($l5)->toBe('7.5');
});

it('supports partials', function () {
    $tpl = <<<HBS
Items:
{{#each items}}
- {{> row}}
{{/each}}
HBS;

    $partials = [
        'row' => '{{name}} x{{qty}} = {{multiply qty price}}',
    ];

    $out = $this->engine->render($tpl, [
        'items' => [
            ['name' => 'Apple',  'qty' => 2, 'price' => 1.25],
            ['name' => 'Banana', 'qty' => 3, 'price' => 0.5],
        ],
    ], $partials);

    $lines = array_values(array_filter(array_map('trim', explode("\n", $out))));
    // Expect:
    // Items:
    // - Apple x2 = 2.5
    // - Banana x3 = 1.5
    expect($lines[0])->toBe('Items:');
    expect($lines[1])->toBe('- Apple x2 = 2.5');
    expect($lines[2])->toBe('- Banana x3 = 1.5');
});

it('allows providing engineFlags (e.g. a custom helper) at call time', function () {
    $tpl = 'Custom: {{triple 7}}';

    $out = $this->engine->render($tpl, [], [], [
        'helpers' => [
            // LightnCandy accepts closures or FQN strings.
            'triple' => function ($v) { return (string) ((int) $v * 3); },
        ],
    ]);

    expect($out)->toBe('Custom: 21');
});

it('throws a RuntimeException on compile errors', function () {
    // Unclosed block → LightnCandy compile() returns false; engine should throw
    $invalid = '{{#if foo}} oops ';

    $call = fn () => $this->engine->render($invalid, ['foo' => true]);

    expect($call)->toThrow(\RuntimeException::class, 'Handlebars compile failed');
});
