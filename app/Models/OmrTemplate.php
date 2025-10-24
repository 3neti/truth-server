<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'handlebars_template',
        'sample_data',
        'schema',
        'is_public',
        'user_id',
        'family_id',
        'layout_variant',
        'version',
    ];

    protected $casts = [
        'sample_data' => 'array',
        'schema' => 'array',
        'is_public' => 'boolean',
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
    public function createVersion(string $changelog = null, int $userId = null): TemplateVersion
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
}
