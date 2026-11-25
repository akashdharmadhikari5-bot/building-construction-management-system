<?php
/**
 * BCMS — First-Run Setup Script
 * 
 * Run this ONCE after installing XAMPP and importing the database.
 * URL: http://localhost/bcms/public/setup.php
 * 
 * DELETE or RENAME this file after setup is complete.
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

$step    = (int)($_GET['step'] ?? 1);
$message = '';
$error   = '';
$success = false;

// Security check: Check if an admin account already exists
$adminExists = false;
try {
    $existingAdmin = dbQueryOne("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
    if ($existingAdmin !== null) {
        $adminExists = true;
    }
} catch (Throwable) {
    // Database or users table might not be initialized yet
}

// ── Step 1: DB connection test ──────────────────────────────
if ($step === 1) {
    try {
        $result = dbQueryOne('SELECT VERSION() AS ver');
        $message = 'MySQL connected successfully! Version: ' . ($result['ver'] ?? 'unknown');
        $success = true;
    } catch (Throwable $e) {
        $error = 'Database connection failed: ' . $e->getMessage();
    }
}

// ── Step 2: Create Admin Account ───────────────────────────
if ($step === 2) {
    if ($adminExists && !hasRole(ROLE_ADMIN)) {
        $error = 'Setup is locked: An administrator account already exists. Please log in instead of creating new accounts through setup.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validateCsrf();

        $name     = strInput($_POST['name'] ?? '', 150);
        $email    = strInput($_POST['email'] ?? '', 200);
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                // Check if user already exists
                $exists = dbQueryOne('SELECT user_id FROM users WHERE email = ?', [$email]);
                if ($exists) {
                    $error = 'A user with this email already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $id = dbInsert(
                        'INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)',
                        [$name, $email, $hash, 'admin', 1]
                    );
                    $message = "Admin account created! ID: $id. You can now log in.";
                    $success = true;
                    $adminExists = true;
                }
            } catch (Throwable $e) {
                $error = 'Error creating admin: ' . $e->getMessage();
            }
        }
    }
}

// ── Step 3: Import Schema Check ────────────────────────────
if ($step === 3) {
    try {
        $tables = dbQuery("SHOW TABLES");
        $tableNames = array_map('array_pop', $tables);
        $required = ['users','clients','projects','workers','worker_assignments',
                     'attendance','tasks','materials','material_purchases','material_usage',
                     'expenses','payments','stock_adjustments','activity_log'];
        $missing = array_diff($required, $tableNames);
        if (empty($missing)) {
            $message = 'All required tables exist! (' . count($tableNames) . ' tables found)';
            $success = true;
        } else {
            $error = 'Missing tables: ' . implode(', ', $missing) . 
                     '. Please import database/schema.sql first.';
        }
    } catch (Throwable $e) {
        $error = 'Error checking tables: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BCMS Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .setup-card { max-width: 640px; margin: 60px auto; }
        .setup-header { background: linear-gradient(135deg, #1a3a5c, #2d6a9f); color: white; border-radius: 12px 12px 0 0; padding: 30px; text-align: center; }
        .setup-body { background: white; border-radius: 0 0 12px 12px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<div class="setup-card">
    <div class="setup-header">
        <i class="bi bi-buildings-fill" style="font-size:3rem"></i>
        <h2 class="mt-2 mb-1">BCMS Setup Wizard</h2>
        <p class="mb-0 opacity-75">Building Construction Management System</p>
    </div>
    <div class="setup-body">

        <!-- Steps nav -->
        <div class="d-flex justify-content-between mb-4">
            <?php foreach (['1' => 'DB Test', '2' => 'Create Admin', '3' => 'Verify Tables'] as $s => $label): ?>
                <a href="?step=<?= $s ?>" class="btn btn-sm <?= $step == $s ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Step <?= $s ?>: <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $success ? 'success' : 'info' ?>">
                <i class="bi bi-<?= $success ? 'check-circle' : 'info-circle' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <h5>Step 1: Test Database Connection</h5>
            <p>This checks if PHP can connect to MySQL using your <code>.env</code> settings.</p>
            <p><strong>DB Host:</strong> <?= DB_HOST ?></p>
            <p><strong>DB Name:</strong> <?= DB_NAME ?></p>
            <p><strong>DB User:</strong> <?= DB_USER ?></p>
            <a href="?step=1" class="btn btn-primary me-2">Run Test</a>
            <?php if ($success): ?>
                <a href="?step=3" class="btn btn-outline-primary me-2">Verify Tables</a>
                <a href="?step=2" class="btn btn-success">Create Admin</a>
            <?php endif; ?>

        <?php elseif ($step === 2): ?>
            <h5>Step 2: Create Admin Account</h5>
            <?php if ($adminExists && !hasRole(ROLE_ADMIN)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-shield-lock-fill me-2"></i>
                    An administrator account already exists. For system security, further accounts cannot be created via the setup wizard.
                </div>
                <a href="<?= APP_URL ?>/login.php" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                </a>
            <?php else: ?>
                <p>Create your administrator account. This admin can manage all system data.</p>
                <form method="POST" action="?step=2">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password (min. 8 characters)</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="bi bi-person-check me-2"></i>Create Admin Account
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="mt-3">
                    <a href="<?= APP_URL ?>/login.php" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                    </a>
                </div>
            <?php endif; ?>

        <?php elseif ($step === 3): ?>
            <h5>Step 3: Verify Database Tables</h5>
            <p>Check that all required tables exist. If missing, import <code>database/schema.sql</code> via phpMyAdmin.</p>
            <p><strong>How to import:</strong><br>
               1. Open phpMyAdmin → <code>http://localhost/phpmyadmin</code><br>
               2. Create database <code>bcms_db</code><br>
               3. Click the database → Import → Choose <code>database/schema.sql</code><br>
               4. Click Go
            </p>
            <a href="?step=3" class="btn btn-primary">Check Tables</a>
        <?php endif; ?>

        <hr class="mt-4">
        <div class="alert alert-warning mt-3 mb-0">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Security:</strong> Delete or rename <code>setup.php</code> after setup is complete!
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
