<?php

namespace TruthRenderer\Template;

use TruthRenderer\Contracts\TemplateRegistryInterface;

final class TemplateAssetsLoader
{
    public function __construct(
        private readonly TemplateRegistryInterface $registry
    ) {}

    /**
     * @return array{
     *   template: string,
     *   schema: array|null,
     *   partials: array<string,string>,
     *   assetsBaseUrl: string|null
     * }
     */
    public function load(string $templateName): array
    {
        // Always get the template source from the registry
        $template = $this->registry->get($templateName);

        $dir = $this->registry->resolveDir($templateName);
        $schema = null;
        $partials = [];
        $assetsBaseUrl = $dir ?: null;

        if ($dir && is_dir($dir)) {
            // schema.json (optional)
            $schemaFile = $dir . DIRECTORY_SEPARATOR . 'schema.json';
            if (is_file($schemaFile)) {
                $json = file_get_contents($schemaFile);
                if ($json !== false) {
                    $decoded = json_decode($json, true);
                    if (is_array($decoded)) {
                        $schema = $decoded;
                    }
                }
            }

            // partials/ folder (optional)
            $partialsDir = $dir . DIRECTORY_SEPARATOR . 'partials';
            if (is_dir($partialsDir)) {
                $dh = opendir($partialsDir);
                if ($dh) {
                    while (($f = readdir($dh)) !== false) {
                        if ($f === '.' || $f === '..') continue;
                        if (!preg_match('/^(?<name>.+)\.(hbs|html)$/i', $f, $m)) continue;
                        $path = $partialsDir . DIRECTORY_SEPARATOR . $f;
                        $src  = file_get_contents($path);
                        if ($src !== false) {
                            $partials[$m['name']] = $src;
                        }
                    }
                    closedir($dh);
                }
            }
        }

        return [
            'template'      => $template,
            'schema'        => $schema,
            'partials'      => $partials,
            'assetsBaseUrl' => $assetsBaseUrl,
        ];
    }
}
