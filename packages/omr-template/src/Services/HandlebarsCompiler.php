<?php

namespace LBHurtado\OMRTemplate\Services;

use LightnCandy\LightnCandy;

class HandlebarsCompiler
{
    protected HandlebarsEngine $engine;

    public function __construct(HandlebarsEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Compile a Handlebars template with JSON data to produce a JSON specification.
     *
     * @param string $template Handlebars template string
     * @param array $data Data to inject into the template
     * @return array Parsed JSON specification
     * @throws \Exception If template is invalid or result is not valid JSON
     */
    public function compile(string $template, array $data): array
    {
        try {
            // Render the template with data
            $json = $this->engine->renderString($template, $data);

            // Parse and validate JSON
            $spec = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON generated: ' . json_last_error_msg());
            }

            return $spec;
        } catch (\Exception $e) {
            throw new \Exception('Template compilation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate a Handlebars template for syntax errors.
     *
     * @param string $template Handlebars template string
     * @return bool True if valid
     * @throws \Exception If template has syntax errors
     */
    public function validate(string $template): bool
    {
        try {
            LightnCandy::compile($template, [
                'flags' => LightnCandy::FLAG_HANDLEBARS
                    | LightnCandy::FLAG_ERROR_EXCEPTION
                    | LightnCandy::FLAG_RUNTIMEPARTIAL,
            ]);

            return true;
        } catch (\Exception $e) {
            throw new \Exception('Invalid Handlebars syntax: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Compile with custom Handlebars helpers.
     *
     * @param string $template Handlebars template string
     * @param array $data Data to inject into the template
     * @param array $helpers Custom Handlebars helpers
     * @return array Parsed JSON specification
     * @throws \Exception If template is invalid or result is not valid JSON
     */
    public function compileWithHelpers(string $template, array $data, array $helpers = []): array
    {
        try {
            $phpStr = LightnCandy::compile($template, [
                'flags' => LightnCandy::FLAG_HANDLEBARS
                    | LightnCandy::FLAG_ERROR_EXCEPTION
                    | LightnCandy::FLAG_RUNTIMEPARTIAL,
                'helpers' => array_merge($this->getDefaultHelpers(), $helpers),
            ]);

            $renderer = LightnCandy::prepare($phpStr);
            $json = $renderer($data);

            // Parse and validate JSON
            $spec = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON generated: ' . json_last_error_msg());
            }

            return $spec;
        } catch (\Exception $e) {
            throw new \Exception('Template compilation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get default Handlebars helpers for OMR templates.
     *
     * @return array
     */
    protected function getDefaultHelpers(): array
    {
        return [
            'eq' => function ($a, $b) {
                return $a === $b;
            },
            'ne' => function ($a, $b) {
                return $a !== $b;
            },
            'gt' => function ($a, $b) {
                return $a > $b;
            },
            'gte' => function ($a, $b) {
                return $a >= $b;
            },
            'lt' => function ($a, $b) {
                return $a < $b;
            },
            'lte' => function ($a, $b) {
                return $a <= $b;
            },
            'json' => function ($value) {
                return json_encode($value);
            },
            'uppercase' => function ($str) {
                return strtoupper($str);
            },
            'lowercase' => function ($str) {
                return strtolower($str);
            },
            'capitalize' => function ($str) {
                return ucfirst($str);
            },
            'length' => function ($array) {
                return is_array($array) ? count($array) : 0;
            },
        ];
    }
}
