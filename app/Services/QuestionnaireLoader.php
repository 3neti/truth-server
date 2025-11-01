<?php

namespace App\Services;

use App\Models\TemplateData;
use Illuminate\Support\Facades\File;
use RuntimeException;

class QuestionnaireLoader
{
    /**
     * Load questionnaire data from file or database
     * 
     * Priority:
     * 1. File (if configPath provided and file exists)
     * 2. Database (if documentId provided)
     * 3. null (if neither available)
     * 
     * @param string|null $configPath Optional config directory path (relative to base_path)
     * @param string|null $documentId Database document ID (fallback)
     * @return array|null Questionnaire data or null
     */
    public function load(?string $configPath = null, ?string $documentId = null): ?array
    {
        // Try file-based loading first
        if ($configPath) {
            $questionnaire = $this->loadFromFile($configPath);
            if ($questionnaire !== null) {
                return $questionnaire;
            }
        }
        
        // Fall back to database
        if ($documentId) {
            $questionnaire = $this->loadFromDatabase($documentId);
            if ($questionnaire !== null) {
                return $questionnaire;
            }
        }
        
        return null;
    }
    
    /**
     * Load questionnaire from file system
     * 
     * @param string $configPath Config directory path
     * @return array|null Questionnaire data or null if not found/invalid
     */
    protected function loadFromFile(string $configPath): ?array
    {
        $resolvedDir = $this->resolvePath($configPath);
        $questionnaireFile = rtrim($resolvedDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'questionnaire.json';
        
        if (!File::exists($questionnaireFile)) {
            return null;
        }
        
        try {
            $json = File::get($questionnaireFile);
            $data = json_decode($json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Invalid JSON in questionnaire.json: ' . json_last_error_msg());
            }
            
            return $data;
        } catch (\Exception $e) {
            // Log error but don't throw - allow fallback to database
            if (function_exists('report')) {
                report($e);
            }
            return null;
        }
    }

    /**
     * Resolve a path that may be absolute or relative to base_path
     */
    protected function resolvePath(string $path): string
    {
        // Absolute path detection for Unix and Windows
        $isAbsolute = (
            DIRECTORY_SEPARATOR === '/' && str_starts_with($path, '/')
        ) || (
            DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Za-z]:\\\\/', $path) === 1
        );
        
        if ($isAbsolute) {
            return $path;
        }
        
        // base_path may not be available in some contexts (e.g., early bootstrap)
        if (function_exists('base_path')) {
            return base_path($path);
        }
        
        // Fallback to current working directory
        return getcwd() . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
    
    /**
     * Load questionnaire from database
     * 
     * @param string $documentId Document ID to load
     * @return array|null Questionnaire data or null if not found
     */
    protected function loadFromDatabase(string $documentId): ?array
    {
        try {
            $template = TemplateData::where('document_id', $documentId)->first();
            
            if ($template && $template->json_data) {
                return $template->json_data;
            }
            
            return null;
        } catch (\Exception $e) {
            // Database not available or query failed
            // Log error but don't throw - graceful degradation
            if (function_exists('report')) {
                report($e);
            }
            return null;
        }
    }
    
    /**
     * Load questionnaire with auto-detection using ElectionConfigLoader
     * 
     * Uses the configured election config path to attempt file loading,
     * then falls back to database if a document_id is specified in config.
     * 
     * @return array|null Questionnaire data or null
     */
    public function loadAuto(): ?array
    {
        try {
            $configLoader = app(ElectionConfigLoader::class);
            $configPath = $configLoader->getConfigPath();
            
            // Try to extract document_id from election config if it exists
            $documentId = null;
            try {
                $election = $configLoader->loadElection();
                $documentId = $election['questionnaire_document_id'] ?? null;
            } catch (\Exception $e) {
                // Config not available or doesn't have questionnaire_document_id
            }
            
            return $this->load($configPath, $documentId);
        } catch (\Exception $e) {
            // ElectionConfigLoader not available
            if (function_exists('report')) {
                report($e);
            }
            return null;
        }
    }
    
    /**
     * Validate questionnaire structure
     * 
     * Ensures questionnaire has the expected structure:
     * - 'positions' array
     * - Each position has 'code', 'name', 'candidates'
     * - Each candidate has 'code', 'name'
     * 
     * @param array $questionnaire Questionnaire data to validate
     * @return bool True if valid
     * @throws RuntimeException If invalid
     */
    public function validate(array $questionnaire): bool
    {
        if (!isset($questionnaire['positions'])) {
            throw new RuntimeException('Questionnaire missing "positions" array');
        }
        
        if (!is_array($questionnaire['positions'])) {
            throw new RuntimeException('Questionnaire "positions" must be an array');
        }
        
        foreach ($questionnaire['positions'] as $index => $position) {
            if (!isset($position['code'])) {
                throw new RuntimeException("Position at index {$index} missing 'code'");
            }
            
            if (!isset($position['name'])) {
                throw new RuntimeException("Position at index {$index} missing 'name'");
            }
            
            if (!isset($position['candidates']) || !is_array($position['candidates'])) {
                throw new RuntimeException("Position '{$position['code']}' missing 'candidates' array");
            }
            
            foreach ($position['candidates'] as $candIndex => $candidate) {
                if (!isset($candidate['code'])) {
                    throw new RuntimeException("Candidate at index {$candIndex} in position '{$position['code']}' missing 'code'");
                }
                
                if (!isset($candidate['name'])) {
                    throw new RuntimeException("Candidate at index {$candIndex} in position '{$position['code']}' missing 'name'");
                }
            }
        }
        
        return true;
    }
}
