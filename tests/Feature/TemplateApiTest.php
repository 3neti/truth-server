<?php

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can compile handlebars template with data', function () {
    $template = '{
        "document": {
            "title": "{{election.title}}",
            "unique_id": "{{election.id}}"
        }
    }';

    $data = [
        'election' => [
            'title' => '2025 General Election',
            'id' => 'BAL-2025-001',
        ],
    ];

    $response = $this->postJson('/api/truth-templates/compile', [
        'template' => $template,
        'data' => $data,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonPath('spec.document.title', '2025 General Election')
        ->assertJsonPath('spec.document.unique_id', 'BAL-2025-001');
});

test('compile endpoint validates required fields', function () {
    $response = $this->postJson('/api/truth-templates/compile', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['template', 'data']);
});

test('can list public templates', function () {
    // Create some templates
    Template::factory()->create([
        'name' => 'Public Template',
        'is_public' => true,
    ]);

    Template::factory()->create([
        'name' => 'Private Template',
        'is_public' => false,
    ]);

    $response = $this->getJson('/api/truth-templates/templates');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonCount(1, 'templates');
});

test('can get specific template by id', function () {
    $template = Template::factory()->create([
        'name' => 'Test Template',
        'category' => 'ballot',
    ]);

    $response = $this->getJson("/api/truth-templates/templates/{$template->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'template' => [
                'id' => $template->id,
                'name' => 'Test Template',
                'category' => 'ballot',
            ],
        ]);
});

test('returns 404 for non-existent template', function () {
    $response = $this->getJson('/api/truth-templates/templates/99999');

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'error' => 'Template not found',
        ]);
});

test('can save new template', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/truth-templates/templates', [
        'name' => 'New Template',
        'description' => 'A test template',
        'category' => 'ballot',
        'handlebars_template' => '{"title": "{{title}}"}',
        'sample_data' => ['title' => 'Sample'],
        'is_public' => true,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'template' => [
                'name' => 'New Template',
                'category' => 'ballot',
            ],
        ]);

    $this->assertDatabaseHas('templates', [
        'name' => 'New Template',
        'category' => 'ballot',
    ]);
});

test('save template validates required fields', function () {
    $response = $this->postJson('/api/truth-templates/templates', []);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ])
        ->assertJsonValidationErrors(['name', 'category', 'handlebars_template']);
});

test('can filter templates by category', function () {
    Template::factory()->create([
        'category' => 'ballot',
        'is_public' => true,
    ]);

    Template::factory()->create([
        'category' => 'survey',
        'is_public' => true,
    ]);

    $response = $this->getJson('/api/truth-templates/templates?category=ballot');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'templates');
});
