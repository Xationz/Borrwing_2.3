<?php
/**
 * Base Model Class
 * Provides common database operations for all models
 * Following CRUD principles and SDLC best practices
 */

class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Find record by ID
     * @param int $id Primary key value
     * @return array|false Record data or false if not found
     */
    public function find($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Find failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all records
     * @param string $orderBy Column to order by
     * @param string $direction Sort direction (ASC/DESC)
     * @return array All records
     */
    public function findAll($orderBy = 'created_at', $direction = 'DESC') {
        try {
            $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY {$orderBy} {$direction}");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("FindAll failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get paginated records
     * @param int $page Current page number
     * @param int $perPage Records per page
     * @param string $orderBy Column to order by
     * @param string $direction Sort direction
     * @return array ['data' => array, 'pagination' => array]
     */
    public function paginate($page = 1, $perPage = 10, $orderBy = 'created_at', $direction = 'DESC') {
        try {
            // Get total count
            $countStmt = $this->db->query("SELECT COUNT(*) as total FROM {$this->table}");
            $total = $countStmt->fetch()['total'];
            
            // Get paginated data
            $offset = ($page - 1) * $perPage;
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->table} ORDER BY {$orderBy} {$direction} LIMIT ? OFFSET ?"
            );
            $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return [
                'data' => $stmt->fetchAll(),
                'pagination' => paginate($total, $perPage, $page)
            ];
        } catch (PDOException $e) {
            error_log("Paginate failed: " . $e->getMessage());
            return ['data' => [], 'pagination' => ['totalPages' => 0, 'currentPage' => $page]];
        }
    }
    
    /**
     * Insert new record
     * @param array $data Record data
     * @return int|false Last insert ID or false on failure
     */
    public function create($data) {
        try {
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})"
            );
            
            $stmt->execute($data);
            
            // Log activity if user is logged in
            if (isset($_SESSION['user_id'])) {
                logActivity('CREATE', $_SESSION['user_id'], "Created record in {$this->table}");
            }
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Create failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update record
     * @param int $id Primary key value
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public function update($id, $data) {
        try {
            $set = [];
            foreach (array_keys($data) as $column) {
                $set[] = "{$column} = :{$column}";
            }
            $setString = implode(', ', $set);
            
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} SET {$setString} WHERE {$this->primaryKey} = :id"
            );
            
            $data['id'] = $id;
            $stmt->execute($data);
            
            // Log activity if user is logged in
            if (isset($_SESSION['user_id'])) {
                logActivity('UPDATE', $_SESSION['user_id'], "Updated record in {$this->table} with ID: {$id}");
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Update failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete record
     * @param int $id Primary key value
     * @return bool True on success, false on failure
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?"
            );
            $stmt->execute([$id]);
            
            // Log activity if user is logged in
            if (isset($_SESSION['user_id'])) {
                logActivity('DELETE', $_SESSION['user_id'], "Deleted record from {$this->table} with ID: {$id}");
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Delete failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Count records
     * @param string|null $where WHERE clause (optional)
     * @param array $params Parameters for prepared statement
     * @return int Total count
     */
    public function count($where = null, $params = []) {
        try {
            $sql = "SELECT COUNT(*) as total FROM {$this->table}";
            if ($where) {
                $sql .= " WHERE {$where}";
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch()['total'];
        } catch (PDOException $e) {
            error_log("Count failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Find records by condition
     * @param string $where WHERE clause
     * @param array $params Parameters for prepared statement
     * @param string $orderBy Column to order by
     * @param string $direction Sort direction
     * @return array Matching records
     */
    public function findBy($where, $params = [], $orderBy = 'created_at', $direction = 'DESC') {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderBy} {$direction}"
            );
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("FindBy failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Find single record by condition
     * @param string $where WHERE clause
     * @param array $params Parameters for prepared statement
     * @return array|false Single record or false if not found
     */
    public function findOneBy($where, $params = []) {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE {$where} LIMIT 1"
            );
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("FindOneBy failed: " . $e->getMessage());
            return false;
        }
    }
}
