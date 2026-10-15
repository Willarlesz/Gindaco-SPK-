<?php
include 'db.php';
$pesan = "";

if (isset($_POST['submit_penjualan'])) {
    $tanggal = $_POST['tanggal'];
    $id_menu = intval($_POST['id_menu']);
    $jumlah = intval($_POST['jumlah_terjual']);

    if ($jumlah > 0 && $id_menu > 0) {
        $conn->query("INSERT INTO transaksi_penjualan (tanggal, id_menu, jumlah_terjual) VALUES ('$tanggal', $id_menu, $jumlah)");

        $bom = $conn->query("SELECT * FROM menu_bom WHERE id_menu = $id_menu");
        $affected = 0;
        if ($bom) {
            while($b = $bom->fetch_assoc()) {
                $kode_bahan = $b['kode_bahan'];
                $qty_butuh = $b['qty_dibutuhkan'] * $jumlah;
                $conn->query("UPDATE bahan_baku SET stok_aktual = stok_aktual - $qty_butuh WHERE kode_bahan = '$kode_bahan'");
                $affected++;
            }
        }
        $pesan = "
        <div class='alert alert-success d-flex align-items-center gap-3 shadow-sm border-0 mb-4' role='alert'>
            <div class='fs-3 text-success'><i class='fas fa-circle-check'></i></div>
            <div>
                <h6 class='fw-bold mb-1'>Transaksi Penjualan Berhasil Disimpan!</h6>
                <div class='small'>Stok bahan baku untuk menu ini telah otomatis dikurangi sebanyak <strong>$affected</strong> komponen berdasarkan komposisi Bill of Materials (BOM).</div>
            </div>
        </div>";
    } else {
        $pesan = "
        <div class='alert alert-danger d-flex align-items-center gap-3 shadow-sm border-0 mb-4' role='alert'>
            <div class='fs-3 text-danger'><i class='fas fa-triangle-exclamation'></i></div>
            <div>
                <h6 class='fw-bold mb-1'>Validasi Gagal</h6>
                <div class='small'>Harap pilih menu dan pastikan kuantitas penjualan lebih dari 0.</div>
            </div>
        </div>";
    }
}

$menus = $conn->query("SELECT * FROM menu ORDER BY nama_menu ASC");
$riwayat = $conn->query("SELECT t.*, m.nama_menu FROM transaksi_penjualan t LEFT JOIN menu m ON t.id_menu = m.id ORDER BY t.id DESC LIMIT 15");

$page_title = 'Input Penjualan Menu & Auto BOM';
$active_page = 'penjualan';
include 'includes/header.php';
?>

<div class="container-fluid px-lg-4 py-4">

    <!-- PAGE HEADER -->
    <div class="page-header-box">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item active text-danger fw-semibold" aria-current="page">Input Penjualan</li>
                </ol>
            </nav>
            <h1 class="page-header-title">
                <i class="fas fa-cash-register text-primary"></i> Input Penjualan Manual (POS Sim)
            </h1>
            <p class="page-header-desc">
                Pencatatan pesanan kasir secara satuan / manual dengan kalkulasi otomatis pemotongan stok bahan baku gudang via resep BOM.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="import_csv.php" class="btn btn-soft">
                <i class="fas fa-file-csv me-1"></i> Buka Import CSV
            </a>
            <a href="bahan.php" class="btn btn-gindaco">
                <i class="fas fa-boxes-stacked me-1"></i> Cek Stok Gudang
            </a>
        </div>
    </div>

    <?php echo $pesan; ?>

    <div class="row g-4 mb-4">
        <!-- FORM INPUT CARD -->
        <div class="col-lg-5">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-plus-circle text-danger me-2"></i>Entri Transaksi Baru
                    </h5>
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Auto-BOM</span>
                </div>
                <div class="modern-card-body p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small text-uppercase">
                                <i class="far fa-calendar-alt text-muted me-1"></i> Tanggal Transaksi
                            </label>
                            <input type="date" class="form-control" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small text-uppercase">
                                <i class="fas fa-utensils text-muted me-1"></i> Pilih Menu Gindaco
                            </label>
                            <select class="form-select select2" name="id_menu" required>
                                <option value="">-- Pilih Varian Menu Takoyaki / Taiyaki --</option>
                                <?php if ($menus): while($m = $menus->fetch_assoc()): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['nama_menu']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark small text-uppercase">
                                <i class="fas fa-cubes-stacked text-muted me-1"></i> Kuantitas Terjual (Porsi)
                            </label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="jumlah_terjual" min="1" value="1" required>
                                <span class="input-group-text bg-light text-muted fw-bold">Porsi</span>
                            </div>
                            <div class="form-text small">Kuantitas ini akan dikalikan dengan resep BOM untuk memotong stok bahan baku.</div>
                        </div>

                        <button type="submit" name="submit_penjualan" class="btn btn-gindaco w-100 py-2 fw-bold">
                            <i class="fas fa-check-circle me-1"></i> Proses Penjualan & Kurangi Stok
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- BOM RECIPE LOGIC INFO -->
        <div class="col-lg-7">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-circle-nodes text-success me-2"></i>Mekanisme Bill of Materials (BOM)
                    </h5>
                    <span class="badge badge-subtle-success">Terintegrasi</span>
                </div>
                <div class="modern-card-body p-4">
                    <p class="text-muted small">
                        Di Gindaco, setiap varian menu (Original, Teritama, Mentaiko Cheese) terdiri atas takaran resep bahan baku baku yang telah terstandarisasi:
                    </p>
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="fw-bold text-dark small mb-1"><i class="fas fa-cookie-bite text-warning me-1"></i> Original Takoyaki</div>
                                <div class="text-muted small">Tepung premix, octopus, cabbage, green dried seaweed, katsuobushi, gindaco sauce.</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="fw-bold text-dark small mb-1"><i class="fas fa-egg text-warning me-1"></i> Teritama Takoyaki</div>
                                <div class="text-muted small">Komponen Original + telur tamagoyaki, kewpie mayo, teriyaki sauce, kizami nori.</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="fw-bold text-dark small mb-1"><i class="fas fa-cheese text-warning me-1"></i> Mentaiko Cheese</div>
                                <div class="text-muted small">Komponen Original + spicy mentaiko sauce, shredded mozzarella, parmesan.</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="fw-bold text-dark small mb-1"><i class="fas fa-box text-warning me-1"></i> Kemasan Packaging</div>
                                <div class="text-muted small">Boat tray 4/6/8, liner gindaco, tusuk sate yakitori, spunbond bag.</div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 px-3 small mb-0 border-0 d-flex align-items-center gap-2">
                        <i class="fas fa-info-circle text-info fs-5"></i>
                        <span>Setiap kali pesanan disimpan, sistem secara otomatis mengeksekusi trigger pengurangan stok gudang di tabel <code>bahan_baku</code>.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RECENT TRANSACTIONS TABLE -->
    <div class="modern-card">
        <div class="modern-card-header">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-history text-secondary me-2"></i>Riwayat Entri Penjualan Terbaru
                </h5>
                <span class="text-muted small">Daftar transaksi kasir yang tercatat di database</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-modern table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th style="width: 140px;">Tanggal</th>
                        <th>Varian Menu / Artikel</th>
                        <th style="width: 160px;" class="text-center">Kuantitas Terjual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($riwayat && $riwayat->num_rows > 0): ?>
                        <?php $no = 1; while($r = $riwayat->fetch_assoc()): ?>
                        <tr>
                            <td class="text-muted fw-semibold"><?php echo $no++; ?></td>
                            <td>
                                <span class="small fw-semibold text-dark">
                                    <i class="far fa-calendar-alt text-muted me-1"></i><?php echo htmlspecialchars($r['tanggal']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">
                                    <?php echo htmlspecialchars($r['nama_menu'] ?? ($r['article_name'] ?? 'Menu Gindaco')); ?>
                                </div>
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
                            <td colspan="4" class="text-center py-4 text-muted">
                                Belum ada transaksi penjualan yang tercatat.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>