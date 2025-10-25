<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use HasFactory;

    protected $table = 'templates';

    protected $fillable = [
        'name',
        'description',
        'category',
        'handlebars_template',
        'sample_data',
        'schema',
        'json_schema',
        'is_public',
        'user_id',
        'family_id',
        'layout_variant',
        'version',
        'checksum_sha256',
        'verified_at',
        'verified_by',
        'storage_type',
        'template_uri',
        'remote_metadata',
        'cached_template',
        'last_fetched_at',
    ];

    protected $casts = [
        'sample_data' => 'array',
        'schema' => 'array',
        'json_schema' => 'array',
        'is_public' => 'boolean',
        'verified_at' => 'datetime',
        'remote_metadata' => 'array',
        'last_fetched_at' => 'datetime',
    ];

    /**
     * Get the user that owns the template.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the template family this template belongs to.
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(TemplateFamily::class);
    }

    /**
     * Get the instances created from this template.
     */
    public function instances(): HasMany
    {
        return $this->hasMany(TemplateInstance::class);
    }

    /**
     * Get all versions of this template.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class, 'template_id')->latest();
    }

    /**
     * Scope to get public templates.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to get templates by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get templates accessible by a user (public or owned by them).
     */
    public function scopeAccessibleBy($query, ?int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('is_public', true);
            if ($userId) {
                $q->orWhere('user_id', $userId);
            }
        });
    }

    /**
     * Check if this is the default layout variant.
     */
    public function isDefaultLayout(): bool
    {
        return $this->layout_variant === 'default';
    }

    /**
     * Get other layout variants in the same family.
     */
    public function siblingVariants()
    {
        if (!$this->family_id) {
            return collect([]);
        }

        return static::where('family_id', $this->family_id)
            ->where('id', '!=', $this->id)
            ->get();
    }

    /**
     * Create a new version snapshot of this template.
     */
    public function createVersion(?string $changelog = null, ?int $userId = null): TemplateVersion
    {
        $userId = $userId ?? auth()->id();
        
        return $this->versions()->create([
            'version' => $this->version,
            'handlebars_template' => $this->handlebars_template,
            'sample_data' => $this->sample_data,
            'changelog' => $changelog,
            'created_by' => $userId,
        ]);
    }

    /**
     * Increment the version number.
     */
    public function incrementVersion(string $type = 'patch'): string
    {
        preg_match('/(\d+)\.(\d+)\.(\d+)/', $this->version, $matches);
        $major = (int)($matches[1] ?? 0);
        $minor = (int)($matches[2] ?? 0);
        $patch = (int)($matches[3] ?? 0);

        switch ($type) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
            default:
                $patch++;
                break;
        }

        $newVersion = "{$major}.{$minor}.{$patch}";
        $this->version = $newVersion;
        return $newVersion;
    }

    /**
     * Rollback to a specific version.
     */
    public function rollbackToVersion(int $versionId): bool
    {
        $version = $this->versions()->find($versionId);
        
        if (!$version) {
            return false;
        }

        // Create a version of current state before rollback
        $this->createVersion('Backup before rollback to v' . $version->version);

        // Restore from version
        $this->handlebars_template = $version->handlebars_template;
        $this->sample_data = $version->sample_data;
        $this->version = $version->version;
        $this->save();

        return true;
    }

    /**
     * Get version history.
     */
    public function getVersionHistory()
    {
        return $this->versions()->with('creator')->get();
    }

    /**
     * Generate SHA256 checksum of the template content.
     */
    public function generateChecksum(): string
    {
        $content = $this->handlebars_template . json_encode($this->sample_data ?? []);
        return hash('sha256', $content);
    }

    /**
     * Verify template integrity by comparing checksums.
     */
    public function verifyChecksum(): bool
    {
        if (!$this->checksum_sha256) {
            return false;
        }
        
        return $this->checksum_sha256 === $this->generateChecksum();
    }

    /**
     * Sign the template by generating and storing checksum.
     */
    public function sign(?int $userId = null): bool
    {
        $this->checksum_sha256 = $this->generateChecksum();
        $this->verified_at = now();
        $this->verified_by = $userId ?? auth()->id();
        
        return $this->save();
    }

    /**
     * Check if template is signed and verified.
     */
    public function isSigned(): bool
    {
        return !empty($this->checksum_sha256) && !empty($this->verified_at);
    }

    /**
     * Check if template has been modified since signing.
     */
    public function isModified(): bool
    {
        return $this->isSigned() && !$this->verifyChecksum();
    }

    /**
     * Validate data against JSON schema.
     */
    public function validateData(array $data): array
    {
        if (!$this->json_schema) {
            return ['valid' => true, 'errors' => []];
        }

        $errors = [];
        $schema = $this->json_schema;

        // Basic validation - check required fields
        if (isset($schema['required'])) {
            foreach ($schema['required'] as $field) {
                if (!isset($data[$field])) {
                    $errors[] = "Required field '{$field}' is missing";
                }
            }
        }

        // Validate field types
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $field => $rules) {
                if (!isset($data[$field])) {
                    continue;
                }

                $value = $data[$field];
                $type = $rules['type'] ?? null;

                if ($type && !$this->validateType($value, $type)) {
                    $errors[] = "Field '{$field}' must be of type {$type}";
                }

                // Check min/max for numbers
                if ($type === 'number' || $type === 'integer') {
                    if (isset($rules['minimum']) && $value < $rules['minimum']) {
                        $errors[] = "Field '{$field}' must be >= {$rules['minimum']}";
                    }
                    if (isset($rules['maximum']) && $value > $rules['maximum']) {
                        $errors[] = "Field '{$field}' must be <= {$rules['maximum']}";
                    }
                }

                // Check minLength/maxLength for strings
                if ($type === 'string') {
                    if (isset($rules['minLength']) && strlen($value) < $rules['minLength']) {
                        $errors[] = "Field '{$field}' must be at least {$rules['minLength']} characters";
                    }
                    if (isset($rules['maxLength']) && strlen($value) > $rules['maxLength']) {
                        $errors[] = "Field '{$field}' must be at most {$rules['maxLength']} characters";
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate value type.
     */
    private function validateType($value, string $type): bool
    {
        return match($type) {
            'string' => is_string($value),
            'number' => is_numeric($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value) || is_array($value),
            default => true,
        };
    }

    /**
     * Check if template is stored remotely.
     */
    public function isRemote(): bool
    {
        return $this->storage_type === 'remote';
    }

    /**
     * Check if template cache is stale (older than TTL).
     */
    public function isCacheStale(int $ttlSeconds = 86400): bool
    {
        if (!$this->last_fetched_at) {
            return true;
        }

        return $this->last_fetched_at->addSeconds($ttlSeconds)->isPast();
    }

    /**
     * Get template content (from cache or fetch remote).
     */
    public function getTemplateContent(bool $forceRefresh = false): string
    {
        // Local templates
        if ($this->storage_type === 'local') {
            return $this->handlebars_template;
        }

        // Remote templates - check cache first
        if (!$forceRefresh && $this->cached_template && !$this->isCacheStale()) {
            return $this->cached_template;
        }

        // Fetch from remote
        return $this->fetchAndCacheRemoteTemplate();
    }

    /**
     * Fetch template from remote source and cache it.
     */
    public function fetchAndCacheRemoteTemplate(): string
    {
        $resolver = app(\App\Services\Templates\TemplateResolver::class);
        
        try {
            $content = $resolver->resolve($this->template_uri);
            
            // Cache the content
            $this->cached_template = $content;
            $this->last_fetched_at = now();
            $this->save();
            
            return $content;
        } catch (\Exception $e) {
            // If fetch fails and we have cached content, use it
            if ($this->cached_template) {
                \Log::warning("Failed to fetch remote template {$this->template_uri}, using cached version", [
                    'error' => $e->getMessage()
                ]);
                return $this->cached_template;
            }
            
            throw $e;
        }
    }

    /**
     * Clear the template cache.
     */
    public function clearCache(): void
    {
        $this->cached_template = null;
        $this->last_fetched_at = null;
        $this->save();
    }
}
