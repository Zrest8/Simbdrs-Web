<?php
// =====================================================================
// Cek sesi & peran. Di-require di baris paling atas setiap halaman:
//
//     require __DIR__ . '/../../auth/cek_akses.php';
//     wajib_login(['PETUGAS', 'KEPALA']);   // peran yang boleh membuka
//
// File ini sekaligus memuat fungsi.php → koneksi.php ($pdo siap dipakai).
// =====================================================================

require_once __DIR__ . '/../includes/fungsi.php';

/**
 * Pastikan pengguna sudah login dan perannya diizinkan.
 * $peran_boleh kosong = semua peran boleh.
 */
function wajib_login(array $peran_boleh = []): array
{
    $p = pengguna();

    if (!$p) {
        set_flash('warning', 'Silakan login terlebih dahulu.');
        redirect('auth/login.php');
    }

    if ($peran_boleh && !in_array($p['role'], $peran_boleh, true)) {
        http_response_code(403);
        $judul = 'Akses Ditolak';
        $menu_aktif = '';
        require __DIR__ . '/../includes/header.php';
        echo '<div class="empty-state py-5"><i class="bi bi-shield-lock"></i>'
           . '<h5 class="mt-3">Akses ditolak</h5>'
           . '<p class="text-muted">Peran <b>' . e(nama_role($p['role'])) . '</b> tidak memiliki izin membuka halaman ini.</p>'
           . '<a class="btn btn-primary" href="' . url('modules/dashboard/index.php') . '">Kembali ke Dashboard</a></div>';
        require __DIR__ . '/../includes/footer.php';
        exit;
    }

    return $p;
}
