<?php
/**
 * Equipment Controller
 * Handles equipment-related requests
 * Following MVC architecture and SDLC best practices
 */

require_once __DIR__ . '/../models/Equipment.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../includes/functions.php';

class EquipmentController {
    private $equipmentModel;
    private $categoryModel;
    
    public function __construct() {
        $this->equipmentModel = new Equipment();
        $this->categoryModel = new Category();
    }
    
    /**
     * Display equipment list (READ)
     */
    public function index() {
        requireAuth();
        requireRole('admin');
        
        $equipment = $this->equipmentModel->findAllWithCategory();
        $categories = $this->categoryModel->findAll();
        
        return [
            'equipment' => $equipment,
            'categories' => $categories
        ];
    }
    
    /**
     * Show create form
     */
    public function create() {
        requireAuth();
        requireRole('admin');
        
        $categories = $this->categoryModel->findAll();
        return ['categories' => $categories];
    }
    
    /**
     * Store new equipment (CREATE)
     */
    public function store($data, $files) {
        requireAuth();
        requireRole('admin');
        
        // Validate CSRF token
        if (!verifyCSRFToken($data['csrf_token'] ?? '')) {
            return ['success' => false, 'message' => 'Invalid security token'];
        }
        
        // Validate input
        $errors = $this->validateEquipmentData($data);
        if (!empty($errors)) {
            return ['success' => false, 'message' => implode(', ', $errors)];
        }
        
        // Handle image upload
        $imageName = null;
        if (isset($files['image']) && $files['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadFile($files['image'], 'Uploads/', ['jpg', 'jpeg', 'png']);
            if (!$uploadResult['success']) {
                return ['success' => false, 'message' => $uploadResult['error']];
            }
            $imageName = $uploadResult['filename'];
        }
        
        // Prepare data
        $equipmentData = [
            'name' => sanitize($data['name']),
            'description' => sanitize($data['description'] ?? ''),
            'category_id' => (int)$data['category_id'],
            'quantity' => (int)$data['quantity'],
            'image' => $imageName
        ];
        
        // Validate serial codes
        $serialCodes = $data['serial_codes'] ?? [];
        if (count($serialCodes) !== $equipmentData['quantity']) {
            return ['success' => false, 'message' => 'Number of serial codes must match quantity'];
        }
        
        // Check for empty or duplicate serial codes
        $cleanSerials = array_map('trim', $serialCodes);
        if (in_array('', $cleanSerials, true)) {
            return ['success' => false, 'message' => 'All serial code fields are required'];
        }
        if (count($cleanSerials) !== count(array_unique($cleanSerials))) {
            return ['success' => false, 'message' => 'Duplicate serial codes detected'];
        }
        
        // Check for existing serial codes
        foreach ($cleanSerials as $serial) {
            if ($this->equipmentModel->serialCodeExists($serial)) {
                return ['success' => false, 'message' => "Serial code '{$serial}' already exists"];
            }
        }
        
        // Create equipment with serial codes
        $result = $this->equipmentModel->createWithSerials($equipmentData, $cleanSerials);
        
        if ($result) {
            return ['success' => true, 'message' => 'Equipment added successfully', 'id' => $result];
        }
        
        return ['success' => false, 'message' => 'Failed to add equipment'];
    }
    
    /**
     * Show edit form (READ single)
     */
    public function edit($id) {
        requireAuth();
        requireRole('admin');
        
        $equipment = $this->equipmentModel->findWithSerials($id);
        if (!$equipment) {
            return null;
        }
        
        $categories = $this->categoryModel->findAll();
        
        return [
            'equipment' => $equipment,
            'categories' => $categories
        ];
    }
    
    /**
     * Update equipment (UPDATE)
     */
    public function update($id, $data, $files) {
        requireAuth();
        requireRole('admin');
        
        // Validate CSRF token
        if (!verifyCSRFToken($data['csrf_token'] ?? '')) {
            return ['success' => false, 'message' => 'Invalid security token'];
        }
        
        // Validate input
        $errors = $this->validateEquipmentData($data, $id);
        if (!empty($errors)) {
            return ['success' => false, 'message' => implode(', ', $errors)];
        }
        
        // Get existing equipment
        $existing = $this->equipmentModel->find($id);
        if (!$existing) {
            return ['success' => false, 'message' => 'Equipment not found'];
        }
        
        // Handle image upload
        $imageName = $existing['image'];
        if (isset($files['image']) && $files['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadFile($files['image'], 'Uploads/', ['jpg', 'jpeg', 'png']);
            if (!$uploadResult['success']) {
                return ['success' => false, 'message' => $uploadResult['error']];
            }
            $imageName = $uploadResult['filename'];
            
            // Delete old image if exists
            if ($existing['image'] && file_exists('Uploads/' . $existing['image'])) {
                unlink('Uploads/' . $existing['image']);
            }
        }
        
        // Prepare data
        $equipmentData = [
            'name' => sanitize($data['name']),
            'description' => sanitize($data['description'] ?? ''),
            'category_id' => (int)$data['category_id'],
            'quantity' => (int)$data['quantity'],
            'image' => $imageName
        ];
        
        // Update equipment (serial codes handled separately if provided)
        $serialCodes = isset($data['serial_codes']) ? $data['serial_codes'] : null;
        $result = $this->equipmentModel->updateWithSerials($id, $equipmentData, $serialCodes);
        
        if ($result) {
            return ['success' => true, 'message' => 'Equipment updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update equipment'];
    }
    
    /**
     * Delete equipment (DELETE)
     */
    public function destroy($id) {
        requireAuth();
        requireRole('admin');
        
        $equipment = $this->equipmentModel->find($id);
        if (!$equipment) {
            return ['success' => false, 'message' => 'Equipment not found'];
        }
        
        // Delete image if exists
        if ($equipment['image'] && file_exists('Uploads/' . $equipment['image'])) {
            unlink('Uploads/' . $equipment['image']);
        }
        
        if ($this->equipmentModel->delete($id)) {
            return ['success' => true, 'message' => 'Equipment deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete equipment'];
    }
    
    /**
     * Search equipment
     */
    public function search($query) {
        requireAuth();
        
        $results = $this->equipmentModel->search($query);
        return ['results' => $results];
    }
    
    /**
     * Validate equipment data
     */
    private function validateEquipmentData($data, $id = null) {
        $errors = [];
        
        if (empty(trim($data['name'] ?? ''))) {
            $errors[] = 'Equipment name is required';
        }
        
        if (!isset($data['category_id']) || (int)$data['category_id'] <= 0) {
            $errors[] = 'Valid category is required';
        }
        
        if (!isset($data['quantity']) || (int)$data['quantity'] < 1) {
            $errors[] = 'Quantity must be at least 1';
        }
        
        return $errors;
    }
}
