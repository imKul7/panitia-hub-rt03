# PANITIA HUB RT 03 — Panduan rilis dan hosting

## Status dan batasan GitHub

Repository GitHub adalah **sumber kode dan pemeriksaan CI**, BUKAN server Laravel. GitHub Pages hanya mengirim HTML/CSS/JS statis; aplikasi ini membutuhkan PHP, sesi login, Laravel, database, dan penyimpanan file privat. Jangan aktifkan GitHub Pages sebagai pengganti backend atau mengklaim aplikasi live hanya karena merge ke `main`.

Release pertama melalui `main` adalah **kode kandidat rilis**, bukan otorisasi memasukkan data pribadi warga. Database pada komputer developer tidak otomatis pindah atau tersinkron dengan server.

## Sebelum dipakai warga (wajib)

- [ ] Pilih hosting yang menjalankan PHP 8.2+, extension Laravel, Composer, Node/Vite build (saat build saja), HTTPS, database persistennya (MySQL/MariaDB atau SQLite dengan persistent disk), dan direktori storage yang **persisten serta di luar public document root**.
- [ ] Jalankan uji penerimaan pengguna (UAT) di domain staging HTTPS pada HP Android, iPhone, laptop, semua role: login/logout, pergantian tahun, delegasi akses, tugas/progres, peserta, hasil, hadiah, keuangan dan bukti, arsip, unduhan file, galeri, reset password, serta kontrol privasi.
- [ ] Tentukan kebijakan izin foto, retensi data, akses keuangan, persetujuan pengeluaran, dan prosedur koreksi yang memelihara jejak audit.
- [ ] Simpan rahasia di environment server, **bukan GitHub/public/**. `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://DOMAIN_ASLI`, `APP_KEY` unik, kredensial DB dan email server. Jangan menggunakan akun dummy atau kata sandi uji.
- [ ] Konfigurasikan document root server ke folder `public/` saja; nonaktifkan directory listing; jalankan server lewat HTTPS. Jangan membuat direktori `storage/app/private` dapat diakses publik.
- [ ] Siapkan backup otomatis **database dan private uploads** di lokasi terpisah; uji pemulihan; pantau ruang penyimpanan dan error aplikasi.
- [ ] Uji restore, rate limiting, CSRF, file upload/download, akses berdasarkan role, error 403/404, pergantian password dan sesi. Tes CI saja tidak cukup untuk menyatakan aplikasi bebas bug atau aman dari semua ancaman.

## Alur rilis via GitHub (sumber kode)

1. Buka pull request ke `main`, verifikasi review, dan tunggu Quality Gate sukses (PHP unit/feature, JS, frontend build, reset smoke test pada database temporer).
2. Merge commit yang sudah lulus. GitHub kemudian menyimpan rilis dan secara otomatis **menguji**, tetapi belum men-deploy Laravel ke internet.
3. Hubungkan server hosting Laravel ke repository GitHub atau deploy artifact dari CI sesuai penyedia hosting. Gunakan migrasi **non-destruktif** (`php artisan migrate --force`), `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, cache Laravel, dan set izin `storage` / `bootstrap/cache` secara aman. Jalankan langkah dan urutannya sesuai host dan kebutuhan zero-downtime; jangan jalankan `migrate:fresh`, `db:wipe`, atau `rt03:reset-demo` pada production.
4. Pastikan hostname HTTPS benar, database/server private storage persisten, akun Super Admin awal dibuat dengan kredensial kuat dan jalur bootstrap yang aman, serta backup sudah aktif. Uji login sungguhan pada domain staging.
5. Buka akses ke warga **setelah** seluruh uji penerimaan dan kebijakan privasi disetujui pengurus.

## Catatan aset lokal

File `public/audio/indonesia-raya-instrumental.mp3` di komputer pengembang tidak ikut ke GitHub jika masih bertanda `??` / *untracked*. Uji audio pada hosting setelah file berlisensi/diizinkan dipasang secara sah. Foto warga dan database lokal jangan dikomit ke repository publik.

## Status keamanan

Tes sukses merupakan bukti perilaku yang dicakup tes, **bukan sertifikat keamanan**. Belum ada audit penetrasi langsung atau uji beban production yang terdokumentasi. Jangan klaim *production live* sebelum alamat hosting dan backend diverifikasi.
