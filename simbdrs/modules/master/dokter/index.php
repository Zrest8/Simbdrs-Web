<?php
// =====================================================================
// Master Dokter — DAFTAR (F-02)
// Tabel: dokter (SELECT), permintaan_darah (hitung pemakaian)
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_login(['ADMIN']);

$judul = 'Data Dokter';
$menu_aktif = 'dokter';

$cari = trim($_GET['cari'] ?? '');

// Hitung berapa permintaan darah yang memakai dokter ini (dokter penanggung jawab, BR-14)
$sql = "SELECT d.id_dokter, d.nama_dokter, d.spesialis, d.aktif,
               (SELECT COUNT(*) FROM permintaan_darah p WHERE p.id_dokter = d.id_dokter) AS jml_permintaan
        FROM dokter d";
$param = [];
if ($cari !== '') {
    $sql .= " WHERE d.nama_dokter LIKE ? OR d.spesialis LIKE ?";
    $param[] = '%' . $cari . '%';
    $param[] = '%' . $cari . '%';
}
$sql .= " ORDER BY d.aktif DESC, d.nama_dokter";

$stmt = $pdo->prepare($sql);
$stmt->execute($param);
$daftar = $stmt->fetchAll();

require __DIR__ . '/../../../includes/header.php';
?>

<section class="panel">
    <div class="panel-head flex-wrap">
        <i class="bi bi-person-badge"></i><h2>Daftar Dokter</h2>
        <form class="d-flex gap-2 ms-auto" method="get" role="search">
            <input class="form-control form-control-sm" name="cari" value="<?= e($cari) ?>" placeholder="Cari nama / spesialis..." aria-label="Cari dokter">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <a class="btn btn-sm btn-primary" href="tambah.php"><i class="bi bi-plus-lg"></i> Tambah</a>
    </div>

    <?php if (!$daftar): ?>
        <div class="empty-state"><i class="bi bi-person-badge"></i>
            <p class="mt-2 mb-0"><?= $cari !== '' ? 'Tidak ada dokter dengan kata "' . e($cari) . '".' : 'Belum ada data dokter.' ?></p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th style="width:60px">No</th><th>Nama Dokter</th><th>Spesialis</th><th>Dipakai</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $i => $d): ?>
                <tr class="<?= $d['aktif'] ? '' : 'text-muted' ?>">
                    <td><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= e($d['nama_dokter']) ?></td>
                    <td><?= $d['spesialis'] !== null && $d['spesialis'] !== '' ? e($d['spesialis']) : '<span class="text-secondary">—</span>' ?></td>
                    <td><small><?= (int) $d['jml_permintaan'] ?> permintaan</small></td>
                    <td><?= $d['aktif'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="edit.php?id=<?= (int) $d['id_dokter'] ?>"><i class="bi bi-pencil"></i> Edit</a>
                        <form class="d-inline" method="post" action="hapus.php"
                              onsubmit="return confirm('Hapus dokter <?= e(addslashes($d['nama_dokter'])) ?>? Jika masih dipakai, dokter hanya akan dinonaktifkan.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $d['id_dokter'] ?>">
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
