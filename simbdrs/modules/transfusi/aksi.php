<?php
// =====================================================================
// Transfusi — AKSI (POST saja)
//
//  serahkan (PETUGAS)  kantong DIPESAN → DIKELUARKAN; INSERT transfusi
//                      (id_kantong UNIQUE → BR-06 satu kantong satu pasien)
//  mulai    (RUANGAN)  verifikasi no. kantong + No. RM pasien, UPDATE transfusi.tgl_mulai
//  selesai  (RUANGAN)  UPDATE transfusi.tgl_selesai; kantong DIKELUARKAN → DITRANSFUSIKAN
//  Semua: log_aktivitas (BR-12), satu transaksi, perubahan status dijaga WHERE status lama.
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
$saya = wajib_login(['PETUGAS', 'RUANGAN']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/transfusi/index.php');
}
cek_csrf();

const KEMBALI = 'modules/transfusi/index.php';

function gagal(PDO $pdo, string $pesan): never
{
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('danger', $pesan);
    redirect(KEMBALI);
}

/** Baca datetime-local; kosong = sekarang. Tidak boleh di masa depan. */
function baca_waktu(PDO $pdo, string $nilai): string
{
    if ($nilai === '') {
        return date('Y-m-d H:i:s');
    }
    $w = DateTime::createFromFormat('Y-m-d\TH:i', $nilai);
    if (!$w || $w->format('Y-m-d\TH:i') !== $nilai) {
        gagal($pdo, 'Format waktu tidak valid.');
    }
    if ($w > new DateTime('+5 minutes')) {
        gagal($pdo, 'Waktu tidak boleh di masa depan.');
    }
    return $w->format('Y-m-d H:i:00');
}

$aksi = $_POST['aksi'] ?? '';
$pdo->beginTransaction();

// -------------------------------------------------------------------
if ($aksi === 'serahkan') {
    if ($saya['role'] !== 'PETUGAS') {
        gagal($pdo, 'Hanya petugas BDRS yang menyerahkan darah.');
    }
    $stmt = $pdo->prepare(
        "SELECT k.*, p.no_permintaan, p.id_pasien, p.status AS status_permintaan, r.nama_ruangan
         FROM kantong_darah k
         JOIN permintaan_darah p ON p.id_permintaan = k.id_permintaan_dipesan
         JOIN ruangan r          ON r.id_ruangan = p.id_ruangan
         WHERE k.id_kantong = ? FOR UPDATE"
    );
    $stmt->execute([(int) ($_POST['id_kantong'] ?? 0)]);
    $k = $stmt->fetch();
    if (!$k || $k['status'] !== 'DIPESAN') {
        gagal($pdo, 'Kantong tidak ditemukan atau tidak berstatus DIPESAN.');
    }
    if ($k['status_permintaan'] === 'DIBATALKAN') {
        gagal($pdo, 'Permintaan sudah dibatalkan.');
    }
    if ($k['tgl_kedaluwarsa'] < date('Y-m-d')) {
        gagal($pdo, "Kantong {$k['no_kantong']} sudah kedaluwarsa dan tidak boleh dikeluarkan (BR-03).");
    }

    $upd = $pdo->prepare("UPDATE kantong_darah SET status = 'DIKELUARKAN' WHERE id_kantong = ? AND status = 'DIPESAN'");
    $upd->execute([$k['id_kantong']]);
    if ($upd->rowCount() !== 1) {
        gagal($pdo, 'Kantong baru saja diubah pengguna lain.');
    }
    try {
        $pdo->prepare(
            "INSERT INTO transfusi (id_kantong, id_permintaan, id_pasien, tgl_keluar_bdrs, id_pencatat) VALUES (?, ?, ?, NOW(), ?)"
        )->execute([$k['id_kantong'], $k['id_permintaan_dipesan'], $k['id_pasien'], (int) $saya['id_pengguna']]);
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            gagal($pdo, "Kantong {$k['no_kantong']} sudah pernah tercatat untuk transfusi (BR-06).");
        }
        throw $ex;
    }
    catat_log($pdo, 'STATUS', 'kantong_darah', (int) $k['id_kantong'],
        "Kantong {$k['no_kantong']} DIPESAN → DIKELUARKAN ke {$k['nama_ruangan']} ({$k['no_permintaan']})");
    $pdo->commit();
    set_flash('success', "Kantong {$k['no_kantong']} diserahkan ke {$k['nama_ruangan']}.");
    redirect(KEMBALI);
}

// -------------------------------------------------------------------
if ($aksi === 'mulai' || $aksi === 'selesai') {
    if ($saya['role'] !== 'RUANGAN') {
        gagal($pdo, 'Pencatatan transfusi dilakukan oleh dokter/perawat ruangan.');
    }
    $stmt = $pdo->prepare(
        "SELECT t.*, k.no_kantong, k.status AS status_kantong, ps.no_rm, ps.nama_pasien, p.id_ruangan
         FROM transfusi t
         JOIN kantong_darah k    ON k.id_kantong = t.id_kantong
         JOIN pasien ps          ON ps.id_pasien = t.id_pasien
         JOIN permintaan_darah p ON p.id_permintaan = t.id_permintaan
         WHERE t.id_transfusi = ? FOR UPDATE"
    );
    $stmt->execute([(int) ($_POST['id_transfusi'] ?? 0)]);
    $t = $stmt->fetch();
    if (!$t || (int) $t['id_ruangan'] !== (int) $saya['id_ruangan']) {   // BR-11
        gagal($pdo, 'Data transfusi tidak ditemukan.');
    }
    if ($t['status_kantong'] !== 'DIKELUARKAN') {
        gagal($pdo, "Kantong {$t['no_kantong']} berstatus {$t['status_kantong']}, tidak bisa dicatat lagi.");
    }
    $waktu = baca_waktu($pdo, trim($_POST['waktu'] ?? ''));

    if ($aksi === 'mulai') {
        if ($t['tgl_mulai']) {
            gagal($pdo, 'Transfusi ini sudah dimulai.');
        }
        // Verifikasi di samping tempat tidur: label kantong & gelang pasien harus sama dengan data
        $cek_kantong = strtoupper(trim($_POST['cek_kantong'] ?? ''));
        $cek_rm      = strtoupper(trim($_POST['cek_rm'] ?? ''));
        if ($cek_kantong !== strtoupper($t['no_kantong']) || $cek_rm !== strtoupper($t['no_rm'])) {
            $pdo->rollBack();
            $pdo->beginTransaction();
            catat_log($pdo, 'TOLAK', 'transfusi', (int) $t['id_transfusi'],
                "Verifikasi gagal: input kantong '$cek_kantong' / RM '$cek_rm', seharusnya {$t['no_kantong']} / {$t['no_rm']}");
            $pdo->commit();
            set_flash('danger', "STOP — no. kantong atau No. RM tidak sesuai. Kantong ini untuk {$t['nama_pasien']} ({$t['no_rm']}). Jangan lanjutkan transfusi; periksa ulang identitas.");
            redirect(KEMBALI);
        }
        // dibandingkan per menit, karena isian datetime-local tidak punya detik
        if ($waktu < substr($t['tgl_keluar_bdrs'], 0, 16) . ':00') {
            gagal($pdo, 'Waktu mulai tidak boleh sebelum darah keluar dari BDRS.');
        }
        $pdo->prepare("UPDATE transfusi SET tgl_mulai = ?, id_pencatat = ? WHERE id_transfusi = ?")
            ->execute([$waktu, (int) $saya['id_pengguna'], $t['id_transfusi']]);
        catat_log($pdo, 'MULAI', 'transfusi', (int) $t['id_transfusi'],
            "Transfusi {$t['no_kantong']} ke {$t['no_rm']} dimulai (identitas terverifikasi)");
        $pdo->commit();
        set_flash('success', "Identitas cocok. Transfusi kantong {$t['no_kantong']} untuk {$t['nama_pasien']} dimulai.");
        redirect(KEMBALI);
    }

    // selesai
    if (!$t['tgl_mulai']) {
        gagal($pdo, 'Catat waktu mulai terlebih dahulu.');
    }
    if ($waktu < $t['tgl_mulai']) {
        gagal($pdo, 'Waktu selesai tidak boleh sebelum waktu mulai.');
    }
    $upd = $pdo->prepare("UPDATE kantong_darah SET status = 'DITRANSFUSIKAN' WHERE id_kantong = ? AND status = 'DIKELUARKAN'");
    $upd->execute([$t['id_kantong']]);
    if ($upd->rowCount() !== 1) {
        gagal($pdo, 'Kantong baru saja diubah pengguna lain.');
    }
    $pdo->prepare("UPDATE transfusi SET tgl_selesai = ?, id_pencatat = ? WHERE id_transfusi = ?")
        ->execute([$waktu, (int) $saya['id_pengguna'], $t['id_transfusi']]);
    catat_log($pdo, 'STATUS', 'kantong_darah', (int) $t['id_kantong'],
        "Kantong {$t['no_kantong']} DIKELUARKAN → DITRANSFUSIKAN ke {$t['no_rm']}");
    $pdo->commit();
    set_flash('success', "Transfusi kantong {$t['no_kantong']} selesai dicatat.");
    redirect(KEMBALI);
}

gagal($pdo, 'Aksi tidak dikenal.');
