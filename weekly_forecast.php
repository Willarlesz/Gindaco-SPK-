<?php
/**
 * weekly_forecast.php - Hybrid Reactive & Server-Side Weekly Sales Forecasting
 * 
 * Fitur:
 * 1. Client-Side Instant Reactive Parsing (FileReader API):
 *    - Begitu 2 file CSV dipilih/diunggah, JavaScript langsung membaca data di browser.
 *    - Otomatis filter void == False / 0 / f / no.
 *    - Full Outer Join item Minggu 1 & Minggu 2 (jika kosong diisi 0).
 *    - Kalkulasi reaktif Exponential Smoothing saat itu juga tanpa perlu reload halaman!
 *    - Slider Alpha langsung mengkalkulasi ulang seketika (real-time).
 * 2. Server-Side Fallback & Export:
 *    - Mendukung POST submission standar untuk server recording & audit trail.
 * 3. Manual Override:
 *    - Kolom input manual per baris dengan live highlighting dan update Final Forecast otomatis.
 * 4. Summary KPI Card real-time.
 */

include 'db.php';
require_once 'includes/auth.php';
require_login();

$user_is_manager = is_manager();
$page_title = 'Weekly Sales Forecasting (Exponential Smoothing)';
$active_page = 'weekly_forecast';

// Helper deteksi delimiter
function detect_csv_delimiter($filePath) {
    $delimiters = [',', ';', "\t"];
    $firstLine = '';
    if (($handle = fopen($filePath, 'r')) !== FALSE) {
        $firstLine = fgets($handle);
        fclose($handle);
    }
    $maxCount = 0;
    $chosen = ',';
    foreach ($delimiters as $delim) {
        $count = substr_count($firstLine, $delim);
        if ($count > $maxCount) {
            $maxCount = $count;
            $chosen = $delim;
        }
    }
    return $chosen;
}

// Helper parsing server-side
function parse_weekly_sales_csv($filePath) {
    if (!file_exists($filePath)) {
        return ['error' => 'File tidak ditemukan di server.'];
    }

    $delimiter = detect_csv_delimiter($filePath);
    $handle = fopen($filePath, 'r');
    if ($handle === FALSE) {
        return ['error' => 'Gagal membaca file CSV.'];
    }

    $header = fgetcsv($handle, 10000, $delimiter);
    if (!$header) {
        fclose($handle);
        return ['error' => 'File CSV kosong atau tidak memiliki header.'];
    }

    $col_code = -1;
    $col_name = -1;
    $col_qty = -1;
    $col_void = -1;

    foreach ($header as $idx => $h) {
        $h_clean = strtolower(trim(str_replace(['"', "'", "\xEF\xBB\xBF"], '', $h)));
        if (in_array($h_clean, ['article_code', 'item_code', 'kode_artikel', 'kode_barang', 'kode_menu', 'code', 'plu', 'articlecode'])) {
            $col_code = $idx;
        }
        if (in_array($h_clean, ['article_name', 'item_name', 'nama_artikel', 'nama_barang', 'nama_menu', 'name', 'description', 'articlename'])) {
            $col_name = $idx;
        }
        if (in_array($h_clean, ['quantity', 'qty', 'jumlah', 'jumlah_terjual', 'qty_sold', 'sales_qty'])) {
            $col_qty = $idx;
        }
        if (in_array($h_clean, ['void', 'is_void', 'status_void', 'isvoid'])) {
            $col_void = $idx;
        }
    }

    if ($col_code === -1 || $col_qty === -1) {
        fclose($handle);
        return [
            'error' => "Format CSV tidak sesuai! Kolom 'article_code' dan 'quantity' wajib ada. Kolom ditemukan: " . implode(', ', $header)
        ];
    }

    $aggregated = [];
    $total_raw_rows = 0;
    $void_rows_ignored = 0;

    while (($row = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
        $total_raw_rows++;
        if (count($row) <= max($col_code, $col_qty)) continue;

        // Filter void == False
        if ($col_void !== -1) {
            $void_val = strtolower(trim($row[$col_void] ?? 'false'));
            if ($void_val === 'true' || $void_val === '1' || $void_val === 't' || $void_val === 'yes') {
                $void_rows_ignored++;
                continue;
            }
        }

        $code = trim($row[$col_code]);
        if (empty($code)) continue;

        $name = ($col_name !== -1 && !empty($row[$col_name])) ? trim($row[$col_name]) : $code;
        $qty = floatval(str_replace(',', '.', trim($row[$col_qty])));

        if (!isset($aggregated[$code])) {
            $aggregated[$code] = [
                'code' => $code,
                'name' => $name,
                'qty' => 0.0
            ];
        }

        $aggregated[$code]['qty'] += $qty;
        if (!empty($name) && strlen($name) > strlen($aggregated[$code]['name'])) {
            $aggregated[$code]['name'] = $name;
        }
    }

    fclose($handle);

    return [
        'data' => $aggregated,
        'total_raw_rows' => $total_raw_rows,
        'void_rows_ignored' => $void_rows_ignored
    ];
}

$server_error = '';
$server_success = '';
$server_forecast_data = [];
$alpha = isset($_POST['alpha']) ? floatval($_POST['alpha']) : 0.3;
if ($alpha < 0.1) $alpha = 0.1;
if ($alpha > 0.9) $alpha = 0.9;

// Tangani POST upload jika dikirim secara tradisional
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_forecast'])) {
    if (!isset($_FILES['file_week1']) || !isset($_FILES['file_week2']) ||
        $_FILES['file_week1']['error'] !== UPLOAD_ERR_OK || $_FILES['file_week2']['error'] !== UPLOAD_ERR_OK) {
        $server_error = "Kedua file CSV (Minggu Lalu dan Minggu Sekarang) wajib diunggah.";
    } else {
        $f1_tmp = $_FILES['file_week1']['tmp_name'];
        $f2_tmp = $_FILES['file_week2']['tmp_name'];

        $res_w1 = parse_weekly_sales_csv($f1_tmp);
        $res_w2 = parse_weekly_sales_csv($f2_tmp);

        if (isset($res_w1['error'])) {
            $server_error = "File Minggu Lalu: " . $res_w1['error'];
        } elseif (isset($res_w2['error'])) {
            $server_error = "File Minggu Sekarang: " . $res_w2['error'];
        } else {
            $data_w1 = $res_w1['data'];
            $data_w2 = $res_w2['data'];

            $all_codes = array_unique(array_merge(array_keys($data_w1), array_keys($data_w2)));
            sort($all_codes);

            foreach ($all_codes as $code) {
                $name = $data_w2[$code]['name'] ?? ($data_w1[$code]['name'] ?? $code);
                $qty_w1 = isset($data_w1[$code]) ? $data_w1[$code]['qty'] : 0.0;
                $qty_w2 = isset($data_w2[$code]) ? $data_w2[$code]['qty'] : 0.0;
                $raw_f = ($alpha * $qty_w2) + ((1.0 - $alpha) * $qty_w1);
                $sys_f = (int)round($raw_f);

                $server_forecast_data[] = [
                    'code' => $code,
                    'name' => $name,
                    'w1' => $qty_w1,
                    'w2' => $qty_w2,
                    'system_forecast' => $sys_f
                ];
            }

            catat_audit_log($conn, 'CALCULATE_WEEKLY_FORECAST', 'forecasting', 0, [
                'alpha' => $alpha,
                'total_items' => count($server_forecast_data),
                'file_w1' => $_FILES['file_week1']['name'],
                'file_w2' => $_FILES['file_week2']['name']
            ]);

            $server_success = "Berhasil memuat " . count($server_forecast_data) . " item penjualan melalui server.";
        }
    }
}

include 'includes/header.php';
?>

<div class="container-fluid px-lg-4 py-4">

    <!-- KOP SURAT PRINT RESMI -->
    <?php 
    $judul_laporan = 'Laporan Peramalan Penjualan Mingguan (Weekly Sales Forecasting)';
    $subjudul_laporan = 'Metode Single Exponential Smoothing &bull; Outlet Mall Artha Gading &bull; ' . date('d F Y');
    include 'includes/print_header.php'; 
    ?>

    <!-- PAGE HEADER -->
    <div class="page-header-box">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="smoothing.php" class="text-decoration-none text-muted">Exponential Smoothing</a></li>
                    <li class="breadcrumb-item active text-danger fw-semibold" aria-current="page">Weekly Sales Forecast</li>
                </ol>
            </nav>
            <h1 class="page-header-title">
                <i class="fas fa-calendar-week text-danger"></i> Weekly Sales Forecasting with Exponential Smoothing
            </h1>
            <p class="page-header-desc">
                Unggah 2 dataset penjualan POS/ERP mingguan, terapkan rumus Single Exponential Smoothing secara reaktif, dan sesuaikan angka dengan koreksi manual secara fleksibel.
            </p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-soft">
                <i class="fas fa-print me-1"></i> Cetak Laporan
            </button>
            <a href="rekomendasi.php" class="btn btn-gindaco fw-bold">
                <i class="fas fa-clipboard-check me-1"></i> Rekomendasi SPK Bahan Baku
            </a>
            <a href="smoothing.php" class="btn btn-outline-danger">
                <i class="fas fa-calculator me-1"></i> Smoothing Bulanan
            </a>
        </div>
    </div>

    <!-- NOTIFIKASI TOAST / ALERT STATUS -->
    <div id="statusAlertContainer">
        <?php if (!empty($server_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
                <i class="fas fa-circle-exclamation me-2 fs-5"></i>
                <strong>Gagal:</strong> <?php echo htmlspecialchars($server_error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($server_success)): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
                <i class="fas fa-circle-check me-2 fs-5"></i>
                <strong>Sukses:</strong> <?php echo htmlspecialchars($server_success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    </div>

    <!-- CARD FORM UPLOAD 2 FILE CSV & PARAMETER -->
    <div class="modern-card mb-4 no-print">
        <div class="modern-card-header">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-upload text-danger me-2"></i>Unggah Dataset Penjualan POS &amp; Parameter Peramalan
            </h5>
            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Auto-Parser CSV &amp; Reactive Engine</span>
        </div>
        <div class="modern-card-body p-4">
            <form method="POST" enctype="multipart/form-data" id="weeklyForecastForm">
                <input type="hidden" name="action_forecast" value="1">

                <div class="row g-4">
                    <!-- File Minggu 1 -->
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <label class="form-label fw-bold text-dark small text-uppercase d-flex align-items-center justify-content-between">
                                <span><i class="fas fa-file-csv text-primary me-1"></i> File 1: Aktual Minggu Lalu (F<sub>t</sub> / Baseline)</span>
                                <span class="badge bg-primary">Dataset Historis</span>
                            </label>
                            <input type="file" id="fileWeek1Input" name="file_week1" class="form-control mb-2" accept=".csv,text/csv,text/plain" required>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small" style="font-size: 0.75rem;">Total kuantitas barang minggu lalu.</span>
                                <span class="small fw-semibold text-primary" id="statusFile1">Belum dipilih</span>
                            </div>
                        </div>
                    </div>

                    <!-- File Minggu 2 -->
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <label class="form-label fw-bold text-dark small text-uppercase d-flex align-items-center justify-content-between">
                                <span><i class="fas fa-file-csv text-success me-1"></i> File 2: Aktual Minggu Sekarang (A<sub>t</sub> / Riil Terbaru)</span>
                                <span class="badge bg-success">Dataset Aktual</span>
                            </label>
                            <input type="file" id="fileWeek2Input" name="file_week2" class="form-control mb-2" accept=".csv,text/csv,text/plain" required>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small" style="font-size: 0.75rem;">Total kuantitas barang minggu sekarang.</span>
                                <span class="small fw-semibold text-success" id="statusFile2">Belum dipilih</span>
                            </div>
                        </div>
                    </div>

                    <!-- Parameter Alpha -->
                    <div class="col-lg-6">
                        <label class="form-label fw-bold text-dark small text-uppercase d-flex justify-content-between">
                            <span>Bobot Smoothing Factor (&alpha; / Alpha)</span>
                            <span class="badge bg-danger font-monospace fs-6" id="alphaBadge">&alpha; = <?php echo $alpha; ?></span>
                        </label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="range" class="form-range" id="alphaRange" min="0.1" max="0.9" step="0.05" value="<?php echo $alpha; ?>" oninput="onAlphaChange(this.value)">
                            <input type="number" step="0.01" min="0.1" max="0.9" class="form-control text-center fw-bold font-monospace" style="width: 100px;" id="alphaInput" name="alpha" value="<?php echo $alpha; ?>" oninput="onAlphaChange(this.value)">
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <span class="text-muted small align-self-center me-1">Preset:</span>
                            <button type="button" class="btn btn-xs btn-soft rounded-pill" onclick="onAlphaChange(0.2)">&alpha; = 0.2 (Halus)</button>
                            <button type="button" class="btn btn-xs btn-outline-danger rounded-pill fw-bold" onclick="onAlphaChange(0.3)">&alpha; = 0.3 (Standar Gindaco)</button>
                            <button type="button" class="btn btn-xs btn-soft rounded-pill" onclick="onAlphaChange(0.5)">&alpha; = 0.5 (Seimbang)</button>
                            <button type="button" class="btn btn-xs btn-soft rounded-pill" onclick="onAlphaChange(0.7)">&alpha; = 0.7 (Responsif)</button>
                        </div>
                    </div>

                    <!-- Info Formula & Aturan Void -->
                    <div class="col-lg-6">
                        <div class="p-3 bg-white rounded-3 border h-100">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <strong class="small text-dark"><i class="fas fa-filter text-danger me-1"></i>Aturan Pembersihan &amp; Rumus:</strong>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Filter Void: True Diabaikan</span>
                            </div>
                            <div class="text-muted small" style="font-size: 0.75rem; line-height: 1.45;">
                                &bull; Kolom Wajib: <code>article_code</code>, <code>quantity</code>.<br>
                                &bull; Kolom Opsional: <code>article_name</code>, <code>void</code>, <code>created_date</code>.<br>
                                &bull; Formula Baku: <span class="fw-bold font-monospace text-danger">F<sub>t+1</sub> = (&alpha; &middot; A<sub>t</sub>) + ((1 - &alpha;) &middot; F<sub>t</sub>)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small" id="reactiveStatusText">
                        <i class="fas fa-bolt text-warning me-1"></i> Sistem otomatis memproses data begitu kedua file CSV dipilih.
                    </span>
                    <button type="submit" class="btn btn-gindaco px-4 py-2 fw-bold shadow-sm" id="btnSubmitForm">
                        <i class="fas fa-arrows-rotate me-1"></i> Proses &amp; Hitung Forecasting
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- AREA HASIL: SUMMARY CARDS & TABEL INTERAKTIF -->
    <div id="forecastResultsContainer" style="display: none;">

        <!-- SINKRONISASI RECIPE EXPLOSION KE SPK BANNER -->
        <div id="spkSyncStatusBanner" style="display: none;"></div>

        <!-- 4 SUMMARY CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card stat-primary py-3">
                    <div>
                        <p class="stat-label mb-1">Total Item Terproses</p>
                        <h4 class="stat-value mb-0 fs-3"><span id="sumTotalItems">0</span> <span class="fs-6 fw-normal text-muted">Item</span></h4>
                    </div>
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-boxes-stacked text-primary"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-secondary py-3">
                    <div>
                        <p class="stat-label mb-1">Total Quantity Minggu Lalu</p>
                        <h4 class="stat-value mb-0 fs-3"><span id="sumQtyW1">0</span> <span class="fs-6 fw-normal text-muted">Pcs</span></h4>
                    </div>
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-calendar-minus text-secondary"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-success py-3">
                    <div>
                        <p class="stat-label mb-1">Total Quantity Minggu Sekarang</p>
                        <h4 class="stat-value mb-0 fs-3"><span id="sumQtyW2">0</span> <span class="fs-6 fw-normal text-muted">Pcs</span></h4>
                    </div>
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-chart-line text-success"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-warning py-3">
                    <div>
                        <p class="stat-label mb-1">Total Estimasi Minggu Depan</p>
                        <h4 class="stat-value mb-0 fs-3 text-dark"><span id="sumFinalForecast">0</span> <span class="fs-6 fw-normal text-muted">Pcs</span></h4>
                    </div>
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-calculator text-warning"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- MAIN TABLE CARD -->
        <div class="modern-card mb-4">
            <div class="modern-card-header flex-column flex-md-row gap-3">
                <div>
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-table text-success me-2"></i>Tabel Hasil Peramalan Penjualan &amp; Koreksi Manual
                    </h5>
                    <span class="text-muted small" id="tableItemCountText">Menampilkan 0 item barang</span>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 no-print">
                    <div class="input-group input-group-sm" style="max-width: 250px;">
                        <span class="input-group-text bg-light text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchTableInput" class="form-control bg-light" placeholder="Cari kode / nama barang...">
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetAllOverrides()" title="Kembalikan semua angka ke perhitungan sistem asli">
                        <i class="fas fa-rotate-left me-1"></i> Reset Koreksi
                    </button>

                    <button type="button" class="btn btn-sm btn-success fw-bold" onclick="exportToCSV()" title="Unduh tabel hasil ke format CSV">
                        <i class="fas fa-file-excel me-1"></i> Export to CSV
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-modern table-hover align-middle mb-0" id="weeklyForecastTable">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th style="width: 140px;">Kode Barang</th>
                            <th>Nama Barang</th>
                            <th style="width: 150px;" class="text-end">Aktual Minggu Lalu (F<sub>t</sub>)</th>
                            <th style="width: 160px;" class="text-end">Aktual Minggu Sekarang (A<sub>t</sub>)</th>
                            <th style="width: 160px;" class="text-end">Forecast Sistem</th>
                            <th style="width: 170px;" class="text-center no-print">Koreksi Manual</th>
                            <th style="width: 150px;" class="text-end">Final Forecast</th>
                        </tr>
                    </thead>
                    <tbody id="forecastTableBody">
                        <!-- Diisi secara dinamis oleh JS / Server -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- EMPTY PLACEHOLDER STATE -->
    <div id="emptyPlaceholderCard" class="modern-card p-5 text-center">
        <div class="brand-icon mx-auto mb-3" style="width: 64px; height: 64px; font-size: 1.8rem; background: #e02424;">
            <i class="fas fa-file-csv"></i>
        </div>
        <h4 class="fw-bold text-dark mb-2">Pilih 2 File CSV Mingguan untuk Memulai</h4>
        <p class="text-muted mx-auto mb-4" style="max-width: 600px;">
            Sistem otomatis memfilter transaksi void, menggabungkan seluruh komoditas barang (Full Outer Join), menghitung formula Single Exponential Smoothing, serta menyediakan kolom koreksi manual interaktif.
        </p>
        <div class="d-inline-flex align-items-center gap-2 p-2 px-3 bg-light rounded-pill border">
            <i class="fas fa-bolt text-warning"></i>
            <span class="small text-muted">Kalkulasi langsung berjalan instan di peramban saat file dipilih!</span>
        </div>
    </div>

    <!-- PRINT FOOTER -->
    <?php include 'includes/print_footer.php'; ?>
</div>

<style>
/* Highlight baris yang dikoreksi manual */
.row-overridden {
    background-color: #fffbeb !important;
    border-left: 4px solid #f59e0b !important;
}
.row-overridden .final-badge {
    background-color: #fef3c7 !important;
    color: #b45309 !important;
    border: 1px solid #fde68a !important;
}
</style>

<script>
// STATE UTAMA FORECASTING
let appForecastData = []; // [{ code, name, w1, w2, sysForecast, manualOverride }]
let currentAlpha = <?php echo $alpha; ?>;
let file1ParsedData = null; // { [code]: { code, name, qty } }
let file2ParsedData = null;

// Ubah Alpha dan hitung ulang reaktif
function onAlphaChange(val) {
    currentAlpha = parseFloat(val);
    document.getElementById('alphaRange').value = currentAlpha;
    document.getElementById('alphaInput').value = currentAlpha;
    document.getElementById('alphaBadge').innerText = 'α = ' + currentAlpha.toFixed(2);

    if (appForecastData.length > 0) {
        recalculateForecasts();
        renderForecastTable();
    }
}

// Tampilkan alert visual
function showAppAlert(message, type = 'danger') {
    const container = document.getElementById('statusAlertContainer');
    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="fas ${type === 'danger' ? 'fa-circle-exclamation' : 'fa-circle-check'} me-2 fs-5"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Deteksi delimiter file CSV di browser
function detectDelim(text) {
    const firstLine = text.split(/\r\n|\n/)[0] || '';
    const commas = (firstLine.match(/,/g) || []).length;
    const semis = (firstLine.match(/;/g) || []).length;
    const tabs = (firstLine.match(/\t/g) || []).length;
    if (semis > commas && semis > tabs) return ';';
    if (tabs > commas && tabs > semis) return '\t';
    return ',';
}

// Parse baris CSV menangani tanda kutip
function parseCSVLine(line, delimiter) {
    const result = [];
    let start = 0;
    let inQuotes = false;
    for (let i = 0; i < line.length; i++) {
        if (line[i] === '"') {
            inQuotes = !inQuotes;
        } else if (line[i] === delimiter && !inQuotes) {
            let val = line.substring(start, i).trim();
            if (val.startsWith('"') && val.endsWith('"')) {
                val = val.substring(1, val.length - 1).replace(/""/g, '"');
            }
            result.push(val);
            start = i + 1;
        }
    }
    let lastVal = line.substring(start).trim();
    if (lastVal.startsWith('"') && lastVal.endsWith('"')) {
        lastVal = lastVal.substring(1, lastVal.length - 1).replace(/""/g, '"');
    }
    result.push(lastVal);
    return result;
}

// Client-side parser untuk file CSV penjualan
function parseWeeklyCSVClient(fileText, fileName) {
    const lines = fileText.split(/\r\n|\n/).filter(l => l.trim() !== '');
    if (lines.length < 2) {
        throw new Error(`File ${fileName} kosong atau tidak memiliki baris data.`);
    }

    const delimiter = detectDelim(fileText);
    const headerRow = parseCSVLine(lines[0], delimiter);

    let colCode = -1;
    let colName = -1;
    let colQty = -1;
    let colVoid = -1;

    headerRow.forEach((h, idx) => {
        const clean = h.toLowerCase().trim().replace(/['"_\s-]/g, '');
        if (['articlecode', 'itemcode', 'kodeartikel', 'kodebarang', 'kodemenu', 'code', 'plu'].includes(clean)) colCode = idx;
        if (['articlename', 'itemname', 'namaartikel', 'namabarang', 'namamenu', 'name', 'description'].includes(clean)) colName = idx;
        if (['quantity', 'qty', 'jumlah', 'jumlahterjual', 'qtysold', 'salesqty'].includes(clean)) colQty = idx;
        if (['void', 'isvoid', 'statusvoid'].includes(clean)) colVoid = idx;
    });

    if (colCode === -1 || colQty === -1) {
        throw new Error(`Format CSV pada <strong>${fileName}</strong> tidak sesuai!<br>Wajib memiliki kolom <code>article_code</code> dan <code>quantity</code>.<br>Kolom terdeteksi: <em>${headerRow.join(', ')}</em>`);
    }

    const aggregated = {};
    let totalRows = 0;
    let voidRowsIgnored = 0;

    for (let i = 1; i < lines.length; i++) {
        const row = parseCSVLine(lines[i], delimiter);
        if (row.length <= Math.max(colCode, colQty)) continue;
        totalRows++;

        // Filter void == False
        if (colVoid !== -1) {
            const vVal = (row[colVoid] || '').toLowerCase().trim();
            if (['true', '1', 't', 'yes', 'y'].includes(vVal)) {
                voidRowsIgnored++;
                continue; // Skip item void
            }
        }

        const code = (row[colCode] || '').trim();
        if (!code) continue;

        const name = (colName !== -1 && row[colName]) ? row[colName].trim() : code;
        const qtyRaw = (row[colQty] || '0').trim().replace(/,/g, '.');
        const qty = parseFloat(qtyRaw) || 0;

        if (!aggregated[code]) {
            aggregated[code] = { code, name, qty: 0 };
        }
        aggregated[code].qty += qty;
        if (name && name.length > aggregated[code].name.length) {
            aggregated[code].name = name;
        }
    }

    return {
        data: aggregated,
        totalRows,
        voidRowsIgnored,
        uniqueItemCount: Object.keys(aggregated).length
    };
}

// Baca file async menggunakan FileReader
function readFileAsText(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = e => resolve(e.target.result);
        reader.onerror = e => reject(new Error('Gagal membaca file: ' + file.name));
        reader.readAsText(file);
    });
}

// Proses 2 file CSV yang telah diparsing dan jalankan Full Outer Join
function processAndMergeDatasets() {
    if (!file1ParsedData || !file2ParsedData) return;

    const dataW1 = file1ParsedData.data;
    const dataW2 = file2ParsedData.data;

    // Full Outer Join pada article_code
    const allCodes = Array.from(new Set([...Object.keys(dataW1), ...Object.keys(dataW2)])).sort();

    appForecastData = allCodes.map(code => {
        const name = (dataW2[code] && dataW2[code].name) || (dataW1[code] && dataW1[code].name) || code;
        const w1 = dataW1[code] ? dataW1[code].qty : 0.0;
        const w2 = dataW2[code] ? dataW2[code].qty : 0.0;

        // F_(t+1) = (alpha * w2) + ((1 - alpha) * w1)
        const rawF = (currentAlpha * w2) + ((1.0 - currentAlpha) * w1);
        const sysForecast = Math.round(rawF);

        return {
            code,
            name,
            w1,
            w2,
            sysForecast,
            manualOverride: null
        };
    });

    renderForecastTable();

    // Tampilkan container hasil & sembunyikan placeholder
    document.getElementById('forecastResultsContainer').style.display = 'block';
    document.getElementById('emptyPlaceholderCard').style.display = 'none';

    showAppAlert(`Berhasil menggabungkan dataset penjualan: <strong>${appForecastData.length} menu</strong> terhitung menggunakan Single Exponential Smoothing (&alpha; = ${currentAlpha.toFixed(2)}).`, 'success');

    // SINKRONISASI REAKTIF KE MODUL SPK BAHAN BAKU (RECIPE EXPLOSION)
    syncSalesToSPKMaterials(dataW1, dataW2);
}

// Fungsi AJAX sinkronisasi Menu Penjualan ke Rekomendasi SPK Bahan Baku
function syncSalesToSPKMaterials(menuW1, menuW2) {
    const spkSyncBanner = document.getElementById('spkSyncStatusBanner');
    if (spkSyncBanner) {
        spkSyncBanner.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Mengonversi menu penjualan ke kebutuhan Bahan Baku via BOM Recipe Explosion...`;
        spkSyncBanner.className = 'alert alert-info py-2 px-3 small rounded-3 mb-3 d-flex align-items-center justify-content-between';
        spkSyncBanner.style.display = 'flex';
    }

    fetch('api_explode_bom.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            menu_w1: menuW1,
            menu_w2: menuW2,
            alpha: currentAlpha
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && spkSyncBanner) {
            spkSyncBanner.innerHTML = `
                <div>
                    <i class="fas fa-check-circle text-success me-1"></i>
                    <strong>Recipe Explosion Berhasil:</strong> ${res.total_materials} Bahan Baku telah diperbarui untuk pesanan SPK Mingguan.
                </div>
                <a href="rekomendasi.php" class="btn btn-sm btn-gindaco fw-bold px-3 py-1">
                    <i class="fas fa-clipboard-check me-1"></i> Buka Rekomendasi SPK Bahan Baku &rarr;
                </a>
            `;
            spkSyncBanner.className = 'alert alert-success py-2 px-3 small rounded-3 mb-3 d-flex align-items-center justify-content-between';
        }
    })
    .catch(err => {
        console.error('BOM Explosion Sync Error:', err);
    });
}

// Hitung ulang peramalan sistem saat Alpha berubah
function recalculateForecasts() {
    appForecastData.forEach(item => {
        const rawF = (currentAlpha * item.w2) + ((1.0 - currentAlpha) * item.w1);
        item.sysForecast = Math.round(rawF);
    });
}

// Render isi tabel dan update summary cards
function renderForecastTable() {
    const tbody = document.getElementById('forecastTableBody');
    tbody.innerHTML = '';

    let sumW1 = 0;
    let sumW2 = 0;
    let sumFinal = 0;

    appForecastData.forEach((item, idx) => {
        sumW1 += item.w1;
        sumW2 += item.w2;

        const isOverridden = item.manualOverride !== null && !isNaN(item.manualOverride);
        const finalVal = isOverridden ? item.manualOverride : item.sysForecast;
        sumFinal += finalVal;

        const tr = document.createElement('tr');
        tr.id = `row-${idx}`;
        if (isOverridden) tr.classList.add('row-overridden');

        tr.innerHTML = `
            <td class="text-muted fw-semibold">${idx + 1}</td>
            <td>
                <span class="badge bg-light text-dark border font-monospace">${escapeHtml(item.code)}</span>
            </td>
            <td>
                <strong class="text-dark">${escapeHtml(item.name)}</strong>
            </td>
            <td class="text-end font-monospace text-muted">
                ${item.w1.toLocaleString('en-US')}
            </td>
            <td class="text-end font-monospace text-secondary fw-semibold">
                ${item.w2.toLocaleString('en-US')}
            </td>
            <td class="text-end">
                <span class="badge bg-light text-primary border font-monospace fs-6">
                    ${item.sysForecast.toLocaleString('en-US')}
                </span>
            </td>
            <td class="text-center no-print">
                <input type="number" 
                       step="1" 
                       min="0" 
                       class="form-control form-control-sm text-center font-monospace fw-bold override-input" 
                       style="max-width: 120px; margin: 0 auto;" 
                       placeholder="Auto" 
                       value="${isOverridden ? item.manualOverride : ''}"
                       oninput="onManualOverrideInput(this, ${idx})">
            </td>
            <td class="text-end">
                <span class="badge-pill-custom badge-subtle-success fs-6 fw-bold final-badge" id="final-badge-${idx}">
                    <i class="fas fa-boxes-packing me-1"></i><span id="final-txt-${idx}">${finalVal.toLocaleString('en-US')}</span>
                </span>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Update Summary KPI Cards
    document.getElementById('sumTotalItems').innerText = appForecastData.length.toLocaleString('en-US');
    document.getElementById('sumQtyW1').innerText = sumW1.toLocaleString('en-US');
    document.getElementById('sumQtyW2').innerText = sumW2.toLocaleString('en-US');
    document.getElementById('sumFinalForecast').innerText = sumFinal.toLocaleString('en-US');
    document.getElementById('tableItemCountText').innerText = `Menampilkan ${appForecastData.length} item barang (α = ${currentAlpha.toFixed(2)})`;
}

// Handler perubahan input manual override
function onManualOverrideInput(inputEl, idx) {
    const rawVal = inputEl.value.trim();
    const rowEl = document.getElementById(`row-${idx}`);
    const finalTxtEl = document.getElementById(`final-txt-${idx}`);

    if (rawVal !== '' && !isNaN(rawVal)) {
        const val = Math.max(0, parseInt(rawVal, 10));
        appForecastData[idx].manualOverride = val;
        finalTxtEl.innerText = val.toLocaleString('en-US');
        rowEl.classList.add('row-overridden');
    } else {
        appForecastData[idx].manualOverride = null;
        finalTxtEl.innerText = appForecastData[idx].sysForecast.toLocaleString('en-US');
        rowEl.classList.remove('row-overridden');
    }

    // Update total estimasi KPI
    let totalFinal = 0;
    appForecastData.forEach(item => {
        totalFinal += (item.manualOverride !== null) ? item.manualOverride : item.sysForecast;
    });
    document.getElementById('sumFinalForecast').innerText = totalFinal.toLocaleString('en-US');
}

// Reset semua override manual
function resetAllOverrides() {
    appForecastData.forEach((item, idx) => {
        item.manualOverride = null;
        const rowEl = document.getElementById(`row-${idx}`);
        const inputEl = rowEl ? rowEl.querySelector('.override-input') : null;
        const finalTxtEl = document.getElementById(`final-txt-${idx}`);

        if (inputEl) inputEl.value = '';
        if (finalTxtEl) finalTxtEl.innerText = item.sysForecast.toLocaleString('en-US');
        if (rowEl) rowEl.classList.remove('row-overridden');
    });

    let totalFinal = 0;
    appForecastData.forEach(item => totalFinal += item.sysForecast);
    document.getElementById('sumFinalForecast').innerText = totalFinal.toLocaleString('en-US');
}

// Export hasil ke CSV
function exportToCSV() {
    if (appForecastData.length === 0) return;

    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "No,Kode Barang,Nama Barang,Aktual Minggu Lalu,Aktual Minggu Sekarang,Forecast Sistem,Koreksi Manual,Final Forecast,Status\r\n";

    appForecastData.forEach((item, idx) => {
        const isOverridden = item.manualOverride !== null;
        const finalVal = isOverridden ? item.manualOverride : item.sysForecast;
        const manualStr = isOverridden ? item.manualOverride : '';
        const statusStr = isOverridden ? 'Manual Override' : 'System';

        const codeSafe = `"${item.code.replace(/"/g, '""')}"`;
        const nameSafe = `"${item.name.replace(/"/g, '""')}"`;

        csvContent += `${idx + 1},${codeSafe},${nameSafe},${item.w1},${item.w2},${item.sysForecast},${manualStr},${finalVal},${statusStr}\r\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `Weekly_Sales_Forecast_Gindaco_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Helper escape HTML
function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Event Listeners saat DOM siap
document.addEventListener("DOMContentLoaded", function() {
    const file1Input = document.getElementById('fileWeek1Input');
    const file2Input = document.getElementById('fileWeek2Input');
    const statusFile1 = document.getElementById('statusFile1');
    const statusFile2 = document.getElementById('statusFile2');
    const reactiveStatus = document.getElementById('reactiveStatusText');

    // Trigger otomatis saat File 1 dipilih
    file1Input.addEventListener('change', async function() {
        if (!this.files || this.files.length === 0) return;
        const file = this.files[0];
        statusFile1.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>Membaca...`;
        try {
            const text = await readFileAsText(file);
            file1ParsedData = parseWeeklyCSVClient(text, file.name);
            statusFile1.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i>${file1ParsedData.uniqueItemCount} item`;

            if (file2ParsedData) {
                processAndMergeDatasets();
            } else {
                reactiveStatus.innerHTML = `<i class="fas fa-info-circle text-info me-1"></i>File 1 selesai dimuat (${file1ParsedData.uniqueItemCount} item). Silakan pilih File 2.`;
            }
        } catch (err) {
            statusFile1.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle"></i> Error</span>`;
            showAppAlert(err.message, 'danger');
        }
    });

    // Trigger otomatis saat File 2 dipilih
    file2Input.addEventListener('change', async function() {
        if (!this.files || this.files.length === 0) return;
        const file = this.files[0];
        statusFile2.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>Membaca...`;
        try {
            const text = await readFileAsText(file);
            file2ParsedData = parseWeeklyCSVClient(text, file.name);
            statusFile2.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i>${file2ParsedData.uniqueItemCount} item`;

            if (file1ParsedData) {
                processAndMergeDatasets();
            } else {
                reactiveStatus.innerHTML = `<i class="fas fa-info-circle text-info me-1"></i>File 2 selesai dimuat (${file2ParsedData.uniqueItemCount} item). Silakan pilih File 1.`;
            }
        } catch (err) {
            statusFile2.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle"></i> Error</span>`;
            showAppAlert(err.message, 'danger');
        }
    });

    // Search filter tabel
    const searchInput = document.getElementById('searchTableInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#forecastTableBody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('tableItemCountText').innerText = `Menampilkan ${visibleCount} dari ${appForecastData.length} item barang`;
        });
    }

    // Jika ada data hasil POST server-side yang diinjeksi
    <?php if (!empty($server_forecast_data)): ?>
    appForecastData = <?php echo json_encode($server_forecast_data, JSON_UNESCAPED_UNICODE); ?>.map(item => ({
        code: item.code,
        name: item.name,
        w1: parseFloat(item.w1) || 0,
        w2: parseFloat(item.w2) || 0,
        sysForecast: parseInt(item.system_forecast, 10) || 0,
        manualOverride: null
    }));

    if (appForecastData.length > 0) {
        document.getElementById('forecastResultsContainer').style.display = 'block';
        document.getElementById('emptyPlaceholderCard').style.display = 'none';
        renderForecastTable();
    }
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>
