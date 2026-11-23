<?php
// =====================================================================
// Rekomendasi Pemesanan ke UDD (F-11, BR-10)
//
//   pakai     = jumlah kantong keluar BDRS (transfusi.tgl_keluar_bdrs)
//               dalam N hari terakhir, per komponen × golongan × rhesus
//   kebutuhan = ⌈ pakai / N × P ⌉          (P = periode pemesanan)
//   minimal   = stok_minimal.jumlah_minimal (0 bila belum diatur)
//   tersedia  = kantong TERSEDIA dan belum kedaluwarsa (BR-03)
//   rekomendasi = MAX(0, kebutuhan + minimal − tersedia)
//
// N dan P dibaca dari tabel pengaturan (diatur Admin). Halaman ini hanya
// membaca (SELECT); tidak ada data yang diubah.
// Tabel: pengaturan, transfusi, kantong_darah, komponen_darah, stok_minimal
// Peran: PETUGAS, KEPALA
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
$saya = wajib_hak('rekomendasi');

$judul = 'Rekomendasi Pemesanan';
$menu_aktif = 'rekomendasi';

$set = $pdo->query("SELECT kunci, nilai FROM pengaturan")->fetchAll(PDO::FETCH_KEY_PAIR);
$N = max(1, (int) ($set['periode_rata_rata_hari'] ?? 30));
$P_db = max(1, (int) ($set['periode_pemesanan_hari'] ?? 7));

// Simulasi: periode pesan boleh dicoba lain lewat ?p= (tidak disimpan)
$P = $P_db;
if (isset($_GET['p']) && ctype_digit($_GET['p']) && (int) $_GET['p'] >= 1 && (int) $_GET['p'] <= 60) {
    $P = (int) $_GET['p'];
}
$simulasi = $P !== $P_db;
$semua = isset($_GET['semua']);

$komponen = $pdo->query("SELECT id_komponen, kode, nama_komponen FROM komponen_darah ORDER BY masa_simpan_hari, kode")->fetchAll();

// Kunci gabungan: id_komponen|gol|rh
$kunci = fn($k, $g, $r) => $k . '|' . $g . '|' . $r;

$pakai = [];
$stmt = $pdo->prepare(
    "SELECT k.id_komponen, k.gol_darah, k.rhesus, COUNT(*) AS n
     FROM transfusi t JOIN kantong_darah k ON k.id_kantong = t.id_kantong
     WHERE t.tgl_keluar_bdrs >= CURDATE() - INTERVAL ? DAY
     GROUP BY k.id_komponen, k.gol_darah, k.rhesus"
);
$stmt->execute([$N]);
foreach ($stmt as $r) {
    $pakai[$kunci($r['id_komponen'], $r['gol_darah'], $r['rhesus'])] = (int) $r['n'];
}

$minimal = [];
foreach ($pdo->query("SELECT id_komponen, gol_darah, rhesus, jumlah_minimal FROM stok_minimal") as $r) {
    $minimal[$kunci($r['id_komponen'], $r['gol_darah'], $r['rhesus'])] = (int) $r['jumlah_minimal'];
}

// Tersedia + berapa di antaranya akan kedaluwarsa sebelum periode pesan habis (info saja)
$tersedia = [];
$akan_ed = [];
$stmt = $pdo->prepare(
    "SELECT id_komponen, gol_darah, rhesus, COUNT(*) AS n,
            SUM(tgl_kedaluwarsa < CURDATE() + INTERVAL ? DAY) AS ed
     FROM kantong_darah
     WHERE status = 'TERSEDIA' AND tgl_kedaluwarsa >= CURDATE()
     GROUP BY id_komponen, gol_darah, rhesus"
);
$stmt->execute([$P]);
foreach ($stmt as $r) {
    $k = $kunci($r['id_komponen'], $r['gol_darah'], $r['rhesus']);
    $tersedia[$k] = (int) $r['n'];
    $akan_ed[$k] = (int) $r['ed'];
}

// Susun baris per komponen
$baris = [];
$total_pesan = 0;
foreach ($komponen as $c) {
    foreach (['A', 'B', 'AB', 'O'] as $g) {
        foreach (['+', '-'] as $rh) {
            $k = $kunci($c['id_komponen'], $g, $rh);
            $pk = $pakai[$k] ?? 0;
            $mn = $minimal[$k] ?? 0;
            $ts = $tersedia[$k] ?? 0;
            if (!$semua && $pk === 0 && $mn === 0 && $ts === 0) {
                continue;
            }
            $butuh = intdiv($pk * $P + $N - 1, $N);          // ⌈pk/N × P⌉ dengan bilangan bulat
            $rek = max(0, $butuh + $mn - $ts);
            $total_pesan += $rek;
            $baris[$c['id_komponen']][] = [
                'gol' => $g . $rh, 'pakai' => $pk, 'rata' => $pk / $N, 'butuh' => $butuh,
                'min' => $mn, 'ada' => $ts, 'ed' => $akan_ed[$k] ?? 0, 'rek' => $rek,
            ];
        }
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="d-none d-print-block mb-3">
    <h4 class="mb-0">Draf Pesanan Darah ke UDD</h4>
    <div class="small">Dibuat <?= e(tgl_indo(date('Y-m-d H:i:s'), true)) ?> oleh <?= e($saya['nama']) ?> · periode pemesanan <?= $P ?> hari · rata-rata <?= $N ?> hari terakhir</div>
</div>

<section class="panel h-auto mb-3 d-print-none">
    <div class="panel-body d-flex flex-wrap align-items-center gap-3">
        <div class="small">
            <div>Rata-rata pemakaian dihitung dari <strong><?= $N ?> hari terakhir</strong> (kantong keluar BDRS).</div>
            <div>Pesanan menutup kebutuhan <strong><?= $P ?> hari</strong> ke depan<?= $simulasi ? ' <span class="badge text-bg-warning">simulasi — pengaturan tersimpan: ' . $P_db . ' hari</span>' : '' ?>.</div>
        </div>
        <form class="ms-auto d-flex flex-wrap gap-2 align-items-center" method="get">
            <label class="small text-secondary" for="p">Coba periode</label>
            <input class="form-control form-control-sm" style="width:80px" type="number" min="1" max="60" id="p" name="p" value="<?= $P ?>">
            <div class="form-check form-check-inline small mb-0">
                <input class="form-check-input" type="checkbox" id="semua" name="semua" value="1" <?= $semua ? 'checked' : '' ?>>
                <label class="form-check-label" for="semua">semua golongan</label>
            </div>
            <button class="btn btn-sm btn-outline-secondary" type="submit">Hitung</button>
            <?php if ($simulasi || $semua): ?><a class="btn btn-sm btn-link" href="?">reset</a><?php endif; ?>
            <button class="btn btn-sm btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Cetak draf pesanan</button>
        </form>
    </div>
</section>

<div class="row g-3 mb-3 d-print-none">
    <div class="col-sm-6 col-lg-3"><div class="panel stat h-auto"><div class="panel-body">
        <div class="small text-secondary">Total kantong disarankan dipesan</div>
        <div class="fs-2 fw-bold <?= $total_pesan > 0 ? 'text-danger' : '' ?>"><?= $total_pesan ?></div>
    </div></div></div>
    <?php foreach ($komponen as $c): $sub = array_sum(array_column($baris[$c['id_komponen']] ?? [], 'rek')); ?>
    <div class="col-6 col-sm-3 col-lg-2"><div class="panel stat h-auto"><div class="panel-body">
        <div class="small text-secondary"><?= e($c['kode']) ?></div>
        <div class="fs-4 fw-bold"><?= $sub ?> <small class="fs-6 fw-normal text-secondary">kantong</small></div>
    </div></div></div>
    <?php endforeach; ?>
</div>

<?php if (!$baris): ?>
<section class="panel h-auto">
    <div class="empty-state py-5"><i class="bi bi-graph-up-arrow"></i>
        <p class="mt-2 mb-1">Belum ada data untuk dihitung.</p>
        <p class="small text-secondary mb-0">Rekomendasi muncul setelah ada pemakaian darah, stok tersedia, atau stok minimal yang diatur Admin.</p></div>
</section>
<?php endif; ?>

<?php foreach ($komponen as $c): if (empty($baris[$c['id_komponen']])) continue; ?>
<section class="panel h-auto mb-3 rekom-blok">
    <div class="panel-head"><i class="bi bi-droplet-half"></i><h2><?= e($c['kode']) ?> <small class="fw-normal text-secondary">— <?= e($c['nama_komponen']) ?></small></h2></div>
    <div class="table-responsive">
        <table class="table align-middle text-center tabel-rekom mb-0">
            <thead><tr>
                <th class="text-start">Gol.</th>
                <th>Pemakaian<br><small class="fw-normal"><?= $N ?> hari</small></th>
                <th>Rata-rata<br><small class="fw-normal">per hari</small></th>
                <th>Kebutuhan<br><small class="fw-normal"><?= $P ?> hari</small></th>
                <th>+ Stok<br><small class="fw-normal">minimal</small></th>
                <th>− Tersedia<br><small class="fw-normal">sekarang</small></th>
                <th class="table-active">Pesan</th>
            </tr></thead>
            <tbody>
            <?php foreach ($baris[$c['id_komponen']] as $b): ?>
                <tr class="<?= $b['rek'] > 0 ? '' : 'text-secondary' ?>">
                    <td class="text-start fw-semibold"><?= e($b['gol']) ?></td>
                    <td><?= $b['pakai'] ?></td>
                    <td><?= number_format($b['rata'], 2, ',', '.') ?></td>
                    <td><?= $b['butuh'] ?></td>
                    <td><?= $b['min'] ?></td>
                    <td><?= $b['ada'] ?>
                        <?php if ($b['ed'] > 0): ?><span class="badge text-bg-warning ms-1 d-print-none" title="Akan kedaluwarsa sebelum <?= $P ?> hari">ED <?= $b['ed'] ?></span><?php endif; ?></td>
                    <td class="table-active fw-bold fs-6 <?= $b['rek'] > 0 ? 'text-danger' : '' ?>"><?= $b['rek'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="fw-semibold"><td class="text-start" colspan="6">Jumlah <?= e($c['kode']) ?></td><td class="table-active"><?= array_sum(array_column($baris[$c['id_komponen']], 'rek')) ?></td></tr></tfoot>
        </table>
    </div>
</section>
<?php endforeach; ?>

<?php if ($baris): ?>
<div class="small text-secondary">
    <p class="mb-1"><i class="bi bi-calculator"></i> Rumus BR-10: <strong>Pesan = ⌈rata-rata pemakaian per hari × <?= $P ?>⌉ + stok minimal − tersedia</strong>, paling kecil 0. Kantong kedaluwarsa tidak dihitung sebagai tersedia.</p>
    <p class="mb-1 d-print-none"><span class="badge text-bg-warning">ED n</span> = n kantong tersedia akan kedaluwarsa sebelum periode pemesanan habis. Angka ini hanya informasi dan tidak mengubah rumus; petugas bisa mempertimbangkannya saat memesan.</p>
    <p class="mb-0">Rekomendasi ini alat bantu; keputusan akhir pemesanan tetap pada petugas/Kepala BDRS.</p>
</div>
<div class="d-none d-print-flex justify-content-between mt-5 small">
    <div>Petugas BDRS<br><br><br>( ................................ )</div>
    <div>Mengetahui, Kepala BDRS<br><br><br>( ................................ )</div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
