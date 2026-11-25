<?php
/**
 * BCMS — Self Registration for Client & Worker
 * Allows new clients and construction workers to register their profile and login credentials.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$error   = null;
$role    = strInput($_POST['role'] ?? 'client', 20);
$nameVal = '';
$emailVal = '';
$phoneVal = '';
$addressVal = '';
$skillVal = 'Mason';
$wageVal = '500.00';

$availableSkills = [
    'Mason'        => 'Mason (Rajmistri / Bricklayer)',
    'Carpenter'    => 'Carpenter (Badhai / Woodwork)',
    'Plumber'      => 'Plumber (Sanitary / Piping)',
    'Electrician'  => 'Electrician (Wiring / Power)',
    'Painter'      => 'Painter (Painting & Finishing)',
    'Steel Fixer'  => 'Steel Fixer (Bar Bender / Rebar)',
    'Welder'       => 'Welder (Fabrication)',
    'Tile Layer'   => 'Tile & Marble Layer',
    'Laborer'      => 'General Construction Laborer',
    'Site Supervisor' => 'Site Supervisor / Foreman',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $role       = strtolower(strInput($_POST['role'] ?? 'client', 20));
    $name       = strInput($_POST['name'] ?? '', 150);
    $email      = strInput($_POST['email'] ?? '', 200);
    $phone      = strInput($_POST['phone'] ?? '', 20);
    $password   = $_POST['password'] ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    // Role-specific inputs
    $address   = strInput($_POST['address'] ?? '', 1000);
    $skill     = strInput($_POST['skill'] ?? 'Mason', 100);
    $dailyWage = decimalInput($_POST['daily_wage'] ?? '500.00');

    // Retain form values in case of validation error
    $nameVal    = $name;
    $emailVal   = $email;
    $phoneVal   = $phone;
    $addressVal = $address;
    $skillVal   = $skill;
    $wageVal    = (string)($dailyWage ?? '500.00');

    if (!in_array($role, ['client', 'worker'], true)) {
        $error = 'Please select a valid account type (Client or Worker).';
    } elseif ($name === '' || $email === '' || $phone === '') {
        $error = 'Name, email address, and phone number are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPwd) {
        $error = 'Password and confirmation password do not match.';
    } elseif ($role === 'client' && $address === '') {
        $error = 'Please enter your project site or contact address.';
    } elseif ($role === 'worker' && ($dailyWage === null || $dailyWage < 0)) {
        $error = 'Please provide a valid expected daily wage (0 or greater).';
    } else {
        $userModel = new UserModel();
        $existing  = $userModel->findByEmail($email);

        if ($existing !== null) {
            $error = 'An account with this email address already exists. Please log in.';
        } else {
            try {
                $pdo = db();
                $pdo->beginTransaction();

                $linkedId = 0;

                if ($role === 'client') {
                    $stmtClient = $pdo->prepare(
                        'INSERT INTO clients (name, phone, email, address) VALUES (?, ?, ?, ?)'
                    );
                    $stmtClient->execute([$name, $phone, $email, $address]);
                    $linkedId = (int)$pdo->lastInsertId();
                } else {
                    $stmtWorker = $pdo->prepare(
                        'INSERT INTO workers (name, phone, skill, daily_wage, is_active) VALUES (?, ?, ?, ?, 1)'
                    );
                    $stmtWorker->execute([$name, $phone, $skill, $dailyWage ?? 500.00]);
                    $linkedId = (int)$pdo->lastInsertId();
                }

                // Create user login record
                $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmtUser = $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, linked_id, is_active)
                     VALUES (?, ?, ?, ?, ?, 1)'
                );
                $stmtUser->execute([$name, $email, $passwordHash, $role, $linkedId]);
                $newUserId = (int)$pdo->lastInsertId();

                $pdo->commit();

                logActivity('self_register', 'users', $newUserId, "Self-registered new {$role}: {$name} ({$email})");
                setFlash('success', "Account created successfully! You can now log in as a {$role}.");
                redirect('/login.php');
            } catch (Throwable $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Create Account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register an account on Building Construction Management System">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <!-- Anti-FOUC Early Theme Script -->
    <script>
    (function() {
      try {
        const savedTheme = localStorage.getItem('bcms_theme') || 
          (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
        document.documentElement.setAttribute('data-theme', savedTheme);
        if (savedTheme === 'dark') {
          document.documentElement.classList.add('dark-theme');
        }
      } catch (e) {}
    })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/bcms.css" rel="stylesheet">
</head>
<body class="login-page">
    <!-- Floating Dark / Light Mode Toggle -->
    <div class="login-theme-toggle">
        <button type="button" class="bcms-theme-toggle-btn btn-theme-glass" id="themeToggleBtn" aria-label="Toggle dark/light theme" title="Toggle Dark / Light Mode">
            <i class="bi bi-moon-stars-fill theme-icon-dark"></i>
            <i class="bi bi-sun-fill theme-icon-light d-none"></i>
        </button>
    </div>

    <div class="login-wrapper" style="max-width: 540px;">
        <div class="login-card">
            <!-- Brand Logo -->
            <div class="login-logo mb-3">
                <div class="login-logo-icon">
                    <i class="bi bi-buildings-fill"></i>
                </div>
                <h1 class="login-title fs-3">Join BCMS</h1>
                <p class="login-subtitle">Register a new Client or Worker profile</p>
            </div>

            <!-- Error Notification -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= e($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= APP_URL ?>/register.php" novalidate id="registerForm">
                <?= csrfField() ?>

                <!-- Role Selection -->
                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase tracking-wider text-muted mb-2">
                        Account Type <span class="text-danger">*</span>
                    </label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input
                                type="radio"
                                class="btn-check"
                                name="role"
                                id="roleClient"
                                value="client"
                                <?= ($role === 'client') ? 'checked' : '' ?>
                                onchange="switchRole('client')"
                            >
                            <label class="btn btn-outline-primary w-100 py-2 d-flex flex-column align-items-center" for="roleClient">
                                <i class="bi bi-person-badge fs-4 mb-1"></i>
                                <span class="fw-semibold">Client (Owner)</span>
                                <small class="text-muted" style="font-size: 0.75rem;">Site & Project Owner</small>
                            </label>
                        </div>
                        <div class="col-6">
                            <input
                                type="radio"
                                class="btn-check"
                                name="role"
                                id="roleWorker"
                                value="worker"
                                <?= ($role === 'worker') ? 'checked' : '' ?>
                                onchange="switchRole('worker')"
                            >
                            <label class="btn btn-outline-success w-100 py-2 d-flex flex-column align-items-center" for="roleWorker">
                                <i class="bi bi-tools fs-4 mb-1"></i>
                                <span class="fw-semibold">Worker / Tradesperson</span>
                                <small class="text-muted" style="font-size: 0.75rem;">Laborer / Contractor</small>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <!-- Full Name -->
                    <div class="col-12">
                        <label for="name" class="form-label fw-semibold small">
                            Full Name <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                            <input
                                type="text"
                                class="form-control"
                                id="name"
                                name="name"
                                value="<?= e($nameVal) ?>"
                                placeholder="e.g. Ramesh Sharma"
                                required
                            >
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div class="col-md-6">
                        <label for="email" class="form-label fw-semibold small">
                            Email Address <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                value="<?= e($emailVal) ?>"
                                placeholder="name@example.com"
                                required
                            >
                        </div>
                    </div>

                    <!-- Phone Number -->
                    <div class="col-md-6">
                        <label for="phone" class="form-label fw-semibold small">
                            Phone Number <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone"></i></span>
                            <input
                                type="tel"
                                class="form-control"
                                id="phone"
                                name="phone"
                                value="<?= e($phoneVal) ?>"
                                placeholder="9876543210"
                                required
                            >
                        </div>
                    </div>

                    <!-- Client Specific: Address -->
                    <div class="col-12" id="clientFields" style="<?= ($role === 'worker') ? 'display: none;' : '' ?>">
                        <label for="address" class="form-label fw-semibold small">
                            Site / Office Address <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-geo-alt"></i></span>
                            <textarea
                                class="form-control"
                                id="address"
                                name="address"
                                rows="2"
                                placeholder="Enter street, city, pin code..."
                            ><?= e($addressVal) ?></textarea>
                        </div>
                    </div>

                    <!-- Worker Specific: Skill & Daily Wage -->
                    <div class="col-12" id="workerFields" style="<?= ($role === 'worker') ? '' : 'display: none;' ?>">
                        <div class="row g-2">
                            <div class="col-md-7">
                                <label for="skill" class="form-label fw-semibold small">
                                    Primary Trade / Skill <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="skill" name="skill">
                                    <?php foreach ($availableSkills as $sKey => $sLabel): ?>
                                        <option value="<?= e($sKey) ?>" <?= ($skillVal === $sKey) ? 'selected' : '' ?>>
                                            <?= e($sLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="daily_wage" class="form-label fw-semibold small">
                                    Expected Daily Wage (<?= CURRENCY_SYMBOL ?>)
                                </label>
                                <input
                                    type="number"
                                    step="10"
                                    min="0"
                                    class="form-control"
                                    id="daily_wage"
                                    name="daily_wage"
                                    value="<?= e($wageVal) ?>"
                                    placeholder="500.00"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="col-md-6">
                        <label for="password" class="form-label fw-semibold small">
                            Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                placeholder="Min. 8 characters"
                                required
                            >
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('password', 'eye1')">
                                <i class="bi bi-eye" id="eye1"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label fw-semibold small">
                            Confirm Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input
                                type="password"
                                class="form-control"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Re-enter password"
                                required
                            >
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('confirm_password', 'eye2')">
                                <i class="bi bi-eye" id="eye2"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg fw-semibold shadow-sm" id="registerBtn">
                        <i class="bi bi-check2-circle me-1"></i>Create Account
                    </button>
                </div>
            </form>

            <div class="text-center mt-3 pt-3 border-top">
                <span class="text-muted small">Already have an account? </span>
                <a href="<?= APP_URL ?>/login.php" class="small fw-semibold text-primary text-decoration-none">
                    Log in here <i class="bi bi-box-arrow-in-right"></i>
                </a>
            </div>
        </div>

        <div class="text-center text-white-50 small mt-3">
            &copy; <?= date('Y') ?> Building Construction Management System
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= APP_URL ?>/assets/js/bcms.js"></script>
    <script>
        function switchRole(role) {
            const clientFields = document.getElementById('clientFields');
            const workerFields = document.getElementById('workerFields');
            const submitBtn    = document.getElementById('registerBtn');

            if (role === 'worker') {
                clientFields.style.display = 'none';
                workerFields.style.display = 'block';
                submitBtn.innerHTML = '<i class="bi bi-tools me-1"></i>Register as Worker';
                submitBtn.className = 'btn btn-success btn-lg fw-semibold shadow-sm';
            } else {
                clientFields.style.display = 'block';
                workerFields.style.display = 'none';
                submitBtn.innerHTML = '<i class="bi bi-person-badge me-1"></i>Register as Client';
                submitBtn.className = 'btn btn-primary btn-lg fw-semibold shadow-sm';
            }
        }

        function togglePwd(inputId, iconId) {
            const inp = document.getElementById(inputId);
            const ico = document.getElementById(iconId);
            if (inp.type === 'password') {
                inp.type = 'text';
                ico.className = 'bi bi-eye-slash';
            } else {
                inp.type = 'password';
                ico.className = 'bi bi-eye';
            }
        }

        document.getElementById('registerForm').addEventListener('submit', function() {
            const btn = document.getElementById('registerBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating Account...';
        });

        // Initialize button label on load
        const checkedRole = document.querySelector('input[name="role"]:checked')?.value || 'client';
        switchRole(checkedRole);
    </script>
</body>
</html>

