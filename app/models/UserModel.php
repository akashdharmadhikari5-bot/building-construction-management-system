<?php
/**
 * BCMS — Building Construction Management System
 * UserModel: Handles authentication and user management.
 */

declare(strict_types=1);

class UserModel
{
    /**
     * Find a user record by email address.
     *
     * @param string $email
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }

        return dbQueryOne(
            'SELECT user_id, name, email, password, role, linked_id, is_active, created_at, updated_at
             FROM users
             WHERE email = ?
             LIMIT 1',
            [$email]
        );
    }

    /**
     * Find a user record by user ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return dbQueryOne(
            'SELECT user_id, name, email, password, role, linked_id, is_active, created_at, updated_at
             FROM users
             WHERE user_id = ?
             LIMIT 1',
            [$id]
        );
    }

    /**
     * Create a new user with bcrypt password hashing.
     *
     * @param array<string, mixed> $data
     * @return int Inserted user_id
     * @throws InvalidArgumentException
     */
    public function create(array $data): int
    {
        $name     = trim((string)($data['name'] ?? ''));
        $email    = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $role     = trim((string)($data['role'] ?? 'worker'));
        $linkedId = isset($data['linked_id']) && $data['linked_id'] !== '' ? (int)$data['linked_id'] : null;
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        if ($name === '') {
            throw new InvalidArgumentException('User name is required.');
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        if (strlen($password) < 6) {
            throw new InvalidArgumentException('Password must be at least 6 characters long.');
        }

        $allowedRoles = ['admin', 'client', 'worker'];
        if (!in_array($role, $allowedRoles, true)) {
            throw new InvalidArgumentException('Invalid role specified: ' . $role);
        }

        // Check if email already registered
        $existing = $this->findByEmail($email);
        if ($existing !== null) {
            throw new InvalidArgumentException('Email address is already registered.');
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        return dbInsert(
            'INSERT INTO users (name, email, password, role, linked_id, is_active)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$name, $email, $passwordHash, $role, $linkedId, $isActive]
        );
    }

    /**
     * Update an existing user's details.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     * @throws InvalidArgumentException
     */
    public function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid user ID.');
        }

        $existing = $this->findById($id);
        if ($existing === null) {
            throw new InvalidArgumentException('User not found.');
        }

        $fields = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $name = trim((string)$data['name']);
            if ($name === '') {
                throw new InvalidArgumentException('User name cannot be empty.');
            }
            $fields[] = 'name = ?';
            $params[] = $name;
        }

        if (array_key_exists('email', $data)) {
            $email = trim((string)$data['email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('A valid email address is required.');
            }
            // Check email uniqueness if changed
            if (strcasecmp($email, $existing['email']) !== 0) {
                $other = $this->findByEmail($email);
                if ($other !== null && (int)$other['user_id'] !== $id) {
                    throw new InvalidArgumentException('Email address is already in use by another user.');
                }
            }
            $fields[] = 'email = ?';
            $params[] = $email;
        }

        if (array_key_exists('role', $data)) {
            $role = trim((string)$data['role']);
            $allowedRoles = ['admin', 'client', 'worker'];
            if (!in_array($role, $allowedRoles, true)) {
                throw new InvalidArgumentException('Invalid role specified.');
            }
            $fields[] = 'role = ?';
            $params[] = $role;
        }

        if (array_key_exists('linked_id', $data)) {
            $linkedId = $data['linked_id'] !== null && $data['linked_id'] !== '' ? (int)$data['linked_id'] : null;
            $fields[] = 'linked_id = ?';
            $params[] = $linkedId;
        }

        if (array_key_exists('is_active', $data)) {
            $fields[] = 'is_active = ?';
            $params[] = (int)(bool)$data['is_active'];
        }

        if (isset($data['password']) && (string)$data['password'] !== '') {
            $password = (string)$data['password'];
            if (strlen($password) < 6) {
                throw new InvalidArgumentException('Password must be at least 6 characters long.');
            }
            $fields[] = 'password = ?';
            $params[] = password_hash($password, PASSWORD_BCRYPT);
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE user_id = ?';
        dbExecute($sql, $params);

        return true;
    }

    /**
     * Verify input plain text password against stored hash.
     *
     * @param string $input
     * @param string $hash
     * @return bool
     */
    public function verifyPassword(string $input, string $hash): bool
    {
        return password_verify($input, $hash);
    }

    /**
     * Get all users ordered by creation date descending.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllUsers(): array
    {
        return dbQuery(
            'SELECT user_id, name, email, role, linked_id, is_active, created_at, updated_at
             FROM users
             ORDER BY created_at DESC'
        );
    }

    /**
     * Deactivate a user by ID.
     *
     * @param int $id
     * @return bool
     * @throws InvalidArgumentException
     */
    public function deactivate(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid user ID.');
        }

        $affected = dbExecute('UPDATE users SET is_active = 0 WHERE user_id = ?', [$id]);
        return $affected > 0;
    }

    /**
     * Change user password.
     *
     * @param int $id
     * @param string $newPassword
     * @return bool
     * @throws InvalidArgumentException
     */
    public function changePassword(int $id, string $newPassword): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid user ID.');
        }

        if (strlen($newPassword) < 6) {
            throw new InvalidArgumentException('New password must be at least 6 characters long.');
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $affected = dbExecute('UPDATE users SET password = ? WHERE user_id = ?', [$hash, $id]);

        return $affected > 0;
    }
}
