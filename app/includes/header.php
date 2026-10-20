<?php
/**
 * Application Header Component
 */
if (!isset($pageTitle)) {
    $pageTitle = 'Hệ thống Quản lý Phòng Gym & Fitness';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> - Titan Fitness</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220%22%20width=%22100%22%20height=%22100%22><text y=%22.9em%22 font-size=%2290%22>💪</text></svg>">
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="app-main">
    <header class="app-topbar">
        <div class="topbar-left">
            <h2 class="page-title"><?= sanitize($pageTitle) ?></h2>
        </div>
        <div class="topbar-right">
            <div class="system-status-indicator" title="Hệ thống cơ sở dữ liệu và container đang hoạt động bình thường">
                <span class="status-dot"></span>
                <span>Hệ thống trực tuyến (MySQL Live)</span>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                📅 <?= date('d/m/Y') ?>
            </div>
        </div>
    </header>

    <main class="app-content">
        <!-- Hiển thị Flash Alerts nếu có -->
        <?php $flashMsgs = getFlashMessages(); ?>
        <?php if (!empty($flashMsgs)): ?>
            <div class="flash-container">
                <?php foreach ($flashMsgs as $flash): ?>
                    <div class="flash-alert <?= sanitize($flash['type']) ?>">
                        <span>
                            <?php if ($flash['type'] === 'success'): ?>✅
                            <?php elseif ($flash['type'] === 'error'): ?>❌
                            <?php elseif ($flash['type'] === 'warning'): ?>⚠️
                            <?php else: ?>ℹ️
                            <?php endif; ?>
                            <?= sanitize($flash['message']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
