<?php

namespace App\Support;

use App\Models\EventPeriod;
use App\Models\User;

/**
 * Workspace navigation is a presentation layer, never an authorization
 * policy. Access::can and controller checks stay authoritative.
 *
 * Menus are selected per ACTIVE membership and division in the current
 * event period. Primary work is shown first; other permitted read-only
 * references live in a collapsible section. Multiple memberships combine
 * their work menus instead of hiding one of the user's responsibilities.
 */
final class RoleMenu
{
    private const ITEMS = [
        'dashboard' => ['Dashboard', 'layout-dashboard', 'Ringkasan kegiatan'],
        'people.index' => ['Struktur Panitia', 'users', 'Tim & jabatan'],
        'tasks.index' => ['Tugas & Progres', 'check-square', 'Kerjakan & pantau tugas'],
        'notes.index' => ['Catatan Divisi', 'notebook', 'Catatan kerja'],
        'competitions.index' => ['Manajemen Lomba', 'trophy', 'Atur perlombaan'],
        'registrations.index' => ['Peserta Lomba', 'contact', 'Daftar peserta'],
        'results.index' => ['Hasil Lomba', 'award', 'Juara & hasil'],
        'prizes.index' => ['Hadiah Lomba', 'gift', 'Stok & penyerahan'],
        'inventory.index' => ['Inventaris', 'package', 'Peralatan'],
        'schedules.index' => ['Jadwal & Acara', 'calendar', 'Agenda & rundown'],
        'announcements.index' => ['Pengumuman', 'megaphone', 'Informasi warga'],
        'gallery.index' => ['Galeri Foto', 'images', 'Dokumentasi'],
        'documents.index' => ['Sekretariat', 'folder', 'Surat & dokumen'],
        'guides.index' => ['Panduan & SOP', 'book', 'Panduan kerja'],
        'mentor.index' => ['Ruang Mentor', 'message-square', 'Masukan senior'],
        'archive.index' => ['Arsip Tahunan', 'archive', 'Riwayat kegiatan'],
        'expenses.index' => ['Pengajuan Dana', 'receipt', 'Permintaan anggaran'],
        'finance.index' => ['Keuangan', 'wallet', 'Transaksi & saldo'],
        'periods.index' => ['Kelola Periode', 'calendar-cog', 'Periode tahunan'],
        'profile.index' => ['Profil Saya', 'user-circle', 'Akun & keamanan'],
    ];

    /** @return array{label:string,description:string,primary:array,more:array} */
    public static function forUser(User $user, EventPeriod $period): array
    {
        if ($user->is_super_admin) {
            $roles = ['super_admin'];
            $divisions = [];
            $label = 'Super Admin';
            $description = 'Kelola akun, periode & sistem.';
        } else {
            $memberships = $user->memberships()->with('division')
                ->where('event_period_id', $period->id)
                ->where('is_active', true)
                ->get();
            $roles = $memberships->pluck('role')->unique()->all();
            $divisions = $memberships
                ->filter(fn ($membership) => in_array($membership->role, ['koordinator', 'anggota'], true))
                ->pluck('division.slug')->filter()->unique()->all();

            // Access::can treats Mentor / Pengurus RT as read-only whenever
            // these roles occur, even with concurrent executive assignments.
            $priority = ['mentor', 'pengurus_rt', 'ketua', 'wakil', 'sekretaris', 'bendahara', 'koordinator', 'anggota'];
            $mainRole = collect($priority)->first(fn ($role) => in_array($role, $roles, true)) ?? 'anggota';
            [$label, $description] = match ($mainRole) {
                'mentor' => ['Mentor', 'Pantau progres & beri arahan.'],
                'pengurus_rt' => ['Pengurus RT', 'Pantau informasi kegiatan.'],
                'ketua' => ['Ketua Panitia', 'Pimpin tim & keputusan.'],
                'wakil' => ['Wakil Ketua', 'Bantu koordinasi & tindak lanjut.'],
                'sekretaris' => ['Sekretaris', 'Kelola surat, agenda & catatan.'],
                'bendahara' => ['Bendahara', 'Kelola anggaran & transaksi.'],
                'koordinator' => ['Koordinator', 'Atur tugas & kebutuhan divisi.'],
                default => ['Anggota', 'Jalankan tugas divisi.'],
            };
            if (in_array($mainRole, ['koordinator', 'anggota'], true)) {
                $divisionNames = $memberships
                    ->filter(fn ($membership) => $membership->role === $mainRole)
                    ->pluck('division.name')->filter()->unique()->values();
                if ($divisionNames->isNotEmpty()) {
                    $label .= ' · '.$divisionNames->take(2)->implode(' / ');
                }
            }
        }

        $priorityKeys = ['dashboard'];
        if ($user->is_super_admin) {
            $priorityKeys = array_merge($priorityKeys, ['people.index','tasks.index','finance.index','periods.index','expenses.index']);
        } elseif (in_array('mentor', $roles, true)) {
            $priorityKeys = array_merge($priorityKeys, ['mentor.index','people.index','tasks.index','guides.index']);
        } elseif (in_array('pengurus_rt', $roles, true)) {
            $priorityKeys = array_merge($priorityKeys, ['people.index','schedules.index','announcements.index','gallery.index']);
        } else {
            if (array_intersect($roles, ['ketua','wakil'])) {
                $priorityKeys = array_merge($priorityKeys, [
                    'people.index','tasks.index','notes.index','competitions.index',
                    'registrations.index','results.index','schedules.index',
                    'expenses.index','finance.index','announcements.index',
                ]);
            }
            if (in_array('sekretaris', $roles, true)) {
                $priorityKeys = array_merge($priorityKeys, [
                    'documents.index','notes.index','schedules.index',
                    'announcements.index','guides.index','tasks.index','expenses.index',
                ]);
            }
            if (in_array('bendahara', $roles, true)) {
                $priorityKeys = array_merge($priorityKeys, ['finance.index','expenses.index','tasks.index']);
            }
            if (array_intersect($roles, ['koordinator','anggota'])) {
                $priorityKeys = array_merge($priorityKeys, ['tasks.index','notes.index','expenses.index']);
                $byDivision = [
                    'lomba' => ['competitions.index','registrations.index','results.index'],
                    'hadiah' => ['prizes.index'],
                    'acara' => ['schedules.index'],
                    'perlengkapan' => ['inventory.index'],
                    'publikasi' => ['announcements.index','gallery.index'],
                    'sekretariat' => ['documents.index','schedules.index'],
                    'keuangan' => ['finance.index','expenses.index'],
                ];
                foreach ($divisions as $division) {
                    $priorityKeys = array_merge($priorityKeys, $byDivision[$division] ?? []);
                }
            }
        }

        // Reference/archive/profile stay below work actions, not above them.
        $priorityKeys = array_unique(array_merge($priorityKeys, [
            'archive.index', 'profile.index',
        ]));
        $primary = [];
        $more = [];
        foreach (self::ITEMS as $route => [$text, $icon, $hint]) {
            if (!self::visible($user, $route)) continue;
            $entry = ['route' => $route, 'label' => $text, 'icon' => $icon, 'hint' => $hint];
            if (in_array($route, $priorityKeys, true)) $primary[$route] = $entry;
            else $more[$route] = $entry;
        }

        // Keep the work section prioritized (not alphabetized), with the
        // dashboard first and the profile/archive at the bottom.
        $ordered = [];
        foreach ($priorityKeys as $route) {
            if (isset($primary[$route])) $ordered[$route] = $primary[$route];
        }
        return [
            'label' => $label,
            'description' => $description,
            'primary' => array_values($ordered),
            'more' => array_values($more),
        ];
    }

    private static function visible(User $user, string $route): bool
    {
        return match ($route) {
            'finance.index' => Access::can($user, 'finance-view'),
            'expenses.index' => Access::can($user, 'expenses-request'),
            'periods.index' => Access::can($user, 'manage-periods'),
            'mentor.index' => $user->is_super_admin
                || Access::can($user, 'mentor-feedback')
                || Access::can($user, 'view'),
            default => Access::can($user, 'view'),
        };
    }
}
