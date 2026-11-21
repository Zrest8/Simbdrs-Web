<?php
// =====================================================================
// Daftar menu SIMBDRS (dipakai sidebar.php dan modules/segera.php).
// 'path' = null → modul belum dibuat; menu tetap bisa diklik dan membuka
//                 halaman "sedang dibangun" (modules/segera.php?m=kunci).
// 'ket'  = penjelasan singkat fungsi modul (tampil di halaman segera).
// =====================================================================
$menu = [
    ['judul' => 'Utama'],
    ['kunci' => 'dashboard',   'ikon' => 'speedometer2',       'label' => 'Dashboard',            'path' => 'modules/dashboard/index.php', 'peran' => ['ADMIN', 'PETUGAS', 'RUANGAN', 'KEPALA']],

    ['judul' => 'Layanan Darah'],
    ['kunci' => 'penerimaan',  'ikon' => 'box-arrow-in-down',  'label' => 'Penerimaan Kantong',   'path' => 'modules/penerimaan/index.php', 'peran' => ['PETUGAS'],
     'ket' => 'Mencatat kantong darah yang datang dari UDD. Tanggal kedaluwarsa dihitung otomatis dari tanggal aftap.', 'tabel' => 'penerimaan, kantong_darah'],
    ['kunci' => 'stok',        'ikon' => 'droplet-half',       'label' => 'Stok Darah',           'path' => 'modules/stok/index.php', 'peran' => ['PETUGAS', 'KEPALA'],
     'ket' => 'Melihat daftar kantong per komponen, golongan, dan status, diurutkan dari kedaluwarsa terdekat (FEFO).', 'tabel' => 'kantong_darah'],
    ['kunci' => 'permintaan',  'ikon' => 'clipboard2-pulse',   'label' => 'Permintaan Darah',     'path' => 'modules/permintaan/index.php', 'peran' => ['PETUGAS', 'RUANGAN'],
     'ket' => 'Ruangan mengajukan permintaan darah untuk pasien; petugas memprosesnya dan mencatat hasil uji silang.', 'tabel' => 'permintaan_darah, uji_silang, kantong_darah'],
    ['kunci' => 'transfusi',   'ikon' => 'heart-pulse',        'label' => 'Transfusi',            'path' => 'modules/transfusi/index.php', 'peran' => ['PETUGAS', 'RUANGAN'],
     'ket' => 'Mencatat penyerahan kantong ke ruangan serta waktu mulai dan selesai transfusi.', 'tabel' => 'transfusi, kantong_darah'],
    ['kunci' => 'keluar',      'ikon' => 'arrow-return-left',  'label' => 'Pengembalian & Pemusnahan', 'path' => 'modules/keluar/index.php', 'peran' => ['PETUGAS'],
     'ket' => 'Mencatat kantong yang dikembalikan ke UDD atau dimusnahkan, beserta alasannya.', 'tabel' => 'pengembalian, pemusnahan, kantong_darah'],

    ['judul' => 'Analisis'],
    ['kunci' => 'rekomendasi', 'ikon' => 'graph-up-arrow',     'label' => 'Rekomendasi Pemesanan','path' => 'modules/rekomendasi/index.php', 'peran' => ['PETUGAS', 'KEPALA'],
     'ket' => 'Menghitung jumlah kantong yang disarankan dipesan ke UDD dari rata-rata pemakaian dan stok minimal (BR-10).', 'tabel' => 'transfusi, kantong_darah, stok_minimal, pengaturan'],
    ['kunci' => 'laporan',     'ikon' => 'file-earmark-bar-graph', 'label' => 'Laporan',          'path' => 'modules/laporan/index.php', 'peran' => ['PETUGAS', 'KEPALA'],
     'ket' => 'Laporan penerimaan, pemakaian, pemusnahan, dan wastage rate per periode.', 'tabel' => 'semua tabel transaksi'],

    ['judul' => 'Data Master'],
    ['kunci' => 'ruangan',     'ikon' => 'hospital',           'label' => 'Ruangan',              'path' => 'modules/master/ruangan/index.php', 'peran' => ['ADMIN']],
    ['kunci' => 'dokter',      'ikon' => 'person-badge',       'label' => 'Dokter',               'path' => 'modules/master/dokter/index.php', 'peran' => ['ADMIN'],
     'ket' => 'Mengelola data dokter penanggung jawab permintaan darah.', 'tabel' => 'dokter'],
    ['kunci' => 'pasien',      'ikon' => 'people',             'label' => 'Pasien',               'path' => 'modules/master/pasien/index.php', 'peran' => ['ADMIN'],
     'ket' => 'Mengelola data pasien (fiktif) beserta golongan darah dan rhesus.', 'tabel' => 'pasien'],
    ['kunci' => 'komponen',    'ikon' => 'capsule',            'label' => 'Komponen Darah',       'path' => 'modules/master/komponen/index.php', 'peran' => ['ADMIN'],
     'ket' => 'Mengelola jenis komponen darah, masa simpan, dan batas hari peringatan.', 'tabel' => 'komponen_darah'],
    ['kunci' => 'udd',         'ikon' => 'building',           'label' => 'UDD Asal',             'path' => 'modules/master/udd/index.php', 'peran' => ['ADMIN'],
     'ket' => 'Mengelola daftar UDD / UTD pengirim kantong darah.', 'tabel' => 'udd_asal'],
    ['kunci' => 'stok_minimal','ikon' => 'sliders',            'label' => 'Stok Minimal',         'path' => 'modules/master/stok_minimal/index.php', 'peran' => ['ADMIN'],
     'ket' => 'Mengatur batas stok minimal per komponen, golongan, dan rhesus.', 'tabel' => 'stok_minimal'],
    ['kunci' => 'pengguna',    'ikon' => 'person-gear',        'label' => 'Pengguna',             'path' => 'modules/master/pengguna/index.php', 'peran' => ['ADMIN'],
     'ket' => 'Mengelola akun login dan perannya.', 'tabel' => 'pengguna'],
    ['kunci' => 'log',         'ikon' => 'journal-text',       'label' => 'Log Aktivitas',        'path' => 'modules/log/index.php', 'peran' => ['ADMIN'],
     'ket' => 'Melihat jejak siapa melakukan apa dan kapan (audit trail).', 'tabel' => 'log_aktivitas'],
];
