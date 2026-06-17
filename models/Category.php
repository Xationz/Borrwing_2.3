<?php
/**
 * Category Model
 * Handles category data operations
 * Following CRUD principles and SDLC best practices
 */

require_once __DIR__ . '/BaseModel.php';

class Category extends BaseModel {
    protected $table = 'categories';
    
    /**
     * Get all categories with equipment count
     * @return array Categories with equipment counts
     */
    public function findAllWithCount() {
        try {
            $sql = "SELECT c.*, COUNT(e.id) as equipment_count 
                    FROM {$this->table} c 
                    LEFT JOIN equipment e ON c.id = e.category_id 
                    GROUP BY c.id 
                    ORDER BY c.name ASC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("FindAllWithCount failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if category has associated equipment
     * @param int $id Category ID
     * @return bool True if has equipment, false otherwise
     */
    public function hasEquipment($id) {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total FROM equipment WHERE category_id = ?"
            );
            $stmt->execute([$id]);
            return $stmt->fetch()['total'] > 0;
        } catch (PDOException $e) {
            error_log("HasEquipment failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete category safely
     * @param int $id Category ID
     * @return array ['success' => bool, 'message' => string]
     */
    public function deleteSafe($id) {
        if ($this->hasEquipment($id)) {
            return [
                'success' => false,
                'message' => 'Cannot delete category with associated equipment'
            ];
        }
        
        if ($this->delete($id)) {
            return [
                'success' => true,
                'message' => 'Category deleted successfully'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to delete category'
        ];
    }
}
