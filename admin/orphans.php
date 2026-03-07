<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
checkAuth();

$pdo = getDB();

// ─── File upload helper ───────────────────────────────────────────────────────
function handleFileUpload(array $file, string $entityType, int $entityId, PDO $pdo): bool {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAX_FILE_SIZE) return false;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) return false;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'image/jpeg', 'image/png',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    if (!in_array($mime, $allowedMimes, true)) return false;
    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest    = UPLOAD_DIR . $newName;
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
    $stmt = $pdo->prepare('INSERT INTO attachments (entity_type, entity_id, file_name, file_path) VALUES (?,?,?,?)');
    $stmt->execute([$entityType, $entityId, htmlspecialchars(basename($file['name']), ENT_QUOTES, 'UTF-8'), $newName]);
    return true;
}

// ─── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'رمز الأمان غير صحيح'];
        header('Location: ' . BASE_PATH . '/admin/orphans.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $fileNumber   = strip_tags(trim($_POST['file_number']    ?? ''));
        $name         = strip_tags(trim($_POST['name']           ?? ''));
        $birthDate    = $_POST['birth_date'] ?? '';
        $gender       = $_POST['gender']     ?? 'ذكر';
        $motherName   = strip_tags(trim($_POST['mother_name']    ?? ''));
        $guardianName = strip_tags(trim($_POST['guardian_name']  ?? ''));
        $contact      = strip_tags(trim($_POST['contact']        ?? ''));
        $address      = strip_tags(trim($_POST['address']        ?? ''));
        $education    = $_POST['education']  ?? 'ابتدائي';
        $health       = strip_tags(trim($_POST['health']         ?? ''));
        $notes        = strip_tags(trim($_POST['notes']          ?? ''));

        $validGender    = ['ذكر', 'أنثى'];
        $validEducation = ['روضة', 'ابتدائي', 'متوسط', 'ثانوي', 'جامعي', 'لا يتعلم'];
        if (!in_array($gender, $validGender, true))       $gender    = 'ذكر';
        if (!in_array($education, $validEducation, true)) $education = 'ابتدائي';

        // Validate birth_date format
        $birthDateVal = null;
        if ($birthDate !== '') {
            $dt = DateTime::createFromFormat('Y-m-d', $birthDate);
            if ($dt && $dt->format('Y-m-d') === $birthDate) {
                $birthDateVal = $birthDate;
            }
        }

        if ($name === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'اسم اليتيم مطلوب'];
            header('Location: ' . BASE_PATH . '/admin/orphans.php');
            exit;
        }

        if ($action === 'add') {
            $stmt = $pdo->prepare(
                'INSERT INTO orphans (file_number,name,birth_date,gender,mother_name,guardian_name,contact,address,education,health,notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([$fileNumber,$name,$birthDateVal,$gender,$motherName,$guardianName,$contact,$address,$education,$health,$notes]);
            $newId = (int)$pdo->lastInsertId();
            if (!empty($_FILES['attachment']['name'])) {
                handleFileUpload($_FILES['attachment'], 'orphan', $newId, $pdo);
            }
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم إضافة اليتيم بنجاح'];
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare(
                'UPDATE orphans
                 SET file_number=?,name=?,birth_date=?,gender=?,mother_name=?,guardian_name=?,
                     contact=?,address=?,education=?,health=?,notes=?,updated_at=NOW()
                 WHERE id=?'
            );
            $stmt->execute([$fileNumber,$name,$birthDateVal,$gender,$motherName,$guardianName,$contact,$address,$education,$health,$notes,$id]);
            if (!empty($_FILES['attachment']['name'])) {
                handleFileUpload($_FILES['attachment'], 'orphan', $id, $pdo);
            }
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم تحديث بيانات اليتيم بنجاح'];
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM orphans WHERE id=?')->execute([$id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم حذف سجل اليتيم بنجاح'];
        }
    }

    header('Location: ' . BASE_PATH . '/admin/orphans.php');
    exit;
}

// ─── Search / List ────────────────────────────────────────────────────────────
$search = strip_tags(trim($_GET['search'] ?? ''));
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $pdo->prepare(
        'SELECT * FROM orphans
         WHERE name LIKE ? OR file_number LIKE ? OR guardian_name LIKE ? OR contact LIKE ?
         ORDER BY created_at DESC'
    );
    $stmt->execute([$like, $like, $like, $like]);
} else {
    $stmt = $pdo->query('SELECT * FROM orphans ORDER BY created_at DESC');
}
$orphans = $stmt->fetchAll();
$total   = count($orphans);

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1"><i class="bi bi-person-heart text-success me-2"></i>الأيتام</h4>
    <p class="text-muted small mb-0">إجمالي السجلات: <strong><?= $total ?></strong></p>
  </div>
  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
    <i class="bi bi-plus-circle me-1"></i> إضافة يتيم جديد
  </button>
</div>

<!-- Search -->
<form method="GET" class="mb-4 no-print">
  <div class="input-group" style="max-width:420px">
    <input type="text" name="search" class="form-control" placeholder="ابحث بالاسم أو رقم الملف أو الولي…"
           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
    <button class="btn btn-outline-success" type="submit"><i class="bi bi-search"></i></button>
    <?php if ($search): ?>
      <a href="<?= BASE_PATH ?>/admin/orphans.php" class="btn btn-outline-secondary">
        <i class="bi bi-x-lg"></i>
      </a>
    <?php endif; ?>
  </div>
</form>

<!-- Table -->
<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>رقم الملف</th>
            <th>الاسم</th>
            <th>تاريخ الميلاد</th>
            <th>الجنس</th>
            <th>اسم الولي</th>
            <th>المرحلة الدراسية</th>
            <th>تاريخ الإضافة</th>
            <th class="no-print">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orphans)): ?>
          <tr><td colspan="9" class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            <?= $search ? 'لا توجد نتائج للبحث' : 'لا توجد سجلات أيتام بعد' ?>
          </td></tr>
          <?php else: ?>
          <?php foreach ($orphans as $o): ?>
          <tr>
            <td class="text-muted small"><?= (int)$o['id'] ?></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($o['file_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
            <td class="fw-semibold"><?= htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($o['birth_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <span class="badge <?= $o['gender'] === 'ذكر' ? 'bg-primary' : 'bg-pink text-white' ?>"
                    style="<?= $o['gender'] !== 'ذكر' ? 'background:#d63384!important' : '' ?>">
                <?= htmlspecialchars($o['gender'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </td>
            <td><?= htmlspecialchars($o['guardian_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?php
              $eBadge = match($o['education']) {
                  'جامعي'   => 'primary',
                  'ثانوي'   => 'info',
                  'متوسط'   => 'success',
                  'ابتدائي' => 'warning text-dark',
                  'روضة'    => 'secondary',
                  default   => 'light text-dark',
              };
              ?>
              <span class="badge bg-<?= $eBadge ?>"><?= htmlspecialchars($o['education'], ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td class="text-muted small"><?= htmlspecialchars(substr($o['created_at'] ?? '', 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="no-print">
              <button class="btn btn-sm btn-outline-primary btn-edit me-1"
                      data-bs-toggle="modal" data-bs-target="#editModal"
                      data-record='<?= htmlspecialchars(json_encode($o, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
                      title="تعديل">
                <i class="bi bi-pencil-square"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger btn-delete"
                      data-bs-toggle="modal" data-bs-target="#deleteModal"
                      data-id="<?= (int)$o['id'] ?>"
                      data-name="<?= htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8') ?>"
                      title="حذف">
                <i class="bi bi-trash3"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══════════════ ADD MODAL ═══════════════ -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title" id="addModalLabel"><i class="bi bi-plus-circle me-2"></i>إضافة يتيم جديد</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">رقم الملف</label>
              <input type="text" name="file_number" class="form-control" placeholder="مثال: O-0001">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">اسم اليتيم <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required placeholder="الاسم الكامل">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">تاريخ الميلاد</label>
              <input type="date" name="birth_date" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">الجنس</label>
              <select name="gender" class="form-select">
                <option value="ذكر" selected>ذكر</option>
                <option value="أنثى">أنثى</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">المرحلة الدراسية</label>
              <select name="education" class="form-select">
                <option value="روضة">روضة</option>
                <option value="ابتدائي" selected>ابتدائي</option>
                <option value="متوسط">متوسط</option>
                <option value="ثانوي">ثانوي</option>
                <option value="جامعي">جامعي</option>
                <option value="لا يتعلم">لا يتعلم</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">اسم الأم</label>
              <input type="text" name="mother_name" class="form-control" placeholder="اسم الأم">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">اسم الولي</label>
              <input type="text" name="guardian_name" class="form-control" placeholder="اسم الولي أو الوصي">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">رقم التواصل</label>
              <input type="text" name="contact" class="form-control" placeholder="05XXXXXXXX">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">العنوان</label>
              <input type="text" name="address" class="form-control" placeholder="المدينة / الحي">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">الحالة الصحية</label>
              <input type="text" name="health" class="form-control" placeholder="وصف الحالة الصحية إن وجد">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">ملاحظات</label>
              <textarea name="notes" class="form-control" rows="3" placeholder="أي ملاحظات إضافية…"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">إرفاق مستند <small class="text-muted">(JPG, PNG, PDF, DOC – بحد أقصى 5MB)</small></label>
              <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i>حفظ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══════════════ EDIT MODAL ═══════════════ -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel"><i class="bi bi-pencil-square me-2"></i>تعديل بيانات اليتيم</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">رقم الملف</label>
              <input type="text" name="file_number" id="edit_file_number" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">اسم اليتيم <span class="text-danger">*</span></label>
              <input type="text" name="name" id="edit_name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">تاريخ الميلاد</label>
              <input type="date" name="birth_date" id="edit_birth_date" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">الجنس</label>
              <select name="gender" id="edit_gender" class="form-select">
                <option value="ذكر">ذكر</option>
                <option value="أنثى">أنثى</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">المرحلة الدراسية</label>
              <select name="education" id="edit_education" class="form-select">
                <option value="روضة">روضة</option>
                <option value="ابتدائي">ابتدائي</option>
                <option value="متوسط">متوسط</option>
                <option value="ثانوي">ثانوي</option>
                <option value="جامعي">جامعي</option>
                <option value="لا يتعلم">لا يتعلم</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">اسم الأم</label>
              <input type="text" name="mother_name" id="edit_mother_name" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">اسم الولي</label>
              <input type="text" name="guardian_name" id="edit_guardian_name" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">رقم التواصل</label>
              <input type="text" name="contact" id="edit_contact" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">العنوان</label>
              <input type="text" name="address" id="edit_address" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">الحالة الصحية</label>
              <input type="text" name="health" id="edit_health" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">ملاحظات</label>
              <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">إرفاق مستند جديد <small class="text-muted">(اختياري)</small></label>
              <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i>حفظ التعديلات</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══════════════ DELETE MODAL ═══════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="deleteModalLabel"><i class="bi bi-exclamation-triangle me-2"></i>تأكيد الحذف</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body text-center py-4">
          <i class="bi bi-trash3-fill text-danger fs-1 mb-3 d-block"></i>
          <p class="fs-6">هل أنت متأكد من حذف سجل: <strong id="deleteName"></strong>؟</p>
          <p class="text-muted small">لا يمكن التراجع عن هذا الإجراء.</p>
        </div>
        <div class="modal-footer justify-content-center">
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-danger px-4"><i class="bi bi-trash3 me-1"></i>حذف</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
