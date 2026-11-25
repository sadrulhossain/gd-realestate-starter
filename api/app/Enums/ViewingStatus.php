<?php

namespace App\Enums;

/** Viewing lifecycle. Scheduled and confirmed viewings block the agent calendar. */
enum ViewingStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [self::Scheduled->value, self::Confirmed->value];
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Scheduled => [self::Confirmed, self::Cancelled, self::NoShow],
            self::Confirmed => [self::Completed, self::Cancelled, self::NoShow],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    public function isActive(): bool
    {
        return in_array($this->value, self::activeValues(), true);
    }
}
