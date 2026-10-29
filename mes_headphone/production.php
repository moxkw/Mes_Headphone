<?php
// FILE: production.php - Theo dõi tiến độ + nhập sản lượng (Admin và Worker đều dùng được)
require 'config/database.php';
require 'includes/auth.php';
require_login();
$page_title = 'Theo dõi sản xuất';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $produced = $_POST['produced_qty'] ?? '';
    $good     = $_POST['good_qty'] ?? '';
    $defect   = $_POST['defect_qty'] ?? '';
    $note     = trim($_POST['note'] ?? '');

    // Lấy lệnh sản xuất
    $s = $pdo->prepare('SELECT * FROM production_orders WHERE id = ?');
    $s->execute([$order_id]);
    $order = $s->fetch();

    $error = '';
    if (!$order) $error = 'Vui lòng chọn lệnh sản xuất.';
    elseif (!in_array($order['status'], ['Planned', 'In Production'])) $error = 'Lệnh này đã hoàn thành hoặc đã hủy, không thể nhập sản lượng.';
    elseif (!ctype_digit((string)$produced) || !ctype_digit((string)$good) || !ctype_digit((string)$defect)) $error = 'Các số lượng phải là số nguyên không âm.';
    elseif ((int)$produced < 1) $error = 'Số lượng sản xuất phải lớn hơn 0.';
    elseif ((int)$good + (int)$defect !== (int)$produced) $error = 'Số lượng đạt + số lượng lỗi phải bằng số lượng sản xuất.';
    elseif ($order['produced_qty'] + (int)$produced > $order['quantity']) $error = 'Vượt quá số lượng kế hoạch (còn lại ' . ($order['quantity'] - $order['produced_qty']) . ').';

    if ($error) {
        set_flash('danger', $error);
    } else {
        $produced = (int)$produced; $good = (int)$good; $defect = (int)$defect;
        try {
            $pdo->beginTransaction();   // các bước dưới phải cùng thành công hoặc cùng thất bại

            // 1. Ghi nhật ký sản xuất
            $pdo->prepare('INSERT INTO production_logs (order_id, produced_qty, good_qty, defect_qty, log_date, user_id, note) VALUES (?,?,?,?,CURDATE(),?,?)')
                ->execute([$order_id, $produced, $good, $defect, $_SESSION['user']['id'], $note]);

            // 2. Cập nhật tiến độ lệnh: cộng dồn sản lượng, tự đổi trạng thái
            $new_total = $order['produced_qty'] + $produced;
            $new_status = ($new_total >= $order['quantity']) ? 'Completed' : 'In Production';
            $pdo->prepare('UPDATE production_orders SET produced_qty = produced_qty + ?, defect_qty = defect_qty + ?, status = ? WHERE id = ?')
                ->execute([$produced, $defect, $new_status, $order_id]);

            // 3. Trừ tồn kho nguyên vật liệu theo BOM (không cho âm)
            $pdo->prepare('UPDATE materials m JOIN bom b ON b.material_id = m.id
                           SET m.stock_qty = GREATEST(0, m.stock_qty - b.quantity * ?)
                           WHERE b.product_id = ?')
                ->execute([$produced, $order['product_id']]);

            $pdo->commit();
            set_flash('success', 'Đã cập nhật sản lượng thành công.' . ($new_status === 'Completed' ? ' Lệnh ' . $order['order_code'] . ' đã hoàn thành!' : ''));
        } catch (PDOException $ex) {
            $pdo->rollBack();
            set_flash('danger', 'Lỗi khi cập nhật sản lượng: ' . $ex->getMessage());
        }
    }
    redirect('production.php');
}

// Lệnh đang theo dõi (chưa hoàn thành/hủy) để hiển thị tiến độ + chọn nhập sản lượng
$active = $pdo->query("SELECT o.*, p.name AS product_name FROM production_orders o
                       JOIN products p ON p.id = o.product_id
                       WHERE o.status IN ('Planned','In Production') ORDER BY o.id")->fetchAll();
// Tất cả lệnh để xem tiến độ
$all = $pdo->query("SELECT o.*, p.name AS product_name FROM production_orders o
                    JOIN products p ON p.id = o.product_id ORDER BY o.id DESC")->fetchAll();
// 15 lần nhập sản lượng gần nhất
$logs = $pdo->query("SELECT l.*, o.order_code, u.full_name FROM production_logs l
                     JOIN production_orders o ON o.id = l.order_id
                     LEFT JOIN users u ON u.id = l.user_id
                     ORDER BY l.id DESC LIMIT 15")->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="box">
    <h6>Nhập sản lượng</h6>
    <form method="post" class="row g-3">
        <div class="col-md-4"><label class="form-label">Lệnh sản xuất</label>
            <select name="order_id" class="form-select" required>
                <option value="">-- Chọn lệnh --</option>
                <?php foreach ($active as $o): ?>
                    <option value="<?= $o['id'] ?>"><?= e($o['order_code'] . ' - ' . $o['product_name'] . ' (' . $o['produced_qty'] . '/' . $o['quantity'] . ')') ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-2"><label class="form-label">SL sản xuất</label>
            <input name="produced_qty" id="produced" type="number" min="1" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">SL đạt</label>
            <input name="good_qty" id="good" type="number" min="0" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">SL lỗi</label>
            <input name="defect_qty" id="defect" type="number" min="0" class="form-control" required></div>
        <div class="col-md-12"><label class="form-label">Ghi chú</label>
            <input name="note" class="form-control" maxlength="255"></div>
        <div class="col-12"><button class="btn btn-primary">Cập nhật sản lượng</button>
            <small class="text-muted ms-2">Lưu ý: SL đạt + SL lỗi = SL sản xuất. Hệ thống tự trừ nguyên vật liệu theo BOM.</small></div>
    </form>
</div>

<div class="box">
    <h6>Tiến độ các lệnh sản xuất</h6>
    <div class="table-responsive">
    <table class="table align-middle">
        <thead><tr><th>Lệnh</th><th>Sản phẩm</th><th>Kế hoạch</th><th>Đã sản xuất</th><th>Lỗi</th><th>Đạt</th><th style="min-width:160px">Tiến độ</th><th>Trạng thái</th></tr></thead>
        <tbody>
        <?php foreach ($all as $o): ?>
            <tr>
                <td><b><?= e($o['order_code']) ?></b></td>
                <td><?= e($o['product_name']) ?></td>
                <td><?= number_format($o['quantity']) ?></td>
                <td><?= number_format($o['produced_qty']) ?></td>
                <td class="text-danger"><?= number_format($o['defect_qty']) ?></td>
                <td class="text-success"><?= number_format($o['produced_qty'] - $o['defect_qty']) ?></td>
                <td><?= progress_bar($o['produced_qty'], $o['quantity']) ?></td>
                <td><?= status_badge($o['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="box">
    <h6>Nhật ký sản xuất gần đây</h6>
    <div class="table-responsive">
    <table class="table table-sm">
        <thead><tr><th>Ngày</th><th>Lệnh</th><th>Sản xuất</th><th>Đạt</th><th>Lỗi</th><th>Người nhập</th><th>Ghi chú</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr><td><?= fdate($l['log_date']) ?></td><td><?= e($l['order_code']) ?></td><td><?= $l['produced_qty'] ?></td>
                <td class="text-success"><?= $l['good_qty'] ?></td><td class="text-danger"><?= $l['defect_qty'] ?></td>
                <td><?= e($l['full_name']) ?></td><td><?= e($l['note']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<script>
// Gợi ý: nhập SL sản xuất và SL lỗi thì tự tính SL đạt
var p = document.getElementById('produced'), g = document.getElementById('good'), d = document.getElementById('defect');
function tinhDat() { if (p.value !== '' && d.value !== '') g.value = Math.max(0, p.value - d.value); }
p.addEventListener('input', tinhDat); d.addEventListener('input', tinhDat);
</script>
<?php include 'includes/footer.php'; ?>
