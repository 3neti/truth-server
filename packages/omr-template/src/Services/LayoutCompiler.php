<?php

namespace LBHurtado\OMRTemplate\Services;

use LightnCandy\LightnCandy;

/**
 * LayoutCompiler
 * 
 * Compiles Handlebars templates to JSON layouts for PDF generation.
 * Uses LightnCandy (Handlebars-compatible engine) to process templates.
 */
class LayoutCompiler
{
    protected ?string $basePath = null;
    /**
     * Compile a Handlebars template with data to produce a JSON layout
     * 
     * @param string $template Template name (without .hbs extension)
     * @param array $data Data to pass to the template
     * @return array The compiled layout as an associative array
     * @throws \RuntimeException If template not found or JSON invalid
     */
    public function compile(string $template, array $data): array
    {
        $templatePath = $this->resolveTemplatePath($template);
        
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template not found: {$template}");
        }

        $templateContents = file_get_contents($templatePath);
        
        // Compile the template with LightnCandy
        $phpCode = LightnCandy::compile($templateContents, [
            'flags' => LightnCandy::FLAG_HANDLEBARS | 
                      LightnCandy::FLAG_RUNTIMEPARTIAL |
                      LightnCandy::FLAG_ERROR_EXCEPTION,
            'helpers' => $this->getHelpers()
        ]);

        // Create a runtime function and execute it
        $renderer = LightnCandy::prepare($phpCode);
        $json = $renderer($data);

        // Parse the JSON
        $layout = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                "Invalid JSON produced by template: " . json_last_error_msg()
            );
        }

        return $layout;
    }

    /**
     * Compile a Handlebars template and return the raw JSON string
     * 
     * @param string $template Template name (without .hbs extension)
     * @param array $data Data to pass to the template
     * @return string The compiled JSON string
     */
    public function compileToJson(string $template, array $data): string
    {
        $layout = $this->compile($template, $data);
        return json_encode($layout, JSON_PRETTY_PRINT);
    }

    /**
     * Resolve the full path to a template file
     * 
     * @param string $template Template name
     * @return string Full path to template
     */
    protected function resolveTemplatePath(string $template): string
    {
        // Use custom base path if set (for testing)
        if ($this->basePath) {
            $basePath = $this->basePath;
        } else {
            // Try to use Laravel's resource_path if available
            try {
                if (function_exists('app') && app()->bound('path')) {
                    $basePath = resource_path('templates');
                } else {
                    // Fallback for standalone usage
                    $basePath = __DIR__ . '/../../resources/templates';
                }
            } catch (\Exception $e) {
                // Fallback for standalone usage
                $basePath = __DIR__ . '/../../resources/templates';
            }
        }

        // Try with .hbs extension
        $path = "{$basePath}/{$template}.hbs";
        if (file_exists($path)) {
            return $path;
        }

        // Try without extension (template might already have it)
        $path = "{$basePath}/{$template}";
        if (file_exists($path)) {
            return $path;
        }

        // Return the .hbs version for error message
        return "{$basePath}/{$template}.hbs";
    }

    /**
     * Compile inline Handlebars template string
     * 
     * @param string $templateString The Handlebars template as a string
     * @param array $data Data to pass to the template
     * @return array The compiled layout
     */
    public function compileString(string $templateString, array $data): array
    {
        $phpCode = LightnCandy::compile($templateString, [
            'flags' => LightnCandy::FLAG_HANDLEBARS | 
                      LightnCandy::FLAG_RUNTIMEPARTIAL |
                      LightnCandy::FLAG_ERROR_EXCEPTION,
            'helpers' => $this->getHelpers()
        ]);

        $renderer = LightnCandy::prepare($phpCode);
        $json = $renderer($data);

        $layout = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                "Invalid JSON produced by template: " . json_last_error_msg()
            );
        }

        return $layout;
    }

    /**
     * Validate that a compiled layout has required fields
     * 
     * @param array $layout The layout to validate
     * @param array $requiredFields List of required field names
     * @return bool True if valid
     * @throws \RuntimeException If validation fails
     */
    public function validate(array $layout, array $requiredFields = ['identifier']): bool
    {
        foreach ($requiredFields as $field) {
            if (!isset($layout[$field])) {
                throw new \RuntimeException(
                    "Layout missing required field: {$field}"
                );
            }
        }

        return true;
    }

    /**
     * Get Handlebars helper functions
     * 
     * @return array
     */
    protected function getHelpers(): array
    {
        return [
            'add' => function ($a, $b) {
                return $a + $b;
            },
            'subtract' => function ($a, $b) {
                return $a - $b;
            },
            'multiply' => function ($a, $b) {
                return $a * $b;
            },
            'divide' => function ($a, $b) {
                return $b != 0 ? $a / $b : 0;
            },
        ];
    }

    /**
     * Set custom base path for templates (useful for testing)
     * 
     * @param string $path
     * @return self
     */
    public function setBasePath(string $path): self
    {
        $this->basePath = $path;
        return $this;
    }
}
