<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#c82539">
  @php
    [$heading,$description] = match ((int) $status) {
      403 => ['Akses dibatasi','Akun ini tidak mempunyai izin untuk menjalankan tindakan tersebut. Hubungi ketua panitia atau admin jika perlu akses.'],
      404 => ['Halaman tidak ditemukan','Halaman atau data yang dicari tidak tersedia. Periksa menu yang digunakan atau kembali ke workspace.'],
      419 => ['Sesi telah berakhir','Sesi login atau token keamanan sudah kedaluwarsa. Masuk kembali lalu ulangi tindakan Anda.'],
      422 => ['Data belum sesuai','Permintaan tidak dapat diproses. Periksa kelengkapan dan pilihan data, kemudian coba lagi.'],
      429 => ['Terlalu banyak permintaan','Ada terlalu banyak permintaan dalam waktu singkat. Coba kembali sebentar lagi.'],
      default => ['Permintaan belum dapat diproses','Silakan kembali ke halaman sebelumnya atau hubungi pengelola sistem.'],
    };
  @endphp
  <title>{{ $status }} · {{ $heading }} · PANITIA HUB RT 03</title>
  <style>
    :root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#192c44;background:#f4f6f8}
    *{box-sizing:border-box}
    body{min-height:100vh;margin:0;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 85% 12%,rgba(239,75,79,.09),transparent 34%),#f4f6f8}
    .card{position:relative;max-width:510px;width:100%;padding:clamp(25px,5vw,43px);border:1px solid #e8e8eb;background:#fff;border-radius:23px;box-shadow:0 20px 60px rgba(32,52,74,.09);overflow:hidden}
    .card:before{position:absolute;content:"";height:5px;left:0;right:0;top:0;background:linear-gradient(90deg,#b51e32,#e83e4d 62%,#ffb493)}
    .brand{font-size:12px;letter-spacing:.13em;font-weight:800;color:#a13b4c}
    .label{font-size:11px;font-weight:800;color:#8190a1;letter-spacing:.12em;margin-top:37px}
    .icon{display:grid;place-items:center;width:58px;height:58px;margin-top:27px;border-radius:18px;color:#c82539;background:#fff0f1;font-size:27px;font-weight:800}
    h1{font-size:clamp(27px,5vw,37px);line-height:1.2;letter-spacing:-.055em;margin:14px 0 12px}
    p{color:#708095;font-size:14px;line-height:1.8;margin:0}
    .details{display:flex;align-items:center;gap:10px;margin-top:24px;padding:12px 13px;border-radius:12px;background:#f5f7fa;color:#647588;font-size:12px}
    .code{font-weight:800;color:#ba2a3f}
    .actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:24px}
    a{display:inline-flex;align-items:center;justify-content:center;min-height:45px;padding:12px 18px;border-radius:11px;font-size:13px;font-weight:800;text-decoration:none}
    .primary{background:#c82539;color:white}
    .primary:hover{background:#a91d30}
    .secondary{background:#f1f3f6;color:#263d56}
    .secondary:hover{background:#e8ecf1}
    a:focus-visible{outline:3px solid rgba(200,37,57,.45);outline-offset:3px}
    .foot{font-size:10px;color:#a0abba;margin-top:25px;letter-spacing:.08em}
  </style>
</head>
<body>
  <main class="card">
    <div class="brand">PANITIA HUB <span aria-hidden="true">/</span> RT 03</div>
    <div class="icon" aria-hidden="true">!</div>
    <div class="label">PERMINTAAN TIDAK BERHASIL</div>
    <h1>{{ $heading }}<span style="color:#c82539">.</span></h1>
    <p>{{ $description }}</p>
    <div class="details" role="status">
      <span class="code">HTTP {{ $status }}</span>
      <span>Periksa izin akun atau data yang dikirim, kemudian coba lagi.</span>
    </div>
    <div class="actions">
      <a class="primary" href="{{ (int) $status === 419 ? route('login') : (auth()->check() ? route('dashboard') : route('login')) }}">
        {{ (int) $status === 419 ? 'Masuk kembali' : 'Ke Dashboard' }}
      </a>
      <a class="secondary" href="{{ route('public.home') }}">Beranda Warga</a>
    </div>
    <div class="foot">BERSAMA · BERGERAK · BERKARYA</div>
  </main>
</body>
</html>
