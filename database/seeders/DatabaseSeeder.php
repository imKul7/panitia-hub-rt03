<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{EventPeriod,Division,SopGuide};

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $period=EventPeriod::firstOrCreate(['year'=>2027],[
            'name'=>'Semarak Kemerdekaan RT 03 2027',
            'tagline'=>'Satu lingkungan, satu semangat.',
            'event_date'=>'2027-08-17',
            'status'=>'active',
        ]);
        foreach ([
            'sekretariat'=>'Surat, rapat, notulen, dan dokumen',
            'keuangan'=>'Anggaran, transaksi, dan pertanggungjawaban',
            'lomba'=>'Aturan, peserta, hasil, dan pelaksanaan lomba',
            'hadiah'=>'Pengadaan dan pembagian hadiah',
            'acara'=>'Rundown dan malam puncak',
            'perlengkapan'=>'Peralatan, inventaris, dan logistik',
            'publikasi'=>'Pengumuman dan komunikasi warga',
            'humas'=>'Hubungan warga dan undangan',
            'konsumsi'=>'Kebutuhan konsumsi panitia dan peserta',
            'keamanan'=>'Ketertiban dan keselamatan kegiatan',
        ] as $slug=>$description) {
            Division::firstOrCreate(['event_period_id'=>$period->id,'slug'=>$slug],[
                'name'=>ucwords(str_replace('_',' ',$slug)),'description'=>$description,
            ]);
        }
        foreach ([
            ['Persiapan Ketua Panitia','kepemimpinan',"1. Bentuk struktur dan tentukan PIC setiap divisi.\n2. Sepakati jadwal rapat, anggaran, dan batas waktu.\n3. Periksa hambatan dan keputusan yang butuh persetujuan.\n4. Evaluasi hasil bersama mentor; jangan serahkan pekerjaan ke mentor."],
            ['Panduan Bendahara','keuangan',"1. Susun perkiraan kebutuhan dan sumber dana.\n2. Catat setiap pemasukan atau pengeluaran pada hari yang sama.\n3. Unggah bukti transaksi yang sah.\n4. Cocokkan saldo dengan uang fisik / rekening.\n5. Buat laporan akhir dan minta pemeriksaan ketua."],
            ['Panduan Sekretaris','sekretariat',"1. Buat agenda rapat dan daftar hadir.\n2. Catat keputusan, pemilik tindakan, dan tenggat.\n3. Simpan proposal, surat, notulen, dan laporan dengan nama jelas.\n4. Bagikan keputusan kepada divisi terkait."],
            ['Panduan Koordinator Lomba','lomba',"1. Putuskan jenis lomba, kategori, aturan, dan kuota.\n2. Pastikan data peserta diinput panitia.\n3. Informasikan jadwal dan aturan ke warga.\n4. Verifikasi hasil sebelum dipublikasikan.\n5. Serahkan daftar pemenang kepada divisi hadiah."],
            ['Panduan Malam Puncak','acara',"1. Susun rundown per segmen, jam mulai, dan PIC.\n2. Pastikan perlengkapan, listrik, MC, dan penampilan terkonfirmasi.\n3. Siapkan rencana cadangan bila hujan atau listrik padam.\n4. Lakukan gladi dan koordinasi dengan seluruh koordinator."],
            ['Panduan Mentor','mentoring',"1. Pantau progres melalui dashboard.\n2. Tanyakan kendala dan berikan solusi atau arahan.\n3. Hindari mengubah data maupun mengambil alih pekerjaan junior.\n4. Dokumentasikan evaluasi dan pembelajaran untuk tahun depan."],
        ] as $i=>$row) {
            SopGuide::firstOrCreate(['event_period_id'=>null,'title'=>$row[0]],['category'=>$row[1],'content'=>$row[2],'sort_order'=>$i]);
        }
    }
}
