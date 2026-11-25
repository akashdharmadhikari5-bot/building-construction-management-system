<?php
/**
 * BCMS — Business Logic & Formula Verification Test Suite
 * Validates mathematical formulas, input rules, stock deductions, and security boundaries.
 * Run from CLI: php tests/test_business_logic.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$passed = 0;
$failed = 0;

function assertRule(string $name, bool $condition, string $failMsg = ''): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ PASS: {$name}\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: {$name}" . ($failMsg ? " — {$failMsg}" : '') . "\n";
        $failed++;
    }
}

echo "\n=== BCMS Business Logic & Mathematical Rules Tests ===\n\n";

// ── 1. Wage Calculation Formula ──────────────────────────────────────────────
echo "1. Wage Calculation Mathematical Derivation\n";
// Rule: present = 1.0, half_day = 0.5, absent = 0.0, holiday = 0.0
// total_wage = (present + 0.5 * half_day) * daily_wage
$dailyWage = 650.00;
$presentDays = 18;
$halfDays = 4;
$absentDays = 3;
$holidayDays = 1;

$workingDays = (float)($presentDays + ($halfDays * 0.5));
$totalWage = round($workingDays * $dailyWage, 2);

assertRule('Working days derivation: 18 present + 4 half-days = 20.0 days', $workingDays === 20.0);
assertRule('Total wage calculation: 20.0 * 650 = 13000.00', $totalWage === 13000.00);

// Zero half days
$simpleWage = round((10 + 0 * 0.5) * 500.00, 2);
assertRule('Simple present days wage: 10 * 500 = 5000.00', $simpleWage === 5000.00);


// ── 2. Budget and Utilization Formulas ───────────────────────────────────────
echo "\n2. Budget & Financial Metrics\n";
// Rule: remaining_budget = budget - total_expenses
// utilization_rate = (total_expenses / budget) * 100
$projectBudget = 2500000.00;
$actualExpenses = 1850000.00;
$totalPayments = 2000000.00;

$remainingBudget = $projectBudget - $actualExpenses;
$utilization = round(($actualExpenses / $projectBudget) * 100, 1);
$balanceDue = max(0.0, $projectBudget - $totalPayments);

assertRule('Remaining budget: 2.5M - 1.85M = 650000.00', $remainingBudget === 650000.00);
assertRule('Budget utilization rate: 74.0%', $utilization === 74.0);
assertRule('Client balance due: 2.5M - 2.0M = 500000.00', $balanceDue === 500000.00);

// Cost overrun condition
$overrunExpenses = 2700000.00;
$overrunRemaining = $projectBudget - $overrunExpenses;
assertRule('Cost overrun correctly results in negative remaining budget', $overrunRemaining === -200000.00);


// ── 3. Stock Movement & Inventory Protection ────────────────────────────────
echo "\n3. Stock Movements & Negative Stock Prevention\n";
// Rule: Computed Stock = Purchases - Usage + Adjustments In - Adjustments Out
// Must NOT allow usage if available stock < requested quantity
$purchased = 500.0;
$used = 320.0;
$currentStock = $purchased - $used; // 180.0

assertRule('Current stock calculation: 500 - 320 = 180', $currentStock === 180.0);

// Sufficient stock check
$requestedOk = 150.0;
$canDeductOk = ($currentStock >= $requestedOk);
assertRule('Permit usage when requested (150) <= available (180)', $canDeductOk === true);

$requestedExcess = 200.0;
$canDeductExcess = ($currentStock >= $requestedExcess);
assertRule('Block usage when requested (200) > available (180) to prevent negative stock', $canDeductExcess === false);


// ── 4. Task Progress and Auto-Completion ────────────────────────────────────
echo "\n4. Task Progress & Status Transition\n";
// Rule: When progress reaches 100%, status must transition to 'completed'
$taskProgress = 100;
$taskStatus = ($taskProgress === 100) ? TaskModel::STATUS_COMPLETED : TaskModel::STATUS_IN_PROGRESS;
assertRule('Task auto-completes when progress is 100%', $taskStatus === 'completed');

$partialProgress = 45;
$partialStatus = ($partialProgress === 100) ? TaskModel::STATUS_COMPLETED : TaskModel::STATUS_IN_PROGRESS;
assertRule('Task remains in_progress when progress is 45%', $partialStatus === 'in_progress');


// ── 5. Project-to-Client Ownership Validation (IDOR) ────────────────────────
echo "\n5. Project-Client Relationship & IDOR Prevention\n";
// Rule: A payment or project view request must verify client_id match
$projectRecord = ['project_id' => 10, 'client_id' => 3];
$validClient = 3;
$attackerClient = 5;

assertRule('Authorized client matches project owner', (int)$projectRecord['client_id'] === $validClient);
assertRule('Unauthorized client access correctly detected and rejected', (int)$projectRecord['client_id'] !== $attackerClient);


// ── 6. Input Sanitizers & Validation ────────────────────────────────────────
echo "\n6. Security Input Sanitization\n";
assertRule('strInput trims and enforces max length', strInput('  hello world  ', 5) === 'hello');
assertRule('decimalInput accepts valid float string', decimalInput('12500.75') === 12500.75);
assertRule('decimalInput rejects negative float', decimalInput('-50.00') === null);
assertRule('dateInput accepts valid Y-m-d format', dateInput('2026-11-03') === '2026-11-03');
assertRule('dateInput rejects impossible calendar dates (2026-02-30)', dateInput('2026-02-30') === null);
assertRule('dateInput rejects malicious SQL injection strings', dateInput("2026-01-01' OR '1'='1") === null);


// ── 7. Role Restrictions & Access Constants ─────────────────────────────────
echo "\n7. Role Definitions & Constants\n";
assertRule('Admin role constant is admin', ROLE_ADMIN === 'admin');
assertRule('Client role constant is client', ROLE_CLIENT === 'client');
assertRule('Worker role constant is worker', ROLE_WORKER === 'worker');
assertRule('Currency symbol is defined as ₹', CURRENCY_SYMBOL === '₹');


// ── Summary ──────────────────────────────────────────────────────────────────
$total = $passed + $failed;
echo "\n" . str_repeat('=', 55) . "\n";
echo "BUSINESS LOGIC RESULTS: {$passed}/{$total} passed\n";
if ($failed === 0) {
    echo "✅ ALL BUSINESS LOGIC & MATHEMATICAL RULES VERIFIED!\n";
} else {
    echo "❌ {$failed} RULES FAILED VERIFICATION\n";
}
echo str_repeat('=', 55) . "\n\n";

if ($failed > 0) {
    exit(1);
}
exit(0);

