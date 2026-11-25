<?php
/**
 * BCMS — Building Construction Management System
 * AttendanceModel: Manages daily worker attendance records, calculations, and daily sheets.
 */

declare(strict_types=1);

class AttendanceModel
{
    public const STATUS_PRESENT  = 'present';
    public const STATUS_ABSENT   = 'absent';
    public const STATUS_HALF_DAY = 'half_day';
    public const STATUS_HOLIDAY  = 'holiday';

    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
        self::STATUS_HALF_DAY,
        self::STATUS_HOLIDAY,
    ];

    /**
     * Get attendance records for a project with worker names and optional filters.
     *
     * @param int $projectId
     * @param array<string, mixed> $filters Supports 'from_date', 'to_date', 'date', 'worker_id', 'status'
     * @return array<int, array<string, mixed>>
     */
    public function getByProject(int $projectId, array $filters = []): array
    {
        if ($projectId <= 0) {
            return [];
        }

        $sql = 'SELECT a.*, w.name AS worker_name, w.phone AS worker_phone, w.skill, w.daily_wage
                FROM attendance a
                JOIN workers w ON w.worker_id = a.worker_id
                WHERE a.project_id = ?';
        $params = [$projectId];

        if (!empty($filters['date'])) {
            $sql .= ' AND a.attendance_date = ?';
            $params[] = trim((string)$filters['date']);
        } else {
            if (!empty($filters['from_date'])) {
                $sql .= ' AND a.attendance_date >= ?';
                $params[] = trim((string)$filters['from_date']);
            }
            if (!empty($filters['to_date'])) {
                $sql .= ' AND a.attendance_date <= ?';
                $params[] = trim((string)$filters['to_date']);
            }
        }

        if (!empty($filters['worker_id'])) {
            $sql .= ' AND a.worker_id = ?';
            $params[] = (int)$filters['worker_id'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= ' AND a.status = ?';
            $params[] = (string)$filters['status'];
        }

        $sql .= ' ORDER BY a.attendance_date DESC, w.name ASC';

        return dbQuery($sql, $params);
    }

    /**
     * Get attendance records for a specific worker and project, optionally filtered by month (Y-m).
     *
     * @param int $workerId
     * @param int $projectId
     * @param string|null $month Format 'YYYY-MM'
     * @return array<int, array<string, mixed>>
     */
    public function getByWorker(int $workerId, int $projectId, ?string $month = null): array
    {
        if ($workerId <= 0 || $projectId <= 0) {
            return [];
        }

        $sql = 'SELECT a.*, p.project_name
                FROM attendance a
                JOIN projects p ON p.project_id = a.project_id
                WHERE a.worker_id = ? AND a.project_id = ?';
        $params = [$workerId, $projectId];

        if ($month !== null && preg_match('/^\d{4}-\d{2}$/', trim($month))) {
            $sql .= ' AND a.attendance_date LIKE ?';
            $params[] = trim($month) . '%';
        }

        $sql .= ' ORDER BY a.attendance_date DESC';

        return dbQuery($sql, $params);
    }

    /**
     * Mark attendance for a worker on a project.
     * Prevents duplicate entry for (worker_id, project_id, attendance_date).
     *
     * @param int $workerId
     * @param int $projectId
     * @param string $date (Y-m-d)
     * @param string $status ('present', 'absent', 'half_day', 'holiday')
     * @param string|null $remarks
     * @return int Inserted attendance_id
     * @throws InvalidArgumentException
     */
    public function markAttendance(int $workerId, int $projectId, string $date, string $status, ?string $remarks = null): int
    {
        if ($workerId <= 0) {
            throw new InvalidArgumentException('Invalid worker ID.');
        }

        if ($projectId <= 0) {
            throw new InvalidArgumentException('Invalid project ID.');
        }

        $date = trim($date);
        if ($date === '' || !strtotime($date)) {
            throw new InvalidArgumentException('A valid attendance date is required.');
        }

        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid attendance status: ' . $status);
        }

        // Verify duplicate check against unique constraint uq_attendance
        $duplicate = dbQueryOne(
            'SELECT attendance_id
             FROM attendance
             WHERE worker_id = ? AND project_id = ? AND attendance_date = ?
             LIMIT 1',
            [$workerId, $projectId, $date]
        );

        if ($duplicate !== null) {
            throw new InvalidArgumentException("Attendance already recorded for worker #{$workerId} on {$date}.");
        }

        $recordedBy = isset($_SESSION['user']['user_id']) ? (int)$_SESSION['user']['user_id'] : null;

        return dbInsert(
            'INSERT INTO attendance (worker_id, project_id, attendance_date, status, remarks, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$workerId, $projectId, $date, $status, $remarks, $recordedBy]
        );
    }

    /**
     * Update an existing attendance record.
     *
     * @param int $attendanceId
     * @param string $status
     * @param string|null $remarks
     * @return bool
     * @throws InvalidArgumentException
     */
    public function updateAttendance(int $attendanceId, string $status, ?string $remarks = null): bool
    {
        if ($attendanceId <= 0) {
            throw new InvalidArgumentException('Invalid attendance ID.');
        }

        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid attendance status: ' . $status);
        }

        $affected = dbExecute(
            'UPDATE attendance
             SET status = ?, remarks = ?
             WHERE attendance_id = ?',
            [$status, $remarks, $attendanceId]
        );

        return $affected > 0;
    }

    /**
     * Get a specific attendance record by worker, project, and date.
     *
     * @param int $workerId
     * @param int $projectId
     * @param string $date (Y-m-d)
     * @return array<string, mixed>|null
     */
    public function getRecord(int $workerId, int $projectId, string $date): ?array
    {
        if ($workerId <= 0 || $projectId <= 0 || empty($date)) {
            return null;
        }

        return dbQueryOne(
            'SELECT * FROM attendance WHERE worker_id = ? AND project_id = ? AND attendance_date = ? LIMIT 1',
            [$workerId, $projectId, $date]
        );
    }

    /**
     * Record or update attendance (upsert).
     * If attendance already exists for worker on project and date, updates it. Otherwise inserts new record.
     *
     * @param int $workerId
     * @param int $projectId
     * @param string $date (Y-m-d)
     * @param string $status
     * @param string|null $remarks
     * @return int attendance_id
     */
    public function recordOrUpdate(int $workerId, int $projectId, string $date, string $status, ?string $remarks = null): int
    {
        $existing = $this->getRecord($workerId, $projectId, $date);
        if ($existing !== null) {
            $this->updateAttendance((int)$existing['attendance_id'], $status, $remarks);
            return (int)$existing['attendance_id'];
        }

        return $this->markAttendance($workerId, $projectId, $date, $status, $remarks);
    }

    /**
     * Calculate working days breakdown for a worker on a project within an optional date range.
     * Total working_days = present + (half_day * 0.5).
     *
     * @param int $workerId
     * @param int $projectId
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return array<string, mixed>
     */
    public function calculateWorkingDays(int $workerId, int $projectId, ?string $fromDate = null, ?string $toDate = null): array
    {
        if ($workerId <= 0 || $projectId <= 0) {
            return [
                'present'      => 0,
                'half_day'     => 0,
                'absent'       => 0,
                'holiday'      => 0,
                'working_days' => 0.0,
            ];
        }

        $sql = 'SELECT
                    COUNT(CASE WHEN status = "present" THEN 1 END) AS present_count,
                    COUNT(CASE WHEN status = "half_day" THEN 1 END) AS half_day_count,
                    COUNT(CASE WHEN status = "absent" THEN 1 END) AS absent_count,
                    COUNT(CASE WHEN status = "holiday" THEN 1 END) AS holiday_count
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

        $present = (int)($row['present_count'] ?? 0);
        $halfDay = (int)($row['half_day_count'] ?? 0);
        $absent  = (int)($row['absent_count'] ?? 0);
        $holiday = (int)($row['holiday_count'] ?? 0);

        return [
            'present'      => $present,
            'half_day'     => $halfDay,
            'absent'       => $absent,
            'holiday'      => $holiday,
            'working_days' => (float)($present + ($halfDay * 0.5)),
        ];
    }

    /**
     * Get today's attendance for a project.
     *
     * @param int $projectId
     * @return array<int, array<string, mixed>>
     */
    public function getTodayAttendance(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        return dbQuery(
            'SELECT a.*, w.name AS worker_name, w.skill, w.daily_wage
             FROM attendance a
             JOIN workers w ON w.worker_id = a.worker_id
             WHERE a.project_id = ? AND a.attendance_date = CURRENT_DATE()
             ORDER BY w.name ASC',
            [$projectId]
        );
    }

    /**
     * Get a daily attendance sheet for a project on a given date.
     * Returns all assigned workers with their attendance status for that date (or null if not yet marked).
     *
     * @param int $projectId
     * @param string $date (Y-m-d)
     * @return array<int, array<string, mixed>>
     * @throws InvalidArgumentException
     */
    public function getDailySheet(int $projectId, string $date): array
    {
        if ($projectId <= 0) {
            throw new InvalidArgumentException('Invalid project ID.');
        }

        $date = trim($date);
        if ($date === '' || !strtotime($date)) {
            throw new InvalidArgumentException('A valid date is required.');
        }

        return dbQuery(
            'SELECT
                w.worker_id,
                w.name AS worker_name,
                w.phone AS worker_phone,
                w.skill,
                w.daily_wage,
                wa.assignment_id,
                a.attendance_id,
                a.status AS attendance_status,
                a.remarks,
                a.recorded_by
             FROM worker_assignments wa
             JOIN workers w ON w.worker_id = wa.worker_id
             LEFT JOIN attendance a ON a.worker_id = wa.worker_id
                                   AND a.project_id = wa.project_id
                                   AND a.attendance_date = ?
             WHERE wa.project_id = ?
               AND wa.assigned_date <= ?
               AND (wa.is_active = 1 OR wa.end_date IS NULL OR wa.end_date >= ?)
             ORDER BY w.name ASC',
            [$date, $projectId, $date, $date]
        );
    }
}
