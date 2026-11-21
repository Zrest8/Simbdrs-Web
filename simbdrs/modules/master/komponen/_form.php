<?php
// Form bersama untuk tambah.php dan edit.php.
// Variabel yang dipakai: $k (array data form), $error, $mode_edit (edit), $jml_kantong (edit)
$mode_edit = $mode_edit ?? false;
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-capsule"></i><h2><?= $mode_edit ? 'Edit' : 'Tambah' ?> Komponen Darah</h2>
            </div>
            <div class="panel-body">
                <?php if ($mode_edit && ($jml_kantong ?? 0) > 0): ?>
                    <div class="alert alert-info small d-flex gap-2 align-items-start py-2">
                        <i class="bi bi-info-circle mt-1"></i>
                        <span>Sudah ada <strong><?= (int) $jml_kantong ?> kantong</strong> dengan komponen ini. Mengubah masa simpan
                        <strong>hanya berlaku untuk kantong yang diterima setelah ini</strong>; tanggal kedaluwarsa kantong lama tidak berubah.</span>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex gap-2 align-items-center"><i class="bi bi-x-circle"></i><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <label class="form-label" for="kode">Kode <span class="text-danger">*</span></label>
                            <input class="form-control" id="kode" name="kode" maxlength="10"
                                   value="<?= e($k['kode']) ?>" placeholder="mis. PRC" required autofocus>
                        </div>
                        <div class="col-sm-8">
                            <label class="form-label" for="nama_komponen">Nama komponen <span class="text-danger">*</span></label>
                            <input class="form-control" id="nama_komponen" name="nama_komponen" maxlength="100"
                                   value="<?= e($k['nama_komponen']) ?>" placeholder="mis. Packed Red Cell" required>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label" for="suhu_min">Suhu simpan minimum</label>
                            <div class="input-group">
                                <input class="form-control" type="number" step="0.1" id="suhu_min" name="suhu_min" value="<?= e($k['suhu_min']) ?>">
                                <span class="input-group-text">°C</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="suhu_max">Suhu simpan maksimum</label>
                            <div class="input-group">
                                <input class="form-control" type="number" step="0.1" id="suhu_max" name="suhu_max" value="<?= e($k['suhu_max']) ?>">
                                <span class="input-group-text">°C</span>
                            </div>
                        </div>
                        <div class="col-12"><div class="form-text mt-0">Kosongkan keduanya jika komponen disimpan beku atau suhunya belum diverifikasi.</div></div>

                        <div class="col-sm-6">
                            <label class="form-label" for="masa_simpan_hari">Masa simpan <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input class="form-control" type="number" min="1" max="3650" id="masa_simpan_hari" name="masa_simpan_hari" value="<?= e($k['masa_simpan_hari']) ?>" required>
                                <span class="input-group-text">hari</span>
                            </div>
                            <div class="form-text">Tgl kedaluwarsa = tgl aftap + masa simpan (BR-01).</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="batas_peringatan_hari">Batas peringatan <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input class="form-control" type="number" min="0" id="batas_peringatan_hari" name="batas_peringatan_hari" value="<?= e($k['batas_peringatan_hari']) ?>" required>
                                <span class="input-group-text">hari</span>
                            </div>
                            <div class="form-text">Kantong ditandai "hampir kedaluwarsa" bila sisa harinya ≤ angka ini (BR-08).</div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Simpan</button>
                        <a class="btn btn-outline-secondary" href="index.php">Batal</a>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
