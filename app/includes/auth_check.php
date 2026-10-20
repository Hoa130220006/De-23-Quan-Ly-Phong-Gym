<?php
/**
 * Authentication and Authorization Guard
 * Kiểm tra session đăng nhập và phân quyền (admin / staff)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    setFlash('warning', 'Vui lòng đăng nhập để truy cập hệ thống quản trị!');
    $redirectUrl = '/login.php';
    if (!empty($_SERVER['REQUEST_URI'])) {
        $redirectUrl .= '?redirect=' . urlencode($_SERVER['REQUEST_URI']);
    }
    header("Location: {$redirectUrl}");
    exit;
}

$currentUser = $_SESSION['user'];

/**
 * Kiểm tra quyền vai trò của người dùng hiện tại
 */
function hasRole(string $role): bool {
    global $currentUser;
    return isset($currentUser['role']) && $currentUser['role'] === $role;
}

function isAdmin(): bool {
    return hasRole('admin');
}

/**
 * Bắt buộc vai trò tối thiểu (nếu không đủ quyền sẽ báo lỗi 403)
 */
function requireRole($roles): void {
    global $currentUser;
    if (!is_array($roles)) {
        $roles = [$roles];
    }

    if (!in_array($currentUser['role'] ?? '', $roles, true)) {
        writeAppLog('WARNING', 'Unauthorized access attempt', [
            'user' => $currentUser['username'] ?? 'unknown',
            'required_roles' => $roles,
            'current_role' => $currentUser['role'] ?? 'none',
            'uri' => $_SERVER['REQUEST_URI'] ?? ''
        ]);
        
        http_response_code(403);
        $pageTitle = "403 - Quyền truy cập bị từ chối";
        require_once __DIR__ . '/header.php';
        echo '<div class="access-denied-box">';
        echo '  <div class="denied-icon">⛔</div>';
        echo '  <h2>403 - Quyền Truy Cập Bị Từ Chối</h2>';
        echo '  <p>Bạn không có quyền thực hiện thao tác này. Chức năng chỉ dành cho quản trị viên (Admin).</p>';
        echo '  <a href="/index.php" class="btn btn-primary">Quay lại Dashboard</a>';
        echo '</div>';
        require_once __DIR__ . '/footer.php';
        exit;
    }
}
