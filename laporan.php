<?php
include 'db.php';
require_once 'includes/auth.php';
require_login();

$total_bahan = $conn->query("SELECT COUNT(*) as total FROM bahan_baku")->fetch_assoc()['total'];
$total_historis = $conn->query("SELECT COUNT(*) as total FROM data_historis")->fetch_assoc()['total'];
$kritis = $conn->query("SELECT COUNT(*) as total FROM bahan_baku WHERE stok_aktual <= safety_stock")->fetch_assoc()['total'];

$page_title = 'Pusat 4 Jenis Laporan SPK';
$active_page = 'laporan';
include 'includes/header.php';
?>

<div class="container-fluid px-lg-4 py-4">

    <!-- PAGE HEADER -->
    <div class="page-header-box">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item active text-danger fw-semibold" aria-current="page">Pusat Laporan</li>
                </ol>
            </nav>
            <h1 class="page-header-title">
                <i class="fas fa-file-invoice text-warning"></i> Pusat 4 Laporan Manajerial SPK
            </h1>
            <p class="page-header-desc">
                Dokumentasi terstruktur hasil kalkulasi sistem pendukung keputusan pengadaan bahan baku Gindaco Mall Artha Gading.
            </p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-soft">
                <i class="fas fa-print me-1"></i> Cetak Ringkasan
            </button>
        </div>
    </div>

    <!-- 4 REPORT CARDS GRID -->
    <div class="row g-4 mb-4">
        <!-- LAPORAN 1 -->
        <div class="col-lg-6">
            <div class="modern-card modern-card-interactive h-100 p-4 border-primary border-opacity-25">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="brand-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                        <i class="fas fa-clock-rotate-left"></i>
                    </div>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fw-bold px-3 py-1">
                        Laporan 1
                    </span>
                </div>
                <h5 class="fw-bold text-dark mb-2">Laporan Data Historis Pemakaian</h5>
                <p class="text-muted small mb-4">
                    Rekapitulasi catatan historis penggunaan riil bahan baku Takoyaki & Taiyaki per periode bulanan sebagai landasan dasar perhitungan peramalan time-series.
                </p>
                <div class="p-3 bg-light rounded-3 mb-4 d-flex justify-content-between align-items-center">
                    <span class="text-muted small"><i class="fas fa-database text-primary me-1"></i> Total Rekaman:</span>
                    <strong class="text-dark"><?php echo number_format($total_historis); ?> Baris Data</strong>
                </div>
                <div class="d-flex gap-2">
                    <a href="historis.php" class="btn btn-outline-primary flex-grow-1 fw-semibold">
                        <i class="fas fa-eye me-1"></i> Buka Tabel
                    </a>
                    <a href="historis.php?print=true" target="_blank" class="btn btn-primary fw-semibold px-3">
                        <i class="fas fa-print me-1"></i> Cetak Resmi
                    </a>
                </div>
            </div>
        </div>

        <!-- LAPORAN 2 -->
        <div class="col-lg-6">
            <div class="modern-card modern-card-interactive h-100 p-4 border-success border-opacity-25">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="brand-icon" style="background: linear-gradient(135deg, #10b981, #047857);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fw-bold px-3 py-1">
                        Laporan 2
                    </span>
                </div>
                <h5 class="fw-bold text-dark mb-2">Laporan Hasil Peramalan (Forecasting)</h5>
                <p class="text-muted small mb-4">
                    Tabel komputasi Single Exponential Smoothing lengkap dengan nilai aktual ($Y_t$), ramalan berjalan ($F_t$), dan proyeksi kebutuhan periode berikutnya ($F_{t+1}$).
                </p>
                <div class="p-3 bg-light rounded-3 mb-4 d-flex justify-content-between align-items-center">
                    <span class="text-muted small"><i class="fas fa-sliders text-success me-1"></i> Parameter Pemulusan:</span>
                    <strong class="text-success">&alpha; = 0.3 (Default)</strong>
                </div>
                <div class="d-flex gap-2">
                    <a href="smoothing.php" class="btn btn-outline-success flex-grow-1 fw-semibold">
                        <i class="fas fa-calculator me-1"></i> Buka Tabel
                    </a>
                    <a href="smoothing.php?print=true" target="_blank" class="btn btn-success fw-semibold px-3">
                        <i class="fas fa-print me-1"></i> Cetak Resmi
                    </a>
                </div>
            </div>
        </div>

        <!-- LAPORAN 3 -->
        <div class="col-lg-6">
            <div class="modern-card modern-card-interactive h-100 p-4 border-warning border-opacity-25">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="brand-icon" style="background: linear-gradient(135deg, #f59e0b, #b45309);">
                        <i class="fas fa-boxes-stacked"></i>
                    </div>
                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fw-bold px-3 py-1">
                        Laporan 3
                    </span>
                </div>
                <h5 class="fw-bold text-dark mb-2">Laporan Analisis Stok & Persediaan Gudang</h5>
                <p class="text-muted small mb-4">
                    Status inventaris fisik seluruh komponen bahan baku saat ini dibandingkan dengan ambang batas minimum keselamatan (safety stock) outlet Artha Gading.
                </p>
                <div class="p-3 bg-light rounded-3 mb-4 d-flex justify-content-between align-items-center">
                    <span class="text-muted small"><i class="fas fa-triangle-exclamation text-danger me-1"></i> Bahan Kritis:</span>
                    <strong class="<?php echo ($kritis > 0) ? 'text-danger' : 'text-success'; ?>"><?php echo number_format($kritis); ?> Item Kritis</strong>
                </div>
                <div class="d-flex gap-2">
                    <a href="bahan.php" class="btn btn-outline-warning text-dark flex-grow-1 fw-semibold">
                        <i class="fas fa-warehouse me-1"></i> Buka Tabel
                    </a>
                    <a href="bahan.php?print=true" target="_blank" class="btn btn-warning text-dark fw-semibold px-3">
                        <i class="fas fa-print me-1"></i> Cetak Resmi
                    </a>
                </div>
            </div>
        </div>

        <!-- LAPORAN 4 -->
        <div class="col-lg-6">
            <div class="modern-card modern-card-interactive h-100 p-4 border-danger border-opacity-25">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="brand-icon" style="background: linear-gradient(135deg, #c01b22, #991b1b);">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 fw-bold px-3 py-1">
                        Laporan 4
                    </span>
                </div>
                <h5 class="fw-bold text-dark mb-2">Laporan Rekomendasi Pengadaan Akhir</h5>
                <p class="text-muted small mb-4">
                    Hasil sintesis akhir Sistem Pendukung Keputusan (SPK) yang menyajikan rekomendasi kuantitas pembelian bahan baku untuk diserahkan ke tim Purchasing / Kitchen.
                </p>
                <div class="p-3 bg-light rounded-3 mb-4 d-flex justify-content-between align-items-center">
                    <span class="text-muted small"><i class="fas fa-check-double text-danger me-1"></i> Status SPK:</span>
                    <strong class="text-danger">Siap Dicetak & Dieksekusi</strong>
                </div>
                <div class="d-flex gap-2">
                    <a href="rekomendasi.php" class="btn btn-outline-danger flex-grow-1 fw-semibold">
                        <i class="fas fa-file-contract me-1"></i> Buka Rekomendasi
                    </a>
                    <a href="rekomendasi.php?print=true" target="_blank" class="btn btn-gindaco fw-semibold px-3">
                        <i class="fas fa-print me-1"></i> Cetak Resmi
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- DOCUMENTATION GUIDE -->
    <div class="modern-card">
        <div class="modern-card-header">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fas fa-circle-question text-info me-2"></i>Panduan Penggunaan Laporan untuk Sidang / Evaluasi Outlet
            </h6>
        </div>
        <div class="modern-card-body p-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="fw-bold text-dark mb-1">1. Alur Verifikasi</div>
                    <p class="text-muted small mb-0">Pastikan data transaksi POS telah terimpor secara berkala sebelum mencetak Laporan 1 dan 2.</p>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold text-dark mb-1">2. Penyesuaian Alpha</div>
                    <p class="text-muted small mb-0">Jika terjadi perubahan tren musiman (misal promo/hari libur), sesuaikan nilai alpha pada Laporan 2.</p>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold text-dark mb-1">3. Cek Fisik Gudang</div>
                    <p class="text-muted small mb-0">Cocokkan stok aktual di Laporan 3 dengan penghitungan fisik (stock opname) gudang outlet.</p>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold text-dark mb-1">4. Purchase Order</div>
                    <p class="text-muted small mb-0">Gunakan Laporan 4 sebagai Purchase Request (PR) resmi kepada Central Kitchen Gindaco.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>