<?php
// =====================================================================
// Validasi & aturan bersama untuk tambah.php dan edit.php.
// =====================================================================

const PERAN_VALID = ['ADMIN', 'PETUGAS', 'RUANGAN', 'KEPALA'];
const PANJANG_PASSWORD_MIN = 8;

function baca_form_pengguna(): array
{
    return [
        'nama'       => trim($_POST['nama'] ?? ''),
        'username'   => strtolower(trim($_POST['username'] ?? '')),
        'role'       => $_POST['role'] ?? '',
        'id_ruangan' => (int) ($_POST['id_ruangan'] ?? 0),
        'password'   => $_POST['password'] ?? '',     // tidak di-trim: spasi boleh jadi bagian password
        'password2'  => $_POST['password2'] ?? '',
    ];
}

/**
 * $wajib_password: true saat tambah; saat edit password kosong = tidak diubah.
 * Mengembalikan pesan error ('' jika valid). Pengecekan yang butuh database
 * (ruangan aktif, username kembar, sisa admin) dilakukan di tambah/edit.
 */
function validasi_pengguna(array $p, bool $wajib_password): string
{
    if ($p['nama'] === '' || $p['username'] === '' || $p['role'] === '') {
        return 'Nama, username, dan peran wajib diisi.';
    }
    if (mb_strlen($p['nama']) > 100) {
        return 'Nama maksimal 100 karakter.';
    }
    if (!preg_match('/^[a-z0-9_.]{3,50}$/', $p['username'])) {
        return 'Username 3–50 karakter, hanya huruf kecil, angka, titik, dan garis bawah.';
    }
    if (!in_array($p['role'], PERAN_VALID, true)) {
        return 'Peran tidak valid.';
    }
    if ($p['role'] === 'RUANGAN' && $p['id_ruangan'] <= 0) {
        return 'Akun dengan peran Ruangan wajib dipilihkan ruangannya.';
    }

    if ($wajib_password || $p['password'] !== '') {
        if (mb_strlen($p['password']) < PANJANG_PASSWORD_MIN) {
            return 'Password minimal ' . PANJANG_PASSWORD_MIN . ' karakter.';
        }
        if (mb_strlen($p['password']) > 72) {
            return 'Password maksimal 72 karakter.';   // batas algoritma bcrypt
        }
        if ($p['password'] !== $p['password2']) {
            return 'Konfirmasi password tidak sama.';
        }
    }
    return '';
}

// Ruangan yang bisa dipilih (aktif), ditambah ruangan yang sedang dipakai akun ini
function daftar_ruangan(PDO $pdo, int $id_sekarang = 0): array
{
    $stmt = $pdo->prepare("SELECT id_ruangan, nama_ruangan, aktif FROM ruangan WHERE aktif = 1 OR id_ruangan = ? ORDER BY nama_ruangan");
    $stmt->execute([$id_sekarang]);
    return $stmt->fetchAll();
}

function ruangan_boleh(PDO $pdo, int $id_ruangan, int $id_sekarang = 0): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ruangan WHERE id_ruangan = ? AND (aktif = 1 OR id_ruangan = ?)");
    $stmt->execute([$id_ruangan, $id_sekarang]);
    return (int) $stmt->fetchColumn() > 0;
}
