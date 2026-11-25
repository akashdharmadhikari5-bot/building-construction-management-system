<?php
/**
 * BCMS — Building Construction Management System
 * WorkerModel: Manages construction laborers, project assignments, attendance wages, and skills.
 */

declare(strict_types=1);

class WorkerModel
{
    /**
     * Get all workers with optional filtering.
     *
     * @param array<string, mixed> $filters Supports 'skill', 'is_active', 'search'
     * @return array<int, array<string, mixed>>
     */
    public function getAll(array $filters = []): array
    {
        $sql = 'SELECT w.*,
                       (SELECT COUNT(*) FROM worker_assignments wa WHERE wa.worker_id = w.worker_id AND wa.is_active = 1) AS active_assignments
                FROM workers w';
        $params = [];
        $whereClauses = [];

        if (isset($filters['skill']) && trim((string)$filters['skill']) !== '') {
            $whereClauses[] = 'w.skill = ?';
            $params[] = trim((string)$filters['skill']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $whereClauses[] = 'w.is_active = ?';
            $params[] = (int)(bool)$filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $whereClauses[] = '(w.name LIKE ? OR w.phone LIKE ? OR w.skill LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        $sql .= ' ORDER BY w.name ASC';

        return dbQuery($sql, $params);
    }

    /**
     * Get a worker by ID.
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
            'SELECT w.*,
                    (SELECT COUNT(*) FROM worker_assignments wa WHERE wa.worker_id = w.worker_id AND wa.is_active = 1) AS active_assignments
             FROM workers w
             WHERE w.worker_id = ?
             LIMIT 1',
            [$id]
        );
    }

    /**
     * Create a new worker record.
     * Validates: name, phone, skill, daily_wage >= 0.
     *
     * @param array<string, mixed> $data
     * @return int Inserted worker_id
     * @throws InvalidArgumentException
     */
    public function create(array $data): int
    {
        $name      = trim((string)($data['name'] ?? ''));
        $phone     = trim((string)($data['phone'] ?? ''));
        $skill     = trim((string)($data['skill'] ?? ''));
        $dailyWage = isset($data['daily_wage']) ? (float)$data['daily_wage'] : -1.0;
        $isActive  = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        if ($name === '') {
            throw new InvalidArgumentException('Worker name is required.');
        }

        if ($phone === '') {
            throw new InvalidArgumentException('Worker phone number is required.');
        }

        if ($skill === '') {
            throw new InvalidArgumentException('Worker skill is required.');
        }

        if ($dailyWage < 0.0) {
            throw new InvalidArgumentException('Daily wage cannot be negative.');
        }

        return dbInsert(
            'INSERT INTO workers (name, phone, skill, daily_wage, is_active)
             VALUES (?, ?, ?, ?, ?)',
            [$name, $phone, $skill, $dailyWage, $isActive]
        );
    }

    /**
     * Update an existing worker's details.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     * @throws InvalidArgumentException
     */
    public function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid worker ID.');
        }

        $existing = $this->getById($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Worker not found.');
        }

        $fields = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $name = trim((string)$data['name']);
            if ($name === '') {
                throw new InvalidArgumentException('Worker name cannot be empty.');
            }
            $fields[] = 'name = ?';
            $params[] = $name;
        }

        if (array_key_exists('phone', $data)) {
            $phone = trim((string)$data['phone']);
            if ($phone === '') {
                throw new InvalidArgumentException('Worker phone cannot be empty.');
            }
            $fields[] = 'phone = ?';
            $params[] = $phone;
        }

        if (array_key_exists('skill', $data)) {
            $skill = trim((string)$data['skill']);
            if ($skill === '') {
                throw new InvalidArgumentException('Worker skill cannot be empty.');
            }
            $fields[] = 'skill = ?';
            $params[] = $skill;
        }

        if (array_key_exists('daily_wage', $data)) {
            $dailyWage = (float)$data['daily_wage'];
            if ($dailyWage < 0.0) {
                throw new InvalidArgumentException('Daily wage cannot be negative.');
            }
            $fields[] = 'daily_wage = ?';
            $params[] = $dailyWage;
        }

        if (array_key_exists('is_active', $data)) {
            $fields[] = 'is_active = ?';
            $params[] = (int)(bool)$data['is_active'];
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = 'UPDATE workers SET ' . implode(', ', $fields) . ' WHERE worker_id = ?';
        dbExecute($sql, $params);

        return true;
    }

    /**
     * Deactivate a worker.
     *
     * @param int $id
     * @return bool
     * @throws InvalidArgumentException
     */
    public function deactivate(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid worker ID.');
        }

        $affected = dbExecute('UPDATE workers SET is_active = 0 WHERE worker_id = ?', [$id]);
        return $affected > 0;
    }

    /**
     * Toggle worker active status.
     *
     * @param int $id
     * @return bool
     * @throws InvalidArgumentException
     */
    public function toggleStatus(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid worker ID.');
        }

        $worker = $this->getById($id);
        if ($worker === null) {
            throw new InvalidArgumentException('Worker not found.');
        }

        $newStatus = (int)!$worker['is_active'];
        $affected = dbExecute('UPDATE workers SET is_active = ? WHERE worker_id = ?', [$newStatus, $id]);
        return $affected > 0;
    }

    /**
     * Get all project assignments for a specific worker.
     *
     * @param int $workerId
     * @return array<int, array<string, mixed>>
     */
    public function getAssignments(int $workerId): array
    {
        if ($workerId <= 0) {
            return [];
        }

        return dbQuery(
            'SELECT wa.*, p.project_name, p.status AS project_status, p.location, p.budget
             FROM worker_assignments wa
             JOIN projects p ON p.project_id = wa.project_id
             WHERE wa.worker_id = ?
             ORDER BY wa.is_active DESC, wa.assigned_date DESC',
            [$workerId]
        );
    }

    /**
     * Calculate wage for a worker on a project within an optional date range.
     * Returns present_days, half_days, working_days, daily_wage, and total_wage.
     *
     * @param int $workerId
     * @param int $projectId
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public function calculateWage(int $workerId, int $projectId, ?string $fromDate = null, ?string $toDate = null): array
    {
        if ($workerId <= 0) {
            throw new InvalidArgumentException('Invalid worker ID.');
        }

        if ($projectId <= 0) {
            throw new InvalidArgumentException('Invalid project ID.');
        }

        $worker = $this->getById($workerId);
        if ($worker === null) {
            throw new InvalidArgumentException("Worker #{$workerId} not found.");
        }

        $dailyWage = (float)$worker['daily_wage'];

        $sql = 'SELECT
                    COUNT(CASE WHEN status = "present" THEN 1 END) AS present_days,
                    COUNT(CASE WHEN status = "half_day" THEN 1 END) AS half_days,
                    COUNT(CASE WHEN status = "absent" THEN 1 END) AS absent_days,
                    COUNT(CASE WHEN status = "holiday" THEN 1 END) AS holiday_days
                FROM attendance
                WHERE worker_id = ? AND project_id = ?';
        $params = [$workerId, $projectId];

        if ($fromDate !== null && trim($fromDate) !== '') {
            $sql .= ' AND attendance_date >= ?';
            $params[] = trim($fromDate);
        }

        if ($toDate !== null && trim($toDate) !== '') {
            $sql .= ' AND attendance_date <= ?';
            $params[] = trim($toDate);
        }

        $row = dbQueryOne($sql, $params);

        $presentDays = (int)($row['present_days'] ?? 0);
        $halfDays    = (int)($row['half_days'] ?? 0);
        $absentDays  = (int)($row['absent_days'] ?? 0);
        $holidayDays = (int)($row['holiday_days'] ?? 0);

        // Present counts as 1.0 day; Half-day counts as 0.5 day
        $workingDays = (float)($presentDays + ($halfDays * 0.5));
        $totalWage   = round($workingDays * $dailyWage, 2);

        return [
            'worker_id'    => $workerId,
            'worker_name'  => $worker['name'],
            'project_id'   => $projectId,
            'daily_wage'   => $dailyWage,
            'present_days' => $presentDays,
            'half_days'    => $halfDays,
            'absent_days'  => $absentDays,
            'holiday_days' => $holidayDays,
            'working_days' => $workingDays,
            'total_wage'   => $totalWage,
            'from_date'    => $fromDate,
            'to_date'      => $toDate,
        ];
    }

    /**
     * Get distinct skills list of all workers.
     *
     * @return array<int, string>
     */
    public function getSkills(): array
    {
        $rows = dbQuery(
            'SELECT DISTINCT skill
             FROM workers
             WHERE skill IS NOT NULL AND TRIM(skill) != ""
             ORDER BY skill ASC'
        );

        return array_column($rows, 'skill');
    }
}
