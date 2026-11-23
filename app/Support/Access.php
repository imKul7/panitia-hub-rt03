<?php

namespace App\Support;

use App\Models\User;

final class Access
{
    public static function can(?User $user, string $action, ?int $divisionId = null): bool
    {
        if (!$user || !$user->is_active) return false;
        if ($user->is_super_admin) return true;

        $period = Workspace::period();
        $memberships = $user->memberships()->with('division')
            ->where('event_period_id', $period->id)->where('is_active', true)->get();
        if ($memberships->isEmpty()) return false;
        $roles = $memberships->pluck('role')->all();
        $has = fn(array $allowed) => count(array_intersect($allowed, $roles)) > 0;
        $core = ['ketua','wakil'];
        $isDivision = fn(string $slug) => $memberships->contains(fn($m) => in_array($m->role, ['koordinator','anggota'], true) && $m->division?->slug === $slug);
        $ownDivision = $divisionId !== null && $memberships->contains(fn($m) => $m->division_id === $divisionId && in_array($m->role, ['koordinator','anggota','sekretaris'], true));

        // Mentor dan pengurus RT adalah peran baca-saja meskipun terjadi penugasan ganda.
        if ($has(['mentor'])) return in_array($action,['view','mentor-feedback'],true);
        if ($has(['pengurus_rt'])) return $action === 'view';

        return match ($action) {
            'view' => true,
            'manage-members' => $has($core),
            'task-create' => $has([...$core,'koordinator']),
            'task-assignee-update' => $has([...$core,'sekretaris','bendahara','koordinator','anggota']),
            'task-update' => $has($core) || ($divisionId !== null && $memberships->contains(fn($m) => $m->division_id === $divisionId && $m->role === 'koordinator')),
            'notes-create' => $has($core) || $ownDivision || ($has(['sekretaris']) && $divisionId !== null
                && \App\Models\Division::whereKey($divisionId)->where('event_period_id', $period->id)->where('slug', 'sekretariat')->exists()),
            'documents-manage' => $has([...$core,'sekretaris']),
            'finance-view' => $has([...$core,'bendahara']),
            'finance-manage' => $has(['bendahara']),
            'finance-approve' => $has($core),
            'expenses-request' => $has([...$core,'sekretaris','bendahara','koordinator','anggota']),
            'expenses-request-for' => $has([...$core,'bendahara']) || $ownDivision || ($has(['sekretaris']) && $divisionId !== null && \App\Models\Division::whereKey($divisionId)->where('slug','sekretariat')->exists()),
            'competition-manage', 'registration-manage' => $has($core) || $isDivision('lomba'),
            'schedule-manage' => $has([...$core,'sekretaris']) || $isDivision('acara'),
            'prize-manage' => $has($core) || $isDivision('hadiah'),
            'inventory-manage' => $has($core) || $isDivision('perlengkapan'),
            'gallery-manage' => $has($core) || $isDivision('publikasi'),
            'announcement-manage' => $has([...$core,'sekretaris']) || $isDivision('publikasi'),
            'guides-manage' => $has([...$core,'sekretaris']),
            'mentor-feedback' => $has(['mentor']),
            default => false,
        };
    }

    public static function ensure(string $action, ?int $divisionId = null, bool $write = false): void
    {
        abort_unless(self::can(auth()->user(), $action, $divisionId), 403, 'Akses ditolak untuk jabatan Anda.');
        if ($write) abort_if(Workspace::period()->status === 'archived', 403, 'Arsip tahun ini sudah ditutup.');
    }
}
