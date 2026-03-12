<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';

requireAdmin();

$pdo = getDB();
$message = '';
$error = '';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function numericTail(string $value): int
{
    $value = trim($value);
    return preg_match('/(\d+)$/', $value, $m) ? (int)$m[1] : 0;
}

function generateNextSponsorshipNumber(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT sponsorship_number FROM sponsorships");
    $max = 0;

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $v) {
        $n = numericTail((string)$v);
        if ($n > $max) {
            $max = $n;
        }
    }

    return (string)($max + 1);
}

function resequenceSponsorships(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT id FROM sponsorships ORDER BY id ASC");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $update = $pdo->prepare("UPDATE sponsorships SET sponsorship_number = ? WHERE id = ?");
    $counter = 1;

    foreach ($ids as $id) {
        $update->execute([(string)$counter, (int)$id]);
        $counter++;
    }
}

function deleteSponsorshipRecord(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("DELETE FROM sponsorships WHERE id = ?");
    $stmt->execute([$id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'رمز الحماية غير صالح، يرجى إعادة المحاولة';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $sponsorship_number = trim($_POST['sponsorship_number'] ?? '');
            $orphan_name = trim($_POST['orphan_name'] ?? '');
            $beneficiary_id_number = trim($_POST['beneficiary_id_number'] ?? '');
            $beneficiary_phone = trim($_POST['beneficiary_phone'] ?? '');
            $sponsor_name = trim($_POST['sponsor_name'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $start_date = trim($_POST['start_date'] ?? '');
            $end_date = trim($_POST['end_date'] ?? '');
            $status = trim($_POST['status'] ?? '');
            $payment_method = trim($_POST['payment_method'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($id === 0 && $sponsorship_number === '') {
                $sponsorship_number = generateNextSponsorshipNumber($pdo);
            }

            if ($sponsorship_number === '' || $orphan_name === '') {
                $error = 'الرقم والاسم مطلوبان';
            } else {
                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE sponsorships
                        SET sponsorship_number = ?, orphan_name = ?, beneficiary_id_number = ?, beneficiary_phone = ?, sponsor_name = ?, amount = ?, start_date = ?, end_date = ?, status = ?, payment_method = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $sponsorship_number,
                        $orphan_name,
                        $beneficiary_id_number,
                        $beneficiary_phone,
                        $sponsor_name,
                        $amount,
                        $start_date !== '' ? $start_date : null,
                        $end_date !== '' ? $end_date : null,
                        $status,
                        $payment_method,
                        $notes,
                        $id
                    ]);
                    $message = 'تم تحديث بيانات الكفالة بنجاح';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO sponsorships
                        (sponsorship_number, orphan_name, beneficiary_id_number, beneficiary_phone, sponsor_name, amount, start_date, end_date, status, payment_method, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $sponsorship_number,
                        $orphan_name,
                        $beneficiary_id_number,
                        $beneficiary_phone,
                        $sponsor_name,
                        $amount,
                        $start_date !== '' ? $start_date : null,
                        $end_date !== '' ? $end_date : null,
                        $status,
                        $payment_method,
                        $notes
                    ]);
                    $message = 'تمت إضافة الكفالة بنجاح';
                }
            }
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                deleteSponsorshipRecord($pdo, $id);
                resequenceSponsorships($pdo);
                $message = 'تم حذف السجل وإعادة ترتيب الأرقام بنجاح';
            }
        }

        if ($action === 'bulk_delete') {
            $selectedIds = $_POST['selected_ids'] ?? [];
            $selectedIds = is_array($selectedIds)
                ? array_values(array_filter($selectedIds, fn($v) => ctype_digit((string)$v)))
                : [];

            if (!$selectedIds) {
                $error = 'يرجى تحديد سجل واحد على الأقل للحذف';
            } else {
                foreach ($selectedIds as $selectedId) {
                    deleteSponsorshipRecord($pdo, (int)$selectedId);
                }
                resequenceSponsorships($pdo);
                $message = 'تم حذف السجلات المحددة وإعادة ترتيب الأرقام بنجاح';
            }
        }
    }
}

$editData = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM sponsorships WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

$search = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterPayment = trim($_GET['payment_method'] ?? '');

$conditions = [];
$params = [];

if ($search !== '') {
    $keyword = '%' . $search . '%';
    $conditions[] = "(sponsorship_number LIKE ? OR orphan_name LIKE ? OR beneficiary_id_number LIKE ? OR beneficiary_phone LIKE ? OR sponsor_name LIKE ?)";
    array_push($params, $keyword, $keyword, $keyword, $keyword, $keyword);
}
if ($filterStatus !== '') {
    $conditions[] = "status = ?";
    $params[] = $filterStatus;
}
if ($filterPayment !== '') {
    $conditions[] = "payment_method = ?";
    $params[] = $filterPayment;
}

$sql = "SELECT * FROM sponsorships";
if ($conditions) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY CAST(sponsorship_number AS UNSIGNED) ASC, id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$idNumberCounts = [];
$phoneCounts = [];
foreach ($rows as $row) {
    $idNum = trim((string)($row['beneficiary_id_number'] ?? ''));
    $phone = trim((string)($row['beneficiary_phone'] ?? ''));
    if ($idNum !== '') {
        $idNumberCounts[$idNum] = ($idNumberCounts[$idNum] ?? 0) + 1;
    }
    if ($phone !== '') {
        $phoneCounts[$phone] = ($phoneCounts[$phone] ?? 0) + 1;
    }
}

adminLayoutStart('كفالة الأيتام', 'sponsorships');
?>
<style>
.card-box { border: none; border-radius: 18px; box-shadow: 0 10px 28px rgba(0,0,0,.08); }
.table thead th { background: #eef4ff; }
.summary-box { background: linear-gradient(135deg, #1d4f88, #2563eb); color: #fff; border-radius: 18px; padding: 1rem 1.2rem; margin-bottom: 1rem; }
.duplicate-row { background: #fff8db !important; }
.duplicate-badge { display: inline-block; margin-top: 4px; padding: 3px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; background: #fff3cd; color: #8a5300; border: 1px solid #f7d77a; }
.auto-number-note { font-size: 12px; color: #6b7280; }
.top-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
</style>

<div class="container-fluid py-2">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="fw-bold mb-1">كفالة الأيتام</h1>
            <p class="text-muted mb-0">الترقيم تسلسلي حي: بعد الحذف/الاستيراد يعاد ترتيب الأرقام</p>
        </div>

        <div class="top-actions">
            <a href="<?= BASE_PATH ?>/admin/unified_import.php" class="btn btn-outline-primary">
                <i class="bi bi-upload ms-1"></i> الاستيراد الجماعي
            </a>
            <a href="<?= BASE_PATH ?>/admin/print_distribution_sheet.php?source=sponsorships" class="btn btn-dark">
                <i class="bi bi-printer ms-1"></i> كشف الطباعة
            </a>
        </div>
    </div>

    <div class="summary-box">
        <div class="fw-bold">مهم:</div>
        <div>عند حذف أي سجل يتم إعادة ترتيب أرقام الكفالات بدون قفز.</div>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card card-box mb-4">
        <div class="card-body">
            <h4 class="fw-bold mb-3"><?= $editData ? 'تعديل بيانات الكفالة' : 'إضافة كفالة جديدة' ?></h4>
            <form method="POST">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= e($editData['id'] ?? '') ?>">

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">الرقم</label>
                        <input type="text" name="sponsorship_number" class="form-control" value="<?= e($editData['sponsorship_number'] ?? '') ?>" <?= $editData ? '' : 'readonly' ?>>
                        <?php if (!$editData): ?><div class="auto-number-note mt-1">سيتم توليده تلقائيًا</div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">الاسم</label>
                        <input type="text" name="orphan_name" class="form-control" value="<?= e($editData['orphan_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">رقم الهوية</label>
                        <input type="text" name="beneficiary_id_number" class="form-control" value="<?= e($editData['beneficiary_id_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">الهاتف</label>
                        <input type="text" name="beneficiary_phone" class="form-control" value="<?= e($editData['beneficiary_phone'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">اسم الكافل</label>
                        <input type="text" name="sponsor_name" class="form-control" value="<?= e($editData['sponsor_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">مبلغ الكفالة</label>
                        <input type="number" step="0.01" name="amount" class="form-control" value="<?= e($editData['amount'] ?? 0) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">بداية الكفالة</label>
                        <input type="date" name="start_date" class="form-control" value="<?= e($editData['start_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">نهاية الكفالة</label>
                        <input type="date" name="end_date" class="form-control" value="<?= e($editData['end_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">الحالة</label>
                        <select name="status" class="form-select">
                            <option value="">اختر</option>
                            <option value="نشطة" <?= (($editData['status'] ?? '') === 'نشطة') ? 'selected' : '' ?>>نشطة</option>
                            <option value="منتهية" <?= (($editData['status'] ?? '') === 'منتهية') ? 'selected' : '' ?>>منتهية</option>
                            <option value="معلقة" <?= (($editData['status'] ?? '') === 'معلقة') ? 'selected' : '' ?>>معلقة</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">طريقة الدفع</label>
                        <input type="text" name="payment_method" class="form-control" value="<?= e($editData['payment_method'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">ملاحظات</label>
                        <input type="text" name="notes" class="form-control" value="<?= e($editData['notes'] ?? '') ?>">
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save2 ms-1"></i><?= $editData ? 'حفظ التعديلات' : 'إضافة الكفالة' ?></button>
                    <a href="<?= BASE_PATH ?>/admin/sponsorships.php" class="btn btn-secondary">جديد</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-box">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h4 class="fw-bold mb-0">سجلات الكفالات
                    <?php if ($search !== '' || $filterStatus !== '' || $filterPayment !== ''): ?>
                        <span class="badge bg-info text-dark fs-6 ms-2"><?= count($rows) ?> نتيجة</span>
                    <?php endif; ?>
                </h4>
            </div>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-sm-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="بحث بالرقم أو الاسم أو الهوية أو الهاتف..." value="<?= e($search) ?>">
                </div>
                <div class="col-sm-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">الحالة - الكل</option>
                        <option value="نشطة" <?= $filterStatus === 'نشطة' ? 'selected' : '' ?>>نشطة</option>
                        <option value="منتهية" <?= $filterStatus === 'منتهية' ? 'selected' : '' ?>>منتهية</option>
                        <option value="معلقة" <?= $filterStatus === 'معلقة' ? 'selected' : '' ?>>معلقة</option>
                    </select>
                </div>
                <div class="col-sm-3">
                    <select name="payment_method" class="form-select form-select-sm">
                        <option value="">طريقة الدفع - الكل</option>
                        <option value="نقدي" <?= $filterPayment === 'نقدي' ? 'selected' : '' ?>>نقدي</option>
                        <option value="تحويل بنكي" <?= $filterPayment === 'تحويل بنكي' ? 'selected' : '' ?>>تحويل بنكي</option>
                        <option value="شيك" <?= $filterPayment === 'شيك' ? 'selected' : '' ?>>شيك</option>
                    </select>
                </div>
                <div class="col-sm-2 d-flex gap-1">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                    <a href="<?= BASE_PATH ?>/admin/sponsorships.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                </div>
            </form>

            <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف السجلات المحددة؟');">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="bulk_delete">

                <div class="d-flex gap-2 flex-wrap mb-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllRows(true)">تحديد الكل</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllRows(false)">إلغاء التحديد</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash ms-1"></i> حذف المحدد</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-center">
                        <thead>
                            <tr>
                                <th style="width:50px;"><input type="checkbox" id="checkAll" onclick="toggleAllRows(this.checked)"></th>
                                <th>الرقم</th>
                                <th>الاسم</th>
                                <th>رقم الهوية</th>
                                <th>الهاتف</th>
                                <th>اسم الكافل</th>
                                <th>المبلغ</th>
                                <th>بداية الكفالة</th>
                                <th>نهاية الكفالة</th>
                                <th>الحالة</th>
                                <th>التحكم</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr><td colspan="11">لا توجد بيانات</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $row): ?>
                                    <?php
                                    $idNum = trim((string)($row['beneficiary_id_number'] ?? ''));
                                    $phone = trim((string)($row['beneficiary_phone'] ?? ''));
                                    $duplicateId = $idNum !== '' && ($idNumberCounts[$idNum] ?? 0) > 1;
                                    $duplicatePhone = $phone !== '' && ($phoneCounts[$phone] ?? 0) > 1;
                                    $isDuplicate = $duplicateId || $duplicatePhone;
                                    ?>
                                    <tr class="<?= $isDuplicate ? 'duplicate-row' : '' ?>">
                                        <td><input type="checkbox" class="row-checkbox" name="selected_ids[]" value="<?= (int)$row['id'] ?>"></td>
                                        <td><?= e($row['sponsorship_number']) ?></td>
                                        <td><?= e($row['orphan_name']) ?></td>
                                        <td>
                                            <?= e($row['beneficiary_id_number'] ?? '') ?>
                                            <?php if ($duplicateId): ?><div class="duplicate-badge">هوية مكررة</div><?php endif; ?>
                                        </td>
                                        <td>
                                            <?= e($row['beneficiary_phone'] ?? '') ?>
                                            <?php if ($duplicatePhone): ?><div class="duplicate-badge">هاتف مكرر</div><?php endif; ?>
                                        </td>
                                        <td><?= e($row['sponsor_name']) ?></td>
                                        <td><?= e($row['amount']) ?></td>
                                        <td><?= e($row['start_date']) ?></td>
                                        <td><?= e($row['end_date']) ?></td>
                                        <td><?= e($row['status']) ?></td>
                                        <td>
                                            <div class="d-flex gap-1 justify-content-center flex-wrap">
                                                <a href="<?= BASE_PATH ?>/admin/beneficiary_history.php?type=sponsorships&id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-info text-white">
                                                    السجل
                                                </a>
                                                <a href="<?= BASE_PATH ?>/admin/sponsorships.php?edit=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning">
                                                    تعديل
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="submitSingleDelete(<?= (int)$row['id'] ?>)">
                                                    حذف
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <form id="singleDeleteForm" method="POST" class="d-none">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="singleDeleteId" value="">
            </form>
        </div>
    </div>
</div>

<script>
function toggleAllRows(state) {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = state);
    const master = document.getElementById('checkAll');
    if (master) master.checked = state;
}
function submitSingleDelete(id) {
    if (!confirm('هل أنت متأكد من حذف السجل؟')) return;
    document.getElementById('singleDeleteId').value = id;
    document.getElementById('singleDeleteForm').submit();
}
</script>

<?php adminLayoutEnd(); ?>