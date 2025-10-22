<?php

use LBHurtado\OMRTemplate\Services\DocumentIdGenerator;

test('generates structured document ID', function () {
    $generator = new DocumentIdGenerator;
    
    $id = $generator->generate('BALLOT', 'ABC-001', 147);
    
    expect($id)->toBe('BALLOT-ABC-001-PDF-147');
});

test('pads serial numbers with zeros', function () {
    $generator = new DocumentIdGenerator;
    
    $id = $generator->generate('TEST', 'CLASS9', 7);
    
    expect($id)->toBe('TEST-CLASS9-PDF-007');
});

test('generates UUID-based document ID', function () {
    $generator = new DocumentIdGenerator;
    
    $id = $generator->generateUuid('SURVEY');
    
    expect($id)->toStartWith('SURVEY-');
    expect(strlen($id))->toBeGreaterThan(40); // UUID is 36 chars + prefix
});

test('generates from simple identifier', function () {
    $generator = new DocumentIdGenerator;
    
    $id = $generator->fromIdentifier('ABC-001', 'BALLOT');
    
    expect($id)->toStartWith('BALLOT-ABC-001-PDF-');
    expect($id)->toMatch('/^BALLOT-ABC-001-PDF-\d{3}$/');
});

test('returns full document ID as-is', function () {
    $generator = new DocumentIdGenerator;
    
    $fullId = 'BALLOT-XYZ-PDF-456';
    $id = $generator->fromIdentifier($fullId);
    
    expect($id)->toBe('BALLOT-XYZ-PDF-456');
});

test('can parse document ID into components', function () {
    $generator = new DocumentIdGenerator;
    
    $components = $generator->parse('BALLOT-ABC-001-PDF-147');
    
    expect($components)->toBe([
        'type' => 'BALLOT',
        'group' => 'ABC-001',
        'serial' => 147,
    ]);
});

test('parse returns null for invalid format', function () {
    $generator = new DocumentIdGenerator;
    
    $components = $generator->parse('INVALID-FORMAT');
    
    expect($components)->toBeNull();
});

test('validates correct document ID format', function () {
    $generator = new DocumentIdGenerator;
    
    expect($generator->isValid('BALLOT-ABC-001-PDF-147'))->toBeTrue();
    expect($generator->isValid('TEST-CLASS9-PDF-007'))->toBeTrue();
});

test('invalidates incorrect document ID format', function () {
    $generator = new DocumentIdGenerator;
    
    expect($generator->isValid('INVALID'))->toBeFalse();
    expect($generator->isValid('BALLOT-ABC-001'))->toBeFalse();
});

test('converts to uppercase', function () {
    $generator = new DocumentIdGenerator;
    
    $id = $generator->generate('ballot', 'abc-001', 147);
    
    expect($id)->toBe('BALLOT-ABC-001-PDF-147');
});
