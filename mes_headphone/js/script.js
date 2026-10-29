// FILE: js/script.js - JavaScript thuần cho giao diện

// 1. Hỏi xác nhận trước khi xóa: mọi form có class "form-delete"
document.querySelectorAll('.form-delete').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        if (!confirm('Bạn có chắc chắn muốn xóa? Thao tác này không thể hoàn tác.')) {
            e.preventDefault();
        }
    });
});

// 2. Nút ☰ mở/đóng sidebar trên điện thoại
var btn = document.getElementById('toggleSidebar');
if (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('sidebar').classList.toggle('open');
    });
}

// 3. Tự ẩn thông báo thành công sau 4 giây
setTimeout(function () {
    document.querySelectorAll('.alert.auto-hide').forEach(function (a) {
        a.style.display = 'none';
    });
}, 4000);
