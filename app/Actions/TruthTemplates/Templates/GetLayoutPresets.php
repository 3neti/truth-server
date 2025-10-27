<?php

namespace App\Actions\TruthTemplates\Templates;

use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetLayoutPresets
{
    use AsAction;

    public function handle(): array
    {
        return config('omr-template.layouts', []);
    }

    //this has the same logic as the TemplateController::layouts()
    public function asController(ActionRequest $request)
    {
        $layouts = $this->handle();

        return response()->json([
            'layouts' => $layouts,
        ]);
    }

    //do not implement yet
    public function asCommand(): int
    {
        $layouts = $this->handle();

        $this->info('Available layout presets:');

        foreach ($layouts as $key => $layout) {
            $this->line("  - {$key}: " . ($layout['name'] ?? $key));
        }

        return self::SUCCESS;
    }
}
