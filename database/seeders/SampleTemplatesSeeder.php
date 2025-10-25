<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\TemplateFamily;
use App\Models\User;
use Illuminate\Database\Seeder;

class SampleTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $this->command->info('Creating sample templates...');

        // Get or create a user
        $user = User::first();
        if (!$user) {
            $user = User::factory()->create([
                'name' => 'Demo User',
                'email' => 'demo@truth.app',
            ]);
        }

        // 1. Local Family: National Elections 2025
        $this->createNationalElectionFamily($user);

        // 2. Local Family: Customer Survey
        $this->createCustomerSurveyFamily($user);

        // 3. Remote Family Example (GitHub)
        $this->createRemoteFamilyExample($user);

        // 4. Hybrid Family Example
        $this->createHybridFamilyExample($user);

        $this->command->info('✅ Sample templates created successfully!');
        $this->command->info('');
        $this->command->table(
            ['Family', 'Type', 'Variants', 'Category'],
            [
                ['National Elections 2025', 'Local', '3', 'ballot'],
                ['Customer Survey 2025', 'Local', '2', 'survey'],
                ['COMELEC Official Ballot', 'Remote', '2', 'ballot'],
                ['Regional Ballot (Hybrid)', 'Hybrid', '3', 'ballot'],
            ]
        );
    }

    private function createNationalElectionFamily(User $user): void
    {
        $family = TemplateFamily::create([
            'slug' => 'national-election-2025',
            'name' => 'National Elections 2025',
            'description' => 'Official ballot templates for the 2025 National Elections. Includes multiple layout variants for different paper sizes and printer capabilities.',
            'category' => 'ballot',
            'version' => '1.0.0',
            'is_public' => true,
            'storage_type' => 'local',
            'user_id' => $user->id,
        ]);

        // Single Column Variant
        $template1 = Template::create([
            'name' => 'National Elections 2025 (Single Column)',
            'description' => 'Single column layout for narrow ballots',
            'category' => 'ballot',
            'family_id' => $family->id,
            'layout_variant' => 'single-column',
            'storage_type' => 'local',
            'handlebars_template' => $this->getSingleColumnTemplate(),
            'sample_data' => $this->getNationalElectionSampleData(),
            'json_schema' => $this->getNationalElectionSchema(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);

        // Two Column Variant
        Template::create([
            'name' => 'National Elections 2025 (Two Column)',
            'description' => 'Two column layout for standard 8.5x11 paper',
            'category' => 'ballot',
            'family_id' => $family->id,
            'layout_variant' => 'two-column',
            'storage_type' => 'local',
            'handlebars_template' => $this->getTwoColumnTemplate(),
            'sample_data' => $this->getNationalElectionSampleData(),
            'json_schema' => $this->getNationalElectionSchema(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);

        // Three Column Variant
        Template::create([
            'name' => 'National Elections 2025 (Three Column)',
            'description' => 'Three column layout for wide format ballots',
            'category' => 'ballot',
            'family_id' => $family->id,
            'layout_variant' => 'three-column',
            'storage_type' => 'local',
            'handlebars_template' => $this->getThreeColumnTemplate(),
            'sample_data' => $this->getNationalElectionSampleData(),
            'json_schema' => $this->getNationalElectionSchema(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);

        // Sign the first template
        $template1->sign($user->id);
    }

    private function createCustomerSurveyFamily(User $user): void
    {
        $family = TemplateFamily::create([
            'slug' => 'customer-survey-2025',
            'name' => 'Customer Survey 2025',
            'description' => 'Standard customer satisfaction survey with rating scales and open-ended questions.',
            'category' => 'survey',
            'version' => '1.0.0',
            'is_public' => true,
            'storage_type' => 'local',
            'user_id' => $user->id,
        ]);

        // Standard Variant
        Template::create([
            'name' => 'Customer Survey 2025 (Standard)',
            'description' => 'Standard survey layout',
            'category' => 'survey',
            'family_id' => $family->id,
            'layout_variant' => 'standard',
            'storage_type' => 'local',
            'handlebars_template' => $this->getSurveyTemplate(),
            'sample_data' => $this->getSurveySampleData(),
            'json_schema' => $this->getSurveySchema(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);

        // Compact Variant
        Template::create([
            'name' => 'Customer Survey 2025 (Compact)',
            'description' => 'Space-saving compact layout',
            'category' => 'survey',
            'family_id' => $family->id,
            'layout_variant' => 'compact',
            'storage_type' => 'local',
            'handlebars_template' => $this->getSurveyCompactTemplate(),
            'sample_data' => $this->getSurveySampleData(),
            'json_schema' => $this->getSurveySchema(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);
    }

    private function createRemoteFamilyExample(User $user): void
    {
        $family = TemplateFamily::create([
            'slug' => 'comelec-official-ballot-2025',
            'name' => 'COMELEC Official Ballot 2025',
            'description' => 'Official ballot templates from COMELEC GitHub repository. Templates are fetched remotely and cached locally.',
            'category' => 'ballot',
            'version' => 'v1.0.0',
            'is_public' => true,
            'storage_type' => 'remote',
            'repo_url' => 'https://github.com/example-org/ballot-templates',
            'repo_provider' => 'github',
            'repo_path' => 'ballot-2025',
            'user_id' => $user->id,
        ]);

        // Remote template example (note: these won't work without actual GitHub repo)
        Template::create([
            'name' => 'COMELEC Ballot (Single Column)',
            'description' => 'Official single column ballot from COMELEC',
            'category' => 'ballot',
            'family_id' => $family->id,
            'layout_variant' => 'single-column',
            'storage_type' => 'remote',
            'template_uri' => 'github:example-org/ballot-templates/ballot-2025/single-column.hbs@v1.0.0',
            'sample_data' => $this->getNationalElectionSampleData(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);

        Template::create([
            'name' => 'COMELEC Ballot (Two Column)',
            'description' => 'Official two column ballot from COMELEC',
            'category' => 'ballot',
            'family_id' => $family->id,
            'layout_variant' => 'two-column',
            'storage_type' => 'remote',
            'template_uri' => 'github:example-org/ballot-templates/ballot-2025/two-column.hbs@v1.0.0',
            'sample_data' => $this->getNationalElectionSampleData(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);
    }

    private function createHybridFamilyExample(User $user): void
    {
        $family = TemplateFamily::create([
            'slug' => 'regional-ballot-hybrid',
            'name' => 'Regional Ballot (Hybrid)',
            'description' => 'Hybrid family with official remote templates plus custom local variants.',
            'category' => 'ballot',
            'version' => '1.0.0',
            'is_public' => true,
            'storage_type' => 'hybrid',
            'repo_url' => 'https://github.com/example-org/regional-templates',
            'repo_provider' => 'github',
            'user_id' => $user->id,
        ]);

        // Remote variant
        Template::create([
            'name' => 'Regional Ballot (Official)',
            'description' => 'Official template from central repository',
            'category' => 'ballot',
            'family_id' => $family->id,
            'layout_variant' => 'official',
            'storage_type' => 'remote',
            'template_uri' => 'github:example-org/regional-templates/ballot-standard.hbs@v1.0.0',
            'sample_data' => $this->getNationalElectionSampleData(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);

        // Local custom variants
        Template::create([
            'name' => 'Regional Ballot (Custom Single)',
            'description' => 'Customized single column for special precincts',
            'category' => 'ballot',
            'family_id' => $family->id,
            'layout_variant' => 'custom-single',
            'storage_type' => 'local',
            'handlebars_template' => $this->getSingleColumnTemplate(),
            'sample_data' => $this->getNationalElectionSampleData(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);

        Template::create([
            'name' => 'Regional Ballot (High Density)',
            'description' => 'Custom high-density layout for urban areas',
            'category' => 'ballot',
            'family_id' => $family->id,
            'layout_variant' => 'high-density',
            'storage_type' => 'local',
            'handlebars_template' => $this->getThreeColumnTemplate(),
            'sample_data' => $this->getNationalElectionSampleData(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);
    }

    // Template content methods
    private function getSingleColumnTemplate(): string
    {
        return <<<'JSON'
{
  "document": {
    "title": "{{election_name}}",
    "unique_id": "{{precinct}}-{{date}}",
    "date": "{{date}}",
    "precinct": "{{precinct}}",
    "layout": "single-column"
  },
  "sections": [
    {
      "type": "multiple_choice",
      "code": "CANDIDATES",
      "title": "Candidates",
      "question": "Vote for your candidates",
      "maxSelections": 6,
      "layout": "single-column",
      "choices": [
        {{#each candidates}}
        {
          "code": "{{this.position}}",
          "label": "{{this.name}}",
          "description": "{{this.party}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }
  ]
}
JSON;
    }

    private function getTwoColumnTemplate(): string
    {
        return <<<'JSON'
{
  "document": {
    "title": "{{election_name}}",
    "unique_id": "{{precinct}}-{{date}}",
    "date": "{{date}}",
    "precinct": "{{precinct}}",
    "layout": "2-column"
  },
  "sections": [
    {
      "type": "multiple_choice",
      "code": "CANDIDATES",
      "title": "Candidates",
      "question": "Vote for your candidates",
      "maxSelections": 6,
      "layout": "2-column",
      "choices": [
        {{#each candidates}}
        {
          "code": "{{this.position}}",
          "label": "{{this.name}}",
          "description": "{{this.party}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }
  ]
}
JSON;
    }

    private function getThreeColumnTemplate(): string
    {
        return <<<'JSON'
{
  "document": {
    "title": "{{election_name}}",
    "unique_id": "{{precinct}}-{{date}}",
    "date": "{{date}}",
    "precinct": "{{precinct}}",
    "layout": "3-column"
  },
  "sections": [
    {
      "type": "multiple_choice",
      "code": "CANDIDATES",
      "title": "Candidates",
      "question": "Vote for your candidates",
      "maxSelections": 6,
      "layout": "3-column",
      "choices": [
        {{#each candidates}}
        {
          "code": "{{this.position}}",
          "label": "{{this.name}}",
          "description": "{{this.party}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }
  ]
}
JSON;
    }

    private function getSurveyTemplate(): string
    {
        return <<<'JSON'
{
  "document": {
    "title": "{{survey_title}}",
    "unique_id": "survey-{{date}}",
    "date": "{{date}}",
    "description": "{{description}}",
    "layout": "standard"
  },
  "sections": [
    {{#each questions}}
    {
      "type": "multiple_choice",
      "code": "Q{{this.number}}",
      "title": "Question {{this.number}}",
      "question": "{{this.text}}",
      "maxSelections": 1,
      "layout": "single-column",
      "choices": [
        {{#each this.options}}
        {
          "code": "OPT_{{@index}}",
          "label": "{{this.label}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
JSON;
    }

    private function getSurveyCompactTemplate(): string
    {
        return <<<'JSON'
{
  "document": {
    "title": "{{survey_title}}",
    "unique_id": "survey-{{date}}",
    "date": "{{date}}",
    "description": "{{description}}",
    "layout": "compact"
  },
  "sections": [
    {{#each questions}}
    {
      "type": "multiple_choice",
      "code": "Q{{this.number}}",
      "title": "Question {{this.number}}",
      "question": "{{this.text}}",
      "maxSelections": 1,
      "layout": "inline",
      "choices": [
        {{#each this.options}}
        {
          "code": "OPT_{{@index}}",
          "label": "{{this.label}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
JSON;
    }

    // Sample data methods
    private function getNationalElectionSampleData(): array
    {
        return [
            'election_name' => 'National Elections 2025',
            'precinct' => '001-A',
            'date' => '2025-05-15',
            'candidates' => [
                ['position' => 1, 'name' => 'Maria Santos', 'party' => 'Progressive Party'],
                ['position' => 2, 'name' => 'Juan Dela Cruz', 'party' => 'Democratic Alliance'],
                ['position' => 3, 'name' => 'Ana Rodriguez', 'party' => 'Independent'],
                ['position' => 4, 'name' => 'Pedro Gomez', 'party' => 'Reform Coalition'],
                ['position' => 5, 'name' => 'Lisa Fernandez', 'party' => 'Green Party'],
                ['position' => 6, 'name' => 'Carlos Reyes', 'party' => 'Labor Party'],
            ]
        ];
    }

    private function getSurveySampleData(): array
    {
        return [
            'survey_title' => 'Customer Satisfaction Survey 2025',
            'description' => 'Help us improve our services by sharing your experience',
            'date' => '2025-10-24',
            'questions' => [
                [
                    'number' => 1,
                    'text' => 'How satisfied are you with our service?',
                    'options' => [
                        ['label' => 'Very Satisfied'],
                        ['label' => 'Satisfied'],
                        ['label' => 'Neutral'],
                        ['label' => 'Dissatisfied'],
                        ['label' => 'Very Dissatisfied'],
                    ]
                ],
                [
                    'number' => 2,
                    'text' => 'Would you recommend us to others?',
                    'options' => [
                        ['label' => 'Definitely Yes'],
                        ['label' => 'Probably Yes'],
                        ['label' => 'Not Sure'],
                        ['label' => 'Probably No'],
                        ['label' => 'Definitely No'],
                    ]
                ],
                [
                    'number' => 3,
                    'text' => 'How would you rate our staff?',
                    'options' => [
                        ['label' => 'Excellent'],
                        ['label' => 'Good'],
                        ['label' => 'Average'],
                        ['label' => 'Poor'],
                    ]
                ],
            ]
        ];
    }

    // JSON Schema methods
    private function getNationalElectionSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['election_name', 'precinct', 'date', 'candidates'],
            'properties' => [
                'election_name' => [
                    'type' => 'string',
                    'minLength' => 5,
                    'maxLength' => 200
                ],
                'precinct' => [
                    'type' => 'string',
                    'pattern' => '^[0-9]{3}-[A-Z]$'
                ],
                'date' => [
                    'type' => 'string',
                    'pattern' => '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
                ],
                'candidates' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 100,
                    'items' => [
                        'type' => 'object',
                        'required' => ['position', 'name', 'party'],
                        'properties' => [
                            'position' => ['type' => 'integer', 'minimum' => 1],
                            'name' => ['type' => 'string', 'minLength' => 2],
                            'party' => ['type' => 'string']
                        ]
                    ]
                ]
            ]
        ];
    }

    private function getSurveySchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['survey_title', 'questions'],
            'properties' => [
                'survey_title' => [
                    'type' => 'string',
                    'minLength' => 5
                ],
                'description' => [
                    'type' => 'string'
                ],
                'date' => [
                    'type' => 'string'
                ],
                'questions' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'type' => 'object',
                        'required' => ['number', 'text', 'options'],
                        'properties' => [
                            'number' => ['type' => 'integer', 'minimum' => 1],
                            'text' => ['type' => 'string'],
                            'options' => [
                                'type' => 'array',
                                'minItems' => 2,
                                'items' => [
                                    'type' => 'object',
                                    'required' => ['label'],
                                    'properties' => [
                                        'label' => ['type' => 'string']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
