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

// Add equipment (with serial codes)
if (isset($_POST['add_equipment'])) {
    $name        = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $quantity    = (int)$_POST['quantity'];
    $image       = $_FILES['image']['name'];
    $serials     = $_POST['serial_codes'] ?? [];

    $err = '';
    if (empty($name) || $quantity < 1) {
        $err = 'Equipment name and valid quantity (>=1) are required';
    } elseif ($image && !in_array(strtolower(pathinfo($image, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'])) {
        $err = 'Only JPG, JPEG, PNG files are allowed';
    } elseif (count($serials) !== $quantity) {
        $err = 'Number of serial codes must match quantity';
    } else {
        $clean = array_map('trim', $serials);
        if (in_array('', $clean, true)) { $err = 'All serial code fields are required'; }
        if (!$err && count($clean) !== count(array_unique($clean))) { $err = 'Duplicate serial codes in the list'; }
        if (!$err) {
            $ph = implode(',', array_fill(0, count($clean), '?'));
            $dup = $pdo->prepare("SELECT serial_code FROM equipment_serials WHERE serial_code IN ($ph)");
            $dup->execute($clean);
            $found = $dup->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($found)) { $err = 'Serial codes already exist: ' . implode(', ', $found); }
        }
    }

    if ($err) {
        $swal_msg = "Swal.fire('Error', '" . addslashes($err) . "', 'error');";
    } else {
        if ($image) {
            $target = 'Uploads/' . basename($image);
            move_uploaded_file($_FILES['image']['tmp_name'], $target);
        } else { $image = null; }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO equipment (name, description, category_id, quantity, image) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $category_id, $quantity, $image]);
            $equip_id = $pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO equipment_serials (equipment_id, serial_code) VALUES (?,?)");
            foreach ($clean as $sc) { $ins->execute([$equip_id, $sc]); }
            $pdo->commit();
            $swal_msg = "Swal.fire('Success', 'Equipment added successfully ($quantity items)', 'success');";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $swal_msg = "Swal.fire('Error', '" . addslashes($e->getMessage()) . "', 'error');";
        }
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

<?php if (empty($_SERVER["HTTP_X_REQUESTED_WITH"])) { header("Location: spa_shell.php?page=equipment"); exit; } ?>
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

    <!-- Content -->
    <div class="content">
        <h2 class="mb-4">Equipment</h2>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>

        

        <!-- Manage Equipment -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Manage Equipment</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="addEquipFormStd">
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
                            <input type="number" name="quantity" id="std_qty" min="1" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="image" class="form-label">Image (JPG, PNG)</label>
                            <input type="file" name="image" id="image" class="form-control" accept="image/jpeg,image/png">
                        </div>
                    </div>
                    <!-- Serial Code Section -->
                    <div id="stdSerialSection" style="display:none;" class="mb-3">
                        <hr>
                        <label class="form-label fw-semibold">Serial Codes (one per unit) <span style="color:#dc2626">*</span></label>
                        <div id="stdSerialInputs" class="row g-2"></div>
                        <div id="stdSerialError" class="text-danger small mt-1" style="display:none;"></div>
                    </div>
                    <button type="submit" name="add_equipment" class="btn btn-primary">Add Equipment</button>
                </form>
                <?php
                $all_serials_std = $pdo->query("SELECT serial_code FROM equipment_serials")->fetchAll(PDO::FETCH_COLUMN);
                ?>
                <script>
                const STD_EXISTING_SERIALS = <?php echo json_encode($all_serials_std); ?>;
                (function(){
                    var qi=document.getElementById('std_qty');
                    var sec=document.getElementById('stdSerialSection');
                    var inp=document.getElementById('stdSerialInputs');
                    var err=document.getElementById('stdSerialError');
                    function build(qty){
                        inp.innerHTML='';
                        if(qty<1){sec.style.display='none';return;}
                        sec.style.display='block';
                        for(var i=1;i<=qty;i++){
                            var d=document.createElement('div');
                            d.className='col-md-4 col-sm-6 mb-2';
                            d.innerHTML='<div class="input-group input-group-sm"><span class="input-group-text" style="min-width:80px">Unit '+i+'</span><input type="text" name="serial_codes[]" class="form-control std-serial" placeholder="Serial code" required></div>';
                            inp.appendChild(d);
                        }
                        inp.querySelectorAll('.std-serial').forEach(function(x){x.addEventListener('input',validate);});
                    }
                    function validate(){
                        var inputs=Array.from(inp.querySelectorAll('.std-serial'));
                        var vals=inputs.map(function(i){return i.value.trim();});
                        var errs=[];
                        inputs.forEach(function(i){i.classList.remove('is-invalid','is-valid');});
                        vals.forEach(function(v,idx){
                            if(!v){inputs[idx].classList.add('is-invalid');return;}
                            if(vals.indexOf(v)!==idx){inputs[idx].classList.add('is-invalid');errs.push('Duplicate in list: '+v);return;}
                            if(STD_EXISTING_SERIALS.indexOf(v)!==-1){inputs[idx].classList.add('is-invalid');errs.push('Already exists: '+v);return;}
                            inputs[idx].classList.add('is-valid');
                        });
                        if(errs.length){err.textContent=[...new Set(errs)].join(' | ');err.style.display='block';}
                        else{err.textContent='';err.style.display='none';}
                        return errs.length===0;
                    }
                    qi.addEventListener('input',function(){var q=parseInt(this.value,10);build(isNaN(q)||q<1?0:q);});
                    document.getElementById('addEquipFormStd').addEventListener('submit',function(e){
                        var q=parseInt(qi.value,10);
                        if(q>0&&!validate()){e.preventDefault();alert('Please fix serial code errors before saving.');}
                    });
                })();
                </script>
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