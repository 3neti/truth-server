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
        'name',
        'description',
        'template_ref',
        'data',
        'user_id',
        'is_public',
        'category',
    ];

    protected $casts = [
        'data' => 'array',
        'is_public' => 'boolean',
    ];

    protected $appends = ['formatted_date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->created_at->format('M d, Y');
    }

    /**
     * Get template_ref from data.document.template_ref if it exists in the JSON,
     * otherwise fall back to the template_ref column.
     */
    public function getTemplateRefAttribute($value): ?string
    {
        // Check if template_ref is embedded in data JSON
        if (isset($this->attributes['data'])) {
            $data = json_decode($this->attributes['data'], true);
            if (isset($data['document']['template_ref'])) {
                return $data['document']['template_ref'];
            }
        }
        
        // Fall back to column value
        return $value;
    }

    /**
     * When setting template_ref, just set the column.
     * The sync to data JSON happens in the save() method.
     */
    public function setTemplateRefAttribute($value): void
    {
        $this->attributes['template_ref'] = $value;
    }

    /**
     * Hook into the saving event to sync template_ref.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($model) {
            // Get data as array (Laravel will have already cast it)
            $data = $model->data;
            
            if (is_array($data)) {
                // If template_ref column is set, embed it in data JSON
                if ($model->attributes['template_ref'] ?? null) {
                    if (!isset($data['document'])) {
                        $data['document'] = [];
                    }
                    $data['document']['template_ref'] = $model->attributes['template_ref'];
                    $model->data = $data; // Laravel will JSON encode this
                }
                // If data JSON has template_ref but column doesn't, sync to column
                elseif (isset($data['document']['template_ref']) && !($model->attributes['template_ref'] ?? null)) {
                    $model->attributes['template_ref'] = $data['document']['template_ref'];
                }
            }
        });
    }
}
