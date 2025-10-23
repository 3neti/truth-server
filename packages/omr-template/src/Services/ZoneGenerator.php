<?php

namespace LBHurtado\OMRTemplate\Services;

class ZoneGenerator
{
    /**
     * Generate zones from contests/candidates data
     * This creates mark zones for each candidate option
     */
    public function generateZones(array $contestsOrSections, string $layout = 'A4', int $dpi = 300): array
    {
        if (empty($contestsOrSections)) {
            return [];
        }

        $zones = [];
        
        // Layout dimensions and starting positions (in pixels at given DPI)
        $layoutConfig = $this->getLayoutConfig($layout, $dpi);
        
        $currentY = $layoutConfig['start_y'];
        $markX = $layoutConfig['mark_x'];
        $markWidth = $layoutConfig['mark_width'];
        $markHeight = $layoutConfig['mark_height'];
        
        foreach ($contestsOrSections as $index => $contest) {
            // Space for contest title
            $currentY += $layoutConfig['title_spacing'];
            
            // Generate zones for each candidate
            $candidates = $contest['candidates'] ?? [];
            foreach ($candidates as $candidateIndex => $candidate) {
                $zoneId = $this->generateZoneId($contest, $candidateIndex, $candidate);
                
                $zones[] = [
                    'id' => $zoneId,
                    'x' => $markX,
                    'y' => $currentY,
                    'width' => $markWidth,
                    'height' => $markHeight,
                    'contest' => $contest['title'] ?? "Contest $index",
                    'candidate' => $candidate['name'] ?? "Candidate $candidateIndex",
                    // Render properties for visible mark boxes in PDF
                    'render' => [
                        'enabled' => config('omr-template.mark_boxes.enabled', true),
                        'style' => config('omr-template.mark_boxes.style', 'circle'),
                        'border_width' => config('omr-template.mark_boxes.border_width', 2),
                        'border_color' => config('omr-template.mark_boxes.border_color', '#000000'),
                        'background' => config('omr-template.mark_boxes.background', '#FFFFFF'),
                    ],
                ];
                
                // Move to next candidate position
                $currentY += $layoutConfig['candidate_spacing'];
            }
            
            // Add extra space between contests
            $currentY += $layoutConfig['contest_spacing'];
        }
        
        return $zones;
    }
    
    /**
     * Generate a unique zone ID for a candidate
     */
    protected function generateZoneId(array $contest, int $candidateIndex, array $candidate): string
    {
        // Try to use contest title + candidate name/code
        $contestSlug = $this->slugify($contest['title'] ?? '');
        
        if (isset($candidate['code'])) {
            return $candidate['code'];
        }
        
        if (isset($candidate['name'])) {
            return $contestSlug . '_' . $this->slugify($candidate['name']);
        }
        
        return $contestSlug . '_' . $candidateIndex;
    }
    
    /**
     * Convert string to slug (uppercase, underscores)
     */
    protected function slugify(string $text): string
    {
        $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
        $text = preg_replace('/\s+/', '_', trim($text));
        return strtoupper($text);
    }
    
    /**
     * Get layout-specific configuration
     */
    protected function getLayoutConfig(string $layout, int $dpi): array
    {
        // Get config from Laravel config or use fallback defaults
        $configKey = 'omr-template.zone_layout.' . strtoupper($layout);
        $layoutMultipliers = config($configKey, $this->getDefaultLayoutConfig(strtoupper($layout)));
        
        // Convert multipliers to pixel values at the given DPI
        return [
            'start_y' => (int) ($layoutMultipliers['start_y'] * $dpi),
            'mark_x' => (int) ($layoutMultipliers['mark_x'] * $dpi),
            'mark_width' => (int) ($layoutMultipliers['mark_width'] * $dpi),
            'mark_height' => (int) ($layoutMultipliers['mark_height'] * $dpi),
            'title_spacing' => (int) ($layoutMultipliers['title_spacing'] * $dpi),
            'candidate_spacing' => (int) ($layoutMultipliers['candidate_spacing'] * $dpi),
            'contest_spacing' => (int) ($layoutMultipliers['contest_spacing'] * $dpi),
        ];
    }
    
    /**
     * Get default layout configuration (fallback if config not available)
     */
    protected function getDefaultLayoutConfig(string $layout): array
    {
        $configs = [
            'A4' => [
                'start_y' => 1.35,
                'mark_x' => 0.75,
                'mark_width' => 20 / 72,
                'mark_height' => 20 / 72,
                'title_spacing' => 0.15,
                'candidate_spacing' => 0.2,
                'contest_spacing' => 0.3,
            ],
            'LETTER' => [
                'start_y' => 2.5,
                'mark_x' => 0.5,
                'mark_width' => 0.2,
                'mark_height' => 0.2,
                'title_spacing' => 0.4,
                'candidate_spacing' => 0.35,
                'contest_spacing' => 0.5,
            ],
        ];
        
        return $configs[$layout] ?? $configs['A4'];
    }
}
