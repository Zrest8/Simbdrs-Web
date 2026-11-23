<?php
// =====================================================================
// Master Dokter — TAMBAH (dokter + akun loginnya sekaligus)
// Tabel: pengguna (INSERT role DOKTER), dokter (INSERT), log_aktivitas (INSERT)
//
// Satu dokter = satu akun (dokter.id_pengguna NOT NULL UNIQUE). Keduanya
// disimpan dalam SATU transaksi: kalau salah satu gagal, dua-duanya batal.
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
require __DIR__ . '/../pengguna/_validasi.php';   // aturan username & password yang sama
wajib_hak('dokter', 'tambah');

$judul = 'Tambah Dokter';
$menu_aktif = 'dokter';

$f = ['nama' => '', 'spesialis' => '', 'username' => ''];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $akun = baca_form_pengguna();          // nama, username, password, password2
    $akun['role'] = 'DOKTER';
    $f = ['nama' => $akun['nama'], 'spesialis' => trim($_POST['spesialis'] ?? ''), 'username' => $akun['username']];

    $error = validasi_pengguna($akun, true, ['DOKTER']);
    if ($error === '' && mb_strlen($f['spesialis']) > 100) {
        $error = 'Spesialis maksimal 100 karakter.';
    }
    if ($error === '') {
        $cek = $pdo->prepare("SELECT COUNT(*) FROM pengguna WHERE username = ?");
        $cek->execute([$akun['username']]);
        if ($cek->fetchColumn() > 0) {
            $error = "Username \"{$akun['username']}\" sudah dipakai.";
        }
    }

    if ($error === '') {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO pengguna (nama, username, password_hash, role) VALUES (?, ?, ?, 'DOKTER')")
            ->execute([$akun['nama'], $akun['username'], password_hash($akun['password'], PASSWORD_DEFAULT)]);
        $id_pengguna = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO dokter (id_pengguna, spesialis) VALUES (?, ?)")
            ->execute([$id_pengguna, $f['spesialis'] !== '' ? $f['spesialis'] : null]);
        $id_dokter = (int) $pdo->lastInsertId();
        catat_log($pdo, 'TAMBAH', 'dokter', $id_dokter, "Tambah dokter: {$akun['nama']} (akun {$akun['username']})");
        $pdo->commit();

        set_flash('success', "Dokter \"{$akun['nama']}\" ditambahkan. Ia bisa login dengan username \"{$akun['username']}\".");
        header('Location: index.php');
        exit;
    }
}

require __DIR__ . '/../../../includes/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../../includes/footer.php';
