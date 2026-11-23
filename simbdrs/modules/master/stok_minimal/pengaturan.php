<?php
// =====================================================================
// Pengaturan Pemesanan (dipakai BR-10 Rekomendasi Pemesanan)
// Tabel: pengaturan (SELECT, UPDATE), log_aktivitas (INSERT)
//   periode_pemesanan_hari : pesanan ke UDD menutup kebutuhan N hari ke depan
//   periode_rata_rata_hari : rentang hari untuk menghitung rata-rata pemakaian
// Angka ini kebijakan BDRS, bukan nilai medis; disimpan di database agar
// bisa diubah tanpa mengubah kode.
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_hak('stok_minimal', 'ubah');

$judul = 'Periode Pemesanan';
$menu_aktif = 'stok_minimal';

// kunci => [label, min, maks]
$aturan = [
    'periode_pemesanan_hari' => ['Periode pemesanan (hari)', 1, 60, 'Pesanan ke UDD dihitung untuk kebutuhan sekian hari ke depan.'],
    'periode_rata_rata_hari' => ['Rentang rata-rata pemakaian (hari)', 7, 180, 'Pemakaian dihitung rata-rata per hari selama sekian hari terakhir.'],
];

$lama = [];
foreach ($pdo->query("SELECT kunci, nilai FROM pengaturan") as $p) {
    $lama[$p['kunci']] = $p['nilai'];
}
$isian = [];
foreach ($aturan as $kunci => $_) {
    $isian[$kunci] = $lama[$kunci] ?? '';
}
$galat = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    foreach ($aturan as $kunci => [$label, $min, $maks]) {
        $v = trim($_POST[$kunci] ?? '');
        $isian[$kunci] = $v;
        if (!preg_match('/^\d+$/', $v) || (int) $v < $min || (int) $v > $maks) {
            $galat[$kunci] = "Isi bilangan bulat {$min}–{$maks}.";
        }
    }
    if (!$galat) {
        $pdo->beginTransaction();
        $simpan = $pdo->prepare(
            "INSERT INTO pengaturan (kunci, nilai, keterangan) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)"
        );
        $berubah = [];
        foreach ($aturan as $kunci => [$label, , , $ket]) {
            $baru = (string) (int) $isian[$kunci];
            if (($lama[$kunci] ?? null) !== $baru) {
                $simpan->execute([$kunci, $baru, 'BR-10: ' . $ket]);
                $berubah[] = "$kunci: " . ($lama[$kunci] ?? '-') . " → $baru";
            }
        }
        if ($berubah) {
            catat_log($pdo, 'UBAH', 'pengaturan', null, 'Ubah pengaturan pemesanan: ' . implode('; ', $berubah));
        }
        $pdo->commit();
        set_flash('success', $berubah ? 'Pengaturan pemesanan disimpan.' : 'Tidak ada perubahan.');
        redirect('modules/master/stok_minimal/pengaturan.php');
    }
}

require __DIR__ . '/../../../includes/header.php';
?>

<a class="btn btn-sm btn-link px-0 mb-2" href="<?= url('modules/master/stok_minimal/index.php') ?>"><i class="bi bi-arrow-left"></i> Kembali ke Stok Minimal</a>

<form method="post" class="panel h-auto" style="max-width:640px" novalidate>
    <div class="panel-head"><i class="bi bi-gear"></i><h2>Pengaturan Rekomendasi Pemesanan</h2></div>
    <div class="panel-body">
        <?= csrf_field() ?>
        <?php foreach ($aturan as $kunci => [$label, $min, $maks, $ket]): ?>
        <div class="mb-3">
            <label class="form-label" for="<?= $kunci ?>"><?= e($label) ?></label>
            <input class="form-control<?= isset($galat[$kunci]) ? ' is-invalid' : '' ?>" style="max-width:160px" type="number" min="<?= $min ?>" max="<?= $maks ?>"
                   id="<?= $kunci ?>" name="<?= $kunci ?>" value="<?= e($isian[$kunci]) ?>">
            <?php if (isset($galat[$kunci])): ?><div class="invalid-feedback"><?= e($galat[$kunci]) ?></div><?php endif; ?>
            <div class="form-text"><?= e($ket) ?></div>
        </div>
        <?php endforeach; ?>
        <div class="alert alert-light border small mb-0">
            Rumus BR-10: <strong>⌈rata-rata pemakaian harian × periode pemesanan⌉ + stok minimal − stok tersedia</strong>, paling kecil 0.
        </div>
    </div>
    <div class="panel-body border-top text-end"><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Simpan</button></div>
</form>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>
