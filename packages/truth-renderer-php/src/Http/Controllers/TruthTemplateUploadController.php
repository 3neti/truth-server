<?php

namespace TruthRenderer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use TruthRenderer\Engine\HandlebarsEngine;
use TruthRenderer\Template\TemplateRegistry;
use ZipArchive;

class TruthTemplateUploadController extends Controller
{
    public function __construct(
        private readonly TemplateRegistry $registry,
    ) {}

    /**
     * Accepts either:
     *  - ZIP archive (field: zip) containing:
     *      template.hbs (or template.html), optional schema.json, optional partials/*.hbs
     *  - Manual fields:
     *      template (file or string), schema (file or string, optional), partials[] (files, optional)
     *
     * Params:
     *  - namespace (string, default: config('truth-renderer.upload.default_namespace','core'))
     *  - slug (string, required; may include slashes "invoice/basic")
     *  - dryRun (bool, default true) compile with LightnCandy before finalizing
     *  - overwrite (bool, default false) allow replacing an existing folder
     */
    public function upload(Request $req): Response
    {
        $nsDefault   = (string) config('truth-renderer.upload.default_namespace', 'core');
        $ns          = $this->sanitizeNamespace($req->string('namespace', $nsDefault)->toString());
        $slug        = $this->sanitizeSlug($req->string('slug', '')->toString());
        $dryRun      = filter_var($req->input('dryRun', true), FILTER_VALIDATE_BOOLEAN);
        $overwrite   = filter_var($req->input('overwrite', false), FILTER_VALIDATE_BOOLEAN);

        if ($slug === '') {
            return response(['error' => 'Missing or invalid slug'], 422);
        }

        // Resolve base path from config registry
        $paths = (array) config('truth-renderer.paths', [
            'core' => base_path('resources/truth-templates'),
        ]);
        $base = $paths[$ns] ?? null;
        if (!$base) {
            return response(['error' => "Unknown namespace: {$ns}"], 422);
        }

        $targetDir = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $slug;

        // Prevent directory traversal
        if (!str_starts_with(realpath(dirname($targetDir)) ?: dirname($targetDir), rtrim($base, DIRECTORY_SEPARATOR))) {
            return response(['error' => 'Invalid slug path'], 422);
        }

        if (is_dir($targetDir)) {
            if (!$overwrite) {
                return response(['error' => "Target exists: {$ns}:{$slug}. Use overwrite=true to replace."], 409);
            }
            $this->rrmdir($targetDir);
        }

        // Create working dir
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true)) {
            return response(['error' => "Failed to create target dir: {$targetDir}"], 500);
        }

        // Gather sources
        $mainTplName   = null;         // template.hbs or template.html
        $mainTplSource = null;         // string
        $schemaSource  = null;         // string|null
        $partialsMap   = [];           // [name => source]

        // Mode 1: ZIP
        if ($req->hasFile('zip')) {
            $zipFile = $req->file('zip');
            if (!$zipFile->isValid()) {
                return response(['error' => 'Invalid ZIP upload'], 422);
            }

            $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'truth_zip_' . Str::random(8);
            if (!@mkdir($tmpDir, 0775, true)) {
                return response(['error' => 'Failed to create temp dir'], 500);
            }

            $zip = new ZipArchive();
            if ($zip->open($zipFile->getRealPath()) !== true) {
                return response(['error' => 'Failed to open ZIP'], 422);
            }
            $zip->extractTo($tmpDir);
            $zip->close();

            // Find template.*; prefer .hbs then .html
            $mainTplPath = $this->findFirstFile($tmpDir, ['template.hbs', 'template.html']);
            if (!$mainTplPath) {
                $this->rrmdir($tmpDir);
                return response(['error' => 'template.hbs or template.html not found in ZIP'], 422);
            }
            $mainTplName   = basename($mainTplPath);
            $mainTplSource = file_get_contents($mainTplPath);

            // Optional schema.json
            $schemaPath = $this->findFirstFile($tmpDir, ['schema.json']);
            if ($schemaPath && is_file($schemaPath)) {
                $schemaSource = file_get_contents($schemaPath);
                // sanity: JSON must parse (we only verify well-formed here)
                json_decode($schemaSource);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->rrmdir($tmpDir);
                    return response(['error' => 'schema.json is not valid JSON'], 422);
                }
            }

            // Partials under partials/*.hbs
            $partialsDir = $tmpDir . DIRECTORY_SEPARATOR . 'partials';
            if (is_dir($partialsDir)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
                    $partialsDir,
                    \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
                ));
                foreach ($it as $fileInfo) {
                    /** @var \SplFileInfo $fileInfo */
                    if (!$fileInfo->isFile()) continue;
                    if (strtolower($fileInfo->getExtension()) !== 'hbs') continue;
                    $relName = trim(str_replace($partialsDir, '', $fileInfo->getPathname()), DIRECTORY_SEPARATOR);
                    $relName = str_replace(DIRECTORY_SEPARATOR, '/', $relName);
                    $nameNoExt = preg_replace('/\.hbs$/i', '', $relName);
                    $partialsMap[$nameNoExt] = file_get_contents($fileInfo->getPathname());
                }
            }

            // Move files into target dir
            $written = [];
            $mainOut = $targetDir . DIRECTORY_SEPARATOR . $mainTplName;
            if (@file_put_contents($mainOut, $mainTplSource) === false) {
                $this->rrmdir($tmpDir);
                return response(['error' => 'Failed to write main template'], 500);
            }
            $written[] = $mainOut;

            if ($schemaSource !== null) {
                $schemaOut = $targetDir . DIRECTORY_SEPARATOR . 'schema.json';
                if (@file_put_contents($schemaOut, $schemaSource) === false) {
                    $this->rrmdir($tmpDir);
                    return response(['error' => 'Failed to write schema.json'], 500);
                }
                $written[] = $schemaOut;
            }

            if (!empty($partialsMap)) {
                $pDir = $targetDir . DIRECTORY_SEPARATOR . 'partials';
                if (!is_dir($pDir) && !@mkdir($pDir, 0775, true)) {
                    $this->rrmdir($tmpDir);
                    return response(['error' => 'Failed to create partials dir'], 500);
                }
                foreach ($partialsMap as $name => $src) {
                    $dest = $pDir . DIRECTORY_SEPARATOR . $name . '.hbs';
                    // ensure subdirs exist
                    $subdir = dirname($dest);
                    if (!is_dir($subdir) && !@mkdir($subdir, 0775, true)) {
                        $this->rrmdir($tmpDir);
                        return response(['error' => 'Failed to create partial subdir'], 500);
                    }
                    if (@file_put_contents($dest, $src) === false) {
                        $this->rrmdir($tmpDir);
                        return response(['error' => "Failed to write partial: {$name}"], 500);
                    }
                    $written[] = $dest;
                }
            }

            $this->rrmdir($tmpDir);
        }
        // Mode 2: Manual fields
        else {
            // template as file or as string
            $mainTplName = 'template.hbs';
            if ($req->hasFile('template')) {
                $f = $req->file('template');
                if (!$f->isValid()) {
                    return response(['error' => 'Invalid template file'], 422);
                }
                $mainTplName = $f->getClientOriginalName();
                $mainTplSource = file_get_contents($f->getRealPath());
            } else {
                $mainTplSource = (string) $req->input('template', '');
            }
            if (!trim((string) $mainTplSource)) {
                return response(['error' => 'Missing template content'], 422);
            }

            // schema as file or as string
            if ($req->hasFile('schema')) {
                $sf = $req->file('schema');
                if (!$sf->isValid()) {
                    return response(['error' => 'Invalid schema file'], 422);
                }
                $schemaSource = file_get_contents($sf->getRealPath());
            } elseif ($req->filled('schema')) {
                $schemaRaw = $req->input('schema');
                $schemaSource = is_array($schemaRaw)
                    ? json_encode($schemaRaw)
                    : (string) $schemaRaw;
            }

            if ($schemaSource !== null) {
                json_decode($schemaSource);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response(['error' => 'Provided schema is not valid JSON'], 422);
                }
            }

            // partials[] as files
            if ($req->hasFile('partials')) {
                foreach ((array) $req->file('partials') as $pf) {
                    if (!$pf->isValid()) continue;
                    if (strtolower($pf->getClientOriginalExtension() ?: '') !== 'hbs') continue;
                    $nameNoExt = preg_replace('/\.hbs$/i', '', $pf->getClientOriginalName());
                    $partialsMap[$nameNoExt] = file_get_contents($pf->getRealPath());
                }
            }

            // Write to disk
            $written  = [];
            $mainOut  = $targetDir . DIRECTORY_SEPARATOR . $mainTplName;
            if (@file_put_contents($mainOut, $mainTplSource) === false) {
                return response(['error' => 'Failed to write main template'], 500);
            }
            $written[] = $mainOut;

            if ($schemaSource !== null) {
                $schemaOut = $targetDir . DIRECTORY_SEPARATOR . 'schema.json';
                if (@file_put_contents($schemaOut, $schemaSource) === false) {
                    return response(['error' => 'Failed to write schema.json'], 500);
                }
                $written[] = $schemaOut;
            }

            if (!empty($partialsMap)) {
                $pDir = $targetDir . DIRECTORY_SEPARATOR . 'partials';
                if (!is_dir($pDir) && !@mkdir($pDir, 0775, true)) {
                    return response(['error' => 'Failed to create partials dir'], 500);
                }
                foreach ($partialsMap as $name => $src) {
                    $dest = $pDir . DIRECTORY_SEPARATOR . $name . '.hbs';
                    $subdir = dirname($dest);
                    if (!is_dir($subdir) && !@mkdir($subdir, 0775, true)) {
                        return response(['error' => 'Failed to create partial subdir'], 500);
                    }
                    if (@file_put_contents($dest, $src) === false) {
                        return response(['error' => "Failed to write partial: {$name}"], 500);
                    }
                    $written[] = $dest;
                }
            }
        }

        // Optional dry-run compile to catch template/partial/helper issues early
        if ($dryRun) {
            try {
                $engine   = new HandlebarsEngine();
                $partials = $this->loadPartialsFrom($targetDir . DIRECTORY_SEPARATOR . 'partials');
                $mainSrc  = file_get_contents($targetDir . DIRECTORY_SEPARATOR . $mainTplName) ?: '';
                // render with empty data to at least compile, you can accept sampleData from request later
                $engine->render($mainSrc, [], $partials, []);
            } catch (\Throwable $e) {
                // clean up on failure if we just created the folder
                if (!$overwrite) {
                    $this->rrmdir($targetDir);
                }
                return response(['error' => 'Dry-run compile failed: ' . $e->getMessage()], 422);
            }
        }

        $templateName = "{$ns}:" . $this->normalizeRelName($slug) . '/template';

        return response([
            'ok'           => true,
            'templateName' => $templateName,
            'files'        => $written,
        ], 201);
    }

    // ---------- helpers ----------

    private function sanitizeNamespace(string $ns): string
    {
        $ns = trim($ns);
        $ns = preg_replace('/[^a-z0-9_\-]/i', '', $ns) ?? '';
        return $ns ?: 'core';
    }

    private function sanitizeSlug(string $slug): string
    {
        $slug = trim($slug);
        // allow nested slugs (invoice/basic), keep a-z0-9-_/
        $slug = preg_replace('#[^a-z0-9/_\-]#i', '', $slug) ?? '';
        // collapse multiple slashes
        $slug = preg_replace('#/+#', '/', $slug) ?? '';
        // strip ../
        $slug = str_replace(['..'], '', $slug);
        return trim($slug, '/');
    }

    private function normalizeRelName(string $rel): string
    {
        $rel = trim($rel, '/');
        return str_replace('\\', '/', $rel);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
        }
        @rmdir($dir);
    }

    private function findFirstFile(string $dir, array $names): ?string
    {
        foreach ($names as $n) {
            $p = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $n;
            if (is_file($p)) return $p;
        }
        return null;
    }

    /** @return array<string,string> name => source */
    private function loadPartialsFrom(string $partialsDir): array
    {
        if (!is_dir($partialsDir)) return [];
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $partialsDir,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
        ));
        foreach ($it as $fi) {
            /** @var \SplFileInfo $fi */
            if (!$fi->isFile()) continue;
            if (strtolower($fi->getExtension()) !== 'hbs') continue;
            $rel = substr($fi->getPathname(), strlen($partialsDir) + 1);
            $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
            $name = preg_replace('/\.hbs$/i', '', $rel);
            $out[$name] = file_get_contents($fi->getPathname());
        }
        return $out;
    }
}
