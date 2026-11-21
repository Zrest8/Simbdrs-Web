<?php
// =====================================================================
// Fungsi bantu (helper) yang dipakai di semua halaman.
// File ini juga memuat pengaturan aplikasi, koneksi database ($pdo),
// dan memulai session.
// =====================================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/koneksi.php';   // → menyediakan $pdo

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Amankan teks sebelum ditampilkan ke HTML (mencegah XSS). */
function e($teks): string
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

/** Buat alamat lengkap dari path di dalam aplikasi, mis. url('auth/login.php'). */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Pindah ke halaman lain lalu hentikan script. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Data pengguna yang sedang login (atau null). */
function pengguna(): ?array
{
    return $_SESSION['pengguna'] ?? null;
}

/** Pesan singkat yang muncul sekali setelah redirect (mis. "Data tersimpan"). */
function set_flash(string $tipe, string $pesan): void
{
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function tampil_flash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $ikon = ['success' => 'check-circle', 'danger' => 'x-circle', 'warning' => 'exclamation-triangle'][$f['tipe']] ?? 'info-circle';
    return '<div class="alert alert-' . e($f['tipe']) . ' alert-dismissible fade show d-flex align-items-center gap-2" role="alert">'
         . '<i class="bi bi-' . $ikon . '"></i><div>' . e($f['pesan']) . '</div>'
         . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>';
}

/** Token CSRF: memastikan form benar-benar dikirim dari halaman SIMBDRS sendiri. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function cek_csrf(): void
{
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Permintaan tidak valid (token CSRF salah). Muat ulang halaman lalu coba lagi.');
    }
}

/** Catat aktivitas penting ke tabel log_aktivitas (audit trail, BR-12). */
function catat_log(PDO $pdo, string $aksi, ?string $tabel = null, ?int $id_data = null, ?string $keterangan = null): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO log_aktivitas (id_pengguna, aksi, nama_tabel, id_data, keterangan) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([pengguna()['id_pengguna'] ?? null, $aksi, $tabel, $id_data, $keterangan]);
}

/** Format tanggal Indonesia: 2026-11-05 → 5 Nov 2026 */
function tgl_indo(?string $tanggal, bool $dengan_jam = false): string
{
    if (!$tanggal) {
        return '-';
    }
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($tanggal);
    $hasil = date('j', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t);
    return $dengan_jam ? $hasil . ', ' . date('H:i', $t) : $hasil;
}

/** Nama peran yang ramah dibaca. */
function nama_role(string $role): string
{
    return [
        'ADMIN'   => 'Administrator',
        'PETUGAS' => 'Petugas BDRS',
        'RUANGAN' => 'Dokter / Perawat',
        'KEPALA'  => 'Kepala BDRS',
    ][$role] ?? $role;
}

/** Badge berwarna untuk status kantong darah (6 status siklus hidup). */
function badge_status_kantong(string $status): string
{
    $warna = [
        'TERSEDIA'       => 'text-bg-success',
        'DIPESAN'        => 'text-bg-warning',
        'DIKELUARKAN'    => 'text-bg-info',
        'DITRANSFUSIKAN' => 'text-bg-primary',
        'DIKEMBALIKAN'   => 'text-bg-secondary',
        'DIMUSNAHKAN'    => 'text-bg-dark',
    ][$status] ?? 'text-bg-light';
    return '<span class="badge ' . $warna . '">' . e($status) . '</span>';
}

/** Teks sisa umur kantong: "12 hari lagi", "hari ini", "lewat 3 hari". */
function teks_sisa_hari(int $sisa): string
{
    if ($sisa > 0) {
        return $sisa . ' hari lagi';
    }
    return $sisa === 0 ? 'kedaluwarsa hari ini' : 'lewat ' . abs($sisa) . ' hari';
}
