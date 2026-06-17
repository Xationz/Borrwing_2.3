# SDLC (Software Development Life Cycle) Documentation

## Equipment Borrowing System - MVC & CRUD Implementation

---

## Table of Contents

1. [SDLC Phases Overview](#sdlc-phases-overview)
2. [Phase 1: Planning & Requirement Analysis](#phase-1-planning--requirement-analysis)
3. [Phase 2: System Design](#phase-2-system-design)
4. [Phase 3: Implementation (Coding)](#phase-3-implementation-coding)
5. [Phase 4: Testing](#phase-4-testing)
6. [Phase 5: Deployment](#phase-5-deployment)
7. [Phase 6: Maintenance](#phase-6-maintenance)
8. [CRUD Operations Matrix](#crud-operations-matrix)

---

## SDLC Phases Overview

| Phase | Description | Status |
|-------|-------------|--------|
| 1. Planning | Requirement gathering, feasibility study | Complete |
| 2. Design | Architecture design, database schema, UI/UX | Complete |
| 3. Implementation | Coding, MVC structure, CRUD operations | In Progress |
| 4. Testing | Unit testing, integration testing, security testing | Pending |
| 5. Deployment | Server setup, database migration, go-live | Pending |
| 6. Maintenance | Bug fixes, updates, feature enhancements | Future |

---

## Phase 1: Planning & Requirement Analysis

### Business Requirements

- **System Purpose**: Manage equipment borrowing and returning processes
- **Target Users**: 
  - Administrators (manage equipment, approve returns)
  - Regular Users (browse, borrow, return equipment)

### Functional Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-001 | User login/logout | High |
| FR-002 | Admin can add/edit/delete equipment | High |
| FR-003 | User can browse available equipment | High |
| FR-004 | User can request to borrow equipment | High |
| FR-005 | Admin can approve/reject borrowing requests | High |
| FR-006 | User can return borrowed equipment | High |

### Non-Functional Requirements

| ID | Requirement | Description |
|----|-------------|-------------|
| NFR-001 | Security | Password hashing, CSRF protection, SQL injection prevention |
| NFR-002 | Performance | Page load time under 2 seconds |
| NFR-003 | Maintainability | Modular code with documentation |

---

## Phase 2: System Design

### Architecture Design: MVC Pattern

```
User Interface (Browser)
        |
        v
Controller Layer (Request Handling)
        |
        v
Model Layer (Business Logic)
        |
        v
Database Layer (MySQL/MariaDB)
```

### Database Schema

**Tables:**
1. users - User accounts and authentication
2. categories - Equipment categories
3. equipment - Equipment inventory
4. equipment_serials - Individual item tracking
5. borrowings - Borrowing transactions

### Security Design

| Threat | Mitigation |
|--------|------------|
| SQL Injection | Prepared statements (PDO) |
| XSS Attack | Input sanitization, output escaping |
| CSRF | Token validation on forms |
| Password Theft | Bcrypt password hashing |

---

## Phase 3: Implementation (Coding)

### File Structure

```
project01/
├── includes/
│   ├── db.php              # Database connection (Singleton)
│   └── functions.php       # Helper functions
├── models/
│   ├── BaseModel.php       # Base CRUD operations
│   ├── Equipment.php       # Equipment business logic
│   ├── Category.php        # Category operations
│   ├── User.php            # User authentication
│   └── Borrowing.php       # Borrowing workflows
├── controllers/
│   └── EquipmentController.php  # Request handler
├── views/                  # UI templates
├── partials/               # Reusable components
└── Database/               # SQL migrations
```

### CRUD Implementation Status

| Entity | Create | Read | Update | Delete | Status |
|--------|--------|------|--------|--------|--------|
| Equipment | Yes | Yes | Yes | Yes | Complete |
| Category | Yes | Yes | Yes | Yes | Complete |
| User | Yes | Yes | Yes | Yes | Complete |
| Borrowing | Yes | Yes | Yes | Yes | Complete |

---

## Phase 4: Testing

### Test Plan

| Test Type | Description | Status |
|-----------|-------------|--------|
| Unit Testing | Test individual methods | Pending |
| Integration Testing | Test module interactions | Pending |
| Security Testing | Vulnerability assessment | Pending |
| User Acceptance Testing | End-user validation | Pending |

---

## Phase 5: Deployment

### Environment Requirements

| Component | Requirement |
|-----------|-------------|
| Web Server | Apache 2.4+ / Nginx |
| PHP Version | 7.4 or higher |
| Database | MySQL 5.7+ / MariaDB 10.3+ |

### Deployment Checklist

- [ ] Backup existing database
- [ ] Upload files to server
- [ ] Configure database connection
- [ ] Run database migrations
- [ ] Set file permissions
- [ ] Test all functionality

---

## Phase 6: Maintenance

### Monitoring

| Metric | Frequency |
|--------|-----------|
| Server Uptime | Continuous |
| Error Logs | Daily review |
| Security Updates | Monthly |

### Backup Strategy

| Data Type | Frequency | Retention |
|-----------|-----------|-----------|
| Database | Daily | 30 days |
| Uploaded Files | Daily | 30 days |

---

## CRUD Operations Matrix

### Equipment Operations

| Operation | Method | Controller | Model |
|-----------|--------|------------|-------|
| Create | POST | EquipmentController::store() | Equipment::createWithSerials() |
| Read All | GET | EquipmentController::index() | Equipment::findAllWithCategory() |
| Read One | GET | EquipmentController::edit() | Equipment::findWithSerials() |
| Update | POST | EquipmentController::update() | Equipment::updateWithSerials() |
| Delete | GET/POST | EquipmentController::destroy() | Equipment::delete() |

### Category Operations

| Operation | Method | Model |
|-----------|--------|-------|
| Create | POST | Category::create() |
| Read All | GET | Category::findAllWithCount() |
| Update | POST | Category::update() |
| Delete | GET/POST | Category::deleteSafe() |

### User Operations

| Operation | Method | Model |
|-----------|--------|-------|
| Create | POST | User::createUser() |
| Read All | GET | User::findAll() |
| Update | POST | User::updateUser() |
| Delete | GET/POST | User::deleteSafe() |

### Borrowing Operations

| Operation | Method | Model |
|-----------|--------|-------|
| Create | POST | Borrowing::createBorrowing() |
| Read All | GET | Borrowing::findAllWithDetails() |
| Return | POST | Borrowing::returnEquipment() |
| Approve | POST | Borrowing::approveReturn() |

---

## Security Features Implemented

1. **Input Sanitization** - All user inputs are sanitized
2. **CSRF Protection** - Token validation on all forms
3. **Password Hashing** - Bcrypt for secure password storage
4. **Prepared Statements** - PDO prevents SQL injection
5. **Role-Based Access** - Admin and user roles enforced
6. **Session Management** - Secure session handling

---

## Benefits of MVC Architecture

| Benefit | Description |
|---------|-------------|
| Maintainability | Easy to locate and fix bugs |
| Scalability | Add features without breaking existing code |
| Reusability | Share code across different parts |
| Security | Centralized security measures |
| Testability | Each component can be tested independently |

---

*Document Version: 1.0*
*Last Updated: June 17, 2025*
