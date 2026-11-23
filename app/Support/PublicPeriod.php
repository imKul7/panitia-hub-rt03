<?php

namespace App\Support;

use App\Models\EventPeriod;

final class PublicPeriod
{
    /**
     * Show the live year, or a draft if a future year has not started yet.
     * Archived periods are never re-exposed by public fallback navigation.
     */
    public static function current(): ?EventPeriod
    {
        return EventPeriod::where('status', 'active')->first()
            ?? EventPeriod::where('status', 'draft')->orderByDesc('year')->first();
    }
}
