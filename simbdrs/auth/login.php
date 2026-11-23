<?php
// =====================================================================
// Halaman login (F-01)
// Tabel: pengguna, ruangan, dokter (SELECT), log_aktivitas (INSERT)
// =====================================================================

require_once __DIR__ . '/../includes/fungsi.php';

// Sudah login? langsung ke dashboard
if (pengguna()) {
    redirect('modules/dashboard/index.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        // Ambil akun berdasarkan username (prepared statement)
        $stmt = $pdo->prepare(
            "SELECT p.id_pengguna, p.nama, p.username, p.password_hash, p.role,
                    p.id_ruangan, p.aktif, r.nama_ruangan, d.id_dokter
             FROM pengguna p
             LEFT JOIN ruangan r ON r.id_ruangan = p.id_ruangan
             LEFT JOIN dokter d  ON d.id_pengguna = p.id_pengguna   -- terisi bila akun ini milik dokter
             WHERE p.username = ?"
        );
        $stmt->execute([$username]);
        $akun = $stmt->fetch();

        // password_verify() mencocokkan password dengan hash di database
        if (!$akun || !password_verify($password, $akun['password_hash'])) {
            $error = 'Username atau password salah.';
            // Catat percobaan gagal (tanpa password) untuk audit keamanan
            catat_log($pdo, 'LOGIN_GAGAL', 'pengguna', $akun ? (int) $akun['id_pengguna'] : null,
                'Login gagal untuk username "' . mb_substr($username, 0, 50) . '"');
        } elseif ((int) $akun['aktif'] === 0) {
            $error = 'Akun ini sudah dinonaktifkan. Hubungi admin.';
            catat_log($pdo, 'LOGIN_GAGAL', 'pengguna', (int) $akun['id_pengguna'], 'Login ditolak: akun nonaktif');
        } else {
            session_regenerate_id(true);   // cegah session fixation
            unset($akun['password_hash']); // jangan simpan hash di session
            $_SESSION['pengguna'] = $akun;
            catat_log($pdo, 'LOGIN', 'pengguna', (int) $akun['id_pengguna'], 'Login berhasil');
            redirect('modules/dashboard/index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — <?= e(NAMA_APP) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="login-page">
<div class="login-wrap">
    <div class="login-brand">
        <div class="brand-logo"><i class="bi bi-droplet-fill"></i></div>
        <h1><?= e(NAMA_APP) ?></h1>
        <p class="lead">Sistem Informasi Manajemen Bank Darah Rumah Sakit Mulyosari</p>
        <ul class="login-points">
            <li><i class="bi bi-alarm"></i> Peringatan dini kedaluwarsa &amp; FEFO</li>
            <li><i class="bi bi-heart-pulse"></i> Pencatatan permintaan &amp; transfusi per pasien</li>
            <li><i class="bi bi-graph-up-arrow"></i> Rekomendasi pemesanan berbasis data</li>
        </ul>
    </div>

    <div class="login-form">
        <h2 class="mb-4">Masuk</h2>

        <?= tampil_flash() ?>
        <?php if ($error): ?>
            <div class="alert alert-danger d-flex gap-2 align-items-center"><i class="bi bi-x-circle"></i><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input class="form-control" id="username" name="username" value="<?= e($username) ?>" autocomplete="username" autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input class="form-control" id="password" name="password" type="password" autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" id="lihat-password" aria-label="Tampilkan password" aria-pressed="false"><i class="bi bi-eye"></i></button>
                </div>
            </div>
            <button class="btn btn-primary w-100 py-2" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i> Masuk</button>
        </form>
    </div>
</div>
<script>
// Tombol mata: tampilkan / sembunyikan password
const pw = document.getElementById('password');
const tombolLihat = document.getElementById('lihat-password');
tombolLihat.addEventListener('click', () => {
    const tampil = pw.type === 'password';
    pw.type = tampil ? 'text' : 'password';
    tombolLihat.setAttribute('aria-pressed', tampil);
    tombolLihat.setAttribute('aria-label', tampil ? 'Sembunyikan password' : 'Tampilkan password');
    tombolLihat.innerHTML = tampil ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
});
</script>
</body>
</html>
