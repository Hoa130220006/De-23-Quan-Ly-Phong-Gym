<?php
/**
 * Global Utility Functions & Helpers
 * Xử lý bảo mật, ghi log hệ thống, kiểm tra xung đột lịch và format dữ liệu
 */

if (session_status() === PHP_SESSION_NONE) {
    // Cấu hình session an toàn
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * Ghi log ứng dụng ra file dạng JSON cho Promtail/Loki thu thập
 */
function writeAppLog(string $level, string $message, array $context = []): void {
    $logDir = '/var/log/app';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/app.log';

    $logEntry = [
        'timestamp' => date('Y-m-d\TH:i:sP'),
        'level'     => strtoupper($level),
        'message'   => $message,
        'user'      => $_SESSION['user']['username'] ?? 'anonymous',
        'role'      => $_SESSION['user']['role'] ?? 'guest',
        'ip'        => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'user_agent'=> $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'context'   => $context
    ];

    $jsonLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    @file_put_contents($logFile, $jsonLine, FILE_APPEND | LOCK_EX);
}

/**
 * Xử lý làm sạch chuỗi đầu vào chống XSS
 */
function sanitize(?string $data): string {
    if ($data === null) {
        return '';
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Định dạng tiền tệ VNĐ
 */
function formatVND($amount): string {
    return number_format((float)$amount, 0, ',', '.') . ' ₫';
}

/**
 * Định dạng ngày d/m/Y
 */
function formatDate(?string $date): string {
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    return date('d/m/Y', strtotime($date));
}

/**
 * Định dạng ngày giờ d/m/Y H:i
 */
function formatDateTime(?string $datetime): string {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    return date('d/m/Y H:i', strtotime($datetime));
}

/**
 * CSRF Protection
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verify_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            writeAppLog('WARNING', 'CSRF token validation failed', [
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? ''
            ]);
            return false;
        }
    }
    return true;
}

/**
 * Quản lý Flash Messages thông báo giao diện
 */
function setFlash(string $type, string $message): void {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type, // success, error, warning, info
        'message' => $message
    ];
}

function getFlashMessages(): array {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Ghi vết hoạt động người dùng vào bảng MySQL `system_logs`
 */
function recordAuditLog(PDO $pdo, string $action, string $details = ''): void {
    try {
        $userId = $_SESSION['user']['id'] ?? null;
        $username = $_SESSION['user']['username'] ?? 'anonymous';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $pdo->prepare("
            INSERT INTO system_logs (user_id, username, action, details, ip_address)
            VALUES (:user_id, :username, :action, :details, :ip)
        ");
        $stmt->execute([
            ':user_id'  => $userId,
            ':username' => $username,
            ':action'   => $action,
            ':details'  => $details,
            ':ip'       => $ip
        ]);

        writeAppLog('INFO', "Audit: {$action}", ['details' => $details]);
    } catch (Exception $e) {
        writeAppLog('ERROR', 'Failed to insert audit log: ' . $e->getMessage());
    }
}

/**
 * Kiểm tra xung đột lịch tập của Huấn luyện viên
 * Tránh 1 HLV bị trùng 2 ca tập cùng 1 khung giờ trong ngày
 */
function checkTrainerScheduleConflict(
    PDO $pdo,
    int $trainerId,
    string $date,
    string $startTime,
    string $endTime,
    ?int $excludeScheduleId = null
): bool {
    $sql = "
        SELECT id, member_id, start_time, end_time 
        FROM training_schedules
        WHERE trainer_id = :trainer_id
          AND schedule_date = :schedule_date
          AND status != 'cancelled'
          AND (
              (start_time < :end_time AND end_time > :start_time)
          )
    ";

    $params = [
        ':trainer_id'    => $trainerId,
        ':schedule_date' => $date,
        ':start_time'    => $startTime,
        ':end_time'      => $endTime
    ];

    if ($excludeScheduleId !== null) {
        $sql .= " AND id != :exclude_id";
        $params[':exclude_id'] = $excludeScheduleId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $conflicts = $stmt->fetchAll();

    return count($conflicts) > 0;
}

/**
 * Tự động cập nhật các gói tập hết hạn dựa trên ngày hiện tại
 */
function autoExpireMemberships(PDO $pdo): void {
    try {
        $stmt = $pdo->prepare("
            UPDATE memberships 
            SET status = 'expired' 
            WHERE end_date < CURDATE() 
              AND status = 'active'
        ");
        $stmt->execute();
    } catch (Exception $e) {
        // silent fail or log
        writeAppLog('ERROR', 'Auto-expire memberships failed: ' . $e->getMessage());
    }
}
