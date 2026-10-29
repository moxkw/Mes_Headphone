<?php
// FILE: production_orders.php - Lệnh sản xuất: Admin tạo/sửa/xóa, Worker chỉ xem
require 'config/database.php';
require 'includes/auth.php';
require_login();
$page_title = 'Lệnh sản xuất';

$statuses = ['Planned', 'In Production', 'Completed', 'Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try {
            $pdo->prepare('DELETE FROM production_orders WHERE id = ?')->execute([(int)$_POST['id']]);
            set_flash('success', 'Đã xóa lệnh sản xuất thành công.');
        } catch (PDOException $ex) {
            set_flash('danger', 'Không thể xóa lệnh sản xuất này.');
        }
        redirect('production_orders.php');
    }

    if ($action === 'save') {
        $id         = (int)($_POST['id'] ?? 0);
        $code       = trim($_POST['order_code'] ?? '');
        $product_id = (int)($_POST['product_id'] ?? 0);
        $quantity   = $_POST['quantity'] ?? '';
        $start      = $_POST['start_date'] ?? '';
        $end        = $_POST['expected_end_date'] ?? '';
        $manager    = trim($_POST['manager'] ?? '');
        $status     = $_POST['status'] ?? 'Planned';

        $errors = [];
        if ($code === '' || $manager === '' || $product_id <= 0) $errors[] = 'Vui lòng nhập mã lệnh, chọn sản phẩm và người phụ trách.';
        if (!ctype_digit((string)$quantity) || (int)$quantity < 1) $errors[] = 'Số lượng phải là số nguyên dương.';
        if ($start === '' || $end === '') $errors[] = 'Vui lòng chọn ngày bắt đầu và ngày dự kiến hoàn thành.';
        elseif ($end < $start) $errors[] = 'Ngày dự kiến hoàn thành phải sau ngày bắt đầu.';
        if (!in_array($status, $statuses)) $errors[] = 'Trạng thái không hợp lệ.';

        if (!$errors) {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE production_orders SET order_code=?, product_id=?, quantity=?, start_date=?, expected_end_date=?, manager=?, status=? WHERE id=?')
                        ->execute([$code, $product_id, $quantity, $start, $end, $manager, $status, $id]);
                    set_flash('success', 'Đã cập nhật lệnh sản xuất thành công.');
                } else {
                    $pdo->prepare('INSERT INTO production_orders (order_code, product_id, quantity, start_date, expected_end_date, manager, status) VALUES (?,?,?,?,?,?,?)')
                        ->execute([$code, $product_id, $quantity, $start, $end, $manager, $status]);
                    set_flash('success', 'Đã tạo lệnh sản xuất thành công.');
                }
                redirect('production_orders.php');
            } catch (PDOException $ex) {
                $errors[] = 'Mã lệnh "' . $code . '" đã tồn tại.';
            }
        }
        set_flash('danger', implode(' ', $errors));
        redirect($id > 0 ? 'production_orders.php?edit=' . $id : 'production_orders.php');
    }
}

$products = $pdo->query("SELECT id, code, name FROM products WHERE status='Active' ORDER BY code")->fetchAll();

// Lọc theo trạng thái
$filter = $_GET['status'] ?? '';
$sql = 'SELECT o.*, p.name AS product_name FROM production_orders o JOIN products p ON p.id = o.product_id';
$params = [];
if (in_array($filter, $statuses)) { $sql .= ' WHERE o.status = ?'; $params[] = $filter; }
$sql .= ' ORDER BY o.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$edit = null;
if (is_admin() && isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM production_orders WHERE id = ?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}
// Gợi ý mã lệnh tiếp theo: MO + số thứ tự
$next_code = 'MO' . str_pad($pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM production_orders')->fetchColumn(), 3, '0', STR_PAD_LEFT);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<?php if (is_admin()): ?>
<div class="box">
    <h6><?= $edit ? 'Sửa lệnh sản xuất' : 'Tạo lệnh sản xuất mới' ?></h6>
    <form method="post" class="row g-3">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
        <div class="col-md-2"><label class="form-label">Mã lệnh</label>
            <input name="order_code" class="form-control" required value="<?= e($edit['order_code'] ?? $next_code) ?>"></div>
        <div class="col-md-4"><label class="form-label">Sản phẩm</label>
            <select name="product_id" class="form-select" required>
                <option value="">-- Chọn sản phẩm --</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($edit['product_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>><?= e($p['code'] . ' - ' . $p['name']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-2"><label class="form-label">Số lượng</label>
            <input name="quantity" type="number" min="1" class="form-control" required value="<?= e($edit['quantity'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Ngày bắt đầu</label>
            <input name="start_date" type="date" class="form-control" required value="<?= e($edit['start_date'] ?? date('Y-m-d')) ?>"></div>
        <div class="col-md-2"><label class="form-label">Dự kiến hoàn thành</label>
            <input name="expected_end_date" type="date" class="form-control" required value="<?= e($edit['expected_end_date'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Người phụ trách</label>
            <input name="manager" class="form-control" required value="<?= e($edit['manager'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Trạng thái</label>
            <select name="status" class="form-select">
                <?php foreach ($statuses as $s): ?>
                    <option <?= ($edit['status'] ?? 'Planned') === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-12">
            <button class="btn btn-primary"><?= $edit ? 'Cập nhật' : 'Tạo lệnh' ?></button>
            <?php if ($edit): ?><a href="production_orders.php" class="btn btn-secondary">Hủy</a><?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="box">
    <form method="get" class="d-flex gap-2 mb-3">
        <select name="status" class="form-select w-auto" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            <?php foreach ($statuses as $s): ?><option <?= $filter === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
        </select>
    </form>
    <div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>Mã lệnh</th><th>Sản phẩm</th><th>Số lượng</th><th>Bắt đầu</th><th>Dự kiến xong</th><th>Phụ trách</th><th>Trạng thái</th><?php if (is_admin()): ?><th>Thao tác</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><b><?= e($o['order_code']) ?></b></td>
                <td><?= e($o['product_name']) ?></td>
                <td><?= number_format($o['quantity']) ?></td>
                <td><?= fdate($o['start_date']) ?></td>
                <td><?= fdate($o['expected_end_date']) ?></td>
                <td><?= e($o['manager']) ?></td>
                <td><?= status_badge($o['status']) ?></td>
                <?php if (is_admin()): ?>
                <td class="text-nowrap">
                    <a href="production_orders.php?edit=<?= $o['id'] ?>" class="btn btn-sm btn-warning">Sửa</a>
                    <form method="post" class="d-inline form-delete">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $o['id'] ?>">
                        <button class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="8" class="text-center text-muted">Không có lệnh sản xuất</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
