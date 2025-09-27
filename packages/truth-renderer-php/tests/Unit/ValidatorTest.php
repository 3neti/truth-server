<?php

use TruthRenderer\Validation\Validator;

beforeEach(function () {
    $this->validator = new Validator();
});

it('is a no-op when schema is null', function () {
    $data = ['hello' => 'world'];
    expect(fn () => $this->validator->validate($data, null))->not->toThrow(\Throwable::class);
});

it('validates a simple object successfully', function () {
    $schema = [
        '$schema'   => 'http://json-schema.org/draft-07/schema#',
        'type'      => 'object',
        'required'  => ['code', 'amount'],
        'properties'=> [
            'code'   => ['type' => 'string'],
            'amount' => ['type' => 'number'],
        ],
        'additionalProperties' => false,
    ];

    $data = [
        'code'   => 'DEMO-001',
        'amount' => 12.5,
    ];

    expect(fn () => $this->validator->validate($data, $schema))->not->toThrow(\Throwable::class);
});

it('fails with clear messages when required fields are missing', function () {
    $schema = [
        '$schema'   => 'http://json-schema.org/draft-07/schema#',
        'type'      => 'object',
        'required'  => ['code', 'amount'],
        'properties'=> [
            'code'   => ['type' => 'string'],
            'amount' => ['type' => 'number'],
        ],
    ];

    $data = [
        'code' => 'DEMO-001',
        // missing amount
    ];

    $call = fn () => $this->validator->validate($data, $schema);

    expect($call)->toThrow(\RuntimeException::class);
    try { $call(); }
    catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('Schema validation failed');
        expect($e->getMessage())->toContain('amount'); // mentions missing field
    }
});

it('fails on type mismatch with helpful output', function () {
    $schema = [
        '$schema'   => 'http://json-schema.org/draft-07/schema#',
        'type'      => 'object',
        'properties'=> [
            'count' => ['type' => 'integer'],
        ],
    ];

    $data = [
        'count' => 'not-an-integer',
    ];

    expect(fn () => $this->validator->validate($data, $schema))
        ->toThrow(\RuntimeException::class);
});

it('validates nested structures and coerces arrays to objects internally', function () {
    $schema = [
        '$schema' => 'http://json-schema.org/draft-07/schema#',
        'type'    => 'object',
        'properties' => [
            'meta' => [
                'type' => 'object',
                'properties' => [
                    'tags' => [
                        'type'  => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'flags' => [
                        'type' => 'object',
                        'properties' => [
                            'archived' => ['type' => 'boolean'],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $data = [
        'meta' => [
            'tags'  => ['a', 'b', 'c'],
            'flags' => ['archived' => false],
        ],
    ];

    expect(fn () => $this->validator->validate($data, $schema))->not->toThrow(\Throwable::class);
});

it('validates enums', function () {
    $schema = [
        '$schema' => 'http://json-schema.org/draft-07/schema#',
        'type'    => 'object',
        'properties' => [
            'format' => [
                'type' => 'string',
                'enum' => ['pdf', 'html', 'md'],
            ],
        ],
        'required' => ['format'],
    ];

    expect(fn () => $this->validator->validate(['format' => 'pdf'], $schema))
        ->not->toThrow(\Throwable::class);

    expect(fn () => $this->validator->validate(['format' => 'docx'], $schema))
        ->toThrow(\RuntimeException::class);
});
