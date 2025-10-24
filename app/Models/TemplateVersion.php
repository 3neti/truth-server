<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'version',
        'handlebars_template',
        'sample_data',
        'changelog',
        'created_by',
    ];

    protected $casts = [
        'sample_data' => 'array',
    ];

    /**
     * Get the template this version belongs to.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OmrTemplate::class, 'template_id');
    }

    /**
     * Get the user who created this version.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get versions for a template.
     */
    public function scopeForTemplate($query, int $templateId)
    {
        return $query->where('template_id', $templateId)->latest();
    }

    /**
     * Parse semantic version into components.
     */
    public function versionParts(): array
    {
        preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $this->version, $matches);
        return [
            'major' => (int)($matches[1] ?? 0),
            'minor' => (int)($matches[2] ?? 0),
            'patch' => (int)($matches[3] ?? 0),
        ];
    }
}
