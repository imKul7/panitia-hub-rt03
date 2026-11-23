<?php

namespace App\Support;

use App\Models\{EventPeriod, ActivityLog, User};
use Illuminate\Support\Facades\Auth;

final class Workspace
{
    public static function period(): EventPeriod
    {
        return EventPeriod::query()->where('status', 'active')->first()
            ?? EventPeriod::query()->where('status', 'draft')->orderByDesc('year')->firstOrFail();
    }

    public static function log(string $action, string $description, ?int $subjectId = null, ?string $subjectType = null): void
    {
        ActivityLog::create([
            'event_period_id' => self::period()->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'subject_id' => $subjectId,
            'subject_type' => $subjectType,
        ]);
    }
}
