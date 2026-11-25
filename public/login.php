<?php
/**
 * BCMS Login Page
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$error = null;
$emailVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $email    = strInput($_POST['email'] ?? '', 200);
    $password = $_POST['password'] ?? '';
    $emailVal = $email;

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $userModel = new UserModel();
        $user = $userModel->findByEmail($email);

        if ($user && $user['is_active'] && $userModel->verifyPassword($password, $user['password'])) {
            // Regenerate session on login (security)
            session_regenerate_id(true);

            $_SESSION['user'] = [
                'user_id'   => $user['user_id'],
                'name'      => $user['name'],
                'email'     => $user['email'],
                'role'      => $user['role'],
                'linked_id' => $user['linked_id'],
            ];

            logActivity('login', 'auth', $user['user_id'], 'User logged in');
            setFlash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect('/dashboard.php');
        } else {
            $error = 'Invalid email or password. Please try again.';
            // Slight delay to slow brute-force
            sleep(1);
        }
    }
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to Building Construction Management System">
    <title>Login | <?= e(APP_NAME) ?></title>
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

    <div class="login-wrapper">
        <div class="login-card">
            <!-- Logo -->
            <div class="login-logo">
                <div class="login-logo-icon">
                    <i class="bi bi-buildings-fill"></i>
                </div>
                <h1 class="login-title">BCMS</h1>
                <p class="login-subtitle">Building Construction Management System</p>
            </div>

            <!-- Flash success -->
            <?php if ($flashSuccess = getFlash('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= e($flashSuccess) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Flash error -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= e($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="<?= APP_URL ?>/login.php" novalidate id="loginForm">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">
                        <i class="bi bi-envelope me-1"></i>Email Address
                    </label>
                    <input
                        type="email"
                        class="form-control form-control-lg"
                        id="email"
                        name="email"
                        value="<?= e($emailVal) ?>"
                        placeholder="Enter your email"
                        required
                        autocomplete="email"
                    >
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">
                        <i class="bi bi-lock me-1"></i>Password
                    </label>
                    <div class="input-group">
                        <input
                            type="password"
                            class="form-control form-control-lg"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" title="Show/hide password">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary btn-lg fw-semibold" id="loginBtn">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Log In
                    </button>
                </div>
            </form>

            <div class="text-center my-3">
                <span class="text-muted small">Don't have an account? </span>
                <a href="<?= APP_URL ?>/register.php" class="small fw-semibold text-primary text-decoration-none">
                    Register as Client or Worker <i class="bi bi-arrow-right-short"></i>
                </a>
            </div>

            <div class="login-card-footer text-center pt-3 border-top">
                <span class="text-muted small">
                    <i class="bi bi-shield-lock-fill text-success me-1"></i>Secure Enterprise Access
                </span>
            </div>
        </div>
        <div class="text-center text-white-50 small mt-3">
            &copy; <?= date('Y') ?> Building Construction Management System. All rights reserved.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= APP_URL ?>/assets/js/bcms.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                pwd.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });

        // Form submit loader
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...';
        });
    </script>
</body>
</html>
