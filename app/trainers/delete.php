<?php
/**
 * Delete Trainer Handler (Admin Only)
 */

require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin']);

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    setFlash('error', 'Mã xác thực bảo mật không hợp lệ!');
    header('Location: /trainers/index.php');
    exit;
}

if ($id > 0) {
    try {
        $stmtFind = $pdo->prepare("SELECT trainer_code, full_name FROM trainers WHERE id = :id");
        $stmtFind->execute([':id' => $id]);
        $trainer = $stmtFind->fetch();

        if ($trainer) {
            // Kiểm tra xem HLV có lịch tập liên kết không
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM training_schedules WHERE trainer_id = :id");
            $stmtCount->execute([':id' => $id]);
            $schedulesCount = $stmtCount->fetchColumn();

            if ($schedulesCount > 0) {
                setFlash('error', "Không thể xóa HLV '{$trainer['full_name']}' vì đang có {$schedulesCount} ca tập liên kết! Hãy chuyển trạng thái sang 'Tạm nghỉ việc' hoặc hủy các ca tập liên quan trước.");
            } else {
                $stmtDel = $pdo->prepare("DELETE FROM trainers WHERE id = :id");
                $stmtDel->execute([':id' => $id]);

                recordAuditLog($pdo, 'TRAINER_DELETE', "Đã xóa HLV: {$trainer['full_name']} ({$trainer['trainer_code']})");
                writeAppLog('WARNING', "Admin deleted trainer ID {$id}: {$trainer['trainer_code']}");

                setFlash('success', "Đã xóa thành công huấn luyện viên '{$trainer['full_name']}'!");
            }
        } else {
            setFlash('error', 'Huấn luyện viên không tồn tại trong hệ thống!');
        }
    } catch (Exception $e) {
        setFlash('error', 'Lỗi khi xóa huấn luyện viên: ' . $e->getMessage());
        writeAppLog('ERROR', "Failed to delete trainer {$id}: " . $e->getMessage());
    }
}

header('Location: /trainers/index.php');
exit;
