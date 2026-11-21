<?php
// =====================================================================
// Permintaan Darah — AKSI (POST saja)
//
//  aksi=uji    (PETUGAS)  catat uji silang; COCOK → kantong TERSEDIA → DIPESAN (BR-07)
//              Tabel: uji_silang (INSERT), kantong_darah (UPDATE), permintaan_darah (UPDATE status)
//  aksi=lepas  (PETUGAS)  kantong DIPESAN → TERSEDIA ("batal dipakai")
//              Tabel: kantong_darah (UPDATE), permintaan_darah (UPDATE status)
//  aksi=batal  (PETUGAS, atau RUANGAN pemilik permintaan)
//              permintaan → DIBATALKAN, semua kantong DIPESAN-nya → TERSEDIA
//              Tabel: permintaan_darah (UPDATE), kantong_darah (UPDATE)
//  Semua aksi: log_aktivitas (INSERT, BR-12) dan berjalan dalam satu transaksi.
//
// Perubahan status kantong memakai UPDATE ... WHERE status = '<status lama>'
// lalu memeriksa rowCount(). Jika 0 baris berubah, berarti kantong sudah
// diambil/diubah orang lain → transaksi dibatalkan. Ini mencegah satu kantong
// dipesan untuk dua pasien sekaligus (BR-06).
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
require __DIR__ . '/_fungsi.php';
$saya = wajib_login(['PETUGAS', 'RUANGAN']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/permintaan/index.php');
}
cek_csrf();

$aksi = $_POST['aksi'] ?? '';
$id   = (int) ($_POST['id_permintaan'] ?? 0);
$kembali = 'modules/permintaan/detail.php?id=' . $id;

/** Hentikan transaksi dengan pesan untuk pengguna. */
function gagal(PDO $pdo, string $pesan, string $kembali): never
{
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('danger', $pesan);
    redirect($kembali);
}

if (!in_array($aksi, ['uji', 'lepas', 'batal'], true)) {
    gagal($pdo, 'Aksi tidak dikenal.', 'modules/permintaan/index.php');
}
if ($aksi !== 'batal' && $saya['role'] !== 'PETUGAS') {
    gagal($pdo, 'Hanya petugas BDRS yang boleh melakukan aksi ini.', $kembali);
}

$pdo->beginTransaction();

// Kunci baris permintaan agar tidak diproses dua petugas bersamaan
$p = ambil_permintaan($pdo, $id, $saya, true);
if (!$p) {
    gagal($pdo, 'Permintaan tidak ditemukan.', 'modules/permintaan/index.php');
}
if (!in_array($p['status'], STATUS_BISA_DIUBAH, true)) {
    gagal($pdo, "Permintaan sudah berstatus {$p['status']} dan tidak bisa diubah lagi.", $kembali);
}

// -------------------------------------------------------------------
if ($aksi === 'uji') {
    $id_kantong = (int) ($_POST['id_kantong'] ?? 0);
    $hasil      = $_POST['hasil'] ?? '';
    if (!in_array($hasil, ['COCOK', 'TIDAK_COCOK'], true)) {
        gagal($pdo, 'Hasil uji silang tidak valid.', $kembali);
    }

    // Kantong harus cocok dengan aturan pencocokan (dicek ulang di server, jangan percaya form)
    $stmt = $pdo->prepare(
        "SELECT * FROM kantong_darah WHERE id_kantong = ? FOR UPDATE"
    );
    $stmt->execute([$id_kantong]);
    $k = $stmt->fetch();
    if (!$k) {
        gagal($pdo, 'Kantong tidak ditemukan.', $kembali);
    }
    if ((int) $k['id_komponen'] !== (int) $p['id_komponen'] || $k['gol_darah'] !== $p['gol_darah'] || $k['rhesus'] !== $p['rhesus']) {
        gagal($pdo, "Kantong {$k['no_kantong']} tidak sesuai komponen/golongan pasien (BR-04).", $kembali);
    }
    if ($k['status'] !== 'TERSEDIA') {
        gagal($pdo, "Kantong {$k['no_kantong']} berstatus {$k['status']}, bukan TERSEDIA.", $kembali);
    }
    if ($k['tgl_kedaluwarsa'] < date('Y-m-d')) {
        gagal($pdo, "Kantong {$k['no_kantong']} sudah kedaluwarsa dan tidak boleh dipesan (BR-03).", $kembali);
    }
    if ($hasil === 'COCOK') {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM kantong_darah WHERE id_permintaan_dipesan = ? AND status IN ('DIPESAN','DIKELUARKAN','DITRANSFUSIKAN')"
        );
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() >= (int) $p['jumlah_kantong']) {
            gagal($pdo, 'Jumlah kantong untuk permintaan ini sudah terpenuhi.', $kembali);
        }
    }

    $pdo->prepare("INSERT INTO uji_silang (id_permintaan, id_kantong, hasil, id_petugas) VALUES (?, ?, ?, ?)")
        ->execute([$id, $id_kantong, $hasil, (int) $saya['id_pengguna']]);
    catat_log($pdo, 'UJI_SILANG', 'uji_silang', (int) $pdo->lastInsertId(),
        "Uji silang {$k['no_kantong']} untuk {$p['no_permintaan']}: $hasil");

    if ($hasil === 'COCOK') {
        // BR-07: hanya berubah jika kantong MASIH TERSEDIA saat ini
        $upd = $pdo->prepare(
            "UPDATE kantong_darah SET status = 'DIPESAN', id_permintaan_dipesan = ?
             WHERE id_kantong = ? AND status = 'TERSEDIA' AND tgl_kedaluwarsa >= CURDATE()"
        );
        $upd->execute([$id, $id_kantong]);
        if ($upd->rowCount() !== 1) {
            gagal($pdo, "Kantong {$k['no_kantong']} baru saja dipakai pengguna lain. Pilih kantong lain.", $kembali);
        }
        catat_log($pdo, 'STATUS', 'kantong_darah', $id_kantong,
            "Kantong {$k['no_kantong']} TERSEDIA → DIPESAN untuk {$p['no_permintaan']} ({$p['no_rm']})");
        $pesan = "Kantong {$k['no_kantong']} COCOK dan kini DIPESAN untuk {$p['nama_pasien']}.";
        $tipe = 'success';
    } else {
        $pesan = "Kantong {$k['no_kantong']} dicatat TIDAK COCOK dan tidak akan disarankan lagi untuk permintaan ini.";
        $tipe = 'warning';
    }
    $status = hitung_status_permintaan($pdo, $id);
    $pdo->commit();
    set_flash($tipe, $pesan . ($status === 'TERPENUHI' ? ' Semua kantong untuk permintaan ini sudah siap.' : ''));
    redirect($kembali);
}

// -------------------------------------------------------------------
if ($aksi === 'lepas') {
    $id_kantong = (int) ($_POST['id_kantong'] ?? 0);
    $stmt = $pdo->prepare("SELECT no_kantong FROM kantong_darah WHERE id_kantong = ?");
    $stmt->execute([$id_kantong]);
    $no = $stmt->fetchColumn() ?: '?';

    $upd = $pdo->prepare(
        "UPDATE kantong_darah SET status = 'TERSEDIA', id_permintaan_dipesan = NULL
         WHERE id_kantong = ? AND id_permintaan_dipesan = ? AND status = 'DIPESAN'"
    );
    $upd->execute([$id_kantong, $id]);
    if ($upd->rowCount() !== 1) {
        gagal($pdo, "Kantong $no tidak bisa dilepas (bukan DIPESAN untuk permintaan ini).", $kembali);
    }
    catat_log($pdo, 'STATUS', 'kantong_darah', $id_kantong, "Kantong $no DIPESAN → TERSEDIA (dilepas dari {$p['no_permintaan']})");
    hitung_status_permintaan($pdo, $id);
    $pdo->commit();
    set_flash('info', "Kantong $no dikembalikan ke stok TERSEDIA.");
    redirect($kembali);
}

// -------------------------------------------------------------------
if ($aksi === 'batal') {
    $alasan = trim($_POST['alasan'] ?? '');
    if ($alasan === '' || mb_strlen($alasan) > 200) {
        gagal($pdo, 'Alasan pembatalan wajib diisi (maksimal 200 karakter).', $kembali);
    }
    // Tidak boleh batal jika sudah ada kantong yang keluar dari BDRS
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM kantong_darah WHERE id_permintaan_dipesan = ? AND status IN ('DIKELUARKAN','DITRANSFUSIKAN')");
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        gagal($pdo, 'Permintaan tidak bisa dibatalkan karena sudah ada kantong yang diserahkan ke ruangan.', $kembali);
    }

    $stmt = $pdo->prepare("SELECT id_kantong, no_kantong FROM kantong_darah WHERE id_permintaan_dipesan = ? AND status = 'DIPESAN' FOR UPDATE");
    $stmt->execute([$id]);
    $dilepas = $stmt->fetchAll();
    $upd = $pdo->prepare("UPDATE kantong_darah SET status = 'TERSEDIA', id_permintaan_dipesan = NULL WHERE id_kantong = ? AND status = 'DIPESAN'");
    foreach ($dilepas as $k) {
        $upd->execute([$k['id_kantong']]);
        catat_log($pdo, 'STATUS', 'kantong_darah', (int) $k['id_kantong'],
            "Kantong {$k['no_kantong']} DIPESAN → TERSEDIA (permintaan {$p['no_permintaan']} dibatalkan)");
    }
    $pdo->prepare("UPDATE permintaan_darah SET status = 'DIBATALKAN' WHERE id_permintaan = ?")->execute([$id]);
    catat_log($pdo, 'BATAL', 'permintaan_darah', $id, "Permintaan {$p['no_permintaan']} dibatalkan: $alasan");
    $pdo->commit();
    set_flash('info', "Permintaan {$p['no_permintaan']} dibatalkan" . ($dilepas ? '; ' . count($dilepas) . ' kantong kembali ke stok.' : '.'));
    redirect($kembali);
}
