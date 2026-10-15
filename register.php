<?php
require_once 'db.php';
require_once 'includes/auth.php';

// Jika sudah login dan bukan Manager yang mendaftarkan staff, arahkan ke index
$is_admin_mode = is_manager();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik = trim($_POST['nik'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';
    $level_request = trim($_POST['level'] ?? 'staff');

    // Validasi
    if (empty($nik) || empty($nama) || empty($password)) {
        $error = "Semua field wajib diisi.";
    } elseif (!preg_match('/^[a-zA-Z0-9_-]{4,20}$/', $nik)) {
        $error = "NIK harus alfanumerik (4-20 karakter).";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal harus 6 karakter.";
    } elseif ($password !== $konfirmasi) {
        $error = "Konfirmasi password tidak cocok.";
    } else {
        // Keamanan Ketat: Hanya Manager yang sedang login atau token otorisasi yang dapat membuat akun Manager
        $level = 'staff'; // default
        if ($level_request === 'manager') {
            $auth_code = trim($_POST['manager_auth_token'] ?? '');
            // Token rahasia otorisasi pembuatan akun manager tanpa login sebelumnya: "GINDACO-MGR-2026"
            // Atau jika sedang login sebagai manager
            if ($is_admin_mode || $auth_code === 'GINDACO-MGR-2026') {
                $level = 'manager';
            } else {
                $error = "Kode Otorisasi Manager tidak valid. Pendaftaran level Manager ditolak demi keamanan.";
            }
        }

        if (empty($error)) {
            // Cek apakah NIK sudah digunakan
            $nik_safe = $conn->real_escape_string($nik);
            $cek = $conn->query("SELECT id FROM users WHERE nik = '$nik_safe' LIMIT 1");
            if ($cek && $cek->num_rows > 0) {
                $error = "NIK '$nik' sudah terdaftar dalam sistem. Silakan gunakan NIK lain.";
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $nama_safe = $conn->real_escape_string($nama);

                $ins = $conn->query("INSERT INTO users (nik, nama, password, level) VALUES ('$nik_safe', '$nama_safe', '$hash', '$level')");
                if ($ins) {
                    $new_id = $conn->insert_id;
                    catat_audit_log($conn, 'REGISTER', 'users', $new_id, "Pendaftaran user baru: NIK $nik, Nama $nama, Level $level.");

                    if ($is_admin_mode) {
                        $success = "Akun $nama ($level) berhasil didaftarkan oleh Manager.";
                    } else {
                        header("Location: login.php?registered=1");
                        exit();
                    }
                } else {
                    $error = "Gagal menyimpan akun: " . $conn->error;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Pengguna - SPK Gindaco</title>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/custom.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #18181b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }
        .register-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            width: 100%;
            max-width: 520px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .register-header {
            background: linear-gradient(135deg, #c01b22 0%, #991b1b 100%);
            color: #ffffff;
            padding: 26px 24px 20px;
            text-align: center;
        }
        .logo-box {
            background: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            margin-bottom: 12px;
        }
    </style>
</head>
<body>

    <div class="register-card">
        <div class="register-header">
            <div class="logo-box">
                <img src="assets/img/gindaco_logo.webp" alt="Logo Gindaco" style="max-height: 36px; width: auto; object-fit: contain;">
            </div>
            <h5 class="fw-bold mb-1 letter-spacing-tight">Pendaftaran Pengguna Sistem</h5>
            <p class="mb-0 text-white-50 small">Sistem Pendukung Keputusan &bull; Outlet Mall Artha Gading</p>
        </div>

        <div class="p-4 p-md-5">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center small py-2 px-3 rounded-3 mb-3 border-danger border-opacity-25">
                    <i class="fas fa-circle-exclamation me-2 fs-6"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success d-flex align-items-center small py-2 px-3 rounded-3 mb-3 border-success border-opacity-25">
                    <i class="fas fa-circle-check me-2 fs-6"></i>
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" id="regForm">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-bold text-dark text-uppercase">Nomor Induk Karyawan (NIK) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fas fa-id-card"></i></span>
                            <input type="text" name="nik" class="form-control bg-light" placeholder="Contoh: 20260012" value="<?php echo htmlspecialchars($_POST['nik'] ?? ''); ?>" required autocomplete="off">
                        </div>
                        <span class="text-muted" style="font-size: 0.72rem;">NIK harus unik dan digunakan sebagai username saat login.</span>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold text-dark text-uppercase">Nama Lengkap Karyawan <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fas fa-user"></i></span>
                            <input type="text" name="nama" class="form-control bg-light" placeholder="Contoh: Wildan Ari Leksono" value="<?php echo htmlspecialchars($_POST['nama'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-dark text-uppercase">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" class="form-control bg-light" placeholder="Min. 6 karakter" required minlength="6">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-dark text-uppercase">Konfirmasi Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fas fa-check-double"></i></span>
                            <input type="password" name="konfirmasi_password" class="form-control bg-light" placeholder="Ulangi password" required minlength="6">
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold text-dark text-uppercase">Level Akses (Hak Akses)</label>
                        <select name="level" id="levelSelect" class="form-select bg-light fw-bold" onchange="toggleManagerToken()">
                            <option value="staff" <?php echo (isset($_POST['level']) && $_POST['level'] === 'staff') ? 'selected' : ''; ?>>
                                Staff (Akses Operasional &amp; Prediksi Forecasting)
                            </option>
                            <option value="manager" <?php echo (isset($_POST['level']) && $_POST['level'] === 'manager') ? 'selected' : ''; ?>>
                                Manager (Akses Penuh Manajemen &amp; Edit Data)
                            </option>
                        </select>
                        <span class="text-muted" style="font-size: 0.72rem;">Secara default pengguna didaftarkan sebagai <strong>Staff</strong> demi integritas sistem.</span>
                    </div>

                    <!-- Token Otorisasi Khusus jika memilih Manager -->
                    <div class="col-12" id="managerTokenRow" style="display: <?php echo (isset($_POST['level']) && $_POST['level'] === 'manager') ? 'block' : 'none'; ?>;">
                        <div class="p-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded-3">
                            <label class="form-label small fw-bold text-danger text-uppercase mb-1">
                                <i class="fas fa-key me-1"></i> Kode Otorisasi Manager
                            </label>
                            <input type="password" name="manager_auth_token" class="form-control bg-white" placeholder="Masukkan kode otorisasi pimpinan...">
                            <span class="text-muted small d-block mt-1" style="font-size: 0.72rem;">
                                Dibutuhkan otorisasi resmi untuk mencegah eskalasi hak akses ilegal.
                            </span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-gindaco w-100 py-2 fw-bold shadow-sm mt-4 d-flex align-items-center justify-content-center gap-2">
                    <i class="fas fa-user-check"></i>
                    <span>Daftarkan Akun Sekarang</span>
                </button>
            </form>

            <div class="mt-4 pt-3 border-top text-center">
                <span class="text-muted small">Sudah memiliki akun?</span>
                <a href="login.php" class="text-danger fw-bold text-decoration-none ms-1 small">
                    Masuk ke Halaman Login &rarr;
                </a>
            </div>
        </div>
    </div>

    <script>
    function toggleManagerToken() {
        const select = document.getElementById('levelSelect');
        const tokenRow = document.getElementById('managerTokenRow');
        if (select.value === 'manager') {
            tokenRow.style.display = 'block';
        } else {
            tokenRow.style.display = 'none';
        }
    }
    </script>
</body>
</html>

