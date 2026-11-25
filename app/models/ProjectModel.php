<?php
/**
 * BCMS — Building Construction Management System
 * ProjectModel: Manages construction projects, budget calculations, task-based progress, and project statistics.
 */

declare(strict_types=1);

class ProjectModel
{
    /**
     * Allowed project statuses.
     */
    public const STATUS_PLANNING  = 'planning';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_ON_HOLD   = 'on_hold';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PLANNING,
        self::STATUS_ACTIVE,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Get all projects with client details and optional filters.
     *
     * @param array<string, mixed> $filters Supports 'status', 'client_id', 'search'
     * @return array<int, array<string, mixed>>
     */
    public function getAll(array $filters = []): array
    {
        $sql = 'SELECT p.*, c.name AS client_name, c.email AS client_email, c.phone AS client_phone
                FROM projects p
                JOIN clients c ON c.client_id = p.client_id';
        $params = [];
        $whereClauses = [];

        if (!empty($filters['status'])) {
            $whereClauses[] = 'p.status = ?';
            $params[] = (string)$filters['status'];
        }

        if (!empty($filters['client_id'])) {
            $whereClauses[] = 'p.client_id = ?';
            $params[] = (int)$filters['client_id'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $whereClauses[] = '(p.project_name LIKE ? OR p.location LIKE ? OR c.name LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        $sql .= ' ORDER BY p.project_id DESC';

        return dbQuery($sql, $params);
    }

    /**
     * Get single project by ID with client information.
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
            'SELECT p.*, c.name AS client_name, c.email AS client_email, c.phone AS client_phone, c.address AS client_address
             FROM projects p
             JOIN clients c ON c.client_id = p.client_id
             WHERE p.project_id = ?
             LIMIT 1',
            [$id]
        );
    }

    /**
     * Create a new project.
     * Validates:
     * - budget > 0
     * - start_date < end_date
     * - client_id exists
     *
     * @param array<string, mixed> $data
     * @return int Inserted project_id
     * @throws InvalidArgumentException
     */
    public function create(array $data): int
    {
        $clientId    = isset($data['client_id']) ? (int)$data['client_id'] : 0;
        $projectName = trim((string)($data['project_name'] ?? ''));
        $location    = trim((string)($data['location'] ?? ''));
        $budget      = isset($data['budget']) ? (float)$data['budget'] : 0.0;
        $startDate   = trim((string)($data['start_date'] ?? ''));
        $endDate     = trim((string)($data['end_date'] ?? ''));
        $status      = trim((string)($data['status'] ?? self::STATUS_PLANNING));
        $progress    = isset($data['progress']) ? (int)$data['progress'] : 0;
        $description = isset($data['description']) ? trim((string)$data['description']) : null;

        if ($clientId <= 0) {
            throw new InvalidArgumentException('A valid client ID is required.');
        }

        // Validate client exists
        $clientExists = dbQueryOne('SELECT client_id FROM clients WHERE client_id = ?', [$clientId]);
        if (!$clientExists) {
            throw new InvalidArgumentException("Client with ID {$clientId} does not exist.");
        }

        if ($projectName === '') {
            throw new InvalidArgumentException('Project name is required.');
        }

        if ($location === '') {
            throw new InvalidArgumentException('Project location is required.');
        }

        if ($budget <= 0.0) {
            throw new InvalidArgumentException('Project budget must be greater than zero.');
        }

        if ($startDate === '' || !strtotime($startDate)) {
            throw new InvalidArgumentException('A valid start date is required.');
        }

        if ($endDate === '' || !strtotime($endDate)) {
            throw new InvalidArgumentException('A valid end date is required.');
        }

        if (strtotime($startDate) >= strtotime($endDate)) {
            throw new InvalidArgumentException('Start date must be earlier than the end date.');
        }

        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid project status: ' . $status);
        }

        if ($progress < 0 || $progress > 100) {
            throw new InvalidArgumentException('Project progress must be between 0 and 100.');
        }

        return dbInsert(
            'INSERT INTO projects (client_id, project_name, location, budget, start_date, end_date, status, progress, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$clientId, $projectName, $location, $budget, $startDate, $endDate, $status, $progress, $description]
        );
    }

    /**
     * Update an existing project.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     * @throws InvalidArgumentException
     */
    public function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid project ID.');
        }

        $existing = $this->getById($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Project not found.');
        }

        $fields = [];
        $params = [];

        if (array_key_exists('client_id', $data)) {
            $clientId = (int)$data['client_id'];
            if ($clientId <= 0) {
                throw new InvalidArgumentException('Invalid client ID.');
            }
            $clientExists = dbQueryOne('SELECT client_id FROM clients WHERE client_id = ?', [$clientId]);
            if (!$clientExists) {
                throw new InvalidArgumentException("Client with ID {$clientId} does not exist.");
            }
            $fields[] = 'client_id = ?';
            $params[] = $clientId;
        }

        if (array_key_exists('project_name', $data)) {
            $projectName = trim((string)$data['project_name']);
            if ($projectName === '') {
                throw new InvalidArgumentException('Project name cannot be empty.');
            }
            $fields[] = 'project_name = ?';
            $params[] = $projectName;
        }

        if (array_key_exists('location', $data)) {
            $location = trim((string)$data['location']);
            if ($location === '') {
                throw new InvalidArgumentException('Project location cannot be empty.');
            }
            $fields[] = 'location = ?';
            $params[] = $location;
        }

        if (array_key_exists('budget', $data)) {
            $budget = (float)$data['budget'];
            if ($budget <= 0.0) {
                throw new InvalidArgumentException('Project budget must be greater than zero.');
            }
            $fields[] = 'budget = ?';
            $params[] = $budget;
        }

        $startDate = array_key_exists('start_date', $data) ? trim((string)$data['start_date']) : $existing['start_date'];
        $endDate   = array_key_exists('end_date', $data)   ? trim((string)$data['end_date'])   : $existing['end_date'];

        if (array_key_exists('start_date', $data) || array_key_exists('end_date', $data)) {
            if (!strtotime($startDate) || !strtotime($endDate)) {
                throw new InvalidArgumentException('Valid start and end dates are required.');
            }
            if (strtotime($startDate) >= strtotime($endDate)) {
                throw new InvalidArgumentException('Start date must be earlier than the end date.');
            }
            if (array_key_exists('start_date', $data)) {
                $fields[] = 'start_date = ?';
                $params[] = $startDate;
            }
            if (array_key_exists('end_date', $data)) {
                $fields[] = 'end_date = ?';
                $params[] = $endDate;
            }
        }

        if (array_key_exists('status', $data)) {
            $status = trim((string)$data['status']);
            if (!in_array($status, self::STATUSES, true)) {
                throw new InvalidArgumentException('Invalid project status: ' . $status);
            }
            $fields[] = 'status = ?';
            $params[] = $status;
        }

        if (array_key_exists('progress', $data)) {
            $progress = (int)$data['progress'];
            if ($progress < 0 || $progress > 100) {
                throw new InvalidArgumentException('Progress must be between 0 and 100.');
            }
            $fields[] = 'progress = ?';
            $params[] = $progress;
        }

        if (array_key_exists('description', $data)) {
            $fields[] = 'description = ?';
            $params[] = $data['description'] !== null ? trim((string)$data['description']) : null;
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = 'UPDATE projects SET ' . implode(', ', $fields) . ' WHERE project_id = ?';
        dbExecute($sql, $params);

        return true;
    }

    /**
     * Delete a project and its directly associated records.
     *
     * @param int $id
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid project ID.');
        }

        $project = $this->getById($id);
        if ($project === null) {
            throw new InvalidArgumentException("Project #{$id} not found.");
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            dbExecute('DELETE FROM worker_assignments WHERE project_id = ?', [$id]);
            dbExecute('DELETE FROM attendance WHERE project_id = ?', [$id]);
            dbExecute('DELETE FROM tasks WHERE project_id = ?', [$id]);
            dbExecute('DELETE FROM material_purchases WHERE project_id = ?', [$id]);
            dbExecute('DELETE FROM material_usage WHERE project_id = ?', [$id]);
            dbExecute('DELETE FROM expenses WHERE project_id = ?', [$id]);
            dbExecute('DELETE FROM payments WHERE project_id = ?', [$id]);
            dbExecute('DELETE FROM projects WHERE project_id = ?', [$id]);

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException("Could not delete project #{$id}: " . $e->getMessage());
        }
    }

    /**
     * Update only project status.
     *
     * @param int $id
     * @param string $status
     * @return bool
     * @throws InvalidArgumentException
     */
    public function updateStatus(int $id, string $status): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid project ID.');
        }

        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid status: ' . $status);
        }

        $sql = 'UPDATE projects SET status = ?';
        $params = [$status];

        // If marked completed and progress is less than 100, set to 100
        if ($status === self::STATUS_COMPLETED) {
            $sql .= ', progress = 100';
        }

        $sql .= ' WHERE project_id = ?';
        $params[] = $id;

        $affected = dbExecute($sql, $params);
        return $affected > 0;
    }

    /**
     * Update project progress percentage (0-100).
     *
     * @param int $id
     * @param int $progress
     * @return bool
     * @throws InvalidArgumentException
     */
    public function updateProgress(int $id, int $progress): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid project ID.');
        }

        if ($progress < 0 || $progress > 100) {
            throw new InvalidArgumentException('Progress must be between 0 and 100.');
        }

        $sql = 'UPDATE projects SET progress = ?';
        $params = [$progress];

        if ($progress === 100) {
            $sql .= ', status = ?';
            $params[] = self::STATUS_COMPLETED;
        }

        $sql .= ' WHERE project_id = ?';
        $params[] = $id;

        $affected = dbExecute($sql, $params);
        return $affected > 0;
    }

    /**
     * Calculate remaining budget (budget minus total expenses).
     *
     * @param int $id
     * @return float
     */
    public function calculateRemainingBudget(int $id): float
    {
        if ($id <= 0) {
            return 0.0;
        }

        $row = dbQueryOne(
            'SELECT p.budget, COALESCE(SUM(e.amount), 0) AS total_expenses
             FROM projects p
             LEFT JOIN expenses e ON e.project_id = p.project_id
             WHERE p.project_id = ?
             GROUP BY p.project_id, p.budget',
            [$id]
        );

        if (!$row) {
            return 0.0;
        }

        return (float)($row['budget'] - $row['total_expenses']);
    }

    /**
     * Calculate overall project progress from average of linked task progress.
     * Updates project progress if tasks exist.
     *
     * @param int $id
     * @return int Average progress percentage (0-100)
     */
    public function calculateProgressFromTasks(int $id): int
    {
        if ($id <= 0) {
            return 0;
        }

        $row = dbQueryOne(
            'SELECT AVG(progress) AS avg_progress, COUNT(*) AS task_count
             FROM tasks
             WHERE project_id = ?',
            [$id]
        );

        if (!$row || (int)$row['task_count'] === 0) {
            $project = dbQueryOne('SELECT progress FROM projects WHERE project_id = ?', [$id]);
            return (int)($project['progress'] ?? 0);
        }

        $avgProgress = (int)round((float)$row['avg_progress']);
        $this->updateProgress($id, $avgProgress);

        return $avgProgress;
    }

    /**
     * Get aggregate statistics across all projects.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $projectStats = dbQueryOne(
            'SELECT
                COUNT(*) AS total_projects,
                COUNT(CASE WHEN status = "planning" THEN 1 END) AS planning,
                COUNT(CASE WHEN status = "active" THEN 1 END) AS active,
                COUNT(CASE WHEN status = "on_hold" THEN 1 END) AS on_hold,
                COUNT(CASE WHEN status = "completed" THEN 1 END) AS completed,
                COUNT(CASE WHEN status = "cancelled" THEN 1 END) AS cancelled,
                COALESCE(SUM(budget), 0) AS total_budget
             FROM projects'
        );

        $expenseStats = dbQueryOne(
            'SELECT COALESCE(SUM(amount), 0) AS total_expenses FROM expenses'
        );

        return [
            'total_projects' => (int)($projectStats['total_projects'] ?? 0),
            'planning'       => (int)($projectStats['planning'] ?? 0),
            'active'         => (int)($projectStats['active'] ?? 0),
            'on_hold'        => (int)($projectStats['on_hold'] ?? 0),
            'completed'      => (int)($projectStats['completed'] ?? 0),
            'cancelled'      => (int)($projectStats['cancelled'] ?? 0),
            'total_budget'   => (float)($projectStats['total_budget'] ?? 0.0),
            'total_expenses' => (float)($expenseStats['total_expenses'] ?? 0.0),
        ];
    }

    /**
     * Get client ID for a given project ID.
     *
     * @param int $projectId
     * @return int|null
     */
    public function getClientId(int $projectId): ?int
    {
        if ($projectId <= 0) {
            return null;
        }

        $row = dbQueryOne('SELECT client_id FROM projects WHERE project_id = ? LIMIT 1', [$projectId]);
        return $row !== null ? (int)$row['client_id'] : null;
    }
}
