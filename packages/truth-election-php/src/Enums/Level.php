<?php

namespace TruthElection\Enums;

enum Level: string
{
    case NATIONAL = 'national';
    case DISTRICT = 'district';
    case LOCAL = 'local';

    /**
     * Get all enum options as [label => value].
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [
                $case->label() => $case->value
            ])
            ->all();
    }

    /**
     * Get a human-friendly label for each enum case.
     */
    public function label(): string
    {
        return match($this) {
            self::NATIONAL => 'National',
            self::DISTRICT => 'District',
            self::LOCAL    => 'Local',
        };
    }

    /**
     * Pick a random Level case.
     */
    public static function random(): self
    {
        return collect(self::cases())->random();
    }
}
