<?php
include 'db.php';
require_once 'includes/auth.php';
require_login();

$user_is_manager = is_manager();

// Ambil parameter alpha
$alpha = isset($_POST['alpha']) ? floatval($_POST['alpha']) : 0.3;
if ($alpha <= 0) $alpha = 0.1;
if ($alpha >= 1) $alpha = 0.99;

// Ambil daftar bahan
$bahan_list = $conn->query("SELECT * FROM bahan_baku ORDER BY nama_bahan ASC");
$total_bahan = $bahan_list ? $bahan_list->num_rows : 0;

// Ambil daftar periode unik di data_historis untuk filter
$periode_result = $conn->query("SELECT DISTINCT periode FROM data_historis ORDER BY id DESC");
$periode_options = [];
if ($periode_result) {
    while ($pr = $periode_result->fetch_assoc()) {
        $periode_options[] = $pr['periode'];
    }
}
$selected_periode = isset($_GET['filter_periode']) ? trim($_GET['filter_periode']) : ($periode_options[0] ?? 'Maret 2026');

// Ambil data aktual berdasarkan periode yang difilter
$filter_sql = "";
if (!empty($selected_periode)) {
    $p_safe = $conn->real_escape_string($selected_periode);
    $filter_sql = "WHERE h.periode = '$p_safe'";
}
$q_aktual = $conn->query("SELECT h.*, b.nama_bahan, b.kode_bahan, b.satuan, b.stok_aktual, b.safety_stock 
                         FROM data_historis h 
                         JOIN bahan_baku b ON h.id_bahan = b.id 
                         $filter_sql 
                         ORDER BY b.nama_bahan ASC");

// Hitung metrik validasi data input
$total_data_aktual = 0;
$anomali_kosong = 0;
$anomali_ekstrim = 0;
$data_aktual_array = [];

if ($q_aktual) {
    while ($item = $q_aktual->fetch_assoc()) {
        $total_data_aktual++;
        $nilai = (float)$item['pemakaian_aktual'];
        $safety = (float)$item['safety_stock'];

        $is_zero = ($nilai <= 0);
        $is_outlier = ($safety > 0 && $nilai > ($safety * 15)); // Pemakaian di atas 15x safety stock dianggap fluktuasi tajam

        if ($is_zero) $anomali_kosong++;
        if ($is_outlier) $anomali_ekstrim++;

        $item['is_zero'] = $is_zero;
        $item['is_outlier'] = $is_outlier;
        $data_aktual_array[] = $item;
    }
}

// Ambil 5 riwayat audit log terakhir
$q_recent_log = $conn->query("SELECT * FROM audit_log WHERE tabel_terkait = 'data_historis' ORDER BY id DESC LIMIT 5");

$page_title = 'Perhitungan Exponential Smoothing & Validasi Data';
$active_page = 'smoothing';
include 'includes/header.php';
?>

<div class="container-fluid px-lg-4 py-4">

    <?php 
    $judul_laporan = 'Laporan Hasil Peramalan (Forecasting) Bahan Baku';
    $subjudul_laporan = 'Metode Single Exponential Smoothing (Konstanta Alpha = ' . $alpha . ') &bull; Outlet Mall Artha Gading &bull; Periode: ' . date('F Y');
    include 'includes/print_header.php'; 
    ?>

    <!-- PAGE HEADER -->
    <div class="page-header-box">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item active text-danger fw-semibold" aria-current="page">Exponential Smoothing</li>
                </ol>
            </nav>
            <h1 class="page-header-title">
                <i class="fas fa-calculator text-primary"></i> Single Exponential Smoothing &amp; Kelola Data Aktual
            </h1>
            <p class="page-header-desc">
                Pusat kalkulasi peramalan kebutuhan bahan baku dan instrumen validasi integritas data aktual pemakaian fisik gudang.
            </p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-soft">
                <i class="fas fa-print me-1"></i> Cetak Dokumen Resmi
            </button>
            <a href="weekly_forecast.php" class="btn btn-outline-danger">
                <i class="fas fa-calendar-week me-1"></i> Weekly Sales Forecast
            </a>
            <a href="rekomendasi.php" class="btn btn-gindaco">
                <i class="fas fa-clipboard-check me-1"></i> Buka Rekomendasi SPK
            </a>
        </div>
    </div>

    <!-- NOTIFIKASI TOAST / ALERT POPUP -->
    <div id="liveAlertPlaceholder" class="no-print"></div>

    <!-- NAVIGATION TABS: PERAMALAN vs KELOLA DATA AKTUAL -->
    <ul class="nav nav-pills mb-4 gap-2 no-print" id="smoothingTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active px-4 py-2 fw-bold d-flex align-items-center gap-2" id="tab-forecast-btn" data-bs-toggle="pill" data-bs-target="#tab-forecast" type="button" role="tab">
                <i class="fas fa-chart-line"></i>
                <span>Tabel Hasil Peramalan (Forecasting)</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-4 py-2 fw-bold d-flex align-items-center gap-2" id="tab-data-btn" data-bs-toggle="pill" data-bs-target="#tab-data" type="button" role="tab">
                <i class="fas fa-database"></i>
                <span>Kelola &amp; Validasi Data Aktual Penjualan</span>
                <?php if ($anomali_kosong > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?php echo $anomali_kosong; ?> Kosong</span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="smoothingTabContent">

        <!-- ========================================== -->
        <!-- TAB 1: HASIL PERAMALAN EXPONENTIAL SMOOTHING -->
        <!-- ========================================== -->
        <div class="tab-pane fade show active" id="tab-forecast" role="tabpanel">

            <!-- ALPHA CONFIGURATION & FORMULA CARD -->
            <div class="row g-4 mb-4 no-print">
                <div class="col-lg-6">
                    <div class="modern-card h-100">
                        <div class="modern-card-header">
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="fas fa-sliders text-danger me-2"></i>Parameter Pemulusan Konstanta (&alpha;)
                            </h5>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Bobot Adaptif</span>
                        </div>
                        <div class="modern-card-body p-4">
                            <form method="POST" id="alphaForm">
                                <label class="form-label fw-bold text-dark small text-uppercase">
                                    Pilih atau Masukkan Nilai Alpha (0 &lt; &alpha; &lt; 1)
                                </label>

                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <input type="range" class="form-range" id="alphaRange" min="0.05" max="0.95" step="0.05" value="<?php echo $alpha; ?>" oninput="document.getElementById('alphaInput').value = this.value">
                                    <input type="number" step="0.01" min="0.01" max="0.99" class="form-control form-control-lg text-center fw-bold" style="width: 110px;" id="alphaInput" name="alpha" value="<?php echo $alpha; ?>" oninput="document.getElementById('alphaRange').value = this.value">
                                </div>

                                <!-- Quick Presets -->
                                <div class="d-flex flex-wrap gap-2 mb-4">
                                    <span class="text-muted small align-self-center me-1">Preset:</span>
                                    <button type="button" class="btn btn-sm btn-soft rounded-pill" onclick="setAlpha(0.1)">&alpha; = 0.1 (Sangat Halus)</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill fw-bold" onclick="setAlpha(0.3)">&alpha; = 0.3 (Standar Gindaco)</button>
                                    <button type="button" class="btn btn-sm btn-soft rounded-pill" onclick="setAlpha(0.5)">&alpha; = 0.5 (Seimbang)</button>
                                    <button type="button" class="btn btn-sm btn-soft rounded-pill" onclick="setAlpha(0.7)">&alpha; = 0.7 (Responsif)</button>
                                </div>

                                <button type="submit" class="btn btn-gindaco px-4 py-2 w-100 fw-bold">
                                    <i class="fas fa-arrows-rotate me-1"></i> Hitung Ulang Peramalan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="modern-card h-100">
                        <div class="modern-card-header">
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="fas fa-square-root-variable text-primary me-2"></i>Penjelasan Matematis
                            </h5>
                            <span class="badge badge-subtle-primary">Rumus Baku</span>
                        </div>
                        <div class="modern-card-body p-4">
                            <div class="p-3 bg-light rounded-3 text-center mb-3 border">
                                <div class="fs-4 fw-bold text-danger font-monospace">
                                    F<sub>t+1</sub> = &alpha; &middot; Y<sub>t</sub> + (1 - &alpha;) &middot; F<sub>t</sub>
                                </div>
                            </div>
                            <div class="small text-muted mb-2">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-1"><strong>F<sub>t+1</sub></strong> : Nilai ramalan untuk periode mendatang (t+1).</li>
                                    <li class="mb-1"><strong>Y<sub>t</sub></strong> : Nilai riil pemakaian aktual pada periode sekarang (t).</li>
                                    <li class="mb-1"><strong>F<sub>t</sub></strong> : Nilai ramalan pada periode sekarang (t).</li>
                                    <li><strong>&alpha; (Alpha)</strong> : Bobot pemulusan. Semakin besar &alpha;, semakin besar pengaruh fluktuasi riil terbaru.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FORECAST RESULTS TABLE CARD -->
            <div class="modern-card mb-4">
                <div class="modern-card-header flex-column flex-md-row gap-3">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-table-list text-success me-2"></i>Tabel Hasil Peramalan Periode Berikutnya (&alpha; = <?php echo $alpha; ?>)
                        </h5>
                        <span class="text-muted small" id="rowCountText">Menampilkan <?php echo $total_bahan; ?> bahan baku yang dihitung</span>
                    </div>

                    <div class="input-group" style="max-width: 300px;">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchInput" class="form-control bg-light border-start-0" placeholder="Cari bahan baku...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-modern table-hover align-middle mb-0" id="forecastTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th style="width: 130px;">Kode Bahan</th>
                                <th>Nama Bahan Baku</th>
                                <th style="width: 160px;" class="text-end">Aktual Lalu (Y<sub>t</sub>)</th>
                                <th style="width: 160px;" class="text-end">Ramalan Lalu (F<sub>t</sub>)</th>
                                <th style="width: 180px;" class="text-end">Hasil Ramalan (F<sub>t+1</sub>)</th>
                                <th style="width: 130px;" class="text-center">Satuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            if ($bahan_list):
                                while($b = $bahan_list->fetch_assoc()):
                                    $id_b = $b['id'];
                                    $q_hist = $conn->query("SELECT * FROM data_historis WHERE id_bahan = $id_b ORDER BY id DESC LIMIT 2");
                                    $hist = [];
                                    if ($q_hist) {
                                        while($h = $q_hist->fetch_assoc()) { $hist[] = $h; }
                                    }
                                    
                                    $yt = isset($hist[0]) ? (float)$hist[0]['pemakaian_aktual'] : (float)$b['safety_stock'] * 1.5;
                                    $ft_prev = isset($hist[1]) ? (float)$hist[1]['pemakaian_aktual'] * 0.95 : (float)$yt * 0.92;
                                    
                                    $ft_next = ($alpha * $yt) + ((1 - $alpha) * $ft_prev);
                            ?>
                            <tr>
                                <td class="text-muted fw-semibold"><?php echo $no++; ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace">
                                        <?php echo htmlspecialchars($b['kode_bahan']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-dark"><?php echo htmlspecialchars($b['nama_bahan']); ?></strong>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-light text-secondary border font-monospace fs-6">
                                        <?php echo number_format($yt, 2); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-light text-muted border font-monospace fs-6">
                                        <?php echo number_format($ft_prev, 2); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="badge-pill-custom badge-subtle-success fs-6 fw-bold">
                                        <i class="fas fa-chart-line me-1"></i><?php echo number_format($ft_next, 2); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                        <?php echo htmlspecialchars($b['satuan']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php 
                                endwhile; 
                            endif;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 2: MENU PENGELOLAAN & VALIDASI DATA AKTUAL (NEW FEATURE) -->
        <!-- ============================================================ -->
        <div class="tab-pane fade" id="tab-data" role="tabpanel">

            <!-- VALIDATION KPI CARDS -->
            <div class="row g-3 mb-4 no-print">
                <div class="col-md-4">
                    <div class="stat-card stat-primary py-3">
                        <div>
                            <p class="stat-label mb-1">Total Catatan Periode Ini</p>
                            <h4 class="stat-value mb-0 fs-3"><?php echo $total_data_aktual; ?> <span class="fs-6 fw-normal text-muted">Bahan</span></h4>
                        </div>
                        <div class="stat-icon-wrapper">
                            <i class="fas fa-database text-primary"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="stat-card <?php echo ($anomali_kosong > 0) ? 'stat-danger' : 'stat-success'; ?> py-3">
                        <div>
                            <p class="stat-label mb-1">Data Nilai Nol / Kosong</p>
                            <h4 class="stat-value mb-0 fs-3"><?php echo $anomali_kosong; ?> <span class="fs-6 fw-normal text-muted">Item</span></h4>
                        </div>
                        <div class="stat-icon-wrapper">
                            <i class="fas <?php echo ($anomali_kosong > 0) ? 'fa-triangle-exclamation text-danger' : 'fa-check-circle text-success'; ?>"></i>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="stat-card stat-warning py-3">
                        <div>
                            <p class="stat-label mb-1">Fluktuasi Ekstrim (>15x SS)</p>
                            <h4 class="stat-value mb-0 fs-3"><?php echo $anomali_ekstrim; ?> <span class="fs-6 fw-normal text-muted">Item</span></h4>
                        </div>
                        <div class="stat-icon-wrapper">
                            <i class="fas fa-wave-square text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FILTER & SEARCH BAR -->
            <div class="modern-card mb-4 no-print">
                <div class="p-3 bg-light border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <form method="GET" class="d-flex align-items-center gap-2">
                        <label class="fw-bold text-dark small text-nowrap mb-0"><i class="far fa-calendar-alt text-danger me-1"></i>Pilih Periode:</label>
                        <select name="filter_periode" class="form-select form-select-sm fw-semibold" style="min-width: 180px;" onchange="this.form.submit()">
                            <?php foreach ($periode_options as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($selected_periode === $opt) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <div class="d-flex align-items-center gap-2">
                        <span class="badge <?php echo $user_is_manager ? 'bg-success' : 'bg-secondary'; ?> px-3 py-2">
                            <i class="fas <?php echo $user_is_manager ? 'fa-user-check' : 'fa-lock'; ?> me-1"></i>
                            Hak Akses: <?php echo $user_is_manager ? 'Manager (Bisa Edit)' : 'Staff (Hanya Lihat)'; ?>
                        </span>
                        <div class="input-group input-group-sm" style="max-width: 250px;">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="searchAktualInput" class="form-control" placeholder="Cari nama bahan...">
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-modern table-hover align-middle mb-0" id="aktualTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th style="width: 120px;">Periode</th>
                                <th style="width: 130px;">Kode Bahan</th>
                                <th>Nama Bahan Baku</th>
                                <th style="width: 140px;" class="text-end">Safety Stock</th>
                                <th style="width: 180px;" class="text-end">Aktual Pemakaian (Y<sub>t</sub>)</th>
                                <th style="width: 100px;" class="text-center">Satuan</th>
                                <th style="width: 140px;" class="text-center">Status Validasi</th>
                                <th style="width: 120px;" class="text-center no-print">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no_akt = 1;
                            if (!empty($data_aktual_array)):
                                foreach ($data_aktual_array as $row):
                                    $row_id = $row['id'];
                                    $nama_b = htmlspecialchars($row['nama_bahan']);
                                    $kode_b = htmlspecialchars($row['kode_bahan']);
                                    $sat = htmlspecialchars($row['satuan']);
                                    $val_akt = (float)$row['pemakaian_aktual'];
                                    $val_safety = (float)$row['safety_stock'];
                            ?>
                            <tr id="row-aktual-<?php echo $row_id; ?>">
                                <td class="text-muted fw-semibold"><?php echo $no_akt++; ?></td>
                                <td>
                                    <span class="badge bg-light text-primary border border-primary border-opacity-25 px-2 py-1 fw-bold">
                                        <i class="far fa-calendar-check me-1"></i><?php echo htmlspecialchars($row['periode']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace">
                                        <?php echo $kode_b; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-dark"><?php echo $nama_b; ?></strong>
                                </td>
                                <td class="text-end text-muted font-monospace">
                                    <?php echo number_format($val_safety, 2); ?>
                                </td>
                                <td class="text-end">
                                    <span class="fs-6 fw-bold font-monospace text-dark" id="txt-val-<?php echo $row_id; ?>">
                                        <?php echo number_format($val_akt, 2); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                        <?php echo $sat; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['is_zero']): ?>
                                        <span class="badge-pill-custom badge-subtle-danger" title="Data pemakaian 0, butuh konfirmasi fisik">
                                            <i class="fas fa-circle-exclamation"></i> Kosong
                                        </span>
                                    <?php elseif ($row['is_outlier']): ?>
                                        <span class="badge-pill-custom badge-subtle-warning" title="Pemakaian sangat tinggi dibanding safety stock">
                                            <i class="fas fa-triangle-exclamation"></i> Ekstrim
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-pill-custom badge-subtle-success">
                                            <i class="fas fa-check"></i> Tervalidasi
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center no-print">
                                    <?php if ($user_is_manager): ?>
                                        <button class="btn btn-sm btn-outline-danger px-3 py-1" onclick="openEditAktualModal(<?php echo $row_id; ?>, '<?php echo addslashes($nama_b); ?>', '<?php echo addslashes($kode_b); ?>', '<?php echo addslashes($row['periode']); ?>', <?php echo $val_akt; ?>, '<?php echo addslashes($sat); ?>')">
                                            <i class="fas fa-pen-to-square me-1"></i> Koreksi
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small" title="Hanya Manager yang dapat mengoreksi data"><i class="fas fa-lock"></i> Terkunci</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="fas fa-folder-open me-2 fs-5"></i> Tidak ada data aktual untuk periode ini.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- RECENT AUDIT LOG CHANGES CARD -->
            <div class="modern-card no-print">
                <div class="modern-card-header">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-clock-rotate-left text-info me-2"></i>Catatan Audit Perubahan Data Aktual Terakhir
                    </h5>
                    <span class="badge badge-subtle-primary">Audit Trail &amp; Integritas Data</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 170px;">Waktu Perubahan</th>
                                <th style="width: 160px;">Pengubah</th>
                                <th>Bahan &amp; Periode</th>
                                <th style="width: 140px;" class="text-end">Nilai Sebelum</th>
                                <th style="width: 140px;" class="text-end">Nilai Sesudah</th>
                                <th>Keterangan / Alasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($q_recent_log && $q_recent_log->num_rows > 0):
                                while ($log = $q_recent_log->fetch_assoc()):
                                    $detail = json_decode($log['detail_perubahan'], true);
                            ?>
                            <tr>
                                <td class="text-muted"><i class="far fa-clock me-1"></i><?php echo date('d M Y, H:i', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <strong class="text-dark"><?php echo htmlspecialchars($log['nama_user']); ?></strong>
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">NIK: <?php echo htmlspecialchars($log['nik']); ?></span>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($detail['nama_bahan'] ?? '-'); ?></span>
                                    <span class="badge bg-light text-muted border ms-1"><?php echo htmlspecialchars($detail['periode'] ?? '-'); ?></span>
                                </td>
                                <td class="text-end font-monospace text-secondary">
                                    <?php echo isset($detail['nilai_sebelum']) ? number_format($detail['nilai_sebelum'], 2) : '-'; ?>
                                </td>
                                <td class="text-end font-monospace text-success fw-bold">
                                    <?php echo isset($detail['nilai_sesudah']) ? number_format($detail['nilai_sesudah'], 2) : '-'; ?>
                                </td>
                                <td class="text-muted"><?php echo htmlspecialchars($detail['keterangan'] ?? '-'); ?></td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted">
                                    Belum ada catatan perubahan data aktual di sistem.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
    
    <?php include 'includes/print_footer.php'; ?>
</div>

<!-- ============================================================ -->
<!-- MODAL KOREKSI / EDIT DATA AKTUAL KHUSUS MANAGER              -->
<!-- ============================================================ -->
<?php if ($user_is_manager): ?>
<div class="modal fade" id="editAktualModal" tabindex="-1" aria-labelledby="editAktualModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold" id="editAktualModalLabel">
                    <i class="fas fa-pen-to-square me-2"></i>Koreksi Data Aktual Pemakaian
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formKoreksiAktual" onsubmit="submitKoreksiAktual(event)">
                <input type="hidden" id="modalId" name="id">
                <input type="hidden" name="action" value="update_aktual">

                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">Bahan Baku:</span>
                            <span class="badge bg-secondary font-monospace" id="modalKode"></span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1" id="modalNama"></h6>
                        <div class="small text-muted">
                            <i class="far fa-calendar-alt text-danger me-1"></i>Periode: <strong id="modalPeriode" class="text-dark"></strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark text-uppercase">Nilai Aktual Saat Ini</label>
                        <div class="input-group">
                            <input type="text" id="modalNilaiLama" class="form-control bg-light font-monospace fw-bold" readonly>
                            <span class="input-group-text bg-light text-muted font-monospace" id="modalSatuanLama"></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark text-uppercase">
                            Nilai Aktual Yang Benar (Setelah Validasi Fisik) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" id="modalNilaiBaru" name="pemakaian_aktual" class="form-control form-control-lg font-monospace fw-bold text-danger" required>
                            <span class="input-group-text bg-light font-monospace" id="modalSatuanBaru"></span>
                        </div>
                        <span class="text-muted" style="font-size: 0.72rem;">Pastikan nilai bersumber dari rekonsiliasi stok fisik atau POS riil.</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark text-uppercase">Alasan Koreksi / Catatan Audit</label>
                        <input type="text" id="modalAlasan" name="alasan" class="form-control" placeholder="Contoh: Penyesuaian selisih stock opname akhir bulan" required>
                    </div>

                    <div class="alert alert-warning small py-2 px-3 rounded-3 mb-0 border-warning border-opacity-25">
                        <i class="fas fa-triangle-exclamation me-1"></i>
                        Perubahan ini akan dicatat ke <strong>Audit Log</strong> dan memicu pembaruan hasil peramalan Exponential Smoothing berikutnya.
                    </div>
                </div>

                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-gindaco px-4 fw-bold" id="btnSimpanKoreksi">
                        <i class="fas fa-check me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function setAlpha(val) {
    document.getElementById('alphaInput').value = val;
    document.getElementById('alphaRange').value = val;
    document.getElementById('alphaForm').submit();
}

function showLiveAlert(message, type = 'success') {
    const alertPlaceholder = document.getElementById('liveAlertPlaceholder');
    alertPlaceholder.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function openEditAktualModal(id, nama, kode, periode, nilaiLama, satuan) {
    document.getElementById('modalId').value = id;
    document.getElementById('modalKode').textContent = kode;
    document.getElementById('modalNama').textContent = nama;
    document.getElementById('modalPeriode').textContent = periode;
    document.getElementById('modalNilaiLama').value = Number(nilaiLama).toFixed(2);
    document.getElementById('modalSatuanLama').textContent = satuan;
    document.getElementById('modalSatuanBaru').textContent = satuan;
    document.getElementById('modalNilaiBaru').value = Number(nilaiLama).toFixed(2);
    document.getElementById('modalAlasan').value = 'Koreksi verifikasi data aktual fisik gudang';

    const modal = new bootstrap.Modal(document.getElementById('editAktualModal'));
    modal.show();
}

function submitKoreksiAktual(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSimpanKoreksi');
    const form = document.getElementById('formKoreksiAktual');
    const formData = new FormData(form);

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

    fetch('api_data_aktual.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check me-1"></i> Simpan Perubahan';

        if (data.success) {
            // Tutup modal
            const modalEl = document.getElementById('editAktualModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            // Perbarui tampilan tabel aktual
            const txtEl = document.getElementById('txt-val-' + data.id);
            if (txtEl) {
                txtEl.textContent = Number(data.nilai_baru).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                txtEl.classList.add('text-success');
            }

            showLiveAlert(data.message + ' Silakan hitung ulang peramalan untuk melihat pengaruh kalkulasi terbaru.', 'success');
        } else {
            showLiveAlert(data.message || 'Terjadi kesalahan saat menyimpan data.', 'danger');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check me-1"></i> Simpan Perubahan';
        showLiveAlert('Koneksi server gagal: ' + err, 'danger');
    });
}

document.addEventListener("DOMContentLoaded", function() {
    // Search Filter Forecast Table
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('#forecastTable tbody tr');
    const rowCountText = document.getElementById('rowCountText');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            let visibleCount = 0;

            tableRows.forEach(row => {
                const text = row.innerText.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (rowCountText) {
                rowCountText.textContent = `Menampilkan ${visibleCount} dari ${tableRows.length} bahan baku yang dihitung`;
            }
        });
    }

    // Search Filter Aktual Table
    const searchAktualInput = document.getElementById('searchAktualInput');
    const aktualRows = document.querySelectorAll('#aktualTable tbody tr');

    if (searchAktualInput) {
        searchAktualInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            aktualRows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>