<?php

namespace App\Services\Templates\Providers;

use App\Services\Templates\Contracts\TemplateProviderInterface;
use Illuminate\Support\Facades\Http;

class GitHubTemplateProvider implements TemplateProviderInterface
{
    public function fetch(array $parts): string
    {
        // URI format: github:org/repo/path/to/template.hbs@version
        // Example: github:lbhurtado/omr-templates/ballot-2025/single-column.hbs@v1.0.0
        
        $org = $parts['org'] ?? throw new \InvalidArgumentException('GitHub org is required');
        $repo = $parts['repo'] ?? throw new \InvalidArgumentException('GitHub repo is required');
        $path = $parts['path'] ?? throw new \InvalidArgumentException('Template path is required');
        $version = $parts['version'] ?? 'main'; // Default to main branch
        
        // Build raw GitHub content URL
        $url = "https://raw.githubusercontent.com/{$org}/{$repo}/{$version}/{$path}";
        
        $response = Http::timeout(10)
            ->get($url);
        
        if ($response->failed()) {
            throw new \Exception("Failed to fetch template from GitHub: {$url}. Status: {$response->status()}");
        }
        
        return $response->body();
    }

    public function canHandle(array $parts): bool
    {
        return isset($parts['provider']) && $parts['provider'] === 'github';
    }

    public function getName(): string
    {
        return 'github';
    }
}
