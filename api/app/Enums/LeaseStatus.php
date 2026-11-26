<?php

namespace App\Enums;

/** Lease lifecycle. Status changes only through LeaseService, which enforces this table. */
enum LeaseStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Ended = 'ended';
    case Terminated = 'terminated';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Draft => [self::Active, self::Cancelled],
            self::Active => [self::Ended, self::Terminated],
            self::Ended, self::Terminated, self::Cancelled => [],
        };
    }
}
