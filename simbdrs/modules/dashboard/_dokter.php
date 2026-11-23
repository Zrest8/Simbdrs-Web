<?php
// =====================================================================
// Dashboard DOKTER — hanya pasien yang ia tangani (dokter penanggung jawab).
// Di-require oleh dashboard/index.php (sudah ada $pdo, $saya, $stok, $gol_kurang, $sapaan).
// Tabel (SELECT): permintaan_darah, pasien, ruangan, komponen_darah, kantong_darah, transfusi
//
// Dokter tidak mengisi apa pun di sistem (permintaan dibuat perawat), tetapi
// bisa memantau: sampai mana permintaan pasiennya, transfusi yang sedang
// berjalan, dan riwayat transfusi terakhir.
// =====================================================================

require_once __DIR__ . '/../permintaan/_fungsi.php';   // badge_status_permintaan()

$id_dokter = (int) ($saya['id_dokter'] ?? 0);

// ---------- Kartu angka ----------
$st = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM permintaan_darah
          WHERE id_dokter = :d1 AND status IN ('MENUNGGU','DIPROSES','TERPENUHI_SEBAGIAN')) AS aktif,
        (SELECT COUNT(*) FROM permintaan_darah
          WHERE id_dokter = :d2 AND status IN ('MENUNGGU','DIPROSES','TERPENUHI_SEBAGIAN') AND urgensi = 'CITO') AS cito,
        (SELECT COUNT(*) FROM kantong_darah k JOIN permintaan_darah p ON p.id_permintaan = k.id_permintaan_dipesan
          WHERE p.id_dokter = :d3 AND k.status = 'DIPESAN') AS siap,
        (SELECT COUNT(*) FROM transfusi t JOIN kantong_darah k ON k.id_kantong = t.id_kantong
           JOIN permintaan_darah p ON p.id_permintaan = t.id_permintaan
          WHERE p.id_dokter = :d4 AND k.status = 'DIKELUARKAN') AS di_ruang,
        (SELECT COUNT(*) FROM transfusi t JOIN permintaan_darah p ON p.id_permintaan = t.id_permintaan
          WHERE p.id_dokter = :d5 AND t.tgl_selesai >= NOW() - INTERVAL 30 DAY) AS selesai_30"
);
$st->execute(['d1' => $id_dokter, 'd2' => $id_dokter, 'd3' => $id_dokter, 'd4' => $id_dokter, 'd5' => $id_dokter]);
$angka = $st->fetch();

// ---------- ① Permintaan pasien yang masih berjalan ----------
// Termasuk yang sudah TERPENUHI tetapi kantongnya belum selesai ditransfusikan.
$st = $pdo->prepare(
    "SELECT p.id_permintaan, p.no_permintaan, p.urgensi, p.status, p.jumlah_kantong, p.tgl_permintaan, p.diagnosis,
            ps.nama_pasien, ps.no_rm, ps.gol_darah, ps.rhesus, c.kode, r.nama_ruangan,
            SUM(k.status = 'DIPESAN')        AS siap,
            SUM(k.status = 'DIKELUARKAN')    AS di_ruang,
            SUM(k.status = 'DITRANSFUSIKAN') AS selesai
     FROM permintaan_darah p
     JOIN pasien ps        ON ps.id_pasien = p.id_pasien
     JOIN ruangan r        ON r.id_ruangan = p.id_ruangan
     JOIN komponen_darah c ON c.id_komponen = p.id_komponen
     LEFT JOIN kantong_darah k ON k.id_permintaan_dipesan = p.id_permintaan
     WHERE p.id_dokter = ? AND p.status <> 'DIBATALKAN'
     GROUP BY p.id_permintaan
     HAVING p.status IN ('MENUNGGU','DIPROSES','TERPENUHI_SEBAGIAN') OR siap > 0 OR di_ruang > 0
     ORDER BY (p.urgensi = 'CITO') DESC, p.tgl_permintaan
     LIMIT 12"
);
$st->execute([$id_dokter]);
$permintaan = $st->fetchAll();

// ---------- ② Riwayat transfusi selesai (7 hari) ----------
$st = $pdo->prepare(
    "SELECT t.tgl_mulai, t.tgl_selesai, k.no_kantong, k.gol_darah, k.rhesus, c.kode,
            ps.nama_pasien, ps.no_rm, r.nama_ruangan
     FROM transfusi t
     JOIN kantong_darah k    ON k.id_kantong = t.id_kantong
     JOIN komponen_darah c   ON c.id_komponen = k.id_komponen
     JOIN permintaan_darah p ON p.id_permintaan = t.id_permintaan
     JOIN pasien ps          ON ps.id_pasien = t.id_pasien
     JOIN ruangan r          ON r.id_ruangan = p.id_ruangan
     WHERE p.id_dokter = ? AND t.tgl_selesai >= NOW() - INTERVAL 7 DAY
     ORDER BY t.tgl_selesai DESC LIMIT 8"
);
$st->execute([$id_dokter]);
$riwayat = $st->fetchAll();

/** Tahap permintaan dalam bahasa sehari-hari untuk dokter. */
function tahap_permintaan(array $p): string
{
    if ((int) $p['di_ruang'] > 0) {
        return (int) $p['di_ruang'] . ' kantong di ruangan';
    }
    if ((int) $p['siap'] > 0) {
        return (int) $p['siap'] . ' kantong siap di BDRS';
    }
    return ['MENUNGGU' => 'Menunggu diproses BDRS', 'DIPROSES' => 'Uji silang di BDRS'][$p['status']] ?? 'Diproses';
}

$kpi = [
    ['angka' => $angka['aktif'], 'label' => 'Permintaan aktif' . ($angka['cito'] ? " · {$angka['cito']} CITO" : ''),
     'ikon' => 'clipboard2-pulse', 'nada' => $angka['cito'] ? 'bahaya' : 'merek'],
    ['angka' => $angka['siap'], 'label' => 'Kantong siap di BDRS', 'ikon' => 'box-seam', 'nada' => $angka['siap'] ? 'waspada' : 'ok'],
    ['angka' => $angka['di_ruang'], 'label' => 'Kantong di ruangan / ditransfusikan', 'ikon' => 'heart-pulse', 'nada' => 'merek'],
    ['angka' => $angka['selesai_30'], 'label' => 'Transfusi selesai (30 hari)', 'ikon' => 'check2-circle', 'nada' => 'ok'],
];

require __DIR__ . '/../../includes/header.php';
?>

<div class="salam">
    <h2><?= e($sapaan) ?>, <?= e($saya['nama']) ?></h2>
    <p><?= e(tgl_indo(date('Y-m-d'))) ?> · Dokter penanggung jawab · data pasien yang Anda tangani</p>
</div>

<div class="row g-3 mb-3">
    <?php foreach ($kpi as $s): ?>
    <div class="col-6 col-xl-3">
        <a class="panel stat" href="<?= url('modules/permintaan/index.php') ?>">
            <div class="stat-ikon nada-<?= $s['nada'] ?>"><i class="bi bi-<?= $s['ikon'] ?>" aria-hidden="true"></i></div>
            <div class="min-w-0">
                <div class="stat-angka"><?= (int) $s['angka'] ?></div>
                <div class="stat-label"><?= e($s['label']) ?></div>
            </div>
            <i class="bi bi-chevron-right stat-panah" aria-hidden="true"></i>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <!-- ① Permintaan pasien -->
    <div class="col-12">
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-clipboard2-pulse" aria-hidden="true"></i><h2>Permintaan Darah Pasien Saya</h2>
                <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= url('modules/permintaan/index.php') ?>">Lihat semua</a>
            </div>
            <?php if (!$permintaan): ?>
                <div class="empty-state"><i class="bi bi-inbox"></i><p class="mt-2 mb-0">Tidak ada permintaan darah pasien Anda yang sedang berjalan.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>No. Permintaan</th><th>Pasien</th><th>Darah</th><th class="d-none d-md-table-cell">Ruangan</th><th>Kantong</th><th>Tahap sekarang</th></tr></thead>
                    <tbody>
                    <?php foreach ($permintaan as $p):
                        $siap_total = (int) $p['siap'] + (int) $p['di_ruang'] + (int) $p['selesai'];
                        $persen = $p['jumlah_kantong'] > 0 ? min(100, round($siap_total / $p['jumlah_kantong'] * 100)) : 0; ?>
                        <tr>
                            <td class="text-nowrap"><a href="<?= url('modules/permintaan/detail.php?id=' . (int) $p['id_permintaan']) ?>"><code><?= e($p['no_permintaan']) ?></code></a>
                                <?php if ($p['urgensi'] === 'CITO'): ?><span class="badge badge-cito ms-1">CITO</span><?php endif; ?>
                                <small class="d-block text-muted"><?= e(tgl_indo($p['tgl_permintaan'], true)) ?></small></td>
                            <td class="text-nowrap" title="<?= e($p['diagnosis']) ?>"><?= e($p['nama_pasien']) ?><small class="d-block text-muted"><?= e($p['no_rm']) ?></small></td>
                            <td class="text-nowrap"><b><?= e($p['kode']) ?> <?= e($p['gol_darah'] . $p['rhesus']) ?></b></td>
                            <td class="text-nowrap small d-none d-md-table-cell"><?= e($p['nama_ruangan']) ?></td>
                            <td class="text-nowrap">
                                <div class="progres-mini" title="<?= $siap_total ?> dari <?= (int) $p['jumlah_kantong'] ?> kantong lolos uji silang">
                                    <div class="progres-mini-bar"><span style="width: <?= $persen ?>%"></span></div>
                                    <small><?= $siap_total ?>/<?= (int) $p['jumlah_kantong'] ?> ktg</small></div>
                            </td>
                            <td class="text-nowrap"><?= badge_status_permintaan($p['status']) ?><small class="d-block text-secondary mt-1"><?= e(tahap_permintaan($p)) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- ② Riwayat -->
    <div class="col-12 col-xl-7">
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-clock-history" aria-hidden="true"></i><h2>Transfusi Selesai</h2>
                <small class="text-muted ms-auto">7 hari terakhir</small>
            </div>
            <?php if (!$riwayat): ?>
                <div class="empty-state"><i class="bi bi-inbox"></i><p class="mt-2 mb-0">Belum ada transfusi selesai dalam 7 hari terakhir.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Pasien</th><th>Kantong</th><th class="d-none d-md-table-cell">Ruangan</th><th>Selesai</th></tr></thead>
                    <tbody>
                    <?php foreach ($riwayat as $t): ?>
                        <tr>
                            <td class="text-nowrap"><?= e($t['nama_pasien']) ?><small class="d-block text-muted"><?= e($t['no_rm']) ?></small></td>
                            <td class="text-nowrap"><code><?= e($t['no_kantong']) ?></code><small class="d-block fw-semibold"><?= e($t['kode'] . ' ' . $t['gol_darah'] . $t['rhesus']) ?></small></td>
                            <td class="text-nowrap small d-none d-md-table-cell"><?= e($t['nama_ruangan']) ?></td>
                            <td class="text-nowrap small"><?= e(tgl_indo($t['tgl_selesai'], true)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- ③ Stok -->
    <div class="col-12 col-xl-5">
        <?php require __DIR__ . '/_stok.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
