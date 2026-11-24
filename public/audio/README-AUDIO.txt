PANITIA HUB RT03 — MUSIK INSTRUMENTAL DI WEBSITE WARGA

1. Siapkan rekaman instrumental Indonesia Raya yang Anda berhak tayangkan secara publik.
2. Di folder PanitiaHubApp, letakkan dengan lokasi dan nama PERSIS:
   public/audio/indonesia-raya-instrumental.mp3
3. Gunakan file MP3 standar yang bisa dimainkan browser.
4. Jalankan Laravel/Vite dan refresh Beranda (Ctrl+Shift+R).

PERILAKU AUDIO
- Website akan mencoba memutar audio bersuara ketika halaman warga dibuka.
- Jika browser memblokir autoplay, klik / sentuh pertama di area mana pun
  pada portal warga memulai musik tanpa harus menekan tombol Putar.
- Jika browser tetap menolak, kontrol Putar pada kartu Beranda atau mini
  player di halaman Lomba/Jadwal/Pengumuman/Hasil/Galeri tetap tersedia.
- Klik Jeda untuk menghentikan pemutaran. Klik lain pada halaman tidak
  mengaktifkan ulang musik setelah pengguna sengaja memilih Jeda.
- Musik berlanjut saat berpindah antara enam halaman warga karena elemen
  audio tetap hidup di luar <main>. Pergi ke login/workspace menghentikan
  musik karena sudah meninggalkan portal warga.
- Ketika rekaman selesai, musik berhenti; tidak loop otomatis.
- Tombol mute/suara mengikuti volume sistem; volume awal situs 38%.
- File tidak tersedia? Kartu menampilkan 'Instrumental segera hadir'.
- Gunakan file instrumental yang punya hak pakai/distribusi yang tepat.

Catatan penting: browser (Chrome, Edge, Safari, Firefox) tidak memberi
jaminan autoplay bersuara pada kunjungan pertama. Solusi klik pertama
adalah cara fallback yang sesuai kebijakan browser, bukan bypass.
