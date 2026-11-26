<?php

namespace App\Enums;

/** Maintenance request lifecycle; a finished request can be reopened. */
enum MaintenanceStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::Resolved, self::Cancelled],
            self::InProgress => [self::Open, self::Resolved, self::Cancelled],
            self::Resolved, self::Cancelled => [self::Open],
        };
    }
}
