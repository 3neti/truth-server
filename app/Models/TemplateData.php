<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TemplateData extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'template_data';

    protected $fillable = [
        'document_id',
        'name',
        'template_id',
        'template_ref',
        'portable_format',
        'json_data',
        'compiled_spec',
        'user_id',
    ];

    protected $casts = [
        'json_data' => 'array',
        'compiled_spec' => 'array',
        'portable_format' => 'boolean',
    ];

    protected $appends = ['formatted_date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->created_at->format('M d, Y');
    }

}
