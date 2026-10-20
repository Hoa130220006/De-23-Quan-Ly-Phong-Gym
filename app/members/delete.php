<?php
/**
 * Delete Member Handler (Admin Only)
 */

require_once __DIR__ . '/../includes/auth_check.php';

// Chỉ quản trị viên mới có quyền xóa hội viên
requireRole(['admin']);

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    setFlash('error', 'Mã xác thực bảo mật không hợp lệ!');
    header('Location: /members/index.php');
    exit;
}

if ($id > 0) {
    try {
        $stmtFind = $pdo->prepare("SELECT member_code, full_name FROM members WHERE id = :id");
        $stmtFind->execute([':id' => $id]);
        $member = $stmtFind->fetch();

        if ($member) {
            $stmtDel = $pdo->prepare("DELETE FROM members WHERE id = :id");
            $stmtDel->execute([':id' => $id]);

            recordAuditLog($pdo, 'MEMBER_DELETE', "Đã xóa hội viên: {$member['full_name']} ({$member['member_code']})");
            writeAppLog('WARNING', "Admin deleted member ID {$id}: {$member['member_code']}");

            setFlash('success', "Đã xóa thành công hội viên '{$member['full_name']}'!");
        } else {
            setFlash('error', 'Hội viên không tồn tại trong hệ thống!');
        }
    } catch (Exception $e) {
        setFlash('error', 'Lỗi khi xóa hội viên: ' . $e->getMessage());
        writeAppLog('ERROR', "Failed to delete member {$id}: " . $e->getMessage());
    }
}

header('Location: /members/index.php');
exit;
