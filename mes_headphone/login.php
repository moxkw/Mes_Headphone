<?php
// FILE: login.php - trang đăng nhập
require 'config/database.php';
require 'includes/auth.php';

if (is_logged_in()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // password_verify so sánh mật khẩu nhập với chuỗi hash trong database
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => $user['id'], 'username' => $user['username'],
                'full_name' => $user['full_name'], 'role' => $user['role'],
            ];
            redirect('index.php');
        } else {
            $error = 'Sai tên đăng nhập hoặc mật khẩu.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập - MES Tai nghe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-box shadow">
        <h4 class="text-center text-primary mb-1">🎧 MES Tai nghe</h4>
        <p class="text-center text-muted">Hệ thống quản lý sản xuất</p>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Tên đăng nhập</label>
                <input type="text" name="username" class="form-control" value="<?= e($_POST['username'] ?? '') ?>" autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Mật khẩu</label>
                <input type="password" name="password" class="form-control">
            </div>
            <button class="btn btn-primary w-100">Đăng nhập</button>
        </form>
        <p class="small text-muted mt-3 mb-0">Demo: <b>admin / admin123</b> hoặc <b>worker / 123456</b></p>
    </div>
</div>
</body>
</html>
