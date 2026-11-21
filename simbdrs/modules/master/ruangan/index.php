<?php
// =====================================================================
// Master Ruangan — DAFTAR (F-02)
// Tabel: ruangan (SELECT), pengguna & permintaan_darah (hitung pemakaian)
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_login(['ADMIN']);

$judul = 'Data Ruangan';
$menu_aktif = 'ruangan';

$cari = trim($_GET['cari'] ?? '');

// Hitung berapa kali ruangan dipakai, supaya admin tahu mana yang bisa dihapus
$sql = "SELECT r.id_ruangan, r.nama_ruangan, r.aktif,
               (SELECT COUNT(*) FROM pengguna p WHERE p.id_ruangan = r.id_ruangan)          AS jml_pengguna,
               (SELECT COUNT(*) FROM permintaan_darah d WHERE d.id_ruangan = r.id_ruangan)  AS jml_permintaan
        FROM ruangan r";
$param = [];
if ($cari !== '') {
    $sql .= " WHERE r.nama_ruangan LIKE ?";
    $param[] = '%' . $cari . '%';
}
$sql .= " ORDER BY r.aktif DESC, r.nama_ruangan";

$stmt = $pdo->prepare($sql);
$stmt->execute($param);
$daftar = $stmt->fetchAll();

require __DIR__ . '/../../../includes/header.php';
?>

<section class="panel">
    <div class="panel-head flex-wrap">
        <i class="bi bi-hospital"></i><h2>Daftar Ruangan</h2>
        <form class="d-flex gap-2 ms-auto" method="get" role="search">
            <input class="form-control form-control-sm" name="cari" value="<?= e($cari) ?>" placeholder="Cari ruangan..." aria-label="Cari ruangan">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <a class="btn btn-sm btn-primary" href="tambah.php"><i class="bi bi-plus-lg"></i> Tambah</a>
    </div>

    <?php if (!$daftar): ?>
        <div class="empty-state"><i class="bi bi-hospital"></i>
            <p class="mt-2 mb-0"><?= $cari !== '' ? 'Tidak ada ruangan dengan kata "' . e($cari) . '".' : 'Belum ada data ruangan.' ?></p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th style="width:60px">No</th><th>Nama Ruangan</th><th>Dipakai</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $i => $r): ?>
                <tr class="<?= $r['aktif'] ? '' : 'text-muted' ?>">
                    <td><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= e($r['nama_ruangan']) ?></td>
                    <td><small><?= (int) $r['jml_pengguna'] ?> akun · <?= (int) $r['jml_permintaan'] ?> permintaan</small></td>
                    <td><?= $r['aktif'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="edit.php?id=<?= (int) $r['id_ruangan'] ?>"><i class="bi bi-pencil"></i> Edit</a>
                        <form class="d-inline" method="post" action="hapus.php"
                              onsubmit="return confirm('Hapus ruangan <?= e(addslashes($r['nama_ruangan'])) ?>? Jika masih dipakai, ruangan hanya akan dinonaktifkan.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id_ruangan'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>
