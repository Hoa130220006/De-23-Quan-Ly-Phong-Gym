<?php
/**
 * User Logout Handler
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$username = $_SESSION['user']['username'] ?? 'anonymous';

try {
    $pdo = getDB();
    recordAuditLog($pdo, 'LOGOUT', "Người dùng {$username} đã đăng xuất");
} catch (Exception $e) {
    // Ignore error on logout
}

writeAppLog('INFO', "User {$username} logged out");

// Xóa sạch session
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Bắt đầu session mới chỉ để lưu flash message
session_start();
setFlash('info', 'Bạn đã đăng xuất khỏi hệ thống thành công!');
header('Location: /login.php');
exit;
