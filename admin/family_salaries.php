<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';

requireAdmin();
$pdo = getDB();

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

function numericTail(string $value): int
{
    $value = trim($value);
    return preg_match('/(\d+)$/', $value, $m) ? (int)$m[1] : 0;
}

function generateNextSalaryNumber(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT salary_number FROM family_salaries");
    $max = 0;

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
        $num = numericTail((string)$value);
        if ($num > $max) {
            $max = $num;
        }
    }

    return (string)($max + 1);
}

function resequenceFamilySalaries(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT id FROM family_salaries ORDER BY id ASC");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $update = $pdo->prepare("UPDATE family_salaries SET salary_number = ? WHERE id = ?");
    $counter = 1;

    foreach ($ids as $id) {
        $update->execute([(string)$counter, (int)$id]);
        $counter++;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'رمز الحماية غير صالح، يرجى إعادة المحاولة';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $salary_number = trim($_POST['salary_number'] ?? '');
            $beneficiary_name = trim($_POST['beneficiary_name'] ?? '');
            $beneficiary_id_number = trim($_POST['beneficiary_id_number'] ?? '');
            $beneficiary_phone = trim($_POST['beneficiary_phone'] ?? '');
            $salary_amount = trim($_POST['salary_amount'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($id === 0 && $salary_number === '') {
                $salary_number = generateNextSalaryNumber($pdo);
            }

            if ($salary_number === '' || $beneficiary_name === '') {
                $error = 'الرقم والاسم مطلوبان';
            } elseif ($salary_amount === '' || !is_numeric($salary_amount) || (float)$salary_amount < 0) {
                $error = 'يرجى إدخال راتب صحيح';
            } else {
                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE family_salaries
                        SET salary_number = ?, beneficiary_name = ?, beneficiary_id_number = ?, beneficiary_phone = ?, salary_amount = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $salary_number,
                        $beneficiary_name,
                        $beneficiary_id_number,
                        $beneficiary_phone,
                        (float)$salary_amount,
                        $notes,
                        $id
                    ]);
                    $message = 'تم تحديث بيانات راتب الأسرة بنجاح';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO family_salaries
                        (salary_number, beneficiary_name, beneficiary_id_number, beneficiary_phone, salary_amount, notes)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $salary_number,
                        $beneficiary_name,
                        $beneficiary_id_number,
                        $beneficiary_phone,
                        (float)$salary_amount,
                        $notes
                    ]);
                    $message = 'تمت إضافة سجل راتب الأسرة بنجاح';
                }
            }
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM family_salaries WHERE id = ?");
                $stmt->execute([$id]);
                resequenceFamilySalaries($pdo);
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
                $stmtDelete = $pdo->prepare("DELETE FROM family_salaries WHERE id = ?");
                foreach ($selectedIds as $selectedId) {
                    $stmtDelete->execute([(int)$selectedId]);
                }
                resequenceFamilySalaries($pdo);
                $message = 'تم حذف السجلات المحددة وإعادة ترتيب الأرقام بنجاح';
            }
        }
    }
}

$editData = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $stmt = $pdo->prepare("
        SELECT id, salary_number, beneficiary_name, beneficiary_id_number, beneficiary_phone, salary_amount, notes
        FROM family_salaries
        WHERE id = ?
    ");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

$search = trim($_GET['search'] ?? '');

$sql = "SELECT id, salary_number, beneficiary_name, beneficiary_id_number, beneficiary_phone, salary_amount, notes
        FROM family_salaries
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $k = '%' . $search . '%';
    $sql .= " AND (
        salary_number LIKE ?
        OR beneficiary_name LIKE ?
        OR beneficiary_id_number LIKE ?
        OR beneficiary_phone LIKE ?
        OR notes LIKE ?
    )";
    $params = [$k, $k, $k, $k, $k];
}
$sql .= " ORDER BY CAST(salary_number AS UNSIGNED) ASC, id ASC";

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

adminLayoutStart('رواتب الأسر', 'family_salaries');
?>
<style>
.card-box { border: none; border-radius: 18px; box-shadow: 0 10px 28px rgba(0,0,0,.08); }
.table thead th { background: #eef4ff; }
.summary-box { background: linear-gradient(135deg, #7c3aed, #8b5cf6); color: #fff; border-radius: 18px; padding: 1rem 1.2rem; margin-bottom: 1rem; }
.duplicate-row { background: #fff8db !important; }
.duplicate-badge { display: inline-block; margin-top: 4px; padding: 3px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; background: #fff3cd; color: #8a5300; border: 1px solid #f7d77a; }
.auto-number-note { font-size: 12px; color: #6b7280; }
.notes-box { min-height: 42px; }
</style>

<div class="container-fluid py-2">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h2 class="fw-bold mb-1">رواتب الأسر</h2>
            <div class="text-muted">إدارة وعرض بيانات رواتب الأسر واستخدامها داخل التوزيعات والطباعة</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="<?= BASE_PATH ?>/admin/distributions.php?target=family_salaries">
                <i class="bi bi-plus-circle ms-1"></i> توزيعة جديدة (رواتب الأسر)
            </a>
        </div>
    </div>

    <div class="summary-box">
        <div class="fw-bold">مهم:</div>
        <div>يمكنك تعديل سجلات رواتب الأسر يدويًا، وعند الحذف يعاد ترتيب الأرقام تلقائيًا.</div>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card card-box mb-4">
        <div class="card-body">
            <h4 class="fw-bold mb-3"><?= $editData ? 'تعديل سجل راتب الأسرة' : 'إضافة سجل راتب أسرة' ?></h4>
            <form method="POST">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= e($editData['id'] ?? '') ?>">

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">الرقم</label>
                        <input type="text" name="salary_number" class="form-control" value="<?= e($editData['salary_number'] ?? '') ?>" <?= $editData ? '' : 'readonly' ?>>
                        <?php if (!$editData): ?><div class="auto-number-note mt-1">سيتم توليده تلقائيًا</div><?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">الاسم</label>
                        <input type="text" name="beneficiary_name" class="form-control" value="<?= e($editData['beneficiary_name'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">رقم الهوية</label>
                        <input type="text" name="beneficiary_id_number" class="form-control" value="<?= e($editData['beneficiary_id_number'] ?? '') ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">الهاتف</label>
                        <input type="text" name="beneficiary_phone" class="form-control" value="<?= e($editData['beneficiary_phone'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">الراتب</label>
                        <input type="number" step="0.01" min="0" name="salary_amount" class="form-control" value="<?= e($editData['salary_amount'] ?? '0') ?>" required>
                    </div>

                    <div class="col-md-9">
                        <label class="form-label">وصف الحالة</label>
                        <textarea name="notes" class="form-control notes-box" rows="2" placeholder="أدخل وصف الحالة هنا"><?= e($editData['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save2 ms-1"></i><?= $editData ? 'حفظ التعديلات' : 'إضافة السجل' ?>
                    </button>
                    <a href="<?= BASE_PATH ?>/admin/family_salaries.php" class="btn btn-secondary">جديد</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-box mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-10">
                    <label class="form-label">بحث</label>
                    <input class="form-control" name="search" value="<?= e($search) ?>" placeholder="بحث بالرقم / الاسم / الهوية / الهاتف / وصف الحالة">
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-dark" type="submit">بحث</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-box">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <h5 class="fw-bold mb-0">القائمة</h5>
                <span class="badge bg-primary">عدد السجلات: <?= count($rows) ?></span>
            </div>

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
                                <th style="width:90px;">الرقم</th>
                                <th>الاسم</th>
                                <th style="width:170px;">الهوية</th>
                                <th style="width:160px;">الهاتف</th>
                                <th style="width:130px;">الراتب</th>
                                <th style="width:220px;">وصف الحالة</th>
                                <th style="width:220px;">التحكم</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr><td colspan="8">لا توجد بيانات</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <?php
                                    $idNum = trim((string)($r['beneficiary_id_number'] ?? ''));
                                    $phone = trim((string)($r['beneficiary_phone'] ?? ''));
                                    $duplicateId = $idNum !== '' && ($idNumberCounts[$idNum] ?? 0) > 1;
                                    $duplicatePhone = $phone !== '' && ($phoneCounts[$phone] ?? 0) > 1;
                                    $isDuplicate = $duplicateId || $duplicatePhone;
                                    ?>
                                    <tr class="<?= $isDuplicate ? 'duplicate-row' : '' ?>">
                                        <td><input type="checkbox" class="row-checkbox" name="selected_ids[]" value="<?= (int)$r['id'] ?>"></td>
                                        <td><?= e($r['salary_number']) ?></td>
                                        <td><?= e($r['beneficiary_name']) ?></td>
                                        <td>
                                            <?= e($r['beneficiary_id_number']) ?>
                                            <?php if ($duplicateId): ?><div class="duplicate-badge">هوية مكررة</div><?php endif; ?>
                                        </td>
                                        <td>
                                            <?= e($r['beneficiary_phone']) ?>
                                            <?php if ($duplicatePhone): ?><div class="duplicate-badge">هاتف مكرر</div><?php endif; ?>
                                        </td>
                                        <td><?= number_format((float)$r['salary_amount'], 2) ?></td>
                                        <td style="white-space: pre-line;"><?= e($r['notes']) ?></td>
                                        <td>
                                            <div class="d-flex gap-1 justify-content-center flex-wrap">
                                                <a class="btn btn-sm btn-info text-white"
                                                   href="<?= BASE_PATH ?>/admin/beneficiary_history.php?type=family_salaries&id=<?= (int)$r['id'] ?>">
                                                    السجل
                                                </a>
                                                <a class="btn btn-sm btn-warning"
                                                   href="<?= BASE_PATH ?>/admin/family_salaries.php?edit=<?= (int)$r['id'] ?>">
                                                    تعديل
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="submitSingleDelete(<?= (int)$r['id'] ?>)">
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