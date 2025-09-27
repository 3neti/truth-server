<?php

namespace TruthElectionDb\Models;

use TruthElectionDb\Traits\{HasAdditionalPrecinctAttributes, HasMeta};
use Spatie\LaravelData\DataCollection;
use TruthElection\Data\PositionData;
use TruthElectionDb\Database\Factories\PrecinctFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use TruthElectionDb\Services\VoteTallyService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use TruthElection\Data\PrecinctData;
use Spatie\LaravelData\WithData;

/**
 * Class Precinct
 *
 * Represents a precinct in the electoral system where voting takes place.
 * A precinct can have multiple ballots and is monitored by electoral inspectors.
 *
 * @property string $id                                  The UUID primary key.
 * @property string $code                                Unique code identifying the precinct.
 * @property string|null $location_name                  Optional human-readable name for the precinct location.
 * @property float|null $latitude                        Latitude coordinate of the precinct.
 * @property float|null $longitude                       Longitude coordinate of the precinct.
 * @property array $electoral_inspectors                 JSON-cast collection of electoral inspector data.
 * @property \Illuminate\Support\Carbon $created_at      Timestamp of when the record was created.
 * @property \Illuminate\Support\Carbon $updated_at      Timestamp of the last update.
 *
 * @property-read array $ballots                         The ballots submitted in this precinct.
 * @property-read array $tallies                         Computed tallies of votes per candidate per position.
 *
 * @property int $watchers_count
 * @property int $precincts_count
 * @property int $registered_voters_count
 * @property int $actual_voters_count
 * @property int $ballots_in_box_count
 * @property int $unused_ballots_count
 * @property int $spoiled_ballots_count
 * @property int $void_ballots_count
 */
class Precinct extends Model
{
    use HasAdditionalPrecinctAttributes;
    use HasFactory;
    use HasUuids;
    use WithData;
    use HasMeta;

    protected string $dataClass = PrecinctData::class;

    protected $fillable = [
        'code',
        'location_name',
        'latitude',
        'longitude',
        'electoral_inspectors',
        'watchers_count',
        'precincts_count',
        'registered_voters_count',
        'actual_voters_count',
        'ballots_in_box_count',
        'unused_ballots_count',
        'spoiled_ballots_count',
        'void_ballots_count',
        'closed_at',
    ];

    protected $casts = [
        'electoral_inspectors' => 'array',
    ];

    protected $appends = ['ballots', 'tallies'];

    public static function newFactory(): PrecinctFactory
    {
        return PrecinctFactory::new();
    }

    protected static function booted() {
        static::saved(fn() => Cache::forget('shared.precinct'));
    }

    public static function fromData(PrecinctData $data): static
    {
        // Extract only non-relational attributes
        $attributes = $data->toArray();
        unset($attributes['ballots']);

        $precinct = static::updateOrCreate(
            ['code' => $data->code],
            $attributes
        );

        // Sync ballots separately
        if (!empty($data->ballots) && $data->ballots instanceof DataCollection) {
            foreach ($data->ballots as $ballotData) {
                Ballot::fromData($ballotData->setPrecinctCode($precinct->code));
            }
        }

        return $precinct;
    }

    public function getBallotsAttribute(): array
    {
        return $this->hasMany(Ballot::class, 'precinct_code', 'code')
            ->getResults()
            ->toArray();
    }

    public function getTalliesAttribute(): array
    {
        return app(VoteTallyService::class)->fromPrecinct($this)
            ->toArray();
    }
}
