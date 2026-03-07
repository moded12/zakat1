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
        header('Location: ' . BASE_PATH . '/admin/sponsorships.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $sponsorshipNumber = strip_tags(trim($_POST['sponsorship_number'] ?? ''));
        $orphanId          = (int)($_POST['orphan_id'] ?? 0);
        $sponsorName       = strip_tags(trim($_POST['sponsor_name']       ?? ''));
        $amount            = max(0.0, (float)($_POST['amount']            ?? 0));
        $startDate         = $_POST['start_date'] ?? '';
        $endDate           = $_POST['end_date']   ?? '';
        $status            = $_POST['status']         ?? 'نشطة';
        $paymentMethod     = $_POST['payment_method'] ?? 'نقدي';
        $notes             = strip_tags(trim($_POST['notes'] ?? ''));

        $validStatus  = ['نشطة', 'منتهية', 'موقوفة'];
        $validPayment = ['نقدي', 'تحويل بنكي', 'شيك'];
        if (!in_array($status, $validStatus, true))       $status        = 'نشطة';
        if (!in_array($paymentMethod, $validPayment, true)) $paymentMethod = 'نقدي';

        // Validate dates
        $validateDate = function (string $d): ?string {
            if ($d === '') return null;
            $dt = DateTime::createFromFormat('Y-m-d', $d);
            return ($dt && $dt->format('Y-m-d') === $d) ? $d : null;
        };
        $startDateVal = $validateDate($startDate);
        $endDateVal   = $validateDate($endDate);

        if ($sponsorName === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'اسم الكفيل مطلوب'];
            header('Location: ' . BASE_PATH . '/admin/sponsorships.php');
            exit;
        }

        $orphanIdVal = $orphanId > 0 ? $orphanId : null;

        if ($action === 'add') {
            $stmt = $pdo->prepare(
                'INSERT INTO sponsorships (sponsorship_number,orphan_id,sponsor_name,amount,start_date,end_date,status,payment_method,notes)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([$sponsorshipNumber,$orphanIdVal,$sponsorName,$amount,$startDateVal,$endDateVal,$status,$paymentMethod,$notes]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم إضافة الكفالة بنجاح'];
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare(
                'UPDATE sponsorships
                 SET sponsorship_number=?,orphan_id=?,sponsor_name=?,amount=?,start_date=?,
                     end_date=?,status=?,payment_method=?,notes=?,updated_at=NOW()
                 WHERE id=?'
            );
            $stmt->execute([$sponsorshipNumber,$orphanIdVal,$sponsorName,$amount,$startDateVal,$endDateVal,$status,$paymentMethod,$notes,$id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم تحديث بيانات الكفالة بنجاح'];
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM sponsorships WHERE id=?')->execute([$id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم حذف الكفالة بنجاح'];
        }
    }

    header('Location: ' . BASE_PATH . '/admin/sponsorships.php');
    exit;
}

// ─── Load orphans for dropdown ─────────────────────────────────────────────
$orphansList = $pdo->query('SELECT id, name FROM orphans ORDER BY name ASC')->fetchAll();

// ─── Search / List ────────────────────────────────────────────────────────────
$search = strip_tags(trim($_GET['search'] ?? ''));
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $pdo->prepare(
        'SELECT s.*, o.name AS orphan_name
         FROM sponsorships s
         LEFT JOIN orphans o ON s.orphan_id = o.id
         WHERE s.sponsor_name LIKE ? OR s.sponsorship_number LIKE ? OR o.name LIKE ?
         ORDER BY s.created_at DESC'
    );
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query(
        'SELECT s.*, o.name AS orphan_name
         FROM sponsorships s
         LEFT JOIN orphans o ON s.orphan_id = o.id
         ORDER BY s.created_at DESC'
    );
}
$sponsorships = $stmt->fetchAll();
$total        = count($sponsorships);

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1"><i class="bi bi-hand-thumbs-up-fill text-warning me-2"></i>الكفالات</h4>
    <p class="text-muted small mb-0">إجمالي السجلات: <strong><?= $total ?></strong></p>
  </div>
  <button class="btn btn-warning text-white" data-bs-toggle="modal" data-bs-target="#addModal">
    <i class="bi bi-plus-circle me-1"></i> إضافة كفالة جديدة
  </button>
</div>

<!-- Search -->
<form method="GET" class="mb-4 no-print">
  <div class="input-group" style="max-width:420px">
    <input type="text" name="search" class="form-control" placeholder="ابحث باسم الكفيل أو رقم الكفالة أو اليتيم…"
           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
    <button class="btn btn-outline-warning" type="submit"><i class="bi bi-search"></i></button>
    <?php if ($search): ?>
      <a href="<?= BASE_PATH ?>/admin/sponsorships.php" class="btn btn-outline-secondary">
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
            <th>رقم الكفالة</th>
            <th>اسم الكفيل</th>
            <th>اليتيم المكفول</th>
            <th>المبلغ (ريال)</th>
            <th>تاريخ البدء</th>
            <th>تاريخ الانتهاء</th>
            <th>الحالة</th>
            <th>طريقة الدفع</th>
            <th class="no-print">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($sponsorships)): ?>
          <tr><td colspan="10" class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            <?= $search ? 'لا توجد نتائج للبحث' : 'لا توجد كفالات مسجّلة بعد' ?>
          </td></tr>
          <?php else: ?>
          <?php foreach ($sponsorships as $sp): ?>
          <tr>
            <td class="text-muted small"><?= (int)$sp['id'] ?></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($sp['sponsorship_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
            <td class="fw-semibold"><?= htmlspecialchars($sp['sponsor_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($sp['orphan_name'] ?? 'غير محدد', ENT_QUOTES, 'UTF-8') ?></td>
            <td class="text-success fw-bold"><?= number_format((float)$sp['amount'], 2) ?></td>
            <td><?= htmlspecialchars($sp['start_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($sp['end_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?php
              $sBadge = match($sp['status']) {
                  'نشطة'    => 'success',
                  'منتهية'  => 'secondary',
                  'موقوفة'  => 'warning text-dark',
                  default   => 'light text-dark',
              };
              ?>
              <span class="badge bg-<?= $sBadge ?>"><?= htmlspecialchars($sp['status'], ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td><?= htmlspecialchars($sp['payment_method'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="no-print">
              <button class="btn btn-sm btn-outline-primary btn-edit me-1"
                      data-bs-toggle="modal" data-bs-target="#editModal"
                      data-record='<?= htmlspecialchars(json_encode($sp, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
                      title="تعديل">
                <i class="bi bi-pencil-square"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger btn-delete"
                      data-bs-toggle="modal" data-bs-target="#deleteModal"
                      data-id="<?= (int)$sp['id'] ?>"
                      data-name="<?= htmlspecialchars($sp['sponsor_name'], ENT_QUOTES, 'UTF-8') ?>"
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
          <h5 class="modal-title" id="addModalLabel"><i class="bi bi-plus-circle me-2"></i>إضافة كفالة جديدة</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">رقم الكفالة</label>
              <input type="text" name="sponsorship_number" class="form-control" placeholder="مثال: SP-0001">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">اسم الكفيل <span class="text-danger">*</span></label>
              <input type="text" name="sponsor_name" class="form-control" required placeholder="الاسم الكامل للكفيل">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">اليتيم المكفول</label>
              <select name="orphan_id" class="form-select">
                <option value="">-- اختر يتيماً --</option>
                <?php foreach ($orphansList as $orph): ?>
                <option value="<?= (int)$orph['id'] ?>"><?= htmlspecialchars($orph['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">المبلغ (ريال)</label>
              <input type="number" name="amount" class="form-control" value="0" min="0" step="0.01">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">تاريخ البدء</label>
              <input type="date" name="start_date" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">تاريخ الانتهاء</label>
              <input type="date" name="end_date" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">حالة الكفالة</label>
              <select name="status" class="form-select">
                <option value="نشطة" selected>نشطة</option>
                <option value="منتهية">منتهية</option>
                <option value="موقوفة">موقوفة</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">طريقة الدفع</label>
              <select name="payment_method" class="form-select">
                <option value="نقدي" selected>نقدي</option>
                <option value="تحويل بنكي">تحويل بنكي</option>
                <option value="شيك">شيك</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">ملاحظات</label>
              <textarea name="notes" class="form-control" rows="3" placeholder="أي ملاحظات إضافية…"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-warning text-white"><i class="bi bi-save me-1"></i>حفظ</button>
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
          <h5 class="modal-title" id="editModalLabel"><i class="bi bi-pencil-square me-2"></i>تعديل بيانات الكفالة</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">رقم الكفالة</label>
              <input type="text" name="sponsorship_number" id="edit_sponsorship_number" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">اسم الكفيل <span class="text-danger">*</span></label>
              <input type="text" name="sponsor_name" id="edit_sponsor_name" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">اليتيم المكفول</label>
              <select name="orphan_id" id="edit_orphan_id" class="form-select">
                <option value="">-- اختر يتيماً --</option>
                <?php foreach ($orphansList as $orph): ?>
                <option value="<?= (int)$orph['id'] ?>"><?= htmlspecialchars($orph['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">المبلغ (ريال)</label>
              <input type="number" name="amount" id="edit_amount" class="form-control" min="0" step="0.01">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">تاريخ البدء</label>
              <input type="date" name="start_date" id="edit_start_date" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">تاريخ الانتهاء</label>
              <input type="date" name="end_date" id="edit_end_date" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">حالة الكفالة</label>
              <select name="status" id="edit_status" class="form-select">
                <option value="نشطة">نشطة</option>
                <option value="منتهية">منتهية</option>
                <option value="موقوفة">موقوفة</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">طريقة الدفع</label>
              <select name="payment_method" id="edit_payment_method" class="form-select">
                <option value="نقدي">نقدي</option>
                <option value="تحويل بنكي">تحويل بنكي</option>
                <option value="شيك">شيك</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">ملاحظات</label>
              <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-warning text-white"><i class="bi bi-save me-1"></i>حفظ التعديلات</button>
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
          <p class="fs-6">هل أنت متأكد من حذف كفالة: <strong id="deleteName"></strong>؟</p>
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
