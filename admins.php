<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: login.php'); exit; }

// Add admin
if (isset($_POST['add_admin'])) {
    $uname = trim($_POST['username']); $pass = trim($_POST['password']);
    if (empty($uname) || empty($pass)) { $swal_msg = "Swal.fire('ผิดพลาด','กรุณากรอก Username และ Password','error');"; }
    else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
            $stmt->execute([$uname, password_hash($pass, PASSWORD_BCRYPT)]);
            $swal_msg = "Swal.fire('สำเร็จ','เพิ่มแอดมินเรียบร้อย','success');";
        } catch (PDOException $e) { $swal_msg = "Swal.fire('ผิดพลาด','Username นี้มีอยู่แล้ว','error');"; }
    }
}
// Update admin
if (isset($_POST['update_admin'])) {
    $id = $_POST['admin_id']; $uname = trim($_POST['username']); $pass = trim($_POST['password']);
    if (empty($uname)) { $swal_msg = "Swal.fire('ผิดพลาด','กรุณากรอก Username','error');"; }
    else {
        try {
            if ($pass) { $stmt = $pdo->prepare("UPDATE users SET username=?, password=? WHERE id=? AND role='admin'"); $stmt->execute([$uname, password_hash($pass, PASSWORD_BCRYPT), $id]); }
            else       { $stmt = $pdo->prepare("UPDATE users SET username=? WHERE id=? AND role='admin'"); $stmt->execute([$uname, $id]); }
            $swal_msg = "Swal.fire('สำเร็จ','อัปเดตแอดมินเรียบร้อย','success');";
        } catch (PDOException $e) { $swal_msg = "Swal.fire('ผิดพลาด','Username นี้มีอยู่แล้ว','error');"; }
    }
}
// Delete admin
if (isset($_GET['delete_admin'])) {
    $id = $_GET['delete_admin'];
    if ($id != $_SESSION['user_id']) { $pdo->prepare("DELETE FROM users WHERE id=? AND role='admin'")->execute([$id]); $swal_msg = "Swal.fire('สำเร็จ','ลบแอดมินเรียบร้อย','success');"; }
    else { $swal_msg = "Swal.fire('ผิดพลาด','ไม่สามารถลบบัญชีของตัวเองได้','error');"; }
}

$admins = $pdo->query("SELECT * FROM users WHERE role='admin'")->fetchAll();
$current_page = 'admins';
?>
<?php if (empty($_SERVER["HTTP_X_REQUESTED_WITH"])) { header("Location: spa_shell.php?page=admins"); exit; } ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการแอดมิน - ระบบยืมครุภัณฑ์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="content">
    <div class="page-header">
        <h4><i class="bi bi-people-fill me-2"></i>จัดการแอดมิน</h4>
        <p>เพิ่ม แก้ไข หรือลบบัญชีผู้ดูแลระบบ</p>
    </div>

    <div class="card">
        <div class="card-header"><i class="bi bi-person-plus"></i> เพิ่มแอดมินใหม่</div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Username <span style="color:#dc2626">*</span></label>
                        <input type="text" name="username" id="admin_username" class="form-control" placeholder="กรอก Username" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Password <span style="color:#dc2626">*</span></label>
                        <input type="password" name="password" id="admin_password" class="form-control" placeholder="กรอก Password" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="add_admin" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle me-1"></i> เพิ่ม
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="bi bi-people"></i> รายชื่อแอดมิน</div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">#</th>
                        <th>Username</th>
                        <th>วันที่สร้าง</th>
                        <th>การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td class="ps-4"><?php echo $admin['id']; ?></td>
                        <td><i class="bi bi-shield-check text-success me-2"></i><?php echo htmlspecialchars($admin['username']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($admin['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#editAdmin<?php echo $admin['id']; ?>">
                                <i class="bi bi-pencil"></i> แก้ไข
                            </button>
                            <?php if ($admin['id'] != $_SESSION['user_id']): ?>
                            <a href="?delete_admin=<?php echo $admin['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('ยืนยันการลบแอดมินนี้?')">
                                <i class="bi bi-trash"></i> ลบ
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <!-- Edit Modal -->
                    <div class="modal fade" id="editAdmin<?php echo $admin['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>แก้ไขแอดมิน</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Password ใหม่ <small class="text-muted">(เว้นว่างถ้าไม่เปลี่ยน)</small></label>
                                            <input type="password" name="password" class="form-control">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                        <button type="submit" name="update_admin" class="btn btn-primary">บันทึก</button>
                                    </div>
                                </form>
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
<?php if (!empty($swal_msg)): ?><script><?php echo $swal_msg; ?></script><?php endif; ?>
</body>
</html>