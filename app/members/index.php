<?php
/**
 * Member Management - Index List & Search
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT * FROM members WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (member_code LIKE :search1 OR full_name LIKE :search2 OR phone LIKE :search3 OR email LIKE :search4)";
    $like = "%{$search}%";
    $params[':search1'] = $like;
    $params[':search2'] = $like;
    $params[':search3'] = $like;
    $params[':search4'] = $like;
}

if ($statusFilter !== '' && in_array($statusFilter, ['active', 'inactive', 'suspended'])) {
    $sql .= " AND status = :status";
    $params[':status'] = $statusFilter;
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

$pageTitle = 'Quản Lý Hội Viên';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="search-filter-bar">
            <form method="GET" action="/members/index.php" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div class="search-input-wrapper">
                    <span class="search-icon">🔍</span>
                    <input type="text" name="search" class="form-control" placeholder="Tìm theo Mã HV, Tên, SĐT, Email..." 
                           value="<?= sanitize($search) ?>">
                </div>

                <select name="status" class="form-control" style="width: 160px;" onchange="this.form.submit()">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
                    <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Tạm hoãn</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Ngưng hoạt động</option>
                </select>

                <button type="submit" class="btn btn-secondary">Lọc</button>
                <?php if ($search !== '' || $statusFilter !== ''): ?>
                    <a href="/members/index.php" class="btn btn-secondary">Đặt lại</a>
                <?php endif; ?>
            </form>
        </div>

        <a href="/members/create.php" class="btn btn-primary">➕ Thêm Hội Viên Mới</a>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Mã HV</th>
                    <th>Họ và Tên</th>
                    <th>Giới Tính</th>
                    <th>Ngày Sinh</th>
                    <th>Số Điện Thoại</th>
                    <th>Email</th>
                    <th>Ngày Đăng Ký</th>
                    <th>Trạng Thái</th>
                    <th style="text-align: right;">Hành Động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            Không tìm thấy hội viên nào phù hợp với điều kiện tìm kiếm.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--primary);"><?= sanitize($m['member_code']) ?></strong>
                            </td>
                            <td>
                                <a href="/members/view.php?id=<?= $m['id'] ?>" style="font-weight: 600; color: #fff;">
                                    <?= sanitize($m['full_name']) ?>
                                </a>
                            </td>
                            <td>
                                <?= $m['gender'] === 'male' ? 'Nam' : ($m['gender'] === 'female' ? 'Nữ' : 'Khác') ?>
                            </td>
                            <td><?= formatDate($m['dob']) ?></td>
                            <td><?= sanitize($m['phone']) ?></td>
                            <td><?= sanitize($m['email']) ?></td>
                            <td><?= formatDate($m['registration_date']) ?></td>
                            <td>
                                <span class="status-pill <?= sanitize($m['status']) ?>">
                                    <?php
                                    switch ($m['status']) {
                                        case 'active': echo 'Hoạt động'; break;
                                        case 'suspended': echo 'Tạm hoãn'; break;
                                        default: echo 'Ngưng'; break;
                                    }
                                    ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="/members/view.php?id=<?= $m['id'] ?>" class="btn btn-secondary btn-sm" title="Xem hồ sơ">👁️</a>
                                <a href="/members/edit.php?id=<?= $m['id'] ?>" class="btn btn-secondary btn-sm" title="Sửa thông tin">✏️</a>
                                <?php if (isAdmin()): ?>
                                    <a href="/members/delete.php?id=<?= $m['id'] ?>&csrf_token=<?= csrf_token() ?>" 
                                       class="btn btn-danger btn-sm confirm-delete" 
                                       data-confirm="Xác nhận xóa hội viên <?= sanitize($m['full_name']) ?> (<?= sanitize($m['member_code']) ?>)? Toàn bộ dữ liệu gói tập và lịch tập liên quan cũng sẽ bị xóa!"
                                       title="Xóa hội viên">🗑️</a>
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
