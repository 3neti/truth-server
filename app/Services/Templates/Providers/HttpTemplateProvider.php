<?php

namespace App\Services\Templates\Providers;

use App\Services\Templates\Contracts\TemplateProviderInterface;
use Illuminate\Support\Facades\Http;

class HttpTemplateProvider implements TemplateProviderInterface
{
    public function fetch(array $parts): string
    {
        // URI format: http:full-url or https:full-url
        // Example: https:example.com/templates/ballot.hbs
        
        $url = $parts['url'] ?? throw new \InvalidArgumentException('URL is required');
        
        // Reconstruct full URL with protocol
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $protocol = $parts['provider'] === 'https' ? 'https://' : 'http://';
            $url = $protocol . $url;
        }
        
        $response = Http::timeout(10)
            ->get($url);
        
        if ($response->failed()) {
            throw new \Exception("Failed to fetch template from URL: {$url}. Status: {$response->status()}");
        }
        
        return $response->body();
    }

    public function canHandle(array $parts): bool
    {
        return isset($parts['provider']) && in_array($parts['provider'], ['http', 'https']);
    }

    public function getName(): string
    {
        return 'http';
    }
}
