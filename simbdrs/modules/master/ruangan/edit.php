<?php
// =====================================================================
// Master Ruangan — EDIT
// Tabel: ruangan (SELECT, UPDATE), log_aktivitas (INSERT)
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_login(['ADMIN']);

$judul = 'Edit Ruangan';
$menu_aktif = 'ruangan';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM ruangan WHERE id_ruangan = ?");
$stmt->execute([$id]);
$ruangan = $stmt->fetch();

if (!$ruangan) {
    set_flash('danger', 'Data ruangan tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$nama  = $ruangan['nama_ruangan'];
$aktif = (int) $ruangan['aktif'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $nama  = trim($_POST['nama_ruangan'] ?? '');
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    if ($nama === '') {
        $error = 'Nama ruangan wajib diisi.';
    } elseif (mb_strlen($nama) > 100) {
        $error = 'Nama ruangan maksimal 100 karakter.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE ruangan SET nama_ruangan = ?, aktif = ? WHERE id_ruangan = ?");
            $stmt->execute([$nama, $aktif, $id]);
            catat_log($pdo, 'UBAH', 'ruangan', $id,
                "Ubah ruangan: {$ruangan['nama_ruangan']} → $nama (" . ($aktif ? 'aktif' : 'nonaktif') . ')');
            set_flash('success', "Ruangan \"$nama\" berhasil diperbarui.");
            header('Location: index.php');
            exit;
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $error = "Ruangan \"$nama\" sudah ada.";
            } else {
                throw $ex;
            }
        }
    }
}

$mode_edit = true;
require __DIR__ . '/../../../includes/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../../includes/footer.php';
