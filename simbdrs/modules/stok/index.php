<?php
// =====================================================================
// Stok Darah — REKAP & DAFTAR KANTONG (F-04, BR-03/BR-04/BR-08/BR-09)
// Tabel (SELECT saja): kantong_darah, komponen_darah, stok_minimal,
//                      penerimaan, udd_asal
//
// Halaman ini hanya MEMBACA. Status kantong berubah lewat modul lain
// (penerimaan, permintaan/uji silang, transfusi, pengembalian/pemusnahan).
//
//  - Rekap: jumlah kantong TERSEDIA & belum kedaluwarsa per komponen × golongan,
//    dibandingkan dengan stok minimal (BR-09).
//  - Daftar: diurutkan FEFO — kedaluwarsa terdekat di atas (BR-04).
//  - Kondisi "hampir kedaluwarsa" bila sisa hari ≤ komponen.batas_peringatan_hari (BR-08).
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
$saya = wajib_login(['PETUGAS', 'KEPALA']);

$judul = 'Stok Darah';
$menu_aktif = 'stok';

$golongan = [];
foreach (['A', 'B', 'AB', 'O'] as $g) {
    foreach (['+', '-'] as $r) {
        $golongan[] = $g . $r;
    }
}
$daftar_status = ['TERSEDIA', 'DIPESAN', 'DIKELUARKAN', 'DITRANSFUSIKAN', 'DIKEMBALIKAN', 'DIMUSNAHKAN'];
$komponen = $pdo->query("SELECT id_komponen, kode, nama_komponen, batas_peringatan_hari FROM komponen_darah ORDER BY masa_simpan_hari, kode")->fetchAll();

// ---------- 1. Rekap stok siap pakai per komponen × golongan ----------
$rekap = [];
$q = $pdo->query(
    "SELECT id_komponen, CONCAT(gol_darah, rhesus) AS gol, COUNT(*) AS jml
     FROM kantong_darah
     WHERE status = 'TERSEDIA' AND tgl_kedaluwarsa >= CURDATE()
     GROUP BY id_komponen, gol_darah, rhesus"
);
foreach ($q as $r) {
    $rekap[$r['id_komponen']][$r['gol']] = (int) $r['jml'];
}
$minimal = [];
foreach ($pdo->query("SELECT id_komponen, CONCAT(gol_darah, rhesus) AS gol, jumlah_minimal FROM stok_minimal") as $r) {
    $minimal[$r['id_komponen']][$r['gol']] = (int) $r['jumlah_minimal'];
}

// ---------- 2. Filter daftar kantong ----------
$f = [
    'komponen' => (int) ($_GET['komponen'] ?? 0),
    'gol'      => in_array($_GET['gol'] ?? '', $golongan, true) ? $_GET['gol'] : '',
    'status'   => in_array($_GET['status'] ?? '', $daftar_status, true) ? $_GET['status'] : (isset($_GET['status']) && $_GET['status'] === 'SEMUA' ? 'SEMUA' : 'TERSEDIA'),
    'kondisi'  => in_array($_GET['kondisi'] ?? '', ['hampir', 'lewat'], true) ? $_GET['kondisi'] : '',
    'cari'     => strtoupper(trim($_GET['cari'] ?? '')),
];

$where = [];
$param = [];
if ($f['komponen'] > 0) {
    $where[] = 'k.id_komponen = ?';
    $param[] = $f['komponen'];
}
if ($f['gol'] !== '') {
    $where[] = 'k.gol_darah = ? AND k.rhesus = ?';
    $param[] = substr($f['gol'], 0, -1);
    $param[] = substr($f['gol'], -1);
}
if ($f['status'] !== 'SEMUA') {
    $where[] = 'k.status = ?';
    $param[] = $f['status'];
}
if ($f['kondisi'] === 'hampir') {
    $where[] = 'k.tgl_kedaluwarsa >= CURDATE() AND DATEDIFF(k.tgl_kedaluwarsa, CURDATE()) <= c.batas_peringatan_hari';
} elseif ($f['kondisi'] === 'lewat') {
    $where[] = 'k.tgl_kedaluwarsa < CURDATE()';
}
if ($f['cari'] !== '') {
    $where[] = 'k.no_kantong LIKE ?';
    $param[] = '%' . $f['cari'] . '%';
}

const BATAS_TAMPIL = 300;
$sql = "SELECT k.id_kantong, k.no_kantong, k.gol_darah, k.rhesus, k.volume_ml, k.tgl_aftap,
               k.tgl_kedaluwarsa, k.status, k.id_penerimaan,
               c.kode, c.batas_peringatan_hari,
               u.nama_udd, pn.tgl_terima,
               DATEDIFF(k.tgl_kedaluwarsa, CURDATE()) AS sisa_hari
        FROM kantong_darah k
        JOIN komponen_darah c ON c.id_komponen = k.id_komponen
        JOIN penerimaan pn    ON pn.id_penerimaan = k.id_penerimaan
        JOIN udd_asal u       ON u.id_udd = pn.id_udd"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY k.tgl_kedaluwarsa ASC, k.no_kantong ASC
        LIMIT " . (BATAS_TAMPIL + 1);
$stmt = $pdo->prepare($sql);
$stmt->execute($param);
$kantong = $stmt->fetchAll();
$terpotong = count($kantong) > BATAS_TAMPIL;
$kantong = array_slice($kantong, 0, BATAS_TAMPIL);

// Ringkasan angka untuk kartu atas
$ringkas = $pdo->query(
    "SELECT
        SUM(k.status = 'TERSEDIA' AND k.tgl_kedaluwarsa >= CURDATE())                                              AS siap,
        SUM(k.status = 'TERSEDIA' AND k.tgl_kedaluwarsa >= CURDATE()
            AND DATEDIFF(k.tgl_kedaluwarsa, CURDATE()) <= c.batas_peringatan_hari)                                  AS hampir,
        SUM(k.status = 'TERSEDIA' AND k.tgl_kedaluwarsa < CURDATE())                                               AS lewat,
        SUM(k.status = 'DIPESAN')                                                                                    AS dipesan
     FROM kantong_darah k JOIN komponen_darah c ON c.id_komponen = k.id_komponen"
)->fetch();

// Link filter cepat: pertahankan filter lain, ganti satu parameter
function link_filter(array $f, array $ubah): string
{
    $q = array_filter(array_merge($f, $ubah), fn($v) => $v !== '' && $v !== 0);
    return '?' . http_build_query($q);
}

$boleh_lihat_penerimaan = $saya['role'] === 'PETUGAS';

require __DIR__ . '/../../includes/header.php';
?>

<div class="row g-3 mb-3">
    <?php
    $kartu = [
        ['Siap pakai', $ringkas['siap'], 'droplet-fill', link_filter($f, ['status' => 'TERSEDIA', 'kondisi' => '']), ''],
        ['Hampir kedaluwarsa', $ringkas['hampir'], 'hourglass-split', link_filter($f, ['status' => 'TERSEDIA', 'kondisi' => 'hampir']), 'text-warning'],
        ['Kedaluwarsa (belum dimusnahkan)', $ringkas['lewat'], 'exclamation-octagon', link_filter($f, ['status' => 'TERSEDIA', 'kondisi' => 'lewat']), 'text-danger'],
        ['Dipesan (lolos uji silang)', $ringkas['dipesan'], 'bookmark-check', link_filter($f, ['status' => 'DIPESAN', 'kondisi' => '']), ''],
    ];
    foreach ($kartu as [$label, $angka, $ikon, $link, $kelas]): ?>
        <div class="col-6 col-xl-3">
            <a class="panel stat text-decoration-none text-reset" href="<?= e($link) ?>">
                <span class="stat-ikon <?= $kelas ?>"><i class="bi bi-<?= $ikon ?>"></i></span>
                <span><span class="stat-angka d-block <?= (int) $angka > 0 ? $kelas : '' ?>"><?= (int) $angka ?></span><span class="stat-label"><?= e($label) ?></span></span>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<section class="panel h-auto mb-3">
    <div class="panel-head flex-wrap">
        <i class="bi bi-grid-3x3"></i><h2>Rekap Stok Siap Pakai</h2>
        <span class="ms-auto small text-secondary">kantong TERSEDIA & belum kedaluwarsa · klik angka untuk melihat kantongnya</span>
    </div>
    <div class="table-responsive">
        <table class="table text-center align-middle tabel-rekap mb-0">
            <thead><tr><th class="text-start">Komponen</th><?php foreach ($golongan as $g): ?><th><?= e($g) ?></th><?php endforeach; ?><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($komponen as $c): $id = $c['id_komponen']; $total = 0; ?>
                <tr>
                    <td class="text-start"><span class="badge text-bg-danger"><?= e($c['kode']) ?></span></td>
                    <?php foreach ($golongan as $g):
                        $n = $rekap[$id][$g] ?? 0;
                        $min = $minimal[$id][$g] ?? null;
                        $kurang = $min !== null && $n < $min;
                        $total += $n;
                    ?>
                        <td class="<?= $kurang ? 'sel-kurang' : '' ?>">
                            <a class="text-decoration-none <?= $n === 0 ? 'text-secondary' : 'fw-semibold text-reset' ?>"
                               href="<?= e(link_filter($f, ['komponen' => (int) $id, 'gol' => $g, 'status' => 'TERSEDIA', 'kondisi' => ''])) ?>"><?= $n ?></a>
                            <?php if ($min !== null): ?><small class="d-block <?= $kurang ? 'text-danger' : 'text-secondary' ?>">min <?= $min ?></small><?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="fw-bold"><?= $total ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel h-auto">
    <div class="panel-head flex-wrap">
        <i class="bi bi-list-ol"></i><h2>Daftar Kantong <small class="text-secondary fw-normal">— urut FEFO (kedaluwarsa terdekat dulu)</small></h2>
    </div>
    <div class="panel-body border-bottom">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="f-komponen">Komponen</label>
                <select class="form-select form-select-sm" id="f-komponen" name="komponen">
                    <option value="">Semua</option>
                    <?php foreach ($komponen as $c): ?>
                        <option value="<?= (int) $c['id_komponen'] ?>" <?= $f['komponen'] === (int) $c['id_komponen'] ? 'selected' : '' ?>><?= e($c['kode']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="f-gol">Golongan</label>
                <select class="form-select form-select-sm" id="f-gol" name="gol">
                    <option value="">Semua</option>
                    <?php foreach ($golongan as $g): ?><option <?= $f['gol'] === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="f-status">Status</label>
                <select class="form-select form-select-sm" id="f-status" name="status">
                    <?php foreach ($daftar_status as $s): ?><option <?= $f['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
                    <option value="SEMUA" <?= $f['status'] === 'SEMUA' ? 'selected' : '' ?>>Semua status</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="f-kondisi">Kondisi</label>
                <select class="form-select form-select-sm" id="f-kondisi" name="kondisi">
                    <option value="">Semua</option>
                    <option value="hampir" <?= $f['kondisi'] === 'hampir' ? 'selected' : '' ?>>Hampir kedaluwarsa</option>
                    <option value="lewat" <?= $f['kondisi'] === 'lewat' ? 'selected' : '' ?>>Sudah kedaluwarsa</option>
                </select>
            </div>
            <div class="col-8 col-md-2">
                <label class="form-label small mb-1" for="f-cari">No. kantong</label>
                <input class="form-control form-control-sm" id="f-cari" name="cari" value="<?= e($f['cari']) ?>" placeholder="cari...">
            </div>
            <div class="col-4 col-md-2 d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-fill" type="submit"><i class="bi bi-funnel"></i> Terapkan</button>
                <a class="btn btn-sm btn-outline-secondary" href="index.php" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>

    <?php if (!$kantong): ?>
        <div class="empty-state"><i class="bi bi-droplet"></i><p class="mt-2 mb-0">Tidak ada kantong yang cocok dengan filter ini.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th style="width:44px">#</th><th>No. Kantong</th><th>Komp.</th><th>Gol.</th><th>Vol.</th><th>Kedaluwarsa</th><th>Sisa</th><th>Status</th><th>Asal</th></tr></thead>
            <tbody>
            <?php foreach ($kantong as $i => $k):
                $sisa = (int) $k['sisa_hari'];
                $aktif_di_stok = in_array($k['status'], ['TERSEDIA', 'DIPESAN'], true);
                $lewat  = $aktif_di_stok && $sisa < 0;
                $hampir = $aktif_di_stok && $sisa >= 0 && $sisa <= (int) $k['batas_peringatan_hari'];
            ?>
                <tr class="<?= $lewat ? 'table-danger' : ($hampir ? 'table-warning' : '') ?>">
                    <td class="text-secondary"><?= $i + 1 ?></td>
                    <td><code><?= e($k['no_kantong']) ?></code><?php if ($i === 0 && $f['status'] === 'TERSEDIA' && !$lewat): ?> <span class="badge text-bg-light border" title="Pakai kantong ini lebih dulu (FEFO)">pakai dulu</span><?php endif; ?></td>
                    <td><?= e($k['kode']) ?></td>
                    <td class="fw-semibold"><?= e($k['gol_darah'] . $k['rhesus']) ?></td>
                    <td class="text-nowrap"><?= (int) $k['volume_ml'] ?> ml</td>
                    <td class="text-nowrap"><?= e(tgl_indo($k['tgl_kedaluwarsa'])) ?></td>
                    <td class="text-nowrap small <?= $lewat ? 'text-danger fw-semibold' : ($hampir ? 'text-warning-emphasis fw-semibold' : 'text-secondary') ?>">
                        <?= $aktif_di_stok ? e(teks_sisa_hari($sisa)) : '—' ?></td>
                    <td><?= badge_status_kantong($k['status']) ?></td>
                    <td class="small">
                        <?php if ($boleh_lihat_penerimaan): ?>
                            <a href="<?= url('modules/penerimaan/detail.php?id=' . (int) $k['id_penerimaan']) ?>"><?= e($k['nama_udd']) ?></a>
                        <?php else: ?><?= e($k['nama_udd']) ?><?php endif; ?>
                        <span class="d-block text-secondary"><?= e(tgl_indo($k['tgl_terima'])) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="panel-body border-top small text-secondary d-flex flex-wrap gap-3">
        <span><span class="legend-box bg-warning-subtle border"></span> hampir kedaluwarsa (sisa ≤ batas peringatan komponen, BR-08)</span>
        <span><span class="legend-box bg-danger-subtle border"></span> sudah kedaluwarsa — segera musnahkan</span>
        <?php if ($terpotong): ?><span class="text-danger">Hanya <?= BATAS_TAMPIL ?> kantong pertama yang ditampilkan; persempit filter.</span><?php endif; ?>
    </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
