<?php
include 'db.php';
require_once 'includes/auth.php';
require_manager();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (isset($_POST['update'])) {
    $stok = floatval($_POST['stok_aktual']);
    $safety = floatval($_POST['safety_stock']);

    $old = $conn->query("SELECT * FROM bahan_baku WHERE id = $id")->fetch_assoc();
    $conn->query("UPDATE bahan_baku SET stok_aktual = $stok, safety_stock = $safety WHERE id = $id");

    if ($old) {
        catat_audit_log($conn, 'UPDATE_STOK', 'bahan_baku', $id, [
            'nama_bahan' => $old['nama_bahan'],
            'stok_lama' => (float)$old['stok_aktual'],
            'stok_baru' => $stok,
            'safety_lama' => (float)$old['safety_stock'],
            'safety_baru' => $safety
        ]);
    }

    header("Location: bahan.php?updated=1");
    exit();
}

$row = $conn->query("SELECT * FROM bahan_baku WHERE id = $id")->fetch_assoc();
if (!$row) {
    header("Location: bahan.php");
    exit();
}

$page_title = 'Edit Stok Bahan: ' . $row['nama_bahan'];
$active_page = 'bahan';
include 'includes/header.php';
?>

<div class="container py-5" style="max-width: 650px;">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="bahan.php" class="text-decoration-none text-muted">Data Bahan</a></li>
            <li class="breadcrumb-item active text-danger fw-semibold" aria-current="page">Edit Stok</li>
        </ol>
    </nav>

    <div class="modern-card">
        <div class="modern-card-header bg-light">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-icon" style="width: 44px; height: 44px; font-size: 1.1rem;">
                    <i class="fas fa-boxes-stacked"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">Edit Stok & Safety Stock</h5>
                    <span class="text-muted small">Update kuantitas fisik gudang Gindaco</span>
                </div>
            </div>
            <span class="badge bg-secondary font-monospace"><?php echo htmlspecialchars($row['kode_bahan']); ?></span>
        </div>

        <div class="modern-card-body p-4">
            <!-- Item Info Box -->
            <div class="p-3 bg-light rounded-3 border mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small d-block">Nama Bahan Baku:</span>
                    <strong class="text-dark fs-6"><?php echo htmlspecialchars($row['nama_bahan']); ?></strong>
                </div>
                <div class="text-end">
                    <span class="text-muted small d-block">Satuan:</span>
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 fw-bold px-2 py-1">
                        <?php echo htmlspecialchars($row['satuan']); ?>
                    </span>
                </div>
            </div>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small text-uppercase">
                        <i class="fas fa-warehouse text-primary me-1"></i> Stok Aktual Gudang
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-cubes text-muted"></i></span>
                        <input type="number" step="any" class="form-control form-control-lg fs-6" name="stok_aktual" id="inputStok" value="<?php echo $row['stok_aktual']; ?>" required>
                        <span class="input-group-text bg-light text-muted fw-bold"><?php echo htmlspecialchars($row['satuan']); ?></span>
                    </div>
                    <div class="form-text small">Masukkan jumlah fisik barang yang tersedia di outlet saat ini.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-dark small text-uppercase">
                        <i class="fas fa-shield-halved text-warning me-1"></i> Safety Stock (Batas Aman)
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-lock text-muted"></i></span>
                        <input type="number" step="any" class="form-control form-control-lg fs-6" name="safety_stock" id="inputSafety" value="<?php echo $row['safety_stock']; ?>" required>
                        <span class="input-group-text bg-light text-muted fw-bold"><?php echo htmlspecialchars($row['satuan']); ?></span>
                    </div>
                    <div class="form-text small">Ambang batas minimum persediaan sebelum sistem memicu peringatan order ulang.</div>
                </div>

                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                    <a href="bahan.php" class="btn btn-soft">
                        <i class="fas fa-arrow-left me-1"></i> Batal
                    </a>
                    <button type="submit" name="update" class="btn btn-gindaco px-4">
                        <i class="fas fa-check me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>