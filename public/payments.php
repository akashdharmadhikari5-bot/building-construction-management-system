<?php
/**
 * BCMS — Payments Management
 * Client payment receipts ledger, project-client linkage validation (IDOR prevention), and client portal view.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireLogin();

$user     = currentUser();
$userRole = $user['role'] ?? ROLE_WORKER;
$isAdmin  = ($userRole === ROLE_ADMIN);
$isClient = ($userRole === ROLE_CLIENT);

if (!$isAdmin && !$isClient) {
    setFlash('error', 'You do not have permission to access the payments module.');
    redirect('/dashboard.php');
}

$paymentModel = new PaymentModel();
$projectModel = new ProjectModel();
$clientModel  = new ClientModel();

// ── Handle POST: Record Client Payment (Admin only) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        setFlash('error', 'Only administrators can record client payments.');
        redirect('/payments.php');
    }

    validateCsrf();
    $action = strInput($_POST['action'] ?? '', 50);

    if ($action === 'add') {
        $projectId   = intInput($_POST['project_id'] ?? null, 1);
        $amount      = decimalInput($_POST['amount'] ?? null);
        $paymentDate = dateInput($_POST['payment_date'] ?? date('Y-m-d'));
        $paymentType = strInput($_POST['payment_type'] ?? 'partial', 50);
        $paymentMode = strInput($_POST['payment_mode'] ?? 'cash', 50);
        $referenceNo = strInput($_POST['reference_no'] ?? '', 100);
        $notes       = strInput($_POST['notes'] ?? '', 1000);

        if (!$projectId) {
            setFlash('error', 'Please select a valid construction project.');
        } elseif ($amount === null || $amount <= 0.0) {
            setFlash('error', 'Payment amount must be greater than zero.');
        } elseif (!$paymentDate) {
            setFlash('error', 'A valid payment date is required.');
        } else {
            try {
                // Determine client_id from project
                $project = $projectModel->getById($projectId);
                if (!$project) {
                    throw new InvalidArgumentException("Project #{$projectId} not found.");
                }
                $clientId = (int)$project['client_id'];

                $paymentId = $paymentModel->create([
                    'project_id'   => $projectId,
                    'client_id'    => $clientId,
                    'amount'       => $amount,
                    'payment_date' => $paymentDate,
                    'payment_type' => $paymentType,
                    'payment_mode' => $paymentMode,
                    'reference_no' => $referenceNo ?: null,
                    'notes'        => $notes ?: null,
                    'created_by'   => $user['user_id'] ?? null,
                ]);

                logActivity('create', 'payments', $paymentId, "Recorded payment of " . formatCurrency($amount) . " for project: {$project['project_name']}");
                setFlash('success', 'Payment of ' . formatCurrency($amount) . ' recorded successfully!');
            } catch (Throwable $e) {
                setFlash('error', 'Error recording payment: ' . $e->getMessage());
            }
        }
        redirect('/payments.php' . ($projectId ? "?project_id={$projectId}" : ''));
    }
}

// ── GET: Filters & Listing ────────────────────────────────────────────────────
$filters = [];
$projectFilter = intInput($_GET['project_id'] ?? null, 1);
$typeFilter    = strInput($_GET['payment_type'] ?? '', 50);
$fromDate      = dateInput($_GET['from_date'] ?? null);
$toDate        = dateInput($_GET['to_date'] ?? null);

if ($projectFilter) $filters['project_id']   = $projectFilter;
if ($typeFilter)    $filters['payment_type'] = $typeFilter;
if ($fromDate)      $filters['from_date']    = $fromDate;
if ($toDate)        $filters['to_date']      = $toDate;

// Client role enforcement: view only payments for current client
if ($isClient) {
    $filters['client_id'] = (int)($user['linked_id'] ?? 0);
}

$payments      = [];
$projects      = [];
$totalReceived = 0.0;
$typeTotals    = [
    'advance'   => 0.0,
    'milestone' => 0.0,
    'final'     => 0.0,
    'partial'   => 0.0,
];

try {
    $payments = $paymentModel->getAll($filters);
    $projects = $isAdmin ? $projectModel->getAll() : $projectModel->getAll(['client_id' => $user['linked_id'] ?? 0]);

    foreach ($payments as $pay) {
        $amt = (float)$pay['amount'];
        $totalReceived += $amt;
        $t = $pay['payment_type'];
        if (isset($typeTotals[$t])) {
            $typeTotals[$t] += $amt;
        }
    }
} catch (Throwable $e) {
    setFlash('error', 'Error loading payments: ' . $e->getMessage());
}

$pageTitle = $isClient ? 'My Payment Receipts' : 'Project Payments';
$activeNav = 'payments';

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
          <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
        </ol>
      </nav>
      <h2 class="h4 mb-0 fw-bold"><?= e($pageTitle) ?></h2>
      <p class="text-muted small mb-0">Track customer advances, milestone billings, and payment settlements.</p>
    </div>
    <?php if ($isAdmin): ?>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
        <i class="bi bi-credit-card-2-front me-1"></i>Record Client Payment
      </button>
    <?php endif; ?>
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
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success me-3">
            <i class="bi bi-cash-stack fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Total Received</div>
            <div class="fs-4 fw-bold text-success"><?= formatCurrency($totalReceived) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary me-3">
            <i class="bi bi-flag fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Milestone Payments</div>
            <div class="fs-5 fw-bold text-dark"><?= formatCurrency($typeTotals['milestone']) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning me-3">
            <i class="bi bi-wallet2 fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Advance Received</div>
            <div class="fs-5 fw-bold text-dark"><?= formatCurrency($typeTotals['advance']) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-3 p-3 bg-info bg-opacity-10 text-info me-3">
            <i class="bi bi-receipt fs-3"></i>
          </div>
          <div>
            <div class="text-muted small">Total Transactions</div>
            <div class="fs-4 fw-bold text-info"><?= count($payments) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Search & Filter Bar -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" action="<?= APP_URL ?>/payments.php" class="row g-3 align-items-center">
        <div class="col-md-4">
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
          <select name="payment_type" class="form-select">
            <option value="">All Payment Types</option>
            <option value="advance" <?= $typeFilter === 'advance' ? 'selected' : '' ?>>Advance</option>
            <option value="milestone" <?= $typeFilter === 'milestone' ? 'selected' : '' ?>>Milestone</option>
            <option value="partial" <?= $typeFilter === 'partial' ? 'selected' : '' ?>>Partial</option>
            <option value="final" <?= $typeFilter === 'final' ? 'selected' : '' ?>>Final Settlement</option>
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" name="from_date" class="form-control" value="<?= e($fromDate ?? '') ?>" placeholder="From date">
        </div>
        <div class="col-md-2">
          <input type="date" name="to_date" class="form-control" value="<?= e($toDate ?? '') ?>" placeholder="To date">
        </div>
        <div class="col-md-1 d-flex gap-1">
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i></button>
          <a href="<?= APP_URL ?>/payments.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
      </form>
    </div>
  </div>

  <!-- Payments Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
      <h5 class="mb-0 fw-bold">Payments Ledger (<?= count($payments) ?>)</h5>
    </div>
    <div class="card-body p-0">
      <?php if (empty($payments)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-credit-card-2-back fs-2"></i>
          <h5 class="mt-3">No payments recorded</h5>
          <p class="small">Incoming client payments will appear in this ledger.</p>
          <?php if ($isAdmin): ?>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
              <i class="bi bi-plus-circle me-1"></i>Record First Payment
            </button>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Date</th>
                <th>Project Site</th>
                <th>Client</th>
                <th>Amount</th>
                <th>Type</th>
                <th>Payment Mode</th>
                <th class="pe-4">Reference / Notes</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($payments as $pay): ?>
                <tr>
                  <td class="ps-4 fw-semibold text-nowrap"><?= formatDate($pay['payment_date']) ?></td>
                  <td>
                    <a href="<?= APP_URL ?>/project_view.php?project_id=<?= (int)$pay['project_id'] ?>" class="text-decoration-none fw-semibold">
                      <?= e($pay['project_name']) ?>
                    </a>
                  </td>
                  <td>
                    <div class="fw-semibold text-dark"><?= e($pay['client_name']) ?></div>
                  </td>
                  <td class="fw-bold text-success text-nowrap">
                    <?= formatCurrency($pay['amount']) ?>
                  </td>
                  <td>
                    <span class="badge bg-primary-subtle text-primary border text-capitalize px-2 py-1">
                      <?= e($pay['payment_type']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border text-capitalize px-2 py-1">
                      <?= str_replace('_', ' ', e($pay['payment_mode'])) ?>
                    </span>
                  </td>
                  <td class="pe-4 small text-muted text-truncate" style="max-width: 250px;">
                    <?= !empty($pay['reference_no']) ? '<strong class="text-dark">Ref:</strong> ' . e($pay['reference_no']) . ' ' : '' ?>
                    <?= e($pay['notes'] ?? '—') ?>
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

<?php if ($isAdmin): ?>
<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= APP_URL ?>/payments.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-credit-card-2-front me-2"></i>Record Client Payment</h5>
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
                    <?= e($pr['project_name']) ?> (Client: <?= e($pr['client_name']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Amount Received (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="0.00" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
              <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Payment Type <span class="text-danger">*</span></label>
              <select name="payment_type" class="form-select" required>
                <option value="advance">Advance Payment</option>
                <option value="milestone" selected>Milestone Stage</option>
                <option value="partial">Partial Payment</option>
                <option value="final">Final Settlement</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Payment Mode <span class="text-danger">*</span></label>
              <select name="payment_mode" class="form-select" required>
                <option value="bank_transfer" selected>Bank Transfer (NEFT/RTGS/IMPS)</option>
                <option value="upi">UPI</option>
                <option value="cheque">Cheque</option>
                <option value="cash">Cash</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Transaction / Cheque / UTR Reference No.</label>
              <input type="text" name="reference_no" class="form-control" placeholder="e.g. UTR20261103001, CHQ-405921">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Receipt Notes</label>
              <input type="text" name="notes" class="form-control" placeholder="Installment details, bank branch...">
            </div>
          </div>
          <div class="alert alert-info small py-2 mt-3 mb-0">
            <i class="bi bi-shield-check me-1"></i>The system will automatically link the payment to the project's assigned client and verify record ownership to ensure data integrity.
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Record Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
require_once VIEW_PATH . '/layout/footer.php';

