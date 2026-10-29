<?php
// FILE: warehouse.php - Kho thành phẩm: nhập kho sản phẩm đạt của lệnh đã hoàn thành
require 'config/database.php';
require 'includes/auth.php';
require_login();
$page_title = 'Kho thành phẩm';

// Câu SQL lấy số lượng còn có thể nhập kho của từng lệnh đã hoàn thành
// = (đã sản xuất - lỗi) - tổng đã nhập kho trước đó
$avail_sql = "SELECT o.id, o.order_code, o.product_id, p.name AS product_name,
                     (o.produced_qty - o.defect_qty) - COALESCE((SELECT SUM(w.quantity) FROM warehouse w WHERE w.order_id = o.id), 0) AS available
              FROM production_orders o JOIN products p ON p.id = o.product_id
              WHERE o.status = 'Completed'";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();   // chỉ Admin được nhập/xóa kho
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM warehouse WHERE id = ?')->execute([(int)$_POST['id']]);
        set_flash('success', 'Đã xóa phiếu nhập kho thành công.');
        redirect('warehouse.php');
    }

    if ($action === 'save') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $qty      = $_POST['quantity'] ?? '';
        $location = trim($_POST['location'] ?? '');
        $date     = $_POST['import_date'] ?? '';

        $s = $pdo->prepare($avail_sql . ' AND o.id = ?');
        $s->execute([$order_id]);
        $order = $s->fetch();

        if (!$order) set_flash('danger', 'Vui lòng chọn lệnh sản xuất đã hoàn thành.');
        elseif (!ctype_digit((string)$qty) || (int)$qty < 1) set_flash('danger', 'Số lượng nhập kho phải là số nguyên dương.');
        elseif ((int)$qty > $order['available']) set_flash('danger', 'Vượt quá số lượng còn có thể nhập kho (' . $order['available'] . ').');
        elseif ($location === '' || $date === '') set_flash('danger', 'Vui lòng nhập vị trí kho và ngày nhập.');
        else {
            $pdo->prepare('INSERT INTO warehouse (product_id, order_id, quantity, import_date, location) VALUES (?,?,?,?,?)')
                ->execute([$order['product_id'], $order_id, (int)$qty, $date, $location]);
            set_flash('success', 'Đã nhập kho thành phẩm thành công.');
        }
        redirect('warehouse.php');
    }
}

$importable = $pdo->query($avail_sql . ' HAVING available > 0 ORDER BY o.id')->fetchAll();
$items = $pdo->query("SELECT w.*, p.code AS pcode, p.name AS pname, o.order_code
                      FROM warehouse w JOIN products p ON p.id = w.product_id
                      LEFT JOIN production_orders o ON o.id = w.order_id ORDER BY w.id DESC")->fetchAll();
// Tồn kho theo sản phẩm
$summary = $pdo->query("SELECT p.code, p.name, COALESCE(SUM(w.quantity),0) AS total
                        FROM products p LEFT JOIN warehouse w ON w.product_id = p.id
                        GROUP BY p.id, p.code, p.name ORDER BY p.code")->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="row g-3 mb-3">
<?php foreach ($summary as $s): ?>
    <div class="col-6 col-lg-3"><div class="stat-card green"><div class="num"><?= number_format($s['total']) ?></div>
        <div class="label"><?= e($s['code'] . ' - ' . $s['name']) ?></div></div></div>
<?php endforeach; ?>
</div>

<?php if (is_admin()): ?>
<div class="box">
    <h6>Nhập kho thành phẩm</h6>
    <?php if (!$importable): ?>
        <p class="text-muted mb-0">Hiện không có lệnh sản xuất hoàn thành nào còn sản phẩm chưa nhập kho.</p>
    <?php else: ?>
    <form method="post" class="row g-3">
        <input type="hidden" name="action" value="save">
        <div class="col-md-4"><label class="form-label">Lệnh đã hoàn thành</label>
            <select name="order_id" class="form-select" required>
                <option value="">-- Chọn lệnh --</option>
                <?php foreach ($importable as $o): ?>
                    <option value="<?= $o['id'] ?>"><?= e($o['order_code'] . ' - ' . $o['product_name'] . ' (còn nhập được ' . $o['available'] . ')') ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-2"><label class="form-label">Số lượng nhập</label>
            <input name="quantity" type="number" min="1" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Vị trí kho</label>
            <input name="location" class="form-control" required placeholder="Ví dụ: Kệ A1"></div>
        <div class="col-md-3"><label class="form-label">Ngày nhập</label>
            <input name="import_date" type="date" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
        <div class="col-12"><button class="btn btn-primary">Nhập kho</button></div>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="box">
    <h6>Lịch sử nhập kho</h6>
    <div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>Mã SP</th><th>Tên sản phẩm</th><th>Lệnh SX</th><th>Số lượng nhập</th><th>Ngày nhập</th><th>Vị trí kho</th><?php if (is_admin()): ?><th>Thao tác</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($items as $i): ?>
            <tr>
                <td><b><?= e($i['pcode']) ?></b></td>
                <td><?= e($i['pname']) ?></td>
                <td><?= e($i['order_code']) ?></td>
                <td><?= number_format($i['quantity']) ?></td>
                <td><?= fdate($i['import_date']) ?></td>
                <td><?= e($i['location']) ?></td>
                <?php if (is_admin()): ?>
                <td>
                    <form method="post" class="d-inline form-delete">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $i['id'] ?>">
                        <button class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="7" class="text-center text-muted">Kho trống</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
