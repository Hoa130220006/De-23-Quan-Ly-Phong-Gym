/**
 * GYM & FITNESS MANAGEMENT SYSTEM - CLIENT SCRIPTS
 * Xử lý tương tác giao diện, validation, fill demo tài khoản và biểu đồ
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Tự động ẩn thông báo Flash sau 5 giây
    const flashAlerts = document.querySelectorAll('.flash-alert');
    flashAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // 2. Xác nhận trước khi xóa dữ liệu (Confirmation Guard)
    const deleteLinks = document.querySelectorAll('.confirm-delete, .btn-delete');
    deleteLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            const message = link.getAttribute('data-confirm') || 'Bạn có chắc chắn muốn xóa bản ghi này? Dữ liệu không thể phục hồi!';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 3. Client-side quick filter cho bảng dữ liệu (Data-Table Quick Search)
    const tableSearchInput = document.getElementById('tableSearchInput');
    if (tableSearchInput) {
        tableSearchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase().trim();
            const table = document.querySelector('.custom-table tbody');
            if (!table) return;
            const rows = table.querySelectorAll('tr');

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                if (text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // 4. Quick fill demo credentials on login page
    window.fillCredentials = function(username, password) {
        const userInput = document.getElementById('username');
        const passInput = document.getElementById('password');
        if (userInput && passInput) {
            userInput.value = username;
            passInput.value = password;
            userInput.focus();
        }
    };
});
