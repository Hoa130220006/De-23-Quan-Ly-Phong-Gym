<?php
/**
 * Database Configuration & PDO Connection
 * Quản lý kết nối MySQL qua PDO với prepared statements
 */

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'mysql';
            $port = getenv('DB_PORT') ?: '3306';
            $db   = getenv('DB_NAME') ?: 'gym_management';
            $user = getenv('DB_USER') ?: 'gym_user';
            $pass = getenv('DB_PASS') ?: 'GymSecret@2026';
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Ghi log lỗi kết nối CSDL và báo thông điệp an toàn
                if (function_exists('writeAppLog')) {
                    writeAppLog('CRITICAL', 'Database connection failed: ' . $e->getMessage());
                } else {
                    error_log('Database connection failed: ' . $e->getMessage());
                }
                die("Lỗi kết nối cơ sở dữ liệu: Không thể kết nối đến máy chủ MySQL. Vui lòng kiểm tra cấu hình .env và trạng thái container MySQL.");
            }
        }

        return self::$instance;
    }
}

function getDB(): PDO {
    return Database::getConnection();
}
