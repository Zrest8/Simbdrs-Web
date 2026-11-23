<?php
// =====================================================================
// Permintaan Darah — DETAIL & PROSES (F-05, F-06)
// Tabel (SELECT): permintaan_darah + pasien/ruangan/dokter/komponen,
//                 kantong_darah (kantong terikat & saran FEFO), uji_silang
// Aksi (uji silang / lepas / batal) dikirim ke aksi.php.
//
// Saran kantong (hanya PETUGAS, permintaan masih aktif):
//   BR-04  komponen sama, golongan ABO + rhesus sama persis dengan pasien
//   BR-03  status TERSEDIA dan belum kedaluwarsa
//   BR-05  urut tgl_kedaluwarsa ASC (FEFO)
//   + kantong yang sudah TIDAK_COCOK untuk permintaan ini tidak disarankan lagi
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
require __DIR__ . '/_fungsi.php';
$saya = wajib_hak('permintaan');

$judul = 'Detail Permintaan';
$menu_aktif = 'permintaan';

$id = (int) ($_GET['id'] ?? 0);
$p = ambil_permintaan($pdo, $id, $saya);
if (!$p) {
    set_flash('danger', 'Permintaan tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$aktif      = in_array($p['status'], STATUS_BISA_DIUBAH, true);   // termasuk TERPENUHI (belum diserahkan)
$petugas    = hak('permintaan', 'ubah');   // boleh memproses (uji silang, pesan/lepas kantong)

// Kantong yang terikat ke permintaan ini (sudah lolos uji silang)
$stmt = $pdo->prepare(
    "SELECT k.id_kantong, k.no_kantong, k.gol_darah, k.rhesus, k.volume_ml, k.tgl_kedaluwarsa, k.status,
            DATEDIFF(k.tgl_kedaluwarsa, CURDATE()) AS sisa_hari
     FROM kantong_darah k
     WHERE k.id_permintaan_dipesan = ? AND k.status IN ('DIPESAN','DIKELUARKAN','DITRANSFUSIKAN')
     ORDER BY k.tgl_kedaluwarsa"
);
$stmt->execute([$id]);
$terikat = $stmt->fetchAll();
$kurang  = max(0, (int) $p['jumlah_kantong'] - count($terikat));

// Riwayat uji silang (termasuk TIDAK_COCOK)
$stmt = $pdo->prepare(
    "SELECT u.*, k.no_kantong, pg.nama AS nama_petugas
     FROM uji_silang u
     JOIN kantong_darah k ON k.id_kantong = u.id_kantong
     JOIN pengguna pg     ON pg.id_pengguna = u.id_petugas
     WHERE u.id_permintaan = ? ORDER BY u.tgl_uji DESC, u.id_uji DESC"
);
$stmt->execute([$id]);
$riwayat_uji = $stmt->fetchAll();

// Saran FEFO
$saran = [];
$jml_cocok_total = 0;
if ($petugas && $aktif && $kurang > 0) {
    $sql_saran = "FROM kantong_darah k
                  WHERE k.id_komponen = ? AND k.gol_darah = ? AND k.rhesus = ?
                    AND k.status = 'TERSEDIA' AND k.tgl_kedaluwarsa >= CURDATE()
                    AND k.id_kantong NOT IN (SELECT u.id_kantong FROM uji_silang u
                                              WHERE u.id_permintaan = ? AND u.hasil = 'TIDAK_COCOK')";
    $arg = [(int) $p['id_komponen'], $p['gol_darah'], $p['rhesus'], $id];

    $stmt = $pdo->prepare("SELECT COUNT(*) $sql_saran");
    $stmt->execute($arg);
    $jml_cocok_total = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT k.id_kantong, k.no_kantong, k.volume_ml, k.tgl_kedaluwarsa,
                DATEDIFF(k.tgl_kedaluwarsa, CURDATE()) AS sisa_hari $sql_saran
         ORDER BY k.tgl_kedaluwarsa ASC, k.no_kantong ASC LIMIT 10"
    );
    $stmt->execute($arg);
    $saran = $stmt->fetchAll();
}

// Boleh dibatalkan selama belum ada kantong yang keluar dari BDRS
$ada_yang_keluar = (bool) array_filter($terikat, fn($k) => $k['status'] !== 'DIPESAN');
$boleh_batal = $aktif && !$ada_yang_keluar;

require __DIR__ . '/../../includes/header.php';
?>

<div class="mb-3"><a href="index.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Kembali ke daftar permintaan</a></div>

<div class="row g-3">
    <!-- Kiri: data permintaan -->
    <div class="col-lg-4">
        <section class="panel h-auto mb-3">
            <div class="panel-head flex-wrap">
                <i class="bi bi-clipboard2-pulse"></i><h2><code><?= e($p['no_permintaan']) ?></code></h2>
                <span class="ms-auto"><?php if ($p['urgensi'] === 'CITO'): ?><span class="badge badge-cito me-1">CITO</span><?php endif; ?><?= badge_status_permintaan($p['status']) ?></span>
            </div>
            <div class="panel-body">
                <div class="text-center mb-3">
                    <div class="display-6 fw-bold text-primary"><?= e($p['kode']) ?> <?= e($p['gol_darah'] . $p['rhesus']) ?></div>
                    <div class="text-secondary"><?= count($terikat) ?> dari <?= (int) $p['jumlah_kantong'] ?> kantong siap</div>
                    <div class="progress mt-2" role="progressbar" aria-label="Kantong terpenuhi" style="height:8px">
                        <div class="progress-bar bg-success" style="width: <?= min(100, round(count($terikat) / max(1, (int) $p['jumlah_kantong']) * 100)) ?>%"></div>
                    </div>
                </div>
                <dl class="mb-0 small">
                    <dt class="text-secondary fw-normal">Pasien</dt>
                    <dd class="fw-semibold"><?= e($p['nama_pasien']) ?> <span class="fw-normal text-secondary">· <?= e($p['no_rm']) ?> · <?= $p['jenis_kelamin'] === 'L' ? 'L' : 'P' ?></span></dd>
                    <dt class="text-secondary fw-normal">Ruangan</dt><dd><?= e($p['nama_ruangan']) ?></dd>
                    <dt class="text-secondary fw-normal">Dokter penanggung jawab</dt><dd><?= e($p['nama_dokter']) ?><?= $p['spesialis'] ? ' <span class="text-secondary">(' . e($p['spesialis']) . ')</span>' : '' ?></dd>
                    <dt class="text-secondary fw-normal">Diagnosis / indikasi</dt><dd><?= e($p['diagnosis']) ?></dd>
                    <dt class="text-secondary fw-normal">Diminta oleh</dt><dd class="mb-0"><?= e($p['nama_peminta']) ?> · <?= e(tgl_indo($p['tgl_permintaan'], true)) ?></dd>
                </dl>
            </div>
            <?php if ($boleh_batal && hak('permintaan', 'hapus')): ?>
            <div class="panel-body border-top">
                <form method="post" action="aksi.php" onsubmit="return confirm('Batalkan permintaan ini? Kantong yang sudah dipesan akan dikembalikan ke stok.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="batal">
                    <input type="hidden" name="id_permintaan" value="<?= $id ?>">
                    <label class="form-label small" for="alasan_batal">Alasan pembatalan</label>
                    <div class="input-group input-group-sm">
                        <input class="form-control" id="alasan_batal" name="alasan" maxlength="200" placeholder="mis. pasien membaik, transfusi ditunda" required>
                        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-x-octagon"></i> Batalkan</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Kanan: kantong & proses -->
    <div class="col-lg-8">
        <section class="panel h-auto mb-3">
            <div class="panel-head"><i class="bi bi-bookmark-check"></i><h2>Kantong untuk Permintaan Ini</h2></div>
            <?php if (!$terikat): ?>
                <div class="empty-state py-4"><i class="bi bi-hourglass"></i><p class="mt-2 mb-0">
                    <?= $petugas ? 'Belum ada kantong yang lolos uji silang. Pilih kantong dari saran di bawah.' : 'BDRS sedang memproses permintaan ini.' ?></p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>No. Kantong</th><th>Gol.</th><th>Vol.</th><th>Kedaluwarsa</th><th>Status</th><?php if ($petugas && $aktif): ?><th class="text-end">Aksi</th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($terikat as $k): ?>
                        <tr>
                            <td><code><?= e($k['no_kantong']) ?></code></td>
                            <td class="fw-semibold"><?= e($k['gol_darah'] . $k['rhesus']) ?></td>
                            <td><?= (int) $k['volume_ml'] ?> ml</td>
                            <td class="text-nowrap"><?= e(tgl_indo($k['tgl_kedaluwarsa'])) ?>
                                <?php if ($k['status'] === 'DIPESAN'): ?><small class="d-block <?= (int) $k['sisa_hari'] < 0 ? 'text-danger fw-semibold' : 'text-secondary' ?>"><?= e(teks_sisa_hari((int) $k['sisa_hari'])) ?></small><?php endif; ?></td>
                            <td><?= badge_status_kantong($k['status']) ?></td>
                            <?php if ($petugas && $aktif): ?>
                            <td class="text-end">
                                <?php if ($k['status'] === 'DIPESAN'): ?>
                                <form method="post" action="aksi.php" class="d-inline" onsubmit="return confirm('Lepaskan kantong <?= e($k['no_kantong']) ?> kembali ke stok TERSEDIA?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="lepas">
                                    <input type="hidden" name="id_permintaan" value="<?= $id ?>">
                                    <input type="hidden" name="id_kantong" value="<?= (int) $k['id_kantong'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit" title="DIPESAN → TERSEDIA"><i class="bi bi-arrow-counterclockwise"></i> Lepas</button>
                                </form>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <?php if ($petugas && $p['status'] === 'TERPENUHI'): ?>
                <div class="panel-body border-top small text-success"><i class="bi bi-check-circle"></i> Semua kantong siap. Langkah berikutnya: serahkan ke ruangan (modul Transfusi).</div>
            <?php endif; ?>
        </section>

        <?php if ($petugas && $aktif && $kurang > 0): ?>
        <section class="panel h-auto mb-3">
            <div class="panel-head flex-wrap">
                <i class="bi bi-sort-down"></i><h2>Saran Kantong (FEFO)</h2>
                <span class="ms-auto small text-secondary"><?= e($p['kode']) ?> <?= e($p['gol_darah'] . $p['rhesus']) ?> · tersedia <?= $jml_cocok_total ?> · butuh <?= $kurang ?> lagi</span>
            </div>
            <?php if (!$saran): ?>
                <div class="empty-state py-4"><i class="bi bi-exclamation-triangle text-warning"></i>
                    <p class="mt-2 mb-0">Tidak ada kantong <?= e($p['kode'] . ' ' . $p['gol_darah'] . $p['rhesus']) ?> yang tersedia dan belum kedaluwarsa.<br>
                    <small>Hubungi UDD untuk permintaan darah (lihat Rekomendasi Pemesanan).</small></p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th style="width:40px">#</th><th>No. Kantong</th><th>Vol.</th><th>Kedaluwarsa</th><th class="text-end">Catat hasil uji silang</th></tr></thead>
                    <tbody>
                    <?php foreach ($saran as $i => $k): ?>
                        <tr class="<?= $i === 0 ? 'table-success' : '' ?>">
                            <td class="text-secondary"><?= $i + 1 ?></td>
                            <td><code><?= e($k['no_kantong']) ?></code><?= $i === 0 ? ' <span class="badge text-bg-success">FEFO: uji ini dulu</span>' : '' ?></td>
                            <td><?= (int) $k['volume_ml'] ?> ml</td>
                            <td class="text-nowrap"><?= e(tgl_indo($k['tgl_kedaluwarsa'])) ?> <small class="text-secondary">(<?= e(teks_sisa_hari((int) $k['sisa_hari'])) ?>)</small></td>
                            <td class="text-end text-nowrap">
                                <form method="post" action="aksi.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="uji">
                                    <input type="hidden" name="id_permintaan" value="<?= $id ?>">
                                    <input type="hidden" name="id_kantong" value="<?= (int) $k['id_kantong'] ?>">
                                    <button class="btn btn-sm btn-success" name="hasil" value="COCOK" type="submit" title="Kantong menjadi DIPESAN untuk pasien ini"><i class="bi bi-check-lg"></i> Cocok</button>
                                    <button class="btn btn-sm btn-outline-danger" name="hasil" value="TIDAK_COCOK" type="submit"
                                            onclick="return confirm('Catat kantong <?= e($k['no_kantong']) ?> TIDAK COCOK untuk pasien ini?');"><i class="bi bi-x-lg"></i> Tidak cocok</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="panel-body border-top small text-secondary">
                <i class="bi bi-info-circle"></i> Hanya kantong dengan komponen dan golongan ABO + rhesus <strong>sama persis</strong> (BR-04),
                status TERSEDIA dan belum kedaluwarsa (BR-03), urut kedaluwarsa terdekat (BR-05).
                Kompatibilitas lanjutan (mis. O− untuk golongan lain): <strong>TODO: verifikasi</strong>.
                Hasil uji silang berasal dari laboratorium; sistem hanya mencatatnya.
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="panel h-auto">
            <div class="panel-head"><i class="bi bi-clipboard-check"></i><h2>Riwayat Uji Silang</h2></div>
            <?php if (!$riwayat_uji): ?>
                <div class="empty-state py-4"><p class="mb-0">Belum ada uji silang.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Waktu</th><th>No. Kantong</th><th>Hasil</th><th>Petugas</th></tr></thead>
                    <tbody>
                    <?php foreach ($riwayat_uji as $u): ?>
                        <tr>
                            <td class="text-nowrap"><small><?= e(tgl_indo($u['tgl_uji'], true)) ?></small></td>
                            <td><code><?= e($u['no_kantong']) ?></code></td>
                            <td><?= $u['hasil'] === 'COCOK' ? '<span class="badge text-bg-success">COCOK</span>' : '<span class="badge text-bg-danger">TIDAK COCOK</span>' ?></td>
                            <td><small><?= e($u['nama_petugas']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
