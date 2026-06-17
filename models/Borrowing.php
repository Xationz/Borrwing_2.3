<?php
/**
 * Borrowing Model
 * Handles borrowing/returning operations
 * Following CRUD principles and SDLC best practices
 */

require_once __DIR__ . '/BaseModel.php';

class Borrowing extends BaseModel {
    protected $table = 'borrowings';
    
    /**
     * Get all borrowings with user and equipment details
     * @return array Borrowing records with related data
     */
    public function findAllWithDetails() {
        try {
            $sql = "SELECT b.*, 
                           u.username as user_name,
                           e.name as equipment_name,
                           es.serial_code
                    FROM {$this->table} b
                    JOIN users u ON b.user_id = u.id
                    JOIN equipment e ON b.equipment_id = e.id
                    LEFT JOIN equipment_serials es ON b.serial_id = es.id
                    ORDER BY b.created_at DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("FindAllWithDetails failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get borrowings by user ID
     * @param int $userId User ID
     * @return array User's borrowings with equipment details
     */
    public function findByUser($userId) {
        try {
            $sql = "SELECT b.*, 
                           e.name as equipment_name,
                           e.image as equipment_image,
                           es.serial_code
                    FROM {$this->table} b
                    JOIN equipment e ON b.equipment_id = e.id
                    LEFT JOIN equipment_serials es ON b.serial_id = es.id
                    WHERE b.user_id = ?
                    ORDER BY b.created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("FindByUser failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get active borrowings
     * @return array Active borrowing records
     */
    public function findActive() {
        return $this->findBy("status = 'borrowed' OR status = 'active'", [], 'borrow_date', 'DESC');
    }
    
    /**
     * Create new borrowing with serial code assignment
     * @param array $data Borrowing data
     * @return int|false Borrowing ID on success, false on failure
     */
    public function createBorrowing($data) {
        try {
            $this->db->beginTransaction();
            
            // Validate equipment availability
            $equipmentModel = new Equipment();
            $available = $equipmentModel->getAvailableQuantity($data['equipment_id']);
            
            if ($available < $data['quantity']) {
                throw new Exception("Insufficient equipment available");
            }
            
            // If serial_id not provided, get first available serial
            if (!isset($data['serial_id'])) {
                $stmt = $this->db->prepare(
                    "SELECT id FROM equipment_serials 
                     WHERE equipment_id = ? 
                     AND id NOT IN (
                         SELECT serial_id FROM borrowings 
                         WHERE status IN ('borrowed', 'active') AND serial_id IS NOT NULL
                     )
                     LIMIT 1"
                );
                $stmt->execute([$data['equipment_id']]);
                $serial = $stmt->fetch();
                if ($serial) {
                    $data['serial_id'] = $serial['id'];
                }
            }
            
            // Set default status
            if (!isset($data['status'])) {
                $data['status'] = 'active';
            }
            
            // Set borrow date if not provided
            if (!isset($data['borrow_date'])) {
                $data['borrow_date'] = date('Y-m-d');
            }
            
            // Create borrowing record
            $borrowingId = $this->create($data);
            
            $this->db->commit();
            
            if (isset($_SESSION['user_id'])) {
                logActivity('BORROW', $_SESSION['user_id'], 
                    "Borrowed {$data['quantity']} item(s) from equipment ID: {$data['equipment_id']}");
            }
            
            return $borrowingId;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("CreateBorrowing failed: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("CreateBorrowing failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Return borrowed equipment
     * @param int $id Borrowing ID
     * @return bool True on success, false on failure
     */
    public function returnEquipment($id) {
        try {
            $this->db->beginTransaction();
            
            // Get borrowing record
            $borrowing = $this->find($id);
            if (!$borrowing) {
                throw new Exception("Borrowing record not found");
            }
            
            if ($borrowing['status'] === 'returned') {
                throw new Exception("Equipment already returned");
            }
            
            // Update borrowing status
            $updateData = [
                'status' => 'returned',
                'return_date' => date('Y-m-d')
            ];
            
            if (!$this->update($id, $updateData)) {
                throw new Exception("Failed to update borrowing status");
            }
            
            $this->db->commit();
            
            if (isset($_SESSION['user_id'])) {
                logActivity('RETURN', $_SESSION['user_id'], 
                    "Returned borrowing ID: {$id}");
            }
            
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("ReturnEquipment failed: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("ReturnEquipment failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Approve return request
     * @param int $id Borrowing ID
     * @param int $approverId Admin ID approving the return
     * @return bool True on success, false on failure
     */
    public function approveReturn($id, $approverId) {
        try {
            $this->db->beginTransaction();
            
            // Get borrowing record
            $borrowing = $this->find($id);
            if (!$borrowing) {
                throw new Exception("Borrowing record not found");
            }
            
            // Update status
            $updateData = [
                'status' => 'returned',
                'return_date' => date('Y-m-d')
            ];
            
            if (!$this->update($id, $updateData)) {
                throw new Exception("Failed to approve return");
            }
            
            $this->db->commit();
            
            // Log activity
            logActivity('APPROVE_RETURN', $approverId, 
                "Approved return for borrowing ID: {$id}");
            
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("ApproveReturn failed: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("ApproveReturn failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get borrowing statistics
     * @return array Statistics data
     */
    public function getStatistics() {
        try {
            $stats = [];
            
            // Total borrowings
            $stats['total'] = $this->count();
            
            // Active borrowings
            $stats['active'] = $this->count("status IN ('borrowed', 'active')");
            
            // Returned today
            $stats['returned_today'] = $this->count(
                "DATE(return_date) = CURDATE()"
            );
            
            // Overdue (if you have due_date column)
            $stats['overdue'] = $this->count(
                "(status IN ('borrowed', 'active')) AND due_date < CURDATE()"
            );
            
            return $stats;
        } catch (PDOException $e) {
            error_log("GetStatistics failed: " . $e->getMessage());
            return ['total' => 0, 'active' => 0, 'returned_today' => 0, 'overdue' => 0];
        }
    }
    
    /**
     * Search borrowings
     * @param string $query Search query
     * @return array Matching borrowing records
     */
    public function search($query) {
        try {
            $searchTerm = "%{$query}%";
            $sql = "SELECT b.*, 
                           u.username as user_name,
                           e.name as equipment_name
                    FROM {$this->table} b
                    JOIN users u ON b.user_id = u.id
                    JOIN equipment e ON b.equipment_id = e.id
                    WHERE u.username LIKE ? 
                       OR e.name LIKE ? 
                       OR b.status LIKE ?
                    ORDER BY b.created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Search failed: " . $e->getMessage());
            return [];
        }
    }
}
