<?php
/**
 * Edit / Reschedule Training Session
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Lịch tập không hợp lệ!');
    header('Location: /schedules/index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT ts.*, m.full_name as member_name, m.member_code, t.full_name as trainer_name
    FROM training_schedules ts
    JOIN members m ON ts.member_id = m.id
    JOIN trainers t ON ts.trainer_id = t.id
    WHERE ts.id = :id
");
$stmt->execute([':id' => $id]);
$sched = $stmt->fetch();

if (!$sched) {
    setFlash('error', 'Không tìm thấy ca tập!');
    header('Location: /schedules/index.php');
    exit;
}

$trainers = $pdo->query("SELECT id, trainer_code, full_name, specialty FROM trainers WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();

$errors = [];
$data = $sched;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Mã bảo mật CSRF không hợp lệ!';
    } else {
        $data['trainer_id']    = (int)($_POST['trainer_id'] ?? 0);
        $data['schedule_date'] = trim($_POST['schedule_date'] ?? '');
        $data['start_time']    = trim($_POST['start_time'] ?? '');
        $data['end_time']      = trim($_POST['end_time'] ?? '');
        $data['status']        = trim($_POST['status'] ?? 'scheduled');
        $data['notes']         = trim($_POST['notes'] ?? '');

        if ($data['trainer_id'] <= 0) {
            $errors[] = 'Vui lòng chọn huấn luyện viên!';
        }
        if (empty($data['schedule_date'])) {
            $errors[] = 'Ngày tập không được để trống!';
        }
        if (empty($data['start_time']) || empty($data['end_time'])) {
            $errors[] = 'Giờ bắt đầu và kết thúc không được để trống!';
        } elseif (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
            $errors[] = 'Giờ kết thúc phải sau giờ bắt đầu!';
        }

        // KIỂM TRA XUNG ĐỘT (loại trừ chính ca tập này)
        if (empty($errors) && $data['status'] !== 'cancelled') {
            $hasConflict = checkTrainerScheduleConflict(
                $pdo,
                $data['trainer_id'],
                $data['schedule_date'],
                $data['start_time'],
                $data['end_time'],
                $id // Exclude current schedule ID
            );

            if ($hasConflict) {
                $errors[] = "⚠️ Xung đột lịch: Huấn luyện viên đã có ca tập khác trong khung giờ " 
                          . $data['start_time'] . " - " . $data['end_time'] 
                          . " vào ngày " . formatDate($data['schedule_date']) . "!";
            }
        }

        if (empty($errors)) {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE training_schedules SET
                        trainer_id = :trainer_id,
                        schedule_date = :schedule_date,
                        start_time = :start_time,
                        end_time = :end_time,
                        status = :status,
                        notes = :notes
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    ':trainer_id'    => $data['trainer_id'],
                    ':schedule_date' => $data['schedule_date'],
                    ':start_time'    => $data['start_time'],
                    ':end_time'      => $data['end_time'],
                    ':status'        => $data['status'],
                    ':notes'         => $data['notes'],
                    ':id'            => $id
                ]);

                recordAuditLog($pdo, 'SCHEDULE_UPDATE', "Cập nhật ca tập #{$id} ngày {$data['schedule_date']} trạng thái: {$data['status']}");
                writeAppLog('INFO', "Updated training schedule ID {$id}");

                setFlash('success', 'Cập nhật lịch tập thành công!');
                header('Location: /schedules/index.php?date=' . urlencode($data['schedule_date']));
                exit;
            } catch (Exception $e) {
                $errors[] = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
                writeAppLog('ERROR', "Error updating schedule {$id}: " . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Chỉnh Sửa Ca Tập: #' . $sched['id'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">✏️ Chỉnh Sửa / Đổi Giờ Ca Tập</h3>
        <a href="/schedules/index.php" class="btn btn-secondary btn-sm">⬅️ Quay Lại</a>
    </div>
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="flash-alert error" style="margin-bottom: 20px;">
                <div>
                    <strong>Vui lòng kiểm tra lại:</strong>
                    <ul style="margin-top: 6px; padding-left: 20px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= sanitize($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="/schedules/edit.php?id=<?= $id ?>">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Hội Viên Tập Luyện</label>
                    <input type="text" class="form-control" value="<?= sanitize($sched['member_name']) ?> (<?= sanitize($sched['member_code']) ?>)" readonly disabled>
                </div>

                <div class="form-group">
                    <label class="form-label" for="trainer_id">Huấn Luyện Viên Phụ Trách <span class="required">*</span></label>
                    <select id="trainer_id" name="trainer_id" class="form-control" required>
                        <?php foreach ($trainers as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $data['trainer_id'] == $t['id'] ? 'selected' : '' ?>>
                                <?= sanitize($t['full_name']) ?> - <?= sanitize($t['specialty']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Trạng Thái Ca Tập <span class="required">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="scheduled" <?= $data['status'] === 'scheduled' ? 'selected' : '' ?>>Đã lên lịch (Scheduled)</option>
                        <option value="completed" <?= $data['status'] === 'completed' ? 'selected' : '' ?>>Đã hoàn thành (Completed)</option>
                        <option value="cancelled" <?= $data['status'] === 'cancelled' ? 'selected' : '' ?>>Đã hủy ca (Cancelled)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="schedule_date">Ngày Tập <span class="required">*</span></label>
                    <input type="date" id="schedule_date" name="schedule_date" class="form-control" 
                           value="<?= sanitize($data['schedule_date']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="start_time">Giờ Bắt Đầu <span class="required">*</span></label>
                    <input type="time" id="start_time" name="start_time" class="form-control" 
                           value="<?= sanitize(substr($data['start_time'], 0, 5)) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="end_time">Giờ Kết Thúc <span class="required">*</span></label>
                    <input type="time" id="end_time" name="end_time" class="form-control" 
                           value="<?= sanitize(substr($data['end_time'], 0, 5)) ?>" required>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="notes">Ghi Chú</label>
                    <textarea id="notes" name="notes" class="form-control"><?= sanitize($data['notes']) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Cập Nhật Ca Tập</button>
                <a href="/schedules/index.php" class="btn btn-secondary">Hủy Bỏ</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
