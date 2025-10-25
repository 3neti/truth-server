<?php

namespace App\Services\Templates\Providers;

use App\Models\OmrTemplate;
use App\Services\Templates\Contracts\TemplateProviderInterface;

class LocalTemplateProvider implements TemplateProviderInterface
{
    public function fetch(array $parts): string
    {
        // URI format: local:template-id or local:family-slug/variant
        // Example: local:ballot-2025/single-column
        
        if (isset($parts['id'])) {
            // Fetch by ID
            $template = OmrTemplate::find($parts['id']);
            if (!$template) {
                throw new \Exception("Template with ID {$parts['id']} not found");
            }
            return $template->handlebars_template;
        }
        
        if (isset($parts['family'], $parts['variant'])) {
            // Fetch by family/variant
            $template = OmrTemplate::whereHas('family', function ($q) use ($parts) {
                $q->where('slug', $parts['family']);
            })
            ->where('layout_variant', $parts['variant'])
            ->first();
            
            if (!$template) {
                throw new \Exception("Template {$parts['family']}/{$parts['variant']} not found");
            }
            
            return $template->handlebars_template;
        }
        
        throw new \InvalidArgumentException('Local provider requires either id or family/variant');
    }

    public function canHandle(array $parts): bool
    {
        return isset($parts['provider']) && $parts['provider'] === 'local';
    }

    public function getName(): string
    {
        return 'local';
    }
}
