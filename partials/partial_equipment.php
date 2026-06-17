<?php
// partial_equipment.php — SPA partial (content only)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        http_response_code(403); echo '<div class="alert alert-danger m-3">ไม่มีสิทธิ์เข้าถึง</div>';
    } else { header('Location: ../login.php'); }
    exit;
}

$swal_msg = '';
$upload_dir = dirname(__DIR__) . '/Uploads/';

// ── Auto-create equipment_serials table if not exists ─────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `equipment_serials` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `equipment_id` int(11) NOT NULL,
        `serial_code` varchar(100) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `serial_code` (`serial_code`),
        KEY `equipment_id` (`equipment_id`),
        CONSTRAINT `equipment_serials_ibfk_1`
            FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Add Equipment (with serials) ──────────────────────────────────────────────
if (isset($_POST['add_equipment'])) {
    $name        = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $quantity    = (int)$_POST['quantity'];
    $image_name  = $_FILES['image']['name'] ?? '';
    $serials     = $_POST['serial_codes'] ?? [];

    $err = '';

    if (empty($name) || $quantity < 1) {
        $err = 'กรุณากรอกชื่อครุภัณฑ์และจำนวนที่ถูกต้อง (ต้องมากกว่า 0)';
    } elseif ($image_name && !in_array(strtolower(pathinfo($image_name, PATHINFO_EXTENSION)), ['jpg','jpeg','png'])) {
        $err = 'รองรับเฉพาะ JPG, PNG เท่านั้น';
    } elseif (count($serials) !== $quantity) {
        $err = 'จำนวนรหัสครุภัณฑ์ไม่ตรงกับจำนวนเครื่อง';
    } else {
        $clean_serials = array_map('trim', $serials);
        if (in_array('', $clean_serials, true)) {
            $err = 'กรุณากรอกรหัสครุภัณฑ์ให้ครบทุกช่อง';
        }
        if (!$err && count($clean_serials) !== count(array_unique($clean_serials))) {
            $err = 'มีรหัสครุภัณฑ์ซ้ำกันในรายการที่กรอก';
        }
        if (!$err) {
            $placeholders = implode(',', array_fill(0, count($clean_serials), '?'));
            $dup_stmt = $pdo->prepare("SELECT serial_code FROM equipment_serials WHERE serial_code IN ($placeholders)");
            $dup_stmt->execute($clean_serials);
            $dup_found = $dup_stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($dup_found)) {
                $err = 'รหัสครุภัณฑ์ซ้ำกับที่มีในระบบแล้ว: ' . implode(', ', $dup_found);
            }
        }
    }

    if ($err) {
        $swal_msg = "Swal.fire('ผิดพลาด', '" . addslashes($err) . "', 'error');";
    } else {
        $image = null;
        if ($image_name) {
            $image = basename($image_name);
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image);
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO equipment (name, description, category_id, quantity, image) VALUES (?,?,?,?,?)")
                ->execute([$name, $description, $category_id, $quantity, $image]);
            $equip_id = $pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO equipment_serials (equipment_id, serial_code) VALUES (?,?)");
            foreach ($clean_serials as $sc) { $ins->execute([$equip_id, $sc]); }
            $pdo->commit();
            $swal_msg = "Swal.fire('สำเร็จ', 'เพิ่มครุภัณฑ์ $quantity รายการเรียบร้อย', 'success');";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $swal_msg = "Swal.fire('ผิดพลาด', '" . addslashes($e->getMessage()) . "', 'error');";
        }
    }
}

// ── Update Equipment ──────────────────────────────────────────────────────────
if (isset($_POST['update_equipment'])) {
    $id          = (int)$_POST['equipment_id'];
    $name        = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $quantity    = (int)$_POST['quantity'];
    $image_name  = $_FILES['image']['name'] ?? '';
    if (empty($name) || $quantity < 0) {
        $swal_msg = "Swal.fire('ผิดพลาด', 'กรุณากรอกชื่อครุภัณฑ์และจำนวนที่ถูกต้อง', 'error');";
    } else {
        if ($image_name) {
            $image = basename($image_name);
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image);
        } else {
            $s = $pdo->prepare("SELECT image FROM equipment WHERE id=?");
            $s->execute([$id]);
            $image = $s->fetchColumn();
        }
        $pdo->prepare("UPDATE equipment SET name=?, description=?, category_id=?, quantity=?, image=? WHERE id=?")
            ->execute([$name, $description, $category_id, $quantity, $image, $id]);
        $swal_msg = "Swal.fire('สำเร็จ', 'อัปเดตครุภัณฑ์เรียบร้อย', 'success');";
    }
}

// ── Delete Equipment ──────────────────────────────────────────────────────────
if (isset($_GET['delete_equipment'])) {
    $id = (int)$_GET['delete_equipment'];
    // ตรวจสอบว่ามีการยืมที่ยังไม่คืนจริงๆ (status ที่ไม่ใช่ returned)
    $activeCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM borrowings WHERE equipment_id=? AND status NOT IN ('returned')"
    );
    $activeCheck->execute([$id]);
    $activeCount = (int)$activeCheck->fetchColumn();

    if ($activeCount > 0) {
        $swal_msg = "Swal.fire('ไม่สามารถลบได้', 'ครุภัณฑ์นี้ยังมีรายการยืมที่ค้างอยู่ $activeCount รายการ กรุณาตรวจสอบให้ครบก่อน', 'warning');";
    } else {
        // ลบข้อมูลที่เกี่ยวข้องก่อน เพื่อหลีกเลี่ยง FK constraint
        // (borrowings ที่ returned ทั้งหมดแล้วปลอดภัยที่จะลบประวัติ)
        $pdo->beginTransaction();
        try {
            // 1. ลบ borrow_serials ที่เชื่อมกับ borrowings ของ equipment นี้
            $pdo->prepare("
                DELETE bs FROM borrow_serials bs
                INNER JOIN borrowings b ON bs.borrowing_id = b.id
                WHERE b.equipment_id = ?
            ")->execute([$id]);

            // 2. ลบ borrowings ที่ returned ทั้งหมดของ equipment นี้
            $pdo->prepare("DELETE FROM borrowings WHERE equipment_id=?")->execute([$id]);

            // 3. ลบ equipment_serials
            $pdo->prepare("DELETE FROM equipment_serials WHERE equipment_id=?")->execute([$id]);

            // 4. ลบ equipment
            $pdo->prepare("DELETE FROM equipment WHERE id=?")->execute([$id]);

            $pdo->commit();
            $swal_msg = "Swal.fire('สำเร็จ', 'ลบครุภัณฑ์เรียบร้อย', 'success');";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $swal_msg = "Swal.fire('ผิดพลาด', 'ไม่สามารถลบครุภัณฑ์ได้: " . addslashes($e->getMessage()) . "', 'error');";
        }
    }
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$equipment  = $pdo->query("SELECT e.*, c.name AS category_name FROM equipment e JOIN categories c ON e.category_id = c.id ORDER BY e.id DESC")->fetchAll();
$all_serials = $pdo->query("SELECT serial_code FROM equipment_serials")->fetchAll(PDO::FETCH_COLUMN);

$serials_by_equip = [];
foreach ($pdo->query("SELECT equipment_id, serial_code FROM equipment_serials ORDER BY id")->fetchAll() as $row) {
    $serials_by_equip[$row['equipment_id']][] = $row['serial_code'];
}
?>

<div class="page-header">
    <h4><i class="bi bi-laptop me-2"></i>จัดการครุภัณฑ์</h4>
    <p>เพิ่ม แก้ไข หรือลบรายการครุภัณฑ์</p>
</div>

<!-- ═══ Add Equipment Form ════════════════════════════════════════════════════ -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-plus-circle"></i> เพิ่มครุภัณฑ์ใหม่</div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="addEquipForm">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">ชื่อครุภัณฑ์ <span style="color:#dc2626">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="กรอกชื่อครุภัณฑ์" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">หมวดหมู่ <span style="color:#dc2626">*</span></label>
                    <select name="category_id" class="form-select" required>
                        <option value="" disabled selected>เลือกหมวดหมู่</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">แสดงรหัสครุภัณฑ์</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="คำอธิบายครุภัณฑ์ (ไม่บังคับ)"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">จำนวน <span style="color:#dc2626">*</span></label>
                    <input type="number" name="quantity" id="addQtyInput" class="form-control" min="1" placeholder="ระบุจำนวนเครื่อง" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">รูปภาพ (JPG, PNG)</label>
                    <input type="file" name="image" class="form-control" accept="image/jpeg,image/png">
                </div>

                <!-- ──── Serial Code Section ──────────────────────────────── -->
                <div class="col-12" id="serialSection" style="display:none;">
                    <div class="p-3 rounded" style="background:#f5f3ff;border:1px solid #ddd6fe;">
                        <label class="form-label fw-semibold mb-2">
                            <i class="bi bi-upc-scan me-1" style="color:#722ff9"></i>
                            รหัสครุภัณฑ์รายเครื่อง <span style="color:#dc2626">*</span>
                            <small class="text-muted fw-normal ms-1">(กรอกให้ครบทุกช่อง ห้ามซ้ำ)</small>
                        </label>
                        <div id="serialInputs" class="row g-2"></div>
                        <div id="serialError" class="text-danger small mt-2 fw-semibold" style="display:none;"></div>
                    </div>
                </div>
                <!-- ─────────────────────────────────────────────────────── -->

                <div class="col-12">
                    <button type="submit" name="add_equipment" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> เพิ่มครุภัณฑ์
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Equipment List ════════════════════════════════════════════════════════ -->
<div class="card">
    <div class="card-header"><i class="bi bi-list-ul"></i> รายการครุภัณฑ์ทั้งหมด</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">ชื่อครุภัณฑ์</th>
                        <th>คำอธิบาย</th>
                        <th>หมวดหมู่</th>
                        <th>จำนวน</th>
                        <th>รหัสครุภัณฑ์</th>
                        <th>รูปภาพ</th>
                        <th>การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipment as $item): ?>
                    <?php $item_serials = $serials_by_equip[$item['id']] ?? []; ?>
                    <tr>
                        <td class="ps-3"><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($item['description'] ?: '-'); ?></td>
                        <td><span class="badge rounded-pill" style="background:#ede9fe;color:#6d28d9"><?php echo htmlspecialchars($item['category_name']); ?></span></td>
                        <td>
                            <span class="badge rounded-pill" style="background:<?php echo $item['quantity'] > 5 ? '#d1fae5;color:#059669' : ($item['quantity'] > 0 ? '#fef3c7;color:#d97706' : '#fee2e2;color:#dc2626'); ?>">
                                <?php echo $item['quantity']; ?> ชิ้น
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($item_serials)): ?>
                                <button class="btn btn-sm" style="background:#ede9fe;color:#6d28d9;border:1px solid #ddd6fe;"
                                        onclick="showSerials(<?php echo $item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['name'])); ?>')">
                                    <i class="bi bi-upc-scan me-1"></i><?php echo count($item_serials); ?> รหัส
                                </button>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($item['image']): ?>
                            <img src="Uploads/<?php echo htmlspecialchars($item['image']); ?>" class="equipment-img" alt="">
                            <?php else: ?><span class="text-muted">ไม่มีรูป</span><?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#editEquip<?php echo $item['id']; ?>">
                                <i class="bi bi-pencil"></i> แก้ไข
                            </button>
                            <a href="?page=equipment&delete_equipment=<?php echo $item['id']; ?>"
                               class="btn btn-danger btn-sm spa-confirm-delete"
                               data-confirm="ยืนยันการลบครุภัณฑ์นี้?">
                                <i class="bi bi-trash"></i> ลบ
                            </a>
                        </td>
                    </tr>

                    <!-- ── Edit Modal ── -->
                    <div class="modal fade" id="editEquip<?php echo $item['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>แก้ไขครุภัณฑ์</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="modal-body">
                                        <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">ชื่อครุภัณฑ์</label>
                                                <input type="text" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" class="form-control" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">หมวดหมู่</label>
                                                <select name="category_id" class="form-select" required>
                                                    <?php foreach ($categories as $cat): ?>
                                                    <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $item['category_id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cat['name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">คำอธิบาย</label>
                                                <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($item['description']); ?></textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">จำนวน</label>
                                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="0" class="form-control" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">รูปภาพใหม่ (เว้นว่างถ้าไม่เปลี่ยน)</label>
                                                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png">
                                                <?php if ($item['image']): ?>
                                                <small class="text-muted">ปัจจุบัน: <img src="Uploads/<?php echo htmlspecialchars($item['image']); ?>" class="equipment-img"></small>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($item_serials)): ?>
                                            <div class="col-12">
                                                <label class="form-label fw-semibold"><i class="bi bi-upc-scan me-1" style="color:#722ff9"></i>รหัสครุภัณฑ์ที่มีอยู่</label>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php foreach ($item_serials as $sc): ?>
                                                    <span class="badge rounded-pill" style="background:#ede9fe;color:#6d28d9;font-size:.85rem;padding:.35em .75em;">
                                                        <i class="bi bi-upc me-1"></i><?php echo htmlspecialchars($sc); ?>
                                                    </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                        <button type="submit" name="update_equipment" class="btn btn-primary">บันทึก</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($equipment)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">ยังไม่มีครุภัณฑ์</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Modal แสดงรหัสครุภัณฑ์ ─────────────────────────────────────────────── -->
<div class="modal fade" id="serialListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-upc-scan me-2" style="color:#722ff9"></i><span id="serialModalTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="serialModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<script>
const EXISTING_SERIALS  = <?php echo json_encode(array_values($all_serials), JSON_UNESCAPED_UNICODE); ?>;
const SERIALS_BY_EQUIP  = <?php echo json_encode($serials_by_equip, JSON_UNESCAPED_UNICODE); ?>;
</script>

<?php if (!empty($swal_msg)): ?>
<script>document.addEventListener('DOMContentLoaded',function(){<?php echo $swal_msg; ?>});</script>
<?php endif; ?>

<script>
(function () {
    /* ── Delete confirm ── */
    document.querySelectorAll('.spa-confirm-delete').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var href = this.href, msg = this.dataset.confirm || 'ยืนยัน?';
            Swal.fire({ title: msg, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', cancelButtonText: 'ยกเลิก', confirmButtonText: 'ลบ' })
                .then(function (r) { if (r.isConfirmed) window.location.href = href; });
        });
    });

    /* ── Serial Code Dynamic Inputs ── */
    var qtyInput      = document.getElementById('addQtyInput');
    var serialSection = document.getElementById('serialSection');
    var serialInputs  = document.getElementById('serialInputs');
    var serialError   = document.getElementById('serialError');

    function buildSerialInputs(qty) {
        // Keep existing values when reducing count
        var oldVals = Array.from(serialInputs.querySelectorAll('.serial-input')).map(function(i){ return i.value; });
        serialInputs.innerHTML = '';
        if (qty < 1) { serialSection.style.display = 'none'; return; }
        serialSection.style.display = 'block';
        for (var i = 0; i < qty; i++) {
            var col = document.createElement('div');
            col.className = 'col-md-4 col-sm-6';
            col.innerHTML =
                '<div class="input-group input-group-sm">' +
                  '<span class="input-group-text" style="background:#ede9fe;color:#6d28d9;min-width:84px;font-size:.78rem;">เครื่องที่ ' + (i + 1) + '</span>' +
                  '<input type="text" name="serial_codes[]" class="form-control serial-input"' +
                         ' placeholder="รหัสครุภัณฑ์" autocomplete="off"' +
                         ' value="' + (oldVals[i] ? oldVals[i].replace(/"/g,'&quot;') : '') + '">' +
                '</div>';
            serialInputs.appendChild(col);
        }
        serialInputs.querySelectorAll('.serial-input').forEach(function (inp) {
            inp.addEventListener('input', validateSerials);
            inp.addEventListener('blur',  validateSerials);
        });
    }

    function validateSerials() {
        var inputs  = Array.from(serialInputs.querySelectorAll('.serial-input'));
        var values  = inputs.map(function (i) { return i.value.trim(); });
        var errors  = [];
        inputs.forEach(function (i) { i.classList.remove('is-invalid', 'is-valid'); });

        values.forEach(function (v, idx) {
            if (!v) { inputs[idx].classList.add('is-invalid'); return; }
            var firstIdx = values.indexOf(v);
            if (firstIdx !== idx) {
                inputs[idx].classList.add('is-invalid');
                inputs[firstIdx].classList.add('is-invalid');
                var msg = 'รหัสซ้ำกันในรายการ: ' + v;
                if (errors.indexOf(msg) === -1) errors.push(msg);
                return;
            }
            if (EXISTING_SERIALS.indexOf(v) !== -1) {
                inputs[idx].classList.add('is-invalid');
                errors.push('รหัส "' + v + '" มีในระบบแล้ว');
                return;
            }
            inputs[idx].classList.add('is-valid');
        });

        if (errors.length) {
            serialError.textContent = errors.join(' | ');
            serialError.style.display = 'block';
        } else {
            serialError.textContent = '';
            serialError.style.display = 'none';
        }
        return errors.length === 0;
    }

    qtyInput.addEventListener('input', function () {
        var qty = parseInt(this.value, 10);
        buildSerialInputs(isNaN(qty) || qty < 1 ? 0 : qty);
    });

    document.getElementById('addEquipForm').addEventListener('submit', function (e) {
        var qty = parseInt(qtyInput.value, 10);
        if (qty > 0) {
            if (!validateSerials()) {
                e.preventDefault();
                serialError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                Swal.fire('ผิดพลาด', 'กรุณาตรวจสอบรหัสครุภัณฑ์ให้ถูกต้องก่อนบันทึก', 'warning');
            }
        }
    });

    /* ── Show serials modal ── */
    window.showSerials = function (equipId, name) {
        var serials = SERIALS_BY_EQUIP[equipId] || [];
        document.getElementById('serialModalTitle').textContent = name;
        var html = '<p class="text-muted small mb-2">รหัสครุภัณฑ์ทั้งหมด ' + serials.length + ' รายการ</p>' +
                   '<ol class="list-group list-group-numbered">';
        serials.forEach(function (s) {
            html += '<li class="list-group-item">' +
                    '<i class="bi bi-upc me-2" style="color:#722ff9"></i>' + s + '</li>';
        });
        html += '</ol>';
        document.getElementById('serialModalBody').innerHTML = html;
        new bootstrap.Modal(document.getElementById('serialListModal')).show();
    };
})();
</script>
