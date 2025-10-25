<?php

namespace App\Services\Templates\Contracts;

interface TemplateProviderInterface
{
    /**
     * Fetch template content from the provider.
     *
     * @param array $parts Parsed URI parts (provider, org, repo, path, version, etc.)
     * @return string Template content
     * @throws \Exception if template cannot be fetched
     */
    public function fetch(array $parts): string;

    /**
     * Check if this provider can handle the given URI parts.
     *
     * @param array $parts Parsed URI parts
     * @return bool
     */
    public function canHandle(array $parts): bool;

    /**
     * Get provider name.
     *
     * @return string
     */
    public function getName(): string;
}
