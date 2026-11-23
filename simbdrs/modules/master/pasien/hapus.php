<?php
// =====================================================================
// Master Pasien — HAPUS
// Tabel: pasien (DELETE atau UPDATE aktif = 0), log_aktivitas (INSERT)
//
// Jika pasien sudah punya permintaan darah / transfusi, foreign key
// menolak DELETE → pasien cukup dinonaktifkan agar riwayat transaksi tetap utuh.
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_hak('pasien', 'hapus');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {   // hapus hanya lewat tombol (POST)
    header('Location: index.php');
    exit;
}
cek_csrf();

$id = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT CONCAT(no_rm, ' - ', nama_pasien) FROM pasien WHERE id_pasien = ?");
$stmt->execute([$id]);
$label = $stmt->fetchColumn();

if ($label === false) {
    set_flash('danger', 'Data pasien tidak ditemukan.');
} else {
    try {
        $stmt = $pdo->prepare("DELETE FROM pasien WHERE id_pasien = ?");
        $stmt->execute([$id]);
        catat_log($pdo, 'HAPUS', 'pasien', $id, "Hapus pasien: $label");
        set_flash('success', "Pasien \"$label\" dihapus.");
    } catch (PDOException $ex) {
        if ($ex->getCode() !== '23000') {
            throw $ex;
        }
        $stmt = $pdo->prepare("UPDATE pasien SET aktif = 0 WHERE id_pasien = ?");
        $stmt->execute([$id]);
        catat_log($pdo, 'NONAKTIF', 'pasien', $id, "Nonaktifkan pasien (punya riwayat): $label");
        set_flash('warning', "Pasien \"$label\" punya riwayat permintaan/transfusi, jadi dinonaktifkan (tidak dihapus).");
    }
}

header('Location: index.php');
exit;
