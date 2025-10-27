<?php

/**
 * Phase 2 Migration Tests - Validation & Compilation Actions
 * Tests error handling, status codes, and edge cases
 */

describe('ValidateTemplateSpec Action - Phase 2', function () {
    it('validates a valid template spec', function () {
        $response = $this->postJson('/api/truth-templates/validate', [
            'spec' => [
                'document' => [
                    'title' => 'Test Ballot',
                    'unique_id' => 'TEST-001',
                ],
                'sections' => [
                    [
                        'type' => 'single-choice',
                        'code' => 'TEST',
                        'title' => 'Test Section',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['valid' => true])
            ->assertJsonStructure(['valid', 'errors', 'message']);
    });

    it('rejects invalid spec with missing document', function () {
        $response = $this->postJson('/api/truth-templates/validate', [
            'spec' => [
                'sections' => [],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['spec.document']);
    });

    it('rejects spec with missing required section fields', function () {
        $response = $this->postJson('/api/truth-templates/validate', [
            'spec' => [
                'document' => [
                    'title' => 'Test',
                    'unique_id' => 'TEST-001',
                ],
                'sections' => [
                    ['type' => 'single-choice'], // Missing code and title
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJson(['valid' => false])
            ->assertJsonStructure(['valid', 'errors', 'message'])
            ->assertJsonFragment(['Section code is required'])
            ->assertJsonFragment(['Section title is required']);
    });

    it('rejects spec with empty sections array', function () {
        $response = $this->postJson('/api/truth-templates/validate', [
            'spec' => [
                'document' => [
                    'title' => 'Test',
                    'unique_id' => 'TEST-001',
                ],
                'sections' => [],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['spec.sections']);
    });
});

describe('CompileHandlebarsTemplate Action - Phase 2', function () {
    it('compiles template with simple data', function () {
        $response = $this->postJson('/api/truth-templates/compile', [
            'template' => '{"document": {"title": "{{title}}"}, "sections": []}',
            'data' => ['title' => 'Test Title'],
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'spec'])
            ->assertJsonPath('spec.document.title', 'Test Title');
    });

    it('compiles template with nested data structure', function () {
        $response = $this->postJson('/api/truth-templates/compile', [
            'template' => '{"document": {"title": "{{precinct.name}}"}, "sections": []}',
            'data' => [
                'data' => [
                    'precinct' => ['name' => 'Precinct 123'],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('spec.document.title', 'Precinct 123');
    });

    it('rejects compile request without template', function () {
        $response = $this->postJson('/api/truth-templates/compile', [
            'data' => ['title' => 'Test'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['template']);
    });

    it('rejects compile request without data', function () {
        $response = $this->postJson('/api/truth-templates/compile', [
            'template' => '{"document": {"title": "{{title}}"}}',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data']);
    });

    it('handles compilation errors gracefully', function () {
        // Invalid JSON in template
        $response = $this->postJson('/api/truth-templates/compile', [
            'template' => '{invalid json}',
            'data' => ['title' => 'Test'],
        ]);

        $response->assertStatus(500)
            ->assertJson(['success' => false])
            ->assertJsonStructure(['success', 'error']);
    });
});

describe('CompileStandaloneData Action - Phase 2', function () {
    it('rejects request without document.template_ref', function () {
        $response = $this->postJson('/api/truth-templates/compile-standalone', [
            'document' => [],
            'data' => ['title' => 'Test'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document.template_ref']);
    });

    it('rejects request without data', function () {
        $response = $this->postJson('/api/truth-templates/compile-standalone', [
            'document' => [
                'template_ref' => 'template:test-template',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data']);
    });

    it('handles invalid template reference gracefully', function () {
        $response = $this->postJson('/api/truth-templates/compile-standalone', [
            'document' => [
                'template_ref' => 'template:nonexistent-template',
            ],
            'data' => ['title' => 'Test'],
        ]);

        // Should return 500 for failed template resolution
        $response->assertStatus(500)
            ->assertJson(['success' => false])
            ->assertJsonStructure(['success', 'error']);
    });
});

describe('Phase 2 - Logging and Debugging', function () {
    it('logs compilation attempts', function () {
        // Clear logs
        file_put_contents(storage_path('logs/laravel.log'), '');

        $this->postJson('/api/truth-templates/compile', [
            'template' => '{"document": {"title": "{{title}}"}, "sections": []}',
            'data' => ['title' => 'Test'],
        ]);

        $log = file_get_contents(storage_path('logs/laravel.log'));
        expect($log)->toContain('Compiling template');
    });

    it('logs compilation errors', function () {
        // Clear logs
        file_put_contents(storage_path('logs/laravel.log'), '');

        $this->postJson('/api/truth-templates/compile', [
            'template' => '{invalid}',
            'data' => ['title' => 'Test'],
        ]);

        $log = file_get_contents(storage_path('logs/laravel.log'));
        expect($log)->toContain('Compilation failed');
    });
});
