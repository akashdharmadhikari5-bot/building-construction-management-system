<?php
/**
 * BCMS — Building Construction Management System
 * MaterialModel: Manages inventory materials, low-stock alerts, and stock synchronization.
 */

declare(strict_types=1);

class MaterialModel
{
    /**
     * Get all materials with computed stock from view v_material_stock.
     * Supports optional low_stock filter and search.
     *
     * @param array<string, mixed> $filters Supports 'low_stock' (bool), 'search'
     * @return array<int, array<string, mixed>>
     */
    public function getAll(array $filters = []): array
    {
        $sql = 'SELECT m.*,
                       COALESCE(v.computed_stock, m.stock_quantity) AS computed_stock,
                       COALESCE(v.total_purchased, 0) AS total_purchased,
                       COALESCE(v.total_used, 0) AS total_used,
                       COALESCE(v.total_adj_in, 0) AS total_adj_in,
                       COALESCE(v.total_adj_out, 0) AS total_adj_out
                FROM materials m
                LEFT JOIN v_material_stock v ON v.material_id = m.material_id';
        $params = [];
        $whereClauses = [];

        if (!empty($filters['low_stock'])) {
            $whereClauses[] = 'COALESCE(v.computed_stock, m.stock_quantity) <= m.low_stock_threshold';
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $whereClauses[] = '(m.material_name LIKE ? OR m.unit LIKE ?)';
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        $sql .= ' ORDER BY m.material_name ASC';

        return dbQuery($sql, $params);
    }

    /**
     * Get single material by ID with computed stock and transaction totals.
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
            'SELECT m.*,
                    COALESCE(v.computed_stock, m.stock_quantity) AS computed_stock,
                    COALESCE(v.total_purchased, 0) AS total_purchased,
                    COALESCE(v.total_used, 0) AS total_used,
                    COALESCE(v.total_adj_in, 0) AS total_adj_in,
                    COALESCE(v.total_adj_out, 0) AS total_adj_out
             FROM materials m
             LEFT JOIN v_material_stock v ON v.material_id = m.material_id
             WHERE m.material_id = ?
             LIMIT 1',
            [$id]
        );
    }

    /**
     * Create a new material catalog item.
     * Validates material_name and unit.
     *
     * @param array<string, mixed> $data
     * @return int Inserted material_id
     * @throws InvalidArgumentException
     */
    public function create(array $data): int
    {
        $materialName = trim((string)($data['material_name'] ?? ''));
        $unit         = trim((string)($data['unit'] ?? ''));
        $threshold    = isset($data['low_stock_threshold']) ? (float)$data['low_stock_threshold'] : 10.0;
        $stockQty     = isset($data['stock_quantity']) ? (float)$data['stock_quantity'] : 0.0;

        if ($materialName === '') {
            throw new InvalidArgumentException('Material name is required.');
        }

        if ($unit === '') {
            throw new InvalidArgumentException('Unit of measurement is required (e.g. bags, kg, pieces).');
        }

        if ($threshold < 0.0) {
            throw new InvalidArgumentException('Low stock threshold cannot be negative.');
        }

        if ($stockQty < 0.0) {
            throw new InvalidArgumentException('Stock quantity cannot be negative.');
        }

        return dbInsert(
            'INSERT INTO materials (material_name, unit, stock_quantity, low_stock_threshold)
             VALUES (?, ?, ?, ?)',
            [$materialName, $unit, $stockQty, $threshold]
        );
    }

    /**
     * Update an existing material.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     * @throws InvalidArgumentException
     */
    public function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid material ID.');
        }

        $existing = $this->getById($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Material not found.');
        }

        $fields = [];
        $params = [];

        if (array_key_exists('material_name', $data)) {
            $name = trim((string)$data['material_name']);
            if ($name === '') {
                throw new InvalidArgumentException('Material name cannot be empty.');
            }
            $fields[] = 'material_name = ?';
            $params[] = $name;
        }

        if (array_key_exists('unit', $data)) {
            $unit = trim((string)$data['unit']);
            if ($unit === '') {
                throw new InvalidArgumentException('Unit cannot be empty.');
            }
            $fields[] = 'unit = ?';
            $params[] = $unit;
        }

        if (array_key_exists('low_stock_threshold', $data)) {
            $threshold = (float)$data['low_stock_threshold'];
            if ($threshold < 0.0) {
                throw new InvalidArgumentException('Low stock threshold cannot be negative.');
            }
            $fields[] = 'low_stock_threshold = ?';
            $params[] = $threshold;
        }

        if (array_key_exists('stock_quantity', $data)) {
            $stockQty = (float)$data['stock_quantity'];
            if ($stockQty < 0.0) {
                throw new InvalidArgumentException('Stock quantity cannot be negative.');
            }
            $fields[] = 'stock_quantity = ?';
            $params[] = $stockQty;
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = 'UPDATE materials SET ' . implode(', ', $fields) . ' WHERE material_id = ?';
        dbExecute($sql, $params);

        return true;
    }

    /**
     * Get current computed stock for a material from view v_material_stock.
     *
     * @param int $materialId
     * @return float
     */
    public function getCurrentStock(int $materialId): float
    {
        if ($materialId <= 0) {
            return 0.0;
        }

        $row = dbQueryOne(
            'SELECT computed_stock FROM v_material_stock WHERE material_id = ? LIMIT 1',
            [$materialId]
        );

        if ($row !== null && $row['computed_stock'] !== null) {
            return (float)$row['computed_stock'];
        }

        $mat = dbQueryOne('SELECT stock_quantity FROM materials WHERE material_id = ? LIMIT 1', [$materialId]);
        return $mat !== null ? (float)$mat['stock_quantity'] : 0.0;
    }

    /**
     * Get all materials where computed stock is less than or equal to low_stock_threshold.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLowStockItems(): array
    {
        return dbQuery(
            'SELECT m.*,
                    COALESCE(v.computed_stock, m.stock_quantity) AS computed_stock
             FROM materials m
             LEFT JOIN v_material_stock v ON v.material_id = m.material_id
             WHERE COALESCE(v.computed_stock, m.stock_quantity) <= m.low_stock_threshold
             ORDER BY m.material_name ASC'
        );
    }

    /**
     * Get total count of low stock items.
     *
     * @return int
     */
    public function getLowStockCount(): int
    {
        $row = dbQueryOne(
            'SELECT COUNT(*) AS low_count
             FROM materials m
             LEFT JOIN v_material_stock v ON v.material_id = m.material_id
             WHERE COALESCE(v.computed_stock, m.stock_quantity) <= m.low_stock_threshold'
        );

        return (int)($row['low_count'] ?? 0);
    }

    /**
     * Recalculate and update stock_quantity in materials table
     * using purchases, usage, and adjustments.
     *
     * @param int $materialId
     * @return void
     */
    public function syncStock(int $materialId): void
    {
        if ($materialId <= 0) {
            return;
        }

        // Query view or calculate directly
        $row = dbQueryOne(
            'SELECT
                (
                    COALESCE((SELECT SUM(quantity) FROM material_purchases WHERE material_id = ?), 0)
                    - COALESCE((SELECT SUM(quantity) FROM material_usage WHERE material_id = ?), 0)
                    + COALESCE((SELECT SUM(quantity) FROM stock_adjustments WHERE material_id = ? AND adjustment_type = "increase"), 0)
                    - COALESCE((SELECT SUM(quantity) FROM stock_adjustments WHERE material_id = ? AND adjustment_type = "decrease"), 0)
                ) AS calculated_stock',
            [$materialId, $materialId, $materialId, $materialId]
        );

        $calculated = (float)($row['calculated_stock'] ?? 0.0);
        // materials.stock_quantity CHECK constraint enforces >= 0
        $newStock = max(0.0, $calculated);

        dbExecute(
            'UPDATE materials SET stock_quantity = ? WHERE material_id = ?',
            [$newStock, $materialId]
        );
    }

    /**
     * Check if sufficient stock is available for requested quantity.
     *
     * @param int $materialId
     * @param float $quantity
     * @return bool
     */
    public function checkSufficientStock(int $materialId, float $quantity): bool
    {
        if ($quantity <= 0.0) {
            return true;
        }

        $currentStock = $this->getCurrentStock($materialId);
        return $currentStock >= $quantity;
    }
}
