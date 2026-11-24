<?php

namespace App\Support;

/**
 * Human-readable, role-neutral guidance for validation feedback.
 * Actual permission and business rules always remain in controllers.
 */
final class FormFeedback
{
    private const LABELS = [
        'name' => 'Nama', 'full_name' => 'Nama lengkap', 'title' => 'Judul',
        'description' => 'Deskripsi', 'content' => 'Isi catatan',
        'email' => 'Alamat email', 'password' => 'Kata sandi',
        'current_password' => 'Kata sandi saat ini',
        'password_confirmation' => 'Konfirmasi kata sandi',
        'phone' => 'Nomor telepon', 'guardian_phone' => 'Telepon wali',
        'guardian_name' => 'Nama wali', 'role' => 'Jabatan',
        'user_id' => 'Panitia', 'assigned_to' => 'Penanggung jawab (PIC)',
        'division_id' => 'Divisi', 'competition_id' => 'Lomba',
        'participant_id' => 'Peserta', 'task_id' => 'Tugas',
        'year' => 'Tahun', 'status' => 'Status', 'category' => 'Kategori',
        'rank_position' => 'Peringkat', 'priority' => 'Prioritas',
        'quantity' => 'Jumlah', 'quantity_total' => 'Jumlah stok',
        'amount' => 'Nominal transaksi', 'amount_requested' => 'Nominal pengajuan',
        'transaction_date' => 'Tanggal transaksi', 'starts_at' => 'Waktu mulai',
        'due_date' => 'Tenggat', 'event_date' => 'Tanggal acara',
        'proof' => 'Bukti transaksi', 'image' => 'Foto', 'photo' => 'Foto profil',
        'file' => 'Berkas', 'document_type' => 'Jenis dokumen',
        'public_display_name' => 'Nama untuk publikasi',
        'review_note' => 'Catatan pemeriksaan', 'feedback_text' => 'Masukan mentor',
        'movement_type' => 'Jenis pergerakan', 'location' => 'Lokasi',
        'registration_code' => 'Kode pendaftaran', 'quota' => 'Kuota lomba',
        'consent_confirmed' => 'Persetujuan publikasi',
    ];

    public static function label(string $field): string
    {
        $base = preg_replace('/\.\d+(?=\.|$)/', '', $field);
        $base = substr($base, (strrpos($base, '.') ?: -1) + 1);
        return self::LABELS[$base] ?? ucfirst(str_replace('_', ' ', $base));
    }

    public static function suggestion(string $field, string $reason = ''): string
    {
        $reason = mb_strtolower($reason);
        $name = mb_strtolower($field);
        if (str_contains($reason, 'kuota') || str_contains($reason, 'penuh')) {
            return 'Pilih lomba lain yang tersedia atau hubungi koordinator lomba untuk verifikasi kuota.';
        }
        if (str_contains($reason, 'stok') || str_contains($reason, 'mencukupi') || str_contains($reason, 'melampaui')) {
            return 'Periksa jumlah yang masih tersedia, lalu sesuaikan angka yang diminta.';
        }
        if (str_contains($reason, 'sudah terdaftar') || str_contains($reason, 'sudah memiliki hasil')) {
            return 'Periksa data yang sudah tercatat sebelum membuat entri baru.';
        }
        if (str_contains($reason, 'jabatan') || str_contains($reason, 'pic ') || str_contains($reason, 'divisi tugas')) {
            return 'Pilih divisi dan panitia yang aktif, atau minta ketua panitia memperbarui struktur jabatan.';
        }
        if (str_contains($reason, 'publikasi') || str_contains($reason, 'persetujuan')) {
            return 'Pastikan informasi telah diverifikasi dan persetujuan publikasi sudah diberikan.';
        }
        if (str_contains($reason, 'unik') || str_contains($reason, 'unique') || str_contains($reason, 'digunakan')) {
            return 'Periksa apakah data yang sama sudah terdaftar, lalu gunakan nilai yang berbeda.';
        }
        if (str_contains($name, 'password') || str_contains($name, 'kata_sandi')) {
            return 'Periksa kata sandi, lalu pastikan konfirmasi sama dengan kata sandi baru.';
        }
        if (str_contains($name, 'email')) {
            return 'Gunakan format alamat email yang benar, misalnya panitia@email.com.';
        }
        if (in_array($name, ['division_id', 'assigned_to', 'user_id', 'role'], true)) {
            return 'Pilih opsi yang sesuai dengan jabatan dan divisi aktif pada periode ini.';
        }
        if (in_array($name, ['competition_id', 'participant_id', 'task_id'], true)) {
            return 'Pilih data yang terdaftar pada periode dan kegiatan yang sedang aktif.';
        }
        if (in_array($name, ['proof', 'image', 'photo', 'file'], true)) {
            return 'Periksa format dan ukuran berkas sesuai ketentuan pada kolom unggahan.';
        }
        if (in_array($name, ['amount', 'amount_requested', 'quantity', 'quantity_total', 'quota'], true)) {
            return 'Gunakan angka positif dan pastikan tidak melebihi batas yang diizinkan.';
        }
        if (str_contains($name, 'date') || str_contains($name, 'starts_at') || str_contains($name, 'due_date')) {
            return 'Pilih tanggal atau waktu dengan format yang benar dan sesuai jadwal kegiatan.';
        }
        return 'Periksa nilai di kolom tersebut, lengkapi bila wajib, kemudian coba simpan kembali.';
    }

    public static function rejectedSuggestion(int $status, string $reason): string
    {
        if ($status === 403) return 'Periksa izin jabatan dan periode aktif, atau hubungi ketua panitia/admin.';
        if ($status === 404) return 'Muat ulang daftar untuk memastikan data masih tersedia.';
        return self::suggestion('', $reason);
    }
}
