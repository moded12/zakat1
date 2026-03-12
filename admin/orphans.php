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

function generateNextOrphanNumber(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT file_number FROM orphans");
    $max = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $v) {
        $n = numericTail((string)$v);
        if ($n > $max) {
            $max = $n;
        }
    }
    return (string)($max + 1);
}

function resequenceOrphans(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT id FROM orphans ORDER BY id ASC");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $update = $pdo->prepare("UPDATE orphans SET file_number = ? WHERE id = ?");
    $counter = 1;

    foreach ($ids as $id) {
        $update->execute([(string)$counter, (int)$id]);
        $counter++;
    }
}

function saveOrphanAttachments(PDO $pdo, int $entityId, array $files, string $entityType = 'orphans'): void
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

        $storedName = uniqid('orphan_', true) . '.' . $ext;
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

function getOrphanAttachments(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE entity_type = 'orphans' AND entity_id = ? ORDER BY id DESC");
    $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function deleteOrphanRecord(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE entity_type = 'orphans' AND entity_id = ?");
    $stmt->execute([$id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($files as $file) {
        $path = dirname(__DIR__) . '/' . $file['file_path'];
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM attachments WHERE entity_type = 'orphans' AND entity_id = ?");
    $stmt->execute([$id]);

    $stmt = $pdo->prepare("DELETE FROM orphans WHERE id = ?");
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
            $name = trim($_POST['name'] ?? '');
            $id_number = trim($_POST['id_number'] ?? '');
            $contact_info = trim($_POST['contact_info'] ?? '');
            $birth_date = trim($_POST['birth_date'] ?? '');
            $gender = trim($_POST['gender'] ?? '');
            $mother_name = trim($_POST['mother_name'] ?? '');
            $guardian_name = trim($_POST['guardian_name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $education_status = trim($_POST['education_status'] ?? '');
            $health_status = trim($_POST['health_status'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($id === 0 && $file_number === '') {
                $file_number = generateNextOrphanNumber($pdo);
            }

            if ($file_number === '' || $name === '') {
                $error = 'الرقم واسم اليتيم مطلوبان';
            } else {
                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE orphans
                        SET file_number = ?, name = ?, id_number = ?, contact_info = ?, birth_date = ?, gender = ?, mother_name = ?, guardian_name = ?, address = ?, education_status = ?, health_status = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $file_number,
                        $name,
                        $id_number,
                        $contact_info,
                        $birth_date !== '' ? $birth_date : null,
                        $gender,
                        $mother_name,
                        $guardian_name,
                        $address,
                        $education_status,
                        $health_status,
                        $notes,
                        $id
                    ]);
                    saveOrphanAttachments($pdo, $id, $_FILES['attachments'] ?? []);
                    $message = 'تم تحديث بيانات اليتيم بنجاح';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO orphans
                        (file_number, name, id_number, contact_info, birth_date, gender, mother_name, guardian_name, address, education_status, health_status, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $file_number,
                        $name,
                        $id_number,
                        $contact_info,
                        $birth_date !== '' ? $birth_date : null,
                        $gender,
                        $mother_name,
                        $guardian_name,
                        $address,
                        $education_status,
                        $health_status,
                        $notes
                    ]);
                    $newId = (int)$pdo->lastInsertId();
                    saveOrphanAttachments($pdo, $newId, $_FILES['attachments'] ?? []);
                    $message = 'تمت إضافة اليتيم بنجاح';
                }
            }
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                deleteOrphanRecord($pdo, $id);
                resequenceOrphans($pdo);
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
                    deleteOrphanRecord($pdo, (int)$selectedId);
                }
                resequenceOrphans($pdo);
                $message = 'تم حذف السجلات المحددة وإعادة ترتيب الأرقام بنجاح';
            }
        }
    }
}

$editData = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM orphans WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

$search = trim($_GET['search'] ?? '');
$filterGender = trim($_GET['gender'] ?? '');
$filterEducation = trim($_GET['education_status'] ?? '');

$conditions = [];
$params = [];

if ($search !== '') {
    $keyword = '%' . $search . '%';
    $conditions[] = "(file_number LIKE ? OR name LIKE ? OR id_number LIKE ? OR contact_info LIKE ? OR mother_name LIKE ? OR guardian_name LIKE ? OR address LIKE ?)";
    array_push($params, $keyword, $keyword, $keyword, $keyword, $keyword, $keyword, $keyword);
}
if ($filterGender !== '') {
    $conditions[] = "gender = ?";
    $params[] = $filterGender;
}
if ($filterEducation !== '') {
    $conditions[] = "education_status = ?";
    $params[] = $filterEducation;
}

$sql = "SELECT * FROM orphans";
if ($conditions) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY CAST(file_number AS UNSIGNED) ASC, id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$idNumberCounts = [];
$phoneCounts = [];
foreach ($rows as $row) {
    $idNum = trim((string)($row['id_number'] ?? ''));
    $phone = trim((string)($row['contact_info'] ?? ''));
    if ($idNum !== '') {
        $idNumberCounts[$idNum] = ($idNumberCounts[$idNum] ?? 0) + 1;
    }
    if ($phone !== '') {
        $phoneCounts[$phone] = ($phoneCounts[$phone] ?? 0) + 1;
    }
}

$educationValues = [];
try {
    $educationValues = $pdo->query("SELECT DISTINCT education_status FROM orphans WHERE education_status IS NOT NULL AND education_status != '' ORDER BY education_status ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $educationValues = [];
}

adminLayoutStart('إدارة الأيتام', 'orphans');
?>
<style>
.card-box { border: none; border-radius: 18px; box-shadow: 0 10px 28px rgba(0,0,0,.08); }
.table thead th { background: #eef4ff; }
.attachments a { display: inline-block; margin-left: 6px; margin-bottom: 6px; }
.summary-box { background: linear-gradient(135deg, #1d4f88, #2563eb); color: #fff; border-radius: 18px; padding: 1rem 1.2rem; margin-bottom: 1rem; }
.duplicate-row { background: #fff8db !important; }
.duplicate-badge { display: inline-block; margin-top: 4px; padding: 3px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; background: #fff3cd; color: #8a5300; border: 1px solid #f7d77a; }
.auto-number-note { font-size: 12px; color: #6b7280; }
.top-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
</style>

<div class="container-fluid py-2">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="fw-bold mb-1">إدارة الأيتام</h1>
            <p class="text-muted mb-0">الرقم يتولد تلقائيًا، ويُعاد ترتيب جميع الأرقام بعد الحذف/الاستيراد</p>
        </div>

        <div class="top-actions">
            <a href="<?= BASE_PATH ?>/admin/unified_import.php" class="btn btn-outline-primary">
                <i class="bi bi-upload ms-1"></i> الاستيراد الجماعي
            </a>
            <a href="<?= BASE_PATH ?>/admin/print_distribution_sheet.php?source=orphans" class="btn btn-dark">
                <i class="bi bi-printer ms-1"></i> كشف الطباعة
            </a>
        </div>
    </div>

    <div class="summary-box">
        <div class="fw-bold">مهم:</div>
        <div>الترقيم تسلسلي حي: عند الحذف يعاد ترتيب الأرقام بدون قفز.</div>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card card-box mb-4">
        <div class="card-body">
            <h4 class="fw-bold mb-3"><?= $editData ? 'تعديل بيانات اليتيم' : 'إضافة يتيم جديد' ?></h4>
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
                    <div class="col-md-4">
                        <label class="form-label">الاسم</label>
                        <input type="text" name="name" class="form-control" value="<?= e($editData['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">رقم الهوية</label>
                        <input type="text" name="id_number" class="form-control" value="<?= e($editData['id_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">الهاتف</label>
                        <input type="text" name="contact_info" class="form-control" value="<?= e($editData['contact_info'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">تاريخ الميلاد</label>
                        <input type="date" name="birth_date" class="form-control" value="<?= e($editData['birth_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الجنس</label>
                        <select name="gender" class="form-select">
                            <option value="">اختر</option>
                            <option value="ذكر" <?= (($editData['gender'] ?? '') === 'ذكر') ? 'selected' : '' ?>>ذكر</option>
                            <option value="أنثى" <?= (($editData['gender'] ?? '') === 'أنثى') ? 'selected' : '' ?>>أنثى</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">اسم الأم</label>
                        <input type="text" name="mother_name" class="form-control" value="<?= e($editData['mother_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">اسم الوصي</label>
                        <input type="text" name="guardian_name" class="form-control" value="<?= e($editData['guardian_name'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">التعليم</label>
                        <input type="text" name="education_status" class="form-control" value="<?= e($editData['education_status'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">الصحة</label>
                        <input type="text" name="health_status" class="form-control" value="<?= e($editData['health_status'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">المرفقات</label>
                        <input type="file" name="attachments[]" class="form-control" multiple>
                        <small class="text-muted">pdf, jpg, jpeg, png, doc, docx</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">العنوان</label>
                        <textarea name="address" class="form-control" rows="2"><?= e($editData['address'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="3"><?= e($editData['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <?php if ($editData): ?>
                    <?php $attachments = getOrphanAttachments($pdo, (int)$editData['id']); ?>
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

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save2 ms-1"></i><?= $editData ? 'حفظ التعديلات' : 'إضافة اليتيم' ?>
                    </button>
                    <a href="<?= BASE_PATH ?>/admin/orphans.php" class="btn btn-secondary">جديد</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-box">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h4 class="fw-bold mb-0">سجلات الأيتام</h4>
            </div>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-sm-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="بحث بالرقم أو الاسم أو الهوية أو الهاتف..." value="<?= e($search) ?>">
                </div>
                <div class="col-sm-3">
                    <select name="gender" class="form-select form-select-sm">
                        <option value="">الجنس - الكل</option>
                        <option value="ذكر" <?= $filterGender === 'ذكر' ? 'selected' : '' ?>>ذكر</option>
                        <option value="أنثى" <?= $filterGender === 'أنثى' ? 'selected' : '' ?>>أنثى</option>
                    </select>
                </div>
                <div class="col-sm-3">
                    <select name="education_status" class="form-select form-select-sm">
                        <option value="">التعليم - الكل</option>
                        <?php foreach ($educationValues as $edu): ?>
                            <option value="<?= e($edu) ?>" <?= $filterEducation === $edu ? 'selected' : '' ?>><?= e($edu) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-2 d-flex gap-1">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                    <a href="<?= BASE_PATH ?>/admin/orphans.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
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
                                <th>تاريخ الميلاد</th>
                                <th>الوصي</th>
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
                                    $files = getOrphanAttachments($pdo, (int)$row['id']);
                                    $idNum = trim((string)($row['id_number'] ?? ''));
                                    $phone = trim((string)($row['contact_info'] ?? ''));
                                    $duplicateId = $idNum !== '' && ($idNumberCounts[$idNum] ?? 0) > 1;
                                    $duplicatePhone = $phone !== '' && ($phoneCounts[$phone] ?? 0) > 1;
                                    $isDuplicate = $duplicateId || $duplicatePhone;
                                    ?>
                                    <tr class="<?= $isDuplicate ? 'duplicate-row' : '' ?>">
                                        <td><input type="checkbox" class="row-checkbox" name="selected_ids[]" value="<?= (int)$row['id'] ?>"></td>
                                        <td><?= e($row['file_number']) ?></td>
                                        <td><?= e($row['name']) ?></td>
                                        <td>
                                            <?= e($row['id_number'] ?? '') ?>
                                            <?php if ($duplicateId): ?><div class="duplicate-badge">هوية مكررة</div><?php endif; ?>
                                        </td>
                                        <td>
                                            <?= e($row['contact_info']) ?>
                                            <?php if ($duplicatePhone): ?><div class="duplicate-badge">هاتف مكرر</div><?php endif; ?>
                                        </td>
                                        <td><?= e($row['birth_date']) ?></td>
                                        <td><?= e($row['guardian_name']) ?></td>
                                        <td><?= count($files) ?></td>
                                        <td>
                                            <div class="d-flex gap-1 justify-content-center flex-wrap">
                                                <a href="<?= BASE_PATH ?>/admin/beneficiary_history.php?type=orphans&id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-info text-white">
                                                    السجل
                                                </a>
                                                <a href="<?= BASE_PATH ?>/admin/orphans.php?edit=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning">
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