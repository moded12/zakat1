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

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'رمز الحماية غير صالح، يرجى إعادة المحاولة';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $assistance_type = trim($_POST['assistance_type'] ?? '');
            $beneficiary_name = trim($_POST['beneficiary_name'] ?? '');
            $category_name = trim($_POST['category_name'] ?? '');
            $distribution_date = trim($_POST['distribution_date'] ?? '');
            $quantity_or_amount = trim($_POST['quantity_or_amount'] ?? '');
            $delivery_status = trim($_POST['delivery_status'] ?? '');
            $responsible_person = trim($_POST['responsible_person'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($assistance_type === '' || $beneficiary_name === '') {
                $error = 'نوع المساعدة واسم المستفيد مطلوبان';
            } else {
                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE distributions
                        SET assistance_type = ?, beneficiary_name = ?, category_name = ?, distribution_date = ?, quantity_or_amount = ?, delivery_status = ?, responsible_person = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $assistance_type,
                        $beneficiary_name,
                        $category_name,
                        $distribution_date !== '' ? $distribution_date : null,
                        $quantity_or_amount,
                        $delivery_status,
                        $responsible_person,
                        $notes,
                        $id
                    ]);
                    $message = 'تم تحديث بيانات التوزيع بنجاح';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO distributions
                        (assistance_type, beneficiary_name, category_name, distribution_date, quantity_or_amount, delivery_status, responsible_person, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $assistance_type,
                        $beneficiary_name,
                        $category_name,
                        $distribution_date !== '' ? $distribution_date : null,
                        $quantity_or_amount,
                        $delivery_status,
                        $responsible_person,
                        $notes
                    ]);
                    $message = 'تمت إضافة سجل التوزيع بنجاح';
                }
            }
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM distributions WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'تم حذف سجل التوزيع بنجاح';
            }
        }
    }
}

$editData = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM distributions WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT * FROM distributions
        WHERE assistance_type LIKE ?
           OR beneficiary_name LIKE ?
           OR category_name LIKE ?
           OR delivery_status LIKE ?
           OR responsible_person LIKE ?
        ORDER BY id DESC
    ");
    $keyword = '%' . $search . '%';
    $stmt->execute([$keyword, $keyword, $keyword, $keyword, $keyword]);
} else {
    $stmt = $pdo->query("SELECT * FROM distributions ORDER BY id DESC");
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>إدارة التوزيعات</title>
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
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/orphans.php">الأيتام</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/sponsorships.php">كفالة الأيتام</a>
                <a class="nav-link active" href="<?= BASE_PATH ?>/admin/distributions.php">التوزيعات</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/reports.php">التقارير</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/logout.php">تسجيل الخروج</a>
            </nav>
        </aside>

        <main class="col-lg-9 col-xl-10 content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fw-bold mb-1">إدارة التوزيعات</h1>
                    <p class="text-muted mb-0">إضافة، تعديل، حذف، عرض، والبحث في سجلات التوزيع</p>
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
                    <h4 class="fw-bold mb-3"><?= $editData ? 'تعديل سجل التوزيع' : 'إضافة توزيع جديد' ?></h4>
                    <form method="POST">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= e($editData['id'] ?? '') ?>">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">نوع المساعدة</label>
                                <input type="text" name="assistance_type" class="form-control" value="<?= e($editData['assistance_type'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">اسم المستفيد</label>
                                <input type="text" name="beneficiary_name" class="form-control" value="<?= e($editData['beneficiary_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">الفئة</label>
                                <input type="text" name="category_name" class="form-control" value="<?= e($editData['category_name'] ?? '') ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">تاريخ التوزيع</label>
                                <input type="date" name="distribution_date" class="form-control" value="<?= e($editData['distribution_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">الكمية أو المبلغ</label>
                                <input type="text" name="quantity_or_amount" class="form-control" value="<?= e($editData['quantity_or_amount'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">حالة التسليم</label>
                                <select name="delivery_status" class="form-select">
                                    <option value="">اختر</option>
                                    <option value="تم التسليم" <?= (($editData['delivery_status'] ?? '') === 'تم التسليم') ? 'selected' : '' ?>>تم التسليم</option>
                                    <option value="معلق" <?= (($editData['delivery_status'] ?? '') === 'معلق') ? 'selected' : '' ?>>معلق</option>
                                    <option value="ملغي" <?= (($editData['delivery_status'] ?? '') === 'ملغي') ? 'selected' : '' ?>>ملغي</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">المسؤول</label>
                                <input type="text" name="responsible_person" class="form-control" value="<?= e($editData['responsible_person'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label">ملاحظات</label>
                                <textarea name="notes" class="form-control" rows="3"><?= e($editData['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save2 ms-1"></i>
                                <?= $editData ? 'حفظ التعديلات' : 'إضافة التوزيع' ?>
                            </button>
                            <a href="<?= BASE_PATH ?>/admin/distributions.php" class="btn btn-secondary">جديد</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-box">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h4 class="fw-bold mb-0">سجلات التوزيعات</h4>
                        <form method="GET" class="d-flex gap-2">
                            <input type="text" name="search" class="form-control" placeholder="بحث..." value="<?= e($search) ?>">
                            <button class="btn btn-outline-primary" type="submit">بحث</button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle text-center">
                            <thead>
                                <tr>
                                    <th>نوع المساعدة</th>
                                    <th>اسم المستفيد</th>
                                    <th>الفئة</th>
                                    <th>تاريخ التوزيع</th>
                                    <th>الكمية أو المبلغ</th>
                                    <th>حالة التسليم</th>
                                    <th>المسؤول</th>
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
                                        <tr>
                                            <td><?= e($row['assistance_type']) ?></td>
                                            <td><?= e($row['beneficiary_name']) ?></td>
                                            <td><?= e($row['category_name']) ?></td>
                                            <td><?= e($row['distribution_date']) ?></td>
                                            <td><?= e($row['quantity_or_amount']) ?></td>
                                            <td><?= e($row['delivery_status']) ?></td>
                                            <td><?= e($row['responsible_person']) ?></td>
                                            <td>
                                                <a href="<?= BASE_PATH ?>/admin/distributions.php?edit=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning">
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