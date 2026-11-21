<?php
// =====================================================================
// Master UDD Asal — EDIT
// Tabel: udd_asal (SELECT, UPDATE), log_aktivitas (INSERT)
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
require __DIR__ . '/_validasi.php';
wajib_login(['ADMIN']);

$judul = 'Edit UDD Asal';
$menu_aktif = 'udd';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM udd_asal WHERE id_udd = ?");
$stmt->execute([$id]);
$lama = $stmt->fetch();

if (!$lama) {
    set_flash('danger', 'Data UDD tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$u = [
    'nama_udd' => $lama['nama_udd'],
    'alamat'   => $lama['alamat'] ?? '',
    'telepon'  => $lama['telepon'] ?? '',
];
$aktif = (int) $lama['aktif'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $u = baca_form_udd();
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    $error = validasi_udd($u);

    if ($error === '') {
        try {
            $stmt = $pdo->prepare("UPDATE udd_asal SET nama_udd = ?, alamat = ?, telepon = ?, aktif = ? WHERE id_udd = ?");
            $stmt->execute([...nilai_udd($u), $aktif, $id]);
            catat_log($pdo, 'UBAH', 'udd_asal', $id,
                "Ubah UDD: {$lama['nama_udd']} → {$u['nama_udd']} (" . ($aktif ? 'aktif' : 'nonaktif') . ')');
            set_flash('success', "UDD \"{$u['nama_udd']}\" berhasil diperbarui.");
            header('Location: index.php');
            exit;
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $error = "UDD \"{$u['nama_udd']}\" sudah ada.";
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
