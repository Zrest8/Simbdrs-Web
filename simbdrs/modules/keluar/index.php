<?php
// =====================================================================
// Pengembalian & Pemusnahan — DAFTAR (F-08)
// Hanya kantong berstatus TERSEDIA yang bisa dikembalikan ke UDD atau
// dimusnahkan. Kantong DIPESAN harus dilepas dulu di halaman permintaan.
// Tabel (SELECT): kantong_darah, komponen_darah, penerimaan, udd_asal,
//                 pengembalian, pemusnahan, pengguna
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
$saya = wajib_login(['PETUGAS']);

$judul = 'Pengembalian & Pemusnahan';
$menu_aktif = 'keluar';

$cari    = trim($_GET['cari'] ?? '');
$kondisi = $_GET['kondisi'] ?? 'lewat';
if (!in_array($kondisi, ['lewat', 'hampir', 'semua'], true)) {
    $kondisi = 'lewat';
}
$tab = ($_GET['tab'] ?? 'musnah') === 'kembali' ? 'kembali' : 'musnah';

// ---------- Ringkasan ----------
$jml_lewat = (int) $pdo->query(
    "SELECT COUNT(*) FROM kantong_darah WHERE status = 'TERSEDIA' AND tgl_kedaluwarsa < CURDATE()"
)->fetchColumn();
$jml_dipesan_lewat = (int) $pdo->query(
    "SELECT COUNT(*) FROM kantong_darah WHERE status = 'DIPESAN' AND tgl_kedaluwarsa < CURDATE()"
)->fetchColumn();

// ---------- Kandidat (TERSEDIA) ----------
$where = ["k.status = 'TERSEDIA'"];
$param = [];
if ($kondisi === 'lewat') {
    $where[] = 'k.tgl_kedaluwarsa < CURDATE()';
} elseif ($kondisi === 'hampir') {
    $where[] = 'DATEDIFF(k.tgl_kedaluwarsa, CURDATE()) BETWEEN 0 AND c.batas_peringatan_hari';
}
if ($cari !== '') {
    $where[] = 'k.no_kantong LIKE ?';
    $param[] = '%' . $cari . '%';
}
$stmt = $pdo->prepare(
    "SELECT k.id_kantong, k.no_kantong, k.gol_darah, k.rhesus, k.volume_ml, k.tgl_kedaluwarsa,
            DATEDIFF(k.tgl_kedaluwarsa, CURDATE()) AS sisa_hari, c.kode, u.nama_udd
     FROM kantong_darah k
     JOIN komponen_darah c ON c.id_komponen = k.id_komponen
     JOIN penerimaan pn    ON pn.id_penerimaan = k.id_penerimaan
     JOIN udd_asal u       ON u.id_udd = pn.id_udd
     WHERE " . implode(' AND ', $where) . "
     ORDER BY k.tgl_kedaluwarsa, k.no_kantong LIMIT 200"
);
$stmt->execute($param);
$kandidat = $stmt->fetchAll();

// ---------- Riwayat ----------
if ($tab === 'musnah') {
    $riwayat = $pdo->query(
        "SELECT m.tgl_musnah AS tgl, m.alasan, m.keterangan, k.id_kantong, k.no_kantong, k.gol_darah, k.rhesus, c.kode, pg.nama
         FROM pemusnahan m
         JOIN kantong_darah k  ON k.id_kantong = m.id_kantong
         JOIN komponen_darah c ON c.id_komponen = k.id_komponen
         JOIN pengguna pg      ON pg.id_pengguna = m.id_petugas
         ORDER BY m.tgl_musnah DESC LIMIT 50"
    )->fetchAll();
} else {
    $riwayat = $pdo->query(
        "SELECT b.tgl_kembali AS tgl, b.alasan, NULL AS keterangan, k.id_kantong, k.no_kantong, k.gol_darah, k.rhesus, c.kode, pg.nama, u.nama_udd
         FROM pengembalian b
         JOIN kantong_darah k  ON k.id_kantong = b.id_kantong
         JOIN komponen_darah c ON c.id_komponen = k.id_komponen
         JOIN penerimaan pn    ON pn.id_penerimaan = k.id_penerimaan
         JOIN udd_asal u       ON u.id_udd = pn.id_udd
         JOIN pengguna pg      ON pg.id_pengguna = b.id_petugas
         ORDER BY b.tgl_kembali DESC LIMIT 50"
    )->fetchAll();
}

function link_keluar(array $ubah): string
{
    $q = array_merge(['kondisi' => $_GET['kondisi'] ?? 'lewat', 'cari' => $_GET['cari'] ?? '', 'tab' => $_GET['tab'] ?? 'musnah'], $ubah);
    return url('modules/keluar/index.php?' . http_build_query(array_filter($q, fn($v) => $v !== '')));
}

require __DIR__ . '/../../includes/header.php';
?>

<?php if ($jml_lewat > 0): ?>
<div class="alert alert-danger d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-octagon-fill fs-5"></i>
    <div><strong><?= $jml_lewat ?> kantong TERSEDIA sudah kedaluwarsa.</strong> Kantong ini tidak boleh dipakai (BR-03) — kembalikan ke UDD atau musnahkan.</div>
</div>
<?php endif; ?>
<?php if ($jml_dipesan_lewat > 0): ?>
<div class="alert alert-warning small"><i class="bi bi-info-circle"></i> Ada <?= $jml_dipesan_lewat ?> kantong <strong>DIPESAN</strong> yang sudah kedaluwarsa. Lepas dulu dari permintaannya (halaman Permintaan Darah) agar statusnya TERSEDIA, lalu tindak di sini.</div>
<?php endif; ?>

<section class="panel h-auto mb-3">
    <div class="panel-head flex-wrap gap-2"><i class="bi bi-box-seam"></i><h2>Pilih Kantong</h2>
        <form class="ms-auto d-flex flex-wrap gap-2" method="get">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <select class="form-select form-select-sm w-auto" name="kondisi" aria-label="Kondisi" onchange="this.form.submit()">
                <option value="lewat"  <?= $kondisi === 'lewat' ? 'selected' : '' ?>>Sudah kedaluwarsa</option>
                <option value="hampir" <?= $kondisi === 'hampir' ? 'selected' : '' ?>>Hampir kedaluwarsa</option>
                <option value="semua"  <?= $kondisi === 'semua' ? 'selected' : '' ?>>Semua TERSEDIA</option>
            </select>
            <input class="form-control form-control-sm w-auto" name="cari" value="<?= e($cari) ?>" placeholder="No. kantong" aria-label="Cari no. kantong">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <?php if (!$kandidat): ?>
        <div class="empty-state py-4"><i class="bi bi-check2-circle"></i><p class="mt-2 mb-0">Tidak ada kantong untuk filter ini.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>No. Kantong</th><th>Komponen</th><th>Gol.</th><th>Volume</th><th>UDD Asal</th><th>Kedaluwarsa</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($kandidat as $k): $sisa = (int) $k['sisa_hari']; ?>
                <tr class="<?= $sisa < 0 ? 'table-danger' : '' ?>">
                    <td><code><?= e($k['no_kantong']) ?></code></td>
                    <td><?= e($k['kode']) ?></td>
                    <td class="fw-semibold"><?= e($k['gol_darah'] . $k['rhesus']) ?></td>
                    <td><?= (int) $k['volume_ml'] ?> mL</td>
                    <td><?= e($k['nama_udd']) ?></td>
                    <td class="text-nowrap small"><?= e(tgl_indo($k['tgl_kedaluwarsa'])) ?> · <?= e(teks_sisa_hari($sisa)) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('modules/keluar/proses.php?id=' . (int) $k['id_kantong']) ?>"><i class="bi bi-arrow-right-circle"></i> Tindak</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section class="panel h-auto">
    <div class="panel-head"><i class="bi bi-clock-history"></i><h2>Riwayat</h2>
        <ul class="nav nav-pills nav-sm ms-auto">
            <li class="nav-item"><a class="nav-link py-1 <?= $tab === 'musnah' ? 'active' : '' ?>" href="<?= link_keluar(['tab' => 'musnah']) ?>">Pemusnahan</a></li>
            <li class="nav-item"><a class="nav-link py-1 <?= $tab === 'kembali' ? 'active' : '' ?>" href="<?= link_keluar(['tab' => 'kembali']) ?>">Pengembalian</a></li>
        </ul>
    </div>
    <?php if (!$riwayat): ?>
        <div class="empty-state py-4"><p class="mb-0">Belum ada data <?= $tab === 'musnah' ? 'pemusnahan' : 'pengembalian' ?>.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Waktu</th><th>Kantong</th><?php if ($tab === 'kembali'): ?><th>Ke UDD</th><?php endif; ?><th>Alasan</th><th>Petugas</th></tr></thead>
            <tbody>
            <?php foreach ($riwayat as $r): ?>
                <tr>
                    <td class="text-nowrap small"><?= e(tgl_indo($r['tgl'], true)) ?></td>
                    <td class="text-nowrap"><code><?= e($r['no_kantong']) ?></code> <small class="fw-semibold"><?= e($r['kode'] . ' ' . $r['gol_darah'] . $r['rhesus']) ?></small></td>
                    <?php if ($tab === 'kembali'): ?><td><?= e($r['nama_udd']) ?></td><?php endif; ?>
                    <td><?= e($r['alasan']) ?><?php if ($r['keterangan']): ?><small class="d-block text-secondary"><?= e($r['keterangan']) ?></small><?php endif; ?></td>
                    <td class="small"><?= e($r['nama']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
