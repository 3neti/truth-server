<?php

namespace TruthRenderer\Contracts;

interface TemplateRegistryInterface
{
    /**
     * Resolve a template by name and return its raw source (Handlebars/HTML).
     *
     * @throws \RuntimeException when not found
     */
    public function get(string $name): string;

    /**
     * Register a template source under a name (runtime/in-memory).
     */
    public function set(string $name, string $source): void;

    /**
     * List all available template names (from memory + configured paths).
     *
     * @return string[]
     */
    public function list(): array;

    /**
     * Absolute path to the template file if resolvable (e.g. …/invoice/basic/template.hbs)
     * or null when the template came from memory.
     */
    public function resolveFile(string $name): ?string;

    /**
     * Absolute directory that contains the template file (parent of resolveFile),
     * or null for in-memory templates.
     */
    public function resolveDir(string $name): ?string;
}
