<?php
/**
 * Training Schedules Management - List & Date Filter
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();

$dateFilter = trim($_GET['date'] ?? '');
$trainerFilter = (int)($_GET['trainer_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT ts.*, 
           m.member_code, m.full_name as member_name, m.phone as member_phone,
           t.trainer_code, t.full_name as trainer_name, t.specialty
    FROM training_schedules ts
    JOIN members m ON ts.member_id = m.id
    JOIN trainers t ON ts.trainer_id = t.id
    WHERE 1=1
";
$params = [];

if ($dateFilter !== '') {
    $sql .= " AND ts.schedule_date = :date";
    $params[':date'] = $dateFilter;
}

if ($trainerFilter > 0) {
    $sql .= " AND ts.trainer_id = :trainer_id";
    $params[':trainer_id'] = $trainerFilter;
}

if ($statusFilter !== '' && in_array($statusFilter, ['scheduled', 'completed', 'cancelled'])) {
    $sql .= " AND ts.status = :status";
    $params[':status'] = $statusFilter;
}

$sql .= " ORDER BY ts.schedule_date DESC, ts.start_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$schedules = $stmt->fetchAll();

$trainers = $pdo->query("SELECT id, full_name, trainer_code FROM trainers ORDER BY full_name ASC")->fetchAll();

$pageTitle = 'Quản Lý Lịch Tập Cá Nhân';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="search-filter-bar">
            <form method="GET" action="/schedules/index.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <input type="date" name="date" class="form-control" style="width: 160px;" 
                       value="<?= sanitize($dateFilter) ?>" title="Lọc theo ngày tập">

                <select name="trainer_id" class="form-control" style="width: 200px;">
                    <option value="">-- Tất cả Huấn luyện viên --</option>
                    <?php foreach ($trainers as $tr): ?>
                        <option value="<?= $tr['id'] ?>" <?= $trainerFilter === $tr['id'] ? 'selected' : '' ?>>
                            <?= sanitize($tr['full_name']) ?> (<?= sanitize($tr['trainer_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="form-control" style="width: 160px;">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="scheduled" <?= $statusFilter === 'scheduled' ? 'selected' : '' ?>>Đã lên lịch</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Đã hoàn thành</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Đã hủy ca</option>
                </select>

                <button type="submit" class="btn btn-secondary">Lọc Lịch</button>
                <a href="/schedules/index.php?date=<?= date('Y-m-d') ?>" class="btn btn-secondary">Hôm Nay</a>
                <?php if ($dateFilter !== '' || $trainerFilter > 0 || $statusFilter !== ''): ?>
                    <a href="/schedules/index.php" class="btn btn-secondary">Đặt lại</a>
                <?php endif; ?>
            </form>
        </div>

        <a href="/schedules/create.php" class="btn btn-primary">➕ Đặt Lịch Tập Mới</a>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Ngày Tập</th>
                    <th>Khung Giờ</th>
                    <th>Hội Viên</th>
                    <th>Huấn Luyện Viên</th>
                    <th>Chuyên Môn</th>
                    <th>Trạng Thái</th>
                    <th>Ghi Chú</th>
                    <th style="text-align: right;">Hành Động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schedules)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            Không tìm thấy lịch tập nào phù hợp.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($schedules as $sc): ?>
                        <tr>
                            <td>
                                <strong><?= formatDate($sc['schedule_date']) ?></strong>
                                <?php if ($sc['schedule_date'] === date('Y-m-d')): ?>
                                    <span class="badge-role staff" style="margin-left: 4px;">Hôm nay</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: var(--secondary);">
                                    <?= substr($sc['start_time'], 0, 5) ?> - <?= substr($sc['end_time'], 0, 5) ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?= sanitize($sc['member_name']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= sanitize($sc['member_code']) ?> &bull; <?= sanitize($sc['member_phone']) ?></small>
                            </td>
                            <td>
                                <strong><?= sanitize($sc['trainer_name']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= sanitize($sc['trainer_code']) ?></small>
                            </td>
                            <td><span style="color: var(--text-muted);"><?= sanitize($sc['specialty']) ?></span></td>
                            <td>
                                <span class="status-pill <?= sanitize($sc['status']) ?>">
                                    <?php
                                    switch ($sc['status']) {
                                        case 'scheduled': echo 'Đã lên lịch'; break;
                                        case 'completed': echo 'Hoàn thành'; break;
                                        case 'cancelled': echo 'Đã hủy'; break;
                                        default: echo sanitize($sc['status']);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td><?= sanitize($sc['notes'] ?: '-') ?></td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="/schedules/edit.php?id=<?= $sc['id'] ?>" class="btn btn-secondary btn-sm" title="Sửa ca tập">✏️</a>
                                <?php if (isAdmin() || $sc['status'] === 'scheduled'): ?>
                                    <a href="/schedules/delete.php?id=<?= $sc['id'] ?>&csrf_token=<?= csrf_token() ?>" 
                                       class="btn btn-danger btn-sm confirm-delete" 
                                       data-confirm="Xác nhận hủy lịch tập này?"
                                       title="Hủy/Xóa ca tập">❌</a>
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
