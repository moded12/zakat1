<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

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

            if ($file_number === '' || $head_name === '') {
                $error = 'رقم الملف واسم رب الأسرة مطلوبان';
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
                deleteFamilyAttachments($pdo, $id);

                $stmt = $pdo->prepare("DELETE FROM poor_families WHERE id = ?");
                $stmt->execute([$id]);

                $message = 'تم حذف السجل بنجاح';
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
        ORDER BY id DESC
    ");
    $keyword = '%' . $search . '%';
    $stmt->execute([$keyword, $keyword, $keyword, $keyword, $keyword, $keyword]);
} else {
    $stmt = $pdo->query("SELECT * FROM poor_families ORDER BY id DESC");
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>إدارة الأسر الفقيرة</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
body {
    font-family: 'Cairo', sans-serif;
    background: #f4f7fb;
}
.sidebar {
    min-height: 100vh;
    background: linear-gradient(180deg, #163d6b 0%, #1d4f88 100%);
    color: white;
    padding: 1.25rem 1rem;
}
.nav-link {
    color: #e5eefb;
    border-radius: 12px;
    padding: .85rem 1rem;
    margin-bottom: .45rem;
    font-weight: 600;
}
.nav-link:hover, .nav-link.active {
    background: rgba(255,255,255,.14);
    color: #fff;
}
.card-box {
    border: none;
    border-radius: 18px;
    box-shadow: 0 10px 28px rgba(0,0,0,.08);
}
.content {
    padding: 2rem;
}
.table thead th {
    background: #eef4ff;
}
.attachments a {
    display: inline-block;
    margin-left: 6px;
    margin-bottom: 6px;
}
.summary-box {
    background: linear-gradient(135deg, #1d4f88, #2563eb);
    color: #fff;
    border-radius: 18px;
    padding: 1rem 1.2rem;
    margin-bottom: 1rem;
}
</style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-lg-3 col-xl-2 sidebar">
            <h4 class="fw-bold mb-4"><i class="bi bi-heart-fill text-warning ms-2"></i>إدارة الزكاة</h4>
            <nav class="nav flex-column">
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/index.php">لوحة التحكم</a>
                <a class="nav-link active" href="<?= BASE_PATH ?>/admin/poor_families.php">الأسر الفقيرة</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/orphans.php">الأيتام</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/sponsorships.php">كفالة الأيتام</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/distributions.php">التوزيعات</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/reports.php">التقارير</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/print_distribution_sheet.php">كشوفات الطباعة</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/logout.php">تسجيل الخروج</a>
            </nav>
        </aside>

        <main class="col-lg-9 col-xl-10 content">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h1 class="fw-bold mb-1">إدارة الأسر الفقيرة</h1>
                    <p class="text-muted mb-0">إدخال وإدارة البيانات الأساسية المعتمدة في كشوفات التوزيع والطباعة</p>
                </div>
                <a href="<?= BASE_PATH ?>/admin/print_distribution_sheet.php?source=poor_families" class="btn btn-dark">
                    <i class="bi bi-printer ms-1"></i> كشف الطباعة
                </a>
            </div>

            <div class="summary-box">
                <div class="fw-bold">مهم:</div>
                <div>يفضّل إدخال رقم الهوية ورقم الهاتف وعدد الأفراد بدقة لأن هذه البيانات تُستخدم مباشرة في كشوفات التوزيع.</div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= e($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="card card-box mb-4">
                <div class="card-body">
                    <h4 class="fw-bold mb-3"><?= $editData ? 'تعديل بيانات الأسرة' : 'إضافة أسرة جديدة' ?></h4>
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= e($editData['id'] ?? '') ?>">

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">رقم الملف</label>
                                <input type="text" name="file_number" class="form-control" value="<?= e($editData['file_number'] ?? '') ?>" required>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label">اسم رب الأسرة / الاسم الرباعي</label>
                                <input type="text" name="head_name" class="form-control" value="<?= e($editData['head_name'] ?? '') ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">رقم الهوية</label>
                                <input type="text" name="id_number" class="form-control" value="<?= e($editData['id_number'] ?? '') ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">عدد الأفراد</label>
                                <input type="number" name="members_count" class="form-control" value="<?= e($editData['members_count'] ?? 0) ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">رقم الهاتف</label>
                                <input type="text" name="mobile" class="form-control" value="<?= e($editData['mobile'] ?? '') ?>">
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
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save2 ms-1"></i>
                                <?= $editData ? 'حفظ التعديلات' : 'إضافة الأسرة' ?>
                            </button>
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
                            <input type="text" name="search" class="form-control" placeholder="بحث برقم الملف / الاسم / الهوية / الهاتف" value="<?= e($search) ?>">
                            <button class="btn btn-outline-primary" type="submit">بحث</button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle text-center">
                            <thead>
                                <tr>
                                    <th>رقم الملف</th>
                                    <th>اسم رب الأسرة</th>
                                    <th>رقم الهوية</th>
                                    <th>عدد الأفراد</th>
                                    <th>رقم الهاتف</th>
                                    <th>نوع الاحتياج</th>
                                    <th>المرفقات</th>
                                    <th>التحكم</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$rows): ?>
                                    <tr>
                                        <td colspan="8">لا توجد بيانات</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $row): ?>
                                        <?php $files = getFamilyAttachments($pdo, (int)$row['id']); ?>
                                        <tr>
                                            <td><?= e($row['file_number']) ?></td>
                                            <td><?= e($row['head_name']) ?></td>
                                            <td><?= e($row['id_number'] ?? '') ?></td>
                                            <td><?= e($row['members_count']) ?></td>
                                            <td><?= e($row['mobile']) ?></td>
                                            <td><?= e($row['need_type']) ?></td>
                                            <td><?= count($files) ?></td>
                                            <td>
                                                <div class="d-flex gap-1 justify-content-center flex-wrap">
                                                    <a href="<?= BASE_PATH ?>/admin/poor_families.php?edit=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning">
                                                        تعديل
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف السجل؟');">
                                                        <?= csrfInput() ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>
</body>
</html>