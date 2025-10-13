<?php

namespace TruthRenderer\Engine;

use LightnCandy\LightnCandy;

/**
 * Compiles Handlebars to a render Closure without using the deprecated prepare().
 */
class HandlebarsEngine
{
    /**
     * @param array<string,mixed>  $data
     * @param array<string,string> $partials    keyed partial name => template source
     * @param array<string,mixed>  $engineFlags LightnCandy options (compile/runtime)
     */
    public function render(string $template, array $data, array $partials = [], array $engineFlags = []): string
    {
        // --- Normalize any helpers passed via $engineFlags['helpers'] ---
        $helpersInput = $engineFlags['helpers'] ?? [];
        $normalizedHelpers = [];

        foreach ($helpersInput as $name => $callable) {
            if ($callable instanceof \Closure) {
                $normalizedHelpers[$name] = $callable;
                continue;
            }
            if (is_string($callable)) {
                // "Class::method" or global function name
                $normalizedHelpers[$name] = $callable;
                continue;
            }
            throw new \InvalidArgumentException(
                "Helper '{$name}' must be a Closure or 'Class::method' string; array-callables are not supported by LightnCandy."
            );
        }

        // Remove 'helpers' from engineFlags so it can't overwrite our normalized set
        $engineFlagsSansHelpers = $engineFlags;
        unset($engineFlagsSansHelpers['helpers']);

        // --- Build final compile options ---
        $compileOptions = array_merge([
            'flags'    => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_NAMEDARG,
            'partials' => $partials,
        ], $engineFlagsSansHelpers);
        // Built-in helpers as FQN strings to satisfy LightnCandy's exporter
        $compileOptions['helpers'] = array_merge([
            'upper'     => HbsHelpers::class . '::upper',
            'lower'     => HbsHelpers::class . '::lower',
            'currency'  => HbsHelpers::class . '::currency',
            'date'      => HbsHelpers::class . '::date',
            'multiply'  => HbsHelpers::class . '::multiply',
            'lineTotal' => HbsHelpers::class . '::lineTotal',
            'calcTotal' => HbsHelpers::class . '::calcTotal',
            'round2'    => HbsHelpers::class . '::round2',
            'currencyISO'=> HbsHelpers::class . '::currencyISO',
            'let'       => HbsHelpers::class . '::let',
            'eq'        => HbsHelpers::class . '::eq',
            'groupBy'   => HbsHelpers::class . '::groupBy',
            'inc'       => HbsHelpers::class . '::inc',
            'startsWith' => HbsHelpers::class . '::startsWith',
            'includes'  => HbsHelpers::class . '::includes',
            'add'       => HbsHelpers::class . '::add',
            'lt'        => HbsHelpers::class . '::lt',
        ], $normalizedHelpers);

        // --- Compile ---
        $php = LightnCandy::compile($template, $compileOptions);
        if ($php === false) {
            $ctx = LightnCandy::getContext();
            $msg = is_array($ctx['error'] ?? null) ? implode('; ', $ctx['error']) : 'Unknown compile error';
            throw new \RuntimeException('Handlebars compile failed: ' . $msg);
        }

        // --- Safely load the compiled renderer as a callable (no eval issues with use-statements) ---
        $tmp = tempnam(sys_get_temp_dir(), 'hbs_');
        if ($tmp === false) {
            throw new \RuntimeException('Failed to create temp file for compiled template');
        }

        $phpFile = "<?php\n" . $php;
        if (@file_put_contents($tmp, $phpFile) === false) {
            @unlink($tmp);
            throw new \RuntimeException('Failed to write compiled template to temp file');
        }

        try {
            /** @var callable $renderer */
            $renderer = include $tmp;
        } finally {
            @unlink($tmp);
        }

        if (!is_callable($renderer)) {
            throw new \RuntimeException('Compiled template did not return a callable renderer');
        }

        // --- Render ---
        return $renderer($data);
    }
}
