<?php

namespace TruthElectionDb\Models;

use TruthElection\Data\{BallotData, ElectionReturnData, ElectoralInspectorData};
use Illuminate\Database\Eloquent\Casts\Attribute;
use TruthElectionDb\Database\Factories\ElectionReturnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\WithData;
use DateTimeInterface;

/**
 * Class ElectionReturn
 *
 * Represents the official summary of election results for a given precinct.
 * This model aggregates and signs the tallies of votes cast via the associated ballots.
 *
 * @property string $id                                   The UUID primary key.
 * @property string $code                                 Unique code identifying this election return.
 * @property array $signatures                            Digital signatures from electoral inspectors.
 * @property string $precinct_code                        Foreign key reference to the associated precinct.
 * @property \Illuminate\Support\Carbon $created_at       Timestamp when the record was created.
 * @property \Illuminate\Support\Carbon $updated_at       Timestamp when the record was last updated.
 *
 * @property-read array $precinct                         Hydrated precinct data as array (custom accessor).
 * @property-read DataCollection<BallotData> $ballots     JSON-cast ballots data (from precinct).
 * @property-read array $tallies                          Vote tallies from precinct.
 */
class ElectionReturn extends Model
{
    use HasFactory;
    use HasUuids;
    use WithData;

    protected string $dataClass = ElectionReturnData::class;

    protected $fillable = [
        'id',
        'code',
        'signatures',
        'precinct_code',
    ];

    protected $appends = ['tallies'];

    protected $casts = [
        'signatures' => 'array',
        'ballots' => 'array',
    ];

    public static function newFactory(): ElectionReturnFactory
    {
        return ElectionReturnFactory::new();
    }

    public static function fromData(ElectionReturnData $data): self
    {
        $model = self::updateOrCreate(
            ['code' => $data->code], // use code, not id, to allow syncing from external
            [
                'signatures' => $data->signatures->toArray(),
                'precinct_code' => $data->precinct->code,
            ]
        );

        // 🔁 Sync ballots (create/update them)
        foreach ($data->ballots as $ballotData) {
            // Assign precinct code to each ballot (needed for fromData)
            $ballotData->setPrecinctCode($data->precinct->code);

            Ballot::fromData($ballotData);
        }

        return $model;
    }

//    public static function fromData(ElectionReturnData $data): self
//    {
//        $model = self::updateOrCreate(
//            ['id' => $data->id],
//            [
//                'code' => $data->code,
//                'signatures' => $data->signatures->toArray(),
//                'precinct_code' => $data->precinct->code,
//            ]
//        );
//
//        return $model;
//    }

//    protected function serializeDate(DateTimeInterface $date): string
//    {
//        return $date->format('Y-m-d\TH:i:sP');
//    }

    public function setPrecinctAttribute(Precinct|string $precinct): static
    {
        if (is_string($precinct)) {
            $this->precinct_code = $precinct;
        } elseif ($precinct instanceof Precinct) {
            $this->precinct_code = $precinct->code;
        }

        return $this;
    }

    protected function signatures(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => json_decode($value, true) ?? [],
        );
    }

    public function getPrecinctAttribute(): array
    {
        return $this->belongsTo(Precinct::class, 'precinct_code', 'code')
            ->getResults()
            ?->toArray() ?? [];
    }

    public function getTalliesAttribute(): array
    {
        return $this->precinct['tallies'] ?? [];
    }

    public function getBallotsAttribute(): array
    {
        return $this->precinct['ballots'] ?? [];
    }
}
