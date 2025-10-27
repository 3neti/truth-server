<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateValidationSigningTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_json_schema_validation_passes_with_valid_data()
    {
        $user = User::factory()->create();
        
        $template = Template::factory()->create([
            'user_id' => $user->id,
            'json_schema' => [
                'type' => 'object',
                'required' => ['title', 'year'],
                'properties' => [
                    'title' => ['type' => 'string', 'minLength' => 3],
                    'year' => ['type' => 'integer', 'minimum' => 2000, 'maximum' => 2100],
                ]
            ]
        ]);

        $validData = ['title' => 'Test Election', 'year' => 2025];
        $result = $template->validateData($validData);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_template_json_schema_validation_fails_with_invalid_data()
    {
        $user = User::factory()->create();
        
        $template = Template::factory()->create([
            'user_id' => $user->id,
            'json_schema' => [
                'type' => 'object',
                'required' => ['title', 'year'],
                'properties' => [
                    'title' => ['type' => 'string', 'minLength' => 3],
                    'year' => ['type' => 'integer', 'minimum' => 2000, 'maximum' => 2100],
                ]
            ]
        ]);

        $invalidData = ['title' => 'AB', 'year' => 1999];
        $result = $template->validateData($invalidData);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertContains("Field 'title' must be at least 3 characters", $result['errors']);
        $this->assertContains("Field 'year' must be >= 2000", $result['errors']);
    }

    public function test_template_can_be_signed()
    {
        $user = User::factory()->create();
        
        $template = Template::factory()->create([
            'user_id' => $user->id,
            'handlebars_template' => '<div>Test</div>',
        ]);

        $this->assertFalse($template->isSigned());

        $template->sign($user->id);

        $this->assertTrue($template->isSigned());
        $this->assertNotNull($template->checksum_sha256);
        $this->assertNotNull($template->verified_at);
        $this->assertEquals($user->id, $template->verified_by);
    }

    public function test_template_checksum_verification()
    {
        $user = User::factory()->create();
        
        $template = Template::factory()->create([
            'user_id' => $user->id,
            'handlebars_template' => '<div>Test</div>',
        ]);

        $template->sign($user->id);
        $this->assertTrue($template->verifyChecksum());

        // Modify template
        $template->handlebars_template = '<div>Modified</div>';
        $this->assertFalse($template->verifyChecksum());
        $this->assertTrue($template->isModified());
    }

    public function test_validation_api_endpoint()
    {
        $user = User::factory()->create();
        
        $template = Template::factory()->create([
            'user_id' => $user->id,
            'json_schema' => [
                'type' => 'object',
                'required' => ['title'],
                'properties' => [
                    'title' => ['type' => 'string'],
                ]
            ]
        ]);

        $response = $this->postJson("/api/truth-templates/templates/{$template->id}/validate-data", [
            'data' => ['title' => 'Valid Title']
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'valid' => true,
                'has_schema' => true,
            ]);
    }

    public function test_signing_api_endpoint()
    {
        $user = User::factory()->create();
        
        $template = Template::factory()->create([
            'user_id' => $user->id,
            'handlebars_template' => '<div>Test</div>',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/truth-templates/templates/{$template->id}/sign");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'checksum',
                'verified_at',
                'message'
            ]);

        $template->refresh();
        $this->assertTrue($template->isSigned());
    }

    public function test_verify_api_endpoint()
    {
        $user = User::factory()->create();
        
        $template = Template::factory()->create([
            'user_id' => $user->id,
            'handlebars_template' => '<div>Test</div>',
        ]);

        $template->sign($user->id);

        $response = $this->getJson("/api/truth-templates/templates/{$template->id}/verify");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_signed' => true,
                'is_valid' => true,
                'is_modified' => false,
            ]);
    }
}
