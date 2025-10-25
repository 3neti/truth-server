<?php

namespace Database\Seeders;

use App\Models\DataFile;
use App\Models\OmrTemplate;
use App\Models\TemplateFamily;
use App\Models\User;
use Illuminate\Database\Seeder;

class TemplateDataSeeder extends Seeder
{
    /**
     * Seed templates and data files with proper template references.
     */
    public function run(): void
    {
        $this->command->info('🗑️  Cleaning up old test data...');
        
        // Get or create the admin user
        $user = User::firstOrCreate(
            ['email' => 'admin@disburse.cash'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
            ]
        );
        
        // Delete existing test data created by this seeder
        DataFile::where('user_id', $user->id)
            ->whereIn('name', [
                'Precinct 001-A Election Data',
                'Precinct 002-B Election Data',
                'Q4 2024 Customer Survey Data',
                'General Configuration Data',
            ])
            ->delete();
        
        $ballotFamily = TemplateFamily::where('slug', 'ballot-2025')->first();
        if ($ballotFamily) {
            OmrTemplate::where('family_id', $ballotFamily->id)->delete();
        }
        
        OmrTemplate::where('user_id', $user->id)
            ->where('name', 'Customer Satisfaction Survey')
            ->delete();
        
        TemplateFamily::where('slug', 'ballot-2025')->delete();
        
        $this->command->info('✨ Creating fresh test data...');

        // 1. Create or get a Template Family
        $ballotFamily = TemplateFamily::firstOrCreate(
            ['slug' => 'ballot-2025'],
            [
                'name' => 'Election Ballot 2025',
                'description' => 'Official election ballot templates for 2025',
                'category' => 'ballot',
                'user_id' => $user->id,
                'is_public' => true,
                'storage_type' => 'local',
            ]
        );

        // 2. Create Template Variants in the Family
        $verticalTemplate = OmrTemplate::create([
            'name' => 'Vertical Ballot',
            'description' => 'Single column vertical ballot layout',
            'category' => 'ballot',
            'family_id' => $ballotFamily->id,
            'layout_variant' => 'vertical',
            'handlebars_template' => $this->getVerticalTemplate(),
            'sample_data' => $this->getSampleBallotData(),
            'user_id' => $user->id,
            'is_public' => true,
            'storage_type' => 'local',
        ]);

        $horizontalTemplate = OmrTemplate::create([
            'name' => 'Horizontal Ballot',
            'description' => 'Two column horizontal ballot layout',
            'category' => 'ballot',
            'family_id' => $ballotFamily->id,
            'layout_variant' => 'horizontal',
            'handlebars_template' => $this->getHorizontalTemplate(),
            'sample_data' => $this->getSampleBallotData(),
            'user_id' => $user->id,
            'is_public' => true,
            'storage_type' => 'local',
        ]);

        // 3. Create Data Files referencing these templates
        
        // Data file 1: References vertical template (embedded in JSON)
        DataFile::create([
            'name' => 'Precinct 001-A Election Data',
            'description' => 'Election data for Precinct 001-A using vertical ballot',
            'template_ref' => 'local:ballot-2025/vertical', // Also synced to column for indexing
            'category' => 'election',
            'data' => [
                'document' => [
                    'template_ref' => 'local:ballot-2025/vertical', // Embedded in JSON (portable!)
                ],
                'data' => [
                    'election_name' => '2025 National Elections',
                    'precinct' => '001-A',
                    'date' => '2025-05-15',
                ],
                'positions' => [
                    [
                        'code' => 'PRES',
                        'title' => 'President',
                        'max_selections' => 1,
                        'candidates' => [
                            ['position' => 1, 'name' => 'Alice Martinez', 'party' => 'Progressive Party'],
                            ['position' => 2, 'name' => 'Robert Chen', 'party' => 'Democratic Alliance'],
                            ['position' => 3, 'name' => 'Maria Santos', 'party' => 'Independent'],
                        ],
                    ],
                    [
                        'code' => 'VP',
                        'title' => 'Vice President',
                        'max_selections' => 1,
                        'candidates' => [
                            ['position' => 1, 'name' => 'John Williams', 'party' => 'Progressive Party'],
                            ['position' => 2, 'name' => 'Sarah Lee', 'party' => 'Democratic Alliance'],
                        ],
                    ],
                ],
            ],
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        // Data file 2: References horizontal template (embedded in JSON)
        DataFile::create([
            'name' => 'Precinct 002-B Election Data',
            'description' => 'Election data for Precinct 002-B using horizontal ballot',
            'template_ref' => 'local:ballot-2025/horizontal', // Also synced to column
            'category' => 'election',
            'data' => [
                'document' => [
                    'template_ref' => 'local:ballot-2025/horizontal', // Embedded in JSON
                ],
                'data' => [
                    'election_name' => '2025 National Elections',
                    'precinct' => '002-B',
                    'date' => '2025-05-15',
                ],
                'positions' => [
                    [
                        'code' => 'PRES',
                        'title' => 'President',
                        'max_selections' => 1,
                        'candidates' => [
                            ['position' => 1, 'name' => 'Alice Martinez', 'party' => 'Progressive Party'],
                            ['position' => 2, 'name' => 'Robert Chen', 'party' => 'Democratic Alliance'],
                        ],
                    ],
                ],
            ],
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        // 4. Create standalone template (not in a family)
        $surveyTemplate = OmrTemplate::create([
            'name' => 'Customer Satisfaction Survey',
            'description' => 'Generic customer satisfaction survey template',
            'category' => 'survey',
            'handlebars_template' => $this->getSurveyTemplate(),
            'sample_data' => $this->getSurveySampleData(),
            'user_id' => $user->id,
            'is_public' => true,
            'storage_type' => 'local',
        ]);

        // Data file 3: References standalone template by ID (embedded in JSON)
        DataFile::create([
            'name' => 'Q4 2024 Customer Survey Data',
            'description' => 'Customer feedback collected in Q4 2024',
            'template_ref' => "local:{$surveyTemplate->id}", // Also synced to column
            'category' => 'survey',
            'data' => [
                'document' => [
                    'template_ref' => "local:{$surveyTemplate->id}", // Embedded in JSON
                ],
                'data' => [
                    'survey_title' => 'Q4 2024 Customer Satisfaction',
                    'period' => 'October - December 2024',
                ],
                'questions' => [
                    [
                        'code' => 'Q1',
                        'text' => 'How satisfied are you with our service?',
                        'type' => 'rating',
                        'scale' => 5,
                    ],
                    [
                        'code' => 'Q2',
                        'text' => 'Would you recommend us to others?',
                        'type' => 'yes_no',
                    ],
                ],
            ],
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        // 5. Create a data file WITHOUT template reference (standalone data)
        DataFile::create([
            'name' => 'General Configuration Data',
            'description' => 'Miscellaneous configuration settings',
            'template_ref' => null, // No template
            'category' => 'general',
            'data' => [
                'document' => [], // No template_ref
                'data' => [
                    'app_name' => 'Truth OMR System',
                    'version' => '1.0.0',
                ],
                'settings' => [
                    'theme' => 'light',
                    'language' => 'en',
                    'timezone' => 'Asia/Manila',
                ],
            ],
            'user_id' => $user->id,
            'is_public' => false, // Private
        ]);

        $this->command->info('✅ Seeded 3 templates and 4 data files with proper template references');
        $this->command->info("📝 Template References:");
        $this->command->info("   - local:ballot-2025/vertical");
        $this->command->info("   - local:ballot-2025/horizontal");
        $this->command->info("   - local:{$surveyTemplate->id}");
        $this->command->info("   - null (standalone data)");
    }

    private function getVerticalTemplate(): string
    {
        return <<<'HANDLEBARS'
{
  "document": {
    "title": "{{election_name}}",
    "unique_id": "{{precinct}}-{{date}}",
    "date": "{{date}}",
    "precinct": "{{precinct}}",
    "layout": "1-column"
  },
  "sections": [
    {{#each positions}}
    {
      "type": "multiple_choice",
      "code": "{{code}}",
      "title": "{{title}}",
      "question": "Vote for {{max_selections}}",
      "maxSelections": {{max_selections}},
      "layout": "1-column",
      "choices": [
        {{#each candidates}}
        {
          "code": "{{position}}",
          "label": "{{name}}",
          "description": "{{party}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
HANDLEBARS;
    }

    private function getHorizontalTemplate(): string
    {
        return <<<'HANDLEBARS'
{
  "document": {
    "title": "{{election_name}}",
    "unique_id": "{{precinct}}-{{date}}",
    "date": "{{date}}",
    "precinct": "{{precinct}}",
    "layout": "2-column"
  },
  "sections": [
    {{#each positions}}
    {
      "type": "multiple_choice",
      "code": "{{code}}",
      "title": "{{title}}",
      "question": "Vote for {{max_selections}}",
      "maxSelections": {{max_selections}},
      "layout": "2-column",
      "choices": [
        {{#each candidates}}
        {
          "code": "{{position}}",
          "label": "{{name}}",
          "description": "{{party}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
HANDLEBARS;
    }

    private function getSurveyTemplate(): string
    {
        return <<<'HANDLEBARS'
{
  "document": {
    "title": "{{survey_title}}",
    "unique_id": "SURVEY-{{period}}",
    "layout": "1-column"
  },
  "sections": [
    {{#each questions}}
    {
      "type": "multiple_choice",
      "code": "{{code}}",
      "title": "{{text}}",
      "question": "{{text}}",
      "maxSelections": 1,
      "layout": "1-column",
      "choices": [
        {{#if scale}}
        {"code": "1", "label": "1 - Poor"},
        {"code": "2", "label": "2 - Fair"},
        {"code": "3", "label": "3 - Good"},
        {"code": "4", "label": "4 - Very Good"},
        {"code": "5", "label": "5 - Excellent"}
        {{else}}
        {"code": "YES", "label": "Yes"},
        {"code": "NO", "label": "No"}
        {{/if}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
HANDLEBARS;
    }

    private function getSampleBallotData(): array
    {
        return [
            'election_name' => '2025 National Elections',
            'precinct' => '001-A',
            'date' => '2025-05-15',
            'positions' => [
                [
                    'code' => 'PRES',
                    'title' => 'President',
                    'max_selections' => 1,
                    'candidates' => [
                        ['position' => 1, 'name' => 'Alice Martinez', 'party' => 'Progressive Party'],
                        ['position' => 2, 'name' => 'Robert Chen', 'party' => 'Democratic Alliance'],
                    ],
                ],
            ],
        ];
    }

    private function getSurveySampleData(): array
    {
        return [
            'survey_title' => 'Customer Satisfaction Survey',
            'period' => 'Q1-2025',
            'questions' => [
                [
                    'code' => 'Q1',
                    'text' => 'How satisfied are you with our service?',
                    'type' => 'rating',
                    'scale' => 5,
                ],
                [
                    'code' => 'Q2',
                    'text' => 'Would you recommend us to others?',
                    'type' => 'yes_no',
                ],
                [
                    'code' => 'Q3',
                    'text' => 'How would you rate our product quality?',
                    'type' => 'rating',
                    'scale' => 5,
                ],
            ],
        ];
    }
}
