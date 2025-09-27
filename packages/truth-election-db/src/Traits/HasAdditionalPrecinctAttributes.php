<?php

namespace TruthElectionDb\Traits;

trait HasAdditionalPrecinctAttributes
{
    const WATCHERS_COUNT_FIELD            = 'watchers_count';
    const PRECINCTS_COUNT_FIELD           = 'precincts_count';
    const REGISTERED_VOTERS_COUNT_FIELD   = 'registered_voters_count';
    const ACTUAL_VOTERS_COUNT_FIELD       = 'actual_voters_count';
    const BALLOTS_IN_BOX_COUNT_FIELD      = 'ballots_in_box_count';
    const UNUSED_BALLOTS_COUNT_FIELD      = 'unused_ballots_count';
    const SPOILED_BALLOTS_COUNT_FIELD     = 'spoiled_ballots_count';
    const VOID_BALLOTS_COUNT_FIELD        = 'void_ballots_count';
    const CLOSED_AT                       = 'closed_at';

    public function initializeHasAdditionalPrecinctAttributes(): void
    {
        $this->mergeFillable([
            self::WATCHERS_COUNT_FIELD,
            self::PRECINCTS_COUNT_FIELD,
            self::REGISTERED_VOTERS_COUNT_FIELD,
            self::ACTUAL_VOTERS_COUNT_FIELD,
            self::BALLOTS_IN_BOX_COUNT_FIELD,
            self::UNUSED_BALLOTS_COUNT_FIELD,
            self::SPOILED_BALLOTS_COUNT_FIELD,
            self::VOID_BALLOTS_COUNT_FIELD,
            self::CLOSED_AT,
        ]);

        $this->mergeAppends([
            self::WATCHERS_COUNT_FIELD,
            self::PRECINCTS_COUNT_FIELD,
            self::REGISTERED_VOTERS_COUNT_FIELD,
            self::ACTUAL_VOTERS_COUNT_FIELD,
            self::BALLOTS_IN_BOX_COUNT_FIELD,
            self::UNUSED_BALLOTS_COUNT_FIELD,
            self::SPOILED_BALLOTS_COUNT_FIELD,
            self::VOID_BALLOTS_COUNT_FIELD,
            self::CLOSED_AT,
        ]);
    }

    // Setters and Getters for each field

    // Watchers
    public function setWatchersCountAttribute(?int $value): self
    {
        $this->getAttribute('meta')->set(self::WATCHERS_COUNT_FIELD, $value);

        return $this;
    }

    public function getWatchersCountAttribute(): ?int
    {
        return $this->getAttribute('meta')->get(self::WATCHERS_COUNT_FIELD) ?? null;
    }

    // Precincts
    public function setPrecinctsCountAttribute(?int $value): self
    {
        $this->getAttribute('meta')->set(self::PRECINCTS_COUNT_FIELD, $value);

        return $this;
    }

    public function getPrecinctsCountAttribute(): ?int
    {
        return $this->getAttribute('meta')->get(self::PRECINCTS_COUNT_FIELD) ?? null;
    }

    // Registered voters
    public function setRegisteredVotersCountAttribute(?int $value): self
    {
        $this->getAttribute('meta')->set(self::REGISTERED_VOTERS_COUNT_FIELD, $value);

        return $this;
    }

    public function getRegisteredVotersCountAttribute(): ?int
    {
        return $this->getAttribute('meta')->get(self::REGISTERED_VOTERS_COUNT_FIELD) ?? null;
    }

    // Actual voters
    public function setActualVotersCountAttribute(?int $value): self
    {
        $this->getAttribute('meta')->set(self::ACTUAL_VOTERS_COUNT_FIELD, $value);

        return $this;
    }

    public function getActualVotersCountAttribute(): ?int
    {
        return $this->getAttribute('meta')->get(self::ACTUAL_VOTERS_COUNT_FIELD) ?? null;
    }

    // Ballots in Box
    public function setBallotsInBoxCountAttribute(?int $value): self
    {
        $this->getAttribute('meta')->set(self::BALLOTS_IN_BOX_COUNT_FIELD, $value);

        return $this;
    }

    public function getBallotsInBoxCountAttribute(): ?int
    {
        return $this->getAttribute('meta')->get(self::BALLOTS_IN_BOX_COUNT_FIELD) ?? null;
    }

    // Unused Ballots
    public function setUnusedBallotsCountAttribute(?int $value): self
    {
        $this->getAttribute('meta')->set(self::UNUSED_BALLOTS_COUNT_FIELD, $value);

        return $this;
    }

    public function getUnusedBallotsCountAttribute(): ?int
    {
        return $this->getAttribute('meta')->get(self::UNUSED_BALLOTS_COUNT_FIELD) ?? null;
    }

    // Spoiled Ballots
    public function setSpoiledBallotsCountAttribute(?int $value): self
    {
        $this->getAttribute('meta')->set(self::SPOILED_BALLOTS_COUNT_FIELD, $value);

        return $this;
    }

    public function getSpoiledBallotsCountAttribute(): ?int
    {
        return $this->getAttribute('meta')->get(self::SPOILED_BALLOTS_COUNT_FIELD) ?? null;
    }

    // Void Ballots
    public function setVoidBallotsCountAttribute(?int $value): self
    {
        $this->getAttribute('meta')->set(self::VOID_BALLOTS_COUNT_FIELD, $value);
        return $this;
    }

    public function getVoidBallotsCountAttribute(): ?int
    {
        return $this->getAttribute('meta')->get(self::VOID_BALLOTS_COUNT_FIELD) ?? null;
    }

    // Void Ballots
    public function setClosedAtAttribute(?string $value): self
    {
        $this->getAttribute('meta')->set(self::CLOSED_AT, $value);
        return $this;
    }

    public function getClosedAtAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::CLOSED_AT) ?? null;
    }
}
