<?php
// =====================================================================
// Master Komponen Darah — HAPUS
// Tabel: komponen_darah (DELETE), log_aktivitas (INSERT)
//
// Tabel komponen_darah tidak punya kolom `aktif`. Jika komponen sudah dipakai
// (kantong_darah, permintaan_darah, stok_minimal), foreign key menolak DELETE
// dan penghapusan dibatalkan — data komponen tetap ada.
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_hak('komponen', 'hapus');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {   // hapus hanya lewat tombol (POST)
    header('Location: index.php');
    exit;
}
cek_csrf();

$id = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT kode FROM komponen_darah WHERE id_komponen = ?");
$stmt->execute([$id]);
$kode = $stmt->fetchColumn();

if ($kode === false) {
    set_flash('danger', 'Data komponen tidak ditemukan.');
} else {
    try {
        $stmt = $pdo->prepare("DELETE FROM komponen_darah WHERE id_komponen = ?");
        $stmt->execute([$id]);
        catat_log($pdo, 'HAPUS', 'komponen_darah', $id, "Hapus komponen: $kode");
        set_flash('success', "Komponen \"$kode\" dihapus.");
    } catch (PDOException $ex) {
        if ($ex->getCode() !== '23000') {
            throw $ex;
        }
        set_flash('danger', "Komponen \"$kode\" tidak bisa dihapus karena sudah dipakai di data kantong, permintaan, atau stok minimal.");
    }
}

header('Location: index.php');
exit;
