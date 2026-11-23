<?php
// =====================================================================
// Log Aktivitas — AUDIT TRAIL (F-13, BR-12)
// Hanya ADMIN. Halaman ini HANYA MEMBACA log: tidak ada tombol ubah/hapus,
// karena jejak audit tidak boleh bisa dihapus lewat aplikasi.
// Filter: periode, pengguna, aksi, tabel, kata kunci. 50 baris per halaman.
// Bisa diunduh CSV (maks. 5000 baris sesuai filter).
// Tabel (SELECT): log_aktivitas, pengguna
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
wajib_hak('log');

$judul = 'Log Aktivitas';
$menu_aktif = 'log';
const PER_HALAMAN = 50;

function tgl_ok(?string $s): ?string
{
    $d = DateTime::createFromFormat('!Y-m-d', (string) $s);
    return $d && $d->format('Y-m-d') === $s ? $s : null;
}

// ---------- Filter ----------
$f = [
    'dari'     => tgl_ok($_GET['dari'] ?? null) ?? date('Y-m-d', strtotime('-6 days')),
    'sampai'   => tgl_ok($_GET['sampai'] ?? null) ?? date('Y-m-d'),
    'pengguna' => ctype_digit((string) ($_GET['pengguna'] ?? '')) ? (int) $_GET['pengguna'] : 0,
    'aksi'     => preg_match('/^[A-Z_]{1,50}$/', $_GET['aksi'] ?? '') ? $_GET['aksi'] : '',
    'tabel'    => preg_match('/^[a-z_]{1,50}$/', $_GET['tabel'] ?? '') ? $_GET['tabel'] : '',
    'cari'     => mb_substr(trim($_GET['cari'] ?? ''), 0, 100),
];
if ($f['dari'] > $f['sampai']) {
    [$f['dari'], $f['sampai']] = [$f['sampai'], $f['dari']];
}

$where = ['l.waktu >= ?', 'l.waktu < ? + INTERVAL 1 DAY'];
$param = [$f['dari'], $f['sampai']];
if ($f['pengguna']) { $where[] = 'l.id_pengguna = ?'; $param[] = $f['pengguna']; }
if ($f['aksi'])     { $where[] = 'l.aksi = ?';        $param[] = $f['aksi']; }
if ($f['tabel'])    { $where[] = 'l.nama_tabel = ?';  $param[] = $f['tabel']; }
if ($f['cari'] !== '') { $where[] = 'l.keterangan LIKE ?'; $param[] = '%' . $f['cari'] . '%'; }
$sql_where = implode(' AND ', $where);
$dari_tabel = "FROM log_aktivitas l LEFT JOIN pengguna p ON p.id_pengguna = l.id_pengguna";

// ---------- Unduh CSV ----------
if (($_GET['unduh'] ?? '') === 'csv') {
    $st = $pdo->prepare("SELECT l.waktu, p.username, p.role, l.aksi, l.nama_tabel, l.id_data, l.keterangan $dari_tabel WHERE $sql_where ORDER BY l.waktu DESC, l.id_log DESC LIMIT 5000");
    $st->execute($param);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="log_aktivitas_' . $f['dari'] . '_sd_' . $f['sampai'] . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Waktu', 'Username', 'Peran', 'Aksi', 'Tabel', 'ID Data', 'Keterangan'], ';');
    foreach ($st as $r) {
        // cegah formula injection di Excel: sel yang diawali = + - @ diberi tanda kutip
        $r = array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r);
        fputcsv($out, array_values($r), ';');
    }
    fclose($out);
    exit;
}

// ---------- Ringkasan & data ----------
$st = $pdo->prepare("SELECT COUNT(*) AS n, SUM(l.aksi = 'LOGIN_GAGAL') AS gagal, COUNT(DISTINCT l.id_pengguna) AS orang $dari_tabel WHERE $sql_where");
$st->execute($param);
$ring = $st->fetch();
$total = (int) $ring['n'];
$jml_hal = max(1, (int) ceil($total / PER_HALAMAN));
$hal = min($jml_hal, max(1, (int) ($_GET['hal'] ?? 1)));

$st = $pdo->prepare(
    "SELECT l.*, p.nama, p.username, p.role $dari_tabel WHERE $sql_where
     ORDER BY l.waktu DESC, l.id_log DESC LIMIT " . PER_HALAMAN . " OFFSET " . (($hal - 1) * PER_HALAMAN)
);
$st->execute($param);
$daftar = $st->fetchAll();

// Pilihan dropdown diambil dari data yang ada
$opsi_pengguna = $pdo->query("SELECT id_pengguna, nama, username FROM pengguna ORDER BY nama")->fetchAll();
$opsi_aksi  = $pdo->query("SELECT DISTINCT aksi FROM log_aktivitas ORDER BY aksi")->fetchAll(PDO::FETCH_COLUMN);
$opsi_tabel = $pdo->query("SELECT DISTINCT nama_tabel FROM log_aktivitas WHERE nama_tabel IS NOT NULL ORDER BY nama_tabel")->fetchAll(PDO::FETCH_COLUMN);

$warna_aksi = [
    'TAMBAH' => 'success', 'TERIMA' => 'success', 'UBAH' => 'warning', 'STATUS' => 'info', 'UJI_SILANG' => 'info', 'MULAI' => 'info',
    'HAPUS' => 'danger', 'BATAL' => 'danger', 'TOLAK' => 'danger','LOGIN_GAGAL' => 'danger',
    'NONAKTIF' => 'dark', 'LOGIN' => 'secondary', 'LOGOUT' => 'secondary',
];
$link = fn(array $ubah) => '?' . http_build_query(array_filter(array_merge($f, ['hal' => $hal], $ubah), fn($v) => $v !== '' && $v !== 0));

require __DIR__ . '/../../includes/header.php';
?>

<section class="panel h-auto mb-3">
    <div class="panel-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="dari">Dari</label><input class="form-control form-control-sm" type="date" id="dari" name="dari" value="<?= e($f['dari']) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="sampai">Sampai</label><input class="form-control form-control-sm" type="date" id="sampai" name="sampai" value="<?= e($f['sampai']) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="pengguna">Pengguna</label>
                <select class="form-select form-select-sm" id="pengguna" name="pengguna"><option value="">Semua</option>
                    <?php foreach ($opsi_pengguna as $o): ?><option value="<?= (int) $o['id_pengguna'] ?>" <?= $f['pengguna'] === (int) $o['id_pengguna'] ? 'selected' : '' ?>><?= e($o['nama']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="aksi">Aksi</label>
                <select class="form-select form-select-sm" id="aksi" name="aksi"><option value="">Semua</option>
                    <?php foreach ($opsi_aksi as $o): ?><option <?= $f['aksi'] === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="tabel">Tabel</label>
                <select class="form-select form-select-sm" id="tabel" name="tabel"><option value="">Semua</option>
                    <?php foreach ($opsi_tabel as $o): ?><option <?= $f['tabel'] === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="cari">Kata kunci</label><input class="form-control form-control-sm" id="cari" name="cari" value="<?= e($f['cari']) ?>" placeholder="mis. no. kantong"></div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel"></i> Terapkan</button>
                <a class="btn btn-sm btn-link" href="?">Reset</a>
                <a class="btn btn-sm btn-outline-success ms-auto" href="<?= e($link(['unduh' => 'csv', 'hal' => ''])) ?>"><i class="bi bi-filetype-csv"></i> Unduh CSV</a>
            </div>
        </form>
    </div>
</section>

<div class="d-flex flex-wrap gap-3 small mb-2">
    <span><strong><?= $total ?></strong> catatan</span>
    <span><strong><?= (int) $ring['orang'] ?></strong> pengguna</span>
    <?php if ((int) $ring['gagal'] > 0): ?>
        <a class="text-danger fw-semibold" href="<?= e($link(['aksi' => 'LOGIN_GAGAL', 'hal' => ''])) ?>"><i class="bi bi-shield-exclamation"></i> <?= (int) $ring['gagal'] ?> login gagal</a>
    <?php endif; ?>
    <span class="ms-auto text-secondary"><i class="bi bi-lock"></i> Log hanya bisa dibaca, tidak bisa diubah atau dihapus.</span>
</div>

<section class="panel h-auto">
    <?php if (!$daftar): ?>
        <div class="empty-state py-5"><i class="bi bi-journal-x"></i><p class="mt-2 mb-0">Tidak ada catatan untuk filter ini.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th class="ps-3">Waktu</th><th>Pengguna</th><th>Aksi</th><th>Data</th><th>Keterangan</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $l): ?>
                <tr class="<?= $l['aksi'] === 'LOGIN_GAGAL' ? 'table-danger' : '' ?>">
                    <td class="ps-3 text-nowrap small"><?= e(tgl_indo($l['waktu'], true)) ?></td>
                    <td class="small"><?php if ($l['username']): ?><?= e($l['nama']) ?><span class="d-block text-secondary"><?= e($l['username']) ?> · <?= e(nama_role($l['role'])) ?></span><?php else: ?><span class="text-secondary">—</span><?php endif; ?></td>
                    <td><span class="badge text-bg-<?= $warna_aksi[$l['aksi']] ?? 'light' ?>"><?= e($l['aksi']) ?></span></td>
                    <td class="small text-nowrap"><?= $l['nama_tabel'] ? '<code>' . e($l['nama_tabel']) . '</code>' . ($l['id_data'] !== null ? ' #' . (int) $l['id_data'] : '') : '—' ?></td>
                    <td class="small"><?= e($l['keterangan']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($jml_hal > 1): ?>
    <div class="panel-body border-top d-flex align-items-center gap-2 small">
        <span class="text-secondary">Halaman <?= $hal ?> dari <?= $jml_hal ?></span>
        <nav class="ms-auto" aria-label="Halaman log"><ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $hal <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($link(['hal' => $hal - 1])) ?>">‹ Sebelumnya</a></li>
            <li class="page-item <?= $hal >= $jml_hal ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($link(['hal' => $hal + 1])) ?>">Berikutnya ›</a></li>
        </ul></nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
