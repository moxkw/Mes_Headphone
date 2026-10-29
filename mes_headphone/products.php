<?php
// FILE: products.php - Quản lý sản phẩm: xem, tìm kiếm, thêm, sửa, xóa
require 'config/database.php';
require 'includes/auth.php';
require_login();
$page_title = 'Quản lý sản phẩm';

// ----- Xử lý form POST (chỉ Admin được thêm/sửa/xóa) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try {
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([(int)$_POST['id']]);
            set_flash('success', 'Đã xóa sản phẩm thành công.');
        } catch (PDOException $ex) {
            // Lỗi khóa ngoại: sản phẩm đã có lệnh sản xuất / kho
            set_flash('danger', 'Không thể xóa: sản phẩm đang được dùng trong lệnh sản xuất hoặc kho.');
        }
        redirect('products.php');
    }

    if ($action === 'save') {
        $id     = (int)($_POST['id'] ?? 0);
        $code   = trim($_POST['code'] ?? '');
        $name   = trim($_POST['name'] ?? '');
        $type   = trim($_POST['type'] ?? '');
        $color  = trim($_POST['color'] ?? '');
        $price  = $_POST['price'] ?? '';
        $status = $_POST['status'] ?? 'Active';

        // Kiểm tra dữ liệu đầu vào
        $errors = [];
        if ($code === '' || $name === '' || $type === '' || $color === '') $errors[] = 'Vui lòng nhập đủ mã, tên, loại và màu sắc.';
        if (!is_numeric($price) || $price < 0) $errors[] = 'Giá phải là số không âm.';
        if (!in_array($status, ['Active', 'Inactive'])) $errors[] = 'Trạng thái không hợp lệ.';

        if (!$errors) {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE products SET code=?, name=?, type=?, color=?, price=?, status=? WHERE id=?')
                        ->execute([$code, $name, $type, $color, $price, $status, $id]);
                    set_flash('success', 'Đã cập nhật sản phẩm thành công.');
                } else {
                    $pdo->prepare('INSERT INTO products (code, name, type, color, price, status) VALUES (?,?,?,?,?,?)')
                        ->execute([$code, $name, $type, $color, $price, $status]);
                    set_flash('success', 'Đã thêm sản phẩm thành công.');
                }
                redirect('products.php');
            } catch (PDOException $ex) {
                $errors[] = 'Mã sản phẩm "' . $code . '" đã tồn tại.';
            }
        }
        set_flash('danger', implode(' ', $errors));
        redirect($id > 0 ? 'products.php?edit=' . $id : 'products.php');
    }
}

// ----- Lấy dữ liệu để hiển thị -----
$q = trim($_GET['q'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM products WHERE code LIKE ? OR name LIKE ? OR type LIKE ? ORDER BY id');
$stmt->execute(["%$q%", "%$q%", "%$q%"]);
$products = $stmt->fetchAll();

// Nếu bấm "Sửa" thì lấy sản phẩm cần sửa đổ vào form
$edit = null;
if (is_admin() && isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<?php if (is_admin()): ?>
<div class="box">
    <h6><?= $edit ? 'Sửa sản phẩm' : 'Thêm sản phẩm mới' ?></h6>
    <form method="post" class="row g-3">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
        <div class="col-md-2"><label class="form-label">Mã sản phẩm</label>
            <input name="code" class="form-control" required value="<?= e($edit['code'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Tên sản phẩm</label>
            <input name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Loại</label>
            <input name="type" class="form-control" required value="<?= e($edit['type'] ?? '') ?>" placeholder="Bluetooth, Gaming..."></div>
        <div class="col-md-2"><label class="form-label">Màu sắc</label>
            <input name="color" class="form-control" required value="<?= e($edit['color'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Giá (VNĐ)</label>
            <input name="price" type="number" min="0" class="form-control" required value="<?= e($edit['price'] ?? 0) ?>"></div>
        <div class="col-md-2"><label class="form-label">Trạng thái</label>
            <select name="status" class="form-select">
                <?php foreach (['Active' => 'Đang bán', 'Inactive' => 'Ngừng'] as $k => $v): ?>
                    <option value="<?= $k ?>" <?= ($edit['status'] ?? 'Active') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-12">
            <button class="btn btn-primary"><?= $edit ? 'Cập nhật' : 'Thêm mới' ?></button>
            <?php if ($edit): ?><a href="products.php" class="btn btn-secondary">Hủy</a><?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="box">
    <form method="get" class="d-flex gap-2 mb-3">
        <input name="q" class="form-control" placeholder="Tìm theo mã, tên hoặc loại..." value="<?= e($q) ?>">
        <button class="btn btn-outline-primary">Tìm</button>
        <?php if ($q !== ''): ?><a href="products.php" class="btn btn-outline-secondary">Xóa lọc</a><?php endif; ?>
    </form>
    <div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>ID</th><th>Mã</th><th>Tên sản phẩm</th><th>Loại</th><th>Màu</th><th>Giá</th><th>Trạng thái</th><?php if (is_admin()): ?><th>Thao tác</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><b><?= e($p['code']) ?></b></td>
                <td><?= e($p['name']) ?></td>
                <td><?= e($p['type']) ?></td>
                <td><?= e($p['color']) ?></td>
                <td><?= number_format($p['price']) ?>đ</td>
                <td><span class="badge bg-<?= $p['status'] === 'Active' ? 'success' : 'secondary' ?>"><?= $p['status'] === 'Active' ? 'Đang bán' : 'Ngừng' ?></span></td>
                <?php if (is_admin()): ?>
                <td class="text-nowrap">
                    <a href="products.php?edit=<?= $p['id'] ?>" class="btn btn-sm btn-warning">Sửa</a>
                    <form method="post" class="d-inline form-delete">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?><tr><td colspan="8" class="text-center text-muted">Không có sản phẩm nào</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
