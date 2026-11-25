<?php
/**
 * BCMS — Building Construction Management System
 * UsageModel: Manages material consumption per project, verifies stock availability, and syncs inventory.
 */

declare(strict_types=1);

class UsageModel
{
    /**
     * Get all material usage records with material and project names.
     *
     * @param array<string, mixed> $filters Supports 'project_id', 'material_id', 'from_date', 'to_date', 'search'
     * @return array<int, array<string, mixed>>
     */
    public function getAll(array $filters = []): array
    {
        $sql = 'SELECT mu.*, m.material_name, m.unit, p.project_name
                FROM material_usage mu
                JOIN materials m ON m.material_id = mu.material_id
                JOIN projects p ON p.project_id = mu.project_id';
        $params = [];
        $whereClauses = [];

        if (!empty($filters['project_id'])) {
            $whereClauses[] = 'mu.project_id = ?';
            $params[] = (int)$filters['project_id'];
        }

        if (!empty($filters['material_id'])) {
            $whereClauses[] = 'mu.material_id = ?';
            $params[] = (int)$filters['material_id'];
        }

        if (!empty($filters['from_date'])) {
            $whereClauses[] = 'mu.usage_date >= ?';
            $params[] = trim((string)$filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $whereClauses[] = 'mu.usage_date <= ?';
            $params[] = trim((string)$filters['to_date']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $whereClauses[] = '(m.material_name LIKE ? OR mu.description LIKE ?)';
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        $sql .= ' ORDER BY mu.usage_date DESC, mu.usage_id DESC';

        return dbQuery($sql, $params);
    }

    /**
     * Get all material usage records for a specific project.
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
            'SELECT mu.*, m.material_name, m.unit, p.project_name
             FROM material_usage mu
             JOIN materials m ON m.material_id = mu.material_id
             JOIN projects p ON p.project_id = mu.project_id
             WHERE mu.project_id = ?
             ORDER BY mu.usage_date DESC, mu.usage_id DESC',
            [$projectId]
        );
    }

    /**
     * Get a single usage record by ID with material and project details.
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
            'SELECT mu.*, m.material_name, m.unit, p.project_name
             FROM material_usage mu
             JOIN materials m ON m.material_id = mu.material_id
             JOIN projects p ON p.project_id = mu.project_id
             WHERE mu.usage_id = ?
             LIMIT 1',
            [$id]
        );
    }

    /**
     * Record material usage.
     * Validates project_id, material_id, quantity > 0, usage_date.
     * Checks for sufficient stock using MaterialModel::checkSufficientStock().
     * Throws InvalidArgumentException('Insufficient stock') if requested quantity exceeds stock.
     * Synchronizes material stock after insertion.
     *
     * @param array<string, mixed> $data
     * @return int Inserted usage_id
     * @throws InvalidArgumentException
     */
    public function create(array $data): int
    {
        $projectId   = isset($data['project_id']) ? (int)$data['project_id'] : 0;
        $materialId  = isset($data['material_id']) ? (int)$data['material_id'] : 0;
        $quantity    = isset($data['quantity']) ? (float)$data['quantity'] : 0.0;
        $usageDate   = trim((string)($data['usage_date'] ?? ''));
        $description = isset($data['description']) ? trim((string)$data['description']) : null;

        if ($projectId <= 0) {
            throw new InvalidArgumentException('A valid project ID is required.');
        }

        $project = dbQueryOne('SELECT project_id FROM projects WHERE project_id = ?', [$projectId]);
        if (!$project) {
            throw new InvalidArgumentException("Project #{$projectId} does not exist.");
        }

        if ($materialId <= 0) {
            throw new InvalidArgumentException('A valid material ID is required.');
        }

        $material = dbQueryOne('SELECT material_id, material_name FROM materials WHERE material_id = ?', [$materialId]);
        if (!$material) {
            throw new InvalidArgumentException("Material #{$materialId} does not exist.");
        }

        if ($quantity <= 0.0) {
            throw new InvalidArgumentException('Usage quantity must be greater than zero.');
        }

        if ($usageDate === '' || !strtotime($usageDate)) {
            throw new InvalidArgumentException('A valid usage date is required.');
        }

        $materialModel = new MaterialModel();

        // Check sufficient stock
        if (!$materialModel->checkSufficientStock($materialId, $quantity)) {
            $available = $materialModel->getCurrentStock($materialId);
            throw new InvalidArgumentException("Insufficient stock for '{$material['material_name']}'. Available: {$available}, Requested: {$quantity}.");
        }

        $createdBy = isset($_SESSION['user']['user_id']) ? (int)$_SESSION['user']['user_id'] : null;

        $usageId = dbInsert(
            'INSERT INTO material_usage (project_id, material_id, quantity, usage_date, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$projectId, $materialId, $quantity, $usageDate, $description, $createdBy]
        );

        // Recalculate material stock
        $materialModel->syncStock($materialId);

        return $usageId;
    }

    /**
     * Get total quantity of a material used by a specific project.
     *
     * @param int $projectId
     * @param int $materialId
     * @return float
     */
    public function getTotalUsedByProject(int $projectId, int $materialId): float
    {
        if ($projectId <= 0 || $materialId <= 0) {
            return 0.0;
        }

        $row = dbQueryOne(
            'SELECT COALESCE(SUM(quantity), 0) AS total
             FROM material_usage
             WHERE project_id = ? AND material_id = ?',
            [$projectId, $materialId]
        );

        return (float)($row['total'] ?? 0.0);
    }
}
