<?php
// =====================================================================
// Master Dokter — TAMBAH
// Tabel: dokter (SELECT cek duplikat, INSERT), log_aktivitas (INSERT)
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_login(['ADMIN']);

$judul = 'Tambah Dokter';
$menu_aktif = 'dokter';

$nama = '';
$spesialis = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $nama      = trim($_POST['nama_dokter'] ?? '');
    $spesialis = trim($_POST['spesialis'] ?? '');

    if ($nama === '') {
        $error = 'Nama dokter wajib diisi.';
    } elseif (mb_strlen($nama) > 100 || mb_strlen($spesialis) > 100) {
        $error = 'Nama dan spesialis maksimal 100 karakter.';
    } else {
        // Kolom nama_dokter tidak UNIQUE (dua dokter bisa bernama sama),
        // jadi duplikat dicek di sini: nama + spesialis yang persis sama ditolak.
        $cek = $pdo->prepare("SELECT COUNT(*) FROM dokter WHERE nama_dokter = ? AND COALESCE(spesialis, '') = ?");
        $cek->execute([$nama, $spesialis]);
        if ($cek->fetchColumn() > 0) {
            $error = "Dokter \"$nama\" dengan spesialis yang sama sudah ada.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO dokter (nama_dokter, spesialis) VALUES (?, ?)");
            $stmt->execute([$nama, $spesialis !== '' ? $spesialis : null]);
            catat_log($pdo, 'TAMBAH', 'dokter', (int) $pdo->lastInsertId(), "Tambah dokter: $nama");
            set_flash('success', "Dokter \"$nama\" berhasil ditambahkan.");
            header('Location: index.php');
            exit;
        }
    }
}

require __DIR__ . '/../../../includes/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../../includes/footer.php';
