<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateFamily extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'category',
        'repo_url',
        'version',
        'is_public',
        'user_id',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get the user that owns the template family.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all templates in this family.
     */
    public function templates(): HasMany
    {
        return $this->hasMany(OmrTemplate::class, 'family_id');
    }

    /**
     * Get the default template for this family.
     */
    public function defaultTemplate()
    {
        return $this->templates()
            ->where('layout_variant', 'default')
            ->first();
    }

    /**
     * Get all unique layout variants in this family.
     */
    public function layoutVariants()
    {
        return $this->templates()
            ->select('layout_variant')
            ->distinct()
            ->pluck('layout_variant');
    }

    /**
     * Get a specific layout variant.
     */
    public function getVariant(string $variant)
    {
        return $this->templates()
            ->where('layout_variant', $variant)
            ->first();
    }

    /**
     * Scope to get public families.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to get families by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get families accessible by a user (public or owned by them).
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
}
