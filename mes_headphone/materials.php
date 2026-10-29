<?php
// FILE: materials.php - Quản lý nguyên vật liệu, cảnh báo "Sắp hết hàng"
require 'config/database.php';
require 'includes/auth.php';
require_login();
$page_title = 'Quản lý nguyên vật liệu';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try {
            $pdo->prepare('DELETE FROM materials WHERE id = ?')->execute([(int)$_POST['id']]);
            set_flash('success', 'Đã xóa nguyên vật liệu thành công.');
        } catch (PDOException $ex) {
            set_flash('danger', 'Không thể xóa: vật liệu đang được dùng trong BOM.');
        }
        redirect('materials.php');
    }

    if ($action === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $code     = trim($_POST['code'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $unit     = trim($_POST['unit'] ?? '');
        $stock    = $_POST['stock_qty'] ?? '';
        $min      = $_POST['min_qty'] ?? '';
        $supplier = trim($_POST['supplier'] ?? '');

        $errors = [];
        if ($code === '' || $name === '' || $unit === '') $errors[] = 'Vui lòng nhập đủ mã, tên và đơn vị.';
        if (!ctype_digit((string)$stock) || !ctype_digit((string)$min)) $errors[] = 'Số lượng tồn kho và tối thiểu phải là số nguyên không âm.';

        if (!$errors) {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE materials SET code=?, name=?, unit=?, stock_qty=?, min_qty=?, supplier=? WHERE id=?')
                        ->execute([$code, $name, $unit, $stock, $min, $supplier, $id]);
                    set_flash('success', 'Đã cập nhật nguyên vật liệu thành công.');
                } else {
                    $pdo->prepare('INSERT INTO materials (code, name, unit, stock_qty, min_qty, supplier) VALUES (?,?,?,?,?,?)')
                        ->execute([$code, $name, $unit, $stock, $min, $supplier]);
                    set_flash('success', 'Đã thêm nguyên vật liệu thành công.');
                }
                redirect('materials.php');
            } catch (PDOException $ex) {
                $errors[] = 'Mã vật liệu "' . $code . '" đã tồn tại.';
            }
        }
        set_flash('danger', implode(' ', $errors));
        redirect($id > 0 ? 'materials.php?edit=' . $id : 'materials.php');
    }
}

$materials = $pdo->query('SELECT * FROM materials ORDER BY id')->fetchAll();
$low_count = 0;
foreach ($materials as $m) if ($m['stock_qty'] < $m['min_qty']) $low_count++;

$edit = null;
if (is_admin() && isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM materials WHERE id = ?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<?php if ($low_count > 0): ?>
    <div class="alert alert-danger">⚠ Có <b><?= $low_count ?></b> nguyên vật liệu đang sắp hết hàng!</div>
<?php endif; ?>

<?php if (is_admin()): ?>
<div class="box">
    <h6><?= $edit ? 'Sửa nguyên vật liệu' : 'Thêm nguyên vật liệu' ?></h6>
    <form method="post" class="row g-3">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
        <div class="col-md-2"><label class="form-label">Mã vật liệu</label>
            <input name="code" class="form-control" required value="<?= e($edit['code'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Tên vật liệu</label>
            <input name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Đơn vị</label>
            <input name="unit" class="form-control" required value="<?= e($edit['unit'] ?? '') ?>" placeholder="cái, sợi..."></div>
        <div class="col-md-2"><label class="form-label">Tồn kho</label>
            <input name="stock_qty" type="number" min="0" class="form-control" required value="<?= e($edit['stock_qty'] ?? 0) ?>"></div>
        <div class="col-md-2"><label class="form-label">Tối thiểu</label>
            <input name="min_qty" type="number" min="0" class="form-control" required value="<?= e($edit['min_qty'] ?? 0) ?>"></div>
        <div class="col-md-6"><label class="form-label">Nhà cung cấp</label>
            <input name="supplier" class="form-control" value="<?= e($edit['supplier'] ?? '') ?>"></div>
        <div class="col-12">
            <button class="btn btn-primary"><?= $edit ? 'Cập nhật' : 'Thêm mới' ?></button>
            <?php if ($edit): ?><a href="materials.php" class="btn btn-secondary">Hủy</a><?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="box">
    <h6>Danh sách nguyên vật liệu</h6>
    <div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>Mã</th><th>Tên vật liệu</th><th>Đơn vị</th><th>Tồn kho</th><th>Tối thiểu</th><th>Nhà cung cấp</th><th>Cảnh báo</th><?php if (is_admin()): ?><th>Thao tác</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($materials as $m): $low = $m['stock_qty'] < $m['min_qty']; ?>
            <tr class="<?= $low ? 'table-danger' : '' ?>">
                <td><b><?= e($m['code']) ?></b></td>
                <td><?= e($m['name']) ?></td>
                <td><?= e($m['unit']) ?></td>
                <td><?= number_format($m['stock_qty']) ?></td>
                <td><?= number_format($m['min_qty']) ?></td>
                <td><?= e($m['supplier']) ?></td>
                <td><?= $low ? '<span class="badge bg-danger">Sắp hết hàng</span>' : '<span class="badge bg-success">Đủ hàng</span>' ?></td>
                <?php if (is_admin()): ?>
                <td class="text-nowrap">
                    <a href="materials.php?edit=<?= $m['id'] ?>" class="btn btn-sm btn-warning">Sửa</a>
                    <form method="post" class="d-inline form-delete">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                        <button class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
