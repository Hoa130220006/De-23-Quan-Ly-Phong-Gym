-- ==========================================================
-- ĐỀ TÀI 23: HỆ THỐNG QUẢN LÝ PHÒNG GYM / FITNESS
-- Cơ sở dữ liệu: gym_management
-- Hệ quản trị CSDL: MySQL 8.0
-- Bảng: users, members, trainers, packages, memberships, training_schedules, system_logs
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `gym_management` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gym_management`;

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET character_set_client = utf8mb4;
SET character_set_connection = utf8mb4;
SET character_set_results = utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `training_schedules`;
DROP TABLE IF EXISTS `memberships`;
DROP TABLE IF EXISTS `packages`;
DROP TABLE IF EXISTS `trainers`;
DROP TABLE IF EXISTS `members`;
DROP TABLE IF EXISTS `system_logs`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. BẢNG USERS (Tài khoản người dùng & phân quyền)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. BẢNG MEMBERS (Hội viên phòng gym)
CREATE TABLE `members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_code` VARCHAR(20) NOT NULL UNIQUE,
  `full_name` VARCHAR(100) NOT NULL,
  `dob` DATE NOT NULL,
  `gender` ENUM('male', 'female', 'other') NOT NULL DEFAULT 'male',
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `address` VARCHAR(255) DEFAULT NULL,
  `registration_date` DATE NOT NULL,
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_members_code` (`member_code`),
  INDEX `idx_members_phone` (`phone`),
  INDEX `idx_members_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BẢNG TRAINERS (Huấn luyện viên)
CREATE TABLE `trainers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `trainer_code` VARCHAR(20) NOT NULL UNIQUE,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `specialty` VARCHAR(150) NOT NULL,
  `bio` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_trainers_code` (`trainer_code`),
  INDEX `idx_trainers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BẢNG PACKAGES (Gói tập)
CREATE TABLE `packages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `package_code` VARCHAR(20) NOT NULL UNIQUE,
  `package_name` VARCHAR(100) NOT NULL,
  `duration_days` INT NOT NULL,
  `price` DECIMAL(12, 2) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_packages_code` (`package_code`),
  INDEX `idx_packages_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BẢNG MEMBERSHIPS (Gói tập hội viên đã đăng ký)
CREATE TABLE `memberships` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_id` INT NOT NULL,
  `package_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `price_paid` DECIMAL(12, 2) NOT NULL,
  `payment_status` ENUM('paid', 'pending', 'cancelled') NOT NULL DEFAULT 'paid',
  `status` ENUM('active', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_memberships_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_memberships_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE RESTRICT,
  INDEX `idx_memberships_member` (`member_id`),
  INDEX `idx_memberships_package` (`package_id`),
  INDEX `idx_memberships_status` (`status`),
  INDEX `idx_memberships_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. BẢNG TRAINING_SCHEDULES (Lịch tập cá nhân với huấn luyện viên)
CREATE TABLE `training_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_id` INT NOT NULL,
  `trainer_id` INT NOT NULL,
  `schedule_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `status` ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_schedules_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_schedules_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`) ON DELETE RESTRICT,
  INDEX `idx_schedules_trainer_date` (`trainer_id`, `schedule_date`),
  INDEX `idx_schedules_member_date` (`member_id`, `schedule_date`),
  INDEX `idx_schedules_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. BẢNG SYSTEM_LOGS (Ghi vết hoạt động trong ứng dụng)
CREATE TABLE `system_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `username` VARCHAR(50) DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_logs_user` (`user_id`),
  INDEX `idx_logs_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- DỮ LIỆU MẪU (SEED DATA)
-- ==========================================================

-- Mật khẩu:
-- admin / Admin@123 -> $2y$10$G5vF3F0UWzWJ0jqNLeiQgeqeQXnoFiOFKQVhMIb.k5fnG0l2T6C5m
-- staff / Staff@123 -> $2y$10$D.iZOoiBNXLBEZ2d4ApRreqRvkT84lcqA7SUkg9d5P9QcKaImuY2S
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role`, `status`) VALUES
('admin', '$2y$10$G5vF3F0UWzWJ0jqNLeiQgeqeQXnoFiOFKQVhMIb.k5fnG0l2T6C5m', 'Quản trị viên Hệ thống', 'admin@gymfitness.vn', 'admin', 'active'),
('staff', '$2y$10$D.iZOoiBNXLBEZ2d4ApRreqRvkT84lcqA7SUkg9d5P9QcKaImuY2S', 'Nguyễn Thị Thu Hà', 'staff@gymfitness.vn', 'staff', 'active');

-- Dữ liệu mẫu Huấn luyện viên
INSERT INTO `trainers` (`trainer_code`, `full_name`, `phone`, `email`, `specialty`, `bio`, `status`) VALUES
('HLV001', 'Trần Văn Mạnh', '0901234567', 'tranvanmanh@gymfitness.vn', 'Thể hình & Tăng cơ (Bodybuilding)', '8 năm kinh nghiệm huấn luyện thi đấu thể hình quốc gia.', 'active'),
('HLV002', 'Lê Hoàng Nam', '0912345678', 'lehoangnam@gymfitness.vn', 'Cardio & Giảm mỡ cấp tốc (HIIT)', 'Chuyên gia phục hồi thể lực và chế độ dinh dưỡng thể thao.', 'active'),
('HLV003', 'Nguyễn Thảo My', '0923456789', 'nguyenthaomy@gymfitness.vn', 'Yoga & Pilates dẻo dai', 'Chứng chỉ Yoga quốc tế Alliance 500h, 5 năm kinh nghiệm.', 'active'),
('HLV004', 'Phạm Quốc Bảo', '0934567890', 'phamquocbao@gymfitness.vn', 'Kickboxing & Tự vệ thực chiến', 'Cựu vận động viên Boxing đai vàng, chuyên huấn luyện Kickfit.', 'active');

-- Dữ liệu mẫu Gói tập
INSERT INTO `packages` (`package_code`, `package_name`, `duration_days`, `price`, `description`, `status`) VALUES
('GOI01', 'Gói Khởi Động (1 Tháng)', 30, 450000.00, 'Tự do tập luyện tất cả các thiết bị cơ bản tại phòng tập trong 30 ngày.', 'active'),
('GOI02', 'Gói Tiêu Chuẩn (3 Tháng)', 90, 1200000.00, 'Tập luyện không giới hạn thời gian + miễn phí đo chỉ số InBody định kỳ.', 'active'),
('GOI03', 'Gói Nâng Cao (6 Tháng)', 180, 2200000.00, 'Tặng 2 buổi cùng HLV cá nhân + tủ đồ riêng + sử dụng phòng xông hơi.', 'active'),
('GOI04', 'Gói VIP Toàn Diện (12 Tháng)', 365, 3900000.00, 'Đầy đủ quyền lợi VIP: tặng 5 buổi HLV cá nhân, xông hơi khô/ướt, khăn tắm miễn phí.', 'active'),
('GOI05', 'Gói Kickfit & Boxing (3 Tháng)', 90, 1800000.00, 'Bao gồm tập Gym tổng hợp và lớp huấn luyện Kickfit chuyên biệt.', 'active');

-- Dữ liệu mẫu Hội viên
INSERT INTO `members` (`member_code`, `full_name`, `dob`, `gender`, `phone`, `email`, `address`, `registration_date`, `status`) VALUES
('HV001', 'Nguyễn Văn An', '1995-03-15', 'male', '0981112233', 'nguyenvanan@gmail.com', '123 Đường Cầu Giấy, Hà Nội', '2026-01-10', 'active'),
('HV002', 'Trần Thị Bích', '1998-07-22', 'female', '0982223344', 'tranthibich@gmail.com', '45 Phố Huế, Hai Bà Trưng, Hà Nội', '2026-02-01', 'active'),
('HV003', 'Lê Tuấn Cường', '1992-11-05', 'male', '0983334455', 'letuancuong@gmail.com', '78 Nguyễn Trãi, Thanh Xuân, Hà Nội', '2026-02-15', 'active'),
('HV004', 'Phạm Mỹ Duyên', '2000-09-30', 'female', '0984445566', 'phammyduyen@gmail.com', '12 Kim Mã, Ba Đình, Hà Nội', '2025-10-01', 'active'),
('HV005', 'Hoàng Văn Em', '1996-04-18', 'male', '0985556677', 'hoangvanem@gmail.com', '56 Giải Phóng, Đống Đa, Hà Nội', '2025-09-15', 'active'),
('HV006', 'Vũ Thị Phương', '1999-12-12', 'female', '0986667788', 'vuthiphuong@gmail.com', '89 Trần Duy Hưng, Cầu Giấy, Hà Nội', '2026-03-01', 'active'),
('HV007', 'Đặng Minh Giang', '1994-06-25', 'male', '0987778899', 'dangminhgiang@gmail.com', '101 Xuân Thủy, Cầu Giấy, Hà Nội', '2025-11-20', 'suspended'),
('HV008', 'Bùi Ngọc Hân', '2001-08-14', 'female', '0988889900', 'buingochan@gmail.com', '210 Hoàng Hoa Thám, Tây Hồ, Hà Nội', '2026-03-10', 'active');

-- Dữ liệu mẫu Gói tập hội viên (Có cả gói còn hạn và hết hạn để Dashboard hiển thị trực quan)
INSERT INTO `memberships` (`member_id`, `package_id`, `start_date`, `end_date`, `price_paid`, `payment_status`, `status`, `notes`) VALUES
(1, 4, '2026-01-10', '2027-01-10', 3900000.00, 'paid', 'active', 'Đăng ký VIP 1 năm'),
(2, 2, '2026-02-01', '2026-05-01', 1200000.00, 'paid', 'active', 'Gói Tiêu chuẩn 3 tháng'),
(3, 3, '2026-02-15', '2026-08-15', 2200000.00, 'paid', 'active', 'Gói Nâng cao 6 tháng'),
(4, 1, '2025-10-01', '2025-10-31', 450000.00, 'paid', 'expired', 'Hết hạn từ năm trước'),
(5, 2, '2025-09-15', '2025-12-15', 1200000.00, 'paid', 'expired', 'Hết hạn gói tiêu chuẩn'),
(6, 5, '2026-03-01', '2026-06-01', 1800000.00, 'paid', 'active', 'Gói Kickfit 3 tháng'),
(7, 1, '2025-11-20', '2025-12-20', 450000.00, 'paid', 'expired', 'Tạm ngưng sinh hoạt'),
(8, 2, '2026-03-10', '2026-06-10', 1200000.00, 'paid', 'active', 'Mới gia hạn');

-- Dữ liệu mẫu Lịch tập (Bao gồm lịch ngày hôm nay CURRENT_DATE và các ngày khác)
INSERT INTO `training_schedules` (`member_id`, `trainer_id`, `schedule_date`, `start_time`, `end_time`, `status`, `notes`) VALUES
(1, 1, CURDATE(), '08:00:00', '09:30:00', 'scheduled', 'Tập ngực & tay sau với HLV Mạnh'),
(2, 3, CURDATE(), '09:30:00', '10:30:00', 'scheduled', 'Lớp Yoga cá nhân kéo giãn cơ'),
(3, 2, CURDATE(), '16:00:00', '17:00:00', 'scheduled', 'Cardio đốt mỡ cường độ cao'),
(6, 4, CURDATE(), '18:00:00', '19:30:00', 'scheduled', 'Tập đối kháng Kickfit cơ bản'),
(1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00', '09:30:00', 'scheduled', 'Tập chân & vai'),
(8, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00', '11:00:00', 'scheduled', 'Tư thế thăng bằng Yoga'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '14:00:00', '15:00:00', 'completed', 'Hoàn thành bài tập mông đùi'),
(3, 4, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '17:30:00', '19:00:00', 'completed', 'Tập kỹ thuật đấm thẳng và móc');

-- Ghi log khởi tạo hệ thống
INSERT INTO `system_logs` (`user_id`, `username`, `action`, `details`, `ip_address`) VALUES
(1, 'admin', 'SYSTEM_INIT', 'Khởi tạo thành công cơ sở dữ liệu và dữ liệu mẫu Gym Management.', '127.0.0.1');
