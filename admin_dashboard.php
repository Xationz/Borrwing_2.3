<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Add category
if (isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$name]);
        $swal_msg = "Swal.fire('Success', 'Category added successfully', 'success');";
    } else {
        $swal_msg = "Swal.fire('Error', 'Category name cannot be empty', 'error');";
    }
}

// Update category
if (isset($_POST['update_category'])) {
    $id = $_POST['category_id'];
    $name = trim($_POST['name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        $swal_msg = "Swal.fire('Success', 'Category updated successfully', 'success');";
    } else {
        $swal_msg = "Swal.fire('Error', 'Category name cannot be empty', 'error');";
    }
}

// Delete category
if (isset($_GET['delete_category'])) {
    $id = $_GET['delete_category'];
    try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $swal_msg = "Swal.fire('Success', 'Category deleted successfully', 'success');";
    } catch (PDOException $e) {
        $swal_msg = "Swal.fire('Error', 'Cannot delete category with associated equipment', 'error');";
    }
}

// Add equipment
if (isset($_POST['add_equipment'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category_id = $_POST['category_id'];
    $quantity = (int)$_POST['quantity'];
    $image = $_FILES['image']['name'];

    if (empty($name) || $quantity < 0) {
        $swal_msg = "Swal.fire('Error', 'Equipment name and valid quantity are required', 'error');";
    } elseif ($image && !in_array(strtolower(pathinfo($image, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'])) {
        $swal_msg = "Swal.fire('Error', 'Only JPG, JPEG, PNG files are allowed', 'error');";
    } else {
        if ($image) {
            $target = 'Uploads/' . basename($image);
            move_uploaded_file($_FILES['image']['tmp_name'], $target);
        } else {
            $image = null;
        }
        $stmt = $pdo->prepare("INSERT INTO equipment (name, description, category_id, quantity, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $category_id, $quantity, $image]);
        $swal_msg = "Swal.fire('Success', 'Equipment added successfully', 'success');";
    }
}

// Update equipment
if (isset($_POST['update_equipment'])) {
    $id = $_POST['equipment_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category_id = $_POST['category_id'];
    $quantity = (int)$_POST['quantity'];
    $image = $_FILES['image']['name'];

    if (empty($name) || $quantity < 0) {
        $swal_msg = "Swal.fire('Error', 'Equipment name and valid quantity are required', 'error');";
    } elseif ($image && !in_array(strtolower(pathinfo($image, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'])) {
        $swal_msg = "Swal.fire('Error', 'Only JPG, JPEG, PNG files are allowed', 'error');";
    } else {
        if ($image) {
            $target = 'Uploads/' . basename($image);
            move_uploaded_file($_FILES['image']['tmp_name'], $target);
        } else {
            $stmt = $pdo->prepare("SELECT image FROM equipment WHERE id = ?");
            $stmt->execute([$id]);
            $image = $stmt->fetchColumn();
        }
        $stmt = $pdo->prepare("UPDATE equipment SET name = ?, description = ?, category_id = ?, quantity = ?, image = ? WHERE id = ?");
        $stmt->execute([$name, $description, $category_id, $quantity, $image, $id]);
        $swal_msg = "Swal.fire('Success', 'Equipment updated successfully', 'success');";
    }
}

// Delete equipment
if (isset($_GET['delete_equipment'])) {
    $id = $_GET['delete_equipment'];
    try {
        $stmt = $pdo->prepare("DELETE FROM equipment WHERE id = ?");
        $stmt->execute([$id]);
        $swal_msg = "Swal.fire('Success', 'Equipment deleted successfully', 'success');";
    } catch (PDOException $e) {
        $swal_msg = "Swal.fire('Error', 'Cannot delete equipment with active borrowings', 'error');";
    }
}

// Add admin
if (isset($_POST['add_admin'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    if (empty($username) || empty($password)) {
        $swal_msg = "Swal.fire('Error', 'Username and password are required', 'error');";
    } else {
        $password = password_hash($password, PASSWORD_BCRYPT);
        $role = 'admin';
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $password, $role]);
            $swal_msg = "Swal.fire('Success', 'Admin added successfully', 'success');";
        } catch (PDOException $e) {
            $swal_msg = "Swal.fire('Error', 'Username already exists', 'error');";
        }
    }
}

// Update admin
if (isset($_POST['update_admin'])) {
    $id = $_POST['admin_id'];
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    if (empty($username)) {
        $swal_msg = "Swal.fire('Error', 'Username is required', 'error');";
    } else {
        try {
            if ($password) {
                $password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE id = ? AND role = 'admin'");
                $stmt->execute([$username, $password, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ? AND role = 'admin'");
                $stmt->execute([$username, $id]);
            }
            $swal_msg = "Swal.fire('Success', 'Admin updated successfully', 'success');";
        } catch (PDOException $e) {
            $swal_msg = "Swal.fire('Error', 'Username already exists', 'error');";
        }
    }
}

// Delete admin
if (isset($_GET['delete_admin'])) {
    $id = $_GET['delete_admin'];
    if ($id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
        $stmt->execute([$id]);
        $swal_msg = "Swal.fire('Success', 'Admin deleted successfully', 'success');";
    } else {
        $swal_msg = "Swal.fire('Error', 'Cannot delete your own account', 'error');";
    }
}

// Fetch data
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$equipment = $pdo->query("SELECT e.*, c.name AS category_name FROM equipment e JOIN categories c ON e.category_id = c.id")->fetchAll();
$admins = $pdo->query("SELECT * FROM users WHERE role = 'admin'")->fetchAll();
?>

<?php if (empty($_SERVER["HTTP_X_REQUESTED_WITH"])) { header("Location: spa_shell.php?page=admin_dashboard"); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Equipment Borrowing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <style>
        body { background-color: #EDE9FE; }
        .sidebar { background-color: #722ff9; color: white; height: 100vh; position: fixed; width: 250px; }
        .sidebar a { color: white; }
        .sidebar a:hover { background-color: #B8A2F9; }
        .content { margin-left: 250px; padding: 20px; }
        .card-header { background-color: #722ff9; color: white; }
        .btn-primary { background-color: #722ff9; border: none; }
        .btn-primary:hover { background-color: #B8A2F9; }
        .btn-info { background-color: #B8A2F9; border: none; }
        .btn-info:hover { background-color: #722ff9; }
        .equipment-img { max-width: 50px; height: auto; }
        .mt-3 { margin-top: 1rem; }
        @media (max-width: 768px) { .sidebar { width: 100%; height: auto; position: relative; } .content { margin-left: 0; } }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-3">
            <h5 class="text-white">Equipment Borrowing</h5>
        </div>
        <ul class="nav flex-column p-3">
            <li class="nav-item">
                <a class="nav-link active" href="categorie.php"><i class="bi bi-grid"></i> Categories</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="equipment.php"><i class="bi bi-grid"></i> Equipment</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="admins.php"><i class="bi bi-grid"></i> Admins</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="calendar.php"><i class="bi bi-grid"></i> Calendar</a>
            </li>


            <li class="nav-item">
                <a class="nav-link" href="borrowing_dashboard.php"><i class="bi bi-bar-chart"></i> Borrowing Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </li>
        </ul>
    </div>

    <!-- Content -->
    <div class="content">
        <h2 class="mb-4">Admin Dashboard</h2>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>

        <!-- Manage Categories -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Manage Categories</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Category Name</label>
                        <input type="text" name="name" id="category_name" class="form-control" required>
                    </div>
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                </form>
                <div class="table-responsive mt-3">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td>
                                        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#editCategoryModal<?php echo $category['id']; ?>">Edit</button>
                                        <a href="?delete_category=<?php echo $category['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</a>
                                    </td>
                                </tr>
                                <!-- Edit Category Modal -->
                                <div class="modal fade" id="editCategoryModal<?php echo $category['id']; ?>" tabindex="-1" aria-labelledby="editCategoryModalLabel<?php echo $category['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editCategoryModalLabel<?php echo $category['id']; ?>">Edit Category</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST">
                                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                    <div class="mb-3">
                                                        <label for="category_name_<?php echo $category['id']; ?>" class="form-label">Category Name</label>
                                                        <input type="text" name="name" id="category_name_<?php echo $category['id']; ?>" value="<?php echo htmlspecialchars($category['name']); ?>" class="form-control" required>
                                                    </div>
                                                    <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Manage Equipment -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Manage Equipment</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="equipment_name" class="form-label">Equipment Name</label>
                            <input type="text" name="name" id="equipment_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select name="category_id" id="category_id" class="form-select" required>
                                <option value="" disabled selected>Choose Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" id="description" class="form-control"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" name="quantity" id="quantity" min="0" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="image" class="form-label">Image (JPG, PNG)</label>
                            <input type="file" name="image" id="image" class="form-control" accept="image/jpeg,image/png">
                        </div>
                    </div>
                    <button type="submit" name="add_equipment" class="btn btn-primary">Add Equipment</button>
                </form>
                <div class="table-responsive mt-3">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Image</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipment as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['description'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>
                                        <?php if ($item['image']): ?>
                                            <img src="Uploads/<?php echo htmlspecialchars($item['image']); ?>" class="equipment-img">
                                        <?php else: ?>
                                            No Image
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#editEquipmentModal<?php echo $item['id']; ?>">Edit</button>
                                        <a href="?delete_equipment=<?php echo $item['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</a>
                                    </td>
                                </tr>
                                <!-- Edit Equipment Modal -->
                                <div class="modal fade" id="editEquipmentModal<?php echo $item['id']; ?>" tabindex="-1" aria-labelledby="editEquipmentModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editEquipmentModalLabel<?php echo $item['id']; ?>">Edit Equipment</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label for="equipment_name_<?php echo $item['id']; ?>" class="form-label">Equipment Name</label>
                                                            <input type="text" name="name" id="equipment_name_<?php echo $item['id']; ?>" value="<?php echo htmlspecialchars($item['name']); ?>" class="form-control" required>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label for="category_id_<?php echo $item['id']; ?>" class="form-label">Category</label>
                                                            <select name="category_id" id="category_id_<?php echo $item['id']; ?>" class="form-select" required>
                                                                <?php foreach ($categories as $category): ?>
                                                                    <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $item['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name']); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="description_<?php echo $item['id']; ?>" class="form-label">Description</label>
                                                        <textarea name="description" id="description_<?php echo $item['id']; ?>" class="form-control"><?php echo htmlspecialchars($item['description']); ?></textarea>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label for="quantity_<?php echo $item['id']; ?>" class="form-label">Quantity</label>
                                                            <input type="number" name="quantity" id="quantity_<?php echo $item['id']; ?>" value="<?php echo $item['quantity']; ?>" min="0" class="form-control" required>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label for="image_<?php echo $item['id']; ?>" class="form-label">Image (JPG, PNG)</label>
                                                            <input type="file" name="image" id="image_<?php echo $item['id']; ?>" class="form-control" accept="image/jpeg,image/png">
                                                            <?php if ($item['image']): ?>
                                                                <small>Current: <img src="Uploads/<?php echo htmlspecialchars($item['image']); ?>" class="equipment-img"></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <button type="submit" name="update_equipment" class="btn btn-primary">Update Equipment</button>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Manage Admins -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Manage Admins</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="admin_username" class="form-label">Username</label>
                            <input type="text" name="username" id="admin_username" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="admin_password" class="form-label">Password</label>
                            <input type="password" name="password" id="admin_password" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" name="add_admin" class="btn btn-primary">Add Admin</button>
                </form>
                <div class="table-responsive mt-3">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                    <td>
                                        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#editAdminModal<?php echo $admin['id']; ?>">Edit</button>
                                        <a href="?delete_admin=<?php echo $admin['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</a>
                                    </td>
                                </tr>
                                <!-- Edit Admin Modal -->
                                <div class="modal fade" id="editAdminModal<?php echo $admin['id']; ?>" tabindex="-1" aria-labelledby="editAdminModalLabel<?php echo $admin['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editAdminModalLabel<?php echo $admin['id']; ?>">Edit Admin</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST">
                                                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                    <div class="mb-3">
                                                        <label for="admin_username_<?php echo $admin['id']; ?>" class="form-label">Username</label>
                                                        <input type="text" name="username" id="admin_username_<?php echo $admin['id']; ?>" value="<?php echo htmlspecialchars($admin['username']); ?>" class="form-control" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="admin_password_<?php echo $admin['id']; ?>" class="form-label">New Password (optional)</label>
                                                        <input type="password" name="password" id="admin_password_<?php echo $admin['id']; ?>" class="form-control">
                                                    </div>
                                                    <button type="submit" name="update_admin" class="btn btn-primary">Update Admin</button>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Borrowing Calendar -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Borrowing Calendar</h5>
            </div>
            <div class="card-body">
                <div id="calendar"></div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if(!empty($swal_msg)): ?>
    <script>
        <?php echo $swal_msg; ?>
    </script>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            if (calendarEl) {
                var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                events: 'fetch_borrowings.php',
                eventBackgroundColor: '#722ff9',
                eventBorderColor: '#B8A2F9',
                eventClick: function(info) {
                    const htmlContent = `
                        <div style="text-align: center;">
                            <p><strong>Equipment:</strong> ${info.event.title.replace('Equipment: ', '')}</p>
                            <p><strong>Borrowed on:</strong> ${info.event.start.toISOString().split('T')[0]}</p>
                            <p><strong>Borrower:</strong> ${info.event.extendedProps.username}</p>
                            <img src="Uploads/${info.event.extendedProps.image}" style="max-width: 250px; height: auto; margin-top: 10px;" alt="Equipment Image">
                        </div>
                    `;
                    Swal.fire({
                        title: 'Borrowing Details',
                        html: htmlContent,
                        icon: 'info',
                        confirmButtonColor: '#722ff9',
                        background: '#EDE9FE',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    });
                }
            });
            calendar.render();
            }
        });
    </script>
</body>
</html>