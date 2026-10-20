<?php
/**
 * Trainer Management - List & Search
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT t.*, COUNT(ts.id) as total_sessions
    FROM trainers t
    LEFT JOIN training_schedules ts ON t.id = ts.trainer_id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (t.trainer_code LIKE :s1 OR t.full_name LIKE :s2 OR t.phone LIKE :s3 OR t.specialty LIKE :s4)";
    $like = "%{$search}%";
    $params[':s1'] = $like;
    $params[':s2'] = $like;
    $params[':s3'] = $like;
    $params[':s4'] = $like;
}

if ($statusFilter !== '' && in_array($statusFilter, ['active', 'inactive'])) {
    $sql .= " AND t.status = :status";
    $params[':status'] = $statusFilter;
}

$sql .= " GROUP BY t.id ORDER BY t.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$trainers = $stmt->fetchAll();

$pageTitle = 'Quản Lý Huấn Luyện Viên';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="search-filter-bar">
            <form method="GET" action="/trainers/index.php" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div class="search-input-wrapper">
                    <span class="search-icon">🔍</span>
                    <input type="text" name="search" class="form-control" placeholder="Tìm tên HLV, mã, chuyên môn..." 
                           value="<?= sanitize($search) ?>">
                </div>

                <select name="status" class="form-control" style="width: 170px;" onchange="this.form.submit()">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Đang làm việc</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Tạm nghỉ việc</option>
                </select>

                <button type="submit" class="btn btn-secondary">Lọc</button>
                <?php if ($search !== '' || $statusFilter !== ''): ?>
                    <a href="/trainers/index.php" class="btn btn-secondary">Đặt lại</a>
                <?php endif; ?>
            </form>
        </div>

        <a href="/trainers/create.php" class="btn btn-primary">➕ Thêm Huấn Luyện Viên</a>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Mã HLV</th>
                    <th>Họ và Tên</th>
                    <th>Số Điện Thoại</th>
                    <th>Email</th>
                    <th>Chuyên Môn Huấn Luyện</th>
                    <th>Tổng Ca Dạy</th>
                    <th>Trạng Thái</th>
                    <th style="text-align: right;">Hành Động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($trainers)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            Không tìm thấy huấn luyện viên nào.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($trainers as $t): ?>
                        <tr>
                            <td><strong style="color: var(--secondary);"><?= sanitize($t['trainer_code']) ?></strong></td>
                            <td><strong><?= sanitize($t['full_name']) ?></strong></td>
                            <td><?= sanitize($t['phone']) ?></td>
                            <td><?= sanitize($t['email']) ?></td>
                            <td>
                                <span style="color: var(--text-main); font-weight: 500;"><?= sanitize($t['specialty']) ?></span>
                            </td>
                            <td>
                                <span class="badge-role staff"><?= $t['total_sessions'] ?> buổi</span>
                            </td>
                            <td>
                                <span class="status-pill <?= sanitize($t['status']) ?>">
                                    <?= $t['status'] === 'active' ? 'Đang làm việc' : 'Nghỉ việc' ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="/trainers/edit.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm" title="Sửa thông tin">✏️ Sửa</a>
                                <?php if (isAdmin()): ?>
                                    <a href="/trainers/delete.php?id=<?= $t['id'] ?>&csrf_token=<?= csrf_token() ?>" 
                                       class="btn btn-danger btn-sm confirm-delete" 
                                       data-confirm="Bạn có chắc muốn xóa huấn luyện viên <?= sanitize($t['full_name']) ?>?"
                                       title="Xóa HLV">🗑️</a>
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
