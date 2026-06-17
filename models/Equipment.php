<?php
/**
 * Equipment Model
 * Handles equipment data operations
 * Following CRUD principles and SDLC best practices
 */

require_once __DIR__ . '/BaseModel.php';

class Equipment extends BaseModel {
    protected $table = 'equipment';
    
    /**
     * Get equipment with category information
     * @return array Equipment records with category names
     */
    public function findAllWithCategory() {
        try {
            $sql = "SELECT e.*, c.name AS category_name 
                    FROM {$this->table} e 
                    JOIN categories c ON e.category_id = c.id 
                    ORDER BY e.created_at DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("FindAllWithCategory failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get equipment by ID with serial codes
     * @param int $id Equipment ID
     * @return array|false Equipment data with serial codes or false if not found
     */
    public function findWithSerials($id) {
        try {
            // Get equipment data
            $equipment = $this->find($id);
            if (!$equipment) {
                return false;
            }
            
            // Get serial codes
            $stmt = $this->db->prepare(
                "SELECT serial_code FROM equipment_serials WHERE equipment_id = ?"
            );
            $stmt->execute([$id]);
            $equipment['serial_codes'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            return $equipment;
        } catch (PDOException $e) {
            error_log("FindWithSerials failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add equipment with serial codes
     * @param array $data Equipment data
     * @param array $serialCodes Serial codes
     * @return int|false Equipment ID on success, false on failure
     */
    public function createWithSerials($data, $serialCodes) {
        try {
            $this->db->beginTransaction();
            
            // Insert equipment
            $equipmentId = $this->create($data);
            if (!$equipmentId) {
                throw new Exception("Failed to create equipment");
            }
            
            // Insert serial codes
            $stmt = $this->db->prepare(
                "INSERT INTO equipment_serials (equipment_id, serial_code) VALUES (?, ?)"
            );
            foreach ($serialCodes as $serialCode) {
                $stmt->execute([$equipmentId, trim($serialCode)]);
            }
            
            $this->db->commit();
            
            if (isset($_SESSION['user_id'])) {
                logActivity('CREATE', $_SESSION['user_id'], 
                    "Created equipment '{$data['name']}' with " . count($serialCodes) . " serial codes");
            }
            
            return $equipmentId;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("CreateWithSerials failed: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("CreateWithSerials failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update equipment with serial codes
     * @param int $id Equipment ID
     * @param array $data Equipment data
     * @param array|null $serialCodes New serial codes (null to keep existing)
     * @return bool True on success, false on failure
     */
    public function updateWithSerials($id, $data, $serialCodes = null) {
        try {
            $this->db->beginTransaction();
            
            // Update equipment
            if (!$this->update($id, $data)) {
                throw new Exception("Failed to update equipment");
            }
            
            // Update serial codes if provided
            if ($serialCodes !== null) {
                // Delete existing serial codes
                $stmt = $this->db->prepare(
                    "DELETE FROM equipment_serials WHERE equipment_id = ?"
                );
                $stmt->execute([$id]);
                
                // Insert new serial codes
                $stmt = $this->db->prepare(
                    "INSERT INTO equipment_serials (equipment_id, serial_code) VALUES (?, ?)"
                );
                foreach ($serialCodes as $serialCode) {
                    $stmt->execute([$id, trim($serialCode)]);
                }
            }
            
            $this->db->commit();
            
            if (isset($_SESSION['user_id'])) {
                logActivity('UPDATE', $_SESSION['user_id'], 
                    "Updated equipment ID: {$id}");
            }
            
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("UpdateWithSerials failed: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("UpdateWithSerials failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if serial code exists
     * @param string $serialCode Serial code to check
     * @param int|null $excludeId Exclude this equipment ID (for updates)
     * @return bool True if exists, false otherwise
     */
    public function serialCodeExists($serialCode, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) as total FROM equipment_serials WHERE serial_code = ?";
            if ($excludeId) {
                $sql .= " AND equipment_id != (SELECT equipment_id FROM equipment_serials WHERE serial_code = ? LIMIT 1)";
            }
            $stmt = $this->db->prepare($sql);
            if ($excludeId) {
                $stmt->execute([$serialCode, $serialCode]);
            } else {
                $stmt->execute([$serialCode]);
            }
            return $stmt->fetch()['total'] > 0;
        } catch (PDOException $e) {
            error_log("SerialCodeExists failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get available quantity for equipment
     * @param int $id Equipment ID
     * @return int Available quantity
     */
    public function getAvailableQuantity($id) {
        try {
            $sql = "SELECT e.quantity - COALESCE(SUM(CASE WHEN b.status = 'active' THEN 1 ELSE 0 END), 0) as available
                    FROM {$this->table} e
                    LEFT JOIN borrowings b ON e.id = b.equipment_id
                    WHERE e.id = ?
                    GROUP BY e.id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            return $result ? (int)$result['available'] : 0;
        } catch (PDOException $e) {
            error_log("GetAvailableQuantity failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Search equipment
     * @param string $query Search query
     * @return array Matching equipment records
     */
    public function search($query) {
        try {
            $searchTerm = "%{$query}%";
            $stmt = $this->db->prepare(
                "SELECT e.*, c.name AS category_name 
                 FROM {$this->table} e 
                 JOIN categories c ON e.category_id = c.id 
                 WHERE e.name LIKE ? OR e.description LIKE ? OR c.name LIKE ?
                 ORDER BY e.created_at DESC"
            );
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Search failed: " . $e->getMessage());
            return [];
        }
    }
}
