<?php

namespace TruthElectionDb\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use TruthElectionDb\Database\Factories\MappingFactory;
use Illuminate\Database\Eloquent\Model;

class Mapping extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'location_name', 'district'];

    public static function newFactory(): MappingFactory
    {
        return MappingFactory::new();
    }

    public function marks()
    {
        return $this->hasMany(Mark::class);
    }
}
