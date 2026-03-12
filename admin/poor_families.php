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

if (!defined('UPLOADS_DIR')) {
    define('UPLOADS_DIR', dirname(__DIR__) . '/upload_files/');
}

if (!is_dir(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0777, true);
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function numericTail(string $value): int
{
    $value = trim($value);
    return preg_match('/(\d+)$/', $value, $m) ? (int)$m[1] : 0;
}

function generateNextFamilyNumber(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT file_number FROM poor_families");
    $max = 0;

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
        $num = numericTail((string)$value);
        if ($num > $max) {
            $max = $num;
        }
    }

    return (string)($max + 1);
}

function resequencePoorFamilies(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT id FROM poor_families ORDER BY id ASC");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $update = $pdo->prepare("UPDATE poor_families SET file_number = ? WHERE id = ?");
    $counter = 1;

    foreach ($ids as $id) {
        $update->execute([(string)$counter, (int)$id]);
        $counter++;
    }
}

function saveAttachments(PDO $pdo, int $entityId, array $files, string $entityType = 'poor_families'): void
{
    if (empty($files['name'][0])) {
        return;
    }

    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $maxSize = 5 * 1024 * 1024;

    foreach ($files['name'] as $i => $originalName) {
        if (empty($files['tmp_name'][$i])) {
            continue;
        }

        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $size = (int)($files['size'][$i] ?? 0);
        if ($size > $maxSize) {
            continue;
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            continue;
        }

        $storedName = uniqid('pf_', true) . '.' . $ext;
        $targetPath = UPLOADS_DIR . $storedName;

        if (move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
            $stmt = $pdo->prepare("
                INSERT INTO attachments (entity_type, entity_id, original_name, stored_name, file_path, file_size, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $entityType,
                $entityId,
                $originalName,
                $storedName,
                'upload_files/' . $storedName,
                $size
            ]);
        }
    }
}

function getFamilyAttachments(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE entity_type = 'poor_families' AND entity_id = ? ORDER BY id DESC");
    $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function deleteFamilyAttachments(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE entity_type = 'poor_families' AND entity_id = ?");
    $stmt->execute([$id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($files as $file) {
        $path = dirname(__DIR__) . '/' . $file['file_path'];
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM attachments WHERE entity_type = 'poor_families' AND entity_id = ?");
    $stmt->execute([$id]);
}

function deleteFamilyRecord(PDO $pdo, int $id): void
{
    deleteFamilyAttachments($pdo, $id);
    $stmt = $pdo->prepare("DELETE FROM poor_families WHERE id = ?");
    $stmt->execute([$id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'رمز الحماية غير صالح، يرجى إعادة المحاولة';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $file_number = trim($_POST['file_number'] ?? '');
            $head_name = trim($_POST['head_name'] ?? '');
            $id_number = trim($_POST['id_number'] ?? '');
            $members_count = (int)($_POST['members_count'] ?? 0);
            $mobile = trim($_POST['mobile'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $work_status = trim($_POST['work_status'] ?? '');
            $income_amount = (float)($_POST['income_amount'] ?? 0);
            $need_type = trim($_POST['need_type'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($id === 0 && $file_number === '') {
                $file_number = generateNextFamilyNumber($pdo);
            }

            if ($file_number === '' || $head_name === '') {
                $error = 'الرقم والاسم مطلوبان';
            } else {
                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE poor_families
                        SET file_number = ?, head_name = ?, id_number = ?, members_count = ?, mobile = ?, address = ?, work_status = ?, income_amount = ?, need_type = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $file_number,
                        $head_name,
                        $id_number,
                        $members_count,
                        $mobile,
                        $address,
                        $work_status,
                        $income_amount,
                        $need_type,
                        $notes,
                        $id
                    ]);
                    saveAttachments($pdo, $id, $_FILES['attachments'] ?? []);
                    $message = 'تم تحديث بيانات الأسرة بنجاح';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO poor_families
                        (file_number, head_name, id_number, members_count, mobile, address, work_status, income_amount, need_type, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $file_number,
                        $head_name,
                        $id_number,
                        $members_count,
                        $mobile,
                        $address,
                        $work_status,
                        $income_amount,
                        $need_type,
                        $notes
                    ]);
                    $newId = (int)$pdo->lastInsertId();
                    saveAttachments($pdo, $newId, $_FILES['attachments'] ?? []);
                    $message = 'تمت إضافة الأسرة بنجاح';
                }
            }
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                deleteFamilyRecord($pdo, $id);
                resequencePoorFamilies($pdo);
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
                    deleteFamilyRecord($pdo, (int)$selectedId);
                }
                resequencePoorFamilies($pdo);
                $message = 'تم حذف السجلات المحددة وإعادة ترتيب الأرقام بنجاح';
            }
        }
    }
}

$editData = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM poor_families WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT * FROM poor_families
        WHERE file_number LIKE ?
           OR head_name LIKE ?
           OR id_number LIKE ?
           OR mobile LIKE ?
           OR address LIKE ?
           OR need_type LIKE ?
        ORDER BY CAST(file_number AS UNSIGNED) ASC, id ASC
    ");
    $keyword = '%' . $search . '%';
    $stmt->execute([$keyword, $keyword, $keyword, $keyword, $keyword, $keyword]);
} else {
    $stmt = $pdo->query("SELECT * FROM poor_families ORDER BY CAST(file_number AS UNSIGNED) ASC, id ASC");
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$idNumberCounts = [];
$mobileCounts = [];

foreach ($rows as $row) {
    $idNum = trim((string)($row['id_number'] ?? ''));
    $mob = trim((string)($row['mobile'] ?? ''));

    if ($idNum !== '') {
        $idNumberCounts[$idNum] = ($idNumberCounts[$idNum] ?? 0) + 1;
    }
    if ($mob !== '') {
        $mobileCounts[$mob] = ($mobileCounts[$mob] ?? 0) + 1;
    }
}

adminLayoutStart('إدارة الأسر الفقيرة', 'poor_families');
?>
<style>
.card-box { border:none; border-radius:18px; box-shadow:0 10px 28px rgba(0,0,0,.08); }
.table thead th { background:#eef4ff; }
.attachments a { display:inline-block; margin-left:6px; margin-bottom:6px; }
.summary-box { background:linear-gradient(135deg, #1d4f88, #2563eb); color:#fff; border-radius:18px; padding:1rem 1.2rem; margin-bottom:1rem; }
.duplicate-row { background:#fff8db !important; }
.duplicate-badge { display:inline-block; margin-top:4px; padding:3px 8px; border-radius:999px; font-size:12px; font-weight:700; background:#fff3cd; color:#8a5300; border:1px solid #f7d77a; }
.auto-number-note { font-size:12px; color:#6b7280; }
</style>

<div class="container-fluid py-2">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="fw-bold mb-1">إدارة الأسر الفقيرة</h1>
            <p class="text-muted mb-0">الرقم يتولد تلقائيًا، ويُعاد ترتيب جميع الأرقام بعد الحذف</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= BASE_PATH ?>/admin/unified_import.php" class="btn btn-outline-primary"><i class="bi bi-upload ms-1"></i> الاستيراد الجماعي</a>
            <a href="<?= BASE_PATH ?>/admin/print_distribution_sheet.php?source=poor_families" class="btn btn-dark"><i class="bi bi-printer ms-1"></i> كشف الطباعة</a>
        </div>
    </div>

    <div class="summary-box">
        <div class="fw-bold">مهم:</div>
        <div>الترقيم الآن تسلسلي حي: عند الحذف يعاد ترتيب جميع الأرقام من 1 إلى آخر سجل.</div>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card card-box mb-4">
        <div class="card-body">
            <h4 class="fw-bold mb-3"><?= $editData ? 'تعديل بيانات الأسرة' : 'إضافة أسرة جديدة' ?></h4>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= e($editData['id'] ?? '') ?>">

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">الرقم</label>
                        <input type="text" name="file_number" class="form-control" value="<?= e($editData['file_number'] ?? '') ?>" <?= $editData ? '' : 'readonly' ?>>
                        <?php if (!$editData): ?><div class="auto-number-note mt-1">سيتم توليده تلقائيًا</div><?php endif; ?>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">الاسم</label>
                        <input type="text" name="head_name" class="form-control" value="<?= e($editData['head_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">رقم الهوية</label>
                        <input type="text" name="id_number" class="form-control" value="<?= e($editData['id_number'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">الهاتف</label>
                        <input type="text" name="mobile" class="form-control" value="<?= e($editData['mobile'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">عدد الأفراد</label>
                        <input type="number" name="members_count" class="form-control" value="<?= e($editData['members_count'] ?? 0) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">حالة العمل</label>
                        <input type="text" name="work_status" class="form-control" value="<?= e($editData['work_status'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الدخل</label>
                        <input type="number" step="0.01" name="income_amount" class="form-control" value="<?= e($editData['income_amount'] ?? 0) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">نوع الاحتياج</label>
                        <input type="text" name="need_type" class="form-control" value="<?= e($editData['need_type'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">المرفقات</label>
                        <input type="file" name="attachments[]" class="form-control" multiple>
                        <small class="text-muted">pdf, jpg, jpeg, png, doc, docx</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">العنوان</label>
                        <textarea name="address" class="form-control" rows="2"><?= e($editData['address'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="3"><?= e($editData['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <?php if ($editData): ?>
                    <?php $attachments = getFamilyAttachments($pdo, (int)$editData['id']); ?>
                    <?php if ($attachments): ?>
                        <div class="attachments mt-3">
                            <label class="form-label fw-bold">المرفقات الحالية:</label><br>
                            <?php foreach ($attachments as $file): ?>
                                <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= BASE_PATH . '/' . e($file['file_path']) ?>">
                                    <i class="bi bi-paperclip"></i> <?= e($file['original_name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="mt-4 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save2 ms-1"></i><?= $editData ? 'حفظ التعديلات' : 'إضافة الأسرة' ?></button>
                    <a href="<?= BASE_PATH ?>/admin/poor_families.php" class="btn btn-secondary">سجل جديد</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-box">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h4 class="fw-bold mb-0">سجلات الأسر الفقيرة</h4>
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <input type="text" name="search" class="form-control" placeholder="بحث بالرقم / الاسم / الهوية / الهاتف" value="<?= e($search) ?>">
                    <button class="btn btn-outline-primary" type="submit">بحث</button>
                </form>
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
                                <th>الرقم</th>
                                <th>الاسم</th>
                                <th>رقم الهوية</th>
                                <th>الهاتف</th>
                                <th>عدد الأفراد</th>
                                <th>نوع الاحتياج</th>
                                <th>المرفقات</th>
                                <th>التحكم</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr><td colspan="9">لا توجد بيانات</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $row): ?>
                                    <?php
                                    $files = getFamilyAttachments($pdo, (int)$row['id']);
                                    $idNum = trim((string)($row['id_number'] ?? ''));
                                    $mob = trim((string)($row['mobile'] ?? ''));
                                    $duplicateId = $idNum !== '' && ($idNumberCounts[$idNum] ?? 0) > 1;
                                    $duplicatePhone = $mob !== '' && ($mobileCounts[$mob] ?? 0) > 1;
                                    $isDuplicate = $duplicateId || $duplicatePhone;
                                    ?>
                                    <tr class="<?= $isDuplicate ? 'duplicate-row' : '' ?>">
                                        <td><input type="checkbox" class="row-checkbox" name="selected_ids[]" value="<?= (int)$row['id'] ?>"></td>
                                        <td><?= e($row['file_number']) ?></td>
                                        <td><?= e($row['head_name']) ?></td>
                                        <td>
                                            <?= e($row['id_number'] ?? '') ?>
                                            <?php if ($duplicateId): ?><div class="duplicate-badge">هوية مكررة</div><?php endif; ?>
                                        </td>
                                        <td>
                                            <?= e($row['mobile']) ?>
                                            <?php if ($duplicatePhone): ?><div class="duplicate-badge">هاتف مكرر</div><?php endif; ?>
                                        </td>
                                        <td><?= e($row['members_count']) ?></td>
                                        <td><?= e($row['need_type']) ?></td>
                                        <td><?= count($files) ?></td>

                                        <td>
                                            <div class="d-flex gap-1 justify-content-center flex-wrap">
                                                <a href="<?= BASE_PATH ?>/admin/beneficiary_history.php?type=poor_families&id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-info text-white">
                                                    السجل
                                                </a>
                                                <a href="<?= BASE_PATH ?>/admin/poor_families.php?edit=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning">
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