<?php

namespace App\Support;

use App\Models\EventPeriod;

final class PublicPeriod
{
    /**
     * Only the active year is publicly visible.
     * Drafts and archives remain internal until an explicit period activation.
     */
    public static function current(): ?EventPeriod
    {
        return EventPeriod::where('status', 'active')->first();
    }
}
