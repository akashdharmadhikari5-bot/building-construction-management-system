<?php
/**
 * BCMS — Material Catalog & Inventory Management
 * Admin-only page for managing material inventory, stock levels, low-stock thresholds, and stock adjustments.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

// Authorization: Admin only
requireRole(ROLE_ADMIN);

$materialModel = new MaterialModel();

// ── Handle POST Requests ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = strInput($_POST['action'] ?? '', 50);

    try {
        if ($action === 'add_material') {
            $name      = strInput($_POST['material_name'] ?? '', 200);
            $unit      = strInput($_POST['unit'] ?? '', 50);
            $threshold = decimalInput($_POST['low_stock_threshold'] ?? 10.0);

            if ($name === '') {
                throw new InvalidArgumentException('Material name is required.');
            }
            if ($unit === '') {
                throw new InvalidArgumentException('Unit of measurement is required (e.g. bags, kg, pieces).');
            }
            if ($threshold === null || $threshold < 0) {
                throw new InvalidArgumentException('Please enter a valid low-stock threshold (>= 0).');
            }

            $newId = $materialModel->create([
                'material_name'       => $name,
                'unit'                => $unit,
                'low_stock_threshold' => $threshold,
                'stock_quantity'      => 0.0,
            ]);

            logActivity('create_material', 'materials', $newId, "Added material: {$name} ({$unit}) with threshold {$threshold}");
            setFlash('success', "Material '{$name}' created successfully.");
            redirect('/materials.php');

        } elseif ($action === 'edit_material') {
            $materialId = intInput($_POST['material_id'] ?? null, 1);
            $name       = strInput($_POST['material_name'] ?? '', 200);
            $unit       = strInput($_POST['unit'] ?? '', 50);
            $threshold  = decimalInput($_POST['low_stock_threshold'] ?? null);

            if (!$materialId) {
                throw new InvalidArgumentException('Invalid material ID.');
            }
            if ($name === '') {
                throw new InvalidArgumentException('Material name is required.');
            }
            if ($unit === '') {
                throw new InvalidArgumentException('Unit of measurement is required.');
            }
            if ($threshold === null || $threshold < 0) {
                throw new InvalidArgumentException('Please enter a valid low-stock threshold (>= 0).');
            }

            $materialModel->update($materialId, [
                'material_name'       => $name,
                'unit'                => $unit,
                'low_stock_threshold' => $threshold,
            ]);

            logActivity('update_material', 'materials', $materialId, "Updated material #{$materialId}: {$name}");
            setFlash('success', "Material '{$name}' updated successfully.");
            redirect('/materials.php');

        } elseif ($action === 'adjust_stock') {
            $materialId = intInput($_POST['material_id'] ?? null, 1);
            $adjType    = strInput($_POST['adjustment_type'] ?? '', 15);
            $quantity   = decimalInput($_POST['quantity'] ?? null);
            $reason     = strInput($_POST['reason'] ?? '', 300);

            if (!$materialId) {
                throw new InvalidArgumentException('Invalid material selection.');
            }
            if (!in_array($adjType, ['increase', 'decrease'], true)) {
                throw new InvalidArgumentException('Adjustment type must be either increase or decrease.');
            }
            if ($quantity === null || $quantity <= 0) {
                throw new InvalidArgumentException('Adjustment quantity must be greater than zero.');
            }
            if ($reason === '') {
                throw new InvalidArgumentException('Reason for stock adjustment is required.');
            }

            $material = $materialModel->getById($materialId);
            if (!$material) {
                throw new InvalidArgumentException('Selected material does not exist.');
            }

            $currentStock = (float)($material['computed_stock'] ?? 0.0);
            if ($adjType === 'decrease' && $quantity > $currentStock) {
                throw new InvalidArgumentException("Cannot decrease stock by {$quantity} {$material['unit']}. Current available stock is {$currentStock} {$material['unit']}.");
            }

            $userId = (int)($_SESSION['user']['user_id'] ?? 0);
            dbInsert(
                'INSERT INTO stock_adjustments (material_id, adjustment_type, quantity, reason, adjusted_by)
                 VALUES (?, ?, ?, ?, ?)',
                [$materialId, $adjType, $quantity, $reason, $userId]
            );

            // Re-sync material cached stock quantity
            $materialModel->syncStock($materialId);

            $newStock = $materialModel->getCurrentStock($materialId);
            logActivity('adjust_stock', 'materials', $materialId, "Adjusted stock ({$adjType} {$quantity} {$material['unit']}) for '{$material['material_name']}'. New stock: {$newStock}. Reason: {$reason}");

            setFlash('success', "Stock successfully adjusted ({$adjType} {$quantity} {$material['unit']}) for '{$material['material_name']}'. Current stock: {$newStock} {$material['unit']}.");
            redirect('/materials.php');

        } else {
            throw new InvalidArgumentException('Invalid action requested.');
        }
    } catch (Throwable $e) {
        setFlash('error', $e->getMessage());
        redirect('/materials.php');
    }
}

// ── GET Data & Filtering ───────────────────────────────────────────────────
$searchParam   = strInput($_GET['search'] ?? '', 100);
$lowStockParam = isset($_GET['low_stock']) && $_GET['low_stock'] === '1';

$filterArray = [];
if ($searchParam !== '') {
    $filterArray['search'] = $searchParam;
}
if ($lowStockParam) {
    $filterArray['low_stock'] = true;
}

$materials = $materialModel->getAll($filterArray);

// Aggregate Statistics
$allMaterialsQuery = $materialModel->getAll();
$totalItems        = count($allMaterialsQuery);
$lowStockCount     = $materialModel->getLowStockCount();

// Calculate estimated inventory stock value from purchases
$stockValueQuery = dbQuery(
    "SELECT 
        m.material_id,
        COALESCE(v.computed_stock, m.stock_quantity) AS stock_qty,
        COALESCE(p.avg_price, 0) AS avg_unit_price
     FROM materials m
     LEFT JOIN v_material_stock v ON v.material_id = m.material_id
     LEFT JOIN (
         SELECT material_id, (SUM(price) / NULLIF(SUM(quantity), 0)) AS avg_price
         FROM material_purchases
         GROUP BY material_id
     ) p ON p.material_id = m.material_id"
);

$totalStockValue = 0.0;
$totalUnitsInStock = 0.0;
foreach ($stockValueQuery as $sv) {
    $stockQty = max(0.0, (float)$sv['stock_qty']);
    $avgPrice = (float)$sv['avg_unit_price'];
    $totalStockValue += ($stockQty * $avgPrice);
    $totalUnitsInStock += $stockQty;
}

$pageTitle = 'Materials Inventory';
require_once BCMS_ROOT . '/app/views/layout/header.php';
require_once BCMS_ROOT . '/app/views/layout/sidebar.php';
require_once BCMS_ROOT . '/app/views/layout/topnav.php';
?>

<main class="bcms-main">
    <div class="container-fluid">
        <!-- Breadcrumb & Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small">
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Materials</li>
                    </ol>
                </nav>
                <h2 class="h4 mb-0 fw-bold text-dark">
                    <i class="bi bi-box-seam me-2 text-primary"></i>Material Catalog & Stock Management
                </h2>
                <p class="text-muted small mb-0">Track live stock quantities, low-stock alerts, procurement costs, and inventory adjustments.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>/purchases.php" class="btn btn-outline-primary">
                    <i class="bi bi-cart-plus me-1"></i> Record Purchase
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Material
                </button>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if ($msg = getFlash('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($msg = getFlash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card bcms-stat-card border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="bcms-stat-icon-wrapper stat-icon-primary me-3">
                            <i class="bi bi-boxes"></i>
                        </div>
                        <div>
                            <div class="bcms-stat-label">Catalog Items</div>
                            <div class="bcms-stat-value"><?= $totalItems ?></div>
                            <div class="bcms-stat-subtext">Material types tracked</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card bcms-stat-card <?= $lowStockCount > 0 ? 'stat-warning' : 'stat-success' ?> border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="bcms-stat-icon-wrapper <?= $lowStockCount > 0 ? 'stat-icon-warning' : 'stat-icon-success' ?> me-3">
                            <i class="bi <?= $lowStockCount > 0 ? 'bi-exclamation-diamond-fill' : 'bi-check2-all' ?>"></i>
                        </div>
                        <div>
                            <div class="bcms-stat-label">Low Stock Alerts</div>
                            <div class="bcms-stat-value <?= $lowStockCount > 0 ? 'text-warning' : 'text-success' ?>">
                                <?= $lowStockCount ?>
                            </div>
                            <div class="bcms-stat-subtext"><?= $lowStockCount > 0 ? 'Below threshold limit' : 'All stock levels healthy' ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card bcms-stat-card stat-accent border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="bcms-stat-icon-wrapper stat-icon-accent me-3">
                            <i class="bi bi-currency-rupee"></i>
                        </div>
                        <div>
                            <div class="bcms-stat-label">Est. Stock Value</div>
                            <div class="bcms-stat-value text-dark" style="font-size: 1.45rem;">
                                <?= formatCurrency($totalStockValue) ?>
                            </div>
                            <div class="bcms-stat-subtext">Based on purchase prices</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card bcms-stat-card stat-info border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="bcms-stat-icon-wrapper stat-icon-primary me-3">
                            <i class="bi bi-stack"></i>
                        </div>
                        <div>
                            <div class="bcms-stat-label">Total Units On Hand</div>
                            <div class="bcms-stat-value text-info" style="font-size: 1.45rem;">
                                <?= number_format($totalUnitsInStock, 2) ?>
                            </div>
                            <div class="bcms-stat-subtext">Cumulative stock volume</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="<?= APP_URL ?>/materials.php" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label for="search" class="form-label small fw-semibold text-muted mb-1">Search Material Name or Unit</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input
                                type="text"
                                class="form-control border-start-0"
                                id="search"
                                name="search"
                                placeholder="Search by cement, sand, bricks, bags, kg..."
                                value="<?= e($searchParam) ?>"
                            >
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="low_stock" class="form-label small fw-semibold text-muted mb-1">Stock Level Status</label>
                        <select class="form-select" id="low_stock" name="low_stock">
                            <option value="">All Stock Levels</option>
                            <option value="1" <?= $lowStockParam ? 'selected' : '' ?>>Low Stock / Reorder Needed Only</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-1"></i> Apply Filters
                        </button>
                        <?php if ($searchParam !== '' || $lowStockParam): ?>
                            <a href="<?= APP_URL ?>/materials.php" class="btn btn-outline-secondary" title="Reset Filters">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Materials Table Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <span class="card-header-title">
                    <i class="bi bi-table me-2 text-primary"></i>
                    Materials Inventory Catalog
                    <span class="badge bg-secondary rounded-pill ms-2"><?= count($materials) ?></span>
                </span>
                <?php if ($lowStockParam): ?>
                    <span class="badge bg-warning text-dark">
                        <i class="bi bi-funnel-fill me-1"></i>Filtering: Low Stock Only
                    </span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-3" style="width: 50px;">#</th>
                            <th>Material Name</th>
                            <th>Unit</th>
                            <th class="text-end">Current Stock</th>
                            <th class="text-end">Low Stock Threshold</th>
                            <th class="text-center">Stock Breakdown</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3" style="width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($materials)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block text-muted opacity-50 mb-2"></i>
                                    No materials found matching your search criteria.
                                    <div class="mt-2">
                                        <a href="<?= APP_URL ?>/materials.php" class="btn btn-sm btn-outline-primary">
                                            Clear Filters
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($materials as $idx => $m): ?>
                                <?php
                                $stock     = (float)($m['computed_stock'] ?? 0.0);
                                $threshold = (float)($m['low_stock_threshold'] ?? 10.0);
                                $isOut     = ($stock <= 0.0);
                                $isLow     = ($stock > 0.0 && $stock <= $threshold);
                                $rowClass  = $isOut ? 'table-danger' : ($isLow ? 'table-warning' : '');
                                ?>
                                <tr class="<?= $rowClass ?>">
                                    <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark">
                                            <?= e($m['material_name']) ?>
                                        </div>
                                        <div class="small text-muted">ID: #<?= (int)$m['material_id'] ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= e($m['unit']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold font-monospace fs-6 <?= $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-success') ?>">
                                        <?= number_format($stock, 2) ?> <span class="small fw-normal text-muted"><?= e($m['unit']) ?></span>
                                    </td>
                                    <td class="text-end font-monospace text-muted">
                                        <?= number_format($threshold, 2) ?> <span class="small"><?= e($m['unit']) ?></span>
                                    </td>
                                    <td class="text-center small">
                                        <span class="text-success" title="Total Purchased">+<?= number_format((float)($m['total_purchased'] ?? 0), 1) ?></span>
                                        <span class="text-muted mx-1">/</span>
                                        <span class="text-danger" title="Total Used">-<?= number_format((float)($m['total_used'] ?? 0), 1) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($isOut): ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-octagon me-1"></i>Out of Stock
                                            </span>
                                        <?php elseif ($isLow): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i>Low Stock
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle-fill me-1"></i>In Stock
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <!-- Adjust Stock Button -->
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary btn-adjust-stock"
                                                title="Adjust Stock (Increase / Decrease)"
                                                data-id="<?= (int)$m['material_id'] ?>"
                                                data-name="<?= e($m['material_name']) ?>"
                                                data-unit="<?= e($m['unit']) ?>"
                                                data-stock="<?= number_format($stock, 2, '.', '') ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#adjustStockModal"
                                            >
                                                <i class="bi bi-sliders"></i>
                                            </button>

                                            <!-- Edit Material Button -->
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-edit-material"
                                                title="Edit Material"
                                                data-id="<?= (int)$m['material_id'] ?>"
                                                data-name="<?= e($m['material_name']) ?>"
                                                data-unit="<?= e($m['unit']) ?>"
                                                data-threshold="<?= number_format($threshold, 2, '.', '') ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editMaterialModal"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Add Material Modal -->
<div class="modal fade" id="addMaterialModal" tabindex="-1" aria-labelledby="addMaterialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= APP_URL ?>/materials.php" novalidate id="addMaterialForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_material">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="addMaterialModalLabel">
                        <i class="bi bi-box-seam me-2"></i>Add New Material
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="add_material_name" class="form-label fw-semibold">
                            Material Name <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="add_material_name"
                            name="material_name"
                            required
                            maxlength="200"
                            placeholder="e.g. UltraTech Cement 50kg, TMT Steel Bars 12mm, Red Clay Bricks"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="add_unit" class="form-label fw-semibold">
                            Unit of Measurement <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="add_unit"
                            name="unit"
                            list="unitSuggestions"
                            required
                            maxlength="50"
                            placeholder="e.g. bags, kg, metric tonne, brass, pieces, litres"
                        >
                        <datalist id="unitSuggestions">
                            <option value="bags"></option>
                            <option value="kg"></option>
                            <option value="metric tonnes"></option>
                            <option value="pieces"></option>
                            <option value="brass"></option>
                            <option value="litres"></option>
                            <option value="sq.ft"></option>
                            <option value="cu.ft"></option>
                            <option value="bundles"></option>
                        </datalist>
                    </div>

                    <div class="mb-3">
                        <label for="add_threshold" class="form-label fw-semibold">
                            Low Stock Alert Threshold <span class="text-danger">*</span>
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control"
                            id="add_threshold"
                            name="low_stock_threshold"
                            required
                            value="10.00"
                        >
                        <div class="form-text">System shows a warning when stock drops to or below this quantity.</div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Save Material
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Material Modal -->
<div class="modal fade" id="editMaterialModal" tabindex="-1" aria-labelledby="editMaterialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= APP_URL ?>/materials.php" novalidate id="editMaterialForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit_material">
                <input type="hidden" name="material_id" id="edit_material_id" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="editMaterialModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>Edit Material Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="edit_material_name" class="form-label fw-semibold">
                            Material Name <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="edit_material_name"
                            name="material_name"
                            required
                            maxlength="200"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="edit_unit" class="form-label fw-semibold">
                            Unit of Measurement <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="edit_unit"
                            name="unit"
                            required
                            maxlength="50"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="edit_threshold" class="form-label fw-semibold">
                            Low Stock Alert Threshold <span class="text-danger">*</span>
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control"
                            id="edit_threshold"
                            name="low_stock_threshold"
                            required
                        >
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Update Material
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= APP_URL ?>/materials.php" novalidate id="adjustStockForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="material_id" id="adjust_material_id" value="">

                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="adjustStockModalLabel">
                        <i class="bi bi-sliders me-2"></i>Stock Adjustment (Admin)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="alert alert-secondary py-2 px-3 mb-3 small d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted">Target Material:</span>
                            <strong id="adjust_material_name" class="d-block text-dark fs-6"></strong>
                        </div>
                        <div class="text-end">
                            <span class="text-muted">Current Stock:</span>
                            <strong id="adjust_current_stock" class="d-block text-primary fs-6"></strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold d-block">
                            Adjustment Direction <span class="text-danger">*</span>
                        </label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="adjustment_type" id="adj_increase" value="increase" checked>
                            <label class="btn btn-outline-success fw-semibold" for="adj_increase">
                                <i class="bi bi-plus-circle me-1"></i> Increase (+)
                            </label>

                            <input type="radio" class="btn-check" name="adjustment_type" id="adj_decrease" value="decrease">
                            <label class="btn btn-outline-danger fw-semibold" for="adj_decrease">
                                <i class="bi bi-dash-circle me-1"></i> Decrease (-)
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="adjust_quantity" class="form-label fw-semibold">
                            Adjustment Quantity <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input
                                type="number"
                                step="0.001"
                                min="0.001"
                                class="form-control"
                                id="adjust_quantity"
                                name="quantity"
                                required
                                placeholder="e.g. 5.0"
                            >
                            <span class="input-group-text" id="adjust_unit_addon">units</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="adjust_reason" class="form-label fw-semibold">
                            Reason for Adjustment <span class="text-danger">*</span>
                        </label>
                        <textarea
                            class="form-control"
                            id="adjust_reason"
                            name="reason"
                            rows="3"
                            required
                            maxlength="300"
                            placeholder="e.g. Physical inventory count correction, damaged goods written off, supplier bonus consignment..."
                        ></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="bi bi-check-lg me-1"></i> Apply Adjustment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Populate Edit Material Modal
    const editButtons = document.querySelectorAll('.btn-edit-material');
    editButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('edit_material_id').value    = this.dataset.id || '';
            document.getElementById('edit_material_name').value  = this.dataset.name || '';
            document.getElementById('edit_unit').value           = this.dataset.unit || '';
            document.getElementById('edit_threshold').value      = this.dataset.threshold || '';
        });
    });

    // Populate Stock Adjustment Modal
    const adjustButtons = document.querySelectorAll('.btn-adjust-stock');
    adjustButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const id    = this.dataset.id || '';
            const name  = this.dataset.name || '';
            const unit  = this.dataset.unit || 'units';
            const stock = this.dataset.stock || '0.00';

            document.getElementById('adjust_material_id').value        = id;
            document.getElementById('adjust_material_name').textContent = name;
            document.getElementById('adjust_current_stock').textContent = stock + ' ' + unit;
            document.getElementById('adjust_unit_addon').textContent    = unit;
            document.getElementById('adjust_quantity').value           = '';
            document.getElementById('adjust_reason').value             = '';
            document.getElementById('adj_increase').checked            = true;
        });
    });
});
</script>

<?php
require_once BCMS_ROOT . '/app/views/layout/footer.php';
