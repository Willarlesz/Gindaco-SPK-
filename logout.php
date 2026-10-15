<?php
require_once 'db.php';
require_once 'includes/auth.php';

if (is_logged_in()) {
    $uid = $_SESSION['user_id'] ?? 0;
    catat_audit_log($conn, 'LOGOUT', 'users', $uid, 'Pengguna telah logout dari sistem.');
}

// Hancurkan seluruh session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: login.php");
exit();

