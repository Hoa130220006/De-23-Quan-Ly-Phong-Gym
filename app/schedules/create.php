<?php
/**
 * Create / Book Training Schedule with Conflict Checking
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();

$members = $pdo->query("SELECT id, member_code, full_name, phone FROM members WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();
$trainers = $pdo->query("SELECT id, trainer_code, full_name, specialty FROM trainers WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();

$preMemberId = (int)($_GET['member_id'] ?? 0);
$errors = [];

$data = [
    'member_id'     => $preMemberId,
    'trainer_id'    => 0,
    'schedule_date' => date('Y-m-d'),
    'start_time'    => '08:00',
    'end_time'      => '09:30',
    'status'        => 'scheduled',
    'notes'         => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Mã bảo mật CSRF không hợp lệ!';
    } else {
        $data['member_id']     = (int)($_POST['member_id'] ?? 0);
        $data['trainer_id']    = (int)($_POST['trainer_id'] ?? 0);
        $data['schedule_date'] = trim($_POST['schedule_date'] ?? date('Y-m-d'));
        $data['start_time']    = trim($_POST['start_time'] ?? '');
        $data['end_time']      = trim($_POST['end_time'] ?? '');
        $data['status']        = trim($_POST['status'] ?? 'scheduled');
        $data['notes']         = trim($_POST['notes'] ?? '');

        // Validation cơ bản
        if ($data['member_id'] <= 0) {
            $errors[] = 'Vui lòng chọn hội viên tham gia tập!';
        }
        if ($data['trainer_id'] <= 0) {
            $errors[] = 'Vui lòng chọn huấn luyện viên phụ trách!';
        }
        if (empty($data['schedule_date'])) {
            $errors[] = 'Ngày tập không được để trống!';
        }
        if (empty($data['start_time']) || empty($data['end_time'])) {
            $errors[] = 'Giờ bắt đầu và kết thúc không được để trống!';
        } elseif (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
            $errors[] = 'Giờ kết thúc ca tập phải sau giờ bắt đầu!';
        }

        // KIỂM TRA XUNG ĐỘT LỊCH HUẤN LUYỆN VIÊN (Overlap Conflict Check)
        if (empty($errors)) {
            $hasConflict = checkTrainerScheduleConflict(
                $pdo,
                $data['trainer_id'],
                $data['schedule_date'],
                $data['start_time'],
                $data['end_time']
            );

            if ($hasConflict) {
                $errors[] = "⚠️ Xung đột lịch tập: Huấn luyện viên đã có ca huấn luyện khác trong khung giờ " 
                          . $data['start_time'] . " - " . $data['end_time'] 
                          . " vào ngày " . formatDate($data['schedule_date']) 
                          . "! Vui lòng chọn khung giờ hoặc HLV khác.";
            }
        }

        if (empty($errors)) {
            try {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO training_schedules (member_id, trainer_id, schedule_date, start_time, end_time, status, notes)
                    VALUES (:member_id, :trainer_id, :schedule_date, :start_time, :end_time, :status, :notes)
                ");
                $stmtInsert->execute([
                    ':member_id'     => $data['member_id'],
                    ':trainer_id'    => $data['trainer_id'],
                    ':schedule_date' => $data['schedule_date'],
                    ':start_time'    => $data['start_time'],
                    ':end_time'      => $data['end_time'],
                    ':status'        => $data['status'],
                    ':notes'         => $data['notes']
                ]);

                $newSchedId = $pdo->lastInsertId();
                recordAuditLog($pdo, 'SCHEDULE_CREATE', "Đặt lịch tập #{$newSchedId} ngày {$data['schedule_date']} ({$data['start_time']}-{$data['end_time']})");
                writeAppLog('INFO', "Created training schedule ID {$newSchedId}");

                setFlash('success', 'Đặt lịch tập thành công và không bị xung đột giờ!');
                header('Location: /schedules/index.php?date=' . urlencode($data['schedule_date']));
                exit;
            } catch (Exception $e) {
                $errors[] = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
                writeAppLog('ERROR', 'Error creating schedule: ' . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Đặt Lịch Tập Cá Nhân';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">📅 Đặt Lịch Tập Cùng Huấn Luyện Viên</h3>
        <a href="/schedules/index.php" class="btn btn-secondary btn-sm">⬅️ Quay Lại</a>
    </div>
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="flash-alert error" style="margin-bottom: 20px;">
                <div>
                    <strong>Lưu ý kiểm tra các lỗi sau:</strong>
                    <ul style="margin-top: 6px; padding-left: 20px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= sanitize($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="/schedules/create.php">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="member_id">Chọn Hội Viên <span class="required">*</span></label>
                    <select id="member_id" name="member_id" class="form-control" required>
                        <option value="">-- Chọn hội viên tham gia --</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $data['member_id'] === $m['id'] ? 'selected' : '' ?>>
                                <?= sanitize($m['full_name']) ?> (<?= sanitize($m['member_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="trainer_id">Chọn Huấn Luyện Viên <span class="required">*</span></label>
                    <select id="trainer_id" name="trainer_id" class="form-control" required>
                        <option value="">-- Chọn HLV phụ trách --</option>
                        <?php foreach ($trainers as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $data['trainer_id'] === $t['id'] ? 'selected' : '' ?>>
                                <?= sanitize($t['full_name']) ?> - <?= sanitize($t['specialty']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="schedule_date">Ngày Tập <span class="required">*</span></label>
                    <input type="date" id="schedule_date" name="schedule_date" class="form-control" 
                           value="<?= sanitize($data['schedule_date']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Trạng Thái Lịch Tập <span class="required">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="scheduled" <?= $data['status'] === 'scheduled' ? 'selected' : '' ?>>Đã lên lịch (Scheduled)</option>
                        <option value="completed" <?= $data['status'] === 'completed' ? 'selected' : '' ?>>Đã hoàn thành (Completed)</option>
                        <option value="cancelled" <?= $data['status'] === 'cancelled' ? 'selected' : '' ?>>Đã hủy (Cancelled)</option>
                    </select>
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
                    <label class="form-label" for="notes">Ghi Chú Nội Dung Ca Tập</label>
                    <textarea id="notes" name="notes" class="form-control" placeholder="Bài tập ngực, chân, cardio, chế độ nghỉ ngơi..."><?= sanitize($data['notes']) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Đặt Lịch Tập</button>
                <a href="/schedules/index.php" class="btn btn-secondary">Hủy Bỏ</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
