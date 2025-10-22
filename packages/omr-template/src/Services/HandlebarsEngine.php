<?php

namespace LBHurtado\OMRTemplate\Services;

use LightnCandy\LightnCandy;

class HandlebarsEngine
{
    protected string $templatePath;

    public function template(string $path): self
    {
        $this->templatePath = $path;

        return $this;
    }

    public function render(array $data): string
    {
        if (! isset($this->templatePath)) {
            throw new \RuntimeException('Template path not set. Call template() first.');
        }

        if (! file_exists($this->templatePath)) {
            throw new \RuntimeException("Template file not found: {$this->templatePath}");
        }

        $template = file_get_contents($this->templatePath);

        $phpStr = LightnCandy::compile($template, [
            'flags' => LightnCandy::FLAG_HANDLEBARS
                | LightnCandy::FLAG_ERROR_EXCEPTION
                | LightnCandy::FLAG_RUNTIMEPARTIAL,
        ]);

        $renderer = LightnCandy::prepare($phpStr);

        return $renderer($data);
    }

    public function renderString(string $template, array $data): string
    {
        $phpStr = LightnCandy::compile($template, [
            'flags' => LightnCandy::FLAG_HANDLEBARS
                | LightnCandy::FLAG_ERROR_EXCEPTION
                | LightnCandy::FLAG_RUNTIMEPARTIAL,
        ]);

        $renderer = LightnCandy::prepare($phpStr);

        return $renderer($data);
    }
}
