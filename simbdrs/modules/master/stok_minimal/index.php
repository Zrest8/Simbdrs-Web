<?php
// =====================================================================
// Master Stok Minimal — MATRIKS (F-02, dipakai BR-09 & BR-10)
// Tabel: stok_minimal (SELECT, INSERT ... ON DUPLICATE KEY UPDATE, DELETE),
//        komponen_darah & kantong_darah (SELECT), log_aktivitas (INSERT)
//
// Satu halaman untuk semua kombinasi komponen × golongan × rhesus.
// Sel kosong  = tidak ada batas (baris stok_minimal dihapus).
// Sel berisi  = batas minimal kantong tersedia; di bawahnya dashboard
//               menampilkan peringatan "stok di bawah minimum".
// Semua perubahan disimpan dalam SATU transaksi: berhasil semua atau batal semua.
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_login(['ADMIN']);

$judul = 'Stok Minimal';
$menu_aktif = 'stok_minimal';

// 8 kombinasi golongan darah, urutan kolom tabel
$golongan = [];
foreach (['A', 'B', 'AB', 'O'] as $g) {
    foreach (['+', '-'] as $r) {
        $golongan[] = ['gol' => $g, 'rh' => $r, 'key' => $g . ($r === '+' ? 'pos' : 'neg')];
    }
}

$komponen = $pdo->query("SELECT id_komponen, kode, nama_komponen FROM komponen_darah ORDER BY masa_simpan_hari, kode")->fetchAll();

// Nilai yang tersimpan sekarang: $lama[id_komponen][key] = jumlah_minimal
$lama = [];
foreach ($pdo->query("SELECT id_komponen, gol_darah, rhesus, jumlah_minimal FROM stok_minimal") as $s) {
    $lama[$s['id_komponen']][$s['gol_darah'] . ($s['rhesus'] === '+' ? 'pos' : 'neg')] = (int) $s['jumlah_minimal'];
}

// Stok tersedia sekarang (TERSEDIA & belum kedaluwarsa), untuk dibandingkan
$tersedia = [];
$q = $pdo->query(
    "SELECT id_komponen, gol_darah, rhesus, COUNT(*) AS jml
     FROM kantong_darah
     WHERE status = 'TERSEDIA' AND tgl_kedaluwarsa >= CURDATE()
     GROUP BY id_komponen, gol_darah, rhesus"
);
foreach ($q as $t) {
    $tersedia[$t['id_komponen']][$t['gol_darah'] . ($t['rhesus'] === '+' ? 'pos' : 'neg')] = (int) $t['jml'];
}

$isian = [];   // nilai form yang ditampilkan ulang bila ada error
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $input = $_POST['min'] ?? [];

    // 1) Validasi semua sel dulu, sebelum menyentuh database
    $baru = [];
    foreach ($komponen as $k) {
        foreach ($golongan as $g) {
            $v = trim((string) ($input[$k['id_komponen']][$g['key']] ?? ''));
            $isian[$k['id_komponen']][$g['key']] = $v;
            if ($v === '') {
                continue;
            }
            if (!ctype_digit($v) || (int) $v > 1000) {
                $error = "Nilai untuk {$k['kode']} {$g['gol']}{$g['rh']} harus bilangan bulat 0–1000.";
                break 2;
            }
            $baru[$k['id_komponen']][$g['key']] = (int) $v;
        }
    }

    // 2) Simpan hanya sel yang berubah, dalam satu transaksi
    if ($error === '') {
        $upsert = $pdo->prepare(
            "INSERT INTO stok_minimal (id_komponen, gol_darah, rhesus, jumlah_minimal)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE jumlah_minimal = VALUES(jumlah_minimal)"
        );
        $hapus = $pdo->prepare("DELETE FROM stok_minimal WHERE id_komponen = ? AND gol_darah = ? AND rhesus = ?");

        $perubahan = [];
        $pdo->beginTransaction();
        try {
            foreach ($komponen as $k) {
                $id = $k['id_komponen'];
                foreach ($golongan as $g) {
                    $sebelum = $lama[$id][$g['key']] ?? null;
                    $sesudah = $baru[$id][$g['key']] ?? null;
                    if ($sebelum === $sesudah) {
                        continue;
                    }
                    if ($sesudah === null) {
                        $hapus->execute([$id, $g['gol'], $g['rh']]);
                    } else {
                        $upsert->execute([$id, $g['gol'], $g['rh'], $sesudah]);
                    }
                    $perubahan[] = "{$k['kode']} {$g['gol']}{$g['rh']}: " . ($sebelum ?? '–') . ' → ' . ($sesudah ?? '–');
                }
            }
            if ($perubahan) {
                catat_log($pdo, 'UBAH', 'stok_minimal', null, 'Ubah stok minimal: ' . implode('; ', $perubahan));
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        set_flash($perubahan ? 'success' : 'info',
            $perubahan ? count($perubahan) . ' batas stok minimal diperbarui.' : 'Tidak ada perubahan.');
        header('Location: index.php');
        exit;
    }
} else {
    foreach ($lama as $id => $baris) {
        foreach ($baris as $key => $v) {
            $isian[$id][$key] = (string) $v;
        }
    }
}

require __DIR__ . '/../../../includes/header.php';
?>

<section class="panel">
    <div class="panel-head flex-wrap">
        <i class="bi bi-sliders"></i><h2>Batas Stok Minimal per Golongan Darah</h2>
        <span class="ms-auto small text-secondary">satuan: kantong</span>
        <a class="btn btn-sm btn-outline-secondary ms-2" href="<?= url('modules/master/stok_minimal/pengaturan.php') ?>"><i class="bi bi-gear"></i> Periode pemesanan</a>
    </div>

    <?php if ($error): ?>
        <div class="panel-body pb-0">
            <div class="alert alert-danger d-flex gap-2 align-items-center mb-0"><i class="bi bi-x-circle"></i><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!$komponen): ?>
        <div class="empty-state"><i class="bi bi-capsule"></i>
            <p class="mt-2 mb-0">Belum ada komponen darah. Tambahkan dulu di menu <a href="<?= url('modules/master/komponen/index.php') ?>">Komponen Darah</a>.</p></div>
    <?php else: ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="table-responsive">
            <table class="table align-middle tabel-stokmin">
                <thead>
                    <tr>
                        <th>Komponen</th>
                        <?php foreach ($golongan as $g): ?>
                            <th class="text-center"><?= e($g['gol'] . $g['rh']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($komponen as $k): $id = $k['id_komponen']; ?>
                    <tr>
                        <td><span class="badge text-bg-danger"><?= e($k['kode']) ?></span>
                            <div class="small text-secondary text-nowrap"><?= e($k['nama_komponen']) ?></div></td>
                        <?php foreach ($golongan as $g):
                            $v   = $isian[$id][$g['key']] ?? '';
                            $ada = $tersedia[$id][$g['key']] ?? 0;
                            $kurang = $v !== '' && ctype_digit($v) && $ada < (int) $v;
                        ?>
                            <td class="text-center">
                                <input class="form-control form-control-sm text-center <?= $kurang ? 'is-invalid' : '' ?>"
                                       type="number" min="0" max="1000" inputmode="numeric"
                                       name="min[<?= (int) $id ?>][<?= e($g['key']) ?>]" value="<?= e($v) ?>"
                                       aria-label="Stok minimal <?= e($k['kode'] . ' ' . $g['gol'] . $g['rh']) ?>">
                                <small class="<?= $kurang ? 'text-danger fw-semibold' : 'text-secondary' ?>">ada <?= $ada ?></small>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="panel-body border-top d-flex flex-wrap gap-3 align-items-center">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Simpan semua</button>
            <small class="text-secondary">
                <i class="bi bi-info-circle"></i>
                Kosongkan sel bila tidak perlu batas. "ada" = kantong TERSEDIA yang belum kedaluwarsa;
                <span class="text-danger">merah</span> berarti di bawah minimum (BR-09).
                Batas ini juga dipakai menghitung rekomendasi pemesanan (BR-10).
            </small>
        </div>
    </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>
