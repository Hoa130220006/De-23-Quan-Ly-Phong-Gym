<?php
/**
 * Register New Membership / Subscribe Package
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();

$members = $pdo->query("SELECT id, member_code, full_name, phone FROM members WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();
$packages = $pdo->query("SELECT id, package_code, package_name, duration_days, price FROM packages WHERE status = 'active' ORDER BY price ASC")->fetchAll();

$preMemberId = (int)($_GET['member_id'] ?? 0);
$prePackageId = (int)($_GET['package_id'] ?? 0);

$errors = [];

$data = [
    'member_id'      => $preMemberId,
    'package_id'     => $prePackageId,
    'start_date'     => date('Y-m-d'),
    'end_date'       => date('Y-m-d', strtotime('+30 days')),
    'price_paid'     => '',
    'payment_status' => 'paid',
    'status'         => 'active',
    'notes'          => ''
];

// Nếu đã chọn package trước, tìm giá và thời hạn tương ứng
if ($prePackageId > 0) {
    foreach ($packages as $pkg) {
        if ($pkg['id'] === $prePackageId) {
            $data['price_paid'] = $pkg['price'];
            $data['end_date'] = date('Y-m-d', strtotime("+{$pkg['duration_days']} days"));
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Mã bảo mật CSRF không hợp lệ!';
    } else {
        $data['member_id']      = (int)($_POST['member_id'] ?? 0);
        $data['package_id']     = (int)($_POST['package_id'] ?? 0);
        $data['start_date']     = trim($_POST['start_date'] ?? date('Y-m-d'));
        $data['end_date']       = trim($_POST['end_date'] ?? '');
        $data['price_paid']     = (float)($_POST['price_paid'] ?? 0);
        $data['payment_status'] = trim($_POST['payment_status'] ?? 'paid');
        $data['status']         = trim($_POST['status'] ?? 'active');
        $data['notes']          = trim($_POST['notes'] ?? '');

        // Validation
        if ($data['member_id'] <= 0) {
            $errors[] = 'Vui lòng chọn hội viên đăng ký!';
        }
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

        // Tự động kiểm tra trạng thái dựa trên end_date
        if (strtotime($data['end_date']) < strtotime(date('Y-m-d'))) {
            $data['status'] = 'expired';
        }

        if (empty($errors)) {
            try {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO memberships (member_id, package_id, start_date, end_date, price_paid, payment_status, status, notes)
                    VALUES (:member_id, :package_id, :start_date, :end_date, :price_paid, :payment_status, :status, :notes)
                ");
                $stmtInsert->execute([
                    ':member_id'      => $data['member_id'],
                    ':package_id'     => $data['package_id'],
                    ':start_date'     => $data['start_date'],
                    ':end_date'       => $data['end_date'],
                    ':price_paid'     => $data['price_paid'],
                    ':payment_status' => $data['payment_status'],
                    ':status'         => $data['status'],
                    ':notes'          => $data['notes']
                ]);

                $newSubId = $pdo->lastInsertId();
                recordAuditLog($pdo, 'MEMBERSHIP_CREATE', "Đăng ký gói tập mới #{$newSubId} cho hội viên ID {$data['member_id']}");
                writeAppLog('INFO', "Created membership subscription ID {$newSubId}");

                setFlash('success', 'Đăng ký gói tập cho hội viên thành công!');
                header('Location: /memberships/index.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
                writeAppLog('ERROR', 'Error creating membership: ' . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Đăng Ký Gói Tập Hội Viên';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">📝 Đăng Ký Gói Tập Mới</h3>
        <a href="/memberships/index.php" class="btn btn-secondary btn-sm">⬅️ Quay Lại Danh Sách</a>
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

        <form method="POST" action="/memberships/create.php">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="member_id">Chọn Hội Viên <span class="required">*</span></label>
                    <select id="member_id" name="member_id" class="form-control" required>
                        <option value="">-- Chọn hội viên --</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $data['member_id'] === $m['id'] ? 'selected' : '' ?>>
                                <?= sanitize($m['full_name']) ?> (<?= sanitize($m['member_code']) ?> - <?= sanitize($m['phone']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="package_id">Chọn Gói Tập <span class="required">*</span></label>
                    <select id="package_id" name="package_id" class="form-control" required onchange="updatePackageData(this)">
                        <option value="">-- Chọn gói tập --</option>
                        <?php foreach ($packages as $p): ?>
                            <option value="<?= $p['id'] ?>" 
                                    data-days="<?= $p['duration_days'] ?>" 
                                    data-price="<?= $p['price'] ?>"
                                    <?= $data['package_id'] === $p['id'] ? 'selected' : '' ?>>
                                <?= sanitize($p['package_name']) ?> (<?= $p['duration_days'] ?> ngày - <?= formatVND($p['price']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="start_date">Ngày Bắt Đầu <span class="required">*</span></label>
                    <input type="date" id="start_date" name="start_date" class="form-control" 
                           value="<?= sanitize($data['start_date']) ?>" required onchange="recalculateEndDate()">
                </div>

                <div class="form-group">
                    <label class="form-label" for="end_date">Ngày Hết Hạn <span class="required">*</span></label>
                    <input type="date" id="end_date" name="end_date" class="form-control" 
                           value="<?= sanitize($data['end_date']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="price_paid">Số Tiền Thanh Toán (VNĐ) <span class="required">*</span></label>
                    <input type="number" id="price_paid" name="price_paid" class="form-control" 
                           value="<?= $data['price_paid'] ?>" min="0" step="10000" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="payment_status">Tình Trạng Thanh Toán <span class="required">*</span></label>
                    <select id="payment_status" name="payment_status" class="form-control" required>
                        <option value="paid" <?= $data['payment_status'] === 'paid' ? 'selected' : '' ?>>Đã thanh toán đủ (Paid)</option>
                        <option value="pending" <?= $data['payment_status'] === 'pending' ? 'selected' : '' ?>>Chờ thanh toán (Pending)</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="notes">Ghi Chú Đăng Ký</label>
                    <textarea id="notes" name="notes" class="form-control" placeholder="Ghi chú khuyến mãi, hóa đơn, người giới thiệu..."><?= sanitize($data['notes']) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Xác Nhận Đăng Ký</button>
                <a href="/memberships/index.php" class="btn btn-secondary">Hủy Bỏ</a>
            </div>
        </form>
    </div>
</div>

<script>
function updatePackageData(selectElem) {
    const selectedOption = selectElem.options[selectElem.selectedIndex];
    if (!selectedOption || !selectedOption.value) return;

    const days = parseInt(selectedOption.getAttribute('data-days')) || 30;
    const price = selectedOption.getAttribute('data-price') || 0;

    const priceInput = document.getElementById('price_paid');
    if (priceInput) priceInput.value = price;

    recalculateEndDate(days);
}

function recalculateEndDate(explicitDays = null) {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const packageSelect = document.getElementById('package_id');

    if (!startDateInput.value) return;

    let days = explicitDays;
    if (days === null && packageSelect) {
        const selected = packageSelect.options[packageSelect.selectedIndex];
        if (selected && selected.getAttribute('data-days')) {
            days = parseInt(selected.getAttribute('data-days'));
        }
    }
    if (!days) days = 30;

    const start = new Date(startDateInput.value);
    start.setDate(start.getDate() + days);
    endDateInput.value = start.toISOString().split('T')[0];
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
