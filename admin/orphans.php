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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'رمز الحماية غير صالح، يرجى إعادة المحاولة';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $file_number = trim($_POST['file_number'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $birth_date = trim($_POST['birth_date'] ?? '');
            $gender = trim($_POST['gender'] ?? '');
            $mother_name = trim($_POST['mother_name'] ?? '');
            $guardian_name = trim($_POST['guardian_name'] ?? '');
            $contact_info = trim($_POST['contact_info'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $education_status = trim($_POST['education_status'] ?? '');
            $health_status = trim($_POST['health_status'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($file_number === '' || $name === '') {
                $error = 'رقم الملف واسم اليتيم مطلوبان';
            } else {
                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE orphans
                        SET file_number = ?, name = ?, birth_date = ?, gender = ?, mother_name = ?, guardian_name = ?, contact_info = ?, address = ?, education_status = ?, health_status = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $file_number,
                        $name,
                        $birth_date !== '' ? $birth_date : null,
                        $gender,
                        $mother_name,
                        $guardian_name,
                        $contact_info,
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
                        (file_number, name, birth_date, gender, mother_name, guardian_name, contact_info, address, education_status, health_status, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $file_number,
                        $name,
                        $birth_date !== '' ? $birth_date : null,
                        $gender,
                        $mother_name,
                        $guardian_name,
                        $contact_info,
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

                $message = 'تم حذف سجل اليتيم بنجاح';
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
if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT * FROM orphans
        WHERE file_number LIKE ?
           OR name LIKE ?
           OR mother_name LIKE ?
           OR guardian_name LIKE ?
           OR contact_info LIKE ?
           OR address LIKE ?
        ORDER BY id DESC
    ");
    $keyword = '%' . $search . '%';
    $stmt->execute([$keyword, $keyword, $keyword, $keyword, $keyword, $keyword]);
} else {
    $stmt = $pdo->query("SELECT * FROM orphans ORDER BY id DESC");
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>إدارة الأيتام</title>
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
</style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-lg-3 col-xl-2 sidebar">
            <h4 class="fw-bold mb-4"><i class="bi bi-heart-fill text-warning ms-2"></i>إدارة الزكاة</h4>
            <nav class="nav flex-column">
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/index.php">لوحة التحكم</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/poor_families.php">الأسر الفقيرة</a>
                <a class="nav-link active" href="<?= BASE_PATH ?>/admin/orphans.php">الأيتام</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/sponsorships.php">كفالة الأيتام</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/distributions.php">التوزيعات</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/reports.php">التقارير</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/logout.php">تسجيل الخروج</a>
            </nav>
        </aside>

        <main class="col-lg-9 col-xl-10 content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fw-bold mb-1">إدارة الأيتام</h1>
                    <p class="text-muted mb-0">إضافة، تعديل، حذف، عرض، والبحث في بيانات الأيتام</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= e($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="card card-box mb-4">
                <div class="card-body">
                    <h4 class="fw-bold mb-3"><?= $editData ? 'تعديل بيانات اليتيم' : 'إضافة يتيم جديد' ?></h4>
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= e($editData['id'] ?? '') ?>">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">رقم الملف</label>
                                <input type="text" name="file_number" class="form-control" value="<?= e($editData['file_number'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">اسم اليتيم</label>
                                <input type="text" name="name" class="form-control" value="<?= e($editData['name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">تاريخ الميلاد</label>
                                <input type="date" name="birth_date" class="form-control" value="<?= e($editData['birth_date'] ?? '') ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">الجنس</label>
                                <select name="gender" class="form-select">
                                    <option value="">اختر</option>
                                    <option value="ذكر" <?= (($editData['gender'] ?? '') === 'ذكر') ? 'selected' : '' ?>>ذكر</option>
                                    <option value="أنثى" <?= (($editData['gender'] ?? '') === 'أنثى') ? 'selected' : '' ?>>أنثى</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">اسم الأم</label>
                                <input type="text" name="mother_name" class="form-control" value="<?= e($editData['mother_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">اسم الوصي</label>
                                <input type="text" name="guardian_name" class="form-control" value="<?= e($editData['guardian_name'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">التواصل</label>
                                <input type="text" name="contact_info" class="form-control" value="<?= e($editData['contact_info'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">المرفقات</label>
                                <input type="file" name="attachments[]" class="form-control" multiple>
                                <small class="text-muted">الملفات المسموحة: pdf, jpg, jpeg, png, doc, docx</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">التعليم</label>
                                <input type="text" name="education_status" class="form-control" value="<?= e($editData['education_status'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الصحة</label>
                                <input type="text" name="health_status" class="form-control" value="<?= e($editData['health_status'] ?? '') ?>">
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
                                <i class="bi bi-save2 ms-1"></i>
                                <?= $editData ? 'حفظ التعديلات' : 'إضافة اليتيم' ?>
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
                        <form method="GET" class="d-flex gap-2">
                            <input type="text" name="search" class="form-control" placeholder="بحث..." value="<?= e($search) ?>">
                            <button class="btn btn-outline-primary" type="submit">بحث</button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle text-center">
                            <thead>
                                <tr>
                                    <th>رقم الملف</th>
                                    <th>الاسم</th>
                                    <th>تاريخ الميلاد</th>
                                    <th>الجنس</th>
                                    <th>الوصي</th>
                                    <th>التواصل</th>
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
                                        <?php $files = getOrphanAttachments($pdo, (int)$row['id']); ?>
                                        <tr>
                                            <td><?= e($row['file_number']) ?></td>
                                            <td><?= e($row['name']) ?></td>
                                            <td><?= e($row['birth_date']) ?></td>
                                            <td><?= e($row['gender']) ?></td>
                                            <td><?= e($row['guardian_name']) ?></td>
                                            <td><?= e($row['contact_info']) ?></td>
                                            <td><?= count($files) ?></td>
                                            <td>
                                                <a href="<?= BASE_PATH ?>/admin/orphans.php?edit=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning">
                                                    تعديل
                                                </a>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف السجل؟');">
                                                    <?= csrfInput() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                                </form>
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