<?php

namespace App\Enums;

/** Property lifecycle. Status changes only through PropertyStatusService, which enforces this table. */
enum PropertyStatus: string
{
    case Draft = 'draft';
    case Available = 'available';
    case UnderOffer = 'under_offer';
    case Sold = 'sold';
    case Rented = 'rented';
    case Archived = 'archived';

    /**
     * Statuses a visitor can see.
     *
     * @return list<string>
     */
    public static function publicValues(): array
    {
        return [self::Available->value, self::UnderOffer->value];
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Draft => [self::Available, self::Archived],
            self::Available => [self::Draft, self::UnderOffer, self::Sold, self::Rented, self::Archived],
            self::UnderOffer => [self::Available, self::Sold, self::Rented, self::Archived],
            self::Sold, self::Rented => [self::Available, self::Archived],
            self::Archived => [self::Draft],
        };
    }

    /** `sold` is for sale listings, `rented` for rent listings. */
    public function canMoveTo(self $to, string $listingType): bool
    {
        if (($to === self::Sold && $listingType !== 'sale') || ($to === self::Rented && $listingType !== 'rent')) {
            return false;
        }

        return in_array($to, $this->next(), true);
    }
}
