<?php
// =====================================================================
// Master Dokter — EDIT
// Tabel: dokter (SELECT, UPDATE), log_aktivitas (INSERT)
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_login(['ADMIN']);

$judul = 'Edit Dokter';
$menu_aktif = 'dokter';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM dokter WHERE id_dokter = ?");
$stmt->execute([$id]);
$dokter = $stmt->fetch();

if (!$dokter) {
    set_flash('danger', 'Data dokter tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$nama      = $dokter['nama_dokter'];
$spesialis = $dokter['spesialis'] ?? '';
$aktif     = (int) $dokter['aktif'];
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $nama      = trim($_POST['nama_dokter'] ?? '');
    $spesialis = trim($_POST['spesialis'] ?? '');
    $aktif     = isset($_POST['aktif']) ? 1 : 0;

    if ($nama === '') {
        $error = 'Nama dokter wajib diisi.';
    } elseif (mb_strlen($nama) > 100 || mb_strlen($spesialis) > 100) {
        $error = 'Nama dan spesialis maksimal 100 karakter.';
    } else {
        // Duplikat = dokter LAIN dengan nama + spesialis yang sama
        $cek = $pdo->prepare("SELECT COUNT(*) FROM dokter WHERE nama_dokter = ? AND COALESCE(spesialis, '') = ? AND id_dokter <> ?");
        $cek->execute([$nama, $spesialis, $id]);
        if ($cek->fetchColumn() > 0) {
            $error = "Dokter \"$nama\" dengan spesialis yang sama sudah ada.";
        } else {
            $stmt = $pdo->prepare("UPDATE dokter SET nama_dokter = ?, spesialis = ?, aktif = ? WHERE id_dokter = ?");
            $stmt->execute([$nama, $spesialis !== '' ? $spesialis : null, $aktif, $id]);
            catat_log($pdo, 'UBAH', 'dokter', $id,
                "Ubah dokter: {$dokter['nama_dokter']} → $nama (" . ($aktif ? 'aktif' : 'nonaktif') . ')');
            set_flash('success', "Dokter \"$nama\" berhasil diperbarui.");
            header('Location: index.php');
            exit;
        }
    }
}

$mode_edit = true;
require __DIR__ . '/../../../includes/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../../includes/footer.php';
