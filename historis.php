<?php
include 'db.php';
require_once 'includes/auth.php';
require_login();

$result = $conn->query("SELECT h.*, b.nama_bahan, b.satuan, b.kode_bahan FROM data_historis h JOIN bahan_baku b ON h.id_bahan = b.id ORDER BY h.id ASC");
$total_rows = $result ? $result->num_rows : 0;

$page_title = 'Laporan 1: Data Historis Pemakaian';
$active_page = 'laporan';
include 'includes/header.php';
?>

<div class="container-fluid px-lg-4 py-4">

    <?php 
    $judul_laporan = 'Laporan Data Historis Pemakaian Bahan Baku';
    $subjudul_laporan = 'Rekapitulasi Mutasi Pemakaian Fisik &bull; Outlet Mall Artha Gading &bull; ' . date('F Y');
    include 'includes/print_header.php'; 
    ?>
    <div class="page-header-box">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="laporan.php" class="text-decoration-none text-muted">4 Laporan</a></li>
                    <li class="breadcrumb-item active text-danger fw-semibold" aria-current="page">Laporan Data Historis</li>
                </ol>
            </nav>
            <h1 class="page-header-title">
                <i class="fas fa-clock-rotate-left text-primary"></i> Laporan Data Historis Pemakaian Bahan Baku
            </h1>
            <p class="page-header-desc">
                Arsip data historis konsumsi bahan baku per periode bulanan sebagai baseline kalkulasi Single Exponential Smoothing.
            </p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-soft">
                <i class="fas fa-print me-1"></i> Cetak Laporan Historis
            </button>
            <a href="laporan.php" class="btn btn-gindaco">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Pusat Laporan
            </a>
        </div>
    </div>

    <!-- MAIN TABLE CARD -->
    <div class="modern-card">
        <div class="modern-card-header flex-column flex-md-row gap-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-database text-secondary me-2"></i>Tabel Riwayat Pemakaian Bahan Riil
                </h5>
                <span class="text-muted small" id="rowCountText">Menampilkan <?php echo $total_rows; ?> baris catatan historis</span>
            </div>

            <div class="input-group" style="max-width: 320px;">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                <input type="text" id="searchInput" class="form-control bg-light border-start-0" placeholder="Cari periode atau bahan baku...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-modern table-hover align-middle mb-0" id="historisTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th style="width: 140px;">Periode</th>
                        <th style="width: 140px;">Kode Bahan</th>
                        <th>Nama Bahan Baku</th>
                        <th style="width: 180px;" class="text-end">Pemakaian Aktual (Y<sub>t</sub>)</th>
                        <th style="width: 110px;" class="text-center">Satuan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1; 
                    if ($result && $result->num_rows > 0):
                        while($row = $result->fetch_assoc()): 
                    ?>
                    <tr>
                        <td class="text-muted fw-semibold"><?php echo $no++; ?></td>
                        <td>
                            <span class="badge bg-light text-primary border border-primary border-opacity-25 px-2 py-1 fw-bold">
                                <i class="far fa-calendar-check me-1"></i><?php echo htmlspecialchars($row['periode']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border font-monospace">
                                <?php echo htmlspecialchars($row['kode_bahan']); ?>
                            </span>
                        </td>
                        <td>
                            <strong class="text-dark"><?php echo htmlspecialchars($row['nama_bahan']); ?></strong>
                        </td>
                        <td class="text-end">
                            <span class="fs-6 fw-bold text-dark font-monospace">
                                <?php echo number_format($row['pemakaian_aktual'], 2); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                <?php echo htmlspecialchars($row['satuan']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php 
                        endwhile; 
                    else:
                    ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            Belum ada rekaman data historis.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php include 'includes/print_footer.php'; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('#historisTable tbody tr');
    const rowCountText = document.getElementById('rowCountText');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            let count = 0;

            tableRows.forEach(row => {
                const text = row.innerText.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                    count++;
                } else {
                    row.style.display = 'none';
                }
            });

            rowCountText.textContent = `Menampilkan ${count} dari ${tableRows.length} baris catatan historis`;
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>