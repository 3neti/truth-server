<?php

use LBHurtado\OMRTemplate\Services\HandlebarsCompiler;
use LBHurtado\OMRTemplate\Services\HandlebarsEngine;

beforeEach(function () {
    $this->engine = new HandlebarsEngine();
    $this->compiler = new HandlebarsCompiler($this->engine);
});

test('compiles simple handlebars template with data', function () {
    $template = '{
        "title": "{{title}}",
        "id": "{{id}}"
    }';

    $data = [
        'title' => 'Test Document',
        'id' => 'DOC-001',
    ];

    $result = $this->compiler->compile($template, $data);

    expect($result)->toBeArray()
        ->and($result['title'])->toBe('Test Document')
        ->and($result['id'])->toBe('DOC-001');
});

test('compiles template with each loop', function () {
    $template = '{
        "items": [
            {{#each items}}
            {
                "name": "{{name}}",
                "value": {{value}}
            }{{#unless @last}},{{/unless}}
            {{/each}}
        ]
    }';

    $data = [
        'items' => [
            ['name' => 'Item 1', 'value' => 10],
            ['name' => 'Item 2', 'value' => 20],
        ],
    ];

    $result = $this->compiler->compile($template, $data);

    expect($result)->toBeArray()
        ->and($result['items'])->toHaveCount(2)
        ->and($result['items'][0]['name'])->toBe('Item 1')
        ->and($result['items'][0]['value'])->toBe(10)
        ->and($result['items'][1]['name'])->toBe('Item 2')
        ->and($result['items'][1]['value'])->toBe(20);
});

test('compiles OMR ballot template with positions and candidates', function () {
    $template = '{
        "document": {
            "title": "{{election.title}}",
            "unique_id": "{{election.id}}"
        },
        "sections": [
            {{#each positions}}
            {
                "type": "multiple_choice",
                "code": "{{code}}",
                "title": "{{title}}",
                "choices": [
                    {{#each candidates}}
                    {
                        "code": "{{code}}",
                        "label": "{{name}}"
                    }{{#unless @last}},{{/unless}}
                    {{/each}}
                ]
            }{{#unless @last}},{{/unless}}
            {{/each}}
        ]
    }';

    $data = [
        'election' => [
            'title' => '2025 General Election',
            'id' => 'BAL-2025-001',
        ],
        'positions' => [
            [
                'code' => 'PRESIDENT',
                'title' => 'President',
                'candidates' => [
                    ['code' => 'P-A', 'name' => 'Candidate A'],
                    ['code' => 'P-B', 'name' => 'Candidate B'],
                ],
            ],
            [
                'code' => 'VICE_PRESIDENT',
                'title' => 'Vice President',
                'candidates' => [
                    ['code' => 'VP-A', 'name' => 'VP Candidate A'],
                    ['code' => 'VP-B', 'name' => 'VP Candidate B'],
                ],
            ],
        ],
    ];

    $result = $this->compiler->compile($template, $data);

    expect($result)->toBeArray()
        ->and($result['document']['title'])->toBe('2025 General Election')
        ->and($result['document']['unique_id'])->toBe('BAL-2025-001')
        ->and($result['sections'])->toHaveCount(2)
        ->and($result['sections'][0]['code'])->toBe('PRESIDENT')
        ->and($result['sections'][0]['title'])->toBe('President')
        ->and($result['sections'][0]['choices'])->toHaveCount(2)
        ->and($result['sections'][1]['code'])->toBe('VICE_PRESIDENT');
});

test('validates correct handlebars template', function () {
    $template = '{
        "title": "{{title}}",
        "items": [
            {{#each items}}
            {"name": "{{name}}"}{{#unless @last}},{{/unless}}
            {{/each}}
        ]
    }';

    $result = $this->compiler->validate($template);

    expect($result)->toBeTrue();
});

test('throws exception for invalid handlebars syntax', function () {
    $template = '{
        "title": "{{title}}",
        "items": [
            {{#each items}}
            {"name": "{{name}}"}
            // Missing closing tag
        ]
    }';

    $this->compiler->validate($template);
})->throws(Exception::class, 'Invalid Handlebars syntax');

test('throws exception for template that generates invalid JSON', function () {
    $template = '{
        "title": "{{title}}"
        // Missing comma
        "id": "{{id}}"
    }';

    $data = [
        'title' => 'Test',
        'id' => '123',
    ];

    $this->compiler->compile($template, $data);
})->throws(Exception::class, 'Invalid JSON generated');

test('compiles with custom helpers', function () {
    $template = '{
        "title": "{{uppercase title}}",
        "count": {{length items}}
    }';

    $data = [
        'title' => 'test document',
        'items' => [1, 2, 3, 4, 5],
    ];

    $result = $this->compiler->compileWithHelpers($template, $data);

    expect($result)->toBeArray()
        ->and($result['title'])->toBe('TEST DOCUMENT')
        ->and($result['count'])->toBe(5);
});
