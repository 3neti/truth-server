<?php

use App\Models\Template;
use App\Models\TemplateData;
use App\Models\TemplateFamily;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('TemplateFamilyFactory', function () {
    it('can create a template family', function () {
        $family = TemplateFamily::factory()->create();

        expect($family)->toBeInstanceOf(TemplateFamily::class)
            ->and($family->slug)->not->toBeNull()
            ->and($family->name)->not->toBeNull()
            ->and($family->category)->toBeIn(['election', 'survey', 'test']);
    });

    it('can create an election family', function () {
        $family = TemplateFamily::factory()->election()->create();

        expect($family->category)->toBe('election')
            ->and($family->name)->toContain('Election');
    });

    it('can create a survey family', function () {
        $family = TemplateFamily::factory()->survey()->create();

        expect($family->category)->toBe('survey')
            ->and($family->name)->toContain('Survey');
    });

    it('can create a test family', function () {
        $family = TemplateFamily::factory()->test()->create();

        expect($family->category)->toBe('test')
            ->and($family->name)->toContain('Test');
    });

    it('can create a remote family', function () {
        $family = TemplateFamily::factory()->remote()->create();

        expect($family->storage_type)->toBe('remote')
            ->and($family->repo_url)->not->toBeNull()
            ->and($family->repo_provider)->toBe('github');
    });

    it('generates unique slugs', function () {
        $family1 = TemplateFamily::factory()->create();
        $family2 = TemplateFamily::factory()->create();

        expect($family1->slug)->not->toBe($family2->slug);
    });
});

describe('TemplateFactory', function () {
    it('can create a template', function () {
        $template = Template::factory()->create();

        expect($template)->toBeInstanceOf(Template::class)
            ->and($template->name)->not->toBeNull()
            ->and($template->category)->toBeIn(['election', 'survey', 'test'])
            ->and($template->handlebars_template)->not->toBeNull()
            ->and($template->version)->toBe('1.0.0');
    });

    it('can create an election template', function () {
        $template = Template::factory()->election()->create();

        expect($template->category)->toBe('election')
            ->and($template->name)->toContain('Election');
    });

    it('can create a template with a family', function () {
        $family = TemplateFamily::factory()->create();
        $template = Template::factory()->create(['family_id' => $family->id]);

        expect($template->family_id)->toBe($family->id)
            ->and($template->family)->toBeInstanceOf(TemplateFamily::class);
    });

    it('creates valid handlebars template', function () {
        $template = Template::factory()->create();
        $content = $template->handlebars_template;

        expect($content)->toContain('document')
            ->and($content)->toContain('sections');
    });

    it('creates valid sample data', function () {
        $template = Template::factory()->create();

        expect($template->sample_data)->toBeArray()
            ->and($template->sample_data)->toHaveKeys(['title', 'id', 'date']);
    });
});

describe('TemplateDataFactory', function () {
    it('can create template data', function () {
        $data = TemplateData::factory()->create();

        expect($data)->toBeInstanceOf(TemplateData::class)
            ->and($data->name)->not->toBeNull()
            ->and($data->template_ref)->not->toBeNull()
            ->and($data->data)->toBeArray();
    });

    it('can create election data', function () {
        $data = TemplateData::factory()->election()->create();

        expect($data->category)->toBe('election')
            ->and($data->data)->toHaveKey('election_name')
            ->and($data->data)->toHaveKey('positions');
    });

    it('can create survey data', function () {
        $data = TemplateData::factory()->survey()->create();

        expect($data->category)->toBe('survey')
            ->and($data->data)->toHaveKey('survey_title')
            ->and($data->data)->toHaveKey('questions');
    });

    it('can create test data', function () {
        $data = TemplateData::factory()->test()->create();

        expect($data->category)->toBe('test')
            ->and($data->data)->toHaveKey('test_title')
            ->and($data->data)->toHaveKey('questions');
    });

    it('creates data with document structure', function () {
        $data = TemplateData::factory()->create();

        expect($data->data)->toHaveKey('document')
            ->and($data->data['document'])->toHaveKey('template_ref');
    });

    it('can create public data', function () {
        $data = TemplateData::factory()->public()->create();

        expect($data->is_public)->toBeTrue();
    });

    it('can create private data', function () {
        $data = TemplateData::factory()->private()->create();

        expect($data->is_public)->toBeFalse();
    });
});
