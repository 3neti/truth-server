<?php

namespace TruthElectionDb\Models;

use TruthElectionDb\Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use TruthElection\Data\CandidateData;
use Spatie\LaravelData\WithData;

/**
 * Class Candidate
 *
 * Represents an electoral candidate running for a specific position in the election.
 * Each candidate is uniquely identified by a string code, and is associated with one position.
 *
 * @property string                      $code           Unique code identifying the candidate (primary key).
 * @property string                      $name           Full name of the candidate.
 * @property string                      $alias          Nickname or ballot name of the candidate.
 * @property string                      $position_code  Foreign key linking to the associated position's code.
 *
 * @property-read \App\Models\Position  $position       The position this candidate is running for.
 *
 * @method string getKey()                              Get the primary key value for the model.
 */
class Candidate extends Model
{
    use HasFactory;
    use WithData;

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code', 'name', 'alias', 'position_code',
    ];

    protected string $dataClass = CandidateData::class;

    public static function newFactory(): CandidateFactory
    {
        return CandidateFactory::new();
    }

    public function position(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_code', 'code');
    }

    public static function fromData(CandidateData $data): static
    {
        // Recursively ensure the related Position exists
        $position = Position::fromData($data->position);

        // Prepare the candidate array
        $attributes = $data->toArray();

        // Inject foreign key
        $attributes['position_code'] = $position->getKey();

        // Remove nested position array if it exists
        unset($attributes['position']);

        // Create or update the candidate
        return static::updateOrCreate(
            ['code' => $data->code],
            $attributes
        );
    }

    public function setPositionAttribute(Position|string $position): static
    {
        if (is_string($position)) {
            $this->position_code = $position;
        } elseif ($position instanceof Position) {
            $this->position_code = $position->getKey();
        }

        return $this;
    }

    public function getPositionAttribute(): ?array
    {
        $position = $this->position()->getResults();

        return $position ? $position->toArray() : null;
    }
}
