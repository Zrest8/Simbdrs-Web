<?php
// =====================================================================
// Fungsi bersama modul Permintaan Darah (F-05, F-06)
// =====================================================================

const STATUS_PERMINTAAN_AKTIF = ['MENUNGGU', 'DIPROSES', 'TERPENUHI_SEBAGIAN'];
// TERPENUHI masih boleh diubah (lepas kantong / batal) selama kantong belum diserahkan ke ruangan
const STATUS_BISA_DIUBAH = ['MENUNGGU', 'DIPROSES', 'TERPENUHI_SEBAGIAN', 'TERPENUHI'];

/** Badge berwarna untuk status permintaan. */
function badge_status_permintaan(string $status): string
{
    $warna = [
        'MENUNGGU'           => 'text-bg-secondary',
        'DIPROSES'           => 'text-bg-primary',
        'TERPENUHI_SEBAGIAN' => 'text-bg-warning',
        'TERPENUHI'          => 'text-bg-success',
        'DIBATALKAN'         => 'text-bg-dark',
    ][$status] ?? 'text-bg-light';
    return '<span class="badge ' . $warna . '">' . e(str_replace('_', ' ', $status)) . '</span>';
}

/**
 * Ambil satu permintaan lengkap + cek hak akses BR-11.
 * Mengembalikan null bila tidak ada ATAU bila pengguna RUANGAN membuka permintaan ruangan lain.
 */
function ambil_permintaan(PDO $pdo, int $id, array $saya, bool $kunci = false): ?array
{
    $stmt = $pdo->prepare(
        "SELECT p.*, ps.no_rm, ps.nama_pasien, ps.gol_darah, ps.rhesus, ps.tgl_lahir, ps.jenis_kelamin,
                r.nama_ruangan, d.nama_dokter, d.spesialis, c.kode, c.nama_komponen,
                pg.nama AS nama_peminta
         FROM permintaan_darah p
         JOIN pasien ps        ON ps.id_pasien  = p.id_pasien
         JOIN ruangan r        ON r.id_ruangan  = p.id_ruangan
         JOIN dokter d         ON d.id_dokter   = p.id_dokter
         JOIN komponen_darah c ON c.id_komponen = p.id_komponen
         JOIN pengguna pg      ON pg.id_pengguna = p.id_peminta
         WHERE p.id_permintaan = ?" . ($kunci ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) {
        return null;
    }
    if ($saya['role'] === 'RUANGAN' && (int) $p['id_ruangan'] !== (int) $saya['id_ruangan']) {
        return null;   // BR-11: perlakukan seperti tidak ada
    }
    return $p;
}

/**
 * Hitung ulang status permintaan dari data kantong & uji silang, lalu simpan.
 *
 * Definisi (keputusan desain):
 *   kantong "terpenuhi" = kantong yang lolos uji silang dan masih terikat ke permintaan ini
 *                         (status DIPESAN, DIKELUARKAN, atau DITRANSFUSIKAN).
 *   TERPENUHI          → jumlah terpenuhi ≥ jumlah_kantong yang diminta
 *   TERPENUHI_SEBAGIAN → 0 < terpenuhi < diminta
 *   DIPROSES           → sudah ada uji silang, tetapi belum ada kantong terpenuhi
 *   MENUNGGU           → belum ada uji silang sama sekali
 *   DIBATALKAN tidak pernah diubah oleh fungsi ini.
 */
function hitung_status_permintaan(PDO $pdo, int $id): string
{
    $stmt = $pdo->prepare(
        "SELECT p.status, p.jumlah_kantong,
                (SELECT COUNT(*) FROM kantong_darah k
                  WHERE k.id_permintaan_dipesan = p.id_permintaan
                    AND k.status IN ('DIPESAN','DIKELUARKAN','DITRANSFUSIKAN')) AS terpenuhi,
                (SELECT COUNT(*) FROM uji_silang u WHERE u.id_permintaan = p.id_permintaan) AS jml_uji
         FROM permintaan_darah p WHERE p.id_permintaan = ?"
    );
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    if (!$r || $r['status'] === 'DIBATALKAN') {
        return $r['status'] ?? '';
    }

    $baru = 'MENUNGGU';
    if ((int) $r['terpenuhi'] >= (int) $r['jumlah_kantong']) {
        $baru = 'TERPENUHI';
    } elseif ((int) $r['terpenuhi'] > 0) {
        $baru = 'TERPENUHI_SEBAGIAN';
    } elseif ((int) $r['jml_uji'] > 0) {
        $baru = 'DIPROSES';
    }

    if ($baru !== $r['status']) {
        $pdo->prepare("UPDATE permintaan_darah SET status = ? WHERE id_permintaan = ?")->execute([$baru, $id]);
        catat_log($pdo, 'STATUS', 'permintaan_darah', $id, "Status permintaan {$r['status']} → $baru");
    }
    return $baru;
}

/** Nomor permintaan berikutnya: PMT-YYMMDD-NNN (urut per hari). */
function nomor_permintaan_baru(PDO $pdo): string
{
    $awalan = 'PMT-' . date('ymd') . '-';
    $stmt = $pdo->prepare("SELECT MAX(no_permintaan) FROM permintaan_darah WHERE no_permintaan LIKE ?");
    $stmt->execute([$awalan . '%']);
    $terakhir = $stmt->fetchColumn();
    $urut = $terakhir ? (int) substr($terakhir, -3) + 1 : 1;
    return $awalan . str_pad((string) $urut, 3, '0', STR_PAD_LEFT);
}
