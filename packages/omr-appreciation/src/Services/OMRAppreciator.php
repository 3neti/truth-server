<?php

namespace LBHurtado\OMRAppreciation\Services;

use RuntimeException;

class OMRAppreciator
{
    /**
     * Run Python OMR appreciation script.
     *
     * @param  string  $imagePath  Absolute path to the scanned/captured image
     * @param  string  $templatePath  Absolute path to the template JSON file
     * @param  float  $threshold  Fill threshold (0.0 to 1.0, default: 0.3)
     * @return array Appreciation results as associative array
     *
     * @throws RuntimeException if Python script fails or returns invalid output
     */
    public function run(string $imagePath, string $templatePath, float $threshold = 0.3): array
    {
        // Validate input files exist
        if (! file_exists($imagePath)) {
            throw new RuntimeException("Image file not found: {$imagePath}");
        }

        if (! file_exists($templatePath)) {
            throw new RuntimeException("Template file not found: {$templatePath}");
        }

        // Construct Python command
        $python = $this->getPythonExecutable();
        $script = base_path('packages/omr-appreciation/omr-python/appreciate.py');

        if (! file_exists($script)) {
            throw new RuntimeException("Python appreciation script not found: {$script}");
        }

        // Build command with proper escaping
        $command = sprintf(
            '%s %s %s %s --threshold %s 2>&1',
            escapeshellarg($python),
            escapeshellarg($script),
            escapeshellarg($imagePath),
            escapeshellarg($templatePath),
            $threshold
        );

        // Execute Python script
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $errorOutput = implode("\n", $output);
            throw new RuntimeException("Python OMR script failed: {$errorOutput}");
        }

        // Parse JSON output
        $jsonOutput = implode("\n", $output);
        $result = json_decode($jsonOutput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to parse Python script output: '.json_last_error_msg());
        }

        return $result;
    }

    /**
     * Run Python OMR appreciation script in debug mode.
     *
     * @param  string  $imagePath  Absolute path to the scanned/captured image
     * @param  string  $templatePath  Absolute path to the template JSON file
     * @param  float  $threshold  Fill threshold (0.0 to 1.0, default: 0.3)
     * @param  string|null  $outputPath  Optional path for debug images
     * @return array Appreciation results as associative array
     *
     * @throws RuntimeException if Python script fails or returns invalid output
     */
    public function runDebug(string $imagePath, string $templatePath, float $threshold = 0.3, ?string $outputPath = null): array
    {
        // Validate input files exist
        if (! file_exists($imagePath)) {
            throw new RuntimeException("Image file not found: {$imagePath}");
        }

        if (! file_exists($templatePath)) {
            throw new RuntimeException("Template file not found: {$templatePath}");
        }

        // Construct Python command for debug script
        $python = $this->getPythonExecutable();
        $script = base_path('packages/omr-appreciation/omr-python/appreciate_debug.py');

        if (! file_exists($script)) {
            throw new RuntimeException("Python debug script not found: {$script}");
        }

        // Determine debug output image path
        $debugImagePath = $outputPath 
            ? dirname($outputPath).'/'.pathinfo($outputPath, PATHINFO_FILENAME).'-debug.jpg'
            : dirname($imagePath).'/'.pathinfo($imagePath, PATHINFO_FILENAME).'-debug.jpg';

        // Build command with proper escaping
        $command = sprintf(
            '%s %s %s %s %s 2>&1',
            escapeshellarg($python),
            escapeshellarg($script),
            escapeshellarg($imagePath),
            escapeshellarg($templatePath),
            escapeshellarg($debugImagePath)
        );

        // Execute Python script
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $errorOutput = implode("\n", $output);
            throw new RuntimeException("Python OMR debug script failed: {$errorOutput}");
        }

        // Parse JSON output
        $jsonOutput = implode("\n", $output);
        $result = json_decode($jsonOutput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to parse Python script output: '.json_last_error_msg());
        }

        // Add debug image paths to result
        $result['debug'] = [
            'aligned_image' => $debugImagePath,
            'original_image' => str_replace('-debug.jpg', '-debug_original.jpg', $debugImagePath),
        ];

        return $result;
    }

    /**
     * Get the Python executable path.
     * Prefers virtual environment if available.
     *
     * @return string
     */
    protected function getPythonExecutable(): string
    {
        $venvPython = base_path('packages/omr-appreciation/omr-python/venv/bin/python');

        if (file_exists($venvPython)) {
            return $venvPython;
        }

        // Fallback to system Python
        return 'python3';
    }
}
