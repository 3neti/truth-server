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
}
