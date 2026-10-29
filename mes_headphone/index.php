<?php
// FILE: index.php - Dashboard: thẻ thống kê + lệnh sản xuất gần đây
require 'config/database.php';
require 'includes/auth.php';
require_login();
$page_title = 'Dashboard';

// Các số liệu thống kê (không có tham số người dùng nên dùng query() được)
$total_orders  = $pdo->query("SELECT COUNT(*) FROM production_orders")->fetchColumn();
$running       = $pdo->query("SELECT COUNT(*) FROM production_orders WHERE status='In Production'")->fetchColumn();
$good_total    = $pdo->query("SELECT COALESCE(SUM(produced_qty - defect_qty),0) FROM production_orders")->fetchColumn();
$defect_total  = $pdo->query("SELECT COALESCE(SUM(defect_qty),0) FROM production_orders")->fetchColumn();
$material_cnt  = $pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();
$stock_total   = $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM warehouse")->fetchColumn();

// 8 lệnh sản xuất mới nhất
$recent = $pdo->query("SELECT o.*, p.name AS product_name
                       FROM production_orders o JOIN products p ON p.id = o.product_id
                       ORDER BY o.id DESC LIMIT 8")->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4 col-xl-2"><div class="stat-card"><div class="num"><?= $total_orders ?></div><div class="label">Tổng lệnh sản xuất</div></div></div>
    <div class="col-6 col-lg-4 col-xl-2"><div class="stat-card yellow"><div class="num"><?= $running ?></div><div class="label">Đang sản xuất</div></div></div>
    <div class="col-6 col-lg-4 col-xl-2"><div class="stat-card green"><div class="num"><?= number_format($good_total) ?></div><div class="label">SP đã hoàn thành (đạt)</div></div></div>
    <div class="col-6 col-lg-4 col-xl-2"><div class="stat-card red"><div class="num"><?= number_format($defect_total) ?></div><div class="label">SP lỗi</div></div></div>
    <div class="col-6 col-lg-4 col-xl-2"><div class="stat-card gray"><div class="num"><?= $material_cnt ?></div><div class="label">Nguyên vật liệu</div></div></div>
    <div class="col-6 col-lg-4 col-xl-2"><div class="stat-card"><div class="num"><?= number_format($stock_total) ?></div><div class="label">SP trong kho</div></div></div>
</div>

<div class="box">
    <h6>Lệnh sản xuất gần đây</h6>
    <div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>Mã lệnh</th><th>Sản phẩm</th><th>Số lượng</th><th>Đã sản xuất</th><th>Lỗi</th><th>Trạng thái</th><th>Ngày bắt đầu</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
            <tr>
                <td><b><?= e($r['order_code']) ?></b></td>
                <td><?= e($r['product_name']) ?></td>
                <td><?= number_format($r['quantity']) ?></td>
                <td><?= number_format($r['produced_qty']) ?></td>
                <td class="text-danger"><?= number_format($r['defect_qty']) ?></td>
                <td><?= status_badge($r['status']) ?></td>
                <td><?= fdate($r['start_date']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="7" class="text-center text-muted">Chưa có lệnh sản xuất</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
