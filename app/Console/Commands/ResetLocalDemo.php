<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\LocalDemoResetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ResetLocalDemo extends Command
{
    protected $signature = 'rt03:reset-demo
        {--execute : Really reset local test data after backup}
        {--confirm= : Must equal RESET-RT03-LOCAL}
        {--wipe-other-accounts : Also remove non-dummy, non-admin accounts}';

    protected $description = 'Back up and reset LOCAL SQLite RT03 testing history; preserve real Super Admin credentials.';

    public function handle(LocalDemoResetService $reset): int
    {
        // Never allow this command on a testing/in-memory or production DB.
        if (!app()->environment('local') || config('database.default') !== 'sqlite'
            || filled(config('database.connections.sqlite.url'))) {
            $this->error('Ditolak: hanya APP_ENV=local dengan SQLite tanpa DB_URL.');
            return self::FAILURE;
        }

        $base = realpath(database_path());
        $configured = (string) config('database.connections.sqlite.database');
        $db = realpath($configured) ?: realpath(database_path($configured));

        if (!$base || !$db || $configured === ':memory:'
            || !str_starts_with(strtolower($db), strtolower($base.DIRECTORY_SEPARATOR))
            || !is_file($db) || !is_readable($db)) {
            $this->error('Ditolak: SQLite harus berupa file dalam folder database/ proyek.');
            return self::FAILURE;
        }

        foreach ([...LocalDemoResetService::BUSINESS_TABLES,
            ...LocalDemoResetService::SESSION_TABLES, 'users'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->error('Ditolak: tabel '.$table.' tidak tersedia. Jangan reset DB yang belum termigrasi.');
                return self::FAILURE;
            }
        }

        $all = User::query()->get(['id', 'name', 'email', 'is_active', 'is_super_admin']);
        $isDummy = static fn (User $user): bool =>
            str_starts_with($user->name, '[DUMMY RT03]')
            || str_starts_with($user->email, 'dummy.');

        $keepers = $all->filter(fn (User $user) =>
            $user->is_active && $user->is_super_admin && !$isDummy($user));
        if ($keepers->isEmpty()) {
            $this->error('Ditolak: tidak ada akun Super Admin ASLI aktif untuk dipertahankan.');
            return self::FAILURE;
        }

        $others = $all->reject(fn (User $user) => $keepers->contains('id', $user->id));
        $otherReal = $others->reject($isDummy);
        if ($otherReal->isNotEmpty() && !$this->option('wipe-other-accounts')) {
            $this->error('Ditemukan '.$otherReal->count().' akun bukan dummy/non-admin.');
            $this->warn('Reset ditolak supaya akun asli tidak ikut terhapus. Periksa akun dahulu.');
            $this->line('Jika SEMUA akun itu memang data testing, ulangi dengan --wipe-other-accounts.');
            return self::FAILURE;
        }

        $this->info('Akun Super Admin asli yang DIPERTAHANKAN (ID/password tetap):');
        $this->table(['ID', 'Nama'], $keepers->map(fn (User $u) => [$u->login_id, $u->name])->all());
        $this->warn('Akan menghapus: '.$others->count().' akun non-admin/dummy, SEMUA transaksi, tugas, peserta, histori, dokumen DB, periode, dan sesi.');
        $this->line('Setelah reset: periode 2027, 10 divisi, 6 panduan; tanpa akun dummy dan tanpa aktivitas lama.');

        if (!$this->option('execute')) {
            $this->comment('DRY RUN: belum ada perubahan. Eksekusi memerlukan --execute --confirm=RESET-RT03-LOCAL.');
            return self::SUCCESS;
        }
        if ($this->option('confirm') !== 'RESET-RT03-LOCAL') {
            $this->error('Ditolak: tulis --confirm=RESET-RT03-LOCAL untuk menyetujui penghapusan.');
            return self::FAILURE;
        }

        $backup = storage_path('rt03-demo-backups/'.now()->format('Ymd-His').'-'.Str::random(8));
        if (!File::makeDirectory($backup, 0700, true, true)) {
            $this->error('Gagal membuat lokasi backup. Tidak ada data yang dihapus.');
            return self::FAILURE;
        }

        try {
            // VACUUM INTO makes a consistent SQLite snapshot, including WAL
            // state. A naive file copy may miss pending WAL transactions.
            $target = $backup.DIRECTORY_SEPARATOR.'before-reset.sqlite';
            $quoted = DB::connection('sqlite')->getPdo()->quote($target);
            DB::connection('sqlite')->unprepared('VACUUM INTO '.$quoted);

            $diskRoot = Storage::disk('local')->path('');
            if (is_dir($diskRoot)
                && !File::copyDirectory($diskRoot, $backup.DIRECTORY_SEPARATOR.'local-files')) {
                throw new \RuntimeException('Gagal membackup storage privat.');
            }

            if (config('session.driver') === 'file') {
                $sessions = storage_path('framework/sessions');
                if (is_dir($sessions)
                    && !File::copyDirectory($sessions, $backup.DIRECTORY_SEPARATOR.'sessions')) {
                    throw new \RuntimeException('Gagal membackup file session.');
                }
            }
            $this->info('Backup lengkap: '.$backup);

            // Every DB mutation is transaction-scoped. No migrate:fresh.
            $reset->reset($keepers->pluck('id')->all());
        } catch (\Throwable $error) {
            $this->error('Reset dibatalkan/gagal: '.$error->getMessage());
            $this->warn('Periksa backup: '.$backup.'; jika DB sudah berubah, pulihkan dari before-reset.sqlite.');
            return self::FAILURE;
        }

        // Remove ONLY app-owned uploaded files, not all of storage/.
        // Any failed unlink is reported; the full backup is retained.
        $disk = Storage::disk('local');
        $cleanupOk = true;
        foreach (['private/gallery', 'private/documents', 'private/finance', 'avatars'] as $directory) {
            try {
                if ($disk->directoryExists($directory) && !$disk->deleteDirectory($directory)) {
                    $cleanupOk = false;
                    $this->warn('Berkas belum bersih: '.$directory);
                }
            } catch (\Throwable $error) {
                report($error);
                $cleanupOk = false;
                $this->warn('Gagal membersihkan berkas: '.$directory);
            }
        }

        if (config('session.driver') === 'file') {
            try {
                File::cleanDirectory(storage_path('framework/sessions'));
            } catch (\Throwable $error) {
                report($error);
                $cleanupOk = false;
                $this->warn('File session lama perlu dibersihkan manual.');
            }
        }

        $this->info('Database pengujian telah direset. Akun Super Admin asli tetap dapat login.');
        $this->line('Backup arsip: '.$backup);
        if (!$cleanupOk) {
            $this->warn('SEBAGIAN BERKAS belum terhapus; jangan anggap reset file lengkap.');
            return self::FAILURE;
        }
        $this->info('Histori testing pada database dan file unggahan aplikasi berhasil dibersihkan.');
        return self::SUCCESS;
    }
}
