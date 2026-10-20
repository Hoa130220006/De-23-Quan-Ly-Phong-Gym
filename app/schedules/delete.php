<?php
/**
 * Cancel / Delete Training Schedule Handler
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    setFlash('error', 'Mã xác thực bảo mật không hợp lệ!');
    header('Location: /schedules/index.php');
    exit;
}

if ($id > 0) {
    try {
        $stmtFind = $pdo->prepare("SELECT id, schedule_date, start_time FROM training_schedules WHERE id = :id");
        $stmtFind->execute([':id' => $id]);
        $sched = $stmtFind->fetch();

        if ($sched) {
            $stmtDel = $pdo->prepare("DELETE FROM training_schedules WHERE id = :id");
            $stmtDel->execute([':id' => $id]);

            recordAuditLog($pdo, 'SCHEDULE_DELETE', "Hủy/Xóa ca tập #{$id} ngày {$sched['schedule_date']}");
            writeAppLog('WARNING', "User deleted schedule ID {$id}");

            setFlash('success', "Đã xóa ca tập #{$id} thành công!");
        } else {
            setFlash('error', 'Ca tập không tồn tại trong hệ thống!');
        }
    } catch (Exception $e) {
        setFlash('error', 'Lỗi khi xóa ca tập: ' . $e->getMessage());
        writeAppLog('ERROR', "Failed to delete schedule {$id}: " . $e->getMessage());
    }
}

header('Location: /schedules/index.php');
exit;
