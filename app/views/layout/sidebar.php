<?php
/**
 * BCMS — Sidebar Partial
 * Role-based navigation for Admin, Client, and Worker.
 */
declare(strict_types=1);

$user = currentUser() ?? [
    'name'  => 'Guest User',
    'email' => '',
    'role'  => 'worker',
];

$userRole = $user['role'] ?? 'worker';
$userName = $user['name'] ?? 'User';

// Role badge styling
$roleBadgeClass = match ($userRole) {
    'admin'  => 'badge bg-danger',
    'client' => 'badge bg-primary',
    'worker' => 'badge bg-success',
    default  => 'badge bg-secondary',
};

// User initial for avatar
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'));

// Current route / active state detection
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
$currentUri    = $_SERVER['REQUEST_URI'] ?? '';
$activeKey     = strtolower((string)($currentPage ?? $activeNav ?? ''));
$pageTitleStr  = strtolower((string)($pageTitle ?? ''));

/**
 * Determine if nav item is currently active.
 */
$isItemActive = function (string $key) use ($activeKey, $currentScript, $currentUri, $pageTitleStr): bool {
    if (!empty($activeKey) && $activeKey === $key) {
        return true;
    }
    if ($currentScript === $key) {
        return true;
    }
    if (str_contains($currentUri, '/' . $key . '.php') || str_contains($currentUri, '/' . $key . '/')) {
        return true;
    }
    if (!empty($pageTitleStr) && (str_contains($pageTitleStr, $key) || ($key === 'projects' && str_contains($pageTitleStr, 'project')))) {
        return true;
    }
    return false;
};

/**
 * Helper to resolve page URL.
 */
$resolveUrl = function (string $slug): string {
    if ($slug === 'dashboard') {
        return APP_URL . '/dashboard.php';
    }
    if (file_exists(BASE_PATH . '/public/' . $slug . '/index.php')) {
        return APP_URL . '/' . $slug . '/index.php';
    }
    return APP_URL . '/' . $slug . '.php';
};

// Define menu items based on role
$menuItems = [];

if (hasRole(ROLE_ADMIN)) {
    $menuItems = [
        [
            'heading' => 'Core',
            'items'   => [
                ['key' => 'dashboard',   'label' => 'Dashboard',   'icon' => 'bi-speedometer2',           'url' => $resolveUrl('dashboard')],
            ],
        ],
        [
            'heading' => 'Management',
            'items'   => [
                ['key' => 'projects',    'label' => 'Projects',    'icon' => 'bi-building',               'url' => $resolveUrl('projects')],
                ['key' => 'clients',     'label' => 'Clients',     'icon' => 'bi-people',                 'url' => $resolveUrl('clients')],
                ['key' => 'workers',     'label' => 'Workers',     'icon' => 'bi-person-badge',           'url' => $resolveUrl('workers')],
                ['key' => 'assignments', 'label' => 'Assignments', 'icon' => 'bi-person-check',           'url' => $resolveUrl('assignments')],
                ['key' => 'attendance',  'label' => 'Attendance',  'icon' => 'bi-calendar-check',         'url' => $resolveUrl('attendance')],
                ['key' => 'tasks',       'label' => 'Tasks',       'icon' => 'bi-check2-square',          'url' => $resolveUrl('tasks')],
            ],
        ],
        [
            'heading' => 'Materials & Costs',
            'items'   => [
                ['key' => 'materials',   'label' => 'Materials',   'icon' => 'bi-box-seam',               'url' => $resolveUrl('materials')],
                ['key' => 'purchases',   'label' => 'Purchases',   'icon' => 'bi-cart-plus',              'url' => $resolveUrl('purchases')],
                ['key' => 'usage',       'label' => 'Usage',       'icon' => 'bi-arrow-up-right-circle',  'url' => $resolveUrl('usage')],
                ['key' => 'expenses',    'label' => 'Expenses',    'icon' => 'bi-cash-stack',             'url' => $resolveUrl('expenses')],
                ['key' => 'payments',    'label' => 'Payments',    'icon' => 'bi-credit-card-2-front',    'url' => $resolveUrl('payments')],
            ],
        ],
        [
            'heading' => 'Analytics & Finance',
            'items'   => [
                ['key' => 'budget',      'label' => 'Budget & Costs', 'icon' => 'bi-pie-chart',             'url' => $resolveUrl('budget')],
                ['key' => 'wages',       'label' => 'Labor Wages',    'icon' => 'bi-calculator',            'url' => $resolveUrl('wages')],
                ['key' => 'reports',     'label' => 'Reports Hub',    'icon' => 'bi-graph-up-arrow',         'url' => $resolveUrl('reports')],
            ],
        ],
    ];
} elseif (hasRole(ROLE_CLIENT)) {
    $menuItems = [
        [
            'heading' => 'Client Portal',
            'items'   => [
                ['key' => 'dashboard', 'label' => 'Dashboard',   'icon' => 'bi-speedometer2',        'url' => $resolveUrl('dashboard')],
                ['key' => 'projects',  'label' => 'My Projects', 'icon' => 'bi-building',            'url' => $resolveUrl('projects')],
                ['key' => 'payments',  'label' => 'My Payments', 'icon' => 'bi-credit-card-2-front', 'url' => $resolveUrl('payments')],
            ],
        ],
    ];
} elseif (hasRole(ROLE_WORKER)) {
    $workerId = (int)($user['linked_id'] ?? 0);
    $menuItems = [
        [
            'heading' => 'Worker Portal',
            'items'   => [
                ['key' => 'dashboard',   'label' => 'Dashboard',         'icon' => 'bi-speedometer2',        'url' => $resolveUrl('dashboard')],
                ['key' => 'tasks',       'label' => 'My Tasks',          'icon' => 'bi-check2-square',       'url' => $resolveUrl('tasks')],
                ['key' => 'attendance',  'label' => 'My Attendance',     'icon' => 'bi-calendar-check',      'url' => $resolveUrl('attendance')],
                ['key' => 'worker_view', 'label' => 'My Profile & Wages', 'icon' => 'bi-person-badge',        'url' => APP_URL . "/worker_view.php?id={$workerId}"],
            ],
        ],
    ];
}
?>

<!-- Mobile Sidebar Backdrop Overlay -->
<div class="bcms-sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- Sidebar Container -->
<aside class="bcms-sidebar" id="bcmsSidebar">
  <!-- Brand / Logo -->
  <div class="bcms-sidebar-brand">
    <a href="<?= APP_URL ?>/dashboard.php" class="bcms-brand-link">
      <div class="bcms-brand-logo" title="BCMS Construction">
        <!-- Construction Hardhat Icon -->
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 2C7.03 2 3 6.03 3 11c0 1.95.63 3.75 1.7 5.22-.38.56-.7 1.25-.7 2.03 0 1.52 1.23 2.75 2.75 2.75h14.5c1.52 0 2.75-1.23 2.75-2.75 0-.78-.32-1.47-.7-2.03C20.37 14.75 21 12.95 21 11c0-4.97-4.03-9-9-9zm-1 2.08c.32-.05.65-.08 1-.08s.68.03 1 .08v6.92h-2V4.08zM5 11c0-3.53 2.61-6.43 6-6.92v6.92H5zm14 0h-6V4.08c3.39.49 6 3.39 6 6.92zm-12.25 8c-.41 0-.75-.34-.75-.75 0-.41.34-.75.75-.75h12.5c.41 0 .75.34.75.75 0 .41-.34.75-.75.75H6.75z"/>
        </svg>
      </div>
      <div class="bcms-brand-text">
        <span class="bcms-brand-name">BC<span>MS</span></span>
        <span class="bcms-brand-desc">Construction ERP</span>
      </div>
    </a>
    <!-- Mobile Close Button -->
    <button type="button" class="btn btn-sm btn-link text-white-50 d-lg-none" id="sidebarCloseBtn" aria-label="Close sidebar">
      <i class="bi bi-x-lg fs-5"></i>
    </button>
  </div>

  <!-- Navigation Links -->
  <nav class="bcms-sidebar-nav" id="sidebarNav">
    <?php foreach ($menuItems as $group): ?>
      <?php if (!empty($group['heading'])): ?>
        <div class="bcms-nav-heading"><?= e($group['heading']) ?></div>
      <?php endif; ?>
      <ul class="bcms-nav-list">
        <?php foreach ($group['items'] as $item): ?>
          <?php $active = $isItemActive($item['key']); ?>
          <li class="bcms-nav-item">
            <a href="<?= e($item['url']) ?>" class="bcms-nav-link <?= $active ? 'active' : '' ?>">
              <i class="bi <?= e($item['icon']) ?> nav-icon"></i>
              <span><?= e($item['label']) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </nav>

  <!-- User Info & Logout (Bottom) -->
  <div class="bcms-sidebar-footer">
    <div class="bcms-user-avatar" title="<?= e($userName) ?>">
      <?= e($userInitial) ?>
    </div>
    <div class="bcms-user-info">
      <div class="bcms-user-name" title="<?= e($userName) ?>"><?= e($userName) ?></div>
      <span class="<?= $roleBadgeClass ?>"><?= ucfirst(e($userRole)) ?></span>
    </div>
    <a href="<?= APP_URL ?>/logout.php" class="bcms-sidebar-logout" title="Log Out" data-confirm="Are you sure you want to log out?">
      <i class="bi bi-box-arrow-right"></i>
    </a>
  </div>
</aside>
