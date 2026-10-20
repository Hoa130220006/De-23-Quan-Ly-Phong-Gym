<?php
/**
 * Delete / Cancel Membership Subscription (Admin Only)
 */

require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin']);

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    setFlash('error', 'Mã xác thực bảo mật không hợp lệ!');
    header('Location: /memberships/index.php');
    exit;
}

if ($id > 0) {
    try {
        $stmtFind = $pdo->prepare("SELECT id, member_id, package_id FROM memberships WHERE id = :id");
        $stmtFind->execute([':id' => $id]);
        $sub = $stmtFind->fetch();

        if ($sub) {
            $stmtDel = $pdo->prepare("DELETE FROM memberships WHERE id = :id");
            $stmtDel->execute([':id' => $id]);

            recordAuditLog($pdo, 'MEMBERSHIP_DELETE', "Đã xóa bản ghi đăng ký gói #{$id}");
            writeAppLog('WARNING', "Admin deleted membership ID {$id}");

            setFlash('success', "Đã xóa bản ghi đăng ký gói tập #{$id} thành công!");
        } else {
            setFlash('error', 'Bản ghi không tồn tại!');
        }
    } catch (Exception $e) {
        setFlash('error', 'Lỗi khi xóa đăng ký: ' . $e->getMessage());
        writeAppLog('ERROR', "Failed to delete membership {$id}: " . $e->getMessage());
    }
}

header('Location: /memberships/index.php');
exit;
