<?php
// =====================================================================
// Master Dokter — EDIT (data dokter + akun loginnya)
// Tabel: dokter + pengguna (SELECT, UPDATE), log_aktivitas (INSERT)
// Nama & status aktif disimpan di tabel pengguna; spesialis di tabel dokter.
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
require __DIR__ . '/../pengguna/_validasi.php';
wajib_hak('dokter', 'ubah');

$judul = 'Edit Dokter';
$menu_aktif = 'dokter';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT d.id_dokter, d.id_pengguna, d.spesialis, u.nama, u.username, u.aktif
     FROM dokter d JOIN pengguna u ON u.id_pengguna = d.id_pengguna
     WHERE d.id_dokter = ?"
);
$stmt->execute([$id]);
$lama = $stmt->fetch();

if (!$lama) {
    set_flash('danger', 'Data dokter tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$f     = ['nama' => $lama['nama'], 'spesialis' => $lama['spesialis'] ?? '', 'username' => $lama['username']];
$aktif = (int) $lama['aktif'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $akun = baca_form_pengguna();
    $akun['role'] = 'DOKTER';
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    $f = ['nama' => $akun['nama'], 'spesialis' => trim($_POST['spesialis'] ?? ''), 'username' => $akun['username']];

    $error = validasi_pengguna($akun, false, ['DOKTER']);   // password kosong = tidak diganti
    if ($error === '' && mb_strlen($f['spesialis']) > 100) {
        $error = 'Spesialis maksimal 100 karakter.';
    }
    if ($error === '') {
        $cek = $pdo->prepare("SELECT COUNT(*) FROM pengguna WHERE username = ? AND id_pengguna <> ?");
        $cek->execute([$akun['username'], $lama['id_pengguna']]);
        if ($cek->fetchColumn() > 0) {
            $error = "Username \"{$akun['username']}\" sudah dipakai akun lain.";
        }
    }

    if ($error === '') {
        $pdo->beginTransaction();
        if ($akun['password'] !== '') {
            $pdo->prepare("UPDATE pengguna SET nama = ?, username = ?, aktif = ?, password_hash = ? WHERE id_pengguna = ?")
                ->execute([$akun['nama'], $akun['username'], $aktif, password_hash($akun['password'], PASSWORD_DEFAULT), $lama['id_pengguna']]);
        } else {
            $pdo->prepare("UPDATE pengguna SET nama = ?, username = ?, aktif = ? WHERE id_pengguna = ?")
                ->execute([$akun['nama'], $akun['username'], $aktif, $lama['id_pengguna']]);
        }
        $pdo->prepare("UPDATE dokter SET spesialis = ? WHERE id_dokter = ?")
            ->execute([$f['spesialis'] !== '' ? $f['spesialis'] : null, $id]);
        catat_log($pdo, 'UBAH', 'dokter', $id,
            "Ubah dokter: {$lama['nama']} → {$akun['nama']} (" . ($aktif ? 'aktif' : 'nonaktif') . ')'
            . ($akun['password'] !== '' ? '; password diganti' : ''));
        $pdo->commit();

        set_flash('success', "Dokter \"{$akun['nama']}\" berhasil diperbarui.");
        header('Location: index.php');
        exit;
    }
}

$mode_edit = true;
require __DIR__ . '/../../../includes/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../../includes/footer.php';
