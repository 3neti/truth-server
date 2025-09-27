<?php

namespace TruthElectionDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use TruthElectionDb\Database\Factories\MarkFactory;
use Illuminate\Database\Eloquent\Model;

class Mark extends Model
{
    use HasFactory;

    protected $fillable = ['mapping_id', 'key', 'value'];

    public static function newFactory(): MarkFactory
    {
        return MarkFactory::new();
    }

    public function mapping()
    {
        return $this->belongsTo(Mapping::class);
    }
}
