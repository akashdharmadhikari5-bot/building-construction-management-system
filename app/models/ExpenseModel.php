<?php
/**
 * BCMS — Building Construction Management System
 * ExpenseModel: Manages construction project expenses, categorization, and monthly analytics.
 */

declare(strict_types=1);

class ExpenseModel
{
    public const TYPE_MATERIAL  = 'material';
    public const TYPE_LABOUR    = 'labour';
    public const TYPE_EQUIPMENT = 'equipment';
    public const TYPE_TRANSPORT = 'transport';
    public const TYPE_OTHER     = 'other';

    public const TYPES = [
        self::TYPE_MATERIAL,
        self::TYPE_LABOUR,
        self::TYPE_EQUIPMENT,
        self::TYPE_TRANSPORT,
        self::TYPE_OTHER,
    ];

    /**
     * Get all expenses with project names and optional filters.
     *
     * @param array<string, mixed> $filters Supports 'project_id', 'type', 'expense_type', 'from_date', 'to_date', 'search'
     * @return array<int, array<string, mixed>>
     */
    public function getAll(array $filters = []): array
    {
        $sql = 'SELECT e.*, p.project_name
                FROM expenses e
                JOIN projects p ON p.project_id = e.project_id';
        $params = [];
        $whereClauses = [];

        if (!empty($filters['project_id'])) {
            $whereClauses[] = 'e.project_id = ?';
            $params[] = (int)$filters['project_id'];
        }

        $typeFilter = $filters['expense_type'] ?? $filters['type'] ?? null;
        if (!empty($typeFilter) && in_array($typeFilter, self::TYPES, true)) {
            $whereClauses[] = 'e.expense_type = ?';
            $params[] = (string)$typeFilter;
        }

        if (!empty($filters['from_date'])) {
            $whereClauses[] = 'e.expense_date >= ?';
            $params[] = trim((string)$filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $whereClauses[] = 'e.expense_date <= ?';
            $params[] = trim((string)$filters['to_date']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $whereClauses[] = '(e.description LIKE ? OR p.project_name LIKE ?)';
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        $sql .= ' ORDER BY e.expense_date DESC, e.expense_id DESC';

        return dbQuery($sql, $params);
    }

    /**
     * Get all expenses for a specific project.
     *
     * @param int $projectId
     * @param array<string, mixed> $filters Optional filters (e.g. 'type', 'from_date', 'to_date')
     * @return array<int, array<string, mixed>>
     */
    public function getByProject(int $projectId, array $filters = []): array
    {
        if ($projectId <= 0) {
            return [];
        }

        $filters['project_id'] = $projectId;
        return $this->getAll($filters);
    }

    /**
     * Get a single expense record by ID with project name.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return dbQueryOne(
            'SELECT e.*, p.project_name
             FROM expenses e
             JOIN projects p ON p.project_id = e.project_id
             WHERE e.expense_id = ?
             LIMIT 1',
            [$id]
        );
    }

    /**
     * Record a new project expense.
     * Validates project_id, expense_type, amount > 0, expense_date.
     *
     * @param array<string, mixed> $data
     * @return int Inserted expense_id
     * @throws InvalidArgumentException
     */
    public function create(array $data): int
    {
        $projectId   = isset($data['project_id']) ? (int)$data['project_id'] : 0;
        $expenseType = trim((string)($data['expense_type'] ?? $data['type'] ?? ''));
        $amount      = isset($data['amount']) ? (float)$data['amount'] : 0.0;
        $expenseDate = trim((string)($data['expense_date'] ?? ''));
        $description = isset($data['description']) ? trim((string)$data['description']) : null;

        if ($projectId <= 0) {
            throw new InvalidArgumentException('A valid project ID is required.');
        }

        $project = dbQueryOne('SELECT project_id FROM projects WHERE project_id = ?', [$projectId]);
        if (!$project) {
            throw new InvalidArgumentException("Project #{$projectId} does not exist.");
        }

        if (!in_array($expenseType, self::TYPES, true)) {
            throw new InvalidArgumentException('Invalid expense type: ' . $expenseType);
        }

        if ($amount <= 0.0) {
            throw new InvalidArgumentException('Expense amount must be greater than zero.');
        }

        if ($expenseDate === '' || !strtotime($expenseDate)) {
            throw new InvalidArgumentException('A valid expense date is required.');
        }

        $createdBy = isset($_SESSION['user']['user_id']) ? (int)$_SESSION['user']['user_id'] : null;

        return dbInsert(
            'INSERT INTO expenses (project_id, expense_type, amount, expense_date, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$projectId, $expenseType, $amount, $expenseDate, $description, $createdBy]
        );
    }

    /**
     * Update an existing expense record.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     * @throws InvalidArgumentException
     */
    public function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid expense ID.');
        }

        $existing = $this->getById($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Expense not found.');
        }

        $fields = [];
        $params = [];

        if (array_key_exists('project_id', $data)) {
            $projectId = (int)$data['project_id'];
            if ($projectId <= 0) {
                throw new InvalidArgumentException('Invalid project ID.');
            }
            $project = dbQueryOne('SELECT project_id FROM projects WHERE project_id = ?', [$projectId]);
            if (!$project) {
                throw new InvalidArgumentException("Project #{$projectId} does not exist.");
            }
            $fields[] = 'project_id = ?';
            $params[] = $projectId;
        }

        if (array_key_exists('expense_type', $data) || array_key_exists('type', $data)) {
            $type = (string)($data['expense_type'] ?? $data['type']);
            if (!in_array($type, self::TYPES, true)) {
                throw new InvalidArgumentException('Invalid expense type: ' . $type);
            }
            $fields[] = 'expense_type = ?';
            $params[] = $type;
        }

        if (array_key_exists('amount', $data)) {
            $amount = (float)$data['amount'];
            if ($amount <= 0.0) {
                throw new InvalidArgumentException('Expense amount must be greater than zero.');
            }
            $fields[] = 'amount = ?';
            $params[] = $amount;
        }

        if (array_key_exists('expense_date', $data)) {
            $date = trim((string)$data['expense_date']);
            if ($date === '' || !strtotime($date)) {
                throw new InvalidArgumentException('A valid expense date is required.');
            }
            $fields[] = 'expense_date = ?';
            $params[] = $date;
        }

        if (array_key_exists('description', $data)) {
            $fields[] = 'description = ?';
            $params[] = $data['description'] !== null ? trim((string)$data['description']) : null;
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = 'UPDATE expenses SET ' . implode(', ', $fields) . ' WHERE expense_id = ?';
        dbExecute($sql, $params);

        return true;
    }

    /**
     * Delete an expense record by ID.
     *
     * @param int $id
     * @return bool
     * @throws InvalidArgumentException
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid expense ID.');
        }

        $affected = dbExecute('DELETE FROM expenses WHERE expense_id = ?', [$id]);
        return $affected > 0;
    }

    /**
     * Get total expenses amount for a project.
     *
     * @param int $projectId
     * @return float
     */
    public function getTotalByProject(int $projectId): float
    {
        if ($projectId <= 0) {
            return 0.0;
        }

        $row = dbQueryOne(
            'SELECT COALESCE(SUM(amount), 0) AS total
             FROM expenses
             WHERE project_id = ?',
            [$projectId]
        );

        return (float)($row['total'] ?? 0.0);
    }

    /**
     * Get total expenses grouped by expense type for a project.
     *
     * @param int $projectId
     * @return array<int, array<string, mixed>>
     */
    public function getTotalByType(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        return dbQuery(
            'SELECT expense_type, COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count
             FROM expenses
             WHERE project_id = ?
             GROUP BY expense_type
             ORDER BY total DESC',
            [$projectId]
        );
    }

    /**
     * Get monthly expense totals for a project (useful for charts).
     *
     * @param int $projectId
     * @return array<int, array<string, mixed>>
     */
    public function getMonthlyTotals(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        return dbQuery(
            'SELECT DATE_FORMAT(expense_date, "%Y-%m") AS month,
                    COALESCE(SUM(amount), 0) AS total
             FROM expenses
             WHERE project_id = ?
             GROUP BY DATE_FORMAT(expense_date, "%Y-%m")
             ORDER BY month ASC',
            [$projectId]
        );
    }
}
