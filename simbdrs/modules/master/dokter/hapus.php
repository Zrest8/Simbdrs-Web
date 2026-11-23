<?php
// =====================================================================
// Master Dokter — HAPUS (data dokter + akun loginnya)
// Tabel: dokter & pengguna (DELETE, atau UPDATE pengguna.aktif = 0), log_aktivitas (INSERT)
//
// Jika dokter sudah dipakai di permintaan_darah (fk_permintaan_dokter) atau
// akunnya punya jejak aktivitas, DELETE ditolak database → cukup dinonaktifkan
// (akun tidak bisa login, dokter tidak bisa dipilih lagi, riwayat tetap utuh).
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
$saya = wajib_hak('dokter', 'hapus');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {   // hapus hanya lewat tombol (POST)
    header('Location: index.php');
    exit;
}
cek_csrf();

$id = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT d.id_pengguna, u.nama FROM dokter d JOIN pengguna u ON u.id_pengguna = d.id_pengguna WHERE d.id_dokter = ?");
$stmt->execute([$id]);
$dokter = $stmt->fetch();

if (!$dokter) {
    set_flash('danger', 'Data dokter tidak ditemukan.');
} elseif ((int) $dokter['id_pengguna'] === (int) $saya['id_pengguna']) {
    set_flash('danger', 'Anda tidak bisa menghapus akun Anda sendiri.');
} else {
    $nama = $dokter['nama'];
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM dokter WHERE id_dokter = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM pengguna WHERE id_pengguna = ?")->execute([$dokter['id_pengguna']]);
        catat_log($pdo, 'HAPUS', 'dokter', $id, "Hapus dokter beserta akunnya: $nama");
        $pdo->commit();
        set_flash('success', "Dokter \"$nama\" beserta akunnya dihapus.");
    } catch (PDOException $ex) {
        $pdo->rollBack();
        if ($ex->getCode() !== '23000') {
            throw $ex;
        }
        $pdo->prepare("UPDATE pengguna SET aktif = 0 WHERE id_pengguna = ?")->execute([$dokter['id_pengguna']]);
        catat_log($pdo, 'NONAKTIF', 'dokter', $id, "Nonaktifkan dokter (masih dipakai): $nama");
        set_flash('warning', "Dokter \"$nama\" masih punya riwayat, jadi dinonaktifkan (tidak dihapus). Akunnya tidak bisa login lagi.");
    }
}

header('Location: index.php');
exit;
