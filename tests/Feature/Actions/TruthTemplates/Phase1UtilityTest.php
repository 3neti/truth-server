<?php

/**
 * Phase 1 Migration Tests - Utility Actions
 * Tests for simple utility endpoints with no database dependencies
 */

describe('GetLayoutPresets Action - Phase 1', function () {
    it('returns available layout presets', function () {
        $response = $this->getJson('/api/truth-templates/layouts');

        $response->assertStatus(200)
            ->assertJsonStructure(['layouts']);
    });

    it('returns layouts as an array or object', function () {
        $response = $this->getJson('/api/truth-templates/layouts');

        $data = $response->json();
        expect($data)->toHaveKey('layouts');
    });
});

describe('GetSampleTemplates Action - Phase 1', function () {
    it('returns sample templates', function () {
        $response = $this->getJson('/api/truth-templates/samples');

        $response->assertStatus(200)
            ->assertJsonStructure(['samples']);
    });

    it('returns samples array with expected structure', function () {
        $response = $this->getJson('/api/truth-templates/samples');

        $data = $response->json();
        expect($data)->toHaveKey('samples')
            ->and($data['samples'])->toBeArray();

        // If samples exist, check structure
        if (count($data['samples']) > 0) {
            $sample = $data['samples'][0];
            expect($sample)->toHaveKeys(['name', 'filename', 'spec']);
        }
    });
});

describe('GetCoordinatesMap Action - Phase 1', function () {
    it('returns 404 for non-existent document', function () {
        $response = $this->getJson('/api/truth-templates/coords/NONEXISTENT-DOC-ID');

        $response->assertStatus(404)
            ->assertJsonStructure(['error']);
    });

    it('returns coordinates for existing document', function () {
        // First, render a document to create coordinates
        $spec = [
            'document' => [
                'title' => 'Test Ballot',
                'unique_id' => 'TEST-COORDS-001',
                'layout' => 'portrait',
            ],
            'sections' => [
                [
                    'type' => 'single-choice',
                    'code' => 'TEST',
                    'title' => 'Test Section',
                    'choices' => [
                        ['code' => 'A', 'label' => 'Option A'],
                    ],
                ],
            ],
        ];

        $renderResponse = $this->postJson('/api/truth-templates/render', ['spec' => $spec]);
        $documentId = $renderResponse->json('document_id');

        // Now test coordinates endpoint
        $response = $this->getJson("/api/truth-templates/coords/{$documentId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'document_id',
                'coordinates',
            ])
            ->assertJsonPath('document_id', $documentId);
    });
});

describe('DownloadRenderedPdf Action - Phase 1', function () {
    it('returns 404 for non-existent PDF', function () {
        $response = $this->getJson('/api/truth-templates/download/NONEXISTENT-PDF-ID');

        $response->assertStatus(404)
            ->assertJsonStructure(['error']);
    });

    it('downloads PDF for existing document', function () {
        // First, render a document to create PDF
        $spec = [
            'document' => [
                'title' => 'Test Ballot',
                'unique_id' => 'TEST-PDF-001',
                'layout' => 'portrait',
            ],
            'sections' => [
                [
                    'type' => 'single-choice',
                    'code' => 'TEST',
                    'title' => 'Test Section',
                    'choices' => [
                        ['code' => 'A', 'label' => 'Option A'],
                    ],
                ],
            ],
        ];

        $renderResponse = $this->postJson('/api/truth-templates/render', ['spec' => $spec]);
        $documentId = $renderResponse->json('document_id');

        // Now test download endpoint
        $response = $this->getJson("/api/truth-templates/download/{$documentId}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    });
});

describe('Phase 1 - Integration with Rendering', function () {
    it('generates complete document workflow', function () {
        // 1. Render a document
        $spec = [
            'document' => [
                'title' => 'Integration Test',
                'unique_id' => 'TEST-INTEGRATION-001',
                'layout' => 'portrait',
            ],
            'sections' => [
                [
                    'type' => 'single-choice',
                    'code' => 'Q1',
                    'title' => 'Question 1',
                    'choices' => [
                        ['code' => 'A', 'label' => 'Answer A'],
                        ['code' => 'B', 'label' => 'Answer B'],
                    ],
                ],
            ],
        ];

        $renderResponse = $this->postJson('/api/truth-templates/render', ['spec' => $spec]);
        $renderResponse->assertStatus(200);

        $documentId = $renderResponse->json('document_id');
        $pdfUrl = $renderResponse->json('pdf_url');
        $coordsUrl = $renderResponse->json('coords_url');

        // Verify URLs are properly formatted
        expect($pdfUrl)->toContain($documentId);
        expect($coordsUrl)->toContain($documentId);

        // 2. Verify coordinates are accessible
        $coordsResponse = $this->getJson("/api/truth-templates/coords/{$documentId}");
        $coordsResponse->assertStatus(200)
            ->assertJsonStructure(['document_id', 'coordinates']);

        // 3. Verify PDF is downloadable
        $pdfResponse = $this->getJson("/api/truth-templates/download/{$documentId}");
        $pdfResponse->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    });
});

describe('Phase 1 - Direct Action Invocation', function () {
    it('can call GetLayoutPresets::run() directly', function () {
        $layouts = \App\Actions\TruthTemplates\Templates\GetLayoutPresets::run();

        expect($layouts)->toBeArray();
    });

    it('can call GetSampleTemplates::run() directly', function () {
        $samples = \App\Actions\TruthTemplates\Templates\GetSampleTemplates::run();

        expect($samples)->toBeArray();
    });

    it('can call GetCoordinatesMap::run() with valid document', function () {
        // Create a document first
        $spec = [
            'document' => [
                'title' => 'Direct Call Test',
                'unique_id' => 'TEST-DIRECT-001',
            ],
            'sections' => [
                [
                    'type' => 'single-choice',
                    'code' => 'Q1',
                    'title' => 'Question',
                    'choices' => [['code' => 'A', 'label' => 'Option A']],
                ],
            ],
        ];

        $renderResponse = $this->postJson('/api/truth-templates/render', ['spec' => $spec]);
        $documentId = $renderResponse->json('document_id');

        // Call action directly
        $coords = \App\Actions\TruthTemplates\Rendering\GetCoordinatesMap::run($documentId);

        expect($coords)->toBeArray();
    });

    it('can call DownloadRenderedPdf::run() with valid document', function () {
        // Create a document first
        $spec = [
            'document' => [
                'title' => 'Direct Download Test',
                'unique_id' => 'TEST-DIRECT-DL-001',
            ],
            'sections' => [
                [
                    'type' => 'single-choice',
                    'code' => 'Q1',
                    'title' => 'Question',
                    'choices' => [['code' => 'A', 'label' => 'Option A']],
                ],
            ],
        ];

        $renderResponse = $this->postJson('/api/truth-templates/render', ['spec' => $spec]);
        $documentId = $renderResponse->json('document_id');

        // Call action directly
        $pdfPath = \App\Actions\TruthTemplates\Rendering\DownloadRenderedPdf::run($documentId);

        expect($pdfPath)->toBeString()
            ->and(file_exists($pdfPath))->toBeTrue()
            ->and($pdfPath)->toEndWith('.pdf');
    });
});
