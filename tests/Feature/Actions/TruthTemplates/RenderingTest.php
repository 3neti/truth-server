<?php

use App\Actions\TruthTemplates\Rendering\RenderTemplateSpec;
use App\Actions\TruthTemplates\Rendering\ValidateTemplateSpec;

describe('RenderTemplateSpec Action', function () {
    it('validates a proper spec structure', function () {
        $spec = [
            'document' => [
                'title' => 'Test Ballot',
                'unique_id' => 'TEST-001',
                'layout' => 'portrait',
            ],
            'sections' => [
                [
                    'type' => 'single-choice',
                    'code' => 'PRESIDENT',
                    'title' => 'President',
                    'choices' => [
                        ['code' => 'A', 'label' => 'Candidate A'],
                        ['code' => 'B', 'label' => 'Candidate B'],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/truth-templates/validate', [
            'spec' => $spec,
        ]);

        $response->assertStatus(200)
            ->assertJson(['valid' => true]);
    });

    it('renders a spec and generates PDF', function () {
        $spec = [
            'document' => [
                'title' => 'Test Ballot',
                'unique_id' => 'TEST-RENDER-001',
                'layout' => 'portrait',
            ],
            'sections' => [
                [
                    'type' => 'single-choice',
                    'code' => 'PRESIDENT',
                    'title' => 'President',
                    'choices' => [
                        ['code' => 'A', 'label' => 'Candidate A'],
                        ['code' => 'B', 'label' => 'Candidate B'],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/truth-templates/render', [
            'spec' => $spec,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'document_id',
                'pdf_url',
                'coords_url',
            ]);
    });
});
