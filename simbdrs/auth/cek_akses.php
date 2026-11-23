<?php
// =====================================================================
// Cek sesi & hak akses. Di-require di baris paling atas setiap halaman:
//
//     require __DIR__ . '/../../auth/cek_akses.php';
//     $saya = wajib_hak('permintaan', 'tambah');   // menu + aksi yang dibutuhkan
//
// Siapa boleh apa disimpan di tabel hak_akses (diatur Admin lewat menu
// "Hak Akses"), BUKAN ditulis di kode. Daftar menu & aksinya ada di
// includes/menu.php.
//
// File ini sekaligus memuat fungsi.php → koneksi.php ($pdo siap dipakai).
// =====================================================================

require_once __DIR__ . '/../includes/fungsi.php';

// Hak yang dikunci agar Admin tidak bisa mengunci dirinya sendiri keluar.
const HAK_TERKUNCI = ['ADMIN' => ['pengguna', 'hak_akses']];

/** Daftar menu (includes/menu.php) yang diindeks per kunci. */
function daftar_menu(): array
{
    static $hasil = null;
    if ($hasil === null) {
        require __DIR__ . '/../includes/menu.php';
        $hasil = [];
        foreach ($menu as $m) {
            if (isset($m['kunci'])) {
                $hasil[$m['kunci']] = $m;
            }
        }
    }
    return $hasil;
}

/**
 * Apakah pengguna yang login boleh melakukan $aksi di menu $kunci?
 * $aksi: 'lihat' | 'tambah' | 'ubah' | 'hapus'.
 * Dibaca dari tabel hak_akses sekali per halaman (disimpan di variabel static).
 */
function hak(string $kunci, string $aksi = 'lihat'): bool
{
    global $pdo;
    static $tabel = null;

    $p = pengguna();
    if (!$p) {
        return false;
    }
    $menu = daftar_menu();
    if (!isset($menu[$kunci])) {
        return false;
    }
    if (!isset($menu[$kunci]['aksi'])) {
        return true;                                     // dashboard: semua peran
    }
    if (!isset($menu[$kunci]['aksi'][$aksi])) {
        return false;                                    // aksi tidak berlaku di menu ini
    }
    if (in_array($kunci, HAK_TERKUNCI[$p['role']] ?? [], true)) {
        return true;
    }

    if ($tabel === null) {
        $st = $pdo->prepare('SELECT kunci_menu, lihat, tambah, ubah, hapus FROM hak_akses WHERE role = ?');
        $st->execute([$p['role']]);
        $tabel = [];
        foreach ($st as $r) {
            $tabel[$r['kunci_menu']] = $r;
        }
    }
    $baris = $tabel[$kunci] ?? null;
    // aksi apa pun butuh hak "lihat" lebih dulu
    return $baris && $baris['lihat'] && $baris[$aksi];
}

/** Tampilkan halaman 403 lalu berhenti. */
function tolak_akses(string $pesan): void
{
    http_response_code(403);
    $judul = 'Akses Ditolak';
    $menu_aktif = '';
    require __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state py-5"><i class="bi bi-shield-lock"></i>'
       . '<h5 class="mt-3">Akses ditolak</h5>'
       . '<p class="text-muted">' . $pesan . '</p>'
       . '<a class="btn btn-primary" href="' . url('modules/dashboard/index.php') . '">Kembali ke Dashboard</a></div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

/** Pastikan sudah login. Mengembalikan data pengguna dari sesi. */
function wajib_login(): array
{
    $p = pengguna();
    if (!$p) {
        set_flash('warning', 'Silakan login terlebih dahulu.');
        redirect('auth/login.php');
    }
    return $p;
}

/** Pastikan sudah login DAN punya hak $aksi di menu $kunci. */
function wajib_hak(string $kunci, string $aksi = 'lihat'): array
{
    $p = wajib_login();
    if (!hak($kunci, $aksi)) {
        $menu = daftar_menu();
        $label = $menu[$kunci]['label'] ?? $kunci;
        $arti = $menu[$kunci]['aksi'][$aksi] ?? $aksi;
        tolak_akses('Peran <b>' . e(nama_role($p['role'])) . '</b> tidak memiliki hak <b>'
            . e(strtolower($arti)) . '</b> di menu <b>' . e($label) . '</b>. Hubungi Admin bila perlu.');
    }
    return $p;
}
