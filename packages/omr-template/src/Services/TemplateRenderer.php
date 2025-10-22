<?php

namespace LBHurtado\OMRTemplate\Services;

use LBHurtado\OMRTemplate\Data\TemplateData;

class TemplateRenderer
{
    public function __construct(
        protected HandlebarsEngine $engine,
    ) {}

    public function render(TemplateData $templateData): string
    {
        $templatePath = $this->resolveTemplatePath($templateData->template_id);

        return $this->engine
            ->template($templatePath)
            ->render($templateData->toArray());
    }

    protected function resolveTemplatePath(string $templateId): string
    {
        $basePath = config('omr-template.default_template_path', resource_path('templates'));
        
        // Try with .hbs extension
        $path = "{$basePath}/{$templateId}.hbs";
        if (file_exists($path)) {
            return $path;
        }

        // Try without extension (templateId might already have extension)
        $path = "{$basePath}/{$templateId}";
        if (file_exists($path)) {
            return $path;
        }

        throw new \RuntimeException("Template not found: {$templateId}");
    }
}
