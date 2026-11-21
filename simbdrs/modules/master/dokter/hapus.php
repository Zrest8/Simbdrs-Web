<?php
// =====================================================================
// Master Dokter — HAPUS
// Tabel: dokter (DELETE atau UPDATE aktif = 0), log_aktivitas (INSERT)
//
// Jika dokter sudah dipakai di permintaan_darah, foreign key
// fk_permintaan_dokter menolak DELETE → dokter cukup dinonaktifkan.
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_login(['ADMIN']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {   // hapus hanya lewat tombol (POST)
    header('Location: index.php');
    exit;
}
cek_csrf();

$id = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT nama_dokter FROM dokter WHERE id_dokter = ?");
$stmt->execute([$id]);
$nama = $stmt->fetchColumn();

if ($nama === false) {
    set_flash('danger', 'Data dokter tidak ditemukan.');
} else {
    try {
        $stmt = $pdo->prepare("DELETE FROM dokter WHERE id_dokter = ?");
        $stmt->execute([$id]);
        catat_log($pdo, 'HAPUS', 'dokter', $id, "Hapus dokter: $nama");
        set_flash('success', "Dokter \"$nama\" dihapus.");
    } catch (PDOException $ex) {
        if ($ex->getCode() !== '23000') {
            throw $ex;
        }
        $stmt = $pdo->prepare("UPDATE dokter SET aktif = 0 WHERE id_dokter = ?");
        $stmt->execute([$id]);
        catat_log($pdo, 'NONAKTIF', 'dokter', $id, "Nonaktifkan dokter (masih dipakai): $nama");
        set_flash('warning', "Dokter \"$nama\" masih dipakai di permintaan darah, jadi dinonaktifkan (tidak dihapus).");
    }
}

header('Location: index.php');
exit;
