<?php
/**
 * Gym & Fitness Management Dashboard
 * Tổng quan số liệu thực tế từ MySQL CSDL
 */

require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDB();
autoExpireMemberships($pdo);

// 1. Truy vấn các số liệu KPI thống kê thực tế
// Tổng số hội viên & Số hội viên đang hoạt động
$stmtMembers = $pdo->query("
    SELECT 
        COUNT(*) as total_members,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_members,
        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_members,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_members
    FROM members
");
$memberStats = $stmtMembers->fetch();

// Số gói tập còn hạn và hết hạn
$stmtMemberships = $pdo->query("
    SELECT 
        COUNT(*) as total_subscriptions,
        SUM(CASE WHEN status = 'active' AND end_date >= CURDATE() THEN 1 ELSE 0 END) as active_subscriptions,
        SUM(CASE WHEN status = 'expired' OR end_date < CURDATE() THEN 1 ELSE 0 END) as expired_subscriptions,
        COALESCE(SUM(price_paid), 0) as total_revenue
    FROM memberships
");
$membershipStats = $stmtMemberships->fetch();

// Tổng số huấn luyện viên đang làm việc
$stmtTrainers = $pdo->query("
    SELECT 
        COUNT(*) as total_trainers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_trainers
    FROM trainers
");
$trainerStats = $stmtTrainers->fetch();

// Số lịch tập trong ngày hôm nay
$stmtTodaySchedules = $pdo->query("
    SELECT COUNT(*) as today_schedules_count
    FROM training_schedules
    WHERE schedule_date = CURDATE() AND status != 'cancelled'
");
$todayScheduleCount = $stmtTodaySchedules->fetchColumn();

// 2. Danh sách lịch tập trong ngày hôm nay
$stmtTodayList = $pdo->query("
    SELECT ts.id, ts.schedule_date, ts.start_time, ts.end_time, ts.status, ts.notes,
           m.member_code, m.full_name as member_name, m.phone as member_phone,
           t.trainer_code, t.full_name as trainer_name, t.specialty
    FROM training_schedules ts
    JOIN members m ON ts.member_id = m.id
    JOIN trainers t ON ts.trainer_id = t.id
    WHERE ts.schedule_date = CURDATE()
    ORDER BY ts.start_time ASC
");
$todaySchedules = $stmtTodayList->fetchAll();

// 3. Danh sách 5 hội viên đăng ký mới nhất
$stmtRecentMembers = $pdo->query("
    SELECT id, member_code, full_name, phone, email, registration_date, status
    FROM members
    ORDER BY id DESC
    LIMIT 5
");
$recentMembers = $stmtRecentMembers->fetchAll();

// 4. Doanh thu theo từng gói tập
$stmtPackageRevenue = $pdo->query("
    SELECT p.package_name, COUNT(m.id) as sub_count, COALESCE(SUM(m.price_paid), 0) as revenue
    FROM packages p
    LEFT JOIN memberships m ON p.id = m.package_id
    GROUP BY p.id, p.package_name
    ORDER BY revenue DESC
");
$packageRevenues = $stmtPackageRevenue->fetchAll();

$pageTitle = 'Dashboard Thống Kê & Báo Cáo';
require_once __DIR__ . '/includes/header.php';
?>

<!-- KPI Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Tổng Hội Viên</span>
            <div class="stat-icon emerald">🏋️</div>
        </div>
        <div class="stat-value"><?= number_format($memberStats['total_members']) ?></div>
        <div class="stat-footer">
            <strong style="color: var(--success);"><?= number_format($memberStats['active_members']) ?></strong> đang hoạt động
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Gói Tập Còn Hạn</span>
            <div class="stat-icon cyan">✅</div>
        </div>
        <div class="stat-value"><?= number_format($membershipStats['active_subscriptions']) ?></div>
        <div class="stat-footer">
            <strong style="color: var(--warning);"><?= number_format($membershipStats['expired_subscriptions']) ?></strong> gói đã hết hạn
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Huấn Luyện Viên</span>
            <div class="stat-icon purple">🥊</div>
        </div>
        <div class="stat-value"><?= number_format($trainerStats['total_trainers']) ?></div>
        <div class="stat-footer">
            <strong style="color: var(--success);"><?= number_format($trainerStats['active_trainers']) ?></strong> đang sẵn sàng
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Lịch Tập Hôm Nay</span>
            <div class="stat-icon amber">📅</div>
        </div>
        <div class="stat-value"><?= number_format($todayScheduleCount) ?></div>
        <div class="stat-footer">
            Ngày: <?= date('d/m/Y') ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Tổng Doanh Thu</span>
            <div class="stat-icon rose">💰</div>
        </div>
        <div class="stat-value" style="font-size: 1.45rem; color: #34d399;">
            <?= formatVND($membershipStats['total_revenue']) ?>
        </div>
        <div class="stat-footer">
            <?= number_format($membershipStats['total_subscriptions']) ?> lượt đăng ký
        </div>
    </div>
</div>

<!-- Charts Section -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 30px;">
    <!-- Biểu đồ phân bổ Hội viên & Gói tập -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title">📊 Tỉ Lệ Tình Trạng Hội Viên & Gói Tập</h3>
        </div>
        <div class="card-body" style="height: 280px; position: relative;">
            <canvas id="statusChart"></canvas>
        </div>
    </div>

    <!-- Biểu đồ doanh thu theo gói tập -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title">📈 Doanh Thu Theo Gói Tập (VNĐ)</h3>
        </div>
        <div class="card-body" style="height: 280px; position: relative;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
</div>

<!-- Bảng Lịch tập hôm nay -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">⚡ Lịch Tập Cá Nhân Trong Ngày (<?= date('d/m/Y') ?>)</h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);">
                Tổng cộng: <?= count($todaySchedules) ?> ca tập
            </span>
        </div>
        <a href="/schedules/create.php" class="btn btn-primary btn-sm">➕ Đặt Lịch Mới</a>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Khung Giờ</th>
                    <th>Hội Viên</th>
                    <th>Huấn Luyện Viên</th>
                    <th>Chuyên Môn</th>
                    <th>Trạng Thái</th>
                    <th>Ghi Chú</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($todaySchedules)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            Hôm nay chưa có lịch tập nào được xếp.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($todaySchedules as $sched): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--secondary);">
                                    <?= substr($sched['start_time'], 0, 5) ?> - <?= substr($sched['end_time'], 0, 5) ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?= sanitize($sched['member_name']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= sanitize($sched['member_code']) ?> &bull; <?= sanitize($sched['member_phone']) ?></small>
                            </td>
                            <td>
                                <strong><?= sanitize($sched['trainer_name']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= sanitize($sched['trainer_code']) ?></small>
                            </td>
                            <td>
                                <span style="font-size: 0.82rem; color: var(--text-muted);"><?= sanitize($sched['specialty']) ?></span>
                            </td>
                            <td>
                                <span class="status-pill <?= sanitize($sched['status']) ?>">
                                    <?php
                                    switch ($sched['status']) {
                                        case 'scheduled': echo 'Đã lên lịch'; break;
                                        case 'completed': echo 'Đã hoàn thành'; break;
                                        case 'cancelled': echo 'Đã hủy'; break;
                                        default: echo sanitize($sched['status']);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <small><?= sanitize($sched['notes'] ?: '-') ?></small>
                            </td>
                            <td style="text-align: right;">
                                <a href="/schedules/edit.php?id=<?= $sched['id'] ?>" class="btn btn-secondary btn-sm" title="Sửa ca tập">✏️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Hội viên mới nhất -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">🆕 Hội Viên Đăng Ký Mới Nhất</h3>
        <a href="/members/index.php" class="btn btn-secondary btn-sm">Xem Tất Cả Hội Viên</a>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Mã HV</th>
                    <th>Họ và Tên</th>
                    <th>Số Điện Thoại</th>
                    <th>Email</th>
                    <th>Ngày Đăng Ký</th>
                    <th>Trạng Thái</th>
                    <th style="text-align: right;">Hành Động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentMembers as $m): ?>
                    <tr>
                        <td><span style="color: var(--primary); font-weight: 700;"><?= sanitize($m['member_code']) ?></span></td>
                        <td><strong><?= sanitize($m['full_name']) ?></strong></td>
                        <td><?= sanitize($m['phone']) ?></td>
                        <td><?= sanitize($m['email']) ?></td>
                        <td><?= formatDate($m['registration_date']) ?></td>
                        <td>
                            <span class="status-pill <?= sanitize($m['status']) ?>">
                                <?= $m['status'] === 'active' ? 'Hoạt động' : ($m['status'] === 'suspended' ? 'Tạm hoãn' : 'Ngưng') ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <a href="/members/view.php?id=<?= $m['id'] ?>" class="btn btn-secondary btn-sm">👁️ Xem</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Script vẽ biểu đồ Chart.js -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Biểu đồ tròn Phân bổ Tình trạng
    const ctxStatus = document.getElementById('statusChart');
    if (ctxStatus) {
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: ['Hội viên Hoạt động', 'Hội viên Tạm hoãn', 'Gói tập Còn hạn', 'Gói tập Hết hạn'],
                datasets: [{
                    data: [
                        <?= (int)$memberStats['active_members'] ?>,
                        <?= (int)$memberStats['suspended_members'] ?>,
                        <?= (int)$membershipStats['active_subscriptions'] ?>,
                        <?= (int)$membershipStats['expired_subscriptions'] ?>
                    ],
                    backgroundColor: ['#10b981', '#94a3b8', '#06b6d4', '#f59e0b'],
                    borderColor: '#162032',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#94a3b8', font: { size: 12 } }
                    }
                }
            }
        });
    }

    // 2. Biểu đồ cột Doanh thu theo gói tập
    const ctxRevenue = document.getElementById('revenueChart');
    if (ctxRevenue) {
        const pkgNames = <?= json_encode(array_column($packageRevenues, 'package_name'), JSON_UNESCAPED_UNICODE) ?>;
        const pkgValues = <?= json_encode(array_map('floatval', array_column($packageRevenues, 'revenue'))) ?>;

        new Chart(ctxRevenue, {
            type: 'bar',
            data: {
                labels: pkgNames,
                datasets: [{
                    label: 'Doanh thu (VNĐ)',
                    data: pkgValues,
                    backgroundColor: 'rgba(16, 185, 129, 0.65)',
                    borderColor: '#10b981',
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        ticks: { color: '#94a3b8', font: { size: 10 } },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    },
                    y: {
                        ticks: {
                            color: '#94a3b8',
                            callback: function(value) { return (value / 1000000) + ' Tr'; }
                        },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
