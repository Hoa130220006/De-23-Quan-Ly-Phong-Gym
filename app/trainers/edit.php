<?php
/**
 * Edit Trainer Information
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Huấn luyện viên không hợp lệ!');
    header('Location: /trainers/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM trainers WHERE id = :id");
$stmt->execute([':id' => $id]);
$trainer = $stmt->fetch();

if (!$trainer) {
    setFlash('error', 'Không tìm thấy huấn luyện viên trong hệ thống!');
    header('Location: /trainers/index.php');
    exit;
}

$errors = [];
$data = $trainer;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Mã bảo mật CSRF không hợp lệ!';
    } else {
        $data['trainer_code'] = strtoupper(trim($_POST['trainer_code'] ?? ''));
        $data['full_name']    = trim($_POST['full_name'] ?? '');
        $data['phone']        = trim($_POST['phone'] ?? '');
        $data['email']        = trim($_POST['email'] ?? '');
        $data['specialty']    = trim($_POST['specialty'] ?? '');
        $data['bio']          = trim($_POST['bio'] ?? '');
        $data['status']       = trim($_POST['status'] ?? 'active');

        // Validation
        if (empty($data['trainer_code'])) {
            $errors[] = 'Mã huấn luyện viên không được để trống!';
        }
        if (empty($data['full_name'])) {
            $errors[] = 'Họ và tên huấn luyện viên không được để trống!';
        }
        if (empty($data['phone']) || !preg_match('/^[0-9]{9,11}$/', $data['phone'])) {
            $errors[] = 'Số điện thoại không hợp lệ (9 - 11 chữ số)!';
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Định dạng email không hợp lệ!';
        }
        if (empty($data['specialty'])) {
            $errors[] = 'Chuyên môn huấn luyện không được để trống!';
        }

        // Kiểm tra trùng mã (trừ chính nó)
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM trainers WHERE trainer_code = :code AND id != :id");
            $stmt->execute([':code' => $data['trainer_code'], ':id' => $id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Mã huấn luyện viên '{$data['trainer_code']}' đã được sử dụng!";
            }
        }

        // Kiểm tra trùng email (trừ chính nó)
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM trainers WHERE email = :email AND id != :id");
            $stmt->execute([':email' => $data['email'], ':id' => $id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Email '{$data['email']}' đã được sử dụng bởi HLV khác!";
            }
        }

        if (empty($errors)) {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE trainers SET 
                        trainer_code = :trainer_code,
                        full_name = :full_name,
                        phone = :phone,
                        email = :email,
                        specialty = :specialty,
                        bio = :bio,
                        status = :status
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    ':trainer_code' => $data['trainer_code'],
                    ':full_name'    => $data['full_name'],
                    ':phone'        => $data['phone'],
                    ':email'        => $data['email'],
                    ':specialty'    => $data['specialty'],
                    ':bio'          => $data['bio'],
                    ':status'       => $data['status'],
                    ':id'           => $id
                ]);

                recordAuditLog($pdo, 'TRAINER_UPDATE', "Cập nhật HLV #{$id}: {$data['full_name']} ({$data['trainer_code']})");
                writeAppLog('INFO', "Updated trainer ID {$id}: {$data['trainer_code']}");

                setFlash('success', "Cập nhật thông tin huấn luyện viên '{$data['full_name']}' thành công!");
                header('Location: /trainers/index.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
                writeAppLog('ERROR', "Error updating trainer {$id}: " . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Chỉnh Sửa Huấn Luyện Viên: ' . sanitize($trainer['full_name']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">✏️ Chỉnh Sửa Huấn Luyện Viên</h3>
        <a href="/trainers/index.php" class="btn btn-secondary btn-sm">⬅️ Quay Lại Danh Sách</a>
    </div>
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="flash-alert error" style="margin-bottom: 20px;">
                <div>
                    <strong>Vui lòng khắc phục các lỗi sau:</strong>
                    <ul style="margin-top: 6px; padding-left: 20px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= sanitize($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="/trainers/edit.php?id=<?= $id ?>">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="trainer_code">Mã Huấn Luyện Viên <span class="required">*</span></label>
                    <input type="text" id="trainer_code" name="trainer_code" class="form-control" 
                           value="<?= sanitize($data['trainer_code']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="full_name">Họ và Tên <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name" class="form-control" 
                           value="<?= sanitize($data['full_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Số Điện Thoại <span class="required">*</span></label>
                    <input type="text" id="phone" name="phone" class="form-control" 
                           value="<?= sanitize($data['phone']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Địa Chỉ Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?= sanitize($data['email']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="specialty">Chuyên Môn Giảng Dạy <span class="required">*</span></label>
                    <input type="text" id="specialty" name="specialty" class="form-control" 
                           value="<?= sanitize($data['specialty']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Trạng Thái Làm Việc <span class="required">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="active" <?= $data['status'] === 'active' ? 'selected' : '' ?>>Đang làm việc</option>
                        <option value="inactive" <?= $data['status'] === 'inactive' ? 'selected' : '' ?>>Tạm nghỉ việc</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="bio">Tiểu Sử & Kinh Nghiệm</label>
                    <textarea id="bio" name="bio" class="form-control"><?= sanitize($data['bio']) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Cập Nhật Thay Đổi</button>
                <a href="/trainers/index.php" class="btn btn-secondary">Hủy Bỏ</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
