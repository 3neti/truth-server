<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\TemplateFamily;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ConvertTemplatesToFamiliesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Converting existing templates to template families...');

        // Group templates by name to detect variants
        $templates = Template::all()->groupBy(function ($template) {
            // Extract base name (remove variant indicators)
            $name = $template->name;
            $name = preg_replace('/\s*\((ballot|mapping|reference|portrait|landscape)\)$/i', '', $name);
            return trim($name);
        });

        foreach ($templates as $baseName => $templateGroup) {
            $this->command->info("Processing: {$baseName} ({$templateGroup->count()} variant(s))");

            // Use the first template to determine family properties
            $firstTemplate = $templateGroup->first();

            // Check if family already exists
            $slug = Str::slug($baseName);
            $family = TemplateFamily::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $baseName,
                    'description' => $firstTemplate->description,
                    'category' => $firstTemplate->category,
                    'version' => '1.0.0',
                    'is_public' => $firstTemplate->is_public,
                    'user_id' => $firstTemplate->user_id,
                ]
            );

            // Link templates to this family
            foreach ($templateGroup as $index => $template) {
                // Determine variant name
                $variant = $this->determineVariant($template->name, $baseName);

                $template->update([
                    'family_id' => $family->id,
                    'layout_variant' => $variant,
                    'version' => '1.0.0',
                ]);

                $this->command->line("  - Linked: {$template->name} as '{$variant}' variant");
            }
        }

        $familyCount = TemplateFamily::count();
        $this->command->info("✅ Created {$familyCount} template families!");
    }

    /**
     * Determine the variant name from the template name.
     */
    protected function determineVariant(string $templateName, string $baseName): string
    {
        // Remove base name to get variant indicator
        $variant = trim(str_replace($baseName, '', $templateName));
        $variant = trim($variant, ' -_()');

        if (empty($variant)) {
            return 'default';
        }

        // Common variant patterns
        if (preg_match('/ballot|numbered/i', $variant)) {
            return 'ballot';
        }
        if (preg_match('/mapping|reference|candidate/i', $variant)) {
            return 'candidate-mapping';
        }
        if (preg_match('/portrait/i', $variant)) {
            return 'portrait';
        }
        if (preg_match('/landscape/i', $variant)) {
            return 'landscape';
        }

        // Default: slugify the variant
        return Str::slug($variant) ?: 'default';
    }
}
