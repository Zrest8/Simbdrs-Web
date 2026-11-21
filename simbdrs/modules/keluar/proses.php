<?php
// =====================================================================
// Pengembalian & Pemusnahan — PROSES SATU KANTONG
// GET  : tampilkan data kantong + dua formulir (kembalikan / musnahkan)
// POST jenis=kembali : INSERT pengembalian; kantong TERSEDIA → DIKEMBALIKAN
// POST jenis=musnah  : INSERT pemusnahan;   kantong TERSEDIA → DIMUSNAHKAN
// Aturan: hanya dari TERSEDIA; alasan wajib; alasan KEDALUWARSA hanya untuk
// kantong yang memang sudah lewat tanggal; LAINNYA wajib diberi keterangan.
// Status diubah dengan WHERE status='TERSEDIA' (aman dari klik ganda) dan
// dicatat di log_aktivitas (BR-12). Semua dalam satu transaksi.
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
$saya = wajib_login(['PETUGAS']);

$judul = 'Tindak Kantong';
$menu_aktif = 'keluar';

const ALASAN_MUSNAH = ['KEDALUWARSA' => 'Kedaluwarsa', 'RUSAK' => 'Kantong rusak / bocor', 'HEMOLISIS' => 'Hemolisis', 'LAINNYA' => 'Lainnya'];

function ambil_kantong(PDO $pdo, int $id, bool $kunci = false): ?array
{
    $stmt = $pdo->prepare(
        "SELECT k.*, DATEDIFF(k.tgl_kedaluwarsa, CURDATE()) AS sisa_hari, c.kode, c.nama_komponen,
                pn.no_dokumen, pn.tgl_terima, u.nama_udd
         FROM kantong_darah k
         JOIN komponen_darah c ON c.id_komponen = k.id_komponen
         JOIN penerimaan pn    ON pn.id_penerimaan = k.id_penerimaan
         JOIN udd_asal u       ON u.id_udd = pn.id_udd
         WHERE k.id_kantong = ?" . ($kunci ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

$id = (int) ($_GET['id'] ?? $_POST['id_kantong'] ?? 0);
$k = ambil_kantong($pdo, $id);
if (!$k) {
    set_flash('danger', 'Kantong tidak ditemukan.');
    redirect('modules/keluar/index.php');
}

$galat = [];
$jenis = $_POST['jenis'] ?? '';
$isian = ['alasan_kembali' => '', 'alasan_musnah' => $k['sisa_hari'] < 0 ? 'KEDALUWARSA' : '', 'keterangan' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $isian['alasan_kembali'] = trim($_POST['alasan_kembali'] ?? '');
    $isian['alasan_musnah']  = $_POST['alasan_musnah'] ?? '';
    $isian['keterangan']     = trim($_POST['keterangan'] ?? '');

    if ($jenis === 'kembali') {
        if ($isian['alasan_kembali'] === '') {
            $galat['alasan_kembali'] = 'Alasan pengembalian wajib diisi.';
        } elseif (mb_strlen($isian['alasan_kembali']) > 255) {
            $galat['alasan_kembali'] = 'Alasan maksimal 255 karakter.';
        }
    } elseif ($jenis === 'musnah') {
        if (!array_key_exists($isian['alasan_musnah'], ALASAN_MUSNAH)) {
            $galat['alasan_musnah'] = 'Pilih alasan pemusnahan.';
        } elseif ($isian['alasan_musnah'] === 'KEDALUWARSA' && (int) $k['sisa_hari'] >= 0) {
            $galat['alasan_musnah'] = 'Kantong ini belum kedaluwarsa (berlaku s.d. ' . tgl_indo($k['tgl_kedaluwarsa']) . '). Pilih alasan lain.';
        } elseif ($isian['alasan_musnah'] === 'LAINNYA' && $isian['keterangan'] === '') {
            $galat['keterangan'] = 'Untuk alasan "Lainnya", keterangan wajib diisi.';
        }
    } else {
        $galat['umum'] = 'Jenis tindakan tidak dikenal.';
    }

    if (!$galat) {
        $pdo->beginTransaction();
        $kk = ambil_kantong($pdo, $id, true);
        if ($kk['status'] !== 'TERSEDIA') {
            $pdo->rollBack();
            set_flash('danger', "Kantong {$kk['no_kantong']} berstatus {$kk['status']}. Hanya kantong TERSEDIA yang bisa dikembalikan/dimusnahkan.");
            redirect('modules/keluar/index.php');
        }
        $status_baru = $jenis === 'kembali' ? 'DIKEMBALIKAN' : 'DIMUSNAHKAN';
        $upd = $pdo->prepare("UPDATE kantong_darah SET status = ? WHERE id_kantong = ? AND status = 'TERSEDIA'");
        $upd->execute([$status_baru, $id]);
        if ($upd->rowCount() !== 1) {
            $pdo->rollBack();
            set_flash('danger', 'Kantong baru saja diubah pengguna lain.');
            redirect('modules/keluar/index.php');
        }
        if ($jenis === 'kembali') {
            $pdo->prepare("INSERT INTO pengembalian (id_kantong, tgl_kembali, alasan, id_petugas) VALUES (?, NOW(), ?, ?)")
                ->execute([$id, $isian['alasan_kembali'], (int) $saya['id_pengguna']]);
            $ket = "Kantong {$kk['no_kantong']} TERSEDIA → DIKEMBALIKAN ke {$kk['nama_udd']}: {$isian['alasan_kembali']}";
        } else {
            $pdo->prepare("INSERT INTO pemusnahan (id_kantong, tgl_musnah, alasan, keterangan, id_petugas) VALUES (?, NOW(), ?, ?, ?)")
                ->execute([$id, $isian['alasan_musnah'], $isian['keterangan'] ?: null, (int) $saya['id_pengguna']]);
            $ket = "Kantong {$kk['no_kantong']} TERSEDIA → DIMUSNAHKAN ({$isian['alasan_musnah']})";
        }
        catat_log($pdo, 'STATUS', 'kantong_darah', $id, $ket);
        $pdo->commit();
        set_flash('success', "Kantong {$kk['no_kantong']} " . ($jenis === 'kembali' ? "dicatat dikembalikan ke {$kk['nama_udd']}." : 'dicatat dimusnahkan.'));
        redirect('modules/keluar/index.php?tab=' . ($jenis === 'kembali' ? 'kembali' : 'musnah'));
    }
}

$bisa = $k['status'] === 'TERSEDIA';
$sisa = (int) $k['sisa_hari'];
$cls = fn(string $f) => isset($galat[$f]) ? ' is-invalid' : '';
require __DIR__ . '/../../includes/header.php';
?>

<a class="btn btn-sm btn-link px-0 mb-2" href="<?= url('modules/keluar/index.php') ?>"><i class="bi bi-arrow-left"></i> Kembali ke daftar</a>

<?php if (isset($galat['umum'])): ?><div class="alert alert-danger"><?= e($galat['umum']) ?></div><?php endif; ?>

<section class="panel h-auto mb-3">
    <div class="panel-head"><i class="bi bi-droplet"></i><h2>Kantong <code><?= e($k['no_kantong']) ?></code></h2><span class="ms-auto"><?= badge_status_kantong($k['status']) ?></span></div>
    <div class="panel-body">
        <div class="row g-3 small">
            <div class="col-6 col-md-3"><div class="text-secondary">Komponen</div><div class="fw-semibold"><?= e($k['kode'] . ' — ' . $k['nama_komponen']) ?></div></div>
            <div class="col-6 col-md-2"><div class="text-secondary">Golongan</div><div class="fw-semibold"><?= e($k['gol_darah'] . $k['rhesus']) ?></div></div>
            <div class="col-6 col-md-2"><div class="text-secondary">Volume</div><div class="fw-semibold"><?= (int) $k['volume_ml'] ?> mL</div></div>
            <div class="col-6 col-md-5"><div class="text-secondary">Kedaluwarsa</div><div class="fw-semibold <?= $sisa < 0 ? 'text-danger' : '' ?>"><?= e(tgl_indo($k['tgl_kedaluwarsa'])) ?> · <?= e(teks_sisa_hari($sisa)) ?></div></div>
            <div class="col-6 col-md-3"><div class="text-secondary">UDD Asal</div><div class="fw-semibold"><?= e($k['nama_udd']) ?></div></div>
            <div class="col-6 col-md-4"><div class="text-secondary">Dokumen penerimaan</div><div class="fw-semibold"><?= e($k['no_dokumen']) ?> · <?= e(tgl_indo($k['tgl_terima'])) ?></div></div>
        </div>
    </div>
</section>

<?php if (!$bisa): ?>
    <div class="alert alert-warning">Kantong ini berstatus <strong><?= e($k['status']) ?></strong>. Hanya kantong TERSEDIA yang bisa dikembalikan atau dimusnahkan.
        <?php if ($k['status'] === 'DIPESAN'): ?>Lepas dulu dari permintaannya di halaman Permintaan Darah.<?php endif; ?></div>
<?php else: ?>
<div class="row g-3">
    <div class="col-lg-6">
        <form method="post" class="panel h-auto" novalidate>
            <div class="panel-head"><i class="bi bi-arrow-return-left"></i><h2>Kembalikan ke UDD</h2></div>
            <div class="panel-body">
                <?= csrf_field() ?>
                <input type="hidden" name="jenis" value="kembali">
                <input type="hidden" name="id_kantong" value="<?= (int) $k['id_kantong'] ?>">
                <p class="small text-secondary">Kantong akan dicatat dikembalikan ke <strong><?= e($k['nama_udd']) ?></strong> (UDD asal, dibaca dari data penerimaan).</p>
                <label class="form-label" for="alasan_kembali">Alasan <span class="text-danger">*</span></label>
                <input class="form-control<?= $cls('alasan_kembali') ?>" id="alasan_kembali" name="alasan_kembali" maxlength="255"
                       value="<?= e($isian['alasan_kembali']) ?>" placeholder="mis. kelebihan stok, label tidak sesuai">
                <?php if (isset($galat['alasan_kembali'])): ?><div class="invalid-feedback"><?= e($galat['alasan_kembali']) ?></div><?php endif; ?>
            </div>
            <div class="panel-body border-top text-end">
                <button class="btn btn-outline-primary" type="submit" onclick="return confirm('Catat kantong <?= e($k['no_kantong']) ?> DIKEMBALIKAN ke UDD?');"><i class="bi bi-arrow-return-left"></i> Kembalikan</button>
            </div>
        </form>
    </div>
    <div class="col-lg-6">
        <form method="post" class="panel h-auto" novalidate>
            <div class="panel-head"><i class="bi bi-trash3"></i><h2>Musnahkan</h2></div>
            <div class="panel-body">
                <?= csrf_field() ?>
                <input type="hidden" name="jenis" value="musnah">
                <input type="hidden" name="id_kantong" value="<?= (int) $k['id_kantong'] ?>">
                <label class="form-label" for="alasan_musnah">Alasan <span class="text-danger">*</span></label>
                <select class="form-select<?= $cls('alasan_musnah') ?>" id="alasan_musnah" name="alasan_musnah">
                    <option value="">— pilih —</option>
                    <?php foreach (ALASAN_MUSNAH as $nilai => $label): ?>
                        <option value="<?= $nilai ?>" <?= $isian['alasan_musnah'] === $nilai ? 'selected' : '' ?> <?= $nilai === 'KEDALUWARSA' && $sisa >= 0 ? 'disabled' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($galat['alasan_musnah'])): ?><div class="invalid-feedback"><?= e($galat['alasan_musnah']) ?></div><?php endif; ?>
                <label class="form-label mt-3" for="keterangan">Keterangan</label>
                <textarea class="form-control<?= $cls('keterangan') ?>" id="keterangan" name="keterangan" rows="2" maxlength="1000"
                          placeholder="wajib bila alasan Lainnya"><?= e($isian['keterangan']) ?></textarea>
                <?php if (isset($galat['keterangan'])): ?><div class="invalid-feedback"><?= e($galat['keterangan']) ?></div><?php endif; ?>
                <p class="small text-secondary mt-2 mb-0"><i class="bi bi-info-circle"></i> Prosedur fisik pemusnahan mengikuti SPO limbah medis rumah sakit. <!-- TODO: verifikasi dengan SPO RS --></p>
            </div>
            <div class="panel-body border-top text-end">
                <button class="btn btn-danger" type="submit" onclick="return confirm('Catat kantong <?= e($k['no_kantong']) ?> DIMUSNAHKAN? Tindakan ini tidak bisa dibatalkan.');"><i class="bi bi-trash3"></i> Musnahkan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
