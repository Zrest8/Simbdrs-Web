<?php
// =====================================================================
// Master Ruangan — HAPUS
// Tabel: ruangan (DELETE atau UPDATE aktif = 0), log_aktivitas (INSERT)
//
// Jika ruangan sudah dipakai (ada pengguna/permintaan), foreign key
// menolak DELETE → ruangan cukup dinonaktifkan agar riwayat tidak hilang.
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_login(['ADMIN']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {   // hapus hanya lewat tombol (POST)
    header('Location: index.php');
    exit;
}
cek_csrf();

$id = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT nama_ruangan FROM ruangan WHERE id_ruangan = ?");
$stmt->execute([$id]);
$nama = $stmt->fetchColumn();

if ($nama === false) {
    set_flash('danger', 'Data ruangan tidak ditemukan.');
} else {
    try {
        $stmt = $pdo->prepare("DELETE FROM ruangan WHERE id_ruangan = ?");
        $stmt->execute([$id]);
        catat_log($pdo, 'HAPUS', 'ruangan', $id, "Hapus ruangan: $nama");
        set_flash('success', "Ruangan \"$nama\" dihapus.");
    } catch (PDOException $ex) {
        if ($ex->getCode() !== '23000') {
            throw $ex;
        }
        // Masih dipakai → nonaktifkan saja
        $stmt = $pdo->prepare("UPDATE ruangan SET aktif = 0 WHERE id_ruangan = ?");
        $stmt->execute([$id]);
        catat_log($pdo, 'NONAKTIF', 'ruangan', $id, "Nonaktifkan ruangan (masih dipakai): $nama");
        set_flash('warning', "Ruangan \"$nama\" masih dipakai data lain, jadi dinonaktifkan (tidak dihapus).");
    }
}

header('Location: index.php');
exit;
