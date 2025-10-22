<?php

use LBHurtado\OMRTemplate\Data\TemplateData;
use LBHurtado\OMRTemplate\Data\ZoneMapData;
use LBHurtado\OMRTemplate\Services\FiducialHelper;
use LBHurtado\OMRTemplate\Services\TemplateExporter;
use LBHurtado\OMRTemplate\Services\TemplateRenderer;

test('can generate complete output bundle', function () {
    $templateData = new TemplateData(
        template_id: 'test-template',
        document_type: 'ballot',
        contests_or_sections: [
            [
                'title' => 'President',
                'candidates' => [
                    ['name' => 'John Doe', 'party' => 'Party A'],
                    ['name' => 'Jane Smith', 'party' => 'Party B'],
                ],
            ],
        ],
        document_id: 'BALLOT-TEST-001-PDF-123',
        layout: 'A4',
    );

    $html = '<html><body>Test Document</body></html>';

    $zoneMap = new ZoneMapData(
        template_id: 'test-template',
        document_type: 'ballot',
        zones: [
            [
                'section' => 'PRESIDENT',
                'code' => 'CAND001',
                'x' => 100,
                'y' => 200,
                'width' => 50,
                'height' => 50,
            ],
        ],
    );

    $exporter = app(TemplateExporter::class);
    $output = $exporter->export($html, $zoneMap, ['test' => true]);

    expect($output->html)->toBe($html);
    expect($output->zoneMap->template_id)->toBe('test-template');
    expect($output->metadata)->toHaveKey('test');
});

test('template data properties are accessible', function () {
    $templateData = new TemplateData(
        template_id: 'test',
        document_type: 'ballot',
        contests_or_sections: [],
        document_id: 'TEST-DOC-001-PDF-001',
    );

    expect($templateData->template_id)->toBe('test');
    expect($templateData->document_type)->toBe('ballot');
    expect($templateData->contests_or_sections)->toBe([]);
    expect($templateData->layout)->toBe('A4');
});

test('zone map data can be converted to json', function () {
    $zoneMap = new ZoneMapData(
        template_id: 'test',
        document_type: 'ballot',
        zones: [['x' => 10, 'y' => 20]],
    );

    $json = $zoneMap->toJson();

    expect($json)->toBeString();
    
    $decoded = json_decode($json, true);
    expect($decoded)->toHaveKey('template_id');
    expect($decoded['zones'])->toHaveCount(1);
});

test('zone map includes fiducials when provided', function () {
    $fiducials = [
        ['id' => 'top_left', 'x' => 100, 'y' => 100, 'width' => 50, 'height' => 50],
        ['id' => 'top_right', 'x' => 2000, 'y' => 100, 'width' => 50, 'height' => 50],
    ];

    $zoneMap = new ZoneMapData(
        template_id: 'test',
        document_type: 'ballot',
        zones: [],
        fiducials: $fiducials,
        size: 'A4',
        dpi: 300,
    );

    $json = $zoneMap->toJson();
    $decoded = json_decode($json, true);

    expect($decoded)->toHaveKey('fiducials');
    expect($decoded['fiducials'])->toHaveCount(2);
    expect($decoded['size'])->toBe('A4');
    expect($decoded['dpi'])->toBe(300);
});

test('fiducial helper generates correct number of markers', function () {
    $helper = app(FiducialHelper::class);
    
    $fiducials = $helper->generateFiducials('A4', 300);
    
    expect($fiducials)->toHaveCount(4);
    expect($fiducials[0]['id'])->toBe('top_left');
    expect($fiducials[3]['id'])->toBe('bottom_right');
});
