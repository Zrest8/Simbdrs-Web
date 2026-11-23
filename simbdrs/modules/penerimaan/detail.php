<?php
// =====================================================================
// Penerimaan Kantong — DETAIL SATU KIRIMAN (F-03)
// Tabel: penerimaan, udd_asal, pengguna (SELECT header)
//        kantong_darah + komponen_darah (SELECT isi kiriman & status terkini)
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
wajib_hak('penerimaan');

$judul = 'Detail Penerimaan';
$menu_aktif = 'penerimaan';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT pn.*, u.nama_udd, u.telepon, pg.nama AS nama_petugas
     FROM penerimaan pn
     JOIN udd_asal u  ON u.id_udd = pn.id_udd
     JOIN pengguna pg ON pg.id_pengguna = pn.id_petugas
     WHERE pn.id_penerimaan = ?"
);
$stmt->execute([$id]);
$h = $stmt->fetch();

if (!$h) {
    set_flash('danger', 'Data penerimaan tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT k.*, c.kode, c.suhu_min, c.suhu_max,
            DATEDIFF(k.tgl_kedaluwarsa, CURDATE()) AS sisa_hari
     FROM kantong_darah k
     JOIN komponen_darah c ON c.id_komponen = k.id_komponen
     WHERE k.id_penerimaan = ?
     ORDER BY k.tgl_kedaluwarsa, k.no_kantong"
);
$stmt->execute([$id]);
$kantong = $stmt->fetchAll();

// Suhu saat diterima di luar rentang simpan komponen? (hanya penanda, tidak memblokir)
$suhu_menyimpang = [];
if ($h['suhu_terima'] !== null) {
    foreach ($kantong as $k) {
        if ($k['suhu_min'] !== null && ((float) $h['suhu_terima'] < (float) $k['suhu_min'] || (float) $h['suhu_terima'] > (float) $k['suhu_max'])) {
            $suhu_menyimpang[$k['kode']] = $k['suhu_min'] . '–' . $k['suhu_max'];
        }
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="mb-3"><a href="index.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Kembali ke daftar penerimaan</a></div>

<div class="row g-3">
    <div class="col-lg-4 col-xl-3">
        <section class="panel h-auto panel-lekat">
            <div class="panel-head"><i class="bi bi-file-earmark-text"></i><h2>Data Kiriman</h2></div>
            <div class="panel-body">
                <dl class="mb-0 small">
                    <dt class="text-secondary fw-normal">No. dokumen</dt><dd class="fw-semibold"><code><?= e($h['no_dokumen']) ?></code></dd>
                    <dt class="text-secondary fw-normal">UDD asal</dt><dd class="fw-semibold"><?= e($h['nama_udd']) ?><?= $h['telepon'] ? '<br><span class="fw-normal">' . e($h['telepon']) . '</span>' : '' ?></dd>
                    <dt class="text-secondary fw-normal">Waktu terima</dt><dd><?= e(tgl_indo($h['tgl_terima'], true)) ?></dd>
                    <dt class="text-secondary fw-normal">Suhu saat diterima</dt>
                    <dd><?= $h['suhu_terima'] !== null ? e(str_replace('.', ',', $h['suhu_terima'])) . ' °C' : '—' ?>
                        <?php foreach ($suhu_menyimpang as $kode => $rentang): ?>
                            <div class="text-danger"><i class="bi bi-thermometer-high"></i> di luar rentang <?= e($kode) ?> (<?= e(str_replace('.', ',', $rentang)) ?> °C)</div>
                        <?php endforeach; ?></dd>
                    <dt class="text-secondary fw-normal">Petugas penerima</dt><dd><?= e($h['nama_petugas']) ?></dd>
                    <?php if ($h['catatan']): ?>
                        <dt class="text-secondary fw-normal">Catatan</dt><dd class="mb-0"><?= nl2br(e($h['catatan'])) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </section>
    </div>

    <div class="col-lg-8 col-xl-9">
        <section class="panel">
            <div class="panel-head"><i class="bi bi-droplet"></i><h2>Kantong dalam Kiriman (<?= count($kantong) ?>)</h2>
                <span class="ms-auto small text-secondary">urut kedaluwarsa terdekat</span></div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>No. Kantong</th><th>Komp.</th><th>Gol.</th><th>Vol.</th><th>Aftap</th><th>Kedaluwarsa</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($kantong as $k):
                        $sisa = (int) $k['sisa_hari'];
                        $masih_di_stok = $k['status'] === 'TERSEDIA';
                    ?>
                        <tr>
                            <td><code><?= e($k['no_kantong']) ?></code></td>
                            <td><?= e($k['kode']) ?></td>
                            <td class="fw-semibold"><?= e($k['gol_darah'] . $k['rhesus']) ?></td>
                            <td class="text-nowrap"><?= (int) $k['volume_ml'] ?> ml</td>
                            <td class="text-nowrap"><?= e(tgl_indo($k['tgl_aftap'])) ?></td>
                            <td class="text-nowrap"><?= e(tgl_indo($k['tgl_kedaluwarsa'])) ?>
                                <?php if ($masih_di_stok): ?>
                                    <br><small class="<?= $sisa < 0 ? 'text-danger fw-semibold' : 'text-secondary' ?>"><?= e(teks_sisa_hari($sisa)) ?></small>
                                <?php endif; ?></td>
                            <td class="text-nowrap"><?= badge_status_kantong($k['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
