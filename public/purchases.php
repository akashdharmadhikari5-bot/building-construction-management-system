<?php
/**
 * BCMS — Material Purchases Management
 * Record stock-in purchases per project site and sync material inventory levels.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireRole(ROLE_ADMIN);

$purchaseModel = new PurchaseModel();
$materialModel = new MaterialModel();
$projectModel  = new ProjectModel();

// ── Handle POST: Record Material Purchase ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = strInput($_POST['action'] ?? '', 50);

    if ($action === 'add') {
        $projectId    = intInput($_POST['project_id'] ?? null, 1);
        $materialId   = intInput($_POST['material_id'] ?? null, 1);
        $quantity     = decimalInput($_POST['quantity'] ?? null);
        $price        = decimalInput($_POST['price'] ?? null);
        $purchaseDate = dateInput($_POST['purchase_date'] ?? date('Y-m-d'));
        $supplier     = strInput($_POST['supplier'] ?? '', 200);
        $notes        = strInput($_POST['notes'] ?? '', 1000);

        if (!$projectId) {
            setFlash('error', 'Please select a valid construction project.');
        } elseif (!$materialId) {
            setFlash('error', 'Please select a valid inventory material.');
        } elseif ($quantity === null || $quantity <= 0.0) {
            setFlash('error', 'Purchase quantity must be greater than zero.');
        } elseif ($price === null || $price < 0.0) {
            setFlash('error', 'Purchase price cannot be negative.');
        } elseif (!$purchaseDate) {
            setFlash('error', 'A valid purchase date is required.');
        } else {
            try {
                $purchaseId = $purchaseModel->create([
                    'project_id'    => $projectId,
                    'material_id'   => $materialId,
                    'quantity'      => $quantity,
                    'price'         => $price,
                    'purchase_date' => $purchaseDate,
                    'supplier'      => $supplier ?: null,
                    'notes'         => $notes ?: null,
                ]);

                $mat = $materialModel->getById($materialId);
                $matName = $mat['material_name'] ?? "Material #{$materialId}";

                logActivity('create', 'materials', $purchaseId, "Purchased {$quantity} of {$matName}");
                setFlash('success', "Recorded purchase of {$quantity} {$mat['unit']} of '{$matName}' and updated inventory!");
            } catch (Throwable $e) {
                setFlash('error', 'Error recording purchase: ' . $e->getMessage());
            }
        }
        redirect('/purchases.php' . ($projectId ? "?project_id={$projectId}" : ''));
    }
}

// ── GET: Filters & Listing ────────────────────────────────────────────────────
$filters = [];
$projectFilter = intInput($_GET['project_id'] ?? null, 1);
$materialFilter= intInput($_GET['material_id'] ?? null, 1);
$supplierQuery = strInput($_GET['supplier'] ?? '', 100);
$fromDate      = dateInput($_GET['from_date'] ?? null);
$toDate        = dateInput($_GET['to_date'] ?? null);

if ($projectFilter)  $filters['project_id']  = $projectFilter;
if ($materialFilter) $filters['material_id'] = $materialFilter;
if ($supplierQuery !== '') $filters['supplier'] = $supplierQuery;
if ($fromDate)       $filters['from_date']   = $fromDate;
if ($toDate)         $filters['to_date']     = $toDate;

$purchases    = [];
$materials    = [];
$projects     = [];
$totalSpend   = 0.0;
$totalItems   = 0;

try {
    $purchases = $purchaseModel->getAll($filters);
    $materials = $materialModel->getAll();
    $projects  = $projectModel->getAll(['status' => 'active']);

    foreach ($purchases as $p) {
        $totalSpend += (float)$p['price'];
        $totalItems++;
    }
} catch (Throwable $e) {
    setFlash('error', 'Error loading purchases: ' . $e->getMessage());
}

$pageTitle = 'Material Purchases';
$activeNav = 'purchases';

require_once VIEW_PATH . '/layout/header.php';
require_once VIEW_PATH . '/layout/sidebar.php';
require_once VIEW_PATH . '/layout/topnav.php';
?>

<div class="bcms-content">
  <!-- Page Header -->
  <div class="bcms-page-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/materials.php">Materials</a></li>
          <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
        </ol>
      </nav>
      <h2 class="h4 mb-0 fw-bold"><?= e($pageTitle) ?></h2>
      <p class="text-muted small mb-0">Record raw material procurement, track supplier invoices, and update inventory stock.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPurchaseModal">
      <i class="bi bi-cart-plus me-1"></i>Record New Purchase
    </button>
  </div>

  <!-- Flash Messages -->
  <?php if ($flashSuccess = getFlash('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i><?= e($flashSuccess) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($flashError = getFlash('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($flashError) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- KPI Cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary me-3">
            <i class="bi bi-cart-check fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Total Purchases Recorded</div>
            <div class="fs-4 fw-bold"><?= number_format($totalItems) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success me-3">
            <i class="bi bi-cash-stack fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Total Procurement Spend</div>
            <div class="fs-4 fw-bold text-success"><?= formatCurrency($totalSpend) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-info bg-opacity-10 text-info me-3">
            <i class="bi bi-boxes fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Active Catalog Materials</div>
            <div class="fs-4 fw-bold text-info"><?= count($materials) ?> items</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Search & Filter Bar -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" action="<?= APP_URL ?>/purchases.php" class="row g-3 align-items-center">
        <div class="col-md-3">
          <select name="project_id" class="form-select">
            <option value="">All Projects</option>
            <?php foreach ($projects as $pr): ?>
              <option value="<?= $pr['project_id'] ?>" <?= $projectFilter === (int)$pr['project_id'] ? 'selected' : '' ?>>
                <?= e($pr['project_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="material_id" class="form-select">
            <option value="">All Materials</option>
            <?php foreach ($materials as $m): ?>
              <option value="<?= $m['material_id'] ?>" <?= $materialFilter === (int)$m['material_id'] ? 'selected' : '' ?>>
                <?= e($m['material_name']) ?> (<?= e($m['unit']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" name="from_date" class="form-control" value="<?= e($fromDate ?? '') ?>" placeholder="From date">
        </div>
        <div class="col-md-2">
          <input type="date" name="to_date" class="form-control" value="<?= e($toDate ?? '') ?>" placeholder="To date">
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
          <a href="<?= APP_URL ?>/purchases.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
      </form>
    </div>
  </div>

  <!-- Purchases List Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
      <h5 class="mb-0 fw-bold">Procurement Records (<?= count($purchases) ?>)</h5>
    </div>
    <div class="card-body p-0">
      <?php if (empty($purchases)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-cart-x fs-2"></i>
          <h5 class="mt-3">No purchases recorded</h5>
          <p class="small">Procure materials for site inventory to see transactions here.</p>
          <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPurchaseModal">
            <i class="bi bi-plus-circle me-1"></i>Record First Purchase
          </button>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Date</th>
                <th>Material</th>
                <th>Quantity</th>
                <th>Total Price</th>
                <th>Project Site</th>
                <th>Supplier / Dealer</th>
                <th class="pe-4">Notes</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($purchases as $p): ?>
                <tr>
                  <td class="ps-4 fw-semibold text-nowrap"><?= formatDate($p['purchase_date']) ?></td>
                  <td>
                    <div class="fw-bold"><?= e($p['material_name']) ?></div>
                    <div class="small text-muted">ID: #<?= (int)$p['material_id'] ?></div>
                  </td>
                  <td>
                    <span class="badge bg-success-subtle text-success fs-6 fw-bold">
                      +<?= number_format((float)$p['quantity'], 2) ?> <?= e($p['unit']) ?>
                    </span>
                  </td>
                  <td class="fw-bold text-dark text-nowrap">
                    <?= formatCurrency($p['price']) ?>
                  </td>
                  <td>
                    <a href="<?= APP_URL ?>/project_view.php?project_id=<?= (int)$p['project_id'] ?>" class="text-decoration-none fw-semibold">
                      <?= e($p['project_name']) ?>
                    </a>
                  </td>
                  <td><?= e($p['supplier'] ?? '—') ?></td>
                  <td class="pe-4 small text-muted text-truncate" style="max-width: 200px;">
                    <?= e($p['notes'] ?? '—') ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Add Purchase Modal -->
<div class="modal fade" id="addPurchaseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= APP_URL ?>/purchases.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-cart-plus me-2"></i>Record Material Procurement</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Project Site <span class="text-danger">*</span></label>
              <select name="project_id" class="form-select" required>
                <option value="">Select Project...</option>
                <?php foreach ($projects as $pr): ?>
                  <option value="<?= $pr['project_id'] ?>" <?= $projectFilter === (int)$pr['project_id'] ? 'selected' : '' ?>>
                    <?= e($pr['project_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Material Item <span class="text-danger">*</span></label>
              <select name="material_id" class="form-select" required>
                <option value="">Select Material...</option>
                <?php foreach ($materials as $m): ?>
                  <option value="<?= $m['material_id'] ?>" <?= $materialFilter === (int)$m['material_id'] ? 'selected' : '' ?>>
                    <?= e($m['material_name']) ?> (Unit: <?= e($m['unit']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Quantity Procured <span class="text-danger">*</span></label>
              <input type="number" step="0.001" min="0.001" name="quantity" class="form-control" placeholder="e.g. 100" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Total Invoice Amount (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0" name="price" class="form-control" placeholder="0.00" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Purchase Date <span class="text-danger">*</span></label>
              <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Supplier / Vendor Name</label>
              <input type="text" name="supplier" class="form-control" placeholder="e.g. ACC Cement Dealers, Tata Steel">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Delivery / Invoice Notes</label>
              <input type="text" name="notes" class="form-control" placeholder="Challan number, batch notes...">
            </div>
          </div>
          <div class="alert alert-info small py-2 mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>Saving this purchase will automatically increment the stock quantity in the materials inventory.
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Save Purchase & Sync Stock</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
require_once VIEW_PATH . '/layout/footer.php';

