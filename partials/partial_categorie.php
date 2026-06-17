<?php
// partial_categorie.php — SPA partial (content only)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        http_response_code(403); echo '<div class="alert alert-danger m-3">ไม่มีสิทธิ์เข้าถึง</div>';
    } else { header('Location: ../login.php'); }
    exit;
}

$swal_msg = '';

if (isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    if (!empty($name)) {
        $pdo->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$name]);
        $swal_msg = "Swal.fire('สำเร็จ', 'เพิ่มหมวดหมู่เรียบร้อย', 'success');";
    } else { $swal_msg = "Swal.fire('ผิดพลาด', 'กรุณากรอกชื่อหมวดหมู่', 'error');"; }
}

if (isset($_POST['update_category'])) {
    $id = (int)$_POST['category_id']; $name = trim($_POST['name']);
    if (!empty($name)) {
        $pdo->prepare("UPDATE categories SET name=? WHERE id=?")->execute([$name, $id]);
        $swal_msg = "Swal.fire('สำเร็จ', 'อัปเดตหมวดหมู่เรียบร้อย', 'success');";
    } else { $swal_msg = "Swal.fire('ผิดพลาด', 'กรุณากรอกชื่อหมวดหมู่', 'error');"; }
}

if (isset($_GET['delete_category'])) {
    $id = (int)$_GET['delete_category'];
    try {
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        $swal_msg = "Swal.fire('สำเร็จ', 'ลบหมวดหมู่เรียบร้อย', 'success');";
    } catch (PDOException $e) { $swal_msg = "Swal.fire('ผิดพลาด', 'ไม่สามารถลบหมวดหมู่ที่มีครุภัณฑ์อยู่', 'error');"; }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll();
?>

<div class="page-header">
    <h4><i class="bi bi-tags me-2"></i>จัดการหมวดหมู่</h4>
    <p>เพิ่ม แก้ไข หรือลบหมวดหมู่ครุภัณฑ์</p>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-plus-circle"></i> เพิ่มหมวดหมู่ใหม่</div>
    <div class="card-body">
        <form method="POST">
            <div class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">ชื่อหมวดหมู่ <span style="color:#dc2626">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="กรอกชื่อหมวดหมู่" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="add_category" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-1"></i> เพิ่มหมวดหมู่
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-list-ul"></i> รายการหมวดหมู่ทั้งหมด</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">#</th>
                        <th>ชื่อหมวดหมู่</th>
                        <th>การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td class="ps-4"><?php echo $cat['id']; ?></td>
                        <td><i class="bi bi-tag text-primary me-2"></i><?php echo htmlspecialchars($cat['name']); ?></td>
                        <td>
                            <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#editCat<?php echo $cat['id']; ?>">
                                <i class="bi bi-pencil"></i> แก้ไข
                            </button>
                            <a href="?page=categorie&delete_category=<?php echo $cat['id']; ?>"
                               class="btn btn-danger btn-sm spa-confirm-delete"
                               data-confirm="ยืนยันการลบหมวดหมู่นี้?">
                                <i class="bi bi-trash"></i> ลบ
                            </a>
                        </td>
                    </tr>
                    <!-- Edit Modal -->
                    <div class="modal fade" id="editCat<?php echo $cat['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>แก้ไขหมวดหมู่</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                        <label class="form-label">ชื่อหมวดหมู่</label>
                                        <input type="text" name="name" value="<?php echo htmlspecialchars($cat['name']); ?>" class="form-control" required>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                        <button type="submit" name="update_category" class="btn btn-primary">บันทึก</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                    <tr><td colspan="3" class="text-center py-4 text-muted">ยังไม่มีหมวดหมู่</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($swal_msg)): ?>
<script>document.addEventListener('DOMContentLoaded',function(){<?php echo $swal_msg; ?>});</script>
<?php endif; ?>
<script>
(function(){
    document.querySelectorAll('.spa-confirm-delete').forEach(function(btn){
        btn.addEventListener('click',function(e){
            e.preventDefault();
            var href=this.href, msg=this.dataset.confirm||'ยืนยัน?';
            Swal.fire({title:msg,icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',cancelButtonText:'ยกเลิก',confirmButtonText:'ลบ'})
                .then(function(r){if(r.isConfirmed) window.location.href=href;});
        });
    });
})();
</script>
