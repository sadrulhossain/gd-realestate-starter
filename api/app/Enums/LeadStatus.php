<?php

namespace App\Enums;

/** Lead pipeline. Status changes only through LeadStatusService, which enforces this table. */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Viewing = 'viewing';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::New => [self::Contacted, self::Lost],
            self::Contacted => [self::Qualified, self::Lost],
            self::Qualified => [self::Viewing, self::Negotiation, self::Lost],
            self::Viewing => [self::Qualified, self::Negotiation, self::Lost],
            self::Negotiation => [self::Viewing, self::Won, self::Lost],
            self::Won => [],
            self::Lost => [self::New],
        };
    }
}
