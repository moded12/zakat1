<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';
checkAuth();

$pdo = getDB();

// ─── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'رمز الأمان غير صحيح'];
        header('Location: ' . BASE_PATH . '/admin/families.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $fileNumber   = strip_tags(trim($_POST['file_number']   ?? ''));
        $headName     = strip_tags(trim($_POST['head_name']     ?? ''));
        $membersCount = max(1, (int)($_POST['members_count']    ?? 1));
        $phone        = strip_tags(trim($_POST['phone']         ?? ''));
        $address      = strip_tags(trim($_POST['address']       ?? ''));
        $workStatus   = $_POST['work_status'] ?? 'لا يعمل';
        $income       = max(0.0, (float)($_POST['income']       ?? 0));
        $needType     = $_POST['need_type']   ?? 'غذائية';
        $notes        = strip_tags(trim($_POST['notes']         ?? ''));

        $validWorkStatus = ['يعمل', 'لا يعمل', 'متوفى', 'متقاعد'];
        $validNeedType   = ['غذائية', 'مالية', 'علاجية', 'تعليمية', 'مختلطة'];
        if (!in_array($workStatus, $validWorkStatus, true)) $workStatus = 'لا يعمل';
        if (!in_array($needType, $validNeedType, true))     $needType   = 'غذائية';

        if ($headName === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'اسم رب الأسرة مطلوب'];
            header('Location: ' . BASE_PATH . '/admin/families.php');
            exit;
        }

        if ($action === 'add') {
            $stmt = $pdo->prepare(
                'INSERT INTO poor_families (file_number,head_name,members_count,phone,address,work_status,income,need_type,notes)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([$fileNumber,$headName,$membersCount,$phone,$address,$workStatus,$income,$needType,$notes]);
            $newId = (int)$pdo->lastInsertId();
            $uploadErr = '';
            if (!empty($_FILES['attachment']['name']) && handleFileUpload($_FILES['attachment'], 'family', $newId, $pdo, $uploadErr) === false && $uploadErr !== '') {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم إضافة الأسرة بنجاح. تنبيه: ' . $uploadErr];
            } else {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم إضافة الأسرة بنجاح'];
            }
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare(
                'UPDATE poor_families
                 SET file_number=?,head_name=?,members_count=?,phone=?,address=?,
                     work_status=?,income=?,need_type=?,notes=?,updated_at=NOW()
                 WHERE id=?'
            );
            $stmt->execute([$fileNumber,$headName,$membersCount,$phone,$address,$workStatus,$income,$needType,$notes,$id]);
            $uploadErr = '';
            if (!empty($_FILES['attachment']['name']) && handleFileUpload($_FILES['attachment'], 'family', $id, $pdo, $uploadErr) === false && $uploadErr !== '') {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم تحديث بيانات الأسرة بنجاح. تنبيه: ' . $uploadErr];
            } else {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم تحديث بيانات الأسرة بنجاح'];
            }
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM poor_families WHERE id=?')->execute([$id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم حذف الأسرة بنجاح'];
        }
    }

    header('Location: ' . BASE_PATH . '/admin/families.php');
    exit;
}

// ─── Search / List ────────────────────────────────────────────────────────────
$search = strip_tags(trim($_GET['search'] ?? ''));
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $pdo->prepare(
        'SELECT * FROM poor_families
         WHERE head_name LIKE ? OR file_number LIKE ? OR phone LIKE ?
         ORDER BY created_at DESC'
    );
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query('SELECT * FROM poor_families ORDER BY created_at DESC');
}
$families = $stmt->fetchAll();
$total    = count($families);

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1"><i class="bi bi-people-fill text-primary me-2"></i>الأسر الفقيرة</h4>
    <p class="text-muted small mb-0">إجمالي السجلات: <strong><?= $total ?></strong></p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
    <i class="bi bi-plus-circle me-1"></i> إضافة أسرة جديدة
  </button>
</div>

<!-- Search -->
<form method="GET" class="mb-4 no-print">
  <div class="input-group" style="max-width:420px">
    <input type="text" name="search" class="form-control" placeholder="ابحث بالاسم أو رقم الملف أو الهاتف…"
           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
    <?php if ($search): ?>
      <a href="<?= BASE_PATH ?>/admin/families.php" class="btn btn-outline-secondary">
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
            <th>اسم رب الأسرة</th>
            <th>عدد الأفراد</th>
            <th>الهاتف</th>
            <th>حالة العمل</th>
            <th>نوع الاحتياج</th>
            <th>تاريخ الإضافة</th>
            <th class="no-print">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($families)): ?>
          <tr><td colspan="9" class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            <?= $search ? 'لا توجد نتائج للبحث' : 'لا توجد أسر مسجّلة بعد' ?>
          </td></tr>
          <?php else: ?>
          <?php foreach ($families as $f): ?>
          <tr>
            <td class="text-muted small"><?= (int)$f['id'] ?></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($f['file_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
            <td class="fw-semibold"><?= htmlspecialchars($f['head_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="text-center"><?= (int)$f['members_count'] ?></td>
            <td><?= htmlspecialchars($f['phone'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?php
              $wBadge = match($f['work_status']) {
                  'يعمل'    => 'success',
                  'متقاعد'  => 'info',
                  'متوفى'   => 'dark',
                  default   => 'secondary',
              };
              ?>
              <span class="badge bg-<?= $wBadge ?>"><?= htmlspecialchars($f['work_status'], ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td>
              <?php
              $nBadge = match($f['need_type']) {
                  'غذائية'  => 'warning text-dark',
                  'مالية'   => 'success',
                  'علاجية'  => 'danger',
                  'تعليمية' => 'primary',
                  default   => 'info text-dark',
              };
              ?>
              <span class="badge bg-<?= $nBadge ?>"><?= htmlspecialchars($f['need_type'], ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td class="text-muted small"><?= htmlspecialchars(substr($f['created_at'] ?? '', 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="no-print">
              <button class="btn btn-sm btn-outline-primary btn-edit me-1"
                      data-bs-toggle="modal" data-bs-target="#editModal"
                      data-record='<?= htmlspecialchars(json_encode($f, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
                      title="تعديل">
                <i class="bi bi-pencil-square"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger btn-delete"
                      data-bs-toggle="modal" data-bs-target="#deleteModal"
                      data-id="<?= (int)$f['id'] ?>"
                      data-name="<?= htmlspecialchars($f['head_name'], ENT_QUOTES, 'UTF-8') ?>"
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
          <h5 class="modal-title" id="addModalLabel"><i class="bi bi-plus-circle me-2"></i>إضافة أسرة جديدة</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">رقم الملف</label>
              <input type="text" name="file_number" class="form-control" placeholder="مثال: F-0001">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">اسم رب الأسرة <span class="text-danger">*</span></label>
              <input type="text" name="head_name" class="form-control" required placeholder="الاسم الكامل">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">عدد أفراد الأسرة</label>
              <input type="number" name="members_count" class="form-control" value="1" min="1">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">رقم الهاتف</label>
              <input type="text" name="phone" class="form-control" placeholder="05XXXXXXXX">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">الدخل الشهري (ريال)</label>
              <input type="number" name="income" class="form-control" value="0" min="0" step="0.01">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">العنوان</label>
              <input type="text" name="address" class="form-control" placeholder="المدينة / الحي / الشارع">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">حالة العمل</label>
              <select name="work_status" class="form-select">
                <option value="يعمل">يعمل</option>
                <option value="لا يعمل" selected>لا يعمل</option>
                <option value="متوفى">متوفى</option>
                <option value="متقاعد">متقاعد</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">نوع الاحتياج</label>
              <select name="need_type" class="form-select">
                <option value="غذائية" selected>غذائية</option>
                <option value="مالية">مالية</option>
                <option value="علاجية">علاجية</option>
                <option value="تعليمية">تعليمية</option>
                <option value="مختلطة">مختلطة</option>
              </select>
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
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>حفظ</button>
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
          <h5 class="modal-title" id="editModalLabel"><i class="bi bi-pencil-square me-2"></i>تعديل بيانات الأسرة</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">رقم الملف</label>
              <input type="text" name="file_number" id="edit_file_number" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">اسم رب الأسرة <span class="text-danger">*</span></label>
              <input type="text" name="head_name" id="edit_head_name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">عدد أفراد الأسرة</label>
              <input type="number" name="members_count" id="edit_members_count" class="form-control" min="1">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">رقم الهاتف</label>
              <input type="text" name="phone" id="edit_phone" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">الدخل الشهري (ريال)</label>
              <input type="number" name="income" id="edit_income" class="form-control" min="0" step="0.01">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">العنوان</label>
              <input type="text" name="address" id="edit_address" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">حالة العمل</label>
              <select name="work_status" id="edit_work_status" class="form-select">
                <option value="يعمل">يعمل</option>
                <option value="لا يعمل">لا يعمل</option>
                <option value="متوفى">متوفى</option>
                <option value="متقاعد">متقاعد</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">نوع الاحتياج</label>
              <select name="need_type" id="edit_need_type" class="form-select">
                <option value="غذائية">غذائية</option>
                <option value="مالية">مالية</option>
                <option value="علاجية">علاجية</option>
                <option value="تعليمية">تعليمية</option>
                <option value="مختلطة">مختلطة</option>
              </select>
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
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>حفظ التعديلات</button>
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
          <p class="fs-6">هل أنت متأكد من حذف أسرة: <strong id="deleteName"></strong>؟</p>
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
