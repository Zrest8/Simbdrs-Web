<?php
// =====================================================================
// Master UDD Asal — TAMBAH
// Tabel: udd_asal (INSERT), log_aktivitas (INSERT)
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
require __DIR__ . '/_validasi.php';
wajib_hak('udd', 'tambah');

$judul = 'Tambah UDD Asal';
$menu_aktif = 'udd';

$u = ['nama_udd' => '', 'alamat' => '', 'telepon' => ''];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $u = baca_form_udd();
    $error = validasi_udd($u);

    if ($error === '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO udd_asal (nama_udd, alamat, telepon) VALUES (?, ?, ?)");
            $stmt->execute(nilai_udd($u));
            catat_log($pdo, 'TAMBAH', 'udd_asal', (int) $pdo->lastInsertId(), "Tambah UDD: {$u['nama_udd']}");
            set_flash('success', "UDD \"{$u['nama_udd']}\" berhasil ditambahkan.");
            header('Location: index.php');
            exit;
        } catch (PDOException $ex) {
            // 23000 = pelanggaran UNIQUE nama_udd
            if ($ex->getCode() === '23000') {
                $error = "UDD \"{$u['nama_udd']}\" sudah ada.";
            } else {
                throw $ex;
            }
        }
    }
}

require __DIR__ . '/../../../includes/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../../includes/footer.php';
