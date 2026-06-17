<?php
/**
 * User Model
 * Handles user data operations
 * Following CRUD principles and SDLC best practices
 */

require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel {
    protected $table = 'users';
    
    /**
     * Find user by username
     * @param string $username Username
     * @return array|false User data or false if not found
     */
    public function findByUsername($username) {
        return $this->findOneBy('username = ?', [$username]);
    }
    
    /**
     * Verify user credentials
     * @param string $username Username
     * @param string $password Plain text password
     * @return array|false User data on success, false on failure
     */
    public function verifyCredentials($username, $password) {
        $user = $this->findByUsername($username);
        
        if ($user && password_verify($password, $user['password'])) {
            // Remove password from returned data
            unset($user['password']);
            return $user;
        }
        
        return false;
    }
    
    /**
     * Create user with hashed password
     * @param array $data User data (username, password, role, etc.)
     * @return int|false User ID on success, false on failure
     */
    public function createUser($data) {
        // Hash password
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        // Set default role if not provided
        if (!isset($data['role'])) {
            $data['role'] = 'user';
        }
        
        return $this->create($data);
    }
    
    /**
     * Update user with optional password
     * @param int $id User ID
     * @param array $data User data
     * @return bool True on success, false on failure
     */
    public function updateUser($id, $data) {
        // Hash password only if provided and not empty
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        } else {
            unset($data['password']);
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Check if username exists
     * @param string $username Username to check
     * @param int|null $excludeId Exclude this user ID (for updates)
     * @return bool True if exists, false otherwise
     */
    public function usernameExists($username, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE username = ?";
            $params = [$username];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch()['total'] > 0;
        } catch (PDOException $e) {
            error_log("UsernameExists failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get users by role
     * @param string $role User role
     * @return array Users with specified role
     */
    public function findByRole($role) {
        return $this->findBy('role = ?', [$role], 'created_at', 'DESC');
    }
    
    /**
     * Delete user safely
     * @param int $id User ID
     * @return array ['success' => bool, 'message' => string]
     */
    public function deleteSafe($id) {
        // Prevent deleting own account
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
            return [
                'success' => false,
                'message' => 'Cannot delete your own account'
            ];
        }
        
        // Check for active borrowings
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM borrowings WHERE user_id = ? AND status = 'borrowed'"
            );
            $stmt->execute([$id]);
            $count = $stmt->fetch()['total'];
            
            if ($count > 0) {
                return [
                    'success' => false,
                    'message' => "Cannot delete user with {$count} active borrowing(s)"
                ];
            }
        } catch (PDOException $e) {
            error_log("DeleteSafe check failed: " . $e->getMessage());
        }
        
        if ($this->delete($id)) {
            return [
                'success' => true,
                'message' => 'User deleted successfully'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to delete user'
        ];
    }
}
