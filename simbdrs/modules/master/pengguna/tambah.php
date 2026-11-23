<?php
// =====================================================================
// Master Pengguna — TAMBAH
// Tabel: ruangan (SELECT), pengguna (INSERT), log_aktivitas (INSERT)
// Password disimpan sebagai hash (password_hash), tidak pernah teks asli.
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
require __DIR__ . '/_validasi.php';
wajib_hak('pengguna', 'tambah');

$judul = 'Tambah Pengguna';
$menu_aktif = 'pengguna';

$p = ['nama' => '', 'username' => '', 'role' => '', 'id_ruangan' => 0, 'password' => '', 'password2' => ''];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $p = baca_form_pengguna();
    $error = validasi_pengguna($p, true);

    if ($error === '' && $p['role'] === 'PERAWAT' && !ruangan_boleh($pdo, $p['id_ruangan'])) {
        $error = 'Ruangan yang dipilih tidak ditemukan atau sudah nonaktif.';
    }

    if ($error === '') {
        // id_ruangan hanya diisi untuk peran PERAWAT; peran lain NULL
        $id_ruangan = $p['role'] === 'PERAWAT' ? $p['id_ruangan'] : null;
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO pengguna (nama, username, password_hash, role, id_ruangan) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$p['nama'], $p['username'], password_hash($p['password'], PASSWORD_DEFAULT), $p['role'], $id_ruangan]);
            catat_log($pdo, 'TAMBAH', 'pengguna', (int) $pdo->lastInsertId(),
                "Tambah akun: {$p['username']} (" . nama_role($p['role']) . ')');
            set_flash('success', "Akun \"{$p['username']}\" berhasil dibuat.");
            header('Location: index.php');
            exit;
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $error = "Username \"{$p['username']}\" sudah dipakai.";
            } else {
                throw $ex;
            }
        }
    }
    $p['password'] = $p['password2'] = '';   // jangan tampilkan ulang password
}

$ruangan = daftar_ruangan($pdo);

require __DIR__ . '/../../../includes/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../../includes/footer.php';
