# Audit akhir dan reset aman PANITIA HUB RT 03

## Audit alur dan batas pengujian

Pengujian Laravel + JavaScript di GitHub Actions menggunakan SQLite :memory: sehingga tidak menyentuh database laptop ataupun server. Skenario tambahan mencakup sembilan role, seluruh modul yang dapat dibuka Super Admin, penolakan hak keuangan/periode, pencabutan keanggotaan PIC tugas, rollback upload dokumen/galeri/bukti keuangan ketika DB gagal, serta reset data testing tanpa kehilangan akun Super Admin asli. Browser/HP, performa dengan data sangat besar, dan server yang sedang live masih memerlukan QA terpisah.

## Reset lokal menjadi data awal bersih

**PERINGATAN:** Ini menghapus seluruh data kepanitiaan pada SQLite lokal, termasuk transaksi keuangan, peserta, hasil lomba, tugas, pengajuan, file upload serta log aktivitas. Hanya jalankan jika semua data itu memang testing. Tidak ada fitur hapus massal melalui web dan tidak berjalan otomatis saat deploy.

Langkah:

1. Hentikan `php artisan serve`, queue worker dan proses lain yang memakai SQLite. Cadangkan folder project sebelum reset.
2. Pastikan `.env` benar: `APP_ENV=local`, `DB_CONNECTION=sqlite`, `DB_DATABASE` menuju **database.sqlite di folder database/ proyek**, bukan server atau DB luar.
3. Pastikan ada minimal satu akun Super Admin ASLI (bukan `dummy.*@rt03.test`). Akun Super Admin asli tetap mempertahankan ID RT03 dan hash password; foto profilnya akan dihapus sebagai bagian dari pembersihan.
4. Periksa rencana tanpa mengubah data:

   `php artisan rt03:reset-demo`

5. Jika command menolak karena ada akun bukan dummy/non-admin, audit nama akunnya dahulu. Opsi `--wipe-other-accounts` akan menghapus semua akun selain Super Admin asli. Gunakan hanya bila semuanya data pengujian.
6. Jika sudah yakin menjalankan RESET PENUH, gunakan:

   `php artisan rt03:reset-demo --execute --confirm=RESET-RT03-LOCAL`

   Atau, hanya jika akun non-admin selain dummy juga memang ingin dihapus:

   `php artisan rt03:reset-demo --execute --confirm=RESET-RT03-LOCAL --wipe-other-accounts`

## Hasil reset dan backup

- Sebelum menghapus, command membuat snapshot SQLite konsisten (`VACUUM INTO`) dan salinan seluruh file private di `storage/rt03-demo-backups/`. Backup termasuk data lama dan harus tetap diamankan; tidak lagi terlihat dari aplikasi.
- Data kepanitiaan dan histori di tabel aplikasi dikosongkan dalam transaksi DB; sesi, antrean, cache database, token reset juga dihapus.
- Di storage aktif, hanya folder milik aplikasi `private/gallery`, `private/documents`, `private/finance`, dan `avatars` yang dihapus; file private lama tetap ada dalam backup.
- Database diisi ulang dengan periode 2027, 10 divisi, dan 6 SOP bawaan.
- Akun dummy seeder, transaksi dummy, hasil uji, dan activity log lama hilang dari database aktif. Akun Super Admin asli tetap ada dan bisa login dengan password yang sama.
- Berkas log server di `storage/logs` dan arsip backup sengaja dipertahankan untuk pemulihan / audit; backup bukan bagian aplikasi aktif. Jika membutuhkan penghapusan permanen termasuk backup, lakukan setelah memastikan tidak ada data asli yang perlu dipulihkan dan memenuhi kebijakan retensi.
- Jika pembersihan file gagal, command akan melaporkan peringatan dan exit failure. Jangan menganggap semua file sudah bersih bila ada peringatan.

## Pemulihan

Jika reset salah atau proses gagal, **jangan** menggunakan `migrate:fresh`/`db:wipe` secara spontan. Hentikan server, salin ulang `before-reset.sqlite` ke database SQLite proyek dan pulihkan file dari `local-files/` backup yang sesuai. Jangan menghapus backup sampai aplikasi dan akun login sudah diverifikasi.

## Batas keamanan

Command hanya jalan pada `APP_ENV=local`, koneksi SQLite file di bawah `database/`, tanpa DB_URL, dengan kode konfirmasi eksplisit, backup berhasil, dan Super Admin bukan dummy. Pada production/testing command ditolak. GitHub tidak mempunyai akses otomatis ke database lokal; PR/merge saja tidak membersihkan data laptop.
