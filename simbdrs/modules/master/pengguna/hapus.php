<?php
// =====================================================================
// Master Pengguna — HAPUS
// Tabel: pengguna (DELETE atau UPDATE aktif = 0), log_aktivitas (INSERT)
//
// Akun yang sudah pernah login / mencatat transaksi direferensikan oleh
// log_aktivitas dan tabel transaksi → foreign key menolak DELETE, sehingga
// akun cukup dinonaktifkan (tidak bisa login, jejak audit tetap utuh).
// Admin tidak bisa menghapus akunnya sendiri, dan admin aktif terakhir dilindungi.
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
$saya = wajib_hak('pengguna', 'hapus');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {   // hapus hanya lewat tombol (POST)
    header('Location: index.php');
    exit;
}
cek_csrf();

$id = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT username, role, aktif FROM pengguna WHERE id_pengguna = ?");
$stmt->execute([$id]);
$akun = $stmt->fetch();

if (!$akun) {
    set_flash('danger', 'Data pengguna tidak ditemukan.');
} elseif ($id === (int) $saya['id_pengguna']) {
    set_flash('danger', 'Anda tidak bisa menghapus akun Anda sendiri.');
} elseif ($akun['role'] === 'DOKTER') {
    // Akun dokter terikat ke data dokter (1:1); hapus lewat Master Dokter agar keduanya ikut terhapus
    set_flash('warning', "Akun \"{$akun['username']}\" milik dokter. Hapus atau nonaktifkan lewat menu Dokter.");
} else {
    $nama = $akun['username'];

    // Lindungi admin aktif terakhir
    $admin_lain = 1;
    if ($akun['role'] === 'ADMIN' && (int) $akun['aktif'] === 1) {
        $cek = $pdo->prepare("SELECT COUNT(*) FROM pengguna WHERE role = 'ADMIN' AND aktif = 1 AND id_pengguna <> ?");
        $cek->execute([$id]);
        $admin_lain = (int) $cek->fetchColumn();
    }

    if ($admin_lain === 0) {
        set_flash('danger', "Akun \"$nama\" adalah satu-satunya admin aktif dan tidak bisa dihapus.");
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM pengguna WHERE id_pengguna = ?");
            $stmt->execute([$id]);
            catat_log($pdo, 'HAPUS', 'pengguna', $id, "Hapus akun: $nama");
            set_flash('success', "Akun \"$nama\" dihapus.");
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') {
                throw $ex;
            }
            $stmt = $pdo->prepare("UPDATE pengguna SET aktif = 0 WHERE id_pengguna = ?");
            $stmt->execute([$id]);
            catat_log($pdo, 'NONAKTIF', 'pengguna', $id, "Nonaktifkan akun (punya jejak aktivitas): $nama");
            set_flash('warning', "Akun \"$nama\" punya jejak aktivitas, jadi dinonaktifkan (tidak bisa login lagi).");
        }
    }
}

header('Location: index.php');
exit;
