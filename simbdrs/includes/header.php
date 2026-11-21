<?php
// =====================================================================
// Kerangka atas halaman: <head>, sidebar, dan topbar.
// Sebelum require file ini, halaman mengisi:
//   $judul      → judul halaman
//   $menu_aktif → kunci menu yang disorot di sidebar (lihat sidebar.php)
// =====================================================================
$judul      = $judul ?? NAMA_APP;
$menu_aktif = $menu_aktif ?? '';
$saya       = pengguna();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> — <?= e(NAMA_APP) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
<div class="app">
    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="app-main">
        <header class="topbar">
            <button class="btn btn-link text-body d-lg-none p-0 me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Buka menu">
                <i class="bi bi-list fs-3"></i>
            </button>
            <h1 class="topbar-title"><?= e($judul) ?></h1>

            <div class="dropdown ms-auto">
                <button class="btn user-chip dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar"><?= e(strtoupper(substr($saya['nama'], 0, 1))) ?></span>
                    <span class="d-none d-sm-inline text-start lh-sm">
                        <span class="d-block fw-semibold"><?= e($saya['nama']) ?></span>
                        <small class="text-muted">
                            <?= e(nama_role($saya['role'])) ?><?= $saya['nama_ruangan'] ? ' · ' . e($saya['nama_ruangan']) : '' ?>
                        </small>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><span class="dropdown-item-text small text-muted">Login sebagai <b><?= e($saya['username']) ?></b></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= url('auth/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
                </ul>
            </div>
        </header>

        <main class="content">
            <?= tampil_flash() ?>
