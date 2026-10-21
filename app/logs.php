<?php
/**
 * System Logs & Audit Viewer
 * Xem nhật ký hoạt động hệ thống và thông tin giám sát
 */

require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDB();

// Lọc và tìm kiếm
$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(details LIKE :search OR username LIKE :search OR ip_address LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($actionFilter !== '') {
    if ($actionFilter === 'AUTH') {
        $where[] = "action IN ('LOGIN_SUCCESS', 'LOGIN_FAILED', 'LOGOUT')";
    } elseif ($actionFilter === 'MEMBER') {
        $where[] = "action LIKE 'MEMBER_%'";
    } elseif ($actionFilter === 'MEMBERSHIP') {
        $where[] = "action LIKE 'MEMBERSHIP_%'";
    } elseif ($actionFilter === 'TRAINER') {
        $where[] = "action LIKE 'TRAINER_%'";
    } elseif ($actionFilter === 'PACKAGE') {
        $where[] = "action LIKE 'PACKAGE_%'";
    } elseif ($actionFilter === 'SCHEDULE') {
        $where[] = "action LIKE 'SCHEDULE_%'";
    } else {
        $where[] = "action = :action_filter";
        $params[':action_filter'] = $actionFilter;
    }
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Phân trang
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Tổng số logs theo điều kiện lọc
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM system_logs {$whereClause}");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalCount / $limit);

// Lấy danh sách logs
$sql = "
    SELECT * FROM system_logs 
    {$whereClause}
    ORDER BY id DESC 
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$pageTitle = 'Nhật Ký Hoạt Động Hệ Thống';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 15px;">
        <div>
            <h3 class="card-title">📜 Nhật Ký Hoạt Động (Audit Logs)</h3>
            <span style="font-size: 0.82rem; color: var(--text-muted);">
                Tổng cộng: <strong style="color: var(--primary);"><?= number_format($totalCount) ?></strong> bản ghi
            </span>
        </div>

        <div class="search-filter-bar">
            <form method="GET" action="/logs.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <div class="search-input-wrapper">
                    <span class="search-icon">🔍</span>
                    <input type="text" name="search" class="form-control" placeholder="Tìm theo nội dung, tài khoản, IP..." 
                           value="<?= sanitize($search) ?>">
                </div>

                <select name="action" class="form-control" style="width: 190px;" onchange="this.form.submit()">
                    <option value="">-- Tất cả hành động --</option>
                    <option value="AUTH" <?= $actionFilter === 'AUTH' ? 'selected' : '' ?>>🔐 Đăng nhập / Đăng xuất</option>
                    <option value="MEMBER" <?= $actionFilter === 'MEMBER' ? 'selected' : '' ?>>👤 Quản lý Hội viên</option>
                    <option value="MEMBERSHIP" <?= $actionFilter === 'MEMBERSHIP' ? 'selected' : '' ?>>💳 Đăng ký gói tập</option>
                    <option value="TRAINER" <?= $actionFilter === 'TRAINER' ? 'selected' : '' ?>>🏋️ Huấn luyện viên</option>
                    <option value="PACKAGE" <?= $actionFilter === 'PACKAGE' ? 'selected' : '' ?>>📦 Gói dịch vụ</option>
                    <option value="SCHEDULE" <?= $actionFilter === 'SCHEDULE' ? 'selected' : '' ?>>📅 Lịch tập</option>
                </select>

                <button type="submit" class="btn btn-secondary">Lọc</button>
                <?php if ($search !== '' || $actionFilter !== ''): ?>
                    <a href="/logs.php" class="btn btn-secondary">Đặt lại</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width: 170px;">Thời Gian</th>
                    <th style="width: 140px;">Tài Khoản</th>
                    <th style="width: 180px;">Hành Động</th>
                    <th>Chi Tiết Hoạt Động</th>
                    <th style="width: 140px;">Địa Chỉ IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 35px;">
                            Không tìm thấy bản ghi nhật ký nào phù hợp.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php $actionInfo = formatAuditAction($log['action']); ?>
                        <tr>
                            <td style="color: var(--text-muted); font-size: 0.85rem;">
                                <?= formatDateTime($log['created_at']) ?>
                            </td>
                            <td>
                                <strong style="color: #fff;"><?= sanitize($log['username'] ?: 'Hệ thống') ?></strong>
                            </td>
                            <td>
                                <span class="badge-action <?= $actionInfo['type'] ?>">
                                    <?= sanitize($actionInfo['label']) ?>
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
            <?php 
                $queryParams = $_GET;
                for ($i = 1; $i <= $totalPages; $i++): 
                    $queryParams['page'] = $i;
                    $pageUrl = '?' . http_build_query($queryParams);
            ?>
                <a href="<?= $pageUrl ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
