<?php
/**
 * BCMS — Clients Management
 * CRUD operations for clients (Admin only).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireRole(ROLE_ADMIN);

$clientModel = new ClientModel();

// ── POST ACTION HANDLERS ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $action = strInput($_POST['action'] ?? '');

    // 1. ADD CLIENT
    if ($action === 'add') {
        $name    = strInput($_POST['name'] ?? '', 150);
        $phone   = strInput($_POST['phone'] ?? '', 20);
        $email   = strInput($_POST['email'] ?? '', 200);
        $address = strInput($_POST['address'] ?? '', 1000);

        if ($name === '' || $phone === '' || $email === '' || $address === '') {
            setFlash('error', 'All fields (Name, Phone, Email, Address) are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please provide a valid email address.');
        } else {
            try {
                $newId = $clientModel->create([
                    'name'    => $name,
                    'phone'   => $phone,
                    'email'   => $email,
                    'address' => $address,
                ]);
                logActivity('create', 'clients', $newId, "Added client: {$name}");
                setFlash('success', "Client '{$name}' added successfully!");
            } catch (Throwable $e) {
                setFlash('error', 'Error adding client: ' . $e->getMessage());
            }
        }
        redirect('/clients.php');
    }

    // 2. EDIT CLIENT
    if ($action === 'edit') {
        $clientId = intInput($_POST['client_id'] ?? null);
        $name     = strInput($_POST['name'] ?? '', 150);
        $phone    = strInput($_POST['phone'] ?? '', 20);
        $email    = strInput($_POST['email'] ?? '', 200);
        $address  = strInput($_POST['address'] ?? '', 1000);

        if (!$clientId || $clientId <= 0) {
            setFlash('error', 'Invalid client ID.');
        } elseif ($name === '' || $phone === '' || $email === '' || $address === '') {
            setFlash('error', 'All fields are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please provide a valid email address.');
        } else {
            try {
                $clientModel->update($clientId, [
                    'name'    => $name,
                    'phone'   => $phone,
                    'email'   => $email,
                    'address' => $address,
                ]);
                logActivity('update', 'clients', $clientId, "Updated client: {$name}");
                setFlash('success', "Client '{$name}' updated successfully!");
            } catch (Throwable $e) {
                setFlash('error', 'Error updating client: ' . $e->getMessage());
            }
        }
        redirect('/clients.php');
    }

    // 3. DELETE CLIENT
    if ($action === 'delete') {
        $clientId = intInput($_POST['client_id'] ?? null);

        if (!$clientId || $clientId <= 0) {
            setFlash('error', 'Invalid client ID.');
        } else {
            $client = $clientModel->getById($clientId);
            if (!$client) {
                setFlash('error', 'Client not found.');
            } else {
                try {
                    $clientModel->delete($clientId);
                    logActivity('delete', 'clients', $clientId, "Deleted client: {$client['name']}");
                    setFlash('success', "Client '{$client['name']}' was deleted successfully.");
                } catch (Throwable $e) {
                    setFlash('error', $e->getMessage());
                }
            }
        }
        redirect('/clients.php');
    }

    redirect('/clients.php');
}

// ── GET REQUEST: Search & List ────────────────────────────────
$search = strInput($_GET['search'] ?? '', 100);
$filters = [];
if ($search !== '') {
    $filters['search'] = $search;
}

$clients = $clientModel->getAll($filters);

$pageTitle = 'Clients Management';
require_once VIEW_PATH . '/layout/header.php';
require_once VIEW_PATH . '/layout/sidebar.php';
require_once VIEW_PATH . '/layout/topnav.php';
?>

<main class="bcms-main">
  <div class="container-fluid">

    <!-- Flash Messages -->
    <?php foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $flashKey => $alertClass): ?>
      <?php if ($msg = getFlash($flashKey)): ?>
        <div class="alert alert-<?= $alertClass ?> alert-dismissible fade show mb-4" role="alert">
          <i class="bi bi-<?= $flashKey === 'success' ? 'check-circle-fill' : ($flashKey === 'error' ? 'exclamation-octagon-fill' : 'info-circle-fill') ?> me-2"></i>
          <?= e($msg) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <!-- Page Header & Action Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
      <div>
        <h2 class="h4 mb-1 fw-bold text-dark">
          <i class="bi bi-people-fill text-primary me-2"></i>Clients Directory
        </h2>
        <p class="text-muted small mb-0">Manage property owners, developers, and project sponsors</p>
      </div>
      <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
          <i class="bi bi-person-plus-fill me-1"></i> Add New Client
        </button>
      </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body py-3">
        <form method="GET" action="<?= APP_URL ?>/clients.php" class="row g-2 align-items-center">
          <div class="col-12 col-md-8 col-lg-6">
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0 text-muted">
                <i class="bi bi-search"></i>
              </span>
              <input
                type="text"
                name="search"
                class="form-control border-start-0 ps-0"
                placeholder="Search clients by name, phone, or email..."
                value="<?= e($search) ?>"
              >
              <?php if ($search !== ''): ?>
                <a href="<?= APP_URL ?>/clients.php" class="btn btn-outline-secondary" title="Clear Search">
                  <i class="bi bi-x-lg"></i>
                </a>
              <?php endif; ?>
              <button type="submit" class="btn btn-primary">Search</button>
            </div>
          </div>
          <div class="col-12 col-md-4 col-lg-6 text-md-end text-muted small">
            Showing <strong><?= count($clients) ?></strong> client(s) <?= $search !== '' ? 'matching "<strong>' . e($search) . '</strong>"' : '' ?>
          </div>
        </form>
      </div>
    </div>

    <!-- Clients Table Card -->
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="clientsTable">
            <thead class="table-light small">
              <tr>
                <th style="width: 50px;">#</th>
                <th>Client Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Address</th>
                <th class="text-center" style="width: 130px;">Projects</th>
                <th class="text-end" style="width: 160px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($clients)): ?>
                <tr>
                  <td colspan="7" class="text-center py-5 text-muted">
                    <i class="bi bi-people display-6 d-block mb-2 text-muted"></i>
                    No clients found. Click <strong>Add New Client</strong> to register one.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($clients as $idx => $c): ?>
                  <tr>
                    <td class="text-muted small"><?= $idx + 1 ?></td>
                    <td>
                      <div class="fw-bold text-dark"><?= e($c['name']) ?></div>
                      <small class="text-muted">ID: #<?= (int)$c['client_id'] ?></small>
                    </td>
                    <td>
                      <a href="tel:<?= e($c['phone']) ?>" class="text-decoration-none text-dark">
                        <i class="bi bi-telephone me-1 text-muted"></i><?= e($c['phone']) ?>
                      </a>
                    </td>
                    <td>
                      <a href="mailto:<?= e($c['email']) ?>" class="text-decoration-none">
                        <i class="bi bi-envelope me-1 text-muted"></i><?= e($c['email']) ?>
                      </a>
                    </td>
                    <td class="small text-muted text-truncate" style="max-width: 200px;" title="<?= e($c['address']) ?>">
                      <?= e($c['address']) ?>
                    </td>
                    <td class="text-center">
                      <?php $pCount = (int)$c['total_projects']; ?>
                      <span class="badge rounded-pill <?= $pCount > 0 ? 'bg-primary' : 'bg-light text-dark border' ?>">
                        <?= $pCount ?> Project<?= $pCount !== 1 ? 's' : '' ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <!-- View Details Modal Trigger -->
                        <button
                          type="button"
                          class="btn btn-outline-secondary btn-view-client"
                          title="View Client Details"
                          data-id="<?= (int)$c['client_id'] ?>"
                          data-name="<?= e($c['name']) ?>"
                          data-phone="<?= e($c['phone']) ?>"
                          data-email="<?= e($c['email']) ?>"
                          data-address="<?= e($c['address']) ?>"
                          data-projects="<?= (int)$c['total_projects'] ?>"
                          data-created="<?= formatDate($c['created_at']) ?>"
                        >
                          <i class="bi bi-eye"></i>
                        </button>
                        <!-- Edit Client Modal Trigger -->
                        <button
                          type="button"
                          class="btn btn-outline-primary btn-edit-client"
                          title="Edit Client"
                          data-id="<?= (int)$c['client_id'] ?>"
                          data-name="<?= e($c['name']) ?>"
                          data-phone="<?= e($c['phone']) ?>"
                          data-email="<?= e($c['email']) ?>"
                          data-address="<?= e($c['address']) ?>"
                        >
                          <i class="bi bi-pencil"></i>
                        </button>
                        <!-- Delete Confirmation Trigger -->
                        <button
                          type="button"
                          class="btn btn-outline-danger btn-delete-client"
                          title="Delete Client"
                          data-id="<?= (int)$c['client_id'] ?>"
                          data-name="<?= e($c['name']) ?>"
                          data-projects="<?= (int)$c['total_projects'] ?>"
                        >
                          <i class="bi bi-trash"></i>
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

  </div>
</main>

<!-- ============================================================ -->
<!-- MODAL: ADD CLIENT -->
<!-- ============================================================ -->
<div class="modal fade" id="addClientModal" tabindex="-1" aria-labelledby="addClientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold" id="addClientModalLabel">
          <i class="bi bi-person-plus-fill me-2"></i>Add New Client
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?= APP_URL ?>/clients.php" class="needs-validation" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-body p-4">
          <div class="mb-3">
            <label for="add_name" class="form-label fw-semibold">Client Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add_name" name="name" placeholder="e.g. Ramesh Sharma" required maxlength="150">
            <div class="invalid-feedback">Client name is required.</div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label for="add_phone" class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
              <input type="tel" class="form-control" id="add_phone" name="phone" placeholder="e.g. 9876543210" required maxlength="20">
              <div class="invalid-feedback">Phone number is required.</div>
            </div>
            <div class="col-md-6">
              <label for="add_email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="add_email" name="email" placeholder="client@example.com" required maxlength="200">
              <div class="invalid-feedback">A valid email address is required.</div>
            </div>
          </div>
          <div class="mb-3">
            <label for="add_address" class="form-label fw-semibold">Postal / Billing Address <span class="text-danger">*</span></label>
            <textarea class="form-control" id="add_address" name="address" rows="3" placeholder="Enter complete address, city, state and PIN code" required></textarea>
            <div class="invalid-feedback">Address is required.</div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Save Client
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: EDIT CLIENT -->
<!-- ============================================================ -->
<div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold" id="editClientModalLabel">
          <i class="bi bi-pencil-square me-2"></i>Edit Client Details
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?= APP_URL ?>/clients.php" class="needs-validation" novalidate id="editClientForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="client_id" id="edit_client_id" value="">
        <div class="modal-body p-4">
          <div class="mb-3">
            <label for="edit_name" class="form-label fw-semibold">Client Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit_name" name="name" required maxlength="150">
            <div class="invalid-feedback">Client name is required.</div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label for="edit_phone" class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
              <input type="tel" class="form-control" id="edit_phone" name="phone" required maxlength="20">
              <div class="invalid-feedback">Phone number is required.</div>
            </div>
            <div class="col-md-6">
              <label for="edit_email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="edit_email" name="email" required maxlength="200">
              <div class="invalid-feedback">A valid email address is required.</div>
            </div>
          </div>
          <div class="mb-3">
            <label for="edit_address" class="form-label fw-semibold">Address <span class="text-danger">*</span></label>
            <textarea class="form-control" id="edit_address" name="address" rows="3" required></textarea>
            <div class="invalid-feedback">Address is required.</div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Update Client
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: VIEW CLIENT -->
<!-- ============================================================ -->
<div class="modal fade" id="viewClientModal" tabindex="-1" aria-labelledby="viewClientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold text-dark" id="viewClientModalLabel">
          <i class="bi bi-person-badge text-primary me-2"></i>Client Profile
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="text-center mb-4">
          <div class="bcms-user-avatar mx-auto mb-2" id="view_avatar" style="width: 56px; height: 56px; font-size: 1.5rem;">C</div>
          <h4 class="mb-1 fw-bold text-dark" id="view_name"></h4>
          <span class="badge bg-primary">Registered Client</span>
        </div>
        <ul class="list-group list-group-flush mb-3">
          <li class="list-group-item d-flex justify-content-between px-0">
            <span class="text-muted"><i class="bi bi-telephone me-2"></i>Phone:</span>
            <span class="fw-semibold" id="view_phone"></span>
          </li>
          <li class="list-group-item d-flex justify-content-between px-0">
            <span class="text-muted"><i class="bi bi-envelope me-2"></i>Email:</span>
            <span class="fw-semibold" id="view_email"></span>
          </li>
          <li class="list-group-item d-flex justify-content-between px-0">
            <span class="text-muted"><i class="bi bi-building me-2"></i>Total Projects:</span>
            <span class="badge bg-info text-dark" id="view_projects"></span>
          </li>
          <li class="list-group-item d-flex justify-content-between px-0">
            <span class="text-muted"><i class="bi bi-calendar3 me-2"></i>Registered On:</span>
            <span class="fw-semibold" id="view_created"></span>
          </li>
          <li class="list-group-item px-0">
            <span class="text-muted d-block mb-1"><i class="bi bi-geo-alt me-2"></i>Address:</span>
            <p class="mb-0 text-dark small p-2 bg-light rounded" id="view_address"></p>
          </li>
        </ul>
      </div>
      <div class="modal-footer bg-light justify-content-between">
        <a href="#" class="btn btn-sm btn-outline-primary" id="view_projects_link">
          <i class="bi bi-building me-1"></i>View Client's Projects
        </a>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: DELETE CONFIRMATION -->
<!-- ============================================================ -->
<div class="modal fade" id="deleteClientModal" tabindex="-1" aria-labelledby="deleteClientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title fw-bold" id="deleteClientModalLabel">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Deletion
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?= APP_URL ?>/clients.php" id="deleteClientForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="client_id" id="delete_client_id" value="">
        <div class="modal-body p-4">
          <p class="mb-3">
            Are you sure you want to delete client <strong id="delete_client_name" class="text-danger"></strong>?
          </p>
          <div id="deleteWarningProjects" class="alert alert-warning d-none mb-0">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <strong>Warning:</strong> This client has <span id="delete_projects_count" class="fw-bold"></span> project(s) assigned.
            Database integrity prevents deleting clients with active projects.
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger" id="deleteConfirmBtn">
            <i class="bi bi-trash-fill me-1"></i> Yes, Delete Client
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // 1. Edit Client Modal Handler
  const editModal = new bootstrap.Modal(document.getElementById('editClientModal'));
  document.querySelectorAll('.btn-edit-client').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('edit_client_id').value = this.dataset.id;
      document.getElementById('edit_name').value      = this.dataset.name;
      document.getElementById('edit_phone').value     = this.dataset.phone;
      document.getElementById('edit_email').value     = this.dataset.email;
      document.getElementById('edit_address').value   = this.dataset.address;
      editModal.show();
    });
  });

  // 2. View Client Modal Handler
  const viewModal = new bootstrap.Modal(document.getElementById('viewClientModal'));
  document.querySelectorAll('.btn-view-client').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const name = this.dataset.name;
      document.getElementById('view_name').textContent     = name;
      document.getElementById('view_phone').textContent    = this.dataset.phone;
      document.getElementById('view_email').textContent    = this.dataset.email;
      document.getElementById('view_address').textContent  = this.dataset.address;
      document.getElementById('view_projects').textContent = this.dataset.projects + ' Projects';
      document.getElementById('view_created').textContent  = this.dataset.created;
      document.getElementById('view_avatar').textContent   = (name || 'C').charAt(0).toUpperCase();
      document.getElementById('view_projects_link').href   = '<?= APP_URL ?>/projects.php?client_id=' + this.dataset.id;
      viewModal.show();
    });
  });

  // 3. Delete Confirmation Modal Handler
  const deleteModal = new bootstrap.Modal(document.getElementById('deleteClientModal'));
  document.querySelectorAll('.btn-delete-client').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const clientId = this.dataset.id;
      const clientName = this.dataset.name;
      const projectsCount = parseInt(this.dataset.projects || '0', 10);

      document.getElementById('delete_client_id').value     = clientId;
      document.getElementById('delete_client_name').textContent = clientName;

      const warningEl = document.getElementById('deleteWarningProjects');
      const submitBtn = document.getElementById('deleteConfirmBtn');

      if (projectsCount > 0) {
        warningEl.classList.remove('d-none');
        document.getElementById('delete_projects_count').textContent = projectsCount;
        submitBtn.disabled = true;
        submitBtn.title = 'Cannot delete client with linked projects';
      } else {
        warningEl.classList.add('d-none');
        submitBtn.disabled = false;
        submitBtn.title = '';
      }

      deleteModal.show();
    });
  });
});
</script>

<?php
require_once VIEW_PATH . '/layout/footer.php';
