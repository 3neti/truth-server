<?php

namespace TruthRenderer\Template;

use TruthRenderer\Contracts\TemplateRegistryInterface;

class TemplateRegistry implements TemplateRegistryInterface
{
    /**
     * @var array<string, string> in-memory templates
     */
    private array $memory = [];

    /**
     * @var array<string, string> namespace => directory
     * Example: ['core' => '/.../resources/truth-templates', 'pkg' => '/.../stubs/templates']
     * Files expected to end with ".hbs" (Handlebars) or ".html"
     */
    private array $paths;

    /**
     * @param array<string, string> $paths optional namespace=>directory map
     */
    public function __construct(array $paths = [])
    {
        $this->paths = $paths;
    }

    public function set(string $name, string $source): void
    {
        $this->memory[$name] = $source;
    }

    public function get(string $name): string
    {
        // 1) in-memory
        if (isset($this->memory[$name])) {
            return $this->memory[$name];
        }

        // 2) filesystem (supports subpaths like "invoice/basic/template")
        [$ns, $basename] = $this->splitName($name);
        $dirs = $this->candidateDirs($ns);

        foreach ($dirs as $root) {
            foreach (['.hbs', '.html'] as $ext) {
                $file = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $basename . $ext;
                if (is_file($file)) {
                    $src = file_get_contents($file);
                    if ($src === false) {
                        throw new \RuntimeException("Failed to read template file: {$file}");
                    }
                    return $src;
                }
            }
        }

        throw new \RuntimeException("Template not found: {$name}");
    }

    public function list(): array
    {
        $names = array_keys($this->memory);

        foreach ($this->paths as $ns => $root) {
            if (!is_dir($root)) {
                continue;
            }
            $found = $this->scanTemplatesRecursively($root);
            foreach ($found as $relNoExt) {
                // NEW: skip anything inside a "partials" folder
                if ($this->isPartialPath($relNoExt)) {
                    continue;
                }
                $names[] = $ns ? ($ns . ':' . $relNoExt) : $relNoExt;
            }
        }

        // unique + natural sort
        $names = array_values(array_unique($names));
        natcasesort($names);
        return array_values($names);
    }

    /**
     * Recursively collect relative template names (without extension) under $root.
     * @return string[] e.g. ['invoice/basic/template', 'ballot/simple']
     */
    private function scanTemplatesRecursively(string $root): array
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $out = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if (!$fileInfo->isFile()) continue;

            $ext = strtolower($fileInfo->getExtension());
            if ($ext !== 'hbs' && $ext !== 'html') continue;

            $absPath = $fileInfo->getPathname();
            // relative path without extension
            $rel = substr($absPath, strlen($root) + 1); // +1 for '/'
            $relNoExt = preg_replace('/\.(hbs|html)$/i', '', $rel);
            // normalize directory separators to '/'
            $relNoExt = str_replace(DIRECTORY_SEPARATOR, '/', $relNoExt);

            if ($relNoExt !== '') {
                $out[] = $relNoExt;
            }
        }

        return $out;
    }

    /**
     * @return array{string|null,string} [namespace|null, basename]
     */
    private function splitName(string $name): array
    {
        if (str_contains($name, ':')) {
            [$ns, $base] = explode(':', $name, 2);
            return [trim($ns) ?: null, trim($base)];
        }
        return [null, $name];
    }

    /**
     * @param string|null $ns
     * @return string[] directories to search, in priority order
     */
    private function candidateDirs(?string $ns): array
    {
        if ($ns !== null) {
            return isset($this->paths[$ns]) ? [$this->paths[$ns]] : [];
        }
        return array_values($this->paths);
    }

    public function resolveFile(string $name): ?string
    {
        // memory first
        if (isset($this->memory[$name])) {
            return null; // in-memory; no file
        }

        [$ns, $basename] = $this->splitName($name);
        foreach ($this->candidateDirs($ns) as $dir) {
            foreach (['.hbs', '.html'] as $ext) {
                $file = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $basename . $ext;
                if (is_file($file)) {
                    return realpath($file) ?: $file;
                }
            }
        }
        return null;
    }

    public function resolveDir(string $name): ?string
    {
        $file = $this->resolveFile($name);
        return $file ? dirname($file) : null;
    }

    public function has(string $name): bool
    {
        if (isset($this->memory[$name])) {
            return true;
        }

        [$ns, $basename] = $this->splitName($name);

        foreach ($this->candidateDirs($ns) as $dir) {
            foreach (['.hbs', '.html'] as $ext) {
                $file = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $basename . $ext;
                if (is_file($file)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A path is considered a "partial" when any segment is literally "partials".
     * Matches:
     *  - partials/foo
     *  - invoice/basic/partials/itemRow
     *  - partials
     */
    private function isPartialPath(string $relNoExt): bool
    {
        return (bool) preg_match('~(^|/)partials(/|$)~i', $relNoExt);
    }
}
