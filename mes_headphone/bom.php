<?php
// FILE: bom.php - BOM: sản phẩm cần những nguyên vật liệu nào, mỗi loại bao nhiêu
require 'config/database.php';
require 'includes/auth.php';
require_login();
$page_title = 'Quản lý BOM';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM bom WHERE id = ?')->execute([(int)$_POST['id']]);
        set_flash('success', 'Đã xóa dòng BOM thành công.');
        redirect('bom.php?product_id=' . (int)($_POST['product_id'] ?? 0));
    }

    if ($action === 'save') {
        $product_id  = (int)($_POST['product_id'] ?? 0);
        $material_id = (int)($_POST['material_id'] ?? 0);
        $qty         = $_POST['quantity'] ?? '';

        if ($product_id <= 0 || $material_id <= 0 || !ctype_digit((string)$qty) || (int)$qty < 1) {
            set_flash('danger', 'Vui lòng chọn sản phẩm, nguyên vật liệu và nhập số lượng nguyên dương.');
        } else {
            // Nếu cặp (sản phẩm, vật liệu) đã có thì cập nhật số lượng, chưa có thì thêm mới
            $pdo->prepare('INSERT INTO bom (product_id, material_id, quantity) VALUES (?,?,?)
                           ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)')
                ->execute([$product_id, $material_id, (int)$qty]);
            set_flash('success', 'Đã lưu BOM thành công.');
        }
        redirect('bom.php?product_id=' . $product_id);
    }
}

$products  = $pdo->query("SELECT id, code, name FROM products ORDER BY code")->fetchAll();
$materials = $pdo->query("SELECT id, code, name, unit FROM materials ORDER BY code")->fetchAll();

// Lọc BOM theo sản phẩm (nếu có chọn)
$filter = (int)($_GET['product_id'] ?? 0);
$sql = "SELECT b.*, p.code AS pcode, p.name AS pname, m.code AS mcode, m.name AS mname, m.unit
        FROM bom b JOIN products p ON p.id = b.product_id JOIN materials m ON m.id = b.material_id";
$params = [];
if ($filter > 0) { $sql .= ' WHERE b.product_id = ?'; $params[] = $filter; }
$sql .= ' ORDER BY p.code, m.code';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Bấm "Sửa" thì đổ dòng BOM đó vào form
$edit = null;
if (is_admin() && isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM bom WHERE id = ?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<?php if (is_admin()): ?>
<div class="box">
    <h6><?= $edit ? 'Sửa số lượng BOM' : 'Thêm nguyên vật liệu vào BOM' ?></h6>
    <form method="post" class="row g-3">
        <input type="hidden" name="action" value="save">
        <div class="col-md-4"><label class="form-label">Sản phẩm</label>
            <select name="product_id" class="form-select" required>
                <option value="">-- Chọn sản phẩm --</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($edit['product_id'] ?? $filter) == $p['id'] ? 'selected' : '' ?>><?= e($p['code'] . ' - ' . $p['name']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-4"><label class="form-label">Nguyên vật liệu</label>
            <select name="material_id" class="form-select" required>
                <option value="">-- Chọn vật liệu --</option>
                <?php foreach ($materials as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= ($edit['material_id'] ?? 0) == $m['id'] ? 'selected' : '' ?>><?= e($m['code'] . ' - ' . $m['name']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-2"><label class="form-label">Số lượng</label>
            <input name="quantity" type="number" min="1" class="form-control" required value="<?= e($edit['quantity'] ?? 1) ?>"></div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary me-2"><?= $edit ? 'Cập nhật' : 'Lưu' ?></button>
            <?php if ($edit): ?><a href="bom.php" class="btn btn-secondary">Hủy</a><?php endif; ?>
        </div>
    </form>
    <small class="text-muted">Nếu chọn cặp sản phẩm - vật liệu đã có thì hệ thống sẽ cập nhật lại số lượng.</small>
</div>
<?php endif; ?>

<div class="box">
    <form method="get" class="d-flex gap-2 mb-3">
        <select name="product_id" class="form-select" onchange="this.form.submit()">
            <option value="0">-- Tất cả sản phẩm --</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $filter == $p['id'] ? 'selected' : '' ?>><?= e($p['code'] . ' - ' . $p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>Sản phẩm</th><th>Mã VL</th><th>Nguyên vật liệu</th><th>Số lượng / 1 SP</th><?php if (is_admin()): ?><th>Thao tác</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['pcode'] . ' - ' . $r['pname']) ?></td>
                <td><b><?= e($r['mcode']) ?></b></td>
                <td><?= e($r['mname']) ?></td>
                <td><?= $r['quantity'] . ' ' . e($r['unit']) ?></td>
                <?php if (is_admin()): ?>
                <td class="text-nowrap">
                    <a href="bom.php?product_id=<?= $r['product_id'] ?>&edit=<?= $r['id'] ?>" class="btn btn-sm btn-warning">Sửa</a>
                    <form method="post" class="d-inline form-delete">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="product_id" value="<?= $r['product_id'] ?>">
                        <button class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted">Chưa có BOM</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
