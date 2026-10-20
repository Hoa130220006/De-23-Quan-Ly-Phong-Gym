<?php
/**
 * Application Sidebar Component
 */
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
?>
<aside class="app-sidebar">
    <div class="brand">
        <div class="brand-icon">💪</div>
        <div class="brand-text">
            <h1>TITAN FITNESS</h1>
            <span>Gym Management</span>
        </div>
    </div>

    <ul class="nav-menu">
        <li class="nav-section-title">Tổng quan</li>
        <li class="nav-item <?= ($currentUri === '/index.php' || $currentUri === '/' || $currentUri === '') ? 'active' : '' ?>">
            <a href="/index.php">
                <span class="nav-icon">📊</span>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="nav-section-title">Nghiệp vụ Quản lý</li>
        <li class="nav-item <?= (strpos($currentUri, '/members/') !== false) ? 'active' : '' ?>">
            <a href="/members/index.php">
                <span class="nav-icon">🏋️‍♂️</span>
                <span>Hội viên</span>
            </a>
        </li>
        <li class="nav-item <?= (strpos($currentUri, '/trainers/') !== false) ? 'active' : '' ?>">
            <a href="/trainers/index.php">
                <span class="nav-icon">🥊</span>
                <span>Huấn luyện viên</span>
            </a>
        </li>
        <li class="nav-item <?= (strpos($currentUri, '/packages/') !== false) ? 'active' : '' ?>">
            <a href="/packages/index.php">
                <span class="nav-icon">🏷️</span>
                <span>Gói tập Gym</span>
            </a>
        </li>
        <li class="nav-item <?= (strpos($currentUri, '/memberships/') !== false) ? 'active' : '' ?>">
            <a href="/memberships/index.php">
                <span class="nav-icon">📝</span>
                <span>Đăng ký Gói tập</span>
            </a>
        </li>
        <li class="nav-item <?= (strpos($currentUri, '/schedules/') !== false) ? 'active' : '' ?>">
            <a href="/schedules/index.php">
                <span class="nav-icon">📅</span>
                <span>Lịch tập cá nhân</span>
            </a>
        </li>

        <li class="nav-section-title">Hệ thống & Giám sát</li>
        <li class="nav-item <?= (strpos($currentUri, '/logs.php') !== false) ? 'active' : '' ?>">
            <a href="/logs.php">
                <span class="nav-icon">📜</span>
                <span>Nhật ký Hệ thống</span>
            </a>
        </li>
    </ul>

    <div class="user-profile-widget">
        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['user']['username'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-meta">
                <h4><?= sanitize($_SESSION['user']['full_name'] ?? 'Người dùng') ?></h4>
                <span class="badge-role <?= sanitize($_SESSION['user']['role'] ?? 'staff') ?>">
                    <?= strtoupper(sanitize($_SESSION['user']['role'] ?? 'staff')) ?>
                </span>
            </div>
        </div>
        <a href="/auth/logout.php" class="btn-logout-icon" title="Đăng xuất khỏi hệ thống">
            🚪
        </a>
    </div>
</aside>
