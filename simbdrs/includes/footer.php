        </main>
        <footer class="app-footer">
            <?= e(NAMA_APP) ?> · Prototipe mata kuliah Manajemen Data · Data yang tampil adalah data fiktif
        </footer>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
// Halaman boleh menambahkan script sendiri lewat variabel $script_tambahan
echo $script_tambahan ?? '';
?>
</body>
</html>
