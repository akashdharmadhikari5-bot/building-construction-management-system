<?php
/**
 * BCMS — Top Navigation Bar Partial
 * Includes sidebar toggle, page title, low-stock notifications (admin only), user info, and logout.
 */
declare(strict_types=1);

$user = currentUser() ?? [
    'name'  => 'User',
    'role'  => 'worker',
    'email' => '',
];

$userName  = $user['name'] ?? 'User';
$userRole  = $user['role'] ?? 'worker';
$pageTitle = $pageTitle ?? 'Dashboard';

// Role badge class
$roleBadgeClass = match ($userRole) {
    'admin'  => 'badge bg-danger',
    'client' => 'badge bg-primary',
    'worker' => 'badge bg-success',
    default  => 'badge bg-secondary',
};

// Initial for user avatar
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'));

// Low stock count: only query if logged in user is admin
$lowStockCount = 0;
if (hasRole(ROLE_ADMIN)) {
    try {
        $result = dbQueryOne(
            "SELECT COUNT(*) AS total FROM materials WHERE stock_quantity <= low_stock_threshold"
        );
        $lowStockCount = (int)($result['total'] ?? 0);
    } catch (Throwable) {
        $lowStockCount = 0;
    }
}
?>

<header class="bcms-topnav" id="bcmsTopnav">
  <!-- Left Side: Toggle & Title -->
  <div class="bcms-topnav-left">
    <button type="button" class="bcms-menu-btn" id="sidebarToggle" aria-label="Toggle navigation menu" title="Toggle Menu">
      <i class="bi bi-list"></i>
    </button>
    <div class="bcms-page-title-box">
      <h1 class="bcms-page-title"><?= e($pageTitle) ?></h1>
      <span class="bcms-page-subtitle"><?= e(APP_NAME) ?></span>
    </div>
  </div>

  <!-- Right Side: Action Button, Notifications, User, Logout -->
  <div class="bcms-topnav-right">
    <?php if (!empty($topNavAction)): ?>
      <div class="bcms-topnav-action me-2 d-none d-sm-inline-block">
        <?= $topNavAction ?>
      </div>
    <?php endif; ?>

    <!-- Low Stock Notification (Admin Only) -->
    <?php if (hasRole(ROLE_ADMIN)): ?>
      <div class="dropdown">
        <a
          href="<?= APP_URL ?>/materials.php"
          class="bcms-notification-btn"
          id="notifDropdown"
          data-bs-toggle="<?= $lowStockCount > 0 ? 'dropdown' : '' ?>"
          aria-expanded="false"
          title="<?= $lowStockCount > 0 ? $lowStockCount . ' Low Stock Material Alert(s)' : 'No low stock alerts' ?>"
        >
          <i class="bi bi-bell"></i>
          <?php if ($lowStockCount > 0): ?>
            <span class="bcms-notification-badge" id="lowStockBadge"><?= $lowStockCount ?></span>
          <?php endif; ?>
        </a>

        <?php if ($lowStockCount > 0): ?>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-2" aria-labelledby="notifDropdown" style="width: 280px;">
            <li class="dropdown-header text-uppercase fw-bold text-danger px-2 pb-2">
              <i class="bi bi-exclamation-triangle-fill me-1"></i>Low Stock Alert
            </li>
            <li>
              <div class="alert alert-warning py-2 px-2 mb-2 small">
                There are <strong><?= $lowStockCount ?></strong> material(s) below reorder threshold.
              </div>
            </li>
            <li>
              <a class="dropdown-item btn btn-sm btn-primary text-center text-white rounded py-2" href="<?= APP_URL ?>/materials.php">
                <i class="bi bi-box-seam me-1"></i>View Materials Catalog
              </a>
            </li>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Dark / Light Mode Toggle -->
    <button
      type="button"
      class="bcms-theme-toggle-btn"
      id="themeToggleBtn"
      aria-label="Toggle dark/light theme"
      title="Toggle Dark / Light Mode"
    >
      <i class="bi bi-moon-stars-fill theme-icon-dark"></i>
      <i class="bi bi-sun-fill theme-icon-light d-none"></i>
    </button>

    <!-- User Profile Pill -->
    <div class="bcms-topnav-user">
      <div class="bcms-topnav-avatar" title="<?= e($userName) ?>">
        <?= e($userInitial) ?>
      </div>
      <div class="bcms-topnav-user-meta">
        <span class="bcms-topnav-user-name" title="<?= e($userName) ?>"><?= e($userName) ?></span>
        <span class="<?= $roleBadgeClass ?>" style="font-size: 0.65rem; padding: 0.15em 0.5em;">
          <?= ucfirst(e($userRole)) ?>
        </span>
      </div>
    </div>

    <!-- Quick Logout -->
    <a
      href="<?= APP_URL ?>/logout.php"
      class="bcms-topnav-logout"
      title="Log Out"
      data-confirm="Are you sure you want to log out?"
    >
      <i class="bi bi-box-arrow-right"></i>
    </a>
  </div>
</header>
