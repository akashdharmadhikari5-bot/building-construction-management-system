<?php
/**
 * BCMS — Building Construction Management System
 * PurchaseModel: Manages material purchases, stock additions, and project purchase totals.
 */

declare(strict_types=1);

class PurchaseModel
{
    /**
     * Get all material purchases with material and project details.
     *
     * @param array<string, mixed> $filters Supports 'project_id', 'material_id', 'from_date', 'to_date', 'supplier'
     * @return array<int, array<string, mixed>>
     */
    public function getAll(array $filters = []): array
    {
        $sql = 'SELECT mp.*, m.material_name, m.unit, p.project_name
                FROM material_purchases mp
                JOIN materials m ON m.material_id = mp.material_id
                JOIN projects p ON p.project_id = mp.project_id';
        $params = [];
        $whereClauses = [];

        if (!empty($filters['project_id'])) {
            $whereClauses[] = 'mp.project_id = ?';
            $params[] = (int)$filters['project_id'];
        }

        if (!empty($filters['material_id'])) {
            $whereClauses[] = 'mp.material_id = ?';
            $params[] = (int)$filters['material_id'];
        }

        if (!empty($filters['from_date'])) {
            $whereClauses[] = 'mp.purchase_date >= ?';
            $params[] = trim((string)$filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $whereClauses[] = 'mp.purchase_date <= ?';
            $params[] = trim((string)$filters['to_date']);
        }

        if (!empty($filters['supplier'])) {
            $whereClauses[] = 'mp.supplier LIKE ?';
            $params[] = '%' . trim((string)$filters['supplier']) . '%';
        }

        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        $sql .= ' ORDER BY mp.purchase_date DESC, mp.purchase_id DESC';

        return dbQuery($sql, $params);
    }

    /**
     * Get all purchases for a specific project.
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
            'SELECT mp.*, m.material_name, m.unit, p.project_name
             FROM material_purchases mp
             JOIN materials m ON m.material_id = mp.material_id
             JOIN projects p ON p.project_id = mp.project_id
             WHERE mp.project_id = ?
             ORDER BY mp.purchase_date DESC, mp.purchase_id DESC',
            [$projectId]
        );
    }

    /**
     * Get single purchase by ID with material and project information.
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
            'SELECT mp.*, m.material_name, m.unit, p.project_name
             FROM material_purchases mp
             JOIN materials m ON m.material_id = mp.material_id
             JOIN projects p ON p.project_id = mp.project_id
             WHERE mp.purchase_id = ?
             LIMIT 1',
            [$id]
        );
    }

    /**
     * Record a new material purchase and synchronize material stock.
     * Validates project_id, material_id, quantity > 0, price >= 0, purchase_date.
     *
     * @param array<string, mixed> $data
     * @return int Inserted purchase_id
     * @throws InvalidArgumentException
     */
    public function create(array $data): int
    {
        $projectId    = isset($data['project_id']) ? (int)$data['project_id'] : 0;
        $materialId   = isset($data['material_id']) ? (int)$data['material_id'] : 0;
        $quantity     = isset($data['quantity']) ? (float)$data['quantity'] : 0.0;
        $price        = isset($data['price']) ? (float)$data['price'] : -1.0;
        $purchaseDate = trim((string)($data['purchase_date'] ?? ''));
        $supplier     = isset($data['supplier']) ? trim((string)$data['supplier']) : null;
        $notes        = isset($data['notes']) ? trim((string)$data['notes']) : null;

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

        $material = dbQueryOne('SELECT material_id FROM materials WHERE material_id = ?', [$materialId]);
        if (!$material) {
            throw new InvalidArgumentException("Material #{$materialId} does not exist.");
        }

        if ($quantity <= 0.0) {
            throw new InvalidArgumentException('Purchase quantity must be greater than zero.');
        }

        if ($price < 0.0) {
            throw new InvalidArgumentException('Purchase price cannot be negative.');
        }

        if ($purchaseDate === '' || !strtotime($purchaseDate)) {
            throw new InvalidArgumentException('A valid purchase date is required.');
        }

        $createdBy = isset($_SESSION['user']['user_id']) ? (int)$_SESSION['user']['user_id'] : null;

        // Insert into material_purchases
        $purchaseId = dbInsert(
            'INSERT INTO material_purchases (project_id, material_id, quantity, price, purchase_date, supplier, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$projectId, $materialId, $quantity, $price, $purchaseDate, $supplier, $notes, $createdBy]
        );

        // Synchronize stock in materials table
        $materialModel = new MaterialModel();
        $materialModel->syncStock($materialId);

        return $purchaseId;
    }

    /**
     * Get total purchase spend for a project.
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
            'SELECT COALESCE(SUM(price), 0) AS total
             FROM material_purchases
             WHERE project_id = ?',
            [$projectId]
        );

        return (float)($row['total'] ?? 0.0);
    }
}
