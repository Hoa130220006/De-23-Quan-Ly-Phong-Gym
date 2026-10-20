<?php
/**
 * Packages Management - List Packages
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();

$stmt = $pdo->query("
    SELECT p.*, COUNT(m.id) as subscriber_count, COALESCE(SUM(m.price_paid), 0) as total_revenue
    FROM packages p
    LEFT JOIN memberships m ON p.id = m.package_id
    GROUP BY p.id
    ORDER BY p.id ASC
");
$packages = $stmt->fetchAll();

$pageTitle = 'Quản Lý Gói Tập Gym';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Danh sách tất cả các gói dịch vụ tập luyện và thời hạn tương ứng.
        </p>
    </div>
    <a href="/packages/create.php" class="btn btn-primary">➕ Thêm Gói Tập Mới</a>
</div>

<!-- Package Cards Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <?php foreach ($packages as $pkg): ?>
        <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
            <div class="card-header">
                <div>
                    <span style="font-size: 0.72rem; color: var(--secondary); font-weight: 700;"><?= sanitize($pkg['package_code']) ?></span>
                    <h4 style="font-size: 1.15rem; font-weight: 700; color: #fff; margin-top: 2px;">
                        <?= sanitize($pkg['package_name']) ?>
                    </h4>
                </div>
                <span class="status-pill <?= sanitize($pkg['status']) ?>">
                    <?= $pkg['status'] === 'active' ? 'Đang áp dụng' : 'Tạm ngưng' ?>
                </span>
            </div>
            <div class="card-body" style="flex: 1;">
                <div style="margin-bottom: 16px;">
                    <span style="font-size: 1.8rem; font-weight: 800; color: #34d399;">
                        <?= formatVND($pkg['price']) ?>
                    </span>
                    <span style="color: var(--text-muted); font-size: 0.85rem;"> / <?= $pkg['duration_days'] ?> ngày</span>
                </div>
                <p style="color: var(--text-muted); font-size: 0.88rem; min-height: 50px;">
                    <?= nl2br(sanitize($pkg['description'] ?: 'Không có mô tả chi tiết cho gói tập này.')) ?>
                </p>
                <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--text-muted);">
                    <span>Hội viên đăng ký: <strong style="color: #fff;"><?= $pkg['subscriber_count'] ?></strong></span>
                    <span>Doanh thu: <strong style="color: #34d399;"><?= formatVND($pkg['total_revenue']) ?></strong></span>
                </div>
            </div>
            <div style="padding: 14px 24px; background: rgba(0,0,0,0.2); border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 8px;">
                <a href="/memberships/create.php?package_id=<?= $pkg['id'] ?>" class="btn btn-primary btn-sm">📝 Đăng Ký</a>
                <a href="/packages/edit.php?id=<?= $pkg['id'] ?>" class="btn btn-secondary btn-sm">✏️ Sửa</a>
                <?php if (isAdmin()): ?>
                    <a href="/packages/delete.php?id=<?= $pkg['id'] ?>&csrf_token=<?= csrf_token() ?>" 
                       class="btn btn-danger btn-sm confirm-delete"
                       data-confirm="Bạn có chắc chắn muốn xóa gói tập <?= sanitize($pkg['package_name']) ?>?">🗑️</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
