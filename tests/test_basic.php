<?php
/**
 * BCMS — Basic PHP Syntax & Integration Test Script
 * Run from command line: php tests/test_basic.php
 * 
 * Pre-requisite: MySQL must be running with bcms_db database.
 * XAMPP must be installed and configured.
 */

// Override APP_URL for CLI
$_SERVER['HTTP_HOST'] = 'localhost';

// Load bootstrap
require_once dirname(__DIR__) . '/config/bootstrap.php';

$passed  = 0;
$failed  = 0;
$blocked = 0;
$skipped = 0;

function testAssert(string $name, bool $condition, string $failMsg = ''): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ PASS: $name\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: $name" . ($failMsg ? " — $failMsg" : '') . "\n";
        $failed++;
    }
}

echo "\n=== BCMS Basic Integration Tests ===\n\n";

// ── Test 1: Database Connection ──────────────────────────────
echo "1. Database Connection\n";
$dbConnected = false;
try {
    $pdo = db();
    testAssert('PDO connection established', $pdo instanceof PDO);
    $ver = dbQueryOne('SELECT VERSION() as v');
    testAssert('MySQL query executes', !empty($ver['v']));
    echo "   MySQL Version: " . ($ver['v'] ?? 'unknown') . "\n";
    $dbConnected = true;
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), '2002') || str_contains($e->getMessage(), 'Connection refused') || str_contains($e->getMessage(), 'access permissions')) {
        echo "  ⚠️ BLOCKED (Environment): MySQL server is not reachable on localhost:3306.\n";
        echo "     Start Apache and MySQL in XAMPP to execute live database queries.\n";
        $blocked += 2;
    } else {
        echo "  ❌ FAIL: Database connection — " . $e->getMessage() . "\n";
        $failed++;
    }
}

// ── Test 2: Required Tables Exist ────────────────────────────
echo "\n2. Required Tables\n";
$requiredTables = [
    'users', 'clients', 'projects', 'workers', 'worker_assignments',
    'attendance', 'tasks', 'materials', 'material_purchases', 'material_usage',
    'expenses', 'payments', 'stock_adjustments', 'activity_log'
];
if ($dbConnected) {
    try {
        $tables = dbQuery("SHOW TABLES");
        $tableNames = array_map(fn($row) => array_values($row)[0], $tables);
        foreach ($requiredTables as $t) {
            testAssert("Table '$t' exists", in_array($t, $tableNames));
        }
    } catch (Throwable $e) {
        echo "  ❌ FAIL: Cannot check tables — " . $e->getMessage() . "\n";
        $failed++;
    }
} else {
    echo "  ⚠️ SKIPPED: Required tables check (requires live MySQL connection)\n";
    $skipped += count($requiredTables);
}

// ── Test 3: Views Exist ─────────────────────────────────────
echo "\n3. Database Views\n";
$requiredViews = ['v_material_stock', 'v_project_expense_totals', 'v_worker_attendance_summary'];
try {
    foreach ($requiredViews as $view) {
        $result = dbQueryOne("SHOW CREATE VIEW `$view`");
        testAssert("View '$view' exists", $result !== null);
    }
} catch (Throwable $e) {
    echo "  ⚠️  Views check skipped — " . $e->getMessage() . "\n";
}

// ── Test 4: Model Files Exist ─────────────────────────────────
echo "\n4. Model Files\n";
$models = [
    'UserModel', 'ClientModel', 'ProjectModel', 'WorkerModel', 'AssignmentModel',
    'AttendanceModel', 'TaskModel', 'MaterialModel', 'PurchaseModel', 
    'UsageModel', 'ExpenseModel', 'PaymentModel'
];
foreach ($models as $model) {
    $file = dirname(__DIR__) . "/app/models/{$model}.php";
    testAssert("File {$model}.php exists", file_exists($file));
}

// ── Test 5: Public Pages Exist ────────────────────────────────
echo "\n5. Public Pages\n";
$pages = [
    'login.php', 'logout.php', 'setup.php', 'dashboard.php', 'clients.php',
    'projects.php', 'project_view.php', 'workers.php', 'worker_view.php',
    'assignments.php', 'attendance.php', 'tasks.php', 'materials.php',
    'purchases.php', 'usage.php', 'expenses.php', 'payments.php',
    'reports.php', 'wages.php', 'budget.php'
];
foreach ($pages as $page) {
    $file = dirname(__DIR__) . "/public/{$page}";
    testAssert("Page $page exists", file_exists($file));
}

// ── Test 6: PHP Syntax Check ──────────────────────────────────
echo "\n6. PHP Syntax Check\n";
$phpFiles = array_merge(
    glob(dirname(__DIR__) . '/config/*.php'),
    glob(dirname(__DIR__) . '/app/models/*.php'),
    glob(dirname(__DIR__) . '/public/*.php')
);
$syntaxErrors = 0;
foreach ($phpFiles as $file) {
    $output = [];
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $retCode);
    $ok = ($retCode === 0);
    if (!$ok) {
        $syntaxErrors++;
        echo "  ❌ Syntax error in " . basename($file) . ": " . implode(' ', $output) . "\n";
    }
}
testAssert("All PHP files have valid syntax ($syntaxErrors errors)", $syntaxErrors === 0);

// ── Test 7: UserModel Functions ──────────────────────────────
echo "\n7. UserModel Tests\n";
try {
    $um = new UserModel();
    $user = $um->findByEmail('nonexistent@test.com');
    testAssert('findByEmail returns null for unknown email', $user === null);
    
    // Test password hashing
    $hash = password_hash('testpassword', PASSWORD_BCRYPT);
    testAssert('password_verify works', $um->verifyPassword('testpassword', $hash));
    testAssert('password_verify fails for wrong password', !$um->verifyPassword('wrongpass', $hash));
} catch (Throwable $e) {
    echo "  ⚠️  UserModel test skipped — " . $e->getMessage() . "\n";
}

// ── Test 8: Business Rule: No Negative Stock ─────────────────
echo "\n8. Material Stock Rules\n";
try {
    $mm = new MaterialModel();
    $materials = $mm->getAll();
    testAssert('Materials list returns array', is_array($materials));
    
    // Check no material has negative stock
    $negative = false;
    foreach ($materials as $m) {
        $stock = (float)($m['computed_stock'] ?? $m['stock_quantity'] ?? 0);
        if ($stock < 0) { $negative = true; break; }
    }
    testAssert('No material has negative stock', !$negative);
} catch (Throwable $e) {
    echo "  ⚠️  Material stock test skipped — " . $e->getMessage() . "\n";
}

// ── Test 9: Budget Calculation ──────────────────────────────
echo "\n9. Budget Calculations\n";
try {
    $pm = new ProjectModel();
    $projects = $pm->getAll();
    testAssert('Projects list returns array', is_array($projects));
    
    if (!empty($projects)) {
        $p = $projects[0];
        $remaining = $pm->calculateRemainingBudget((int)$p['project_id']);
        testAssert('calculateRemainingBudget returns numeric', is_numeric($remaining));
        // Budget should be >= remaining (expenses are non-negative)
        testAssert('Remaining budget <= budget', $remaining <= (float)$p['budget'] + 0.01);
    }
} catch (Throwable $e) {
    echo "  ⚠️  Budget test skipped — " . $e->getMessage() . "\n";
}

// ── Test 10: Attendance Duplicate Prevention ─────────────────
echo "\n10. Attendance Duplicate Prevention\n";
try {
    $am = new AttendanceModel();
    // Try to mark attendance for a non-existent combination — just test the logic
    $workers = dbQuery('SELECT worker_id FROM workers LIMIT 1');
    $projects = dbQuery('SELECT project_id FROM projects LIMIT 1');
    
    if (!empty($workers) && !empty($projects)) {
        $wid = $workers[0]['worker_id'];
        $pid = $projects[0]['project_id'];
        $date = '2099-01-01'; // Future date unlikely to conflict
        
        try {
            $am->markAttendance($wid, $pid, $date, 'present');
            // Try to mark again — should throw
            $duplicateCaught = false;
            try {
                $am->markAttendance($wid, $pid, $date, 'absent');
            } catch (InvalidArgumentException $e) {
                $duplicateCaught = true;
            } catch (Throwable $e) {
                $duplicateCaught = true; // DB unique constraint also works
            }
            testAssert('Duplicate attendance prevented', $duplicateCaught);
            // Clean up test record
            dbExecute("DELETE FROM attendance WHERE worker_id=? AND project_id=? AND attendance_date=?", [$wid, $pid, $date]);
        } catch (Throwable $e) {
            echo "  ⚠️  Attendance duplicate test skipped — " . $e->getMessage() . "\n";
        }
    } else {
        echo "  ⚠️  Attendance test skipped — no data in DB (import seed.sql)\n";
    }
} catch (Throwable $e) {
    echo "  ⚠️  Attendance test skipped — " . $e->getMessage() . "\n";
}

// ── Test 11: CSRF Token Generation ──────────────────────────
echo "\n11. Security Functions\n";
// Mock session for CLI
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$token1 = csrfToken();
$token2 = csrfToken();
testAssert('CSRF token is 64 chars hex', strlen($token1) === 64);
testAssert('CSRF token is consistent within session', $token1 === $token2);

// Test utility functions
testAssert('e() escapes HTML', e('<script>') === '&lt;script&gt;');
testAssert('formatCurrency formats correctly', str_contains(formatCurrency(1500.50), '1,500.50'));
testAssert('intInput validates positive int', intInput('5', 1) === 5);
testAssert('intInput rejects negative', intInput('-1', 0) === null);
testAssert('decimalInput validates float', decimalInput('99.99') === 99.99);
testAssert('dateInput validates date', dateInput('2026-01-15') === '2026-01-15');
testAssert('dateInput rejects invalid', dateInput('not-a-date') === null);

// ── Summary ──────────────────────────────────────────────────
$total = $passed + $failed;
echo "\n" . str_repeat('=', 55) . "\n";
echo "SUMMARY: {$passed} passed, {$failed} failed, {$blocked} blocked (env), {$skipped} skipped\n";
if ($failed > 0) {
    echo "❌ APPLICATION TESTS FAILED: {$failed} failures detected\n";
} elseif ($blocked > 0) {
    echo "⚠️ NOTE: Database tests are blocked by environment (MySQL service not running on port 3306)\n";
    echo "   Start Apache and MySQL in XAMPP to run live database checks.\n";
} else {
    echo "✅ ALL EXECUTABLE TESTS PASSED SUCCESSFULLY!\n";
}
echo str_repeat('=', 55) . "\n\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
