<?php
// =====================================================================
// Master Komponen Darah — TAMBAH
// Tabel: komponen_darah (INSERT), log_aktivitas (INSERT)
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
require __DIR__ . '/_validasi.php';
wajib_hak('komponen', 'tambah');

$judul = 'Tambah Komponen Darah';
$menu_aktif = 'komponen';

$k = ['kode' => '', 'nama_komponen' => '', 'suhu_min' => '', 'suhu_max' => '', 'masa_simpan_hari' => '', 'batas_peringatan_hari' => ''];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $k = baca_form_komponen();
    $error = validasi_komponen($k);

    if ($error === '') {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO komponen_darah (kode, nama_komponen, suhu_min, suhu_max, masa_simpan_hari, batas_peringatan_hari)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute(nilai_komponen($k));
            catat_log($pdo, 'TAMBAH', 'komponen_darah', (int) $pdo->lastInsertId(),
                "Tambah komponen: {$k['kode']} (masa simpan {$k['masa_simpan_hari']} hari, peringatan {$k['batas_peringatan_hari']} hari)");
            set_flash('success', "Komponen \"{$k['kode']}\" berhasil ditambahkan.");
            header('Location: index.php');
            exit;
        } catch (PDOException $ex) {
            // 23000 = pelanggaran UNIQUE kode
            if ($ex->getCode() === '23000') {
                $error = "Kode \"{$k['kode']}\" sudah dipakai komponen lain.";
            } else {
                throw $ex;
            }
        }
    }
}

require __DIR__ . '/../../../includes/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../../includes/footer.php';
