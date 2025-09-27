<?php

namespace TruthElectionDb\Traits;

use TruthElectionDb\Models\Precinct;

trait HasPrecinct
{
    public function initializeHasPrecinct(): void
    {
        $this->mergeFillable([
            'precinct_id',
            'precinct',
        ]);

        $this->setAppends(
            array_merge($this->appends, ['precinct'])
        );
    }

    public function precinct(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Precinct::class);
    }

    public function setPrecinctAttribute(Precinct|string $precinct): static
    {
        if (is_string($precinct)) {
            $this->precinct_id = $precinct;
        } elseif ($precinct instanceof Precinct) {
            $this->precinct_id = $precinct->id;
        }

        return $this;
    }

    public function getPrecinctAttribute(): ?Precinct
    {
        return $this->getRelationValue('precinct');
    }
}
