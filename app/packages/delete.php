<?php
/**
 * Delete Package Handler (Admin Only)
 */

require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin']);

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    setFlash('error', 'Mã xác thực bảo mật không hợp lệ!');
    header('Location: /packages/index.php');
    exit;
}

if ($id > 0) {
    try {
        $stmtFind = $pdo->prepare("SELECT package_code, package_name FROM packages WHERE id = :id");
        $stmtFind->execute([':id' => $id]);
        $pkg = $stmtFind->fetch();

        if ($pkg) {
            // Kiểm tra xem có hội viên nào đã đăng ký gói này không
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM memberships WHERE package_id = :id");
            $stmtCount->execute([':id' => $id]);
            $count = $stmtCount->fetchColumn();

            if ($count > 0) {
                setFlash('error', "Không thể xóa gói tập '{$pkg['package_name']}' vì đã có {$count} lượt hội viên đăng ký! Vui lòng chuyển trạng thái gói sang 'Tạm ngưng'.");
            } else {
                $stmtDel = $pdo->prepare("DELETE FROM packages WHERE id = :id");
                $stmtDel->execute([':id' => $id]);

                recordAuditLog($pdo, 'PACKAGE_DELETE', "Đã xóa gói tập: {$pkg['package_name']} ({$pkg['package_code']})");
                writeAppLog('WARNING', "Admin deleted package ID {$id}: {$pkg['package_code']}");

                setFlash('success', "Đã xóa thành công gói tập '{$pkg['package_name']}'!");
            }
        } else {
            setFlash('error', 'Gói tập không tồn tại trong hệ thống!');
        }
    } catch (Exception $e) {
        setFlash('error', 'Lỗi khi xóa gói tập: ' . $e->getMessage());
        writeAppLog('ERROR', "Failed to delete package {$id}: " . $e->getMessage());
    }
}

header('Location: /packages/index.php');
exit;
