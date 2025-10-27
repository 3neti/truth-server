<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\TemplateFamily;
use App\Models\User;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    /**
     * Seed template families and templates.
     */
    public function run(): void
    {
        $this->command->info('Creating template families and templates...');

        $user = User::where('email', 'admin@disburse.cash')->first();

        if (!$user) {
            $this->command->error('Admin user not found. Run UserSeeder first.');
            return;
        }

        // 1. Election Family
        $this->createElectionFamily($user);

        // 2. Philippine Election Ballot Templates
        $this->createPhilippineBallotTemplates($user);

        // 3. Survey Family
        $this->createSurveyFamily($user);

        // 4. Test/Exam Family
        $this->createTestFamily($user);

        $this->command->info('✅ Template families created successfully!');
        $this->command->table(
            ['Family', 'Variants', 'Category'],
            [
                ['Election Ballot', '4', 'election'],
                ['Survey Form', '2', 'survey'],
                ['Test/Exam', '2', 'test'],
            ]
        );
    }

    private function createPhilippineBallotTemplates(User $user): void
    {
        $family = TemplateFamily::where('slug', 'election-ballot')->first();

        if (!$family) {
            $this->command->warn('Election family not found, skipping Philippine templates');
            return;
        }

        // Questionnaire template
        Template::updateOrCreate(
            [
                'family_id' => $family->id,
                'layout_variant' => 'questionnaire',
            ],
            [
                'name' => 'Election Questionnaire - Candidate List',
                'description' => 'Full candidate list with all details for posting and reference',
                'category' => 'election',
                'storage_type' => 'local',
                'handlebars_template' => $this->getQuestionnaireTemplate(),
                'sample_data' => $this->getPhilippineBallotSampleData(),
                'json_schema' => $this->getPhilippineBallotSchema(),
                'is_public' => true,
                'user_id' => $user->id,
                'version' => '1.0.0',
            ]
        );

        // Answer sheet template
        Template::updateOrCreate(
            [
                'family_id' => $family->id,
                'layout_variant' => 'answer-sheet',
            ],
            [
                'name' => 'Election Ballot - Answer Sheet',
                'description' => 'Official ballot for voter marking with ovals for optical scanning',
                'category' => 'election',
                'storage_type' => 'local',
                'handlebars_template' => $this->getAnswerSheetTemplate(),
                'sample_data' => $this->getPhilippineBallotSampleData(),
                'json_schema' => $this->getPhilippineBallotSchema(),
                'is_public' => true,
                'user_id' => $user->id,
                'version' => '1.0.0',
            ]
        );

        $this->command->info('  ✓ Philippine ballot templates created');
    }

    private function createElectionFamily(User $user): void
    {
        $family = TemplateFamily::firstOrCreate(
            ['slug' => 'election-ballot'],
            [
            'slug' => 'election-ballot',
            'name' => 'Election Ballot',
            'description' => 'Templates for election ballots and voting forms',
            'category' => 'election',
            'version' => '1.0.0',
            'is_public' => true,
            'storage_type' => 'local',
            'user_id' => $user->id,
        ]);

        // Standard variant
        Template::create([
            'name' => 'Election Ballot (Standard)',
            'description' => 'Standard ballot layout with single column',
            'category' => 'election',
            'family_id' => $family->id,
            'layout_variant' => 'standard',
            'storage_type' => 'local',
            'handlebars_template' => $this->getElectionStandardTemplate(),
            'sample_data' => $this->getElectionSampleData(),
            'json_schema' => $this->getElectionSchema(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);

        // Compact variant
        Template::create([
            'name' => 'Election Ballot (Compact)',
            'description' => 'Space-efficient compact ballot layout',
            'category' => 'election',
            'family_id' => $family->id,
            'layout_variant' => 'compact',
            'storage_type' => 'local',
            'handlebars_template' => $this->getElectionCompactTemplate(),
            'sample_data' => $this->getElectionSampleData(),
            'json_schema' => $this->getElectionSchema(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);
    }

    private function createSurveyFamily(User $user): void
    {
        $family = TemplateFamily::firstOrCreate(
            ['slug' => 'survey-form'],
            [
            'slug' => 'survey-form',
            'name' => 'Survey Form',
            'description' => 'Templates for surveys, questionnaires, and feedback forms',
            'category' => 'survey',
            'version' => '1.0.0',
            'is_public' => true,
            'storage_type' => 'local',
            'user_id' => $user->id,
        ]);

        // Standard variant
        Template::create([
            'name' => 'Survey Form (Standard)',
            'description' => 'Standard survey layout with vertical questions',
            'category' => 'survey',
            'family_id' => $family->id,
            'layout_variant' => 'standard',
            'storage_type' => 'local',
            'handlebars_template' => $this->getSurveyStandardTemplate(),
            'sample_data' => $this->getSurveySampleData(),
            'json_schema' => $this->getSurveySchema(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);

        // Grid variant
        Template::create([
            'name' => 'Survey Form (Grid)',
            'description' => 'Grid layout for matrix-style questions',
            'category' => 'survey',
            'family_id' => $family->id,
            'layout_variant' => 'grid',
            'storage_type' => 'local',
            'handlebars_template' => $this->getSurveyGridTemplate(),
            'sample_data' => $this->getSurveySampleData(),
            'json_schema' => $this->getSurveySchema(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);
    }

    private function createTestFamily(User $user): void
    {
        $family = TemplateFamily::firstOrCreate(
            ['slug' => 'test-exam'],
            [
            'slug' => 'test-exam',
            'name' => 'Test/Exam',
            'description' => 'Templates for educational assessments, quizzes, and exams',
            'category' => 'test',
            'version' => '1.0.0',
            'is_public' => true,
            'storage_type' => 'local',
            'user_id' => $user->id,
        ]);

        // Standard variant
        Template::create([
            'name' => 'Test/Exam (Standard)',
            'description' => 'Standard test layout with mixed question types',
            'category' => 'test',
            'family_id' => $family->id,
            'layout_variant' => 'standard',
            'storage_type' => 'local',
            'handlebars_template' => $this->getTestStandardTemplate(),
            'sample_data' => $this->getTestSampleData(),
            'json_schema' => $this->getTestSchema(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);

        // Multiple choice variant
        Template::create([
            'name' => 'Test/Exam (Multiple Choice)',
            'description' => 'Optimized layout for multiple choice questions',
            'category' => 'test',
            'family_id' => $family->id,
            'layout_variant' => 'multiple-choice',
            'storage_type' => 'local',
            'handlebars_template' => $this->getTestMultipleChoiceTemplate(),
            'sample_data' => $this->getTestSampleData(),
            'json_schema' => $this->getTestSchema(),
            'is_public' => true,
            'user_id' => $user->id,
            'version' => '1.0.0',
        ]);
    }

    // Template Methods

    private function getElectionStandardTemplate(): string
    {
        return <<<'JSON'
{
  "document": {
    "title": "{{election_name}}",
    "unique_id": "{{precinct}}-{{date}}",
    "date": "{{date}}",
    "precinct": "{{precinct}}",
    "layout": "standard"
  },
  "sections": [
    {{#each positions}}
    {
      "type": "multiple_choice",
      "code": "{{this.code}}",
      "title": "{{this.title}}",
      "question": "Vote for {{this.title}}",
      "maxSelections": {{this.max_selections}},
      "layout": "single-column",
      "choices": [
        {{#each this.candidates}}
        {
          "code": "{{this.code}}",
          "label": "{{this.name}}",
          "description": "{{this.party}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
JSON;
    }

    private function getElectionCompactTemplate(): string
    {
        return <<<'JSON'
{
  "document": {
    "title": "{{election_name}}",
    "unique_id": "{{precinct}}-{{date}}",
    "date": "{{date}}",
    "precinct": "{{precinct}}",
    "layout": "compact"
  },
  "sections": [
    {{#each positions}}
    {
      "type": "multiple_choice",
      "code": "{{this.code}}",
      "title": "{{this.title}}",
      "question": "Vote for {{this.title}}",
      "maxSelections": {{this.max_selections}},
      "layout": "inline-compact",
      "choices": [
        {{#each this.candidates}}
        {
          "code": "{{this.code}}",
          "label": "{{this.name}}",
          "description": "{{this.party}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
JSON;
    }

    private function getSurveyStandardTemplate(): string
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
      "type": "{{this.type}}",
      "code": "Q{{this.number}}",
      "title": "Question {{this.number}}",
      "question": "{{this.text}}",
      "maxSelections": 1,
      "layout": "vertical",
      "choices": [
        {{#each this.options}}
        {
          "code": "OPT_{{@index}}",
          "label": "{{this}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
JSON;
    }

    private function getSurveyGridTemplate(): string
    {
        return <<<'JSON'
{
  "document": {
    "title": "{{survey_title}}",
    "unique_id": "survey-{{date}}",
    "date": "{{date}}",
    "description": "{{description}}",
    "layout": "grid"
  },
  "sections": [
    {{#each questions}}
    {
      "type": "{{this.type}}",
      "code": "Q{{this.number}}",
      "title": "Question {{this.number}}",
      "question": "{{this.text}}",
      "maxSelections": 1,
      "layout": "grid-horizontal",
      "choices": [
        {{#each this.options}}
        {
          "code": "OPT_{{@index}}",
          "label": "{{this}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
JSON;
    }

    private function getTestStandardTemplate(): string
    {
        return <<<'JSON'
{
  "document": {
    "title": "{{test_title}}",
    "unique_id": "test-{{date}}",
    "date": "{{date}}",
    "subject": "{{subject}}",
    "duration": "{{duration}}",
    "layout": "standard"
  },
  "sections": [
    {{#each questions}}
    {
      "type": "multiple_choice",
      "code": "Q{{this.number}}",
      "title": "Question {{this.number}}",
      "question": "{{this.text}}",
      "points": {{this.points}},
      "maxSelections": 1,
      "layout": "vertical",
      "choices": [
        {{#each this.options}}
        {
          "code": "OPT_{{@index}}",
          "label": "{{this}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
JSON;
    }

    private function getTestMultipleChoiceTemplate(): string
    {
        return <<<'JSON'
{
  "document": {
    "title": "{{test_title}}",
    "unique_id": "test-{{date}}",
    "date": "{{date}}",
    "subject": "{{subject}}",
    "duration": "{{duration}}",
    "layout": "mcq-optimized"
  },
  "sections": [
    {{#each questions}}
    {
      "type": "multiple_choice",
      "code": "Q{{this.number}}",
      "title": "Question {{this.number}}",
      "question": "{{this.text}}",
      "points": {{this.points}},
      "maxSelections": 1,
      "layout": "mcq-compact",
      "choices": [
        {{#each this.options}}
        {
          "code": "{{@index}}",
          "label": "{{this}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
JSON;
    }

    // Sample Data Methods

    private function getElectionSampleData(): array
    {
        return [
            'election_name' => 'General Elections',
            'precinct' => '001-A',
            'date' => '2025-05-15',
            'positions' => [
                [
                    'code' => 'PRES',
                    'title' => 'President',
                    'max_selections' => 1,
                    'candidates' => [
                        ['code' => 'P01', 'name' => 'Maria Santos', 'party' => 'Progressive Party'],
                        ['code' => 'P02', 'name' => 'Juan Dela Cruz', 'party' => 'Democratic Alliance'],
                        ['code' => 'P03', 'name' => 'Ana Rodriguez', 'party' => 'Independent'],
                    ]
                ],
                [
                    'code' => 'VP',
                    'title' => 'Vice President',
                    'max_selections' => 1,
                    'candidates' => [
                        ['code' => 'VP01', 'name' => 'Pedro Gomez', 'party' => 'Progressive Party'],
                        ['code' => 'VP02', 'name' => 'Lisa Fernandez', 'party' => 'Democratic Alliance'],
                    ]
                ],
            ]
        ];
    }

    private function getSurveySampleData(): array
    {
        return [
            'survey_title' => 'Customer Satisfaction Survey',
            'description' => 'Help us improve our services',
            'date' => '2025-10-24',
            'questions' => [
                [
                    'number' => 1,
                    'type' => 'multiple_choice',
                    'text' => 'How satisfied are you with our service?',
                    'options' => ['Very Satisfied', 'Satisfied', 'Neutral', 'Dissatisfied', 'Very Dissatisfied']
                ],
                [
                    'number' => 2,
                    'type' => 'multiple_choice',
                    'text' => 'Would you recommend us to others?',
                    'options' => ['Definitely Yes', 'Probably Yes', 'Not Sure', 'Probably No', 'Definitely No']
                ],
            ]
        ];
    }

    private function getTestSampleData(): array
    {
        return [
            'test_title' => 'Mathematics Quiz',
            'subject' => 'Mathematics',
            'date' => '2025-10-24',
            'duration' => '45 minutes',
            'questions' => [
                [
                    'number' => 1,
                    'text' => 'What is 2 + 2?',
                    'points' => 1,
                    'options' => ['3', '4', '5', '6']
                ],
                [
                    'number' => 2,
                    'text' => 'What is the square root of 16?',
                    'points' => 1,
                    'options' => ['2', '4', '8', '16']
                ],
            ]
        ];
    }

    // Schema Methods

    private function getElectionSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['election_name', 'precinct', 'date', 'positions'],
            'properties' => [
                'election_name' => ['type' => 'string', 'minLength' => 3],
                'precinct' => ['type' => 'string'],
                'date' => ['type' => 'string'],
                'positions' => [
                    'type' => 'array',
                    'minItems' => 1,
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
                'survey_title' => ['type' => 'string', 'minLength' => 3],
                'description' => ['type' => 'string'],
                'date' => ['type' => 'string'],
                'questions' => [
                    'type' => 'array',
                    'minItems' => 1,
                ]
            ]
        ];
    }

    private function getTestSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['test_title', 'subject', 'questions'],
            'properties' => [
                'test_title' => ['type' => 'string', 'minLength' => 3],
                'subject' => ['type' => 'string'],
                'date' => ['type' => 'string'],
                'duration' => ['type' => 'string'],
                'questions' => [
                    'type' => 'array',
                    'minItems' => 1,
                ]
            ]
        ];
    }

    // Philippine Ballot Templates

    private function getQuestionnaireTemplate(): string
    {
        return <<<'JSON'
{
  "spec_version": "1.0",
  "document": {
    "title": "{{election_name}} - Candidate List",
    "type": "questionnaire",
    "page_size": "letter",
    "orientation": "portrait"
  },
  "header": {
    "election_name": "{{election_name}}",
    "precinct_code": "{{precinct_code}}",
    "precinct_location": "{{precinct_location}}",
    "date": "{{date}}"
  },
  "sections": [
    {{#each positions}}
    {
      "type": "position_candidates",
      "position_code": "{{code}}",
      "position_title": "{{title}}",
      "level": "{{level}}",
      "max_selections": {{max_selections}},
      "candidates": [
        {{#each candidates}}
        {
          "code": "{{code}}",
          "name": "{{name}}",
          "party": "{{party}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ],
  "footer": {
    "electoral_inspectors": [
      {{#each electoral_inspectors}}
      {
        "id": "{{id}}",
        "name": "{{name}}",
        "role": "{{role}}"
      }{{#unless @last}},{{/unless}}
      {{/each}}
    ]
  }
}
JSON;
    }

    private function getAnswerSheetTemplate(): string
    {
        return <<<'JSON'
{
  "spec_version": "1.0",
  "document": {
    "title": "{{election_name}} - Official Ballot",
    "type": "ballot",
    "page_size": "legal",
    "orientation": "portrait"
  },
  "header": {
    "election_name": "{{election_name}}",
    "precinct_code": "{{precinct_code}}",
    "precinct_location": "{{precinct_location}}",
    "date": "{{date}}"
  },
  "instructions": [
    {{#each instructions}}
    "{{this}}"{{#unless @last}},{{/unless}}
    {{/each}}
  ],
  "positions": [
    {{#each positions}}
    {
      "code": "{{code}}",
      "title": "{{title}}",
      "level": "{{level}}",
      "max_selections": {{max_selections}},
      "instruction": "Vote for not more than {{max_selections}}",
      "candidates": [
        {{#each candidates}}
        {
          "code": "{{code}}",
          "name": "{{name}}",
          "party": "{{party}}",
          "oval": "○"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
JSON;
    }

    private function getPhilippineBallotSampleData(): array
    {
        return [
            'election_name' => 'Philippine National Elections 2025',
            'precinct_code' => 'PRE-001',
            'precinct_location' => 'Sample High School',
            'date' => '2025-05-12',
            'instructions' => [
                'Use a black or blue pen to shade the oval completely.',
                'Do not overvote.',
            ],
            'positions' => [
                [
                    'code' => 'PRESIDENT',
                    'title' => 'President of the Philippines',
                    'level' => 'national',
                    'max_selections' => 1,
                    'candidates' => [
                        ['code' => 'P01', 'name' => 'Leonardo DiCaprio', 'party' => 'LD'],
                        ['code' => 'P02', 'name' => 'Scarlett Johansson', 'party' => 'SJ'],
                    ],
                ],
            ],
            'electoral_inspectors' => [
                ['id' => 'uuid-1', 'name' => 'Juan dela Cruz', 'role' => 'chairperson'],
            ],
        ];
    }

    private function getPhilippineBallotSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['election_name', 'precinct_code', 'precinct_location', 'date', 'positions'],
            'properties' => [
                'election_name' => ['type' => 'string', 'title' => 'Election Name'],
                'precinct_code' => ['type' => 'string', 'title' => 'Precinct Code'],
                'precinct_location' => ['type' => 'string', 'title' => 'Precinct Location'],
                'date' => ['type' => 'string', 'format' => 'date', 'title' => 'Election Date'],
                'instructions' => ['type' => 'array', 'items' => ['type' => 'string']],
                'positions' => [
                    'type' => 'array',
                    'title' => 'Positions',
                    'minItems' => 1,
                ],
                'electoral_inspectors' => [
                    'type' => 'array',
                    'title' => 'Electoral Inspectors',
                ],
            ]
        ];
    }
}
