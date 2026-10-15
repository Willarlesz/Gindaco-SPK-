<?php
/**
 * Auth Middleware & Helper Functions
 * Mengelola otentikasi session dan Role-Based Access Control (RBAC)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user sudah login
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Cek apakah user adalah Manager
 */
function is_manager() {
    return is_logged_in() && isset($_SESSION['user_level']) && $_SESSION['user_level'] === 'manager';
}

/**
 * Cek apakah user adalah Staff
 */
function is_staff() {
    return is_logged_in() && isset($_SESSION['user_level']) && $_SESSION['user_level'] === 'staff';
}

/**
 * Ambil data user yang sedang login
 */
function get_current_user_data() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'nik' => $_SESSION['user_nik'] ?? '',
        'nama' => $_SESSION['user_nama'] ?? 'Pengguna',
        'level' => $_SESSION['user_level'] ?? 'staff'
    ];
}

/**
 * Wajibkan login untuk mengakses halaman
 */
function require_login($redirect_url = 'login.php') {
    if (!is_logged_in()) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Wajibkan level Manager untuk mengakses halaman/aksi tertentu
 */
function require_manager($redirect_url = 'index.php') {
    require_login();
    if (!is_manager()) {
        // Jika request AJAX/API
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Akses ditolak: Operasi ini hanya diizinkan untuk hak akses Manager.'
            ]);
            exit();
        }

        // Tampilkan halaman akses ditolak yang rapi
        http_response_code(403);
        include_once __DIR__ . '/header.php';
        echo '<div class="container py-5 text-center" style="max-width: 600px;">
            <div class="modern-card p-5">
                <div class="brand-icon mx-auto mb-3" style="width: 64px; height: 64px; font-size: 1.8rem; background: #dc2626;">
                    <i class="fas fa-user-lock"></i>
                </div>
                <h3 class="fw-bold text-danger mb-2">Akses Ditolak (403)</h3>
                <p class="text-muted mb-4">
                    Halaman atau fitur ini memerlukan hak akses tingkat <strong>Manager</strong>. Akun Anda saat ini terdaftar sebagai <strong>Staff</strong>.
                </p>
                <a href="' . $redirect_url . '" class="btn btn-gindaco px-4 py-2">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>';
        include_once __DIR__ . '/footer.php';
        exit();
    }
}

/**
 * Catat aktivitas perubahan penting ke tabel audit_log
 */
function catat_audit_log($conn, $aksi, $tabel_terkait, $id_terkait, $detail_perubahan) {
    if (!is_logged_in()) return false;

    $user_id = (int)$_SESSION['user_id'];
    $nik = $conn->real_escape_string($_SESSION['user_nik'] ?? '');
    $nama = $conn->real_escape_string($_SESSION['user_nama'] ?? '');
    $aksi = $conn->real_escape_string($aksi);
    $tabel = $conn->real_escape_string($tabel_terkait);
    $id_terkait = (int)$id_terkait;
    $detail = $conn->real_escape_string(is_array($detail_perubahan) ? json_encode($detail_perubahan, JSON_UNESCAPED_UNICODE) : $detail_perubahan);

    $sql = "INSERT INTO audit_log (user_id, nik, nama_user, aksi, tabel_terkait, id_terkait, detail_perubahan)
            VALUES ($user_id, '$nik', '$nama', '$aksi', '$tabel', $id_terkait, '$detail')";
    return $conn->query($sql);
}

