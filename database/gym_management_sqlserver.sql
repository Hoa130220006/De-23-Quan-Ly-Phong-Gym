-- ==========================================================
-- ĐỀ TÀI: HỆ THỐNG QUẢN LÝ PHÒNG GYM / FITNESS
-- Cơ sở dữ liệu: gym_management
-- Hệ quản trị CSDL: Microsoft SQL Server (T-SQL)
-- Dành cho SQL Server Management Studio (SSMS)
-- ==========================================================

USE master;
GO

IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = N'gym_management')
BEGIN
    CREATE DATABASE gym_management COLLATE Vietnamese_CI_AS;
END
GO

USE gym_management;
GO

-- Xóa các ràng buộc khóa ngoại nếu đã tồn tại
IF OBJECT_ID(N'dbo.training_schedules', N'U') IS NOT NULL DROP TABLE dbo.training_schedules;
IF OBJECT_ID(N'dbo.memberships', N'U') IS NOT NULL DROP TABLE dbo.memberships;
IF OBJECT_ID(N'dbo.packages', N'U') IS NOT NULL DROP TABLE dbo.packages;
IF OBJECT_ID(N'dbo.trainers', N'U') IS NOT NULL DROP TABLE dbo.trainers;
IF OBJECT_ID(N'dbo.members', N'U') IS NOT NULL DROP TABLE dbo.members;
IF OBJECT_ID(N'dbo.system_logs', N'U') IS NOT NULL DROP TABLE dbo.system_logs;
IF OBJECT_ID(N'dbo.users', N'U') IS NOT NULL DROP TABLE dbo.users;
GO

-- 1. BẢNG USERS (Tài khoản người dùng & phân quyền)
CREATE TABLE dbo.users (
    id INT IDENTITY(1,1) PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name NVARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role VARCHAR(20) NOT NULL DEFAULT 'staff' CHECK (role IN ('admin', 'staff')),
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME NOT NULL DEFAULT GETDATE()
);
GO

-- 2. BẢNG MEMBERS (Hội viên phòng gym)
CREATE TABLE dbo.members (
    id INT IDENTITY(1,1) PRIMARY KEY,
    member_code VARCHAR(20) NOT NULL UNIQUE,
    full_name NVARCHAR(100) NOT NULL,
    dob DATE NOT NULL,
    gender VARCHAR(10) NOT NULL DEFAULT 'male' CHECK (gender IN ('male', 'female', 'other')),
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    address NVARCHAR(255) NULL,
    registration_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'suspended')),
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME NOT NULL DEFAULT GETDATE()
);
CREATE INDEX idx_members_code ON dbo.members(member_code);
CREATE INDEX idx_members_phone ON dbo.members(phone);
CREATE INDEX idx_members_status ON dbo.members(status);
GO

-- 3. BẢNG TRAINERS (Huấn luyện viên)
CREATE TABLE dbo.trainers (
    id INT IDENTITY(1,1) PRIMARY KEY,
    trainer_code VARCHAR(20) NOT NULL UNIQUE,
    full_name NVARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    specialty NVARCHAR(150) NOT NULL,
    bio NVARCHAR(MAX) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME NOT NULL DEFAULT GETDATE()
);
CREATE INDEX idx_trainers_code ON dbo.trainers(trainer_code);
CREATE INDEX idx_trainers_status ON dbo.trainers(status);
GO

-- 4. BẢNG PACKAGES (Gói tập)
CREATE TABLE dbo.packages (
    id INT IDENTITY(1,1) PRIMARY KEY,
    package_code VARCHAR(20) NOT NULL UNIQUE,
    package_name NVARCHAR(100) NOT NULL,
    duration_days INT NOT NULL,
    price DECIMAL(12, 2) NOT NULL,
    description NVARCHAR(MAX) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME NOT NULL DEFAULT GETDATE()
);
CREATE INDEX idx_packages_code ON dbo.packages(package_code);
CREATE INDEX idx_packages_status ON dbo.packages(status);
GO

-- 5. BẢNG MEMBERSHIPS (Gói tập hội viên đã đăng ký)
CREATE TABLE dbo.memberships (
    id INT IDENTITY(1,1) PRIMARY KEY,
    member_id INT NOT NULL,
    package_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    price_paid DECIMAL(12, 2) NOT NULL,
    payment_status VARCHAR(20) NOT NULL DEFAULT 'paid' CHECK (payment_status IN ('paid', 'pending', 'cancelled')),
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'expired', 'cancelled')),
    notes NVARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT fk_memberships_member FOREIGN KEY (member_id) REFERENCES dbo.members(id) ON DELETE CASCADE,
    CONSTRAINT fk_memberships_package FOREIGN KEY (package_id) REFERENCES dbo.packages(id)
);
CREATE INDEX idx_memberships_member ON dbo.memberships(member_id);
CREATE INDEX idx_memberships_package ON dbo.memberships(package_id);
CREATE INDEX idx_memberships_status ON dbo.memberships(status);
GO

-- 6. BẢNG TRAINING_SCHEDULES (Lịch tập cá nhân với huấn luyện viên)
CREATE TABLE dbo.training_schedules (
    id INT IDENTITY(1,1) PRIMARY KEY,
    member_id INT NOT NULL,
    trainer_id INT NOT NULL,
    schedule_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'completed', 'cancelled')),
    notes NVARCHAR(MAX) NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT fk_schedules_member FOREIGN KEY (member_id) REFERENCES dbo.members(id) ON DELETE CASCADE,
    CONSTRAINT fk_schedules_trainer FOREIGN KEY (trainer_id) REFERENCES dbo.trainers(id)
);
CREATE INDEX idx_schedules_trainer_date ON dbo.training_schedules(trainer_id, schedule_date);
CREATE INDEX idx_schedules_member_date ON dbo.training_schedules(member_id, schedule_date);
CREATE INDEX idx_schedules_status ON dbo.training_schedules(status);
GO

-- 7. BẢNG SYSTEM_LOGS (Ghi vết hoạt động trong ứng dụng)
CREATE TABLE dbo.system_logs (
    id INT IDENTITY(1,1) PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(50) NULL,
    action NVARCHAR(100) NOT NULL,
    details NVARCHAR(MAX) NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT GETDATE()
);
CREATE INDEX idx_logs_user ON dbo.system_logs(user_id);
CREATE INDEX idx_logs_action ON dbo.system_logs(action);
GO

-- ==========================================================
-- NẠP DỮ LIỆU MẪU (SEED DATA)
-- ==========================================================

-- 1. Tài khoản (admin / Admin@123, staff / Staff@123)
SET IDENTITY_INSERT dbo.users ON;
INSERT INTO dbo.users (id, username, password_hash, full_name, email, role, status) VALUES
(1, 'admin', '$2y$10$G5vF3F0UWzWJ0jqNLeiQgeqeQXnoFiOFKQVhMIb.k5fnG0l2T6C5m', N'Quản trị viên Hệ thống', 'admin@gymfitness.vn', 'admin', 'active'),
(2, 'staff', '$2y$10$D.iZOoiBNXLBEZ2d4ApRreqRvkT84lcqA7SUkg9d5P9QcKaImuY2S', N'Nguyễn Thị Thu Hà', 'staff@gymfitness.vn', 'staff', 'active');
SET IDENTITY_INSERT dbo.users OFF;
GO

-- 2. Huấn luyện viên
SET IDENTITY_INSERT dbo.trainers ON;
INSERT INTO dbo.trainers (id, trainer_code, full_name, phone, email, specialty, bio, status) VALUES
(1, 'HLV001', N'Trần Văn Mạnh', '0901234567', 'tranvanmanh@gymfitness.vn', N'Thể hình & Tăng cơ (Bodybuilding)', N'8 năm kinh nghiệm huấn luyện thi đấu thể hình quốc gia.', 'active'),
(2, 'HLV002', N'Lê Hoàng Nam', '0912345678', 'lehoangnam@gymfitness.vn', N'Cardio & Giảm mỡ cấp tốc (HIIT)', N'Chuyên gia phục hồi thể lực và chế độ dinh dưỡng thể thao.', 'active'),
(3, 'HLV003', N'Nguyễn Thảo My', '0923456789', 'nguyenthaomy@gymfitness.vn', N'Yoga & Pilates dẻo dai', N'Chứng chỉ Yoga quốc tế Alliance 500h, 5 năm kinh nghiệm.', 'active'),
(4, 'HLV004', N'Phạm Quốc Bảo', '0934567890', 'phamquocbao@gymfitness.vn', N'Kickboxing & Tự vệ thực chiến', N'Cựu vận động viên Boxing đai vàng, chuyên huấn luyện Kickfit.', 'active');
SET IDENTITY_INSERT dbo.trainers OFF;
GO

-- 3. Gói tập
SET IDENTITY_INSERT dbo.packages ON;
INSERT INTO dbo.packages (id, package_code, package_name, duration_days, price, description, status) VALUES
(1, 'GOI01', N'Gói Khởi Động (1 Tháng)', 30, 450000.00, N'Tự do tập luyện tất cả các thiết bị cơ bản tại phòng tập trong 30 ngày.', 'active'),
(2, 'GOI02', N'Gói Tiêu Chuẩn (3 Tháng)', 90, 1200000.00, N'Tập luyện không giới hạn thời gian + miễn phí đo chỉ số InBody định kỳ.', 'active'),
(3, 'GOI03', N'Gói Nâng Cao (6 Tháng)', 180, 2200000.00, N'Tặng 2 buổi cùng HLV cá nhân + tủ đồ riêng + sử dụng phòng xông hơi.', 'active'),
(4, 'GOI04', N'Gói VIP Toàn Diện (12 Tháng)', 365, 3900000.00, N'Đầy đủ quyền lợi VIP: tặng 5 buổi HLV cá nhân, xông hơi khô/ướt, khăn tắm miễn phí.', 'active'),
(5, 'GOI05', N'Gói Kickfit & Boxing (3 Tháng)', 90, 1800000.00, N'Bao gồm tập Gym tổng hợp và lớp huấn luyện Kickfit chuyên biệt.', 'active');
SET IDENTITY_INSERT dbo.packages OFF;
GO

-- 4. Hội viên
SET IDENTITY_INSERT dbo.members ON;
INSERT INTO dbo.members (id, member_code, full_name, dob, gender, phone, email, address, registration_date, status) VALUES
(1, 'HV001', N'Nguyễn Văn An', '1995-03-15', 'male', '0981112233', 'nguyenvanan@gmail.com', N'123 Đường Cầu Giấy, Hà Nội', '2026-01-10', 'active'),
(2, 'HV002', N'Trần Thị Bích', '1998-07-22', 'female', '0982223344', 'tranthibich@gmail.com', N'45 Phố Huế, Hai Bà Trưng, Hà Nội', '2026-02-01', 'active'),
(3, 'HV003', N'Lê Tuấn Cường', '1992-11-05', 'male', '0983334455', 'letuancuong@gmail.com', N'78 Nguyễn Trãi, Thanh Xuân, Hà Nội', '2026-02-15', 'active'),
(4, 'HV004', N'Phạm Mỹ Duyên', '2000-09-30', 'female', '0984445566', 'phammyduyen@gmail.com', N'12 Kim Mã, Ba Đình, Hà Nội', '2025-10-01', 'active'),
(5, 'HV005', N'Hoàng Văn Em', '1996-04-18', 'male', '0985556677', 'hoangvanem@gmail.com', N'56 Giải Phóng, Đống Đa, Hà Nội', '2025-09-15', 'active'),
(6, 'HV006', N'Vũ Thị Phương', '1999-12-12', 'female', '0986667788', 'vuthiphuong@gmail.com', N'89 Trần Duy Hưng, Cầu Giấy, Hà Nội', '2026-03-01', 'active'),
(7, 'HV007', N'Đặng Minh Giang', '1994-06-25', 'male', '0987778899', 'dangminhgiang@gmail.com', N'101 Xuân Thủy, Cầu Giấy, Hà Nội', '2025-11-20', 'suspended'),
(8, 'HV008', N'Bùi Ngọc Hân', '2001-08-14', 'female', '0988889900', 'buingochan@gmail.com', N'210 Hoàng Hoa Thám, Tây Hồ, Hà Nội', '2026-03-10', 'active');
SET IDENTITY_INSERT dbo.members OFF;
GO

-- 5. Đăng ký gói tập hội viên
SET IDENTITY_INSERT dbo.memberships ON;
INSERT INTO dbo.memberships (id, member_id, package_id, start_date, end_date, price_paid, payment_status, status, notes) VALUES
(1, 1, 4, '2026-01-10', '2027-01-10', 3900000.00, 'paid', 'active', N'Đăng ký VIP 1 năm'),
(2, 2, 2, '2026-02-01', '2026-05-01', 1200000.00, 'paid', 'active', N'Gói Tiêu chuẩn 3 tháng'),
(3, 3, 3, '2026-02-15', '2026-08-15', 2200000.00, 'paid', 'active', N'Gói Nâng cao 6 tháng'),
(4, 4, 1, '2025-10-01', '2025-10-31', 450000.00, 'paid', 'expired', N'Hết hạn từ năm trước'),
(5, 5, 2, '2025-09-15', '2025-12-15', 1200000.00, 'paid', 'expired', N'Hết hạn gói tiêu chuẩn'),
(6, 6, 5, '2026-03-01', '2026-06-01', 1800000.00, 'paid', 'active', N'Gói Kickfit 3 tháng'),
(7, 7, 1, '2025-11-20', '2025-12-20', 450000.00, 'paid', 'expired', N'Tạm ngưng sinh hoạt'),
(8, 8, 2, '2026-03-10', '2026-06-10', 1200000.00, 'paid', 'active', N'Mới gia hạn');
SET IDENTITY_INSERT dbo.memberships OFF;
GO

-- 6. Lịch tập
SET IDENTITY_INSERT dbo.training_schedules ON;
INSERT INTO dbo.training_schedules (id, member_id, trainer_id, schedule_date, start_time, end_time, status, notes) VALUES
(1, 1, 1, CAST(GETDATE() AS DATE), '08:00:00', '09:30:00', 'scheduled', N'Tập ngực & tay sau với HLV Mạnh'),
(2, 2, 3, CAST(GETDATE() AS DATE), '09:30:00', '10:30:00', 'scheduled', N'Lớp Yoga cá nhân kéo giãn cơ'),
(3, 3, 2, CAST(GETDATE() AS DATE), '16:00:00', '17:00:00', 'scheduled', N'Cardio đốt mỡ cường độ cao'),
(4, 6, 4, CAST(GETDATE() AS DATE), '18:00:00', '19:30:00', 'scheduled', N'Tập đối kháng Kickfit cơ bản'),
(5, 1, 1, DATEADD(DAY, 1, CAST(GETDATE() AS DATE)), '08:00:00', '09:30:00', 'scheduled', N'Tập chân & vai'),
(6, 8, 3, DATEADD(DAY, 1, CAST(GETDATE() AS DATE)), '10:00:00', '11:00:00', 'scheduled', N'Tư thế thăng bằng Yoga'),
(7, 2, 2, DATEADD(DAY, -1, CAST(GETDATE() AS DATE)), '14:00:00', '15:00:00', 'completed', N'Hoàn thành bài tập mông đùi'),
(8, 3, 4, DATEADD(DAY, -2, CAST(GETDATE() AS DATE)), '17:30:00', '19:00:00', 'completed', N'Tập kỹ thuật đấm thẳng và móc');
SET IDENTITY_INSERT dbo.training_schedules OFF;
GO

-- 7. Nhật ký hệ thống
SET IDENTITY_INSERT dbo.system_logs ON;
INSERT INTO dbo.system_logs (id, user_id, username, action, details, ip_address) VALUES
(1, 1, 'admin', N'SYSTEM_INIT', N'Khởi tạo thành công cơ sở dữ liệu và dữ liệu mẫu Gym Management trên Microsoft SQL Server.', '127.0.0.1');
SET IDENTITY_INSERT dbo.system_logs OFF;
GO

PRINT N'Khởi tạo cơ sở dữ liệu gym_management trên SQL Server thành công!';
GO
