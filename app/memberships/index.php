<?php
/**
 * Memberships Management - Subscriptions List & Filter
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();
autoExpireMemberships($pdo);

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT m.*, 
           mem.member_code, mem.full_name as member_name, mem.phone as member_phone,
           p.package_code, p.package_name, p.duration_days
    FROM memberships m
    JOIN members mem ON m.member_id = mem.id
    JOIN packages p ON m.package_id = p.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (mem.member_code LIKE :s1 OR mem.full_name LIKE :s2 OR mem.phone LIKE :s3 OR p.package_name LIKE :s4)";
    $like = "%{$search}%";
    $params[':s1'] = $like;
    $params[':s2'] = $like;
    $params[':s3'] = $like;
    $params[':s4'] = $like;
}

if ($statusFilter !== '' && in_array($statusFilter, ['active', 'expired', 'cancelled'])) {
    $sql .= " AND m.status = :status";
    $params[':status'] = $statusFilter;
}

$sql .= " ORDER BY m.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll();

$pageTitle = 'Quản Lý Đăng Ký Gói Tập';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="search-filter-bar">
            <form method="GET" action="/memberships/index.php" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div class="search-input-wrapper">
                    <span class="search-icon">🔍</span>
                    <input type="text" name="search" class="form-control" placeholder="Tìm theo hội viên, SĐT, gói..." 
                           value="<?= sanitize($search) ?>">
                </div>

                <select name="status" class="form-control" style="width: 170px;" onchange="this.form.submit()">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Còn hạn</option>
                    <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Hết hạn</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
                </select>

                <button type="submit" class="btn btn-secondary">Lọc</button>
                <?php if ($search !== '' || $statusFilter !== ''): ?>
                    <a href="/memberships/index.php" class="btn btn-secondary">Đặt lại</a>
                <?php endif; ?>
            </form>
        </div>

        <a href="/memberships/create.php" class="btn btn-primary">➕ Đăng Ký Gói Mới</a>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Mã ĐK</th>
                    <th>Hội Viên</th>
                    <th>Gói Tập</th>
                    <th>Ngày Bắt Đầu</th>
                    <th>Ngày Hết Hạn</th>
                    <th>Số Tiền</th>
                    <th>Thanh Toán</th>
                    <th>Trạng Thái</th>
                    <th style="text-align: right;">Hành Động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subscriptions)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            Không tìm thấy dữ liệu đăng ký gói tập nào.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subscriptions as $sub): ?>
                        <tr>
                            <td><strong style="color: var(--secondary);">#<?= $sub['id'] ?></strong></td>
                            <td>
                                <strong><?= sanitize($sub['member_name']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= sanitize($sub['member_code']) ?> &bull; <?= sanitize($sub['member_phone']) ?></small>
                            </td>
                            <td>
                                <strong><?= sanitize($sub['package_name']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= $sub['duration_days'] ?> ngày</small>
                            </td>
                            <td><?= formatDate($sub['start_date']) ?></td>
                            <td>
                                <strong style="color: <?= ($sub['status'] === 'expired' || strtotime($sub['end_date']) < time()) ? 'var(--warning)' : 'var(--primary)' ?>;">
                                    <?= formatDate($sub['end_date']) ?>
                                </strong>
                            </td>
                            <td><strong style="color: #34d399;"><?= formatVND($sub['price_paid']) ?></strong></td>
                            <td>
                                <span class="badge-role <?= $sub['payment_status'] === 'paid' ? 'staff' : 'admin' ?>">
                                    <?= strtoupper($sub['payment_status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-pill <?= sanitize($sub['status']) ?>">
                                    <?php
                                    switch ($sub['status']) {
                                        case 'active': echo 'Còn hạn'; break;
                                        case 'expired': echo 'Hết hạn'; break;
                                        case 'cancelled': echo 'Đã hủy'; break;
                                        default: echo sanitize($sub['status']);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="/memberships/edit.php?id=<?= $sub['id'] ?>" class="btn btn-secondary btn-sm" title="Sửa thông tin đăng ký">✏️</a>
                                <?php if (isAdmin()): ?>
                                    <a href="/memberships/delete.php?id=<?= $sub['id'] ?>&csrf_token=<?= csrf_token() ?>" 
                                       class="btn btn-danger btn-sm confirm-delete" 
                                       data-confirm="Bạn có chắc chắn muốn hủy / xóa gói đăng ký #<?= $sub['id'] ?> này?"
                                       title="Xóa bản ghi">🗑️</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
