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

<?php if (empty($_SERVER["HTTP_X_REQUESTED_WITH"])) { header("Location: spa_shell.php?page=calendar"); exit; } ?>
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
                <a class="nav-link active" href="categorie.php"><i class="bi bi-tags"></i> Categories</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="equipment.php"><i class="bi bi-tools"></i> Equipment</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="admins.php"><i class="bi bi-people-fill"></i> Admins</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="calendar.php"><i class="bi bi-calendar-date"></i> Calendar</a>
            </li>


            <li class="nav-item">
                <a class="nav-link" href="borrowing_dashboard.php"><i class="bi bi-bar-chart"></i> Borrowing Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </li>
        </ul>
    </div>
    <div class="container">
        <div class="row">
            <div class="col-md-1">
            </div>
            <div class="col-md-10">
                <!-- Content -->
    <div class="content">
        <h2 class="mb-4">Calendar</h2>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>

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

            </div>
            <div class="col-md-1">
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