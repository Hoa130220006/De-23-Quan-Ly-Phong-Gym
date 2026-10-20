<?php
/**
 * Edit Existing Member
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Hội viên không hợp lệ!');
    header('Location: /members/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM members WHERE id = :id");
$stmt->execute([':id' => $id]);
$member = $stmt->fetch();

if (!$member) {
    setFlash('error', 'Không tìm thấy hội viên trong hệ thống!');
    header('Location: /members/index.php');
    exit;
}

$errors = [];
$data = $member;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Mã bảo mật CSRF không hợp lệ!';
    } else {
        $data['member_code']       = strtoupper(trim($_POST['member_code'] ?? ''));
        $data['full_name']         = trim($_POST['full_name'] ?? '');
        $data['dob']               = trim($_POST['dob'] ?? '');
        $data['gender']            = trim($_POST['gender'] ?? 'male');
        $data['phone']             = trim($_POST['phone'] ?? '');
        $data['email']             = trim($_POST['email'] ?? '');
        $data['address']           = trim($_POST['address'] ?? '');
        $data['registration_date'] = trim($_POST['registration_date'] ?? '');
        $data['status']            = trim($_POST['status'] ?? 'active');

        // Validation
        if (empty($data['member_code'])) {
            $errors[] = 'Mã hội viên không được để trống!';
        }
        if (empty($data['full_name'])) {
            $errors[] = 'Họ và tên hội viên không được để trống!';
        }
        if (empty($data['dob'])) {
            $errors[] = 'Ngày sinh không hợp lệ!';
        }
        if (empty($data['phone']) || !preg_match('/^[0-9]{9,11}$/', $data['phone'])) {
            $errors[] = 'Số điện thoại không hợp lệ (yêu cầu từ 9 - 11 chữ số)!';
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Định dạng Email không hợp lệ!';
        }

        // Kiểm tra trùng mã (trừ chính hội viên này)
        if (empty($errors)) {
            $stmtCheckCode = $pdo->prepare("SELECT COUNT(*) FROM members WHERE member_code = :code AND id != :id");
            $stmtCheckCode->execute([':code' => $data['member_code'], ':id' => $id]);
            if ($stmtCheckCode->fetchColumn() > 0) {
                $errors[] = "Mã hội viên '{$data['member_code']}' đã được sử dụng bởi hội viên khác!";
            }
        }

        // Kiểm tra trùng email (trừ chính hội viên này)
        if (empty($errors)) {
            $stmtCheckEmail = $pdo->prepare("SELECT COUNT(*) FROM members WHERE email = :email AND id != :id");
            $stmtCheckEmail->execute([':email' => $data['email'], ':id' => $id]);
            if ($stmtCheckEmail->fetchColumn() > 0) {
                $errors[] = "Email '{$data['email']}' đã được sử dụng bởi hội viên khác!";
            }
        }

        // Cập nhật CSDL
        if (empty($errors)) {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE members SET 
                        member_code = :member_code,
                        full_name = :full_name,
                        dob = :dob,
                        gender = :gender,
                        phone = :phone,
                        email = :email,
                        address = :address,
                        registration_date = :registration_date,
                        status = :status
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    ':member_code'       => $data['member_code'],
                    ':full_name'         => $data['full_name'],
                    ':dob'               => $data['dob'],
                    ':gender'            => $data['gender'],
                    ':phone'             => $data['phone'],
                    ':email'             => $data['email'],
                    ':address'           => $data['address'],
                    ':registration_date' => $data['registration_date'],
                    ':status'            => $data['status'],
                    ':id'                => $id
                ]);

                recordAuditLog($pdo, 'MEMBER_UPDATE', "Cập nhật hội viên #{$id}: {$data['full_name']} ({$data['member_code']})");
                writeAppLog('INFO', "Updated member ID {$id}: {$data['member_code']}");

                setFlash('success', "Cập nhật thông tin hội viên '{$data['full_name']}' thành công!");
                header('Location: /members/index.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Lỗi cập nhật cơ sở dữ liệu: ' . $e->getMessage();
                writeAppLog('ERROR', "Error updating member {$id}: " . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Chỉnh Sửa Hội Viên: ' . sanitize($member['full_name']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 850px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">✏️ Chỉnh Sửa Hồ Sơ Hội Viên</h3>
        <a href="/members/index.php" class="btn btn-secondary btn-sm">⬅️ Quay Lại Danh Sách</a>
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

        <form method="POST" action="/members/edit.php?id=<?= $id ?>">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="member_code">Mã Hội Viên <span class="required">*</span></label>
                    <input type="text" id="member_code" name="member_code" class="form-control" 
                           value="<?= sanitize($data['member_code']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="full_name">Họ và Tên <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name" class="form-control" 
                           value="<?= sanitize($data['full_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="dob">Ngày Sinh <span class="required">*</span></label>
                    <input type="date" id="dob" name="dob" class="form-control" 
                           value="<?= sanitize($data['dob']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="gender">Giới Tính <span class="required">*</span></label>
                    <select id="gender" name="gender" class="form-control" required>
                        <option value="male" <?= $data['gender'] === 'male' ? 'selected' : '' ?>>Nam</option>
                        <option value="female" <?= $data['gender'] === 'female' ? 'selected' : '' ?>>Nữ</option>
                        <option value="other" <?= $data['gender'] === 'other' ? 'selected' : '' ?>>Khác</option>
                    </select>
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
                    <label class="form-label" for="registration_date">Ngày Đăng Ký <span class="required">*</span></label>
                    <input type="date" id="registration_date" name="registration_date" class="form-control" 
                           value="<?= sanitize($data['registration_date']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Trạng Thái Hoạt Động <span class="required">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="active" <?= $data['status'] === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
                        <option value="suspended" <?= $data['status'] === 'suspended' ? 'selected' : '' ?>>Tạm hoãn</option>
                        <option value="inactive" <?= $data['status'] === 'inactive' ? 'selected' : '' ?>>Ngưng hoạt động</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="address">Địa Chỉ Thường Trú</label>
                    <textarea id="address" name="address" class="form-control"><?= sanitize($data['address']) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Cập Nhật Thay Đổi</button>
                <a href="/members/index.php" class="btn btn-secondary">Hủy Bỏ</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
