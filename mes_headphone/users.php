<?php
// FILE: users.php - Quản lý người dùng (chỉ Admin)
require 'config/database.php';
require 'includes/auth.php';
require_admin();
$page_title = 'Quản lý người dùng';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === (int)$_SESSION['user']['id']) {
            set_flash('danger', 'Bạn không thể tự xóa tài khoản đang đăng nhập.');
        } else {
            try {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                set_flash('success', 'Đã xóa người dùng thành công.');
            } catch (PDOException $ex) {
                set_flash('danger', 'Không thể xóa người dùng này.');
            }
        }
        redirect('users.php');
    }

    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $username  = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password  = $_POST['password'] ?? '';
        $role      = $_POST['role'] ?? 'worker';

        $errors = [];
        if ($username === '' || $full_name === '') $errors[] = 'Vui lòng nhập tên đăng nhập và họ tên.';
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) $errors[] = 'Tên đăng nhập 3-30 ký tự, chỉ gồm chữ không dấu, số và dấu gạch dưới.';
        if (!in_array($role, ['admin', 'worker'])) $errors[] = 'Quyền không hợp lệ.';
        if ($id === 0 && strlen($password) < 6) $errors[] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
        if ($id > 0 && $password !== '' && strlen($password) < 6) $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
        // Không cho Admin tự hạ quyền chính mình (tránh mất quyền quản trị)
        if ($id === (int)$_SESSION['user']['id'] && $role !== 'admin') $errors[] = 'Bạn không thể tự hạ quyền của chính mình.';

        if (!$errors) {
            try {
                if ($id > 0) {
                    if ($password !== '') {
                        // password_hash: mã hóa mật khẩu trước khi lưu
                        $pdo->prepare('UPDATE users SET username=?, full_name=?, role=?, password=? WHERE id=?')
                            ->execute([$username, $full_name, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
                    } else {
                        $pdo->prepare('UPDATE users SET username=?, full_name=?, role=? WHERE id=?')
                            ->execute([$username, $full_name, $role, $id]);
                    }
                    set_flash('success', 'Đã cập nhật người dùng thành công.');
                } else {
                    $pdo->prepare('INSERT INTO users (username, password, full_name, role) VALUES (?,?,?,?)')
                        ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $full_name, $role]);
                    set_flash('success', 'Đã thêm người dùng thành công.');
                }
                redirect('users.php');
            } catch (PDOException $ex) {
                $errors[] = 'Tên đăng nhập "' . $username . '" đã tồn tại.';
            }
        }
        set_flash('danger', implode(' ', $errors));
        redirect($id > 0 ? 'users.php?edit=' . $id : 'users.php');
    }
}

$users = $pdo->query('SELECT id, username, full_name, role, created_at FROM users ORDER BY id')->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT id, username, full_name, role FROM users WHERE id = ?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="box">
    <h6><?= $edit ? 'Sửa người dùng' : 'Thêm người dùng' ?></h6>
    <form method="post" class="row g-3">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
        <div class="col-md-3"><label class="form-label">Tên đăng nhập</label>
            <input name="username" class="form-control" required value="<?= e($edit['username'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Họ tên</label>
            <input name="full_name" class="form-control" required value="<?= e($edit['full_name'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Mật khẩu <?= $edit ? '(để trống nếu không đổi)' : '' ?></label>
            <input name="password" type="password" class="form-control" <?= $edit ? '' : 'required' ?>></div>
        <div class="col-md-3"><label class="form-label">Quyền</label>
            <select name="role" class="form-select">
                <option value="worker" <?= ($edit['role'] ?? '') === 'worker' ? 'selected' : '' ?>>Worker</option>
                <option value="admin" <?= ($edit['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select></div>
        <div class="col-12">
            <button class="btn btn-primary"><?= $edit ? 'Cập nhật' : 'Thêm mới' ?></button>
            <?php if ($edit): ?><a href="users.php" class="btn btn-secondary">Hủy</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="box">
    <h6>Danh sách người dùng</h6>
    <table class="table table-hover align-middle">
        <thead><tr><th>ID</th><th>Tên đăng nhập</th><th>Họ tên</th><th>Quyền</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><b><?= e($u['username']) ?></b></td>
                <td><?= e($u['full_name']) ?></td>
                <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'primary' : 'secondary' ?>"><?= e(strtoupper($u['role'])) ?></span></td>
                <td><?= fdate($u['created_at']) ?></td>
                <td class="text-nowrap">
                    <a href="users.php?edit=<?= $u['id'] ?>" class="btn btn-sm btn-warning">Sửa</a>
                    <form method="post" class="d-inline form-delete">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <button class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include 'includes/footer.php'; ?>
