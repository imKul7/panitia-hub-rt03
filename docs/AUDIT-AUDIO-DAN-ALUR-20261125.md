# Audit ulang Panitia Hub RT 03 — audio, media, dan konsistensi alur
Tanggal: 25 November 2026
Lingkup: branch main GitHub; kode Laravel, Blade, JavaScript, Dockerfile, CI. Tidak mencakup inspeksi browser pengguna, database lokal, atau administrasi layanan Railway.

## 1. Penyebab audio tidak langsung bersuara
- File `public/audio/indonesia-raya-instrumental.mp3` sudah di-commit. Tes Docker melalui HTTP memverifikasi byte file audio dan gambar.
- Browser Chrome, Edge, Safari dan Firefox **dapat memblokir audio autoplay bersuara** sebelum interaksi yang memenuhi kebijakan browser. `HTMLAudioElement.play()` bisa menolak dengan `NotAllowedError`.
- Perilaku yang realistis: coba `play()` pada awal kunjungan, jika ditolak tampilkan pemutar yang jelas di Beranda; coba lagi secara sinkron pada interaksi pertama; sediakan tombol Putar/Jeda; jangan memaksa mute atau bypass; hormati jeda dan jangan otomatis mengulang sesudah trek berakhir.
- Sumber: MDN HTMLMediaElement.play() dan Chrome Developers autoplay policy, dokumen resmi WebKit.

## 2. Audit alur media portal warga
- 6 halaman publik: Beranda, Lomba, Jadwal, Pengumuman, Hasil, Galeri.
- Musik ditempatkan di layout publik, di luar `<main>`. Navigasi cepat hanya mengganti `<main>`, sehingga trek bertahan saat berpindah halaman publik.
- Tombol musik di hero dan mini player di halaman lain disinkronkan. Saat autoplay diblokir, mini player juga ditampilkan di Beranda sehingga pengguna menemukan tombol Putar.
- Status `waiting/stalled` kini ditandai sebagai buffering, bukan "sedang diputar"; tombol Jeda tetap dapat menghentikan audio saat loading.
- Permintaan login, form tulis, dan modul admin tidak masuk sistem navigasi publik dan tidak memuat player publik.
- Foto hero/lingkungan/logo/slide login memakai aset dari `public/images/rt03`. Pembayaran/konten privat **tidak** dipublikasikan melalui folder tersebut.

## 3. Audit keutuhan data
Ada operasi yang menulis data lalu membuat aktivitas log dalam transaksi berbeda. Jika audit log gagal, pengguna bisa melihat error padahal data sudah telanjur berubah.
Perbaikan pada operasi:
- Pengajuan dana: pengajuan + log dalam transaksi tunggal.
- Pendaftaran lomba: peserta baru + pendaftaran + log atomik.
- Peminjaman/pengembalian inventaris: stok + gerakan + log atomik.
- Penyerahan hadiah: stok + riwayat distribusi + log atomik.
- Penugasan jabatan: membership + audit log atomik.
- Pembaruan status tugas: status + progres + audit log atomik.
Tidak ada perubahan struktur database atau penghapusan data.

## 4. Pengujian otomatis
- `tests/js/public-audio.test.cjs`: berhasil autoplay jika diizinkan, `NotAllowedError`, ketukan pertama, synthetic click diabaikan, jeda bertahan, buffering, error media, musik selesai tidak auto-loop, sinkronisasi navigasi cepat.
- `tests/Feature/CriticalAuditAtomicityTest.php`: simulasi kegagalan ActivityLog dan verifikasi rollback untuk enam operasi.
- `tests/Feature/PublicAudioPlaybackTest.php`: audio shell pada 6 halaman publik, area admin/login tidak menerima player.
- Tes role dan modul lain pada suite Laravel yang sudah ada: akses keuangan/periode sembilan peran; penghapusan rekam berbasis izin; proteksi data antarperiode; antarmuka profil, loading, permintaan dana, dokumen/galeri, validasi tugas, reset data dummy.
- Tes Docker: manifest Vite, HTTPS forwarded scheme, URL aset CSS/JS, file media yang benar-benar disajikan lewat HTTP.

## 5. Batasan dan pekerjaan lanjutan
- Tidak ada website yang dapat menjamin musik otomatis bersuara untuk setiap browser: pengunjung yang diblokir browser harus berinteraksi dengan tombol/halaman.
- Status Railway `Active` tidak membuktikan semua halaman berfungsi. Konfirmasi deployment commit terbaru dan tes manual Chrome/Edge/Android/Safari, audio lintas tab, scroll, dan tombol Putar/Jeda.
- Mode `RT03_DEMO_MODE=ephemeral` memakai SQLite di /tmp: seluruh data baru, sesi, log, unggahan internal dapat hilang setelah restart/redeploy. **Tidak boleh** memasukkan data penduduk, peserta, dokumen, atau keuangan asli. Sebelum operasional perlu database persisten, storage persisten, backup+restore teruji, monitoring, domain/HTTPS, role audit, dan kebijakan retensi/privasi.
- Approval pengajuan dana dan pencatatan transaksi kas masih berupa dua alur berbeda; keterkaitan permintaan yang disetujui dengan pencairan sebenarnya serta prosedur koreksi audit masih membutuhkan aturan bisnis eksplisit. Jangan otomatis mengubah data keuangan lama.
- Foto dan MP3 yang sudah di-commit berada pada repository publik: pastikan izin tayang foto dan izin lisensi rekaman. Penempatan di Git publik tidak mudah dicabut dari histori clone.
- Kunci API apa pun yang pernah terlihat di screenshot publik/chat harus dianggap bocor dan diputar/revokasi.
- Audit kode + CI tidak sama dengan pentest menyeluruh atau jaminan 100% bebas cacat.

## 6. Checklist QA manual saat Railway terbaru aktif
1. Buka URL / dan enam halaman warga, pastikan CSS dan foto/hero tampil.
2. Uji akun baru browser/incognito: autoplay bisa ditolak, mini player tampil dan tombol bisa memutar audio.
3. Ketuk layar sekali, verifikasi status berubah menjadi sedang diputar bila browser mengizinkan; jika tidak, tekan Putar.
4. Klik Jeda, lalu klik menu. Musik tetap berhenti dan tidak otomatis dimulai lagi.
5. Tekan Putar lalu pindah antar keenam halaman; jangan restart dari detik 0.
6. Putar sampai akhir; tak boleh mengulang otomatis.
7. Cek setiap role pada data dummy **lokal/testing terisolasi** saja, bukan di database demo bersama.
8. Uji form keuangan, pendaftaran, tugas, inventaris, hadiah dan catatan log; jangan gunakan data nyata di Railway Free.
9. Cek deployment SHA, aplikasi build/healthcheck, dan console Network/Media jika masih tidak berbunyi.
