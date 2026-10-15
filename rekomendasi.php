<?php
/**
 * rekomendasi.php - SPK Rekomendasi Pengadaan Bahan Baku (Dynamic Safety Stock Berbasis Forecast)
 * 
 * 1. Logika Perhitungan Dynamic Safety Stock:
 *    - Kebutuhan Harian Rata-rata (Daily Usage) = Final Forecast Mingguan / 7 hari.
 *    - Buffer Cadangan Aman: Default 1 Hari (tersedia preset 1 Hari, 2 Hari, 15%, 20%, atau custom).
 *    - Rumus Safety Stock Dinamis:
 *        Safety Stock = Daily Usage * Buffer Hari (dibulatkan ke bilangan bulat terdekat).
 * 
 * 2. Formula Saran Order SPK:
 *    - Target Total Stok = Final Forecast Mingguan + Safety Stock Dinamis
 *    - Saran Order = Target Total Stok - Stok Fisik Outlet
 *    - Validasi:
 *        Jika Target Total Stok > Stok Fisik: Saran Order = Target Total Stok - Stok Fisik
 *        Jika Target Total Stok <= Stok Fisik: Saran Order = 0 (Stok fisik saat ini sudah mencukupi).
 * 
 * 3. Kolom Tampilan Tabel SPK:
 *    - Kode Bahan Baku
 *    - Nama Bahan Baku
 *    - Satuan
 *    - Stok Fisik (On-Hand)
 *    - Forecast Mingguan (Hasil konversi resep dari penjualan - editable real-time)
 *    - Safety Stock Dinamis (Hasil hitungan: Forecast / 7 * Buffer Hari)
 *    - Target Stok (Forecast + Safety Stock)
 *    - Rekomendasi Order SPK (Target Stok - Stok Fisik, Min: 0)
 *    - Final Order (Input manual jika user ingin pembulatan pack/dus)
 */

include 'db.php';
require_once 'includes/auth.php';
require_once 'includes/bom_service.php';
require_login();

$user_is_manager = is_manager();
$page_title = 'Rekomendasi Pengadaan SPK Bahan Baku (Dynamic Safety Stock)';
$active_page = 'rekomendasi';

$alpha = isset($_POST['alpha']) ? floatval($_POST['alpha']) : (isset($_SESSION['spk_weekly_alpha']) ? (float)$_SESSION['spk_weekly_alpha'] : 0.3);
if ($alpha < 0.1) $alpha = 0.1;
if ($alpha > 0.9) $alpha = 0.9;

// Parameter Buffer Cadangan Aman (Default: 1 Hari)
$buffer_unit = $_POST['buffer_unit'] ?? $_GET['buffer_unit'] ?? $_SESSION['spk_buffer_unit'] ?? 'days';
$buffer_value = isset($_POST['buffer_value']) ? floatval($_POST['buffer_value']) : (isset($_GET['buffer_value']) ? floatval($_GET['buffer_value']) : floatval($_SESSION['spk_buffer_value'] ?? 1.0));
if ($buffer_value < 0) $buffer_value = 0.0;
$_SESSION['spk_buffer_unit'] = $buffer_unit;
$_SESSION['spk_buffer_value'] = $buffer_value;

// Cek apakah ada data kalkulasi mingguan di session
$has_session_data = isset($_SESSION['spk_weekly_materials']) && is_array($_SESSION['spk_weekly_materials']) && count($_SESSION['spk_weekly_materials']) > 0;

$items = [];
$total_order_needed = 0;
$total_aman = 0;
$total_qty_rekomendasi = 0.0;

if ($has_session_data) {
    // Gunakan hasil Recipe Explosion mingguan terbaru dari session
    $session_items = $_SESSION['spk_weekly_materials'];
    foreach ($session_items as $item) {
        $aktual_lalu = (float)($item['aktual_lalu'] ?? 0);
        $ramalan_lalu = (float)($item['ramalan_lalu'] ?? 0);
        $aktual_sekarang = (float)($item['aktual_sekarang'] ?? 0);

        // Recalculate forecast dengan alpha saat ini
        $forecast_sistem = (float)round(($alpha * $aktual_sekarang) + ((1.0 - $alpha) * $ramalan_lalu), 2);
        $final_forecast = (isset($item['manual_override']) && $item['manual_override'] !== null) ? (float)$item['manual_override'] : ((isset($item['final_forecast'])) ? (float)$item['final_forecast'] : $forecast_sistem);

        $stok_fisik = (float)$item['stok_akhir'];

        // Dynamic Safety Stock
        $daily_usage = $final_forecast / 7.0;
        if ($buffer_unit === 'percent') {
            $dynamic_safety_stock = (float)round($final_forecast * ($buffer_value / 100.0));
        } else {
            $dynamic_safety_stock = (float)round($daily_usage * $buffer_value);
        }

        // Target Total Stok = Final Forecast + Safety Stock Dinamis
        $target_stok = (float)round($final_forecast + $dynamic_safety_stock, 2);

        // Saran Order = Target Total Stok - Stok Fisik Outlet (Min: 0)
        $saran_order = $target_stok - $stok_fisik;
        if ($saran_order < 0) $saran_order = 0.0;
        $saran_order = (float)round($saran_order, 2);

        $order_final = $item['order_final'] ?? $saran_order;

        $is_order = ($saran_order > 0);
        if ($is_order) {
            $total_order_needed++;
        } else {
            $total_aman++;
        }
        $total_qty_rekomendasi += $saran_order;

        $items[] = [
            'id' => $item['id'],
            'kode_bahan' => $item['kode_bahan'],
            'nama_bahan' => $item['nama_bahan'],
            'satuan' => $item['satuan'],
            'aktual_lalu' => $aktual_lalu,
            'ramalan_lalu' => $ramalan_lalu,
            'aktual_sekarang' => $aktual_sekarang,
            'forecast_sistem' => $forecast_sistem,
            'final_forecast' => $final_forecast,
            'daily_usage' => round($daily_usage, 2),
            'dynamic_safety_stock' => $dynamic_safety_stock,
            'target_stok' => $target_stok,
            'stok_fisik' => $stok_fisik,
            'saran_order' => $saran_order,
            'order_final' => $order_final,
            'is_order' => $is_order
        ];
    }
} else {
    // Baseline Inisialisasi: Ambil langsung dari master bahan baku gudang
    $all_raw = get_all_raw_materials($conn);
    foreach ($all_raw as $kb => $b) {
        $stok_fisik = (float)$b['stok_aktual'];
        $aktual_lalu = 0.0;
        $ramalan_lalu = 0.0;
        $aktual_sekarang = 0.0;
        $forecast_sistem = 0.0;
        $final_forecast = 0.0;

        $daily_usage = 0.0;
        $dynamic_safety_stock = 0.0;
        $target_stok = 0.0;
        $saran_order = 0.0;
        $order_final = 0.0;
        $is_order = false;

        $total_aman++;

        $items[] = [
            'id' => $b['id'],
            'kode_bahan' => $b['kode_bahan'],
            'nama_bahan' => $b['nama_bahan'],
            'satuan' => $b['satuan'],
            'aktual_lalu' => $aktual_lalu,
            'ramalan_lalu' => $ramalan_lalu,
            'aktual_sekarang' => $aktual_sekarang,
            'forecast_sistem' => $forecast_sistem,
            'final_forecast' => $final_forecast,
            'daily_usage' => $daily_usage,
            'dynamic_safety_stock' => $dynamic_safety_stock,
            'target_stok' => $target_stok,
            'stok_fisik' => $stok_fisik,
            'saran_order' => $saran_order,
            'order_final' => $order_final,
            'is_order' => $is_order
        ];
    }
}

include 'includes/header.php';
?>

<div class="container-fluid px-lg-4 py-4">

    <!-- KOP SURAT PRINT RESMI -->
    <?php 
    $judul_laporan = 'Laporan Rekomendasi Pengadaan Bahan Baku (SPK Mingguan)';
    $subjudul_laporan = 'Dynamic Safety Stock Berbasis Forecast &bull; Outlet Mall Artha Gading &bull; ' . date('d F Y');
    include 'includes/print_header.php'; 
    ?>

    <!-- PAGE HEADER -->
    <div class="page-header-box">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="weekly_forecast.php" class="text-decoration-none text-muted">Weekly Sales Forecast</a></li>
                    <li class="breadcrumb-item active text-danger fw-semibold" aria-current="page">Rekomendasi SPK Bahan Baku</li>
                </ol>
            </nav>
            <h1 class="page-header-title">
                <i class="fas fa-clipboard-check text-danger"></i> Rekomendasi Pengadaan Bahan Baku (Surat Pesanan Kebutuhan / SPK)
            </h1>
            <p class="page-header-desc">
                Sistem Pendukung Keputusan pengadaan bahan baku dengan <strong>Dynamic Safety Stock</strong> berbasis Forecast Mingguan: cadangan buffer fleksibel dan presisi tanpa risiko overstock.
            </p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-soft">
                <i class="fas fa-print me-1"></i> Cetak Dokumen SPK
            </button>
            <a href="weekly_forecast.php" class="btn btn-gindaco">
                <i class="fas fa-calendar-week me-1"></i> Input CSV Penjualan Mingguan
            </a>
        </div>
    </div>

    <!-- PARAMETER BUFFER CADANGAN AMAN (DYNAMIC SAFETY STOCK CONTROLLER) -->
    <div class="modern-card mb-4 no-print border-start border-4 border-danger">
        <div class="p-3">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div class="d-flex align-items-center">
                    <div class="brand-icon me-3" style="width: 44px; height: 44px; font-size: 1.25rem; background: linear-gradient(135deg, #c01b22, #ea580c); color: #fff;">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h6 class="fw-bold mb-0 text-dark">Parameter Buffer Cadangan Aman (Dynamic Safety Stock)</h6>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold" id="badgeBufferDisplay">
                                <?php echo ($buffer_unit === 'percent') ? "Buffer: {$buffer_value}% Forecast" : "Buffer: {$buffer_value} Hari"; ?>
                            </span>
                        </div>
                        <p class="text-muted small mb-0">
                            <code>Daily Usage = Forecast / 7</code> &bull; <code>Safety Stock = Daily Usage &times; Buffer Hari</code> &bull; <code>Target Stok = Forecast + Safety Stock</code>
                        </p>
                    </div>
                </div>

                <!-- PRESET BUTTONS & CUSTOM INPUT -->
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted small fw-semibold me-1">Preset:</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-danger <?php echo ($buffer_unit === 'days' && $buffer_value == 1.0) ? 'active' : ''; ?>" id="btnPreset1D" onclick="setBufferPreset('days', 1.0)">1 Hari (Default)</button>
                        <button type="button" class="btn btn-outline-danger <?php echo ($buffer_unit === 'days' && $buffer_value == 2.0) ? 'active' : ''; ?>" id="btnPreset2D" onclick="setBufferPreset('days', 2.0)">2 Hari</button>
                        <button type="button" class="btn btn-outline-danger <?php echo ($buffer_unit === 'days' && $buffer_value == 3.0) ? 'active' : ''; ?>" id="btnPreset3D" onclick="setBufferPreset('days', 3.0)">3 Hari</button>
                        <button type="button" class="btn btn-outline-danger <?php echo ($buffer_unit === 'percent' && $buffer_value == 15.0) ? 'active' : ''; ?>" id="btnPreset15P" onclick="setBufferPreset('percent', 15.0)">15%</button>
                        <button type="button" class="btn btn-outline-danger <?php echo ($buffer_unit === 'percent' && $buffer_value == 20.0) ? 'active' : ''; ?>" id="btnPreset20P" onclick="setBufferPreset('percent', 20.0)">20%</button>
                    </div>

                    <div class="input-group input-group-sm ms-lg-2" style="max-width: 230px;">
                        <input type="number" step="0.1" min="0" id="customBufferValue" class="form-control text-center font-monospace fw-bold" value="<?php echo $buffer_value; ?>" oninput="onCustomBufferInput()">
                        <select id="customBufferUnit" class="form-select" onchange="onCustomBufferInput()" style="max-width: 100px;">
                            <option value="days" <?php echo ($buffer_unit === 'days') ? 'selected' : ''; ?>>Hari</option>
                            <option value="percent" <?php echo ($buffer_unit === 'percent') ? 'selected' : ''; ?>>% Pct</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI SUMMARY CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card stat-primary py-3">
                <div>
                    <p class="stat-label mb-1">Total Bahan Dianalisis</p>
                    <h4 class="stat-value mb-0 fs-3" id="kpiTotalMaterials"><?php echo count($items); ?> <span class="fs-6 fw-normal text-muted">Item</span></h4>
                </div>
                <div class="stat-icon-wrapper">
                    <i class="fas fa-boxes-packing text-primary"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card stat-danger py-3">
                <div>
                    <p class="stat-label mb-1">Perlu Restock / Order</p>
                    <h4 class="stat-value mb-0 fs-3" id="kpiNeedOrder"><?php echo $total_order_needed; ?> <span class="fs-6 fw-normal text-muted">Bahan</span></h4>
                </div>
                <div class="stat-icon-wrapper">
                    <i class="fas fa-truck-ramp-box text-danger"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card stat-success py-3">
                <div>
                    <p class="stat-label mb-1">Stok Aman / Cukup</p>
                    <h4 class="stat-value mb-0 fs-3" id="kpiSafeCount"><?php echo $total_aman; ?> <span class="fs-6 fw-normal text-muted">Bahan</span></h4>
                </div>
                <div class="stat-icon-wrapper">
                    <i class="fas fa-circle-check text-success"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card stat-warning py-3">
                <div>
                    <p class="stat-label mb-1">Total Kuantitas Order</p>
                    <h4 class="stat-value mb-0 fs-3" id="kpiTotalOrderQty"><?php echo number_format($total_qty_rekomendasi, 1); ?> <span class="fs-6 fw-normal text-muted">Unit</span></h4>
                </div>
                <div class="stat-icon-wrapper">
                    <i class="fas fa-cart-flatbed text-warning"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN SPK TABLE CARD -->
    <div class="modern-card mb-4">
        <div class="modern-card-header flex-column flex-md-row gap-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-table-list text-danger me-2"></i>Tabel Rekomendasi Pesanan Pengadaan (SPK)
                </h5>
                <span class="text-muted small" id="rowCountText">
                    Menampilkan <?php echo count($items); ?> bahan baku &bull; Formula: <code>Target = Forecast + Safety Stock Dinamis</code> &bull; <code>Saran Order = Target - Stok Fisik</code> (Min: 0)
                </span>
            </div>

            <!-- Toolbar: Filter, Search, Reset, Save, Export -->
            <div class="d-flex flex-wrap align-items-center gap-2 no-print">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary active" id="btnFilterAll" onclick="setFilter('all')">Semua</button>
                    <button type="button" class="btn btn-outline-danger" id="btnFilterOrder" onclick="setFilter('order')">Perlu Order</button>
                    <button type="button" class="btn btn-outline-success" id="btnFilterAman" onclick="setFilter('aman')">Stok Aman</button>
                </div>

                <div class="input-group input-group-sm" style="max-width: 220px;">
                    <span class="input-group-text bg-light text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" id="searchInput" class="form-control bg-light" placeholder="Cari bahan...">
                </div>

                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetSPKOverrides()" title="Kembalikan semua koreksi manual ke kalkulasi sistem">
                    <i class="fas fa-rotate-left me-1"></i> Reset
                </button>

                <button type="button" class="btn btn-sm btn-outline-primary" onclick="persistSPKStateToServer()" title="Simpan perubahan SPK ke sesi server">
                    <i class="fas fa-floppy-disk me-1"></i> Simpan Sesi
                </button>

                <button type="button" class="btn btn-sm btn-success fw-bold" onclick="exportSPKToCSV()" title="Unduh Lembar SPK ke format CSV">
                    <i class="fas fa-file-excel me-1"></i> Export CSV
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-modern table-hover align-middle mb-0" id="spkTable">
                <thead>
                    <tr>
                        <th style="width: 40px;">No</th>
                        <th style="width: 120px;">Kode Bahan</th>
                        <th>Nama Bahan Baku</th>
                        <th style="width: 75px;" class="text-center">Satuan</th>
                        <th style="width: 115px;" class="text-end">Stok Fisik (On-Hand)</th>
                        <th style="width: 145px;" class="text-end">Forecast Mingguan</th>
                        <th style="width: 135px;" class="text-end">Safety Stock Dinamis</th>
                        <th style="width: 120px;" class="text-end">Target Stok</th>
                        <th style="width: 145px;" class="text-end">Rekomendasi Order SPK</th>
                        <th style="width: 135px;" class="text-center">Final Order</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1; 
                    foreach($items as $idx => $item): 
                        $status_tag = $item['is_order'] ? 'order' : 'aman';
                        $satuan = htmlspecialchars($item['satuan']);
                        $is_custom_final = isset($item['order_final']) && $item['order_final'] != $item['saran_order'];
                    ?>
                    <tr id="spk-row-<?php echo $idx; ?>" 
                        class="<?php echo $is_custom_final ? 'row-overridden' : ''; ?>"
                        data-status="<?php echo $status_tag; ?>"
                        data-raw-forecast="<?php echo $item['forecast_sistem']; ?>"
                        data-forecast="<?php echo $item['final_forecast']; ?>"
                        data-stock="<?php echo $item['stok_fisik']; ?>"
                        data-safestock="<?php echo $item['dynamic_safety_stock']; ?>"
                        data-target="<?php echo $item['target_stok']; ?>"
                        data-saran-order="<?php echo $item['saran_order']; ?>"
                        data-satuan="<?php echo $satuan; ?>">
                        
                        <!-- 1. No -->
                        <td class="text-muted fw-semibold"><?php echo $no++; ?></td>
                        
                        <!-- 2. Kode Bahan Baku -->
                        <td>
                            <span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($item['kode_bahan']); ?></span>
                        </td>
                        
                        <!-- 3. Nama Bahan Baku -->
                        <td>
                            <strong class="text-dark"><?php echo htmlspecialchars($item['nama_bahan']); ?></strong>
                        </td>
                        
                        <!-- 4. Satuan -->
                        <td class="text-center">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                <?php echo $satuan; ?>
                            </span>
                        </td>
                        
                        <!-- 5. Stok Fisik (On-Hand) -->
                        <td class="text-end font-monospace fw-semibold text-dark">
                            <?php echo number_format($item['stok_fisik'], 2); ?>
                        </td>
                        
                        <!-- 6. Forecast Mingguan (Editable real-time) -->
                        <td class="text-end">
                            <span class="d-none d-print-inline font-monospace fw-bold text-primary" id="print-forecast-<?php echo $idx; ?>">
                                <?php echo number_format($item['final_forecast'], 2); ?>
                            </span>
                            <div class="input-group input-group-sm justify-content-end d-print-none" style="max-width: 130px; margin-left: auto;">
                                <input type="number" 
                                       step="0.1" 
                                       min="0" 
                                       class="form-control form-control-sm text-end font-monospace fw-bold spk-forecast-input" 
                                       value="<?php echo htmlspecialchars($item['final_forecast']); ?>" 
                                       data-idx="<?php echo $idx; ?>"
                                       title="Edit nilai Forecast untuk bahan ini (Otomatis hitung ulang Safety Stock Dinamis & Saran Order)"
                                       oninput="handleForecastChange(this, <?php echo $idx; ?>)">
                            </div>
                        </td>
                        
                        <!-- 7. Safety Stock Dinamis (Daily Usage * Buffer) -->
                        <td class="text-end">
                            <span class="badge bg-light text-secondary border font-monospace fs-6" id="badge-safestock-<?php echo $idx; ?>" title="Safety Stock = (Forecast / 7) * Buffer">
                                <?php echo number_format($item['dynamic_safety_stock'], 2); ?>
                            </span>
                        </td>
                        
                        <!-- 8. Target Stok (Forecast + Safety Stock) -->
                        <td class="text-end font-monospace fw-bold text-dark">
                            <span id="val-target-<?php echo $idx; ?>"><?php echo number_format($item['target_stok'], 2); ?></span>
                        </td>
                        
                        <!-- 9. Rekomendasi Order SPK (Target - Stok Fisik, Min: 0) -->
                        <td class="text-end">
                            <span class="badge-pill-custom <?php echo ($item['saran_order'] > 0) ? 'badge-subtle-danger' : 'badge-subtle-success'; ?> fs-6 fw-bold spk-order-badge" id="badge-order-<?php echo $idx; ?>">
                                <i class="fas <?php echo ($item['saran_order'] > 0) ? 'fa-cart-shopping' : 'fa-check'; ?> me-1" id="icon-order-<?php echo $idx; ?>"></i>
                                <span id="val-saran-order-<?php echo $idx; ?>"><?php echo number_format($item['saran_order'], 2); ?></span>
                            </span>
                        </td>
                        
                        <!-- 10. Final Order (Manual User Input for pack rounding) -->
                        <td class="text-center">
                            <span class="d-none d-print-inline fw-bold font-monospace text-dark" id="print-final-<?php echo $idx; ?>">
                                <?php echo number_format($item['order_final'], 2); ?>
                            </span>
                            <input type="number" 
                                   step="0.01" 
                                   min="0" 
                                   class="form-control form-control-sm text-center font-monospace fw-bold spk-final-input d-print-none" 
                                   style="max-width: 115px; margin: 0 auto;" 
                                   placeholder="<?php echo number_format($item['saran_order'], 2); ?>"
                                   value="<?php echo $is_custom_final ? htmlspecialchars($item['order_final']) : ''; ?>"
                                   data-idx="<?php echo $idx; ?>"
                                   title="Input manual untuk pembulatan karton / pack"
                                   oninput="handleFinalOrderInput(this, <?php echo $idx; ?>)">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php include 'includes/print_footer.php'; ?>
</div>

<style>
/* Highlight baris yang pesanannya disesuaikan/dibulatkan user */
.row-overridden {
    background-color: #fffbeb !important;
    border-left: 4px solid #f59e0b !important;
}
.row-overridden .spk-final-input {
    border-color: #f59e0b !important;
    background-color: #fef3c7 !important;
    color: #b45309 !important;
}
.row-forecast-edited {
    background-color: #f0fdf4 !important;
}
.row-forecast-edited .spk-forecast-input {
    border-color: #10b981 !important;
    background-color: #ecfdf5 !important;
    color: #047857 !important;
}
</style>

<script>
// State parameter buffer dinamis
let currentBufferUnit = '<?php echo $buffer_unit; ?>';
let currentBufferValue = <?php echo $buffer_value; ?>;

// Preset Buffer Setter
function setBufferPreset(unit, value) {
    currentBufferUnit = unit;
    currentBufferValue = parseFloat(value);

    // Update input UI
    document.getElementById('customBufferValue').value = currentBufferValue;
    document.getElementById('customBufferUnit').value = currentBufferUnit;

    // Update active preset button states
    document.getElementById('btnPreset1D').classList.toggle('active', unit === 'days' && value === 1.0);
    document.getElementById('btnPreset2D').classList.toggle('active', unit === 'days' && value === 2.0);
    document.getElementById('btnPreset3D').classList.toggle('active', unit === 'days' && value === 3.0);
    document.getElementById('btnPreset15P').classList.toggle('active', unit === 'percent' && value === 15.0);
    document.getElementById('btnPreset20P').classList.toggle('active', unit === 'percent' && value === 20.0);

    updateBufferBadge();
    recalculateAllRows();
}

// Custom Buffer Input Handler
function onCustomBufferInput() {
    const valRaw = parseFloat(document.getElementById('customBufferValue').value);
    const unit = document.getElementById('customBufferUnit').value;

    if (!isNaN(valRaw) && valRaw >= 0) {
        currentBufferUnit = unit;
        currentBufferValue = valRaw;

        // Clear active preset buttons if custom
        document.getElementById('btnPreset1D').classList.toggle('active', unit === 'days' && valRaw === 1.0);
        document.getElementById('btnPreset2D').classList.toggle('active', unit === 'days' && valRaw === 2.0);
        document.getElementById('btnPreset3D').classList.toggle('active', unit === 'days' && valRaw === 3.0);
        document.getElementById('btnPreset15P').classList.toggle('active', unit === 'percent' && valRaw === 15.0);
        document.getElementById('btnPreset20P').classList.toggle('active', unit === 'percent' && valRaw === 20.0);

        updateBufferBadge();
        recalculateAllRows();
    }
}

// Update Badge display parameter buffer
function updateBufferBadge() {
    const badge = document.getElementById('badgeBufferDisplay');
    if (!badge) return;
    if (currentBufferUnit === 'percent') {
        badge.innerText = `Buffer: ${currentBufferValue}% Forecast`;
    } else {
        badge.innerText = `Buffer: ${currentBufferValue} Hari`;
    }
}

// Hitung ulang semua baris saat Buffer berubah
function recalculateAllRows() {
    const rows = document.querySelectorAll('#spkTable tbody tr');
    rows.forEach((r, idx) => {
        const forecastInput = r.querySelector('.spk-forecast-input');
        const forecast = parseFloat(forecastInput.value) || 0;
        recalculateRow(idx, forecast);
    });
    recalcSPKSummary();
}

// Hitung ulang satu baris berdasarkan Forecast & Buffer aktif
function recalculateRow(idx, forecastVal) {
    const row = document.getElementById(`spk-row-${idx}`);
    if (!row) return;

    const stock = parseFloat(row.getAttribute('data-stock')) || 0;
    const rawForecast = parseFloat(row.getAttribute('data-raw-forecast')) || 0;

    // 1. Daily Usage = Forecast / 7
    const dailyUsage = forecastVal / 7.0;

    // 2. Dynamic Safety Stock (dibulatkan ke bilangan bulat terdekat)
    let dynamicSafeStock = 0;
    if (currentBufferUnit === 'percent') {
        dynamicSafeStock = Math.round(forecastVal * (currentBufferValue / 100.0));
    } else {
        dynamicSafeStock = Math.round(dailyUsage * currentBufferValue);
    }

    // 3. Target Stok = Forecast + Safety Stock Dinamis
    const targetStok = parseFloat((forecastVal + dynamicSafeStock).toFixed(2));

    // 4. Rekomendasi Order SPK = Target - Stok Fisik (Min: 0)
    let saranOrder = targetStok - stock;
    if (saranOrder < 0) saranOrder = 0;
    saranOrder = parseFloat(saranOrder.toFixed(2));

    // Update atribut data row
    row.setAttribute('data-forecast', forecastVal);
    row.setAttribute('data-safestock', dynamicSafeStock);
    row.setAttribute('data-target', targetStok);
    row.setAttribute('data-saran-order', saranOrder);

    // Tandai row jika forecast diedit berbeda dari forecast sistem awal
    if (Math.abs(forecastVal - rawForecast) > 0.001) {
        row.classList.add('row-forecast-edited');
    } else {
        row.classList.remove('row-forecast-edited');
    }

    // Update UI Elements
    // Safety Stock Badge
    const badgeSafe = document.getElementById(`badge-safestock-${idx}`);
    if (badgeSafe) badgeSafe.innerText = dynamicSafeStock.toFixed(2);

    // Target Stok
    const valTarget = document.getElementById(`val-target-${idx}`);
    if (valTarget) valTarget.innerText = targetStok.toFixed(2);

    // Saran Order Badge & Icon
    const valSaranOrder = document.getElementById(`val-saran-order-${idx}`);
    if (valSaranOrder) valSaranOrder.innerText = saranOrder.toFixed(2);

    const badgeOrder = document.getElementById(`badge-order-${idx}`);
    const iconOrder = document.getElementById(`icon-order-${idx}`);
    if (badgeOrder) {
        badgeOrder.className = `badge-pill-custom ${saranOrder > 0 ? 'badge-subtle-danger' : 'badge-subtle-success'} fs-6 fw-bold spk-order-badge`;
    }
    if (iconOrder) {
        iconOrder.className = `fas ${saranOrder > 0 ? 'fa-cart-shopping' : 'fa-check'} me-1`;
    }

    // Print Forecast text
    const printForecast = document.getElementById(`print-forecast-${idx}`);
    if (printForecast) printForecast.innerText = forecastVal.toFixed(2);

    // Final Order Input & Print Span
    const inputFinal = row.querySelector('.spk-final-input');
    const printFinal = document.getElementById(`print-final-${idx}`);
    if (inputFinal) {
        inputFinal.placeholder = saranOrder.toFixed(2);
        const userFinalVal = inputFinal.value.trim();
        if (userFinalVal === '' || isNaN(userFinalVal)) {
            // User belum override, gunakan saran order baru
            if (printFinal) printFinal.innerText = saranOrder.toFixed(2);
            row.setAttribute('data-status', saranOrder > 0 ? 'order' : 'aman');
        } else {
            const customVal = Math.max(0, parseFloat(userFinalVal));
            if (printFinal) printFinal.innerText = customVal.toFixed(2);
            row.setAttribute('data-status', customVal > 0 ? 'order' : 'aman');
        }
    }
}

// Handler perubahan input Forecast per baris
function handleForecastChange(inputEl, idx) {
    const userVal = inputEl.value.trim();
    const forecastVal = (userVal !== '' && !isNaN(userVal)) ? Math.max(0, parseFloat(userVal)) : 0;
    recalculateRow(idx, forecastVal);
    recalcSPKSummary();
}

// Handler Pembulatan / Koreksi User pada Final Order User
function handleFinalOrderInput(inputEl, idx) {
    const row = document.getElementById(`spk-row-${idx}`);
    const saranOrder = parseFloat(row.getAttribute('data-saran-order')) || 0;
    const userVal = inputEl.value.trim();
    const printSpan = document.getElementById(`print-final-${idx}`);

    if (userVal !== '' && !isNaN(userVal)) {
        const customFinal = Math.max(0, parseFloat(userVal));
        row.classList.add('row-overridden');
        row.setAttribute('data-status', (customFinal > 0) ? 'order' : 'aman');
        if (printSpan) printSpan.innerText = customFinal.toFixed(2);
    } else {
        row.classList.remove('row-overridden');
        row.setAttribute('data-status', (saranOrder > 0) ? 'order' : 'aman');
        if (printSpan) printSpan.innerText = saranOrder.toFixed(2);
    }

    recalcSPKSummary();
}

// Hitung ulang KPI ringkasan
function recalcSPKSummary() {
    let orderItems = 0;
    let safeItems = 0;
    let totalQty = 0;

    const rows = document.querySelectorAll('#spkTable tbody tr');
    rows.forEach(r => {
        const saranOrder = parseFloat(r.getAttribute('data-saran-order')) || 0;
        const inputFinal = r.querySelector('.spk-final-input');
        const customVal = inputFinal ? inputFinal.value.trim() : '';

        let effectiveOrder = saranOrder;
        if (customVal !== '' && !isNaN(customVal)) {
            effectiveOrder = Math.max(0, parseFloat(customVal));
        }

        totalQty += effectiveOrder;
        if (effectiveOrder > 0) {
            orderItems++;
        } else {
            safeItems++;
        }
    });

    document.getElementById('kpiNeedOrder').innerHTML = `${orderItems} <span class="fs-6 fw-normal text-muted">Bahan</span>`;
    document.getElementById('kpiSafeCount').innerHTML = `${safeItems} <span class="fs-6 fw-normal text-muted">Bahan</span>`;
    document.getElementById('kpiTotalOrderQty').innerHTML = `${totalQty.toFixed(1)} <span class="fs-6 fw-normal text-muted">Unit</span>`;
}

// Filter status tabel: all / order / aman
function setFilter(status) {
    document.getElementById('btnFilterAll').classList.toggle('active', status === 'all');
    document.getElementById('btnFilterOrder').classList.toggle('active', status === 'order');
    document.getElementById('btnFilterAman').classList.toggle('active', status === 'aman');

    const rows = document.querySelectorAll('#spkTable tbody tr');
    let visible = 0;
    rows.forEach(r => {
        const rStatus = r.getAttribute('data-status');
        if (status === 'all' || rStatus === status) {
            r.style.display = '';
            visible++;
        } else {
            r.style.display = 'none';
        }
    });
    document.getElementById('rowCountText').innerText = `Menampilkan ${visible} bahan baku (Filter: ${status.toUpperCase()})`;
}

// Reset semua penyesuaian manual (Forecast & Final Order)
function resetSPKOverrides() {
    const rows = document.querySelectorAll('#spkTable tbody tr');
    rows.forEach((r, idx) => {
        // Kembalikan forecast ke nilai sistem awal
        const rawForecast = parseFloat(r.getAttribute('data-raw-forecast')) || 0;
        const fInput = r.querySelector('.spk-forecast-input');
        if (fInput) fInput.value = rawForecast;

        // Reset final order input
        const oInput = r.querySelector('.spk-final-input');
        if (oInput) oInput.value = '';
        r.classList.remove('row-overridden');

        recalculateRow(idx, rawForecast);
    });

    recalcSPKSummary();
}

// Simpan state SPK ke session di server via AJAX
function persistSPKStateToServer() {
    const materials = [];
    const rows = document.querySelectorAll('#spkTable tbody tr');
    rows.forEach(r => {
        const code = r.querySelector('td:nth-child(2) span').innerText.trim();
        const forecastInput = r.querySelector('.spk-forecast-input');
        const finalInput = r.querySelector('.spk-final-input');
        
        const forecastVal = parseFloat(forecastInput.value) || 0;
        const finalVal = (finalInput.value.trim() !== '') ? parseFloat(finalInput.value) : null;

        materials.push({
            kode_bahan: code,
            final_forecast: forecastVal,
            order_final: finalVal
        });
    });

    fetch('api_explode_bom.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save_spk_state',
            buffer_unit: currentBufferUnit,
            buffer_value: currentBufferValue,
            materials: materials
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('Perubahan SPK & parameter buffer berhasil disimpan ke sesi server!');
        }
    })
    .catch(err => {
        console.error('Error saving SPK state:', err);
    });
}

// Export Tabel SPK ke file CSV
function exportSPKToCSV() {
    const rows = document.querySelectorAll('#spkTable tbody tr');
    if (rows.length === 0) return;

    let csv = "data:text/csv;charset=utf-8,";
    csv += "No,Kode Bahan Baku,Nama Bahan Baku,Satuan,Stok Fisik OnHand,Forecast Mingguan,Safety Stock Dinamis,Target Stok,Rekomendasi Order SPK,Final Order,Status\r\n";

    rows.forEach(r => {
        const cells = r.querySelectorAll('td');
        if (cells.length < 10) return;

        const no = cells[0].innerText.trim();
        const code = `"${cells[1].innerText.trim()}"`;
        const name = `"${cells[2].querySelector('strong').innerText.trim().replace(/"/g, '""')}"`;
        const unit = `"${cells[3].innerText.trim()}"`;
        const stock = cells[4].innerText.trim().replace(/,/g, '');
        const forecast = cells[5].querySelector('.spk-forecast-input') ? cells[5].querySelector('.spk-forecast-input').value.trim() : cells[5].innerText.trim();
        const safeStock = cells[6].innerText.trim().replace(/,/g, '');
        const targetStok = cells[7].innerText.trim().replace(/,/g, '');
        const saranOrder = cells[8].innerText.trim().replace(/,/g, '');
        const finalInput = cells[9].querySelector('input') ? cells[9].querySelector('input').value.trim() : '';
        const effectiveFinal = (finalInput !== '') ? finalInput : saranOrder;
        const status = (parseFloat(effectiveFinal) > 0) ? 'Perlu Order' : 'Stok Aman';

        csv += `${no},${code},${name},${unit},${stock},${forecast},${safeStock},${targetStok},${saranOrder},${effectiveFinal},${status}\r\n`;
    });

    const encodedUri = encodeURI(csv);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `SPK_Bahan_Baku_Gindaco_DynamicSafeStock_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Search Filter
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('#spkTable tbody tr');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            let count = 0;
            rows.forEach(r => {
                const text = r.innerText.toLowerCase();
                if (text.includes(query)) {
                    r.style.display = '';
                    count++;
                } else {
                    r.style.display = 'none';
                }
            });
            document.getElementById('rowCountText').innerText = `Menampilkan ${count} dari ${rows.length} bahan baku`;
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>