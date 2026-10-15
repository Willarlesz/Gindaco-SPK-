<?php
include 'db.php';
require_once 'includes/auth.php';
require_manager();
$pesan = "";

// Fungsi pemetaan pintar transaksi POS CSV ke Resep BOM & Bahan Baku
function resolveTransactionToBOM($conn, $article_code, $article_name) {
    $article_code = trim($article_code);
    $article_name_clean = trim($article_name);
    $name_upper = strtoupper($article_name_clean);

    // 1. Cek langsung di tabel menu berdasarkan kode_menu
    $q = $conn->query("SELECT id FROM menu WHERE kode_menu = '$article_code' LIMIT 1");
    if ($q && $q->num_rows > 0) {
        return ['type' => 'menu', 'id' => (int)$q->fetch_assoc()['id']];
    }

    // 2. Pemetaan kode artikel mesin POS Gindaco ke ID Menu
    $pos_code_to_menu = [
        // Original Takoyaki
        '10622408' => 1,  // Original 4 pcs
        '10622409' => 4,  // Original 6 pcs
        '10622410' => 7,  // Original 8 pcs
        '10622411' => 10, // Original Party Box 16 pcs

        // Teritama Takoyaki
        '10622412' => 2,  // Teritama 4 pcs
        '10622413' => 5,  // Teritama 6 pcs
        '10622414' => 8,  // Teritama 8 pcs
        '10622415' => 11, // Teritama Party Box 16 pcs

        // Mentaiko Cheese Takoyaki
        '10622404' => 3,  // Mentaiko 4 pcs
        '10622405' => 6,  // Mentaiko 6 pcs
        '10622406' => 9,  // Mentaiko 8 pcs
        '10622407' => 12, // Mentaiko Party Box 16 pcs

        // Triple Cheese Takoyaki
        '10698023' => 109, // Triple Cheese 4 pcs
        '10698024' => 110, // Triple Cheese 6 pcs
        '10698025' => 111, // Triple Cheese 8 pcs
        '10698026' => 112, // Triple Cheese 16 pcs

        // Party Mix
        '10622416' => 13, // Original Teritama Party Mix 16
        '10622417' => 14, // Original Mentaiko Party Mix 16
        '10622418' => 15, // Teritama Mentaiko Party Mix 16
        '10698027' => 113, // Triple Cheese Mix Original
        '10698028' => 114, // Triple Cheese Mix Teritama
        '10698029' => 115, // Triple Cheese Mix Mentaiko

        // Okonomiyaki
        '10515225' => 61, // Hiroshima Okonomiyaki Original
        '10515226' => 62, // Hiroshima Okonomiyaki Teritama
        '10515227' => 86, // Mentaiko Okonomiyaki
        '10698031' => 86, // Okonomiyaki Triple Cheese

        // Taiyaki
        '10590982' => 43,  // Taiyaki Milk Custard
        '10590983' => 107, // TAIYAKI CHIZU
        '10590984' => 43,  // Taiyaki Chocolate
        '10698030' => 107, // Taiyaki Triple Cheese
    ];

    if (isset($pos_code_to_menu[$article_code])) {
        return ['type' => 'menu', 'id' => $pos_code_to_menu[$article_code]];
    }

    // 3. Cek langsung di tabel bahan_baku berdasarkan kode_bahan (packaging, tas, take-away)
    $q_bb = $conn->query("SELECT id, kode_bahan FROM bahan_baku WHERE kode_bahan = '$article_code' LIMIT 1");
    if ($q_bb && $q_bb->num_rows > 0) {
        return ['type' => 'bahan_direct', 'kode_bahan' => $article_code, 'qty_multiplier' => 1.0];
    }

    // 4. Mapping langsung untuk perlengkapan & add-on POS
    if ($article_code === '10460034') { // Spunbond Ojol
        return ['type' => 'bahan_direct', 'kode_bahan' => '10420731', 'qty_multiplier' => 1.0]; // SPUNBOND GINDACO
    }
    if ($article_code === '10592691') { // Box Taiyaki
        return ['type' => 'bahan_direct', 'kode_bahan' => '10591374', 'qty_multiplier' => 1.0]; // PARTY BOX TAIYAKI
    }
    if ($article_code === '10521058') { // Air Mineral
        return ['type' => 'bahan_direct', 'kode_bahan' => '10502591', 'qty_multiplier' => 1.0]; // AIR MINUM 600 ML
    }
    if ($article_code === '10238765') { // Extra Takoyaki Sauce
        return ['type' => 'bahan_direct', 'kode_bahan' => '10175143', 'qty_multiplier' => 15.0];
    }
    if ($article_code === '10238766') { // Extra Teriyaki Sauce
        return ['type' => 'bahan_direct', 'kode_bahan' => '10175144', 'qty_multiplier' => 15.0];
    }
    if ($article_code === '10238767') { // Extra Mayonaise Kewpie
        return ['type' => 'bahan_direct', 'kode_bahan' => '10175666', 'qty_multiplier' => 15.0];
    }
    if ($article_code === '10238768') { // Extra Fish Shaving
        return ['type' => 'bahan_direct', 'kode_bahan' => '10175140', 'qty_multiplier' => 2.0];
    }
    if ($article_code === '10238769') { // Extra Egg
        return ['type' => 'bahan_direct', 'kode_bahan' => '10432429', 'qty_multiplier' => 25.0];
    }
    if ($article_code === '10238770') { // Extra Mozzarella
        return ['type' => 'bahan_direct', 'kode_bahan' => '10184385', 'qty_multiplier' => 10.0];
    }
    if ($article_code === '10238771') { // Extra Mentaiko
        return ['type' => 'bahan_direct', 'kode_bahan' => '10184382', 'qty_multiplier' => 10.0];
    }

    // 5. Pencocokan berbasis Pola Nama Menu Takoyaki
    if (strpos($name_upper, 'ORIGINAL') !== false) {
        if (strpos($name_upper, '4') !== false) return ['type' => 'menu', 'id' => 1];
        if (strpos($name_upper, '6') !== false) return ['type' => 'menu', 'id' => 4];
        if (strpos($name_upper, '8') !== false) return ['type' => 'menu', 'id' => 7];
        if (strpos($name_upper, '16') !== false || strpos($name_upper, 'PARTY') !== false) return ['type' => 'menu', 'id' => 10];
    }
    if (strpos($name_upper, 'TERITAMA') !== false) {
        if (strpos($name_upper, '4') !== false) return ['type' => 'menu', 'id' => 2];
        if (strpos($name_upper, '6') !== false) return ['type' => 'menu', 'id' => 5];
        if (strpos($name_upper, '8') !== false) return ['type' => 'menu', 'id' => 8];
        if (strpos($name_upper, '16') !== false || strpos($name_upper, 'PARTY') !== false) return ['type' => 'menu', 'id' => 11];
    }
    if (strpos($name_upper, 'MENTAIKO') !== false) {
        if (strpos($name_upper, '4') !== false) return ['type' => 'menu', 'id' => 3];
        if (strpos($name_upper, '6') !== false) return ['type' => 'menu', 'id' => 6];
        if (strpos($name_upper, '8') !== false) return ['type' => 'menu', 'id' => 9];
        if (strpos($name_upper, '16') !== false || strpos($name_upper, 'PARTY') !== false) return ['type' => 'menu', 'id' => 12];
    }
    if (strpos($name_upper, 'TRIPLE CHEESE') !== false) {
        if (strpos($name_upper, '4') !== false) return ['type' => 'menu', 'id' => 109];
        if (strpos($name_upper, '6') !== false) return ['type' => 'menu', 'id' => 110];
        if (strpos($name_upper, '8') !== false) return ['type' => 'menu', 'id' => 111];
        if (strpos($name_upper, '16') !== false || strpos($name_upper, 'PARTY') !== false) return ['type' => 'menu', 'id' => 112];
    }

    // 6. Pencocokan nama bahan baku langsung
    $safe_name = $conn->real_escape_string($article_name_clean);
    $q_name = $conn->query("SELECT kode_bahan FROM bahan_baku WHERE nama_bahan LIKE '%$safe_name%' OR '$safe_name' LIKE CONCAT('%', nama_bahan, '%') LIMIT 1");
    if ($q_name && $q_name->num_rows > 0) {
        return ['type' => 'bahan_direct', 'kode_bahan' => $q_name->fetch_assoc()['kode_bahan'], 'qty_multiplier' => 1.0];
    }

    return null;
}

if (isset($_POST['upload_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file_tmp = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file_tmp, "r");
        
        // Deteksi delimiter otomatis (koma vs titik koma)
        $first_line = fgets($handle);
        $delimiter = ',';
        if (strpos($first_line, ';') !== false && substr_count($first_line, ';') > substr_count($first_line, ',')) {
            $delimiter = ';';
        }
        rewind($handle);

        // Baca header
        $header = fgetcsv($handle, 10000, $delimiter);
        
        // Pemetaan indeks kolom dinamis
        $col_date = 0;
        $col_void = 5;
        $col_code = 8;
        $col_name = 9;
        $col_qty = 10;

        if ($header) {
            foreach ($header as $idx => $h) {
                $h_clean = strtolower(trim(str_replace(['"', "'", "\xEF\xBB\xBF"], '', $h)));
                if (in_array($h_clean, ['created_date', 'date', 'tanggal'])) $col_date = $idx;
                if (in_array($h_clean, ['void', 'is_void', 'status_void'])) $col_void = $idx;
                if (in_array($h_clean, ['article_code', 'item_code', 'kode_artikel', 'kode_menu', 'code'])) $col_code = $idx;
                if (in_array($h_clean, ['article_name', 'item_name', 'nama_artikel', 'nama_menu', 'name', 'description'])) $col_name = $idx;
                if (in_array($h_clean, ['quantity', 'qty', 'jumlah', 'jumlah_terjual'])) $col_qty = $idx;
            }
        }

        $row_count = 0;
        $reduced_components_count = 0;
        $menus_processed = 0;
        $direct_processed = 0;

        $stmt_insert = $conn->prepare("INSERT INTO transaksi_penjualan (tanggal, article_code, article_name, jumlah_terjual) VALUES (?, ?, ?, ?)");

        while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
            if (count($data) > max($col_code, $col_qty)) {
                $tanggal = trim($data[$col_date] ?? date('Y-m-d'));
                // Standarisasi format tanggal Y-m-d jika berformat d/m/Y
                if (strpos($tanggal, '/') !== false) {
                    $parts = explode('/', $tanggal);
                    if (count($parts) === 3) {
                        $tanggal = strlen($parts[2]) === 4 ? "{$parts[2]}-{$parts[1]}-{$parts[0]}" : "{$parts[0]}-{$parts[1]}-{$parts[2]}";
                    }
                }
                
                $article_code = trim($data[$col_code] ?? '');
                $article_name = trim($data[$col_name] ?? '');
                $qty = intval($data[$col_qty] ?? 0);
                $void = strtolower(trim($data[$col_void] ?? 'false'));

                if ($void !== 'true' && $qty > 0 && !empty($article_code)) {
                    $row_count++;

                    // 1. Simpan baris transaksi ke tabel transaksi_penjualan
                    $stmt_insert->bind_param("sssi", $tanggal, $article_code, $article_name, $qty);
                    $stmt_insert->execute();

                    // 2. Selesaikan pemetaan ke BOM resep atau Bahan Baku
                    $resolved = resolveTransactionToBOM($conn, $article_code, $article_name);

                    if ($resolved) {
                        if ($resolved['type'] === 'menu') {
                            $id_menu = $resolved['id'];
                            $q_bom = $conn->query("SELECT * FROM menu_bom WHERE id_menu = $id_menu");
                            if ($q_bom && $q_bom->num_rows > 0) {
                                $menus_processed++;
                                while ($b = $q_bom->fetch_assoc()) {
                                    $kode_bahan = $b['kode_bahan'];
                                    $qty_butuh = (float)$b['qty_dibutuhkan'] * $qty;
                                    if ($qty_butuh > 0) {
                                        $conn->query("UPDATE bahan_baku SET stok_aktual = GREATEST(0, stok_aktual - $qty_butuh) WHERE kode_bahan = '$kode_bahan'");
                                        $reduced_components_count++;
                                    }
                                }
                            }
                        } elseif ($resolved['type'] === 'bahan_direct') {
                            $kode_bahan = $resolved['kode_bahan'];
                            $multiplier = $resolved['qty_multiplier'] ?? 1.0;
                            $qty_butuh = $qty * $multiplier;
                            $conn->query("UPDATE bahan_baku SET stok_aktual = GREATEST(0, stok_aktual - $qty_butuh) WHERE kode_bahan = '$kode_bahan'");
                            $direct_processed++;
                            $reduced_components_count++;
                        }
                    }
                }
            }
        }
        fclose($handle);

        $pesan = "
        <div class='alert alert-success shadow-sm border-0 mb-4 p-4 rounded-3' role='alert'>
            <div class='d-flex align-items-center gap-3 mb-2'>
                <div class='fs-2 text-success'><i class='fas fa-circle-check'></i></div>
                <div>
                    <h5 class='fw-bold mb-0 text-success'>Import CSV Penjualan Berhasil & Stok Terpotong Otomatis!</h5>
                    <span class='text-muted small'>Sistem telah membaca file POS dan mengeksekusi rumus pengurangan persediaan gudang.</span>
                </div>
            </div>
            <div class='row g-2 mt-2 pt-2 border-top'>
                <div class='col-sm-3'>
                    <span class='text-muted small d-block'>Transaksi Diimpor:</span>
                    <strong class='text-dark fs-6'>$row_count Baris</strong>
                </div>
                <div class='col-sm-3'>
                    <span class='text-muted small d-block'>Menu Terpetakan ke BOM:</span>
                    <strong class='text-primary fs-6'>$menus_processed Porsi</strong>
                </div>
                <div class='col-sm-3'>
                    <span class='text-muted small d-block'>Item Kemasan / Direct:</span>
                    <strong class='text-secondary fs-6'>$direct_processed Item</strong>
                </div>
                <div class='col-sm-3'>
                    <span class='text-muted small d-block'>Total Bahan Baku Tereduksi:</span>
                    <strong class='text-danger fs-6'>$reduced_components_count Komponen</strong>
                </div>
            </div>
            <div class='mt-3 pt-2 text-end'>
                <a href='bahan.php' class='btn btn-sm btn-gindaco fw-bold px-3'>
                    <i class='fas fa-boxes-stacked me-1'></i> Lihat Stok Terkini di Data Bahan
                </a>
            </div>
        </div>";
    } else {
        $pesan = "
        <div class='alert alert-danger d-flex align-items-center gap-3 shadow-sm border-0 mb-4 p-3 rounded-3' role='alert'>
            <div class='fs-3 text-danger'><i class='fas fa-triangle-exclamation'></i></div>
            <div>
                <h6 class='fw-bold mb-1'>Gagal Mengunggah Berkas CSV</h6>
                <div class='small'>Pastikan berkas CSV yang diunggah valid dan tidak rusak. Silakan coba lagi.</div>
            </div>
        </div>";
    }
}

$riwayat = $conn->query("SELECT * FROM transaksi_penjualan ORDER BY id DESC LIMIT 20");
$total_history = $riwayat ? $riwayat->num_rows : 0;

$page_title = 'Import Laporan Penjualan CSV';
$active_page = 'import';
include 'includes/header.php';
?>

<div class="container-fluid px-lg-4 py-4">

    <!-- PAGE HEADER -->
    <div class="page-header-box">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item active text-danger fw-semibold" aria-current="page">Import CSV</li>
                </ol>
            </nav>
            <h1 class="page-header-title">
                <i class="fas fa-file-csv text-success"></i> Import Laporan POS (Commodity Report)
            </h1>
            <p class="page-header-desc">
                Sinkronisasi data penjualan harian dari mesin POS untuk otomatisasi pengurangan stok bahan baku gudang berbasis Bill of Materials (BOM).
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="bahan.php" class="btn btn-soft">
                <i class="fas fa-boxes-stacked me-1"></i> Cek Stok Gudang
            </a>
            <a href="rekomendasi.php" class="btn btn-gindaco">
                <i class="fas fa-clipboard-check me-1"></i> Rekomendasi SPK
            </a>
        </div>
    </div>

    <?php echo $pesan; ?>

    <!-- STEPPER PROCESS INFO -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="p-3 bg-white rounded-3 border h-100 d-flex gap-3 align-items-start">
                <div class="badge bg-danger rounded-circle p-2 fs-6">1</div>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">Ekspor CSV dari POS</h6>
                    <p class="text-muted small mb-0">Unduh laporan Commodity Report harian dari software POS Gindaco Artha Gading.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 bg-white rounded-3 border h-100 d-flex gap-3 align-items-start">
                <div class="badge bg-primary rounded-circle p-2 fs-6">2</div>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">Unggah & Ekstraksi</h6>
                    <p class="text-muted small mb-0">Unggah berkas di bawah. Sistem akan memfilter data non-void dan merekam transaksi.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 bg-white rounded-3 border h-100 d-flex gap-3 align-items-start">
                <div class="badge bg-success rounded-circle p-2 fs-6">3</div>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">Auto Reduksi Stok (BOM)</h6>
                    <p class="text-muted small mb-0">Stok tepung, saus, nori, octopus, packaging terpotong otomatis sesuai resep porsi terjual.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- UPLOAD ZONE CARD -->
    <div class="modern-card mb-4">
        <div class="modern-card-header">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-cloud-arrow-up text-primary me-2"></i>Formulir Unggah Laporan CSV
            </h5>
            <span class="badge bg-light text-secondary border">Format .CSV didukung</span>
        </div>
        <div class="modern-card-body p-4">
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-dropzone mb-3" onclick="document.getElementById('csvInput').click();">
                    <div class="upload-icon-circle">
                        <i class="fas fa-file-csv"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Pilih atau Seret Berkas CSV ke Sini</h5>
                    <p class="text-muted small mb-3">Format berkas harus berekstensi <strong>.csv</strong> (standar AH_Commodity_Report)</p>
                    <div id="fileSelectedDisplay" class="d-none">
                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-3 py-2 fs-6">
                            <i class="fas fa-check-circle me-1"></i> <span id="fileNameSpan"></span>
                        </span>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm px-3 mt-2" id="browseBtn">
                        <i class="fas fa-folder-open me-1"></i> Telusuri Berkas Komputer
                    </button>
                    <input type="file" id="csvInput" name="csv_file" accept=".csv" class="d-none" required>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">
                        <i class="fas fa-info-circle text-info me-1"></i>Sistem akan memetakan otomatis kolom created_date, article_code, article_name, quantity ke resep BOM.
                    </span>
                    <button type="submit" name="upload_csv" class="btn btn-gindaco px-4 py-2" id="submitBtn">
                        <i class="fas fa-play me-1"></i> Upload & Proses Reduksi BOM
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RECENT TRANSACTIONS TABLE -->
    <div class="modern-card">
        <div class="modern-card-header">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-clock-rotate-left text-secondary me-2"></i>Riwayat Transaksi Penjualan Terakhir
                </h5>
                <span class="text-muted small">Menampilkan 20 transaksi terbaru yang tersimpan di database</span>
            </div>
            <a href="historis.php" class="btn btn-sm btn-soft">
                <i class="fas fa-chart-line me-1"></i> Rekap Historis Pemakaian
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-modern table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th style="width: 140px;">Tanggal</th>
                        <th style="width: 140px;">Kode Artikel</th>
                        <th>Nama Menu / Varian Takoyaki</th>
                        <th style="width: 140px;" class="text-center">Jumlah Terjual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($riwayat && $riwayat->num_rows > 0): ?>
                        <?php $no = 1; while($r = $riwayat->fetch_assoc()): ?>
                        <tr>
                            <td class="text-muted fw-semibold"><?php echo $no++; ?></td>
                            <td>
                                <span class="text-dark small fw-semibold">
                                    <i class="far fa-calendar-alt text-muted me-1"></i><?php echo htmlspecialchars($r['tanggal']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">
                                    <?php echo htmlspecialchars($r['article_code'] ?? '-'); ?>
                                </span>
                            </td>
                            <td>
                                <strong class="text-dark"><?php echo htmlspecialchars($r['article_name'] ?? 'Menu Terjual'); ?></strong>
                            </td>
                            <td class="text-center">
                                <span class="badge-pill-custom badge-subtle-primary">
                                    <i class="fas fa-bowl-food"></i> <?php echo number_format($r['jumlah_terjual']); ?> Porsi
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fs-3 d-block mb-2 text-secondary"></i>
                                Belum ada riwayat transaksi penjualan yang diimpor.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const csvInput = document.getElementById('csvInput');
    const fileSelectedDisplay = document.getElementById('fileSelectedDisplay');
    const fileNameSpan = document.getElementById('fileNameSpan');
    const dropzone = document.querySelector('.upload-dropzone');

    if (csvInput) {
        csvInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                fileNameSpan.textContent = this.files[0].name;
                fileSelectedDisplay.classList.remove('d-none');
            }
        });
    }

    if (dropzone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            }, false);
        });

        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                csvInput.files = files;
                fileNameSpan.textContent = files[0].name;
                fileSelectedDisplay.classList.remove('d-none');
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>