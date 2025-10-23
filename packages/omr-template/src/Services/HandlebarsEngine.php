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

        return $this->renderString($template, $data);
    }

    public function renderString(string $template, array $data): string
    {
        $phpStr = LightnCandy::compile($template, [
            'flags' => LightnCandy::FLAG_HANDLEBARS
                | LightnCandy::FLAG_ERROR_EXCEPTION
                | LightnCandy::FLAG_RUNTIMEPARTIAL,
            'helpers' => $this->getDefaultHelpers(),
        ]);

        $renderer = LightnCandy::prepare($phpStr);

        return $renderer($data);
    }

    /**
     * Get default Handlebars helpers.
     *
     * @return array
     */
    protected function getDefaultHelpers(): array
    {
        return [
            'compare' => function ($a, $operator, $b) {
                switch ($operator) {
                    case '==':
                    case 'eq':
                        return $a == $b;
                    case '===':
                        return $a === $b;
                    case '!=':
                    case 'ne':
                        return $a != $b;
                    case '!==':
                        return $a !== $b;
                    case '>':
                    case 'gt':
                        return $a > $b;
                    case '>=':
                    case 'gte':
                        return $a >= $b;
                    case '<':
                    case 'lt':
                        return $a < $b;
                    case '<=':
                    case 'lte':
                        return $a <= $b;
                    default:
                        return false;
                }
            },
            'lookup' => function ($obj, $key) {
                if (is_array($obj) && isset($obj[$key])) {
                    return $obj[$key];
                }
                return null;
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
            'eq' => function ($a, $b) {
                return $a === $b;
            },
            'ne' => function ($a, $b) {
                return $a !== $b;
            },
        ];
    }
}
