<?php
// =====================================================================
// Master Pasien — TAMBAH
// Tabel: pasien (INSERT), log_aktivitas (INSERT)
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
require __DIR__ . '/_validasi.php';
wajib_login(['ADMIN']);

$judul = 'Tambah Pasien';
$menu_aktif = 'pasien';

$p = ['no_rm' => '', 'nama_pasien' => '', 'tgl_lahir' => '', 'jenis_kelamin' => '', 'gol_darah' => '', 'rhesus' => ''];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $p = baca_form_pasien();
    $error = validasi_pasien($p);

    if ($error === '') {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO pasien (no_rm, nama_pasien, tgl_lahir, jenis_kelamin, gol_darah, rhesus)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $p['no_rm'], $p['nama_pasien'], $p['tgl_lahir'], $p['jenis_kelamin'],
                null_jika_kosong($p['gol_darah']), null_jika_kosong($p['rhesus']),
            ]);
            catat_log($pdo, 'TAMBAH', 'pasien', (int) $pdo->lastInsertId(), "Tambah pasien: {$p['no_rm']} - {$p['nama_pasien']}");
            set_flash('success', "Pasien \"{$p['nama_pasien']}\" ({$p['no_rm']}) berhasil ditambahkan.");
            header('Location: index.php');
            exit;
        } catch (PDOException $ex) {
            // 23000 = pelanggaran UNIQUE no_rm
            if ($ex->getCode() === '23000') {
                $error = "No. RM \"{$p['no_rm']}\" sudah dipakai pasien lain.";
            } else {
                throw $ex;
            }
        }
    }
}

require __DIR__ . '/../../../includes/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../../includes/footer.php';
