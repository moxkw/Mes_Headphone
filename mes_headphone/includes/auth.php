<?php
// FILE: includes/auth.php
// Đăng nhập, phân quyền và các hàm dùng chung. Mọi trang đều include file này.
session_start();

// Escape dữ liệu trước khi in ra HTML (chống XSS)
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function is_logged_in() { return isset($_SESSION['user']); }
function is_admin()     { return is_logged_in() && $_SESSION['user']['role'] === 'admin'; }

// Bắt buộc phải đăng nhập, nếu chưa thì chuyển về trang login
function require_login() {
    if (!is_logged_in()) { header('Location: login.php'); exit; }
}

// Bắt buộc là Admin (dùng cho các thao tác thêm/sửa/xóa quan trọng)
function require_admin() {
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'Bạn không có quyền thực hiện thao tác này (chỉ Admin).');
        header('Location: index.php'); exit;
    }
}

// Thông báo tạm (flash): lưu vào session, hiển thị 1 lần rồi xóa
function set_flash($type, $msg) { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }
function show_flash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        echo '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show auto-hide">'
           . e($f['msg']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        unset($_SESSION['flash']);
    }
}

// Chuyển hướng về 1 trang rồi dừng
function redirect($url) { header('Location: ' . $url); exit; }

// Hiển thị trạng thái lệnh sản xuất bằng nhãn màu
function status_badge($status) {
    $map = [
        'Planned'       => ['secondary', 'Planned'],
        'In Production' => ['warning text-dark', 'In Production'],   // vàng: đang sản xuất
        'Completed'     => ['success', 'Completed'],                 // xanh lá: hoàn thành
        'Cancelled'     => ['danger', 'Cancelled'],                  // đỏ: hủy
    ];
    $b = $map[$status] ?? ['secondary', $status];
    return '<span class="badge bg-' . $b[0] . '">' . e($b[1]) . '</span>';
}

// Tính % tiến độ (tối đa 100)
function percent($done, $total) {
    return $total > 0 ? min(100, round($done / $total * 100)) : 0;
}

// Thanh tiến độ
function progress_bar($done, $total) {
    $p = percent($done, $total);
    $color = $p >= 100 ? 'bg-success' : 'bg-warning';
    return '<div class="progress" style="height:18px"><div class="progress-bar ' . $color . '" style="width:' . $p . '%">' . $p . '%</div></div>';
}

// Chuyển chuỗi ngày dạng Y-m-d sang d/m/Y để hiển thị
function fdate($d) { return $d ? date('d/m/Y', strtotime($d)) : ''; }
