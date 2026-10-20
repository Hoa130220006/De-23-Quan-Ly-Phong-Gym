<?php
/**
 * Edit Membership Subscription
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Bản ghi không hợp lệ!');
    header('Location: /memberships/index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.*, mem.full_name as member_name, mem.member_code, p.package_name
    FROM memberships m
    JOIN members mem ON m.member_id = mem.id
    JOIN packages p ON m.package_id = p.id
    WHERE m.id = :id
");
$stmt->execute([':id' => $id]);
$sub = $stmt->fetch();

if (!$sub) {
    setFlash('error', 'Không tìm thấy thông tin đăng ký!');
    header('Location: /memberships/index.php');
    exit;
}

$packages = $pdo->query("SELECT id, package_name, duration_days, price FROM packages ORDER BY price ASC")->fetchAll();

$errors = [];
$data = $sub;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Mã bảo mật CSRF không hợp lệ!';
    } else {
        $data['package_id']     = (int)($_POST['package_id'] ?? 0);
        $data['start_date']     = trim($_POST['start_date'] ?? '');
        $data['end_date']       = trim($_POST['end_date'] ?? '');
        $data['price_paid']     = (float)($_POST['price_paid'] ?? 0);
        $data['payment_status'] = trim($_POST['payment_status'] ?? 'paid');
        $data['status']         = trim($_POST['status'] ?? 'active');
        $data['notes']          = trim($_POST['notes'] ?? '');

        if ($data['package_id'] <= 0) {
            $errors[] = 'Vui lòng chọn gói tập!';
        }
        if (empty($data['start_date'])) {
            $errors[] = 'Ngày bắt đầu không được để trống!';
        }
        if (empty($data['end_date']) || strtotime($data['end_date']) < strtotime($data['start_date'])) {
            $errors[] = 'Ngày hết hạn phải sau ngày bắt đầu!';
        }
        if ($data['price_paid'] < 0) {
            $errors[] = 'Số tiền thanh toán không được âm!';
        }

        if (empty($errors)) {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE memberships SET
                        package_id = :package_id,
                        start_date = :start_date,
                        end_date = :end_date,
                        price_paid = :price_paid,
                        payment_status = :payment_status,
                        status = :status,
                        notes = :notes
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    ':package_id'     => $data['package_id'],
                    ':start_date'     => $data['start_date'],
                    ':end_date'       => $data['end_date'],
                    ':price_paid'     => $data['price_paid'],
                    ':payment_status' => $data['payment_status'],
                    ':status'         => $data['status'],
                    ':notes'          => $data['notes'],
                    ':id'             => $id
                ]);

                recordAuditLog($pdo, 'MEMBERSHIP_UPDATE', "Cập nhật đăng ký gói #{$id} cho hội viên {$sub['member_name']}");
                writeAppLog('INFO', "Updated membership subscription ID {$id}");

                setFlash('success', 'Cập nhật đăng ký gói tập thành công!');
                header('Location: /memberships/index.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
                writeAppLog('ERROR', "Error updating membership {$id}: " . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Chỉnh Sửa Đăng Ký Gói: #' . $sub['id'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">✏️ Chỉnh Sửa Thông Tin Đăng Ký Gói Tập</h3>
        <a href="/memberships/index.php" class="btn btn-secondary btn-sm">⬅️ Quay Lại</a>
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

        <form method="POST" action="/memberships/edit.php?id=<?= $id ?>">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Hội Viên Đăng Ký</label>
                    <input type="text" class="form-control" value="<?= sanitize($sub['member_name']) ?> (<?= sanitize($sub['member_code']) ?>)" readonly disabled>
                </div>

                <div class="form-group">
                    <label class="form-label" for="package_id">Gói Tập <span class="required">*</span></label>
                    <select id="package_id" name="package_id" class="form-control" required>
                        <?php foreach ($packages as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $data['package_id'] == $p['id'] ? 'selected' : '' ?>>
                                <?= sanitize($p['package_name']) ?> (<?= formatVND($p['price']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Trạng Thái Gói <span class="required">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="active" <?= $data['status'] === 'active' ? 'selected' : '' ?>>Còn hạn (Active)</option>
                        <option value="expired" <?= $data['status'] === 'expired' ? 'selected' : '' ?>>Đã hết hạn (Expired)</option>
                        <option value="cancelled" <?= $data['status'] === 'cancelled' ? 'selected' : '' ?>>Đã hủy (Cancelled)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="start_date">Ngày Bắt Đầu <span class="required">*</span></label>
                    <input type="date" id="start_date" name="start_date" class="form-control" 
                           value="<?= sanitize($data['start_date']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="end_date">Ngày Hết Hạn <span class="required">*</span></label>
                    <input type="date" id="end_date" name="end_date" class="form-control" 
                           value="<?= sanitize($data['end_date']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="price_paid">Số Tiền (VNĐ) <span class="required">*</span></label>
                    <input type="number" id="price_paid" name="price_paid" class="form-control" 
                           value="<?= $data['price_paid'] ?>" min="0" step="10000" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="payment_status">Thanh Toán <span class="required">*</span></label>
                    <select id="payment_status" name="payment_status" class="form-control" required>
                        <option value="paid" <?= $data['payment_status'] === 'paid' ? 'selected' : '' ?>>Đã thanh toán (Paid)</option>
                        <option value="pending" <?= $data['payment_status'] === 'pending' ? 'selected' : '' ?>>Chờ thanh toán (Pending)</option>
                        <option value="cancelled" <?= $data['payment_status'] === 'cancelled' ? 'selected' : '' ?>>Hủy bỏ (Cancelled)</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="notes">Ghi Chú</label>
                    <textarea id="notes" name="notes" class="form-control"><?= sanitize($data['notes']) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Cập Nhật Đăng Ký</button>
                <a href="/memberships/index.php" class="btn btn-secondary">Hủy Bỏ</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
