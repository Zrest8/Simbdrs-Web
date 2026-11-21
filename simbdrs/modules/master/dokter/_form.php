<?php
// Form bersama untuk tambah.php dan edit.php.
// Variabel yang dipakai: $nama, $spesialis, $error, $aktif (edit), $mode_edit (edit)
$mode_edit = $mode_edit ?? false;
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-person-badge"></i><h2><?= $mode_edit ? 'Edit' : 'Tambah' ?> Dokter</h2>
            </div>
            <div class="panel-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex gap-2 align-items-center"><i class="bi bi-x-circle"></i><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="nama_dokter">Nama dokter <span class="text-danger">*</span></label>
                        <input class="form-control" id="nama_dokter" name="nama_dokter" maxlength="100"
                               value="<?= e($nama) ?>" placeholder="mis. dr. Budi Santoso, Sp.PD" required autofocus>
                        <div class="form-text">Gunakan nama fiktif (data dummy).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="spesialis">Spesialis</label>
                        <input class="form-control" id="spesialis" name="spesialis" maxlength="100"
                               value="<?= e($spesialis) ?>" placeholder="mis. Penyakit Dalam, Bedah, Anak (boleh kosong)">
                    </div>

                    <?php if ($mode_edit): ?>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="aktif" name="aktif" <?= $aktif ? 'checked' : '' ?>>
                        <label class="form-check-label" for="aktif">Dokter aktif</label>
                        <div class="form-text">Dokter nonaktif tidak bisa dipilih untuk permintaan baru, tetapi riwayatnya tetap tersimpan.</div>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Simpan</button>
                        <a class="btn btn-outline-secondary" href="index.php">Batal</a>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
