<?php
/**
 * BCMS — Building Construction Management System
 * TaskModel: Manages project tasks, worker assignments to tasks, and progress tracking.
 */

declare(strict_types=1);

class TaskModel
{
    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_ON_HOLD     = 'on_hold';
    public const STATUS_CANCELLED   = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_ON_HOLD,
        self::STATUS_CANCELLED,
    ];

    /**
     * Get all tasks across projects with worker and project details, with optional filters.
     *
     * @param array<string, mixed> $filters Supports 'project_id', 'status', 'worker_id', 'search'
     * @return array<int, array<string, mixed>>
     */
    public function getAll(array $filters = []): array
    {
        $sql = 'SELECT t.*, w.name AS worker_name, w.skill AS worker_skill, p.project_name
                FROM tasks t
                JOIN projects p ON p.project_id = t.project_id
                LEFT JOIN workers w ON w.worker_id = t.worker_id';
        $params = [];
        $whereClauses = [];

        if (!empty($filters['project_id'])) {
            $whereClauses[] = 't.project_id = ?';
            $params[] = (int)$filters['project_id'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $whereClauses[] = 't.status = ?';
            $params[] = (string)$filters['status'];
        }

        if (!empty($filters['worker_id'])) {
            $whereClauses[] = 't.worker_id = ?';
            $params[] = (int)$filters['worker_id'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $whereClauses[] = '(t.task_name LIKE ? OR p.project_name LIKE ? OR w.name LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        $sql .= ' ORDER BY (t.due_date IS NULL), t.due_date ASC, t.task_id DESC';

        return dbQuery($sql, $params);
    }

    /**
     * Get tasks for a specific project with worker details and optional filters.
     *
     * @param int $projectId
     * @param array<string, mixed> $filters Supports 'status', 'worker_id', 'search'
     * @return array<int, array<string, mixed>>
     */
    public function getByProject(int $projectId, array $filters = []): array
    {
        if ($projectId <= 0) {
            return [];
        }

        $sql = 'SELECT t.*, w.name AS worker_name, w.skill AS worker_skill
                FROM tasks t
                LEFT JOIN workers w ON w.worker_id = t.worker_id
                WHERE t.project_id = ?';
        $params = [$projectId];

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= ' AND t.status = ?';
            $params[] = (string)$filters['status'];
        }

        if (!empty($filters['worker_id'])) {
            $sql .= ' AND t.worker_id = ?';
            $params[] = (int)$filters['worker_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND t.task_name LIKE ?';
            $params[] = '%' . trim((string)$filters['search']) . '%';
        }

        $sql .= ' ORDER BY (t.due_date IS NULL), t.due_date ASC, t.task_id DESC';

        return dbQuery($sql, $params);
    }

    /**
     * Get single task by ID with assigned worker and project names.
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
            'SELECT t.*, w.name AS worker_name, w.phone AS worker_phone, p.project_name
             FROM tasks t
             LEFT JOIN workers w ON w.worker_id = t.worker_id
             JOIN projects p ON p.project_id = t.project_id
             WHERE t.task_id = ?
             LIMIT 1',
            [$id]
        );
    }

    /**
     * Get all tasks assigned to a specific worker.
     *
     * @param int $workerId
     * @return array<int, array<string, mixed>>
     */
    public function getByWorker(int $workerId): array
    {
        if ($workerId <= 0) {
            return [];
        }

        return dbQuery(
            'SELECT t.*, p.project_name, p.location
             FROM tasks t
             JOIN projects p ON p.project_id = t.project_id
             WHERE t.worker_id = ?
             ORDER BY (t.due_date IS NULL), t.due_date ASC, t.task_id DESC',
            [$workerId]
        );
    }

    /**
     * Create a new task.
     * Validates project_id, task_name, and status.
     *
     * @param array<string, mixed> $data
     * @return int Inserted task_id
     * @throws InvalidArgumentException
     */
    public function create(array $data): int
    {
        $projectId   = isset($data['project_id']) ? (int)$data['project_id'] : 0;
        $taskName    = trim((string)($data['task_name'] ?? ''));
        $description = isset($data['description']) ? trim((string)$data['description']) : null;
        $workerId    = isset($data['worker_id']) && $data['worker_id'] !== '' ? (int)$data['worker_id'] : null;
        $status      = trim((string)($data['status'] ?? self::STATUS_PENDING));
        $progress    = isset($data['progress']) ? (int)$data['progress'] : 0;
        $dueDate     = isset($data['due_date']) && trim((string)$data['due_date']) !== '' ? trim((string)$data['due_date']) : null;

        if ($projectId <= 0) {
            throw new InvalidArgumentException('Invalid project ID.');
        }

        // Validate project exists
        $project = dbQueryOne('SELECT project_id FROM projects WHERE project_id = ?', [$projectId]);
        if (!$project) {
            throw new InvalidArgumentException("Project #{$projectId} does not exist.");
        }

        if ($taskName === '') {
            throw new InvalidArgumentException('Task name is required.');
        }

        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid task status: ' . $status);
        }

        if ($progress < 0 || $progress > 100) {
            throw new InvalidArgumentException('Task progress must be between 0 and 100.');
        }

        if ($progress === 100) {
            $status = self::STATUS_COMPLETED;
        }

        if ($workerId !== null) {
            $worker = dbQueryOne('SELECT worker_id FROM workers WHERE worker_id = ?', [$workerId]);
            if (!$worker) {
                throw new InvalidArgumentException("Worker #{$workerId} does not exist.");
            }
        }

        if ($dueDate !== null && !strtotime($dueDate)) {
            throw new InvalidArgumentException('Invalid due date provided.');
        }

        return dbInsert(
            'INSERT INTO tasks (project_id, worker_id, task_name, description, status, progress, due_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$projectId, $workerId, $taskName, $description, $status, $progress, $dueDate]
        );
    }

    /**
     * Update an existing task.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     * @throws InvalidArgumentException
     */
    public function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid task ID.');
        }

        $existing = $this->getById($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Task not found.');
        }

        $fields = [];
        $params = [];

        if (array_key_exists('task_name', $data)) {
            $taskName = trim((string)$data['task_name']);
            if ($taskName === '') {
                throw new InvalidArgumentException('Task name cannot be empty.');
            }
            $fields[] = 'task_name = ?';
            $params[] = $taskName;
        }

        if (array_key_exists('description', $data)) {
            $fields[] = 'description = ?';
            $params[] = $data['description'] !== null ? trim((string)$data['description']) : null;
        }

        if (array_key_exists('worker_id', $data)) {
            $workerId = $data['worker_id'] !== null && $data['worker_id'] !== '' ? (int)$data['worker_id'] : null;
            if ($workerId !== null) {
                $worker = dbQueryOne('SELECT worker_id FROM workers WHERE worker_id = ?', [$workerId]);
                if (!$worker) {
                    throw new InvalidArgumentException("Worker #{$workerId} does not exist.");
                }
            }
            $fields[] = 'worker_id = ?';
            $params[] = $workerId;
        }

        $progressVal = array_key_exists('progress', $data) ? (int)$data['progress'] : null;
        $statusVal   = array_key_exists('status', $data) ? trim((string)$data['status']) : null;

        if ($progressVal !== null) {
            if ($progressVal < 0 || $progressVal > 100) {
                throw new InvalidArgumentException('Task progress must be between 0 and 100.');
            }
            $fields[] = 'progress = ?';
            $params[] = $progressVal;

            if ($progressVal === 100 && $statusVal === null) {
                $statusVal = self::STATUS_COMPLETED;
            }
        }

        if ($statusVal !== null) {
            if (!in_array($statusVal, self::STATUSES, true)) {
                throw new InvalidArgumentException('Invalid task status: ' . $statusVal);
            }
            $fields[] = 'status = ?';
            $params[] = $statusVal;

            if ($statusVal === self::STATUS_COMPLETED && $progressVal === null && (int)$existing['progress'] < 100) {
                $fields[] = 'progress = ?';
                $params[] = 100;
            }
        }

        if (array_key_exists('due_date', $data)) {
            $dueDate = $data['due_date'] !== null && trim((string)$data['due_date']) !== '' ? trim((string)$data['due_date']) : null;
            if ($dueDate !== null && !strtotime($dueDate)) {
                throw new InvalidArgumentException('Invalid due date provided.');
            }
            $fields[] = 'due_date = ?';
            $params[] = $dueDate;
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = 'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE task_id = ?';
        dbExecute($sql, $params);

        return true;
    }

    /**
     * Update task status. Auto-completes progress if status is completed.
     *
     * @param int $id
     * @param string $status
     * @return bool
     * @throws InvalidArgumentException
     */
    public function updateStatus(int $id, string $status): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid task ID.');
        }

        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid task status: ' . $status);
        }

        $sql = 'UPDATE tasks SET status = ?';
        $params = [$status];

        if ($status === self::STATUS_COMPLETED) {
            $sql .= ', progress = 100';
        }

        $sql .= ' WHERE task_id = ?';
        $params[] = $id;

        $affected = dbExecute($sql, $params);
        return $affected > 0;
    }

    /**
     * Update task progress (0-100). Auto-sets status to completed if progress reaches 100.
     *
     * @param int $id
     * @param int $progress
     * @return bool
     * @throws InvalidArgumentException
     */
    public function updateProgress(int $id, int $progress): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid task ID.');
        }

        if ($progress < 0 || $progress > 100) {
            throw new InvalidArgumentException('Task progress must be between 0 and 100.');
        }

        $existing = $this->getById($id);
        if (!$existing) {
            throw new InvalidArgumentException('Task not found.');
        }

        $sql = 'UPDATE tasks SET progress = ?';
        $params = [$progress];

        if ($progress === 100) {
            $sql .= ', status = ?';
            $params[] = self::STATUS_COMPLETED;
        } elseif ($existing['status'] === self::STATUS_PENDING && $progress > 0) {
            $sql .= ', status = ?';
            $params[] = self::STATUS_IN_PROGRESS;
        }

        $sql .= ' WHERE task_id = ?';
        $params[] = $id;

        $affected = dbExecute($sql, $params);
        return $affected > 0;
    }

    /**
     * Assign a worker to a task.
     *
     * @param int $taskId
     * @param int $workerId
     * @return bool
     * @throws InvalidArgumentException
     */
    public function assignWorker(int $taskId, int $workerId): bool
    {
        if ($taskId <= 0) {
            throw new InvalidArgumentException('Invalid task ID.');
        }

        if ($workerId <= 0) {
            throw new InvalidArgumentException('Invalid worker ID.');
        }

        $worker = dbQueryOne('SELECT worker_id FROM workers WHERE worker_id = ?', [$workerId]);
        if (!$worker) {
            throw new InvalidArgumentException("Worker #{$workerId} does not exist.");
        }

        $affected = dbExecute('UPDATE tasks SET worker_id = ? WHERE task_id = ?', [$workerId, $taskId]);
        return $affected > 0;
    }

    /**
     * Get task statistics for a project: counts by status and average progress.
     *
     * @param int $projectId
     * @return array<string, mixed>
     */
    public function getStats(int $projectId): array
    {
        if ($projectId <= 0) {
            return [
                'total'        => 0,
                'pending'      => 0,
                'in_progress'  => 0,
                'completed'    => 0,
                'on_hold'      => 0,
                'cancelled'    => 0,
                'avg_progress' => 0,
            ];
        }

        $row = dbQueryOne(
            'SELECT
                COUNT(*) AS total,
                COUNT(CASE WHEN status = "pending" THEN 1 END) AS pending,
                COUNT(CASE WHEN status = "in_progress" THEN 1 END) AS in_progress,
                COUNT(CASE WHEN status = "completed" THEN 1 END) AS completed,
                COUNT(CASE WHEN status = "on_hold" THEN 1 END) AS on_hold,
                COUNT(CASE WHEN status = "cancelled" THEN 1 END) AS cancelled,
                COALESCE(AVG(progress), 0) AS avg_progress
             FROM tasks
             WHERE project_id = ?',
            [$projectId]
        );

        return [
            'total'        => (int)($row['total'] ?? 0),
            'pending'      => (int)($row['pending'] ?? 0),
            'in_progress'  => (int)($row['in_progress'] ?? 0),
            'completed'    => (int)($row['completed'] ?? 0),
            'on_hold'      => (int)($row['on_hold'] ?? 0),
            'cancelled'    => (int)($row['cancelled'] ?? 0),
            'avg_progress' => (int)round((float)($row['avg_progress'] ?? 0.0)),
        ];
    }
}
