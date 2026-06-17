# MVC Architecture & CRUD Implementation Guide

## Overview
This document describes the implementation of **MVC (Model-View-Controller)** architecture and **CRUD (Create, Read, Update, Delete)** operations in the Equipment Borrowing System, following **SDLC (Software Development Life Cycle)** best practices.

---

## 📁 Project Structure

```
project01/
├── includes/              # Core utilities and configuration
│   ├── db.php            # Database connection (Singleton pattern)
│   └── functions.php     # Common helper functions
├── models/               # Data layer (Business logic)
│   ├── BaseModel.php     # Base model with common CRUD operations
│   ├── Equipment.php     # Equipment-specific operations
│   ├── Category.php      # Category-specific operations
│   └── User.php          # User authentication & management
├── controllers/          # Application logic layer
│   └── EquipmentController.php  # Equipment request handler
├── views/                # Presentation layer (UI templates)
├── partials/             # Reusable UI components
├── Uploads/              # Uploaded files storage
├── Database/             # SQL scripts and migrations
└── *.php                 # Legacy files (being refactored)
```

---

## 🏗️ MVC Architecture Components

### 1. Model Layer (`/models`)
**Purpose:** Handle data access and business logic

#### BaseModel.php
Provides reusable CRUD operations:
- `find($id)` - Get single record by ID
- `findAll()` - Get all records
- `paginate()` - Get paginated results
- `create($data)` - Insert new record
- `update($id, $data)` - Update existing record
- `delete($id)` - Delete record
- `count()` - Count records
- `findBy($where, $params)` - Find by condition
- `findOneBy($where, $params)` - Find single by condition

#### Equipment.php (Example Model)
Extends BaseModel with specific operations:
- `findAllWithCategory()` - Join with categories table
- `findWithSerials($id)` - Include serial codes
- `createWithSerials($data, $serialCodes)` - Transaction-based creation
- `updateWithSerials($id, $data, $serialCodes)` - Update with serial management
- `serialCodeExists($serialCode)` - Validate uniqueness
- `getAvailableQuantity($id)` - Calculate available items
- `search($query)` - Full-text search

### 2. Controller Layer (`/controllers`)
**Purpose:** Handle user requests, validate input, call models, return responses

#### EquipmentController.php
Implements full CRUD operations:
- `index()` - List all equipment (READ)
- `create()` - Show create form
- `store($data, $files)` - Create new equipment (CREATE)
- `edit($id)` - Show edit form (READ single)
- `update($id, $data, $files)` - Update equipment (UPDATE)
- `destroy($id)` - Delete equipment (DELETE)
- `search($query)` - Search equipment

### 3. View Layer (`/views` & `/partials`)
**Purpose:** Present data to users (HTML/CSS/JavaScript)

---

## 🔄 CRUD Operations Flow

### CREATE Operation
```
User Request → Controller → Validation → Model → Database
     ↓
Response ← Flash Message ← Transaction Commit ← Success
```

**Example: Adding Equipment**
```php
// Controller method
public function store($data, $files) {
    // 1. Validate CSRF token
    if (!verifyCSRFToken($data['csrf_token'])) {
        return ['success' => false, 'message' => 'Invalid token'];
    }
    
    // 2. Validate input
    $errors = $this->validateEquipmentData($data);
    if (!empty($errors)) {
        return ['success' => false, 'message' => implode(', ', $errors)];
    }
    
    // 3. Handle file upload
    $uploadResult = uploadFile($files['image']);
    
    // 4. Prepare data
    $equipmentData = [
        'name' => sanitize($data['name']),
        'category_id' => (int)$data['category_id'],
        'quantity' => (int)$data['quantity']
    ];
    
    // 5. Create with transaction
    $result = $this->equipmentModel->createWithSerials($equipmentData, $serialCodes);
    
    return ['success' => $result !== false];
}
```

### READ Operation
```php
// Controller method
public function index() {
    requireAuth();
    requireRole('admin');
    
    $equipment = $this->equipmentModel->findAllWithCategory();
    $categories = $this->categoryModel->findAll();
    
    return compact('equipment', 'categories');
}
```

### UPDATE Operation
```php
// Controller method
public function update($id, $data, $files) {
    // 1. Verify ownership/existence
    $existing = $this->equipmentModel->find($id);
    if (!$existing) {
        return ['success' => false, 'message' => 'Not found'];
    }
    
    // 2. Validate and sanitize
    $equipmentData = ['name' => sanitize($data['name']), ...];
    
    // 3. Update with transaction
    $result = $this->equipmentModel->updateWithSerials($id, $equipmentData, $serialCodes);
    
    return ['success' => $result];
}
```

### DELETE Operation
```php
// Controller method
public function destroy($id) {
    // 1. Check existence
    $equipment = $this->equipmentModel->find($id);
    if (!$equipment) {
        return ['success' => false, 'message' => 'Not found'];
    }
    
    // 2. Clean up related files
    if ($equipment['image']) {
        unlink('Uploads/' . $equipment['image']);
    }
    
    // 3. Delete record
    return ['success' => $this->equipmentModel->delete($id)];
}
```

---

## 🛡️ Security Features

### 1. Input Sanitization
```php
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}
```

### 2. CSRF Protection
```php
// Generate token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
```

### 3. Authentication & Authorization
```php
function requireAuth() {
    if (!isLoggedIn()) {
        redirectWithMessage('login.php', 'Please log in', 'warning');
    }
}

function requireRole($roles) {
    requireAuth();
    if (!in_array($_SESSION['role'], (array)$roles)) {
        redirectWithMessage('login.php', 'Access denied', 'error');
    }
}
```

### 4. Prepared Statements (SQL Injection Prevention)
```php
$stmt = $db->prepare("SELECT * FROM equipment WHERE id = ?");
$stmt->execute([$id]);
```

### 5. Password Hashing
```php
// Hash
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Verify
password_verify($password, $hashedPassword);
```

---

## 📊 SDLC Best Practices Implemented

### 1. Planning & Analysis
- ✅ Clear separation of concerns (MVC)
- ✅ Defined data models and relationships
- ✅ Documented API methods

### 2. Design
- ✅ Singleton pattern for database connection
- ✅ Inheritance (BaseModel → Specific Models)
- ✅ Encapsulation of business logic

### 3. Implementation
- ✅ Consistent naming conventions
- ✅ Comprehensive error handling
- ✅ Transaction support for data integrity
- ✅ Activity logging

### 4. Testing
- ✅ Input validation at controller level
- ✅ Data validation at model level
- ✅ Error messages for debugging

### 5. Deployment
- ✅ Configuration separate from code
- ✅ Environment-ready structure
- ✅ Migration scripts in `/Database`

### 6. Maintenance
- ✅ Modular code for easy updates
- ✅ Activity logs for auditing
- ✅ Clear documentation

---

## 🔧 Usage Examples

### Using the Equipment Model Directly
```php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'models/Equipment.php';

$equipment = new Equipment();

// Get all equipment
$all = $equipment->findAllWithCategory();

// Get single equipment with serials
$item = $equipment->findWithSerials(1);

// Create new equipment
$id = $equipment->createWithSerials(
    ['name' => 'Laptop', 'category_id' => 1, 'quantity' => 5, 'image' => null],
    ['SN001', 'SN002', 'SN003', 'SN004', 'SN005']
);

// Update equipment
$equipment->updateWithSerials(1, ['name' => 'Updated Laptop'], null);

// Delete equipment
$equipment->delete(1);
```

### Using the Controller
```php
require_once 'controllers/EquipmentController.php';

$controller = new EquipmentController();

// List equipment
$data = $controller->index();

// Create equipment
$result = $controller->store($_POST, $_FILES);

// Update equipment
$result = $controller->update($id, $_POST, $_FILES);

// Delete equipment
$result = $controller->destroy($id);
```

---

## 📝 Migration Checklist

To migrate legacy code to new MVC structure:

1. **[ ] Create Models** for each entity:
   - Category ✓
   - Equipment ✓
   - User ✓
   - Borrowing (TODO)
   - Admin (use User model)

2. **[ ] Create Controllers** for each feature:
   - EquipmentController ✓
   - CategoryController (TODO)
   - UserController (TODO)
   - BorrowingController (TODO)

3. **[ ] Update Views** to use controllers:
   - Replace direct DB queries with controller calls
   - Use flash messages for feedback
   - Implement CSRF tokens in forms

4. **[ ] Add Security**:
   - CSRF protection on all forms
   - Input sanitization
   - Role-based access control

5. **[ ] Testing**:
   - Test all CRUD operations
   - Verify security measures
   - Check error handling

---

## 🎯 Benefits of This Architecture

| Benefit | Description |
|---------|-------------|
| **Maintainability** | Easy to locate and fix bugs |
| **Scalability** | Add features without breaking existing code |
| **Reusability** | Share code across different parts |
| **Security** | Centralized security measures |
| **Testability** | Each component can be tested independently |
| **Team Collaboration** | Different developers can work on different layers |

---

## 📚 Additional Resources

- **PDO Documentation**: https://www.php.net/manual/en/book.pdo.php
- **Password Hashing**: https://www.php.net/manual/en/function.password-hash.php
- **Prepared Statements**: https://www.php.net/manual/en/pdo.prepare.php
- **OWASP Security**: https://owasp.org/www-project-top-ten/

---

*Last Updated: 2025*
*Version: 1.0*
