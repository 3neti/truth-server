<?php

namespace TruthElectionDb\Models;

use TruthElectionDb\Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use TruthElection\Data\PositionData;
use Spatie\LaravelData\WithData;
use TruthElection\Enums\Level;

/**
 * Class Position
 *
 * Represents an elective position in the election (e.g., President, Mayor, Councilor).
 * Each position is identified by a unique string code and includes information about
 * its level (national, provincial, city, etc.) and the number of candidates allowed.
 *
 * @property string $code        Unique code identifier for the position (primary key).
 * @property string $name        Descriptive name of the position (e.g., "President of the Philippines").
 * @property Level  $level       Enum value indicating the level of the position (e.g., national, local).
 * @property int    $count       Number of individuals that can be elected to this position per precinct.
 *
 * @method string getKey()       Returns the primary key value of the model.
 */
class Position extends Model
{
    use HasFactory;
    use WithData;

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'level',
        'count',
    ];

    protected $casts = [
        'level' => Level::class,
    ];

    protected string $dataClass = PositionData::class;

    public static function newFactory(): PositionFactory
    {
        return PositionFactory::new();
    }

    public static function fromData(PositionData $data): static
    {
        return static::updateOrCreate(
            ['code' => $data->code],
            $data->toArray()
        );
    }
}
