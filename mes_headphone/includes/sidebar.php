<?php
// FILE: includes/sidebar.php
// Menu bên trái + thanh header phía trên. Mở thẻ <main>, footer.php sẽ đóng lại.
$current = basename($_SERVER['PHP_SELF']);
$menu = [
    'index.php'             => ['Dashboard', '📊'],
    'products.php'          => ['Sản phẩm', '🎧'],
    'materials.php'         => ['Nguyên vật liệu', '📦'],
    'bom.php'               => ['BOM', '🧩'],
    'production_orders.php' => ['Lệnh sản xuất', '📝'],
    'production.php'        => ['Theo dõi sản xuất', '🏭'],
    'quality.php'           => ['Kiểm tra QC', '✅'],
    'warehouse.php'         => ['Kho thành phẩm', '🏬'],
    'reports.php'           => ['Báo cáo', '📈'],
];
if (is_admin()) $menu['users.php'] = ['Người dùng', '👤'];
?>
<aside class="sidebar" id="sidebar">
    <div class="brand">🎧 MES Tai nghe</div>
    <nav>
        <?php foreach ($menu as $file => $item): ?>
            <a href="<?= $file ?>" class="<?= $current === $file ? 'active' : '' ?>">
                <span><?= $item[1] ?></span> <?= e($item[0]) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
<div class="main">
    <header class="topbar">
        <button class="btn btn-outline-primary btn-sm d-md-none" id="toggleSidebar">☰</button>
        <h5 class="m-0"><?= e($page_title ?? '') ?></h5>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="text-muted small"><?= e($_SESSION['user']['full_name']) ?>
                <span class="badge bg-primary"><?= e(strtoupper($_SESSION['user']['role'])) ?></span></span>
            <a href="logout.php" class="btn btn-sm btn-outline-danger">Đăng xuất</a>
        </div>
    </header>
    <main class="content">
        <?php show_flash(); ?>
