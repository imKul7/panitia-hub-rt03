<?php

namespace Database\Seeders;

use App\Models\{CommitteeMember, Division, EventPeriod, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Explicitly opt-in TEST accounts. Never run from DatabaseSeeder.
 *
 * Usage (local/testing only):
 *   php artisan db:seed --class=DemoRoleAccountsSeeder
 */
class DemoRoleAccountsSeeder extends Seeder
{
    private const PASSWORD = 'remajart03';

    private const ACCOUNTS = [
        // slug => [display name, committee role, division slug]
        'superadmin' => ['Super Admin', null, null],
        'ketua' => ['Ketua Panitia', 'ketua', null],
        'wakil' => ['Wakil Ketua', 'wakil', null],
        'sekretaris' => ['Sekretaris', 'sekretaris', null],
        'bendahara' => ['Bendahara', 'bendahara', null],
        'koordinator' => ['Koordinator Lomba', 'koordinator', 'lomba'],
        'anggota' => ['Anggota Lomba', 'anggota', 'lomba'],
        'mentor' => ['Mentor', 'mentor', null],
        'pengurus_rt' => ['Pengurus RT', 'pengurus_rt', null],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'Akun dummy hanya boleh dibuat pada APP_ENV=local/testing. Production ditolak.'
            );
        }

        $period = EventPeriod::where('status', 'active')->first();
        if (! $period) {
            throw new RuntimeException(
                'Belum ada periode aktif. Jalankan php artisan db:seed terlebih dahulu.'
            );
        }

        $lomba = Division::where('event_period_id', $period->id)
            ->where('slug', 'lomba')->first();
        if (! $lomba) {
            throw new RuntimeException(
                'Divisi lomba tidak ditemukan pada periode aktif. Periksa data divisi.'
            );
        }

        $rows = DB::transaction(function () use ($period, $lomba): array {
            $rows = [];
            foreach (self::ACCOUNTS as $slug => [$label, $role, $divisionSlug]) {
                $name = '[DUMMY RT03] '.$label;
                $email = 'dummy.'.$slug.'@rt03.test';

                $user = User::firstOrNew(['email' => $email]);
                // Never take over a pre-existing real account with this email.
                if ($user->exists && $user->name !== $name) {
                    throw new RuntimeException(
                        'Email '.$email.' sudah dipakai akun bukan dummy; tidak ada data diubah.'
                    );
                }

                // Re-running intentionally restores the SAME dummy password.
                // User casts the password as "hashed", never store plaintext in DB.
                $user->fill([
                    'name' => $name,
                    'email' => $email,
                    'password' => self::PASSWORD,
                    'is_active' => true,
                    'is_super_admin' => $role === null,
                ]);
                $user->save();

                if ($role !== null) {
                    CommitteeMember::updateOrCreate(
                        [
                            'event_period_id' => $period->id,
                            'user_id' => $user->id,
                            'role' => $role,
                            'division_id' => $divisionSlug === 'lomba' ? $lomba->id : null,
                        ],
                        [
                            'is_active' => true,
                            'position_title' => $label,
                        ],
                    );
                }

                $rows[] = [$label, $user->login_id, $email];
            }

            return $rows;
        });

        $this->command?->info('Akun dummy RT03 untuk periode '.$period->year.' berhasil disiapkan.');
        $this->command?->table(['Role', 'ID Login', 'Email dummy'], $rows);
        $this->command?->warn(
            'Password semua akun dummy: remajart03. HANYA untuk testing lokal; jangan pakai di server publik.'
        );
    }
}
