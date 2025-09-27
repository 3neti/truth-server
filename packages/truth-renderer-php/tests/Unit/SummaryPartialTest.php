<?php

use TruthRenderer\Engine\HandlebarsEngine;

/**
 * Matches the LightnCandy-safe summary.hbs we’re using:
 * - Uses {{#let ... var="computed"}} to bind the computed total
 * - Compares with supplied @root.total using eq + round2
 */
const SUMMARY_TPL = <<<HBS
{{#let (calcTotal @root.items) var="computed"}}
Total: {{currency computed}}
{{#if @root.total}}
  {{#unless (eq (round2 @root.total) (round2 computed))}}
NOTE: supplied {{currency @root.total}} differs from computed {{currency computed}}
  {{/unless}}
{{/if}}
{{/let}}
HBS;

function norm(string $html): string {
    // decode entities (so &nbsp; becomes the actual NBSP char)
    $t = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // strip tags
    $t = strip_tags($t);
    // normalize NBSP to regular space
    $t = preg_replace('/\x{00A0}/u', ' ', $t);
    // collapse whitespace
    $t = preg_replace('/\s+/u', ' ', $t);
    return trim($t);
}


it('computes total = $21.00 from array rows (no NOTE when equal)', function () {
    $tpl = <<<'HBS'
<p><strong>Total:</strong> ${{round2 (calcTotal items)}}</p>
{{#if total}}
  {{#unless (eq (round2 total) (round2 (calcTotal items)))}}
    <p class="text-amber-700 text-xs">
      Note: supplied total ${{round2 total}} differs from computed ${{round2 (calcTotal items)}}
    </p>
  {{/unless}}
{{/if}}
HBS;

    $data = [
        'items' => [
            ['name' => 'A', 'qty' => 2, 'price' => 4.5],
            ['name' => 'B', 'qty' => 1, 'price' => 12],
        ],
        'total' => 21.0,
    ];

    $html = (new \TruthRenderer\Engine\HandlebarsEngine())->render($tpl, $data);
    $out  = norm($html);

    expect($out)->toContain('Total: $21.00');
    expect($out)->not->toContain('Note: supplied total');
});

it('shows NOTE when supplied total mismatches computed (object rows)', function () {
    $tpl = <<<'HBS'
<p><strong>Total:</strong> ${{round2 (calcTotal items)}}</p>
{{#if total}}
  {{#unless (eq (round2 total) (round2 (calcTotal items)))}}
    <p class="text-amber-700 text-xs">
      Note: supplied total ${{round2 total}} differs from computed ${{round2 (calcTotal items)}}
    </p>
  {{/unless}}
{{/if}}
HBS;

    $data = [
        'items' => [
            (object)['name' => 'A', 'qty' => 2, 'price' => 4.5], // 9.0
            (object)['name' => 'B', 'qty' => 1, 'price' => 12],  // 12.0
        ],
        'total' => 22.0, // wrong on purpose
    ];

    $html = (new \TruthRenderer\Engine\HandlebarsEngine())->render($tpl, $data);
    $out  = norm($html);

    expect($out)->toContain('Total: $21.00');
    expect($out)->toContain('Note: supplied total $22.00 differs from computed $21.00');
});

/**
 * Small sanity check: built-ins return strings (not booleans),
 * so comparisons in earlier tests are stable.
 */
it('built-in helpers render printable strings', function () {
    $tpl = implode("\n", [
        '{{upper title}}',
        '{{lower title}}',
        '{{currency price}}',
        '{{date created_at "Y/m/d"}}',
        '{{multiply qty unit_price}}',
    ]);

    $data = [
        'title'      => 'Hello There',
        'price'      => 1234.5,
        'created_at' => '2024-12-25T10:30:00Z',
        'qty'        => 3,
        'unit_price' => 2.5,
    ];

    $out = (new HandlebarsEngine())->render($tpl, $data);
    [$l1, $l2, $l3, $l4, $l5] = explode("\n", trim($out));

    expect($l1)->toBe('HELLO THERE');
    expect($l2)->toBe('hello there');
    expect($l3)->toBe('$1,234.50');
    expect($l4)->toBe('2024/12/25');
    expect($l5)->toBe('7.5'); // stringify multiply result
});
