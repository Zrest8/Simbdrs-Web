<?php
// =====================================================================
// Generator database/data_dummy.sql — simulasi 90 hari operasional BDRS (semua data FIKTIF).
// Jalankan dari terminal (BUKAN dari browser):
//   C:\xampp\php\php.exe buat_data_dummy.php data_dummy.sql
// Angka acak memakai mt_srand tetap, jadi hasilnya selalu sama.
// =====================================================================
if (PHP_SAPI !== 'cli' || !isset($argv[1])) {
    http_response_code(403);
    exit("Jalankan dari terminal: php buat_data_dummy.php data_dummy.sql\n");
}
// Tanggal ditulis relatif terhadap waktu impor (NOW()/CURDATE()), jadi data selalu "baru".
mt_srand(20261105);
$HARI = 90;
$out = [];
$q = fn($s) => $s === null ? 'NULL' : "'" . str_replace("'", "''", $s) . "'";
$waktu = fn(int $d, int $mundur) => "NOW() - INTERVAL " . (-$d * 1440 + $mundur) . " MINUTE";   // d <= 0
$tgl = fn(int $d) => $d === 0 ? 'CURDATE()' : ($d < 0 ? 'CURDATE() - INTERVAL ' . (-$d) . ' DAY' : 'CURDATE() + INTERVAL ' . $d . ' DAY');
$pick = function (array $bobot) { $t = mt_rand(1, array_sum($bobot)); foreach ($bobot as $k => $w) { if (($t -= $w) <= 0) return $k; } };
$id = fn(string $tabel, string $kol, string $nilai) => "(SELECT id_$tabel FROM " . ($tabel === 'kantong' ? 'kantong_darah' : $tabel) . " WHERE $kol = " . $q($nilai) . ")";
$idk = fn(string $no) => "(SELECT id_kantong FROM kantong_darah WHERE no_kantong = " . $q($no) . ")";
$idu = fn(string $u) => "(SELECT id_pengguna FROM pengguna WHERE username = " . $q($u) . ")";
$log = [];   // [waktuExpr, user, aksi, tabel, idExpr, ket]

// ---------------- Master ----------------
$ruangan = [
    'IGD' => 'igd', 'ICU' => 'icu', 'Ruang Bedah' => 'bedah', 'Ruang Penyakit Dalam' => 'interna', 'Ruang Anak' => 'anak',
];
$akun_baru = [
    ['Perawat ICU', 'icu', 'icu12345', 'ICU'],
    ['Perawat Ruang Bedah', 'bedah', 'bedah123', 'Ruang Bedah'],
    ['Perawat Penyakit Dalam', 'interna', 'interna123', 'Ruang Penyakit Dalam'],
    ['Perawat Ruang Anak', 'anak', 'anak1234', 'Ruang Anak'],
    ['Petugas BDRS Sore', 'petugas2', 'petugas234', null],
];
$dokter = [
    ['dr. Andika Pratama, Sp.PD', 'Penyakit Dalam'], ['dr. Ratna Kusumawati, Sp.B', 'Bedah'],
    ['dr. Hendra Wijaya, Sp.An', 'Anestesi'], ['dr. Melati Anggraini, Sp.A', 'Anak'],
    ['dr. Yusuf Hakim, Sp.EM', 'Emergensi'], ['dr. Sari Puspitasari, Sp.OG', 'Obstetri & Ginekologi'],
    ['dr. Bambang Setiadi', 'Umum'],
];
$dokter_ruang = ['IGD' => [4, 6, 1], 'ICU' => [2, 0], 'Ruang Bedah' => [1, 2], 'Ruang Penyakit Dalam' => [0], 'Ruang Anak' => [3]];
$udd = [
    ['UDD PMI Kota Sukamaju (fiktif)', 'Jl. Melati No. 12, Kota Sukamaju', '0221234567', 'SKM'],
    ['UDD PMI Kabupaten Mekarsari (fiktif)', 'Jl. Raya Mekarsari Km 3', '0227654321', 'MKS'],
    ['UTD RS Harapan Sehat (fiktif)', 'Jl. Kenanga No. 8, Kota Sukamaju', '0229876543', 'HRS'],
];
$depan = ['Ahmad','Budi','Citra','Dewi','Eko','Fitri','Gilang','Hana','Indra','Joko','Kartika','Lukman','Maya','Nanda','Oki','Putri','Rizal','Sinta','Taufik','Umi','Vina','Wahyu','Yani','Zaki','Agus','Bunga','Dimas','Erna','Fajar','Galih','Intan','Rudi','Lestari','Teguh','Wulan','Arif'];
$belakang = ['Saputra','Lestari','Hidayat','Rahmawati','Kurniawan','Wulandari','Siregar','Nugroho','Pertiwi','Santoso','Permata','Gunawan','Halim','Utami','Firmansyah','Maharani'];
$gol_bobot = ['O' => 37, 'B' => 30, 'A' => 25, 'AB' => 8];
$pasien = [];
for ($i = 0; $i < 42; $i++) {
    $anak = $i >= 36;
    $g = $pick($gol_bobot);
    $rh = ($i === 5 || $i === 17) ? '-' : '+';
    if ($i === 40 || $i === 41) { $g = null; $rh = null; }              // belum periksa golongan (BR-13)
    $lahir = $anak ? sprintf('%d-%02d-%02d', mt_rand(2014, 2022), mt_rand(1, 12), mt_rand(1, 28))
                   : sprintf('%d-%02d-%02d', mt_rand(1950, 2003), mt_rand(1, 12), mt_rand(1, 28));
    $pasien[] = ['rm' => sprintf('RM-%06d', 100200 + $i * 7), 'nama' => $depan[$i % count($depan)] . ' ' . $belakang[mt_rand(0, count($belakang) - 1)],
                 'lahir' => $lahir, 'jk' => in_array($depan[$i % count($depan)], ['Citra','Dewi','Fitri','Hana','Kartika','Maya','Putri','Sinta','Umi','Vina','Yani','Bunga','Erna','Intan','Lestari','Wulan']) ? 'P' : 'L',
                 'gol' => $g, 'rh' => $rh, 'anak' => $anak];
}

// ---------------- Simulasi ----------------
$komp = ['WB' => 35, 'PRC' => 35, 'TC' => 5, 'FFP' => 365];
$volume = ['WB' => [350, 450], 'PRC' => [200, 300], 'TC' => [45, 60], 'FFP' => [150, 220]];
$kantong = [];        // no => [...]
$penerimaan = [];
$permintaan = [];
$uji = [];
$transfusi = [];
$musnah = [];
$kembali = [];
$no_seq = ['SKM' => 4100, 'MKS' => 7300, 'HRS' => 900];
$dok_seq = 0;

$terima = function (int $d, array $isi, int $u, bool $tc = false, ?int $mundur_tetap = null) use (&$kantong, &$penerimaan, &$no_seq, &$dok_seq, $udd, $komp, $volume, $gol_bobot, $pick, &$log, $waktu, $idk) {
    $dok_seq++;
    $mundur = $mundur_tetap ?? mt_rand(420, 660);
    $dok = sprintf('SJ/%s/%04d', $udd[$u][3], 300 + $dok_seq);
    $penerimaan[] = ['dok' => $dok, 'udd' => $udd[$u][0], 'd' => $d, 'm' => $mundur,
                     'suhu' => $tc ? number_format(mt_rand(210, 235) / 10, 1, '.', '') : number_format(mt_rand(30, 55) / 10, 1, '.', ''),
                     'petugas' => mt_rand(0, 3) ? 'petugas' : 'petugas2'];
    foreach ($isi as $kode => $n) {
        $daftar = is_array($n) ? $n : array_fill(0, $n, null);      // [[gol, rh], ...] atau jumlah acak
        foreach ($daftar as $gr) {
            [$g, $rh] = $gr ?? [$pick($gol_bobot), mt_rand(1, 100) <= 4 ? '-' : '+'];
            $umur = $kode === 'TC' ? 1 : ($kode === 'FFP' ? mt_rand(10, 60) : mt_rand(2, 9));
            $aftap = $d - $umur;
            $no = $udd[$u][3] . '26-' . sprintf('%05d', ++$no_seq[$udd[$u][3]]);
            $kantong[$no] = ['no' => $no, 'dok' => $dok, 'kode' => $kode, 'gol' => $g, 'rh' => $rh,
                             'vol' => mt_rand($volume[$kode][0], $volume[$kode][1]), 'aftap' => $aftap, 'ed' => $aftap + $komp[$kode],
                             'status' => 'TERSEDIA', 'pmt' => null, 'd_terima' => $d, 'm_terima' => $mundur];
            $log[] = [$waktu($d, $mundur - 5), $penerimaan[count($penerimaan) - 1]['petugas'], 'TERIMA', 'kantong_darah', $idk($no), "Kantong $no ($kode $g$rh) diterima → TERSEDIA, dokumen $dok"];
        }
    }
};

// stok awal (penerimaan sebelum periode simulasi)
$terima(-$HARI, ['PRC' => 30, 'WB' => 6, 'FFP' => 12], 0);
$terima(-$HARI + 1, ['TC' => 5], 2, true);

$diagnosis = [
    'PRC' => ['Anemia berat (data fiktif)', 'Perdarahan saluran cerna (data fiktif)', 'Persiapan operasi (data fiktif)', 'Perdarahan pascapersalinan (data fiktif)', 'Gagal ginjal kronik dengan anemia (data fiktif)', 'Trauma kecelakaan lalu lintas (data fiktif)'],
    'WB'  => ['Perdarahan masif akibat trauma (data fiktif)', 'Perdarahan intraoperatif (data fiktif)'],
    'TC'  => ['Trombositopenia (data fiktif)', 'Demam berdarah dengue (data fiktif)'],
    'FFP' => ['Gangguan pembekuan darah (data fiktif)', 'Gangguan fungsi hati (data fiktif)'],
];
$pmt_seq = [];

for ($d = -$HARI + 1; $d <= 0; $d++) {
    // --- penerimaan rutin ---
    // pemesanan mengikuti kekurangan stok per golongan (seperti BR-10)
    $kurang = function (string $kode, array $target) use (&$kantong, $d) {
        $isi = [];
        foreach ($target as $gr => $n) {
            [$g, $rh] = [substr($gr, 0, -1), substr($gr, -1)];
            $ada = count(array_filter($kantong, fn($k) => $k['kode'] === $kode && $k['gol'] === $g && $k['rh'] === $rh && $k['status'] === 'TERSEDIA' && $k['ed'] > $d + ($kode === 'TC' ? 1 : 4)));
            for ($i = $ada; $i < $n + mt_rand(0, 1); $i++) $isi[] = [$g, $rh];
        }
        return $isi;
    };
    if ($d % 3 === 0 && $d < 0)  $terima($d, ['PRC' => $kurang('PRC', ['O+' => 16, 'B+' => 13, 'A+' => 11, 'AB+' => 5, 'O-' => 2, 'B-' => 2, 'A-' => 2, 'AB-' => 1]), 'WB' => $kurang('WB', ['O+' => 2, 'B+' => 1, 'A+' => 1])], mt_rand(0, 1));
    if ($d % 4 === -1 && $d < -1) $terima($d, ['TC' => $kurang('TC', ['O+' => 1, 'B+' => 1])], 2, true);   // d negatif: -9 % 4 = -1
    if ($d % 15 === -5)           $terima($d, ['FFP' => $kurang('FFP', ['O+' => 4, 'B+' => 3, 'A+' => 3, 'AB+' => 2])], 0);

    // --- kedaluwarsa & pemusnahan (H+1 setelah lewat; 2 hari terakhir dibiarkan sebagai peringatan) ---
    foreach ($kantong as $no => &$k) {
        if ($k['status'] === 'TERSEDIA' && $k['ed'] < $d - 1 && $d <= -1) {
            $k['status'] = 'DIMUSNAHKAN';
            $musnah[] = ['no' => $no, 'd' => $d, 'alasan' => 'KEDALUWARSA', 'ket' => null];
        }
    }
    unset($k);
    if (in_array($d, [-64, -29, -11], true)) {                         // rusak / hemolisis
        foreach ($kantong as $no => &$k) {
            if ($k['status'] === 'TERSEDIA' && $k['kode'] === 'PRC' && $k['ed'] > $d + 5) {
                $k['status'] = 'DIMUSNAHKAN';
                $alasan = $d === -29 ? 'HEMOLISIS' : 'RUSAK';
                $musnah[] = ['no' => $no, 'd' => $d, 'alasan' => $alasan, 'ket' => $alasan === 'RUSAK' ? 'Kantong bocor saat pemeriksaan visual (data fiktif)' : 'Warna plasma kemerahan (data fiktif)'];
                break;
            }
        }
        unset($k);
    }
    if (in_array($d, [-45, -20], true)) {                             // pengembalian ke UDD
        foreach ($kantong as $no => &$k) {
            if ($k['status'] === 'TERSEDIA' && $k['kode'] === 'WB' && $k['ed'] > $d + 3) {
                $k['status'] = 'DIKEMBALIKAN';
                $kembali[] = ['no' => $no, 'd' => $d, 'alasan' => 'Kelebihan stok WB, dikembalikan sesuai kesepakatan dengan UDD (data fiktif)'];
                break;
            }
        }
        unset($k);
    }

    // --- permintaan harian ---
    $jml_pmt = $d === 0 ? 5 : mt_rand(1, 3);
    for ($r = 0; $r < $jml_pmt; $r++) {
        $kode = $pick(['PRC' => 62, 'TC' => 24, 'WB' => 7, 'FFP' => 7]);
        $ruang = $kode === 'TC' ? $pick(['Ruang Anak' => 5, 'Ruang Penyakit Dalam' => 4, 'ICU' => 2]) : $pick(['IGD' => 35, 'Ruang Bedah' => 25, 'Ruang Penyakit Dalam' => 20, 'ICU' => 15, 'Ruang Anak' => 5]);
        $kandidat = array_values(array_filter($pasien, fn($p) => $p['gol'] !== null && $p['anak'] === ($ruang === 'Ruang Anak')));
        $p = $kandidat[mt_rand(0, count($kandidat) - 1)];
        $tglk = $d;
        $pmt_seq[$d] = ($pmt_seq[$d] ?? 0) + 1;
        $no_pmt = ['d' => $d, 'n' => $pmt_seq[$d]];
        $mundur = $d === 0 ? mt_rand(130, 400) : mt_rand(330, 600);
        $jumlah = $kode === 'TC' ? mt_rand(2, 4) : mt_rand(1, 3);
        $cito = mt_rand(1, 100) <= ($ruang === 'IGD' ? 40 : 12);
        $dk = $dokter_ruang[$ruang][mt_rand(0, count($dokter_ruang[$ruang]) - 1)];
        $pm = ['key' => count($permintaan), 'no' => $no_pmt, 'rm' => $p['rm'], 'ruang' => $ruang, 'peminta' => $ruangan[$ruang],
               'dokter' => $dokter[$dk][0], 'kode' => $kode, 'jumlah' => $jumlah, 'cito' => $cito,
               'diag' => $diagnosis[$kode][mt_rand(0, count($diagnosis[$kode]) - 1)], 'd' => $d, 'm' => $mundur, 'status' => 'MENUNGGU'];

        // skenario hari ini: sebagian dibiarkan di tahap tertentu agar halaman kerja terisi
        $tahap = 'selesai';
        if ($d === 0) $tahap = ['menunggu', 'diproses', 'dipesan', 'keluar', 'berjalan'][$r];
        elseif ($d === -1 && $r === 0) $tahap = 'berjalan';
        if ($d < 0 && mt_rand(1, 100) <= 4) $tahap = 'batal';

        $terikat = [];
        if ($tahap !== 'menunggu') {
            // FEFO: kantong cocok komponen & golongan, tersedia, belum kedaluwarsa, sudah diterima sebelum permintaan
            $siap = fn($k) => $k['status'] === 'TERSEDIA' && $k['kode'] === $kode && $k['gol'] === $p['gol'] && $k['rh'] === $p['rh'] && $k['ed'] > $d
                              && ($k['d_terima'] < $d || ($k['d_terima'] === $d && $k['m_terima'] > $mundur + 20));
            $cocok = array_filter($kantong, $siap);
            // TC (masa simpan 5 hari) dipesan ke UDD sesuai permintaan bila stok kurang; tiba sebelum permintaan diproses
            if ($kode === 'TC' && $tahap !== 'batal' && count($cocok) < $jumlah) {
                $terima($d, ['TC' => array_fill(0, $jumlah - count($cocok), [$p['gol'], $p['rh']])], 2, true, $mundur + 90);
                $cocok = array_filter($kantong, $siap);
            }
            uasort($cocok, fn($a, $b) => [$a['ed'], $a['no']] <=> [$b['ed'], $b['no']]);
            $menit = $mundur - 20;
            foreach ($cocok as $no => $k) {
                if (count($terikat) >= $jumlah || $tahap === 'batal' && count($terikat) >= 1) break;
                $tidak = $tahap === 'diproses' || mt_rand(1, 100) <= 4;
                $uji[] = ['pmt' => $pm['key'], 'no' => $no, 'hasil' => $tidak ? 'TIDAK_COCOK' : 'COCOK', 'd' => $d, 'm' => $menit,
                          'catatan' => $tidak ? 'Terdapat aglutinasi pada uji silang mayor (data fiktif)' : null,
                          'petugas' => mt_rand(0, 3) ? 'petugas' : 'petugas2'];
                $menit -= 3;
                if ($tidak) { if ($tahap === 'diproses') break; continue; }
                $kantong[$no]['status'] = 'DIPESAN';
                $kantong[$no]['pmt'] = $pm['key'];
                $terikat[] = $no;
            }
        }
        if ($tahap === 'batal') {
            foreach ($terikat as $no) { $kantong[$no]['status'] = 'TERSEDIA'; $kantong[$no]['pmt'] = null; }
            $terikat = [];
            $pm['status'] = 'DIBATALKAN';
            $log[] = [$waktu($d, $mundur - 60), 'petugas', 'BATAL', 'permintaan_darah', 'PMT' . $pm['key'], 'Permintaan dibatalkan: pasien dirujuk ke RS lain (data fiktif)'];
        } elseif ($tahap === 'menunggu') {
            $pm['status'] = 'MENUNGGU';
        } else {
            $n = count($terikat);
            $pm['status'] = $n >= $jumlah ? 'TERPENUHI' : ($n > 0 ? 'TERPENUHI_SEBAGIAN' : 'DIPROSES');
            if ($n === 0 && $d < -1) { $pm['status'] = 'DIBATALKAN';
                $log[] = [$waktu($d, $mundur - 60), 'petugas', 'BATAL', 'permintaan_darah', 'PMT' . $pm['key'], 'Permintaan dibatalkan: stok golongan ini kosong, pasien dirujuk (data fiktif)']; }
            // penyerahan & transfusi
            if (in_array($tahap, ['keluar', 'berjalan', 'selesai'], true)) {
                foreach ($terikat as $i => $no) {
                    $k_out = $mundur - 45 - $i * 5;
                    $tr = ['no' => $no, 'pmt' => $pm['key'], 'rm' => $p['rm'], 'd' => $d, 'keluar' => $k_out, 'mulai' => null, 'selesai' => null,
                           'pencatat' => $ruangan[$ruang]];
                    $kantong[$no]['status'] = 'DIKELUARKAN';
                    if ($tahap !== 'keluar') { $tr['mulai'] = $k_out - 15; }
                    if ($tahap === 'selesai') { $tr['selesai'] = $k_out - 15 - mt_rand(150, 240); $kantong[$no]['status'] = 'DITRANSFUSIKAN'; }
                    if ($tahap === 'berjalan' && $i > 0) { $tr['mulai'] = null; }
                    $transfusi[] = $tr;
                }
            }
        }
        $permintaan[] = $pm;
    }
}

// ---------------- Tulis SQL ----------------
$no_pmt_sql = fn(array $n) => "CONCAT('PMT-', DATE_FORMAT(" . ($n['d'] === 0 ? 'CURDATE()' : 'CURDATE() - INTERVAL ' . (-$n['d']) . ' DAY') . ", '%y%m%d'), '-', '" . sprintf('%03d', $n['n']) . "')";
$idpm = fn(int $key) => "(SELECT id_permintaan FROM permintaan_darah WHERE no_permintaan = " . $no_pmt_sql($permintaan[$key]['no']) . ")";

$hdr = <<<SQL
-- =====================================================================
-- SIMBDRS — DATA DUMMY (SEMUA DATA FIKTIF, bukan data pasien sungguhan; UU 27/2022)
--
-- Cara pakai:
--   1. Impor database/simbdrs.sql dulu (struktur + akun contoh).
--   2. Impor file ini (phpMyAdmin → database simbdrs → Import, atau
--      C:\\xampp\\mysql\\bin\\mysql.exe -u root simbdrs < data_dummy.sql).
--   Jalankan SEKALI pada database yang transaksinya masih kosong. Kalau
--   dijalankan dua kali, impor berhenti karena no. kantong/No. RM sudah ada
--   dan seluruh isi file dibatalkan (satu transaksi).
--
-- Isi: simulasi {$HARI} hari operasional BDRS sampai HARI IMPOR. Semua tanggal
-- ditulis relatif (NOW()/CURDATE()), jadi data selalu tampak terbaru.
--   • 4 ruangan tambahan + akun perawatnya, 7 dokter, 3 UDD, 42 pasien
--   • penerimaan rutin, permintaan harian (FEFO, uji silang), transfusi,
--     pemusnahan, pengembalian, stok minimal, log aktivitas
--   • keadaan "hari ini": ada permintaan MENUNGGU, DIPROSES, kantong DIPESAN,
--     kantong di ruangan (DIKELUARKAN), dan kantong kedaluwarsa yang belum ditindak
--
-- Akun tambahan (HANYA untuk demo lokal):
--   icu / icu12345        bedah / bedah123       interna / interna123
--   anak / anak1234       petugas2 / petugas234
--
-- File ini DIBUAT OTOMATIS oleh skrip generator (mt_srand tetap) agar seluruh
-- angka konsisten dengan aturan bisnis BR-01 s.d. BR-14.
-- =====================================================================

SET NAMES utf8mb4;
START TRANSACTION;

SQL;
$out[] = $hdr;

$out[] = "-- ---------- Ruangan & akun ruangan ----------";
$out[] = "INSERT INTO ruangan (nama_ruangan) VALUES " . implode(', ', array_map(fn($r) => "(" . $q($r) . ")", array_slice(array_keys($ruangan), 1))) . ";";
$rows = [];
foreach ($akun_baru as [$nama, $user, $pw, $ru]) {
    $rows[] = "(" . $q($nama) . ", " . $q($user) . ", " . $q(password_hash($pw, PASSWORD_DEFAULT)) . ", " . $q($ru ? 'RUANGAN' : 'PETUGAS') . ", " . ($ru ? "(SELECT id_ruangan FROM ruangan r WHERE r.nama_ruangan = " . $q($ru) . ")" : 'NULL') . ")";
}
$out[] = "INSERT INTO pengguna (nama, username, password_hash, role, id_ruangan) VALUES\n" . implode(",\n", $rows) . ";\n";

$out[] = "-- ---------- Dokter, UDD, pasien ----------";
$out[] = "INSERT INTO dokter (nama_dokter, spesialis) VALUES\n" . implode(",\n", array_map(fn($x) => "(" . $q($x[0]) . ", " . $q($x[1]) . ")", $dokter)) . ";";
$out[] = "INSERT INTO udd_asal (nama_udd, alamat, telepon) VALUES\n" . implode(",\n", array_map(fn($x) => "(" . $q($x[0]) . ", " . $q($x[1]) . ", " . $q($x[2]) . ")", $udd)) . ";";
$out[] = "INSERT INTO pasien (no_rm, nama_pasien, tgl_lahir, jenis_kelamin, gol_darah, rhesus) VALUES\n" . implode(",\n", array_map(fn($p) => "(" . $q($p['rm']) . ", " . $q($p['nama']) . ", " . $q($p['lahir']) . ", " . $q($p['jk']) . ", " . $q($p['gol']) . ", " . $q($p['rh']) . ")", $pasien)) . ";\n";

$out[] = "-- ---------- Stok minimal (BR-09) — angka contoh kebijakan BDRS, bukan nilai medis ----------";
$smin = ['PRC' => ['O+' => 6, 'B+' => 5, 'A+' => 4, 'AB+' => 2, 'O-' => 1, 'B-' => 1, 'A-' => 1, 'AB-' => 1], 'WB' => ['O+' => 2]];   // TC tidak diberi stok minimal: dipesan sesuai permintaan
$rows = [];
foreach ($smin as $kode => $isi) foreach ($isi as $gr => $n) {
    $rows[] = "((SELECT id_komponen FROM komponen_darah WHERE kode = '$kode'), '" . substr($gr, 0, -1) . "', '" . substr($gr, -1) . "', $n)";
}
$out[] = "INSERT INTO stok_minimal (id_komponen, gol_darah, rhesus, jumlah_minimal) VALUES\n" . implode(",\n", $rows) . "\nON DUPLICATE KEY UPDATE jumlah_minimal = VALUES(jumlah_minimal);\n";

$out[] = "-- ---------- Penerimaan & kantong (BR-01: kedaluwarsa = aftap + masa simpan) ----------";
$rows = [];
foreach ($penerimaan as $p) {
    $rows[] = "(" . $q($p['dok']) . ", (SELECT id_udd FROM udd_asal WHERE nama_udd = " . $q($p['udd']) . "), " . $waktu($p['d'], $p['m']) . ", {$p['suhu']}, " . $idu($p['petugas']) . ")";
}
$out[] = "INSERT INTO penerimaan (no_dokumen, id_udd, tgl_terima, suhu_terima, id_petugas) VALUES\n" . implode(",\n", $rows) . ";";
$rows = [];
foreach ($kantong as $k) {
    $rows[] = "(" . $q($k['no']) . ", (SELECT id_penerimaan FROM penerimaan WHERE no_dokumen = " . $q($k['dok']) . "), (SELECT id_komponen FROM komponen_darah WHERE kode = '{$k['kode']}'), '{$k['gol']}', '{$k['rh']}', {$k['vol']}, " . $tgl($k['aftap']) . ", " . $tgl($k['ed']) . ", '{$k['status']}')";
}
foreach (array_chunk($rows, 200) as $c) {
    $out[] = "INSERT INTO kantong_darah (no_kantong, id_penerimaan, id_komponen, gol_darah, rhesus, volume_ml, tgl_aftap, tgl_kedaluwarsa, status) VALUES\n" . implode(",\n", $c) . ";";
}

$out[] = "\n-- ---------- Permintaan darah ----------";
$rows = [];
foreach ($permintaan as $p) {
    $rows[] = "(" . $no_pmt_sql($p['no']) . ", (SELECT id_pasien FROM pasien WHERE no_rm = " . $q($p['rm']) . "), (SELECT id_ruangan FROM ruangan WHERE nama_ruangan = " . $q($p['ruang']) . "), " . $idu($p['peminta']) . ", (SELECT id_dokter FROM dokter WHERE nama_dokter = " . $q($p['dokter']) . "), (SELECT id_komponen FROM komponen_darah WHERE kode = '{$p['kode']}'), {$p['jumlah']}, '" . ($p['cito'] ? 'CITO' : 'BIASA') . "', " . $q($p['diag']) . ", " . $waktu($p['d'], $p['m']) . ", '{$p['status']}')";
    $log[] = [$waktu($p['d'], $p['m']), $p['peminta'], 'TAMBAH', 'permintaan_darah', 'PMT' . $p['key'], "Permintaan {$p['jumlah']} kantong {$p['kode']} untuk {$p['rm']}" . ($p['cito'] ? ' (CITO)' : '')];
}
foreach (array_chunk($rows, 150) as $c) {
    $out[] = "INSERT INTO permintaan_darah (no_permintaan, id_pasien, id_ruangan, id_peminta, id_dokter, id_komponen, jumlah_kantong, urgensi, diagnosis, tgl_permintaan, status) VALUES\n" . implode(",\n", $c) . ";";
}
$out[] = "\n-- Kantong DIPESAN/DIKELUARKAN/DITRANSFUSIKAN terikat ke permintaannya (BR-07)";
foreach ($kantong as $k) {
    if ($k['pmt'] !== null && in_array($k['status'], ['DIPESAN', 'DIKELUARKAN', 'DITRANSFUSIKAN'], true)) {
        $out[] = "UPDATE kantong_darah SET id_permintaan_dipesan = " . $idpm($k['pmt']) . " WHERE no_kantong = " . $q($k['no']) . ";";
    }
}

$out[] = "\n-- ---------- Uji silang ----------";
$rows = [];
foreach ($uji as $u) {
    $rows[] = "(" . $idpm($u['pmt']) . ", " . $idk($u['no']) . ", '{$u['hasil']}', " . $waktu($u['d'], $u['m']) . ", " . $idu($u['petugas']) . ", " . $q($u['catatan']) . ")";
    $log[] = [$waktu($u['d'], $u['m']), $u['petugas'], 'UJI_SILANG', 'uji_silang', "(SELECT id_uji FROM uji_silang WHERE id_kantong = " . $idk($u['no']) . " AND id_permintaan = " . $idpm($u['pmt']) . ")", "Uji silang {$u['no']} untuk {$permintaan[$u['pmt']]['rm']}: {$u['hasil']}"];
    if ($u['hasil'] === 'COCOK') $log[] = [$waktu($u['d'], $u['m'] - 1), $u['petugas'], 'STATUS', 'kantong_darah', $idk($u['no']), "Kantong {$u['no']} TERSEDIA → DIPESAN untuk {$permintaan[$u['pmt']]['rm']}"];
}
foreach (array_chunk($rows, 150) as $c) {
    $out[] = "INSERT INTO uji_silang (id_permintaan, id_kantong, hasil, tgl_uji, id_petugas, catatan) VALUES\n" . implode(",\n", $c) . ";";
}

$out[] = "\n-- ---------- Transfusi (BR-06: satu kantong untuk satu pasien) ----------";
$rows = [];
foreach ($transfusi as $t) {
    $rows[] = "(" . $idk($t['no']) . ", " . $idpm($t['pmt']) . ", (SELECT id_pasien FROM pasien WHERE no_rm = " . $q($t['rm']) . "), " . $waktu($t['d'], $t['keluar']) . ", "
        . ($t['mulai'] === null ? 'NULL' : $waktu($t['d'], $t['mulai'])) . ", " . ($t['selesai'] === null ? 'NULL' : $waktu($t['d'], $t['selesai'])) . ", " . $idu($t['selesai'] || $t['mulai'] ? $t['pencatat'] : 'petugas') . ")";
    $log[] = [$waktu($t['d'], $t['keluar']), 'petugas', 'STATUS', 'kantong_darah', $idk($t['no']), "Kantong {$t['no']} DIPESAN → DIKELUARKAN ke {$permintaan[$t['pmt']]['ruang']}"];
    if ($t['mulai'] !== null) $log[] = [$waktu($t['d'], $t['mulai']), $t['pencatat'], 'MULAI', 'transfusi', "(SELECT id_transfusi FROM transfusi WHERE id_kantong = " . $idk($t['no']) . ")", "Transfusi {$t['no']} ke {$t['rm']} dimulai (identitas terverifikasi)"];
    if ($t['selesai'] !== null) $log[] = [$waktu($t['d'], $t['selesai']), $t['pencatat'], 'STATUS', 'kantong_darah', $idk($t['no']), "Kantong {$t['no']} DIKELUARKAN → DITRANSFUSIKAN ke {$t['rm']}"];
}
foreach (array_chunk($rows, 150) as $c) {
    $out[] = "INSERT INTO transfusi (id_kantong, id_permintaan, id_pasien, tgl_keluar_bdrs, tgl_mulai, tgl_selesai, id_pencatat) VALUES\n" . implode(",\n", $c) . ";";
}

$out[] = "\n-- ---------- Pemusnahan & pengembalian ----------";
$rows = [];
foreach ($musnah as $m) {
    $mn = mt_rand(60, 300);
    $rows[] = "(" . $idk($m['no']) . ", " . $waktu($m['d'], $mn) . ", '{$m['alasan']}', " . $q($m['ket']) . ", " . $idu('petugas') . ")";
    $log[] = [$waktu($m['d'], $mn), 'petugas', 'STATUS', 'kantong_darah', $idk($m['no']), "Kantong {$m['no']} TERSEDIA → DIMUSNAHKAN ({$m['alasan']})"];
}
$out[] = "INSERT INTO pemusnahan (id_kantong, tgl_musnah, alasan, keterangan, id_petugas) VALUES\n" . implode(",\n", $rows) . ";";
$rows = [];
foreach ($kembali as $b) {
    $rows[] = "(" . $idk($b['no']) . ", " . $waktu($b['d'], 200) . ", " . $q($b['alasan']) . ", " . $idu('petugas') . ")";
    $log[] = [$waktu($b['d'], 200), 'petugas', 'STATUS', 'kantong_darah', $idk($b['no']), "Kantong {$b['no']} TERSEDIA → DIKEMBALIKAN"];
}
$out[] = "INSERT INTO pengembalian (id_kantong, tgl_kembali, alasan, id_petugas) VALUES\n" . implode(",\n", $rows) . ";";

$out[] = "\n-- ---------- Log aktivitas (BR-12) ----------";
$rows = [];
foreach ($log as [$w, $u, $aksi, $tabel, $idx, $ket]) {
    if (str_starts_with($idx, 'PMT')) $idx = $idpm((int) substr($idx, 3));
    $rows[] = "(" . $idu($u) . ", '$aksi', '$tabel', $idx, " . $q($ket) . ", $w)";
}
foreach (array_chunk($rows, 200) as $c) {
    $out[] = "INSERT INTO log_aktivitas (id_pengguna, aksi, nama_tabel, id_data, keterangan, waktu) VALUES\n" . implode(",\n", $c) . ";";
}
$out[] = "\nCOMMIT;\n";

file_put_contents($argv[1], implode("\n", $out));
$st = array_count_values(array_column($kantong, 'status'));
$sp = array_count_values(array_column($permintaan, 'status'));
echo "kantong " . count($kantong) . " " . json_encode($st) . "\npermintaan " . count($permintaan) . " " . json_encode($sp) .
     "\nuji " . count($uji) . " transfusi " . count($transfusi) . " musnah " . count($musnah) . " kembali " . count($kembali) . " log " . count($log) . "\n";

