<?php

namespace TruthElection\Enums;

enum ElectoralInspectorRole: string
{
    case CHAIRPERSON = 'chairperson';
    case MEMBER = 'member';

    /**
     * Returns an array of values => labels (for UI options).
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $role) => [$role->value => $role->label()])
            ->all();
    }

    /**
     * Returns a random enum instance.
     */
    public static function random(): self
    {
        return collect(self::cases())->random();
    }

    /**
     * Human-friendly label for each role.
     */
    public function label(): string
    {
        return match ($this) {
            self::CHAIRPERSON => 'Chairperson',
            self::MEMBER => 'Member',
        };
    }
}
