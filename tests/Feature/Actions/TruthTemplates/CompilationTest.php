<?php

use App\Actions\TruthTemplates\Compilation\CompileHandlebarsTemplate;
use App\Actions\TruthTemplates\Compilation\CompileStandaloneData;

describe('CompileHandlebarsTemplate Action', function () {
    it('compiles a valid OMR template', function () {
        $template = '{
            "document": {
                "title": "{{title}}",
                "unique_id": "TEST-001",
                "layout": "portrait"
            },
            "sections": []
        }';
        $data = ['title' => 'Test Template'];

        $spec = CompileHandlebarsTemplate::make()->handle($template, $data);

        expect($spec)->toBeArray()
            ->and($spec)->toHaveKey('document')
            ->and($spec['document']['title'])->toBe('Test Template');
    });

    it('works via asController with valid template', function () {
        $response = $this->postJson('/api/truth-templates/compile', [
            'template' => '{
                "document": {
                    "title": "{{title}}",
                    "unique_id": "TEST-001"
                },
                "sections": []
            }',
            'data' => ['title' => 'Test Ballot'],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'spec' => [
                    'document',
                    'sections'
                ]
            ]);
    });
});

describe('CompileStandaloneData Action', function () {
    it('compiles with template reference', function () {
        // This would require a template in the database
        // Skipping for now as it needs fixture setup
    })->skip('Requires database fixtures');
});
