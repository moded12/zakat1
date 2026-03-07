<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
checkAuth();

$pdo = getDB();

// ─── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'رمز الأمان غير صحيح'];
        header('Location: ' . BASE_PATH . '/admin/distributions.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $aidType          = $_POST['aid_type']          ?? 'سلة غذائية';
        $beneficiaryName  = strip_tags(trim($_POST['beneficiary_name'] ?? ''));
        $category         = $_POST['category']          ?? 'أسرة فقيرة';
        $distributionDate = $_POST['distribution_date'] ?? '';
        $quantityAmount   = strip_tags(trim($_POST['quantity_amount']  ?? ''));
        $deliveryStatus   = $_POST['delivery_status']   ?? 'قيد التسليم';
        $responsible      = strip_tags(trim($_POST['responsible']      ?? ''));
        $notes            = strip_tags(trim($_POST['notes']            ?? ''));

        $validAidTypes       = ['سلة غذائية', 'مساعدة مالية', 'ملابس', 'أدوية', 'مستلزمات مدرسية', 'أخرى'];
        $validCategories     = ['أسرة فقيرة', 'يتيم', 'أخرى'];
        $validDeliveryStatus = ['تم التسليم', 'قيد التسليم', 'لم يُسلَّم'];

        if (!in_array($aidType, $validAidTypes, true))           $aidType        = 'سلة غذائية';
        if (!in_array($category, $validCategories, true))        $category       = 'أسرة فقيرة';
        if (!in_array($deliveryStatus, $validDeliveryStatus, true)) $deliveryStatus = 'قيد التسليم';

        $distDateVal = null;
        if ($distributionDate !== '') {
            $dt = DateTime::createFromFormat('Y-m-d', $distributionDate);
            if ($dt && $dt->format('Y-m-d') === $distributionDate) {
                $distDateVal = $distributionDate;
            }
        }

        if ($beneficiaryName === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'اسم المستفيد مطلوب'];
            header('Location: ' . BASE_PATH . '/admin/distributions.php');
            exit;
        }

        if ($action === 'add') {
            $stmt = $pdo->prepare(
                'INSERT INTO distributions (aid_type,beneficiary_name,category,distribution_date,quantity_amount,delivery_status,responsible,notes)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([$aidType,$beneficiaryName,$category,$distDateVal,$quantityAmount,$deliveryStatus,$responsible,$notes]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم تسجيل التوزيع بنجاح'];
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare(
                'UPDATE distributions
                 SET aid_type=?,beneficiary_name=?,category=?,distribution_date=?,
                     quantity_amount=?,delivery_status=?,responsible=?,notes=?,updated_at=NOW()
                 WHERE id=?'
            );
            $stmt->execute([$aidType,$beneficiaryName,$category,$distDateVal,$quantityAmount,$deliveryStatus,$responsible,$notes,$id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم تحديث بيانات التوزيع بنجاح'];
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM distributions WHERE id=?')->execute([$id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم حذف سجل التوزيع بنجاح'];
        }
    }

    header('Location: ' . BASE_PATH . '/admin/distributions.php');
    exit;
}

// ─── Search / List ────────────────────────────────────────────────────────────
$search = strip_tags(trim($_GET['search'] ?? ''));
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $pdo->prepare(
        'SELECT * FROM distributions
         WHERE beneficiary_name LIKE ? OR responsible LIKE ? OR aid_type LIKE ?
         ORDER BY created_at DESC'
    );
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query('SELECT * FROM distributions ORDER BY created_at DESC');
}
$distributions = $stmt->fetchAll();
$total         = count($distributions);

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1"><i class="bi bi-box-seam-fill text-info me-2"></i>التوزيعات</h4>
    <p class="text-muted small mb-0">إجمالي السجلات: <strong><?= $total ?></strong></p>
  </div>
  <button class="btn btn-info text-white" data-bs-toggle="modal" data-bs-target="#addModal">
    <i class="bi bi-plus-circle me-1"></i> تسجيل توزيع جديد
  </button>
</div>

<!-- Search -->
<form method="GET" class="mb-4 no-print">
  <div class="input-group" style="max-width:420px">
    <input type="text" name="search" class="form-control" placeholder="ابحث باسم المستفيد أو نوع المساعدة…"
           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
    <button class="btn btn-outline-info" type="submit"><i class="bi bi-search"></i></button>
    <?php if ($search): ?>
      <a href="<?= BASE_PATH ?>/admin/distributions.php" class="btn btn-outline-secondary">
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
            <th>نوع المساعدة</th>
            <th>اسم المستفيد</th>
            <th>الفئة</th>
            <th>تاريخ التوزيع</th>
            <th>الكمية / المبلغ</th>
            <th>حالة التسليم</th>
            <th>المسؤول</th>
            <th class="no-print">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($distributions)): ?>
          <tr><td colspan="9" class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            <?= $search ? 'لا توجد نتائج للبحث' : 'لا توجد توزيعات مسجّلة بعد' ?>
          </td></tr>
          <?php else: ?>
          <?php foreach ($distributions as $dist): ?>
          <tr>
            <td class="text-muted small"><?= (int)$dist['id'] ?></td>
            <td>
              <?php
              $aBadge = match($dist['aid_type']) {
                  'سلة غذائية'         => 'warning text-dark',
                  'مساعدة مالية'       => 'success',
                  'أدوية'              => 'danger',
                  'ملابس'              => 'info text-dark',
                  'مستلزمات مدرسية'   => 'primary',
                  default              => 'secondary',
              };
              ?>
              <span class="badge bg-<?= $aBadge ?>"><?= htmlspecialchars($dist['aid_type'], ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td class="fw-semibold"><?= htmlspecialchars($dist['beneficiary_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($dist['category'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($dist['distribution_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($dist['quantity_amount'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?php
              $dBadge = match($dist['delivery_status']) {
                  'تم التسليم'  => 'success',
                  'قيد التسليم' => 'warning text-dark',
                  'لم يُسلَّم'  => 'danger',
                  default       => 'secondary',
              };
              ?>
              <span class="badge bg-<?= $dBadge ?>"><?= htmlspecialchars($dist['delivery_status'], ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td><?= htmlspecialchars($dist['responsible'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
            <td class="no-print">
              <button class="btn btn-sm btn-outline-primary btn-edit me-1"
                      data-bs-toggle="modal" data-bs-target="#editModal"
                      data-record='<?= htmlspecialchars(json_encode($dist, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
                      title="تعديل">
                <i class="bi bi-pencil-square"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger btn-delete"
                      data-bs-toggle="modal" data-bs-target="#deleteModal"
                      data-id="<?= (int)$dist['id'] ?>"
                      data-name="<?= htmlspecialchars($dist['beneficiary_name'], ENT_QUOTES, 'UTF-8') ?>"
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
      <form method="POST">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title" id="addModalLabel"><i class="bi bi-plus-circle me-2"></i>تسجيل توزيع جديد</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">نوع المساعدة</label>
              <select name="aid_type" class="form-select">
                <option value="سلة غذائية" selected>سلة غذائية</option>
                <option value="مساعدة مالية">مساعدة مالية</option>
                <option value="ملابس">ملابس</option>
                <option value="أدوية">أدوية</option>
                <option value="مستلزمات مدرسية">مستلزمات مدرسية</option>
                <option value="أخرى">أخرى</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">اسم المستفيد <span class="text-danger">*</span></label>
              <input type="text" name="beneficiary_name" class="form-control" required placeholder="اسم المستفيد">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">الفئة</label>
              <select name="category" class="form-select">
                <option value="أسرة فقيرة" selected>أسرة فقيرة</option>
                <option value="يتيم">يتيم</option>
                <option value="أخرى">أخرى</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">تاريخ التوزيع</label>
              <input type="date" name="distribution_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">الكمية / المبلغ</label>
              <input type="text" name="quantity_amount" class="form-control" placeholder="مثال: 1 سلة، 500 ريال">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">حالة التسليم</label>
              <select name="delivery_status" class="form-select">
                <option value="قيد التسليم" selected>قيد التسليم</option>
                <option value="تم التسليم">تم التسليم</option>
                <option value="لم يُسلَّم">لم يُسلَّم</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">المسؤول عن التسليم</label>
              <input type="text" name="responsible" class="form-control" placeholder="اسم المسؤول">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">ملاحظات</label>
              <textarea name="notes" class="form-control" rows="3" placeholder="أي ملاحظات إضافية…"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-info text-white"><i class="bi bi-save me-1"></i>حفظ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══════════════ EDIT MODAL ═══════════════ -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel"><i class="bi bi-pencil-square me-2"></i>تعديل بيانات التوزيع</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">نوع المساعدة</label>
              <select name="aid_type" id="edit_aid_type" class="form-select">
                <option value="سلة غذائية">سلة غذائية</option>
                <option value="مساعدة مالية">مساعدة مالية</option>
                <option value="ملابس">ملابس</option>
                <option value="أدوية">أدوية</option>
                <option value="مستلزمات مدرسية">مستلزمات مدرسية</option>
                <option value="أخرى">أخرى</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">اسم المستفيد <span class="text-danger">*</span></label>
              <input type="text" name="beneficiary_name" id="edit_beneficiary_name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">الفئة</label>
              <select name="category" id="edit_category" class="form-select">
                <option value="أسرة فقيرة">أسرة فقيرة</option>
                <option value="يتيم">يتيم</option>
                <option value="أخرى">أخرى</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">تاريخ التوزيع</label>
              <input type="date" name="distribution_date" id="edit_distribution_date" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">الكمية / المبلغ</label>
              <input type="text" name="quantity_amount" id="edit_quantity_amount" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">حالة التسليم</label>
              <select name="delivery_status" id="edit_delivery_status" class="form-select">
                <option value="قيد التسليم">قيد التسليم</option>
                <option value="تم التسليم">تم التسليم</option>
                <option value="لم يُسلَّم">لم يُسلَّم</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">المسؤول عن التسليم</label>
              <input type="text" name="responsible" id="edit_responsible" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">ملاحظات</label>
              <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-info text-white"><i class="bi bi-save me-1"></i>حفظ التعديلات</button>
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
          <p class="fs-6">هل أنت متأكد من حذف سجل توزيع: <strong id="deleteName"></strong>؟</p>
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
