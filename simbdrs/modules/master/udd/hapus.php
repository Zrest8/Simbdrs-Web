<?php
// =====================================================================
// Master UDD Asal — HAPUS
// Tabel: udd_asal (DELETE atau UPDATE aktif = 0), log_aktivitas (INSERT)
//
// Jika UDD sudah punya data penerimaan, foreign key fk_penerimaan_udd
// menolak DELETE → UDD cukup dinonaktifkan agar riwayat penerimaan
// (kantong berasal dari UDD mana) tetap utuh.
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_login(['ADMIN']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {   // hapus hanya lewat tombol (POST)
    header('Location: index.php');
    exit;
}
cek_csrf();

$id = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT nama_udd FROM udd_asal WHERE id_udd = ?");
$stmt->execute([$id]);
$nama = $stmt->fetchColumn();

if ($nama === false) {
    set_flash('danger', 'Data UDD tidak ditemukan.');
} else {
    try {
        $stmt = $pdo->prepare("DELETE FROM udd_asal WHERE id_udd = ?");
        $stmt->execute([$id]);
        catat_log($pdo, 'HAPUS', 'udd_asal', $id, "Hapus UDD: $nama");
        set_flash('success', "UDD \"$nama\" dihapus.");
    } catch (PDOException $ex) {
        if ($ex->getCode() !== '23000') {
            throw $ex;
        }
        $stmt = $pdo->prepare("UPDATE udd_asal SET aktif = 0 WHERE id_udd = ?");
        $stmt->execute([$id]);
        catat_log($pdo, 'NONAKTIF', 'udd_asal', $id, "Nonaktifkan UDD (punya riwayat penerimaan): $nama");
        set_flash('warning', "UDD \"$nama\" punya riwayat penerimaan kantong, jadi dinonaktifkan (tidak dihapus).");
    }
}

header('Location: index.php');
exit;
