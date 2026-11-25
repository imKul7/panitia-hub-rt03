# PANITIA HUB RT03 — Render Free (DEMO, bukan production)

**Penting:** Render Free web service menjalankan PHP Laravel dengan Docker, tetapi SQLite dan semua file yang ditulis pada instance **hilang saat sleep, restart atau deploy**. Jangan memasukkan data peserta/warga asli, uang kas sebenarnya, dokumen pribadi, ataupun membuat akun real. Tidak ada akun login panitia yang otomatis dibuat; preview menampilkan portal publik berisi struktur periode 2027. Ini **bukan** penerapan database lokal Anda.

## Langkah di Render Web Service

1. Source GitHub: `imKul7/panitia-hub-rt03`, branch `main`.
2. Name: `panitia-hub-rt03` (jika tersedia).
3. Language: `Docker` (bukan Node); Dockerfile Path: `./Dockerfile`; Root Directory kosong.
4. Region: `Singapore` jika tersedia, agar dekat Indonesia.
5. Compute: **Free — $0/month**. Jika ringkasan masih $7/month, **jangan deploy**.
6. Environment variables (masukkan di Render Environment, **bukan** commit .env):
   - `APP_NAME` = `PANITIA HUB RT 03`
   - `APP_ENV` = `production`
   - `APP_DEBUG` = `false`
   - `APP_URL` = alamat HTTPS yang benar setelah Render memberi domain, misalnya `https://panitia-hub-rt03.onrender.com` jika tersedia
   - `APP_KEY` = nilai unik hasil `php artisan key:generate --show` dari terminal proyek lokal; rahasiakan dan **jangan diubah setiap deploy**
   - `RT03_DEMO_MODE` = `ephemeral`
   - `DB_CONNECTION` = `sqlite`
   - `DB_DATABASE` = `/tmp/rt03-preview.sqlite`
   - `SESSION_DRIVER` = `file`
   - `CACHE_STORE` = `file`
   - `QUEUE_CONNECTION` = `sync`
   - `SESSION_SECURE_COOKIE` = `true`
   - `ASSET_URL` = alamat HTTPS dari APP_URL yang sama
7. Biarkan Docker Command/Build Command/Start Command kustom kosong. Container menjalankan `docker/render-start.sh` otomatis.
8. Klik Deploy hanya sesudah semua kolom benar. Periksa Logs dan health endpoint `/up`.

### Perilaku yang disengaja
- Startup membuat **database SQLite sementara kosong** dengan periode 2027, 10 divisi, 6 panduan bawaan; tidak ada akun Super Admin yang disalin dari laptop.
- Saat Render Free tertidur setelah 15 menit, database sementara dan perubahan file hilang; startup berikutnya membuat struktur awal lagi. Tidak ada jalur migrasi data sungguhan dari laptop.
- Server memakai Caddy/FrankenPHP, bukan `php artisan serve`.
- Tidak membagikan `.env`, password, file MP3, atau data pribadi dalam repository publik / Docker image.

### Jika butuh sistem benar-benar digunakan warga
Gunakan **database persisten yang dikelola dengan backup**, penyimpanan objek privat persisten untuk foto/bukti/dokumen, HTTPS, manajemen akun, pengujian lintas role, penilaian privasi dan uji restore. Script ini sengaja menolak menjalankan layanan di luar konfigurasi demo sementara; rilis production memerlukan desain deployment terpisah.
