<?php
include 'db.php';
require_once 'includes/auth.php';
require_login();

$total_bahan = $conn->query("SELECT COUNT(*) as total FROM bahan_baku")->fetch_assoc()['total'];
$total_menu = $conn->query("SELECT COUNT(*) as total FROM menu")->fetch_assoc()['total'];
$kritis = $conn->query("SELECT COUNT(*) as total FROM bahan_baku WHERE stok_aktual <= safety_stock")->fetch_assoc()['total'];

// Hitung total transaksi jika ada tabelnya
$q_transaksi = $conn->query("SELECT COUNT(*) as total FROM transaksi_penjualan");
$total_transaksi = $q_transaksi ? $q_transaksi->fetch_assoc()['total'] : 0;

// Ambil beberapa bahan yang mendekati atau di bawah safety stock untuk widget alert
$bahan_kritis = $conn->query("SELECT * FROM bahan_baku WHERE stok_aktual <= safety_stock ORDER BY (stok_aktual - safety_stock) ASC LIMIT 5");

// Ambil 5 bahan acak atau teratas untuk visual chart
$chart_bahan = $conn->query("SELECT nama_bahan, stok_aktual, safety_stock FROM bahan_baku LIMIT 6");
$chart_labels = [];
$chart_stok = [];
$chart_safety = [];
while ($cb = $chart_bahan->fetch_assoc()) {
    $short_name = strlen($cb['nama_bahan']) > 15 ? substr($cb['nama_bahan'], 0, 15) . '...' : $cb['nama_bahan'];
    $chart_labels[] = $short_name;
    $chart_stok[] = (float)$cb['stok_aktual'];
    $chart_safety[] = (float)$cb['safety_stock'];
}

$page_title = 'Dashboard SPK Pengadaan';
$active_page = 'dashboard';
include 'includes/header.php';
?>

<div class="container-fluid px-lg-4 py-4">

    <!-- HERO BANNER -->
    <div class="hero-banner mb-4">
        <div class="row align-items-center">
            <div class="col-lg-8 mb-3 mb-lg-0">
                <div class="hero-badge">
                    <i class="fas fa-sparkles text-warning"></i>
                    <span>Decision Support System &bull; Gindaco Mall Artha Gading</span>
                </div>
                <h2 class="display-6 fw-bold mb-2">Selamat Datang di Portal SPK Gindaco</h2>
                <p class="mb-4 text-white-50 lead fs-6" style="max-width: 650px;">
                    Optimasi pengadaan bahan baku Takoyaki & Taiyaki secara presisi menggunakan metode <strong>Single Exponential Smoothing</strong> terintegrasi langsung dengan <strong>Bill of Materials (BOM)</strong> dan <strong>Import POS CSV</strong>.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="import_csv.php" class="btn btn-light fw-bold text-danger px-3 py-2 shadow-sm">
                        <i class="fas fa-file-csv me-1 text-danger"></i> Import POS CSV
                    </a>
                    <a href="rekomendasi.php" class="btn btn-outline-light fw-semibold px-3 py-2">
                        <i class="fas fa-clipboard-check me-1"></i> Lihat Rekomendasi SPK
                    </a>
                    <a href="bahan.php" class="btn btn-dark bg-opacity-25 border-0 text-white fw-semibold px-3 py-2">
                        <i class="fas fa-boxes-stacked me-1"></i> Cek Stok Gudang
                    </a>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="p-3 bg-white rounded-4 shadow-lg text-center mb-2 position-relative" style="z-index: 2;">
                    <img src="assets/img/gindaco_logo.webp" alt="Gindaco Indonesia" class="img-fluid py-1 px-2" style="max-height: 48px; width: auto; object-fit: contain;">
                    <div class="border-top pt-2 mt-2 d-flex justify-content-between align-items-center px-1">
                        <span class="badge bg-danger text-white px-2 py-1"><i class="fas fa-location-dot me-1"></i> Mall Artha Gading</span>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="fas fa-circle fa-2xs me-1"></i>Aktif</span>
                    </div>
                </div>
                <div class="p-3 bg-white bg-opacity-10 rounded-4 backdrop-blur border border-white border-opacity-25 text-start text-white position-relative" style="z-index: 2;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-white-50">Database System:</span>
                        <span class="small fw-bold text-white"><i class="fas fa-database text-warning me-1"></i>db_spk_gindaco</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-white-50">Waktu Server:</span>
                        <span class="small fw-semibold text-white"><i class="far fa-clock text-info me-1"></i><?php echo date('d M Y, H:i'); ?> WIB</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI STATS CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card stat-primary">
                <div>
                    <p class="stat-label">Total Bahan Baku</p>
                    <h3 class="stat-value"><?php echo number_format($total_bahan); ?></h3>
                    <span class="badge badge-subtle-primary">
                        <i class="fas fa-boxes-stacked me-1"></i>Master Inventory
                    </span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-boxes-stacked"></i>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card stat-success">
                <div>
                    <p class="stat-label">Varian Menu</p>
                    <h3 class="stat-value"><?php echo number_format($total_menu); ?></h3>
                    <span class="badge badge-subtle-success">
                        <i class="fas fa-utensils me-1"></i>Resep BOM Aktif
                    </span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-bowl-food"></i>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card stat-danger">
                <div>
                    <p class="stat-label">Bahan Kritis</p>
                    <h3 class="stat-value text-danger"><?php echo number_format($kritis); ?></h3>
                    <span class="badge badge-subtle-danger">
                        <i class="fas fa-triangle-exclamation me-1"></i>Perlu Restock Segera
                    </span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-bell"></i>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card stat-purple">
                <div>
                    <p class="stat-label">Transaksi POS</p>
                    <h3 class="stat-value"><?php echo number_format($total_transaksi); ?></h3>
                    <span class="badge badge-subtle-primary">
                        <i class="fas fa-receipt me-1"></i>Data Terkumpul
                    </span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN DASHBOARD CONTENT -->
    <div class="row g-4">
        <!-- LEFT COLUMN: Chart & Quick Modules -->
        <div class="col-lg-7">
            <!-- Chart Card -->
            <div class="modern-card mb-4">
                <div class="modern-card-header">
                    <div>
                        <h5 class="fw-bold mb-1 text-dark">
                            <i class="fas fa-chart-bar text-danger me-2"></i>Komparasi Stok Aktual vs Safety Stock
                        </h5>
                        <p class="text-muted small mb-0">Visualisasi 6 sampel bahan baku utama di gudang Gindaco</p>
                    </div>
                    <a href="bahan.php" class="btn btn-sm btn-soft">
                        Lihat Semua <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="modern-card-body">
                    <div style="height: 280px; position: relative;">
                        <canvas id="stockOverviewChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Access Modules Grid -->
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="modern-card modern-card-interactive h-100 p-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="brand-icon" style="width: 48px; height: 48px; font-size: 1.1rem; background: linear-gradient(135deg, #10b981, #059669);">
                                <i class="fas fa-file-csv"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-1 text-dark">Import POS Report</h6>
                                <p class="text-muted small mb-2">Upload file CSV penjualan harian dari mesin POS untuk otomatisasi pemotongan stok.</p>
                                <a href="import_csv.php" class="text-danger fw-semibold small text-decoration-none">
                                    Buka Import <i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="modern-card modern-card-interactive h-100 p-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="brand-icon" style="width: 48px; height: 48px; font-size: 1.1rem; background: linear-gradient(135deg, #3b82f6, #2563eb);">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-1 text-dark">Metode Smoothing</h6>
                                <p class="text-muted small mb-2">Simulasi & peramalan kebutuhan bahan baku dengan parameter alpha (&alpha;) dinamis.</p>
                                <a href="smoothing.php" class="text-primary fw-semibold small text-decoration-none">
                                    Hitung Forecasting <i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="modern-card modern-card-interactive h-100 p-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="brand-icon" style="width: 48px; height: 48px; font-size: 1.1rem; background: linear-gradient(135deg, #c01b22, #b91c1c);">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-1 text-dark">Rekomendasi SPK</h6>
                                <p class="text-muted small mb-2">Keputusan pengadaan bahan baku berdasarkan selisih peramalan dan stok riil.</p>
                                <a href="rekomendasi.php" class="text-danger fw-semibold small text-decoration-none">
                                    Lihat Rekomendasi <i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="modern-card modern-card-interactive h-100 p-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="brand-icon" style="width: 48px; height: 48px; font-size: 1.1rem; background: linear-gradient(135deg, #f59e0b, #d97706);">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-1 text-dark">Pusat 4 Laporan</h6>
                                <p class="text-muted small mb-2">Cetak laporan historis, hasil ramalan, analisis stok, dan pengadaan siap print.</p>
                                <a href="laporan.php" class="text-warning fw-semibold small text-decoration-none">
                                    Akses Laporan <i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Stock Alert & Methodology Card -->
        <div class="col-lg-5">
            <!-- Critical Stock Alert Box -->
            <div class="modern-card mb-4 border-danger border-opacity-25">
                <div class="modern-card-header bg-danger bg-opacity-10">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-triangle-exclamation text-danger fs-5"></i>
                        <div>
                            <h6 class="fw-bold mb-0 text-danger">Peringatan Stok Kritis</h6>
                            <span class="small text-muted">Item di bawah atau mendekati Safety Stock</span>
                        </div>
                    </div>
                    <span class="badge bg-danger rounded-pill"><?php echo $kritis; ?> Item</span>
                </div>
                <div class="p-0">
                    <?php if ($kritis > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while($bk = $bahan_kritis->fetch_assoc()): ?>
                                <div class="list-group-item px-3 py-3 d-flex align-items-center justify-content-between hover-bg-light">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-secondary bg-opacity-25 text-dark fw-bold" style="font-size: 0.7rem;"><?php echo $bk['kode_bahan']; ?></span>
                                            <span class="fw-bold text-dark text-truncate" style="max-width: 170px;"><?php echo $bk['nama_bahan']; ?></span>
                                        </div>
                                        <div class="small text-muted">
                                            Stok: <span class="fw-bold text-danger"><?php echo number_format($bk['stok_aktual'], 1) . ' ' . $bk['satuan']; ?></span> 
                                            &bull; Min: <span class="text-secondary"><?php echo number_format($bk['safety_stock'], 1) . ' ' . $bk['satuan']; ?></span>
                                        </div>
                                    </div>
                                    <a href="edit_bahan.php?id=<?php echo $bk['id']; ?>" class="btn btn-sm btn-outline-danger fw-semibold" title="Edit Stok">
                                        <i class="fas fa-pencil"></i>
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <div class="p-3 bg-light text-center border-top">
                            <a href="rekomendasi.php" class="btn btn-sm btn-danger fw-bold w-100">
                                <i class="fas fa-cart-plus me-1"></i> Proses Pengadaan Semua Bahan Kritis
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center">
                            <div class="text-success mb-2" style="font-size: 2.5rem;">
                                <i class="fas fa-circle-check"></i>
                            </div>
                            <h6 class="fw-bold text-dark">Semua Stok Bahan Aman</h6>
                            <p class="text-muted small mb-0">Tidak ada bahan baku yang berada di bawah ambang batas safety stock.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Methodology Card -->
            <div class="modern-card">
                <div class="modern-card-header">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-circle-info text-primary me-2"></i>Formula Perhitungan SPK
                    </h6>
                </div>
                <div class="modern-card-body">
                    <div class="p-3 bg-light rounded-3 mb-3 border font-monospace text-center">
                        <span class="fw-bold text-danger">F<sub>t+1</sub> = &alpha; &middot; Y<sub>t</sub> + (1 - &alpha;) &middot; F<sub>t</sub></span>
                    </div>
                    <div class="small text-muted mb-3">
                        <div class="mb-1"><strong>&bull; F<sub>t+1</sub></strong> : Peramalan kebutuhan periode mendatang.</div>
                        <div class="mb-1"><strong>&bull; Y<sub>t</sub></strong> : Data pemakaian riil / aktual periode berjalan.</div>
                        <div class="mb-1"><strong>&bull; F<sub>t</sub></strong> : Nilai peramalan periode berjalan.</div>
                        <div><strong>&bull; &alpha;</strong> : Parameter bobot pemulusan (0 &lt; &alpha; &lt; 1, default = 0.3).</div>
                    </div>
                    <div class="p-2 bg-warning bg-opacity-10 rounded-2 border border-warning border-opacity-25 small text-dark d-flex gap-2 align-items-center">
                        <i class="fas fa-lightbulb text-warning fs-5"></i>
                        <span>Keputusan Pengadaan = <strong>Max(0, Prediksi Kebutuhan - Stok Gudang)</strong>.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('stockOverviewChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [
                    {
                        label: 'Stok Aktual',
                        data: <?php echo json_encode($chart_stok); ?>,
                        backgroundColor: 'rgba(192, 27, 34, 0.85)',
                        borderColor: '#c01b22',
                        borderWidth: 1,
                        borderRadius: 6
                    },
                    {
                        label: 'Safety Stock',
                        data: <?php echo json_encode($chart_safety); ?>,
                        backgroundColor: 'rgba(217, 119, 6, 0.45)',
                        borderColor: '#d97706',
                        borderWidth: 1,
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { family: "'Plus Jakarta Sans', sans-serif", weight: '600' },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { family: "'Plus Jakarta Sans', sans-serif" },
                        bodyFont: { family: "'Plus Jakarta Sans', sans-serif" },
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { family: "'Plus Jakarta Sans', sans-serif" } }
                    }
                }
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>