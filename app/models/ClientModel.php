<?php
/**
 * BCMS — Building Construction Management System
 * ClientModel: Manages client records, linked projects, and client financial statistics.
 */

declare(strict_types=1);

class ClientModel
{
    /**
     * Get all clients with optional search filtering.
     *
     * @param array<string, mixed> $filters Supports 'search' (matches name, phone, email)
     * @return array<int, array<string, mixed>>
     */
    public function getAll(array $filters = []): array
    {
        $sql = 'SELECT c.*, COUNT(p.project_id) AS total_projects
                FROM clients c
                LEFT JOIN projects p ON p.client_id = c.client_id';
        $params = [];
        $whereClauses = [];

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $whereClauses[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['email'])) {
            $whereClauses[] = 'c.email = ?';
            $params[] = trim((string)$filters['email']);
        }

        if (!empty($filters['phone'])) {
            $whereClauses[] = 'c.phone LIKE ?';
            $params[] = '%' . trim((string)$filters['phone']) . '%';
        }

        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        $sql .= ' GROUP BY c.client_id ORDER BY c.name ASC';

        return dbQuery($sql, $params);
    }

    /**
     * Get a single client by client ID.
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
            'SELECT c.*, COUNT(p.project_id) AS total_projects
             FROM clients c
             LEFT JOIN projects p ON p.client_id = c.client_id
             WHERE c.client_id = ?
             GROUP BY c.client_id
             LIMIT 1',
            [$id]
        );
    }

    /**
     * Create a new client record.
     * Validates name, phone, email, and address — all required.
     *
     * @param array<string, mixed> $data
     * @return int Inserted client_id
     * @throws InvalidArgumentException
     */
    public function create(array $data): int
    {
        $name    = trim((string)($data['name'] ?? ''));
        $phone   = trim((string)($data['phone'] ?? ''));
        $email   = trim((string)($data['email'] ?? ''));
        $address = trim((string)($data['address'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Client name is required.');
        }

        if ($phone === '') {
            throw new InvalidArgumentException('Client phone number is required.');
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid client email address is required.');
        }

        if ($address === '') {
            throw new InvalidArgumentException('Client address is required.');
        }

        return dbInsert(
            'INSERT INTO clients (name, phone, email, address)
             VALUES (?, ?, ?, ?)',
            [$name, $phone, $email, $address]
        );
    }

    /**
     * Update an existing client's details.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     * @throws InvalidArgumentException
     */
    public function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid client ID.');
        }

        $client = $this->getById($id);
        if ($client === null) {
            throw new InvalidArgumentException('Client not found.');
        }

        $fields = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $name = trim((string)$data['name']);
            if ($name === '') {
                throw new InvalidArgumentException('Client name cannot be empty.');
            }
            $fields[] = 'name = ?';
            $params[] = $name;
        }

        if (array_key_exists('phone', $data)) {
            $phone = trim((string)$data['phone']);
            if ($phone === '') {
                throw new InvalidArgumentException('Client phone cannot be empty.');
            }
            $fields[] = 'phone = ?';
            $params[] = $phone;
        }

        if (array_key_exists('email', $data)) {
            $email = trim((string)$data['email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('A valid client email is required.');
            }
            $fields[] = 'email = ?';
            $params[] = $email;
        }

        if (array_key_exists('address', $data)) {
            $address = trim((string)$data['address']);
            if ($address === '') {
                throw new InvalidArgumentException('Client address cannot be empty.');
            }
            $fields[] = 'address = ?';
            $params[] = $address;
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = 'UPDATE clients SET ' . implode(', ', $fields) . ' WHERE client_id = ?';
        dbExecute($sql, $params);

        return true;
    }

    /**
     * Delete a client only if no projects are linked.
     *
     * @param int $id
     * @return bool
     * @throws InvalidArgumentException|RuntimeException
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid client ID.');
        }

        $client = $this->getById($id);
        if ($client === null) {
            throw new InvalidArgumentException('Client not found.');
        }

        // Check if any projects exist for this client
        $linked = dbQueryOne(
            'SELECT COUNT(*) AS project_count FROM projects WHERE client_id = ?',
            [$id]
        );

        $projectCount = (int)($linked['project_count'] ?? 0);
        if ($projectCount > 0) {
            throw new RuntimeException("Cannot delete client #{$id}: {$projectCount} project(s) are linked to this client.");
        }

        $affected = dbExecute('DELETE FROM clients WHERE client_id = ?', [$id]);
        return $affected > 0;
    }

    /**
     * Get all projects associated with a client.
     *
     * @param int $clientId
     * @return array<int, array<string, mixed>>
     */
    public function getProjects(int $clientId): array
    {
        if ($clientId <= 0) {
            return [];
        }

        return dbQuery(
            'SELECT project_id, client_id, project_name, location, budget, start_date, end_date,
                    status, progress, description, created_at, updated_at
             FROM projects
             WHERE client_id = ?
             ORDER BY start_date DESC, project_id DESC',
            [$clientId]
        );
    }

    /**
     * Get statistics for a specific client: total projects and total payments made.
     *
     * @param int $clientId
     * @return array<string, mixed>
     */
    public function getStats(int $clientId): array
    {
        if ($clientId <= 0) {
            return [
                'total_projects'     => 0,
                'active_projects'    => 0,
                'completed_projects' => 0,
                'total_payments'     => 0.0,
            ];
        }

        $projects = dbQueryOne(
            'SELECT
                COUNT(*) AS total_projects,
                COUNT(CASE WHEN status = "active" THEN 1 END) AS active_projects,
                COUNT(CASE WHEN status = "completed" THEN 1 END) AS completed_projects
             FROM projects
             WHERE client_id = ?',
            [$clientId]
        );

        $payments = dbQueryOne(
            'SELECT COALESCE(SUM(amount), 0) AS total_payments
             FROM payments
             WHERE client_id = ?',
            [$clientId]
        );

        return [
            'total_projects'     => (int)($projects['total_projects'] ?? 0),
            'active_projects'    => (int)($projects['active_projects'] ?? 0),
            'completed_projects' => (int)($projects['completed_projects'] ?? 0),
            'total_payments'     => (float)($payments['total_payments'] ?? 0.0),
        ];
    }
}
