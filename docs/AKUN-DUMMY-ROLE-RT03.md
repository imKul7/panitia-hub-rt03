# Akun dummy Panitia Hub RT 03 — untuk testing lokal

Seeder tidak dijalankan otomatis oleh DatabaseSeeder. Hanya untuk lingkungan local/testing. Jangan jalankan pada server publik.

## Cara menyiapkan

1. Backup database penting dan pastikan .env memiliki APP_ENV=local serta koneksi DB pengujian.
2. Jalankan `php artisan migrate` dan `php artisan db:seed` bila tabel/periode belum disiapkan.
3. Jalankan `php artisan db:seed --class=DemoRoleAccountsSeeder`.
4. Salin ID login aktual yang tercetak di terminal.

| Akun | Email dummy untuk dikenali | Hak akses |
| --- | --- | --- |
| Super Admin | dummy.superadmin@rt03.test | Super Admin |
| Ketua Panitia | dummy.ketua@rt03.test | Ketua |
| Wakil Ketua | dummy.wakil@rt03.test | Wakil |
| Sekretaris | dummy.sekretaris@rt03.test | Sekretaris |
| Bendahara | dummy.bendahara@rt03.test | Bendahara |
| Koordinator Lomba | dummy.koordinator@rt03.test | Koordinator Divisi Lomba |
| Anggota Lomba | dummy.anggota@rt03.test | Anggota Divisi Lomba |
| Mentor | dummy.mentor@rt03.test | Mentor |
| Pengurus RT | dummy.pengurus_rt@rt03.test | Pengurus RT |

Password semua akun: `remajart03`.

**Login pakai ID RT03-XX, bukan email.** ID diperoleh dari primary key users.id saat seeder berjalan. ID bisa mulai setelah akun yang sudah ada; tidak mengubah ID lama.

## Ketentuan aman

- Jika dijalankan ulang, akun dummy tidak digandakan. Password dummy akan kembali ke `remajart03`.
- Seeder menolak mengambil alih alamat email yang sudah milik akun asli.
- Semua penulisan dalam satu transaksi database, sehingga kegagalan tidak membuat setengah akun terbuat.
- Akun normal, role normal, keuangan, dokumen, dan tugas asli tidak disentuh.
- Pada APP_ENV=production seeder akan menolak berjalan.
- Sebelum penggunaan nyata, hapus data pengujian dengan backup serta pemeriksaan relasi dan log; jangan sembarang hapus user atau jalankan migrate:fresh pada DB berisi data asli.
- Jangan menggunakan password bersama ini untuk akun sungguhan.
