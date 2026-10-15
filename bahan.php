<?php
include 'db.php';
require_once 'includes/auth.php';
require_manager();

$page_title = 'Data Master Bahan Baku';
$active_page = 'bahan';

// Ambil data bahan
$result = $conn->query("SELECT * FROM bahan_baku ORDER BY nama_bahan ASC");
$total_bahan = $result->num_rows;

// Hitung status aman vs kritis
$q_stats = $conn->query("SELECT 
    SUM(CASE WHEN stok_aktual <= safety_stock THEN 1 ELSE 0 END) as kritis,
    SUM(CASE WHEN stok_aktual > safety_stock THEN 1 ELSE 0 END) as aman
    FROM bahan_baku")->fetch_assoc();
$kritis_count = $q_stats['kritis'] ?? 0;
$aman_count = $q_stats['aman'] ?? 0;

include 'includes/header.php';
?>

<div class="container-fluid px-lg-4 py-4">

    <?php 
    $judul_laporan = 'Laporan Analisis Stok & Persediaan Bahan Baku';
    $subjudul_laporan = 'Monitoring Fisik Gudang & Ambang Batas Safety Stock &bull; Outlet Mall Artha Gading &bull; ' . date('F Y');
    include 'includes/print_header.php'; 
    ?>
    <div class="page-header-box">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item active text-danger fw-semibold" aria-current="page">Data Bahan Baku</li>
                </ol>
            </nav>
            <h1 class="page-header-title">
                <i class="fas fa-boxes-stacked text-danger"></i> Data Master Bahan Baku & Stok Gudang
            </h1>
            <p class="page-header-desc">
                Monitoring ketersediaan fisik bahan baku, ambang batas safety stock, dan satuan ukuran di Gindaco Artha Gading.
            </p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-soft">
                <i class="fas fa-print me-1"></i> Cetak Laporan
            </button>
            <a href="rekomendasi.php" class="btn btn-gindaco">
                <i class="fas fa-clipboard-check me-1"></i> Lihat Rekomendasi SPK
            </a>
        </div>
    </div>

    <!-- QUICK STATS ROW -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card stat-primary py-3">
                <div>
                    <p class="stat-label mb-1">Total Bahan Terdaftar</p>
                    <h4 class="stat-value mb-0 fs-3"><?php echo $total_bahan; ?> <span class="fs-6 fw-normal text-muted">Item</span></h4>
                </div>
                <div class="stat-icon" style="width: 44px; height: 44px; font-size: 1.2rem;">
                    <i class="fas fa-cubes"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card stat-success py-3">
                <div>
                    <p class="stat-label mb-1">Stok Aman / Stabil</p>
                    <h4 class="stat-value mb-0 fs-3 text-success"><?php echo $aman_count; ?> <span class="fs-6 fw-normal text-muted">Item</span></h4>
                </div>
                <div class="stat-icon" style="width: 44px; height: 44px; font-size: 1.2rem;">
                    <i class="fas fa-shield-check"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card stat-danger py-3">
                <div>
                    <p class="stat-label mb-1">Stok Kritis / Restock</p>
                    <h4 class="stat-value mb-0 fs-3 text-danger"><?php echo $kritis_count; ?> <span class="fs-6 fw-normal text-muted">Item</span></h4>
                </div>
                <div class="stat-icon" style="width: 44px; height: 44px; font-size: 1.2rem;">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN TABLE CARD -->
    <div class="modern-card">
        <div class="modern-card-header flex-column flex-md-row gap-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark">Daftar Inventaris Bahan Baku</h5>
                <span class="text-muted small" id="rowCountText">Menampilkan <?php echo $total_bahan; ?> bahan baku</span>
            </div>

            <!-- SEARCH & FILTER TOOLBAR -->
            <div class="d-flex flex-wrap gap-2 w-100 w-md-auto">
                <div class="input-group" style="max-width: 320px;">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" id="searchInput" class="form-control bg-light border-start-0" placeholder="Cari nama atau kode bahan...">
                </div>
                <select id="statusFilter" class="form-select bg-light" style="max-width: 170px;">
                    <option value="all">Semua Status</option>
                    <option value="kritis">Stok Kritis</option>
                    <option value="aman">Stok Aman</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-modern table-hover align-middle" id="bahanTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th style="width: 130px;">Kode Bahan</th>
                        <th>Nama Bahan Baku</th>
                        <th style="width: 100px;">Satuan</th>
                        <th style="width: 150px;">Stok Aktual</th>
                        <th style="width: 150px;">Safety Stock</th>
                        <th style="width: 130px;">Status Gudang</th>
                        <th style="width: 110px;" class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1; 
                    while($row = $result->fetch_assoc()): 
                        $is_kritis = ($row['stok_aktual'] <= $row['safety_stock']);
                        $status_class = $is_kritis ? 'kritis' : 'aman';
                    ?>
                    <tr data-status="<?php echo $status_class; ?>">
                        <td class="text-muted fw-semibold"><?php echo $no++; ?></td>
                        <td>
                            <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                <?php echo htmlspecialchars($row['kode_bahan']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['nama_bahan']); ?></div>
                        </td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                <?php echo htmlspecialchars($row['satuan']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="fw-bold <?php echo $is_kritis ? 'text-danger' : 'text-dark'; ?>">
                                <?php echo number_format($row['stok_aktual'], 2); ?>
                            </span>
                        </td>
                        <td>
                            <span class="text-muted">
                                <?php echo number_format($row['safety_stock'], 2); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($is_kritis): ?>
                                <span class="badge-pill-custom badge-subtle-danger">
                                    <i class="fas fa-triangle-exclamation"></i> Kritis
                                </span>
                            <?php else: ?>
                                <span class="badge-pill-custom badge-subtle-success">
                                    <i class="fas fa-check-circle"></i> Aman
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center no-print">
                            <a href="edit_bahan.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary px-2 py-1" title="Edit Stok & Safety Stock">
                                <i class="fas fa-pen-to-square me-1"></i> Edit
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php include 'includes/print_footer.php'; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const tableRows = document.querySelectorAll('#bahanTable tbody tr');
    const rowCountText = document.getElementById('rowCountText');

    function filterTable() {
        const query = searchInput.value.toLowerCase().trim();
        const selectedStatus = statusFilter.value;
        let visibleCount = 0;

        tableRows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const status = row.getAttribute('data-status');

            const matchesQuery = text.includes(query);
            const matchesStatus = (selectedStatus === 'all') || (status === selectedStatus);

            if (matchesQuery && matchesStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        rowCountText.textContent = `Menampilkan ${visibleCount} dari ${tableRows.length} bahan baku`;
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (statusFilter) statusFilter.addEventListener('change', filterTable);
});
</script>

<?php include 'includes/footer.php'; ?>