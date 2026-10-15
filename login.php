<?php
require_once 'db.php';
require_once 'includes/auth.php';

// Jika sudah login, langsung arahkan ke index.php
if (is_logged_in()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if (isset($_GET['registered'])) {
    $success = "Registrasi akun Staff berhasil! Silakan masuk dengan NIK dan password Anda.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik = trim($_POST['nik'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($nik) || empty($password)) {
        $error = "NIK dan Password wajib diisi.";
    } else {
        $nik_safe = $conn->real_escape_string($nik);
        $res = $conn->query("SELECT * FROM users WHERE nik = '$nik_safe' LIMIT 1");

        if ($res && $res->num_rows === 1) {
            $user = $res->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Login Berhasil
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nik'] = $user['nik'];
                $_SESSION['user_nama'] = $user['nama'];
                $_SESSION['user_level'] = $user['level'];
                $_SESSION['nama_pencetak'] = $user['nama']; // Untuk kop surat cetak

                // Log audit login
                catat_audit_log($conn, 'LOGIN', 'users', $user['id'], 'User login berhasil ke sistem.');

                header("Location: index.php");
                exit();
            } else {
                $error = "NIK atau Password tidak sesuai.";
            }
        } else {
            $error = "NIK atau Password tidak sesuai.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SPK Pengadaan Bahan Gindaco</title>
    
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
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .login-header {
            background: linear-gradient(135deg, #c01b22 0%, #991b1b 100%);
            color: #ffffff;
            padding: 30px 24px 24px;
            text-align: center;
            position: relative;
        }
        .logo-box {
            background: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            margin-bottom: 14px;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <div class="logo-box">
                <img src="assets/img/gindaco_logo.webp" alt="Logo Gindaco" style="max-height: 38px; width: auto; object-fit: contain;">
            </div>
            <h4 class="fw-bold mb-1 letter-spacing-tight">Sistem Pendukung Keputusan</h4>
            <p class="mb-0 text-white-50 small">Mall Artha Gading &bull; Single Exponential Smoothing</p>
        </div>

        <div class="p-4 p-md-5">
            <div class="mb-4 text-center">
                <h5 class="fw-bold text-dark mb-1">Masuk ke Sistem</h5>
                <span class="text-muted small">Gunakan NIK dan password terdaftar Anda</span>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center small py-2 px-3 rounded-3 mb-3 border-danger border-opacity-25" role="alert">
                    <i class="fas fa-circle-exclamation me-2 fs-6"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success d-flex align-items-center small py-2 px-3 rounded-3 mb-3 border-success border-opacity-25" role="alert">
                    <i class="fas fa-circle-check me-2 fs-6"></i>
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark text-uppercase">Nomor Induk Karyawan (NIK)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0">
                            <i class="fas fa-id-card"></i>
                        </span>
                        <input type="text" name="nik" class="form-control bg-light border-start-0" placeholder="Contoh: 19990101" value="<?php echo htmlspecialchars($_POST['nik'] ?? ''); ?>" required autofocus autocomplete="username">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-dark text-uppercase d-flex justify-content-between">
                        <span>Password</span>
                        <span class="text-muted fw-normal" style="font-size: 0.75rem;"><i class="fas fa-shield-halved me-1 text-success"></i>Terenkripsi</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="passwordInput" name="password" class="form-control bg-light border-start-0 border-end-0" placeholder="Masukkan password..." required autocomplete="current-password">
                        <button class="btn btn-light border border-start-0 text-muted" type="button" onclick="togglePassword()">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-gindaco w-100 py-2 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" id="submitBtn">
                    <i class="fas fa-arrow-right-to-bracket"></i>
                    <span id="btnText">Masuk Sekarang</span>
                </button>
            </form>

            <div class="mt-4 pt-3 border-top text-center">
                <p class="text-muted small mb-2">Belum memiliki akun terdaftar?</p>
                <a href="register.php" class="btn btn-sm btn-soft px-3 py-1 fw-semibold text-danger">
                    <i class="fas fa-user-plus me-1"></i> Daftar Akun Staff Baru
                </a>
            </div>

            <div class="mt-4 p-2 bg-light rounded-3 text-center border">
                <span class="text-muted" style="font-size: 0.72rem;">
                    <i class="fas fa-info-circle me-1 text-primary"></i> <strong>Akun Default:</strong> Manager (<code>19990101</code> / <code>manager123</code>) &bull; Staff (<code>2024001</code> / <code>staff123</code>)
                </span>
            </div>
        </div>
    </div>

    <script>
    function togglePassword() {
        const input = document.getElementById('passwordInput');
        const icon = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        const text = document.getElementById('btnText');
        btn.disabled = true;
        text.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memverifikasi...';
    });
    </script>
</body>
</html>

