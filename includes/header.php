<?php
// Ensure session and auth are loaded
require_once __DIR__ . '/auth.php';

// Ensure active_page is defined
if (!isset($active_page)) {
    $active_page = '';
}
if (!isset($page_title)) {
    $page_title = 'SPK Pengadaan Bahan Gindaco';
}

$current_user = get_current_user_data();
$user_is_manager = is_manager();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Gindaco SPK</title>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Select2 (Optional for forms) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Custom Modern Stylesheet -->
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
    <!-- MODERN NAVBAR -->
    <nav class="navbar navbar-expand-xl navbar-custom sticky-top">
        <div class="container-fluid px-lg-4">
            <a class="navbar-brand-custom" href="index.php">
                <div class="bg-white px-2 py-1 rounded-2 shadow-sm d-flex align-items-center justify-content-center" style="height: 38px;">
                    <img src="assets/img/gindaco_logo.webp" alt="Logo Gindaco" style="max-height: 26px; width: auto; object-fit: contain;">
                </div>
                <div>
                    <h1 class="brand-text-title">GINDACO SPK</h1>
                    <p class="brand-text-sub"><i class="fas fa-store me-1 text-warning"></i> Mall Artha Gading</p>
                </div>
            </a>

            <button class="navbar-toggler text-white border-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>

            <div class="collapse navbar-collapse mt-3 mt-xl-0" id="navbarMain">
                <ul class="navbar-nav ms-auto mb-2 mb-xl-0 gap-1 align-items-xl-center">
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($active_page === 'dashboard') ? 'active' : ''; ?>" href="index.php">
                            <i class="fas fa-chart-pie"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($active_page === 'smoothing') ? 'active' : ''; ?>" href="smoothing.php">
                            <i class="fas fa-calculator"></i>
                            <span>Smoothing Bulanan</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($active_page === 'weekly_forecast') ? 'active' : ''; ?>" href="weekly_forecast.php">
                            <i class="fas fa-calendar-week text-warning"></i>
                            <span>Weekly Forecast</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($active_page === 'rekomendasi') ? 'active' : ''; ?>" href="rekomendasi.php">
                            <i class="fas fa-clipboard-check"></i>
                            <span>Rekomendasi SPK</span>
                        </a>
                    </li>

                    <!-- MENU KHUSUS MANAGER -->
                    <?php if ($user_is_manager): ?>
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($active_page === 'bahan') ? 'active' : ''; ?>" href="bahan.php">
                            <i class="fas fa-boxes-stacked"></i>
                            <span>Data Bahan</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($active_page === 'import') ? 'active' : ''; ?>" href="import_csv.php">
                            <i class="fas fa-file-csv"></i>
                            <span>Import CSV</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($active_page === 'laporan') ? 'active' : ''; ?>" href="laporan.php">
                            <i class="fas fa-file-invoice"></i>
                            <span>4 Laporan</span>
                        </a>
                    </li>
                </ul>

                <div class="d-flex align-items-center ms-xl-3 ps-xl-3 border-start border-secondary pt-2 pt-xl-0 gap-2">
                    <?php if ($current_user): ?>
                    <div class="user-chip text-white">
                        <div class="user-avatar" style="<?php echo $user_is_manager ? 'background: linear-gradient(135deg, #c01b22, #ea580c);' : 'background: linear-gradient(135deg, #2563eb, #0891b2);'; ?>">
                            <i class="fas <?php echo $user_is_manager ? 'fa-user-shield' : 'fa-user'; ?>"></i>
                        </div>
                        <div class="d-none d-sm-block text-start" style="line-height: 1.2;">
                            <span class="d-block fw-bold text-truncate" style="font-size: 0.8rem; max-width: 140px;" title="<?php echo htmlspecialchars($current_user['nama']); ?>">
                                <?php echo htmlspecialchars($current_user['nama']); ?>
                            </span>
                            <span class="badge <?php echo $user_is_manager ? 'bg-danger' : 'bg-primary'; ?> px-2 py-0" style="font-size: 0.65rem; text-transform: uppercase;">
                                <?php echo htmlspecialchars($current_user['level']); ?>
                            </span>
                        </div>
                    </div>
                    <a href="logout.php" class="btn btn-sm btn-outline-light rounded-pill px-2 py-1" title="Keluar dari Akun">
                        <i class="fas fa-arrow-right-from-bracket"></i>
                    </a>
                    <?php else: ?>
                    <a href="login.php" class="btn btn-sm btn-gindaco rounded-pill px-3 py-1">
                        <i class="fas fa-arrow-right-to-bracket me-1"></i> Masuk
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main class="flex-grow-1">

