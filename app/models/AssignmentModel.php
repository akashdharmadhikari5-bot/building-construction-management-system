<?php
/**
 * BCMS — Building Construction Management System
 * AssignmentModel: Manages worker-project assignments and tracks active assignments.
 */

declare(strict_types=1);

class AssignmentModel
{
    /**
     * Get all worker assignments for a specific project with worker details.
     *
     * @param int $projectId
     * @return array<int, array<string, mixed>>
     */
    public function getByProject(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        return dbQuery(
            'SELECT wa.*, w.name AS worker_name, w.phone AS worker_phone, w.skill, w.daily_wage, w.is_active AS worker_active
             FROM worker_assignments wa
             JOIN workers w ON w.worker_id = wa.worker_id
             WHERE wa.project_id = ?
             ORDER BY wa.is_active DESC, wa.assigned_date DESC',
            [$projectId]
        );
    }

    /**
     * Get all project assignments for a specific worker with project details.
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
            'SELECT wa.*, p.project_name, p.status AS project_status, p.location, p.start_date, p.end_date AS project_end_date
             FROM worker_assignments wa
             JOIN projects p ON p.project_id = wa.project_id
             WHERE wa.worker_id = ?
             ORDER BY wa.is_active DESC, wa.assigned_date DESC',
            [$workerId]
        );
    }

    /**
     * Check if a worker currently has an active assignment on a project.
     *
     * @param int $projectId
     * @param int $workerId
     * @return bool
     */
    public function isAssigned(int $projectId, int $workerId): bool
    {
        if ($projectId <= 0 || $workerId <= 0) {
            return false;
        }

        $row = dbQueryOne(
            'SELECT assignment_id
             FROM worker_assignments
             WHERE project_id = ? AND worker_id = ? AND is_active = 1
             LIMIT 1',
            [$projectId, $workerId]
        );

        return $row !== null;
    }

    /**
     * Assign a worker to a project.
     * Checks if an active assignment already exists to prevent duplicate assignments.
     *
     * @param int $projectId
     * @param int $workerId
     * @param string $date (Y-m-d)
     * @return int Inserted assignment_id
     * @throws InvalidArgumentException
     */
    public function assign(int $projectId, int $workerId, string $date): int
    {
        if ($projectId <= 0) {
            throw new InvalidArgumentException('Invalid project ID.');
        }

        if ($workerId <= 0) {
            throw new InvalidArgumentException('Invalid worker ID.');
        }

        $date = trim($date);
        if ($date === '' || !strtotime($date)) {
            throw new InvalidArgumentException('A valid assigned date is required.');
        }

        // Verify project exists
        $project = dbQueryOne('SELECT project_id FROM projects WHERE project_id = ?', [$projectId]);
        if (!$project) {
            throw new InvalidArgumentException("Project #{$projectId} does not exist.");
        }

        // Verify worker exists
        $worker = dbQueryOne('SELECT worker_id, is_active FROM workers WHERE worker_id = ?', [$workerId]);
        if (!$worker) {
            throw new InvalidArgumentException("Worker #{$workerId} does not exist.");
        }

        if (!(bool)$worker['is_active']) {
            throw new InvalidArgumentException("Worker #{$workerId} is currently deactivated.");
        }

        // Check if already actively assigned
        if ($this->isAssigned($projectId, $workerId)) {
            throw new InvalidArgumentException("Worker #{$workerId} is already actively assigned to project #{$projectId}.");
        }

        return dbInsert(
            'INSERT INTO worker_assignments (project_id, worker_id, assigned_date, is_active)
             VALUES (?, ?, ?, 1)',
            [$projectId, $workerId, $date]
        );
    }

    /**
     * End an active worker assignment.
     * Sets is_active = 0 and records the end_date.
     *
     * @param int $assignmentId
     * @param string $endDate (Y-m-d)
     * @return bool
     * @throws InvalidArgumentException
     */
    public function unassign(int $assignmentId, string $endDate): bool
    {
        if ($assignmentId <= 0) {
            throw new InvalidArgumentException('Invalid assignment ID.');
        }

        $endDate = trim($endDate);
        if ($endDate === '' || !strtotime($endDate)) {
            throw new InvalidArgumentException('A valid end date is required.');
        }

        $assignment = dbQueryOne(
            'SELECT assignment_id, assigned_date, is_active FROM worker_assignments WHERE assignment_id = ?',
            [$assignmentId]
        );

        if (!$assignment) {
            throw new InvalidArgumentException("Assignment #{$assignmentId} not found.");
        }

        if (strtotime($endDate) < strtotime($assignment['assigned_date'])) {
            throw new InvalidArgumentException('End date cannot be earlier than assigned date (' . $assignment['assigned_date'] . ').');
        }

        $affected = dbExecute(
            'UPDATE worker_assignments
             SET is_active = 0, end_date = ?
             WHERE assignment_id = ?',
            [$endDate, $assignmentId]
        );

        return $affected > 0;
    }

    /**
     * Get active workers who are not currently actively assigned to this project.
     *
     * @param int $projectId
     * @return array<int, array<string, mixed>>
     */
    public function getUnassignedWorkers(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        return dbQuery(
            'SELECT w.*
             FROM workers w
             WHERE w.is_active = 1
               AND w.worker_id NOT IN (
                   SELECT wa.worker_id
                   FROM worker_assignments wa
                   WHERE wa.project_id = ? AND wa.is_active = 1
               )
             ORDER BY w.name ASC',
            [$projectId]
        );
    }
}
