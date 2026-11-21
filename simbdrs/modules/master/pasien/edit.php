<?php
// =====================================================================
// Master Pasien — EDIT
// Tabel: pasien (SELECT, UPDATE), log_aktivitas (INSERT)
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
require __DIR__ . '/_validasi.php';
wajib_login(['ADMIN']);

$judul = 'Edit Pasien';
$menu_aktif = 'pasien';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM pasien WHERE id_pasien = ?");
$stmt->execute([$id]);
$lama = $stmt->fetch();

if (!$lama) {
    set_flash('danger', 'Data pasien tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$p = [
    'no_rm'         => $lama['no_rm'],
    'nama_pasien'   => $lama['nama_pasien'],
    'tgl_lahir'     => $lama['tgl_lahir'],
    'jenis_kelamin' => $lama['jenis_kelamin'],
    'gol_darah'     => $lama['gol_darah'] ?? '',
    'rhesus'        => $lama['rhesus'] ?? '',
];
$aktif = (int) $lama['aktif'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $p = baca_form_pasien();
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    $error = validasi_pasien($p);

    if ($error === '') {
        try {
            $stmt = $pdo->prepare(
                "UPDATE pasien SET no_rm = ?, nama_pasien = ?, tgl_lahir = ?, jenis_kelamin = ?,
                                   gol_darah = ?, rhesus = ?, aktif = ?
                 WHERE id_pasien = ?"
            );
            $stmt->execute([
                $p['no_rm'], $p['nama_pasien'], $p['tgl_lahir'], $p['jenis_kelamin'],
                null_jika_kosong($p['gol_darah']), null_jika_kosong($p['rhesus']), $aktif, $id,
            ]);

            // Catat perubahan golongan darah secara khusus: data ini kritis untuk transfusi
            $gol_lama = ($lama['gol_darah'] ?? '-') . ($lama['rhesus'] ?? '');
            $gol_baru = ($p['gol_darah'] ?: '-') . $p['rhesus'];
            $ket = "Ubah pasien: {$p['no_rm']} - {$p['nama_pasien']} (" . ($aktif ? 'aktif' : 'nonaktif') . ')';
            if ($gol_lama !== $gol_baru) {
                $ket .= "; golongan darah $gol_lama → $gol_baru";
            }
            catat_log($pdo, 'UBAH', 'pasien', $id, $ket);

            set_flash('success', "Pasien \"{$p['nama_pasien']}\" berhasil diperbarui.");
            header('Location: index.php');
            exit;
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $error = "No. RM \"{$p['no_rm']}\" sudah dipakai pasien lain.";
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
