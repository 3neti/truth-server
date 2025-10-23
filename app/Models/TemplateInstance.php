<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'document_id',
        'data',
        'compiled_spec',
        'pdf_path',
        'coords_path',
    ];

    protected $casts = [
        'data' => 'array',
        'compiled_spec' => 'array',
    ];

    /**
     * Get the template that was used to create this instance.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OmrTemplate::class);
    }

    /**
     * Scope to find by document ID.
     */
    public function scopeByDocumentId($query, string $documentId)
    {
        return $query->where('document_id', $documentId);
    }
}
