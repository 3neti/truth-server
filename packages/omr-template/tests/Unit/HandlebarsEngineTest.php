<?php

use LBHurtado\OMRTemplate\Services\HandlebarsEngine;

test('can render simple handlebars template', function () {
    $engine = new HandlebarsEngine;
    
    $template = 'Hello {{name}}!';
    $data = ['name' => 'World'];
    
    $result = $engine->renderString($template, $data);
    
    expect($result)->toBe('Hello World!');
});

test('can render template with each loop', function () {
    $engine = new HandlebarsEngine;
    
    $template = '{{#each items}}{{this.name}} {{/each}}';
    $data = [
        'items' => [
            ['name' => 'Apple'],
            ['name' => 'Banana'],
            ['name' => 'Cherry'],
        ],
    ];
    
    $result = $engine->renderString($template, $data);
    
    expect($result)->toBe('Apple Banana Cherry ');
});

test('can render template with conditional', function () {
    $engine = new HandlebarsEngine;
    
    $template = '{{#if show}}Visible{{/if}}';
    
    $result = $engine->renderString($template, ['show' => true]);
    expect($result)->toBe('Visible');
    
    $result = $engine->renderString($template, ['show' => false]);
    expect($result)->toBe('');
});
