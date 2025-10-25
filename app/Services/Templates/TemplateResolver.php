<?php

namespace App\Services\Templates;

use App\Services\Templates\Contracts\TemplateProviderInterface;
use App\Services\Templates\Providers\GitHubTemplateProvider;
use App\Services\Templates\Providers\HttpTemplateProvider;
use App\Services\Templates\Providers\LocalTemplateProvider;

class TemplateResolver
{
    /**
     * @var TemplateProviderInterface[]
     */
    protected array $providers = [];

    public function __construct()
    {
        // Register default providers
        $this->registerProvider(new GitHubTemplateProvider());
        $this->registerProvider(new HttpTemplateProvider());
        $this->registerProvider(new LocalTemplateProvider());
    }

    /**
     * Register a template provider.
     */
    public function registerProvider(TemplateProviderInterface $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    /**
     * Resolve a template URI and return its content.
     *
     * @param string $templateUri Template URI (e.g., "github:org/repo/path@version")
     * @return string Template content
     * @throws \Exception if template cannot be resolved
     */
    public function resolve(string $templateUri): string
    {
        $parts = $this->parseUri($templateUri);
        
        foreach ($this->providers as $provider) {
            if ($provider->canHandle($parts)) {
                return $provider->fetch($parts);
            }
        }
        
        throw new \Exception("No provider found for template URI: {$templateUri}");
    }

    /**
     * Parse a template URI into components.
     *
     * Supported formats:
     * - github:org/repo/path/to/file.hbs@version
     * - http://example.com/template.hbs
     * - https://example.com/template.hbs
     * - local:family-slug/variant
     * - local:template-id
     *
     * @param string $uri Template URI
     * @return array Parsed components
     */
    public function parseUri(string $uri): array
    {
        // Check for provider prefix (provider:rest)
        if (!str_contains($uri, ':')) {
            throw new \InvalidArgumentException("Invalid template URI format: {$uri}");
        }

        [$provider, $rest] = explode(':', $uri, 2);
        $provider = strtolower($provider);

        $parts = ['provider' => $provider, 'original' => $uri];

        switch ($provider) {
            case 'github':
                return $this->parseGitHubUri($rest, $parts);
            
            case 'http':
            case 'https':
                $parts['url'] = $rest;
                return $parts;
            
            case 'local':
                return $this->parseLocalUri($rest, $parts);
            
            default:
                throw new \InvalidArgumentException("Unknown provider: {$provider}");
        }
    }

    /**
     * Parse GitHub URI format: org/repo/path/to/file.hbs@version
     */
    protected function parseGitHubUri(string $rest, array $parts): array
    {
        // Extract version if present (e.g., @v1.0.0)
        $version = 'main';
        if (str_contains($rest, '@')) {
            [$rest, $version] = explode('@', $rest, 2);
        }

        // Split into org/repo/path components
        $segments = explode('/', $rest);
        if (count($segments) < 3) {
            throw new \InvalidArgumentException("GitHub URI must be: org/repo/path@version");
        }

        $parts['org'] = $segments[0];
        $parts['repo'] = $segments[1];
        $parts['path'] = implode('/', array_slice($segments, 2));
        $parts['version'] = $version;

        return $parts;
    }

    /**
     * Parse local URI format: family-slug/variant or template-id
     */
    protected function parseLocalUri(string $rest, array $parts): array
    {
        if (str_contains($rest, '/')) {
            // Format: family-slug/variant
            [$family, $variant] = explode('/', $rest, 2);
            $parts['family'] = $family;
            $parts['variant'] = $variant;
        } else {
            // Format: template-id
            $parts['id'] = $rest;
        }

        return $parts;
    }

    /**
     * Build a template URI from components.
     *
     * @param string $provider Provider name (github, http, local)
     * @param array $params Parameters for the URI
     * @return string Template URI
     */
    public function buildUri(string $provider, array $params): string
    {
        switch ($provider) {
            case 'github':
                $org = $params['org'] ?? throw new \InvalidArgumentException('org is required');
                $repo = $params['repo'] ?? throw new \InvalidArgumentException('repo is required');
                $path = $params['path'] ?? throw new \InvalidArgumentException('path is required');
                $version = $params['version'] ?? 'main';
                return "github:{$org}/{$repo}/{$path}@{$version}";
            
            case 'http':
            case 'https':
                $url = $params['url'] ?? throw new \InvalidArgumentException('url is required');
                return "{$provider}:{$url}";
            
            case 'local':
                if (isset($params['family'], $params['variant'])) {
                    return "local:{$params['family']}/{$params['variant']}";
                } elseif (isset($params['id'])) {
                    return "local:{$params['id']}";
                }
                throw new \InvalidArgumentException('Local URI requires either family/variant or id');
            
            default:
                throw new \InvalidArgumentException("Unknown provider: {$provider}");
        }
    }
}
