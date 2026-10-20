<?php
/**
 * View Detailed Member Profile & History
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM members WHERE id = :id");
$stmt->execute([':id' => $id]);
$member = $stmt->fetch();

if (!$member) {
    setFlash('error', 'Không tìm thấy hồ sơ hội viên!');
    header('Location: /members/index.php');
    exit;
}

// Lấy lịch sử gói tập của hội viên
$stmtMemberships = $pdo->prepare("
    SELECT m.*, p.package_name, p.duration_days 
    FROM memberships m
    JOIN packages p ON m.package_id = p.id
    WHERE m.member_id = :member_id
    ORDER BY m.id DESC
");
$stmtMemberships->execute([':member_id' => $id]);
$memberships = $stmtMemberships->fetchAll();

// Lấy lịch sử ca tập cá nhân của hội viên
$stmtSchedules = $pdo->prepare("
    SELECT s.*, t.full_name as trainer_name, t.trainer_code, t.specialty
    FROM training_schedules s
    JOIN trainers t ON s.trainer_id = t.id
    WHERE s.member_id = :member_id
    ORDER BY s.schedule_date DESC, s.start_time DESC
");
$stmtSchedules->execute([':member_id' => $id]);
$schedules = $stmtSchedules->fetchAll();

$pageTitle = 'Hồ Sơ Hội Viên: ' . sanitize($member['full_name']);
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <a href="/members/index.php" class="btn btn-secondary btn-sm">⬅️ Quay Lại Danh Sách</a>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="/memberships/create.php?member_id=<?= $member['id'] ?>" class="btn btn-primary btn-sm">➕ Đăng Ký Gói Tập</a>
        <a href="/schedules/create.php?member_id=<?= $member['id'] ?>" class="btn btn-secondary btn-sm">📅 Đặt Lịch Tập</a>
        <a href="/members/edit.php?id=<?= $member['id'] ?>" class="btn btn-secondary btn-sm">✏️ Chỉnh Sửa</a>
    </div>
</div>

<!-- Profile Info Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">👤 Thông Tin Chi Tiết Hội Viên</h3>
        <span class="status-pill <?= sanitize($member['status']) ?>">
            <?= $member['status'] === 'active' ? 'Đang hoạt động' : ($member['status'] === 'suspended' ? 'Tạm hoãn' : 'Ngưng hoạt động') ?>
        </span>
    </div>
    <div class="card-body">
        <div class="form-grid">
            <div>
                <span class="stat-title">Mã Hội Viên</span>
                <div style="font-size: 1.1rem; font-weight: 700; color: var(--primary); margin-top: 4px;">
                    <?= sanitize($member['member_code']) ?>
                </div>
            </div>
            <div>
                <span class="stat-title">Họ và Tên</span>
                <div style="font-size: 1.1rem; font-weight: 700; color: #fff; margin-top: 4px;">
                    <?= sanitize($member['full_name']) ?>
                </div>
            </div>
            <div>
                <span class="stat-title">Giới Tính / Ngày Sinh</span>
                <div style="font-size: 0.95rem; margin-top: 4px;">
                    <?= $member['gender'] === 'male' ? 'Nam' : ($member['gender'] === 'female' ? 'Nữ' : 'Khác') ?> &bull; <?= formatDate($member['dob']) ?>
                </div>
            </div>
            <div>
                <span class="stat-title">Số Điện Thoại</span>
                <div style="font-size: 0.95rem; margin-top: 4px;">
                    <?= sanitize($member['phone']) ?>
                </div>
            </div>
            <div>
                <span class="stat-title">Địa Chỉ Email</span>
                <div style="font-size: 0.95rem; margin-top: 4px;">
                    <?= sanitize($member['email']) ?>
                </div>
            </div>
            <div>
                <span class="stat-title">Ngày Bắt Đầu Đăng Ký</span>
                <div style="font-size: 0.95rem; margin-top: 4px;">
                    <?= formatDate($member['registration_date']) ?>
                </div>
            </div>
            <div class="full-width">
                <span class="stat-title">Địa Chỉ Thường Trú</span>
                <div style="font-size: 0.95rem; margin-top: 4px; color: var(--text-muted);">
                    <?= sanitize($member['address'] ?: 'Chưa cập nhật địa chỉ') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lịch sử Gói tập -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">📝 Lịch Sử Gói Tập Đã Đăng Ký (<?= count($memberships) ?>)</h3>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Gói Tập</th>
                    <th>Thời Hạn (Ngày)</th>
                    <th>Ngày Bắt Đầu</th>
                    <th>Ngày Hết Hạn</th>
                    <th>Giá Tiền</th>
                    <th>Thanh Toán</th>
                    <th>Trạng Thái</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($memberships)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            Hội viên này chưa đăng ký gói tập nào.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($memberships as $ms): ?>
                        <tr>
                            <td><strong><?= sanitize($ms['package_name']) ?></strong></td>
                            <td><?= $ms['duration_days'] ?> ngày</td>
                            <td><?= formatDate($ms['start_date']) ?></td>
                            <td>
                                <strong style="color: <?= (strtotime($ms['end_date']) < time()) ? 'var(--warning)' : 'var(--primary)' ?>;">
                                    <?= formatDate($ms['end_date']) ?>
                                </strong>
                            </td>
                            <td><?= formatVND($ms['price_paid']) ?></td>
                            <td>
                                <span class="badge-role <?= $ms['payment_status'] === 'paid' ? 'staff' : 'admin' ?>">
                                    <?= strtoupper($ms['payment_status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-pill <?= sanitize($ms['status']) ?>">
                                    <?= $ms['status'] === 'active' ? 'Còn hạn' : ($ms['status'] === 'expired' ? 'Hết hạn' : 'Đã hủy') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Lịch sử Ca tập cá nhân -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">📅 Lịch Tập Với Huấn Luyện Viên (<?= count($schedules) ?>)</h3>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Ngày Tập</th>
                    <th>Khung Giờ</th>
                    <th>Huấn Luyện Viên</th>
                    <th>Chuyên Môn</th>
                    <th>Trạng Thái</th>
                    <th>Ghi Chú</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schedules)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            Chưa có lịch tập cá nhân nào.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($schedules as $sc): ?>
                        <tr>
                            <td><strong><?= formatDate($sc['schedule_date']) ?></strong></td>
                            <td><?= substr($sc['start_time'], 0, 5) ?> - <?= substr($sc['end_time'], 0, 5) ?></td>
                            <td><?= sanitize($sc['trainer_name']) ?> (<?= sanitize($sc['trainer_code']) ?>)</td>
                            <td><span style="color: var(--text-muted);"><?= sanitize($sc['specialty']) ?></span></td>
                            <td>
                                <span class="status-pill <?= sanitize($sc['status']) ?>">
                                    <?= $sc['status'] === 'scheduled' ? 'Đã lên lịch' : ($sc['status'] === 'completed' ? 'Hoàn thành' : 'Đã hủy') ?>
                                </span>
                            </td>
                            <td><?= sanitize($sc['notes'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
