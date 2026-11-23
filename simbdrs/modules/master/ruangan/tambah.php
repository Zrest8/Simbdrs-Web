<?php
// =====================================================================
// Master Ruangan — TAMBAH
// Tabel: ruangan (INSERT), log_aktivitas (INSERT)
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_hak('ruangan', 'tambah');

$judul = 'Tambah Ruangan';
$menu_aktif = 'ruangan';

$nama = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $nama = trim($_POST['nama_ruangan'] ?? '');

    // Validasi input
    if ($nama === '') {
        $error = 'Nama ruangan wajib diisi.';
    } elseif (mb_strlen($nama) > 100) {
        $error = 'Nama ruangan maksimal 100 karakter.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO ruangan (nama_ruangan) VALUES (?)");
            $stmt->execute([$nama]);
            catat_log($pdo, 'TAMBAH', 'ruangan', (int) $pdo->lastInsertId(), "Tambah ruangan: $nama");
            set_flash('success', "Ruangan \"$nama\" berhasil ditambahkan.");
            header('Location: index.php');
            exit;
        } catch (PDOException $ex) {
            // 23000 = pelanggaran constraint, di sini UNIQUE nama_ruangan
            if ($ex->getCode() === '23000') {
                $error = "Ruangan \"$nama\" sudah ada.";
            } else {
                throw $ex;
            }
        }
    }
}

require __DIR__ . '/../../../includes/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../../includes/footer.php';
