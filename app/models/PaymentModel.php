<?php
/**
 * BCMS — PaymentModel
 * 
 * Handles payment recording and retrieval.
 * IMPORTANT: Verifies project-client relationship before recording payments.
 */

declare(strict_types=1);

class PaymentModel
{
    private array $validTypes = ['advance', 'milestone', 'final', 'partial'];
    private array $validModes = ['cash', 'bank_transfer', 'cheque', 'upi', 'other'];

    /**
     * Get all payments with project and client names.
     */
    public function getAll(array $filters = []): array
    {
        $sql = "SELECT pay.*, p.project_name, c.name AS client_name
                FROM payments pay
                JOIN projects p ON p.project_id = pay.project_id
                JOIN clients c  ON c.client_id  = pay.client_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= " AND pay.project_id = ?";
            $params[] = $filters['project_id'];
        }
        if (!empty($filters['client_id'])) {
            $sql .= " AND pay.client_id = ?";
            $params[] = $filters['client_id'];
        }
        if (!empty($filters['payment_type'])) {
            $sql .= " AND pay.payment_type = ?";
            $params[] = $filters['payment_type'];
        }
        if (!empty($filters['from_date'])) {
            $sql .= " AND pay.payment_date >= ?";
            $params[] = $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $sql .= " AND pay.payment_date <= ?";
            $params[] = $filters['to_date'];
        }

        $sql .= " ORDER BY pay.payment_date DESC, pay.payment_id DESC";
        return dbQuery($sql, $params);
    }

    /**
     * Get payments for a specific project.
     */
    public function getByProject(int $projectId): array
    {
        return dbQuery(
            "SELECT pay.*, c.name AS client_name
             FROM payments pay
             JOIN clients c ON c.client_id = pay.client_id
             WHERE pay.project_id = ?
             ORDER BY pay.payment_date DESC",
            [$projectId]
        );
    }

    /**
     * Get payments for a specific client.
     */
    public function getByClient(int $clientId): array
    {
        return dbQuery(
            "SELECT pay.*, p.project_name
             FROM payments pay
             JOIN projects p ON p.project_id = pay.project_id
             WHERE pay.client_id = ?
             ORDER BY pay.payment_date DESC",
            [$clientId]
        );
    }

    /**
     * Get a single payment by ID.
     */
    public function getById(int $id): ?array
    {
        return dbQueryOne(
            "SELECT pay.*, p.project_name, c.name AS client_name
             FROM payments pay
             JOIN projects p ON p.project_id = pay.project_id
             JOIN clients c  ON c.client_id  = pay.client_id
             WHERE pay.payment_id = ?",
            [$id]
        );
    }

    /**
     * Record a new payment.
     * 
     * Validates all fields and verifies that the project belongs to the client
     * to prevent IDOR attacks.
     * 
     * @throws InvalidArgumentException
     */
    public function create(array $data): int
    {
        $projectId   = isset($data['project_id'])  ? (int)$data['project_id']  : 0;
        $clientId    = isset($data['client_id'])   ? (int)$data['client_id']   : 0;
        $amount      = isset($data['amount'])      ? (float)$data['amount']    : 0;
        $paymentDate = trim($data['payment_date']  ?? '');
        $paymentType = trim($data['payment_type']  ?? '');
        $paymentMode = trim($data['payment_mode']  ?? 'cash');
        $referenceNo = trim($data['reference_no']  ?? '');
        $notes       = trim($data['notes']         ?? '');
        $createdBy   = isset($data['created_by'])  ? (int)$data['created_by']  : null;

        // Validation
        if ($projectId <= 0) {
            throw new InvalidArgumentException('Invalid project.');
        }
        if ($clientId <= 0) {
            throw new InvalidArgumentException('Invalid client.');
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }
        if (empty($paymentDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            throw new InvalidArgumentException('Invalid payment date.');
        }
        if (!in_array($paymentType, $this->validTypes, true)) {
            throw new InvalidArgumentException('Invalid payment type.');
        }
        if (!in_array($paymentMode, $this->validModes, true)) {
            throw new InvalidArgumentException('Invalid payment mode.');
        }

        // IMPORTANT: Verify project belongs to the specified client (IDOR prevention)
        $project = dbQueryOne(
            'SELECT project_id, client_id FROM projects WHERE project_id = ?',
            [$projectId]
        );
        if (!$project) {
            throw new InvalidArgumentException('Project not found.');
        }
        if ((int)$project['client_id'] !== $clientId) {
            throw new InvalidArgumentException('Project does not belong to the specified client.');
        }

        return dbInsert(
            "INSERT INTO payments 
                (project_id, client_id, amount, payment_date, payment_type, payment_mode, reference_no, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $projectId, $clientId, $amount, $paymentDate,
                $paymentType, $paymentMode,
                $referenceNo ?: null,
                $notes ?: null,
                $createdBy
            ]
        );
    }

    /**
     * Get total payments received for a project.
     */
    public function getTotalByProject(int $projectId): float
    {
        $row = dbQueryOne(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE project_id = ?",
            [$projectId]
        );
        return (float)($row['total'] ?? 0);
    }

    /**
     * Get financial summary for a project.
     * Returns: total_payments, total_expenses, budget, remaining_budget, balance_due
     */
    public function getSummaryByProject(int $projectId): array
    {
        $row = dbQueryOne(
            "SELECT 
                p.budget,
                COALESCE((SELECT SUM(pay.amount) FROM payments pay WHERE pay.project_id = p.project_id), 0) AS total_payments,
                COALESCE((SELECT SUM(e.amount) FROM expenses e WHERE e.project_id = p.project_id), 0) AS total_expenses
             FROM projects p
             WHERE p.project_id = ?",
            [$projectId]
        );

        if (!$row) {
            return [
                'budget'          => 0,
                'total_payments'  => 0,
                'total_expenses'  => 0,
                'remaining_budget'=> 0,
                'balance_due'     => 0,
            ];
        }

        $budget          = (float)$row['budget'];
        $totalExpenses   = (float)$row['total_expenses'];
        $totalPayments   = (float)$row['total_payments'];
        $remainingBudget = $budget - $totalExpenses;
        $balanceDue      = $budget - $totalPayments;

        return [
            'budget'          => $budget,
            'total_expenses'  => $totalExpenses,
            'total_payments'  => $totalPayments,
            'remaining_budget'=> $remainingBudget,
            'balance_due'     => $balanceDue,
        ];
    }

    /**
     * Get recent payments across all projects.
     */
    public function getRecentPayments(int $limit = 10): array
    {
        return dbQuery(
            "SELECT pay.*, p.project_name, c.name AS client_name
             FROM payments pay
             JOIN projects p ON p.project_id = pay.project_id
             JOIN clients c  ON c.client_id  = pay.client_id
             ORDER BY pay.payment_date DESC, pay.payment_id DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Get payment totals by project for all projects.
     */
    public function getAllProjectPaymentTotals(): array
    {
        return dbQuery(
            "SELECT 
                p.project_id,
                p.project_name,
                p.budget,
                COALESCE(SUM(pay.amount), 0) AS total_payments
             FROM projects p
             LEFT JOIN payments pay ON pay.project_id = p.project_id
             GROUP BY p.project_id, p.project_name, p.budget
             ORDER BY p.project_name"
        );
    }
}
