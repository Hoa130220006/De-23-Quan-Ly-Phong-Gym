<?php
/**
 * System Logs & Audit Viewer
 * Xem nhật ký hoạt động hệ thống và thông tin giám sát
 */

require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDB();

// Phân trang đơn giản
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Tổng số logs
$totalCount = (int)$pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn();
$totalPages = ceil($totalCount / $limit);

// Lấy danh sách logs
$stmt = $pdo->prepare("
    SELECT * FROM system_logs 
    ORDER BY id DESC 
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$pageTitle = 'Nhật Ký Hoạt Động Hệ Thống';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">📜 Nhật Ký Kiểm Toán (Audit Logs)</h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);">
                Tổng cộng: <?= number_format($totalCount) ?> bản ghi trong CSDL MySQL
            </span>
        </div>
        <div style="font-size: 0.82rem; color: var(--text-muted);">
            Lưu song song tại: <code>/var/log/app/app.log</code> (Loki Ingestion)
        </div>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Thời Gian</th>
                    <th>Tài Khoản</th>
                    <th>Hành Động</th>
                    <th>Chi Tiết</th>
                    <th>Địa Chỉ IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            Chưa có bản ghi nhật ký nào.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= formatDateTime($log['created_at']) ?></td>
                            <td>
                                <strong><?= sanitize($log['username'] ?: 'Hệ thống') ?></strong>
                            </td>
                            <td>
                                <span class="badge-role <?= $log['action'] === 'LOGIN_SUCCESS' ? 'staff' : 'admin' ?>">
                                    <?= sanitize($log['action']) ?>
                                </span>
                            </td>
                            <td><?= sanitize($log['details']) ?></td>
                            <td><code><?= sanitize($log['ip_address']) ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: center; gap: 8px; padding: 20px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
