<?php
/**
 * Login Page & Authentication Handler
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Nếu đã đăng nhập, chuyển hướng thẳng vào dashboard
if (isset($_SESSION['user']['id'])) {
    header('Location: /index.php');
    exit;
}

$error = '';
$usernameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Mã bảo mật phiên (CSRF) không hợp lệ. Vui lòng thử lại!';
    } else {
        $usernameVal = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($usernameVal) || empty($password)) {
            $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!';
        } else {
            try {
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND status = 'active' LIMIT 1");
                $stmt->execute([':username' => $usernameVal]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Đăng nhập thành công: tái tạo Session ID để chống Session Fixation
                    session_regenerate_id(true);

                    $_SESSION['user'] = [
                        'id'        => $user['id'],
                        'username'  => $user['username'],
                        'full_name' => $user['full_name'],
                        'email'     => $user['email'],
                        'role'      => $user['role']
                    ];

                    recordAuditLog($pdo, 'LOGIN_SUCCESS', "Người dùng {$user['username']} đăng nhập thành công ({$user['role']})");
                    writeAppLog('INFO', "User {$user['username']} logged in successfully with role {$user['role']}");

                    setFlash('success', "Chào mừng " . $user['full_name'] . " đã đăng nhập hệ thống!");

                    $redirect = $_GET['redirect'] ?? '/index.php';
                    header("Location: {$redirect}");
                    exit;
                } else {
                    $error = 'Tên đăng nhập hoặc mật khẩu không chính xác!';
                    writeAppLog('WARNING', "Failed login attempt for username '{$usernameVal}'");
                }
            } catch (Exception $e) {
                $error = 'Lỗi hệ thống trong quá trình xác thực. Vui lòng thử lại!';
                writeAppLog('CRITICAL', 'Login exception: ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Titan Fitness Gym Management</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220%22%20width=%22100%22%20height=%22100%22><text y=%22.9em%22 font-size=%2290%22>💪</text></svg>">
</head>
<body class="login-body">

<div class="login-card">
    <div class="login-brand">
        <div class="login-brand-icon">💪</div>
        <h2>TITAN FITNESS</h2>
        <p>Hệ thống Quản lý Phòng Gym & Fitness Chuyên Nghiệp</p>
    </div>

    <?php $flashMsgs = getFlashMessages(); ?>
    <?php if (!empty($flashMsgs)): ?>
        <?php foreach ($flashMsgs as $flash): ?>
            <div class="flash-alert <?= sanitize($flash['type']) ?>" style="margin-bottom: 16px;">
                <span><?= sanitize($flash['message']) ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="flash-alert error" style="margin-bottom: 20px;">
            <span>❌ <?= sanitize($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="/login.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
        <?= csrf_field() ?>

        <div class="form-group">
            <label class="form-label" for="username">Tên đăng nhập <span class="required">*</span></label>
            <input type="text" id="username" name="username" class="form-control" 
                   value="<?= sanitize($usernameVal) ?>" placeholder="admin hoặc staff" required autofocus>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Mật khẩu <span class="required">*</span></label>
            <input type="password" id="password" name="password" class="form-control" 
                   placeholder="Nhập mật khẩu..." required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px; padding: 12px;">
            🔐 Đăng Nhập Hệ Thống
        </button>
    </form>

    <div class="demo-account-box">
        <div class="demo-account-title">⚡ Tài khoản Demo có sẵn (Click để điền nhanh)</div>
        <div class="demo-buttons">
            <button type="button" class="btn-demo" onclick="fillCredentials('admin', 'Admin@123')">
                🛡️ Admin (Admin@123)
            </button>
            <button type="button" class="btn-demo" onclick="fillCredentials('staff', 'Staff@123')">
                👤 Staff (Staff@123)
            </button>
        </div>
    </div>
</div>

<script src="/js/main.js"></script>
</body>
</html>
