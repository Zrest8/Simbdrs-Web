<?php
// =====================================================================
// Daftar menu SIMBDRS (dipakai sidebar.php dan modules/segera.php).
// 'path' = null → modul belum dibuat; menu tetap bisa diklik dan membuka
//                 halaman "sedang dibangun" (modules/segera.php?m=kunci).
// 'ket'  = penjelasan singkat fungsi modul (tampil di halaman segera).
// 'aksi' = aksi yang berlaku di menu ini + artinya. Siapa yang boleh
//          melakukan aksi itu TIDAK ditulis di sini, tetapi di tabel
//          hak_akses (diatur Admin lewat menu "Hak Akses").
//          Dashboard tidak punya 'aksi': selalu bisa dibuka semua peran.
// =====================================================================
$CRUD = ['lihat' => 'Melihat daftar', 'tambah' => 'Menambah data', 'ubah' => 'Mengubah data', 'hapus' => 'Menghapus / menonaktifkan'];
$menu = [
    ['judul' => 'Utama'],
    ['kunci' => 'dashboard',   'ikon' => 'speedometer2',       'label' => 'Dashboard',            'path' => 'modules/dashboard/index.php'],

    ['judul' => 'Layanan Darah'],
    ['kunci' => 'penerimaan',  'ikon' => 'box-arrow-in-down',  'label' => 'Penerimaan Kantong',   'path' => 'modules/penerimaan/index.php', 'aksi' => ['lihat' => 'Melihat riwayat penerimaan', 'tambah' => 'Mencatat penerimaan kantong'],
     'ket' => 'Mencatat kantong darah yang datang dari UDD. Tanggal kedaluwarsa dihitung otomatis dari tanggal aftap.', 'tabel' => 'penerimaan, kantong_darah'],
    ['kunci' => 'stok',        'ikon' => 'droplet-half',       'label' => 'Stok Darah',           'path' => 'modules/stok/index.php', 'aksi' => ['lihat' => 'Melihat stok kantong'],
     'ket' => 'Melihat daftar kantong per komponen, golongan, dan status, diurutkan dari kedaluwarsa terdekat (FEFO).', 'tabel' => 'kantong_darah'],
    ['kunci' => 'permintaan',  'ikon' => 'clipboard2-pulse',   'label' => 'Permintaan Darah',     'path' => 'modules/permintaan/index.php', 'aksi' => ['lihat' => 'Melihat permintaan', 'tambah' => 'Membuat permintaan', 'ubah' => 'Memproses (uji silang, pesan kantong)', 'hapus' => 'Membatalkan permintaan'],
     'ket' => 'Perawat mengajukan permintaan darah untuk pasien; petugas memprosesnya dan mencatat hasil uji silang.', 'tabel' => 'permintaan_darah, uji_silang, kantong_darah'],
    ['kunci' => 'transfusi',   'ikon' => 'heart-pulse',        'label' => 'Transfusi',            'path' => 'modules/transfusi/index.php', 'aksi' => ['lihat' => 'Melihat transfusi', 'tambah' => 'Menyerahkan kantong ke ruangan', 'ubah' => 'Mencatat mulai / selesai transfusi'],
     'ket' => 'Mencatat penyerahan kantong ke ruangan serta waktu mulai dan selesai transfusi.', 'tabel' => 'transfusi, kantong_darah'],
    ['kunci' => 'keluar',      'ikon' => 'arrow-return-left',  'label' => 'Pengembalian & Pemusnahan', 'path' => 'modules/keluar/index.php', 'aksi' => ['lihat' => 'Melihat riwayat', 'tambah' => 'Mencatat pengembalian / pemusnahan'],
     'ket' => 'Mencatat kantong yang dikembalikan ke UDD atau dimusnahkan, beserta alasannya.', 'tabel' => 'pengembalian, pemusnahan, kantong_darah'],

    ['judul' => 'Analisis'],
    ['kunci' => 'rekomendasi', 'ikon' => 'graph-up-arrow',     'label' => 'Rekomendasi Pemesanan','path' => 'modules/rekomendasi/index.php', 'aksi' => ['lihat' => 'Melihat rekomendasi'],
     'ket' => 'Menghitung jumlah kantong yang disarankan dipesan ke UDD dari rata-rata pemakaian dan stok minimal (BR-10).', 'tabel' => 'transfusi, kantong_darah, stok_minimal, pengaturan'],
    ['kunci' => 'laporan',     'ikon' => 'file-earmark-bar-graph', 'label' => 'Laporan',          'path' => 'modules/laporan/index.php', 'aksi' => ['lihat' => 'Melihat & mencetak laporan'],
     'ket' => 'Laporan penerimaan, pemakaian, pemusnahan, dan wastage rate per periode.', 'tabel' => 'semua tabel transaksi'],

    ['judul' => 'Data Master'],
    ['kunci' => 'ruangan',     'ikon' => 'hospital',           'label' => 'Ruangan',              'path' => 'modules/master/ruangan/index.php', 'aksi' => $CRUD],
    ['kunci' => 'dokter',      'ikon' => 'person-badge',       'label' => 'Dokter',               'path' => 'modules/master/dokter/index.php', 'aksi' => $CRUD,
     'ket' => 'Mengelola data dokter penanggung jawab beserta akun loginnya.', 'tabel' => 'dokter, pengguna'],
    ['kunci' => 'pasien',      'ikon' => 'people',             'label' => 'Pasien',               'path' => 'modules/master/pasien/index.php', 'aksi' => $CRUD,
     'ket' => 'Mengelola data pasien (fiktif) beserta golongan darah dan rhesus.', 'tabel' => 'pasien'],
    ['kunci' => 'komponen',    'ikon' => 'capsule',            'label' => 'Komponen Darah',       'path' => 'modules/master/komponen/index.php', 'aksi' => $CRUD,
     'ket' => 'Mengelola jenis komponen darah, masa simpan, dan batas hari peringatan.', 'tabel' => 'komponen_darah'],
    ['kunci' => 'udd',         'ikon' => 'building',           'label' => 'UDD Asal',             'path' => 'modules/master/udd/index.php', 'aksi' => $CRUD,
     'ket' => 'Mengelola daftar UDD / UTD pengirim kantong darah.', 'tabel' => 'udd_asal'],
    ['kunci' => 'stok_minimal','ikon' => 'sliders',            'label' => 'Stok Minimal',         'path' => 'modules/master/stok_minimal/index.php', 'aksi' => ['lihat' => 'Melihat stok minimal', 'ubah' => 'Mengubah stok minimal & pengaturan'],
     'ket' => 'Mengatur batas stok minimal per komponen, golongan, dan rhesus.', 'tabel' => 'stok_minimal'],
    ['kunci' => 'pengguna',    'ikon' => 'person-gear',        'label' => 'Pengguna',             'path' => 'modules/master/pengguna/index.php', 'aksi' => $CRUD,
     'ket' => 'Mengelola akun login dan perannya.', 'tabel' => 'pengguna'],
    ['kunci' => 'hak_akses',   'ikon' => 'shield-lock',        'label' => 'Hak Akses',            'path' => 'modules/master/hak_akses/index.php', 'aksi' => ['lihat' => 'Melihat hak akses', 'ubah' => 'Mengubah hak akses peran'],
     'ket' => 'Mengatur menu dan aksi yang boleh dipakai setiap peran.', 'tabel' => 'hak_akses'],
    ['kunci' => 'log',         'ikon' => 'journal-text',       'label' => 'Log Aktivitas',        'path' => 'modules/log/index.php', 'aksi' => ['lihat' => 'Melihat log aktivitas'],
     'ket' => 'Melihat jejak siapa melakukan apa dan kapan (audit trail).', 'tabel' => 'log_aktivitas'],
];
