<?php
// FILE: reports.php - Báo cáo sản xuất + biểu đồ Chart.js
require 'config/database.php';
require 'includes/auth.php';
require_login();
$page_title = 'Báo cáo sản xuất';

// Tổng hợp
$t = $pdo->query("SELECT COALESCE(SUM(produced_qty),0) AS produced, COALESCE(SUM(defect_qty),0) AS defect FROM production_orders")->fetch();
$total_produced = (int)$t['produced'];
$total_defect   = (int)$t['defect'];
$total_good     = $total_produced - $total_defect;
$defect_rate    = $total_produced > 0 ? round($total_defect / $total_produced * 100, 2) : 0;

// Sản lượng theo ngày (30 ngày có dữ liệu gần nhất)
$by_day = $pdo->query("SELECT log_date, SUM(produced_qty) AS produced, SUM(good_qty) AS good, SUM(defect_qty) AS defect
                       FROM production_logs GROUP BY log_date ORDER BY log_date DESC LIMIT 30")->fetchAll();
$by_day = array_reverse($by_day);   // đảo lại để biểu đồ đi từ cũ đến mới

// Sản lượng theo sản phẩm
$by_product = $pdo->query("SELECT p.name, COALESCE(SUM(o.produced_qty),0) AS produced, COALESCE(SUM(o.defect_qty),0) AS defect
                           FROM products p LEFT JOIN production_orders o ON o.product_id = p.id
                           GROUP BY p.id, p.name ORDER BY p.id")->fetchAll();

// Tiến độ từng lệnh
$progress = $pdo->query("SELECT order_code, quantity, produced_qty FROM production_orders ORDER BY id")->fetchAll();

// Thống kê loại lỗi từ QC
$defect_types = $pdo->query("SELECT defect_type, SUM(defect_qty) AS total FROM quality_checks
                             WHERE defect_qty > 0 AND defect_type <> '' GROUP BY defect_type ORDER BY total DESC")->fetchAll();

// Chuẩn bị dữ liệu cho JavaScript
$js = [
    'dayLabels'   => array_map(fn($r) => fdate($r['log_date']), $by_day),
    'dayProduced' => array_map(fn($r) => (int)$r['produced'], $by_day),
    'prodLabels'  => array_column($by_product, 'name'),
    'prodGood'    => array_map(fn($r) => (int)$r['produced'] - (int)$r['defect'], $by_product),
    'prodDefect'  => array_map(fn($r) => (int)$r['defect'], $by_product),
    'ordLabels'   => array_column($progress, 'order_code'),
    'ordPercent'  => array_map(fn($r) => percent($r['produced_qty'], $r['quantity']), $progress),
    'good' => $total_good, 'defect' => $total_defect,
];

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="num"><?= number_format($total_produced) ?></div><div class="label">Tổng sản lượng</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card green"><div class="num"><?= number_format($total_good) ?></div><div class="label">Tổng sản phẩm đạt</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card red"><div class="num"><?= number_format($total_defect) ?></div><div class="label">Tổng sản phẩm lỗi</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card yellow"><div class="num"><?= $defect_rate ?>%</div><div class="label">Tỷ lệ lỗi</div></div></div>
</div>

<div class="row">
    <div class="col-lg-7"><div class="box"><h6>Sản lượng theo ngày</h6><canvas id="chartDay" height="150"></canvas></div></div>
    <div class="col-lg-5"><div class="box"><h6>Sản phẩm đạt / lỗi</h6><canvas id="chartQuality" height="200"></canvas></div></div>
    <div class="col-lg-7"><div class="box"><h6>Sản lượng theo sản phẩm</h6><canvas id="chartProduct" height="150"></canvas></div></div>
    <div class="col-lg-5"><div class="box"><h6>Tiến độ các lệnh sản xuất (%)</h6><canvas id="chartProgress" height="200"></canvas></div></div>
</div>

<div class="row">
    <div class="col-lg-7"><div class="box">
        <h6>Bảng sản lượng theo sản phẩm</h6>
        <table class="table table-sm"><thead><tr><th>Sản phẩm</th><th>Sản xuất</th><th>Đạt</th><th>Lỗi</th><th>Tỷ lệ lỗi</th></tr></thead><tbody>
        <?php foreach ($by_product as $p): $good = $p['produced'] - $p['defect']; ?>
            <tr><td><?= e($p['name']) ?></td><td><?= number_format($p['produced']) ?></td>
                <td class="text-success"><?= number_format($good) ?></td><td class="text-danger"><?= number_format($p['defect']) ?></td>
                <td><?= $p['produced'] > 0 ? round($p['defect'] / $p['produced'] * 100, 2) : 0 ?>%</td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div></div>
    <div class="col-lg-5"><div class="box">
        <h6>Các loại lỗi (từ QC)</h6>
        <table class="table table-sm"><thead><tr><th>Loại lỗi</th><th>Số lượng</th></tr></thead><tbody>
        <?php foreach ($defect_types as $d): ?><tr><td><?= e($d['defect_type']) ?></td><td><?= $d['total'] ?></td></tr><?php endforeach; ?>
        <?php if (!$defect_types): ?><tr><td colspan="2" class="text-muted">Chưa có dữ liệu</td></tr><?php endif; ?>
        </tbody></table>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
// Dữ liệu từ PHP truyền sang JavaScript
var d = <?= json_encode($js, JSON_UNESCAPED_UNICODE) ?>;

// Biểu đồ cột: sản lượng theo ngày
new Chart(document.getElementById('chartDay'), {
    type: 'bar',
    data: { labels: d.dayLabels, datasets: [{ label: 'Sản lượng', data: d.dayProduced, backgroundColor: '#0d6efd' }] }
});
// Biểu đồ tròn: đạt / lỗi
new Chart(document.getElementById('chartQuality'), {
    type: 'doughnut',
    data: { labels: ['Đạt', 'Lỗi'], datasets: [{ data: [d.good, d.defect], backgroundColor: ['#198754', '#dc3545'] }] }
});
// Biểu đồ cột chồng: theo sản phẩm
new Chart(document.getElementById('chartProduct'), {
    type: 'bar',
    data: { labels: d.prodLabels, datasets: [
        { label: 'Đạt', data: d.prodGood, backgroundColor: '#198754' },
        { label: 'Lỗi', data: d.prodDefect, backgroundColor: '#dc3545' } ] },
    options: { scales: { x: { stacked: true }, y: { stacked: true } } }
});
// Biểu đồ thanh ngang: tiến độ
new Chart(document.getElementById('chartProgress'), {
    type: 'bar',
    data: { labels: d.ordLabels, datasets: [{ label: 'Tiến độ (%)', data: d.ordPercent, backgroundColor: '#ffc107' }] },
    options: { indexAxis: 'y', scales: { x: { max: 100 } } }
});
</script>
<?php include 'includes/footer.php'; ?>
