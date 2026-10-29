<?php
// FILE: quality.php - Kiểm tra chất lượng (QC): Admin và Worker thêm/sửa, chỉ Admin xóa
require 'config/database.php';
require 'includes/auth.php';
require_login();
$page_title = 'Kiểm tra chất lượng (QC)';

$defect_types = ['Không bật nguồn', 'Không kết nối Bluetooth', 'Âm thanh bị rè', 'Micro không hoạt động', 'Pin lỗi', 'Vỏ bị trầy', 'Sạc không vào'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        require_admin();   // Worker không được xóa
        $pdo->prepare('DELETE FROM quality_checks WHERE id = ?')->execute([(int)$_POST['id']]);
        set_flash('success', 'Đã xóa phiếu QC thành công.');
        redirect('quality.php');
    }

    if ($action === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $code     = trim($_POST['check_code'] ?? '');
        $order_id = (int)($_POST['order_id'] ?? 0);
        $checked  = $_POST['checked_qty'] ?? '';
        $good     = $_POST['good_qty'] ?? '';
        $defect   = $_POST['defect_qty'] ?? '';
        $dtype    = trim($_POST['defect_type'] ?? '');
        $inspector= trim($_POST['inspector'] ?? '');
        $date     = $_POST['check_date'] ?? '';
        $note     = trim($_POST['note'] ?? '');

        // Lấy sản phẩm từ lệnh sản xuất
        $s = $pdo->prepare('SELECT product_id FROM production_orders WHERE id = ?');
        $s->execute([$order_id]);
        $order = $s->fetch();

        $errors = [];
        if ($code === '' || $inspector === '' || $date === '') $errors[] = 'Vui lòng nhập mã kiểm tra, người kiểm tra và ngày kiểm tra.';
        if (!$order) $errors[] = 'Vui lòng chọn lệnh sản xuất.';
        if (!ctype_digit((string)$checked) || !ctype_digit((string)$good) || !ctype_digit((string)$defect)) $errors[] = 'Các số lượng phải là số nguyên không âm.';
        elseif ((int)$checked < 1) $errors[] = 'Số lượng kiểm tra phải lớn hơn 0.';
        elseif ((int)$good + (int)$defect !== (int)$checked) $errors[] = 'Số lượng đạt + lỗi phải bằng số lượng kiểm tra.';
        elseif ((int)$defect > 0 && !in_array($dtype, $defect_types)) $errors[] = 'Có sản phẩm lỗi thì phải chọn loại lỗi.';

        if (!$errors) {
            $rate = round($defect / $checked * 100, 2);          // tỷ lệ lỗi (%)
            if ((int)$defect === 0) $dtype = '';
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE quality_checks SET check_code=?, order_id=?, product_id=?, checked_qty=?, good_qty=?, defect_qty=?, defect_rate=?, defect_type=?, inspector=?, check_date=?, note=? WHERE id=?')
                        ->execute([$code, $order_id, $order['product_id'], $checked, $good, $defect, $rate, $dtype, $inspector, $date, $note, $id]);
                    set_flash('success', 'Đã cập nhật phiếu QC thành công.');
                } else {
                    $pdo->prepare('INSERT INTO quality_checks (check_code, order_id, product_id, checked_qty, good_qty, defect_qty, defect_rate, defect_type, inspector, check_date, note) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$code, $order_id, $order['product_id'], $checked, $good, $defect, $rate, $dtype, $inspector, $date, $note]);
                    set_flash('success', 'Đã lưu kết quả kiểm tra QC thành công.');
                }
                redirect('quality.php');
            } catch (PDOException $ex) {
                $errors[] = 'Mã kiểm tra "' . $code . '" đã tồn tại.';
            }
        }
        set_flash('danger', implode(' ', $errors));
        redirect($id > 0 ? 'quality.php?edit=' . $id : 'quality.php');
    }
}

$orders = $pdo->query("SELECT o.id, o.order_code, p.name FROM production_orders o JOIN products p ON p.id = o.product_id
                       WHERE o.status <> 'Cancelled' ORDER BY o.id DESC")->fetchAll();
$checks = $pdo->query("SELECT q.*, o.order_code, p.name AS product_name FROM quality_checks q
                       JOIN production_orders o ON o.id = q.order_id JOIN products p ON p.id = q.product_id
                       ORDER BY q.id DESC")->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM quality_checks WHERE id = ?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}
$next_code = 'QC' . str_pad($pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM quality_checks')->fetchColumn(), 3, '0', STR_PAD_LEFT);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="box">
    <h6><?= $edit ? 'Sửa phiếu QC' : 'Tạo phiếu kiểm tra QC' ?></h6>
    <form method="post" class="row g-3">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
        <div class="col-md-2"><label class="form-label">Mã kiểm tra</label>
            <input name="check_code" class="form-control" required value="<?= e($edit['check_code'] ?? $next_code) ?>"></div>
        <div class="col-md-4"><label class="form-label">Lệnh sản xuất</label>
            <select name="order_id" class="form-select" required>
                <option value="">-- Chọn lệnh --</option>
                <?php foreach ($orders as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= ($edit['order_id'] ?? 0) == $o['id'] ? 'selected' : '' ?>><?= e($o['order_code'] . ' - ' . $o['name']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-2"><label class="form-label">SL kiểm tra</label>
            <input name="checked_qty" id="checked" type="number" min="1" class="form-control" required value="<?= e($edit['checked_qty'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">SL đạt</label>
            <input name="good_qty" id="good" type="number" min="0" class="form-control" required value="<?= e($edit['good_qty'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">SL lỗi</label>
            <input name="defect_qty" id="defect" type="number" min="0" class="form-control" required value="<?= e($edit['defect_qty'] ?? 0) ?>"></div>
        <div class="col-md-4"><label class="form-label">Loại lỗi (nếu có lỗi)</label>
            <select name="defect_type" class="form-select">
                <option value="">-- Không có lỗi --</option>
                <?php foreach ($defect_types as $t): ?>
                    <option <?= ($edit['defect_type'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-3"><label class="form-label">Người kiểm tra</label>
            <input name="inspector" class="form-control" required value="<?= e($edit['inspector'] ?? $_SESSION['user']['full_name']) ?>"></div>
        <div class="col-md-2"><label class="form-label">Ngày kiểm tra</label>
            <input name="check_date" type="date" class="form-control" required value="<?= e($edit['check_date'] ?? date('Y-m-d')) ?>"></div>
        <div class="col-md-3"><label class="form-label">Ghi chú</label>
            <input name="note" class="form-control" maxlength="255" value="<?= e($edit['note'] ?? '') ?>"></div>
        <div class="col-12">
            <button class="btn btn-primary"><?= $edit ? 'Cập nhật' : 'Lưu kết quả' ?></button>
            <?php if ($edit): ?><a href="quality.php" class="btn btn-secondary">Hủy</a><?php endif; ?>
            <small class="text-muted ms-2">Tỷ lệ lỗi được tự động tính khi lưu.</small>
        </div>
    </form>
</div>

<div class="box">
    <h6>Lịch sử kiểm tra</h6>
    <div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>Mã QC</th><th>Lệnh</th><th>Sản phẩm</th><th>Kiểm tra</th><th>Đạt</th><th>Lỗi</th><th>Tỷ lệ lỗi</th><th>Loại lỗi</th><th>Người KT</th><th>Ngày</th><th>Ghi chú</th><th>Thao tác</th></tr></thead>
        <tbody>
        <?php foreach ($checks as $c): ?>
            <tr>
                <td><b><?= e($c['check_code']) ?></b></td>
                <td><?= e($c['order_code']) ?></td>
                <td><?= e($c['product_name']) ?></td>
                <td><?= $c['checked_qty'] ?></td>
                <td class="text-success"><?= $c['good_qty'] ?></td>
                <td class="text-danger"><?= $c['defect_qty'] ?></td>
                <td><span class="badge bg-<?= $c['defect_rate'] > 5 ? 'danger' : ($c['defect_rate'] > 0 ? 'warning text-dark' : 'success') ?>"><?= $c['defect_rate'] ?>%</span></td>
                <td><?= e($c['defect_type']) ?></td>
                <td><?= e($c['inspector']) ?></td>
                <td><?= fdate($c['check_date']) ?></td>
                <td><?= e($c['note']) ?></td>
                <td class="text-nowrap">
                    <a href="quality.php?edit=<?= $c['id'] ?>" class="btn btn-sm btn-warning">Sửa</a>
                    <?php if (is_admin()): ?>
                    <form method="post" class="d-inline form-delete">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$checks): ?><tr><td colspan="12" class="text-center text-muted">Chưa có phiếu QC</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<script>
// Gợi ý: nhập SL kiểm tra và SL lỗi thì tự tính SL đạt
var c = document.getElementById('checked'), g = document.getElementById('good'), d = document.getElementById('defect');
function tinhDat() { if (c.value !== '' && d.value !== '') g.value = Math.max(0, c.value - d.value); }
c.addEventListener('input', tinhDat); d.addEventListener('input', tinhDat);
</script>
<?php include 'includes/footer.php'; ?>
