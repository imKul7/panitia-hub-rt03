<?php

namespace App\Support;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Clears PANITIA HUB business data only. The command calling this service
 * must first enforce local SQLite, take a backup and collect explicit consent.
 * Separated for isolated, in-memory regression tests.
 */
final class LocalDemoResetService
{
    // Children BEFORE parents: foreign-key checks stay enabled.
    public const BUSINESS_TABLES = [
        'prize_distributions',
        'inventory_movements',
        'task_updates',
        'competition_results',
        'competition_registrations',
        'financial_transactions',
        'expense_requests',
        'budgets',
        'mentor_feedback',
        'documents',
        'division_notes',
        'tasks',
        'prizes',
        'inventory_items',
        'participants',
        'competitions',
        'event_schedules',
        'announcements',
        'gallery_items',
        'sop_guides',
        'activity_logs',
        'committee_members',
        'divisions',
        'event_periods',
    ];

    public const SESSION_TABLES = [
        'sessions',
        'jobs',
        'failed_jobs',
        'job_batches',
        'cache',
        'cache_locks',
        'password_reset_tokens',
    ];

    public function reset(array $preserveAdminIds): void
    {
        if ($preserveAdminIds === []) {
            throw new RuntimeException('Reset ditolak: tidak ada Super Admin asli yang dipertahankan.');
        }

        $keepers = User::query()->whereIn('id', $preserveAdminIds)
            ->where('is_active', true)->where('is_super_admin', true)->get();

        if ($keepers->count() !== count(array_unique($preserveAdminIds))
            || $keepers->contains(fn (User $u) => str_starts_with($u->name, '[DUMMY RT03]')
                || str_starts_with($u->email, 'dummy.'))) {
            throw new RuntimeException('Reset ditolak: Super Admin yang akan dipertahankan tidak valid.');
        }

        DB::transaction(function () use ($preserveAdminIds): void {
            foreach (self::BUSINESS_TABLES as $table) {
                DB::table($table)->delete();
            }

            // Database sessions and queued jobs can still contain old users or
            // test payloads, so remove them alongside other demo history.
            foreach (self::SESSION_TABLES as $table) {
                DB::table($table)->delete();
            }

            User::whereNotIn('id', $preserveAdminIds)->delete();

            // Existing admin passwords/IDs remain valid; photos and remember
            // tokens from the old test workspace must not survive.
            User::whereIn('id', $preserveAdminIds)->update([
                'avatar_path' => null,
                'remember_token' => Str::random(60),
            ]);

            // Restore ONLY neutral system defaults: one 2027 period, 10
            // divisions and 6 global SOP guides. No dummy accounts.
            (new DatabaseSeeder())->run();
        });
    }
}
