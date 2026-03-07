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
            $sponsorship_number = trim($_POST['sponsorship_number'] ?? '');
            $orphan_name = trim($_POST['orphan_name'] ?? '');
            $sponsor_name = trim($_POST['sponsor_name'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $start_date = trim($_POST['start_date'] ?? '');
            $end_date = trim($_POST['end_date'] ?? '');
            $status = trim($_POST['status'] ?? '');
            $payment_method = trim($_POST['payment_method'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($sponsorship_number === '' || $orphan_name === '' || $sponsor_name === '') {
                $error = 'رقم الكفالة واسم اليتيم واسم الكافل مطلوبة';
            } else {
                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE sponsorships
                        SET sponsorship_number = ?, orphan_name = ?, sponsor_name = ?, amount = ?, start_date = ?, end_date = ?, status = ?, payment_method = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $sponsorship_number,
                        $orphan_name,
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
                        (sponsorship_number, orphan_name, sponsor_name, amount, start_date, end_date, status, payment_method, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $sponsorship_number,
                        $orphan_name,
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
                $stmt = $pdo->prepare("DELETE FROM sponsorships WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'تم حذف سجل الكفالة بنجاح';
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
if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT * FROM sponsorships
        WHERE sponsorship_number LIKE ?
           OR orphan_name LIKE ?
           OR sponsor_name LIKE ?
           OR status LIKE ?
           OR payment_method LIKE ?
        ORDER BY id DESC
    ");
    $keyword = '%' . $search . '%';
    $stmt->execute([$keyword, $keyword, $keyword, $keyword, $keyword]);
} else {
    $stmt = $pdo->query("SELECT * FROM sponsorships ORDER BY id DESC");
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>كفالة الأيتام</title>
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
                <a class="nav-link active" href="<?= BASE_PATH ?>/admin/sponsorships.php">كفالة الأيتام</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/distributions.php">التوزيعات</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/reports.php">التقارير</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/logout.php">تسجيل الخروج</a>
            </nav>
        </aside>

        <main class="col-lg-9 col-xl-10 content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fw-bold mb-1">كفالة الأيتام</h1>
                    <p class="text-muted mb-0">إضافة، تعديل، حذف، عرض، والبحث في سجلات الكفالات</p>
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
                    <h4 class="fw-bold mb-3"><?= $editData ? 'تعديل بيانات الكفالة' : 'إضافة كفالة جديدة' ?></h4>
                    <form method="POST">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= e($editData['id'] ?? '') ?>">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">رقم الكفالة</label>
                                <input type="text" name="sponsorship_number" class="form-control" value="<?= e($editData['sponsorship_number'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">اسم اليتيم</label>
                                <input type="text" name="orphan_name" class="form-control" value="<?= e($editData['orphan_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">اسم الكافل</label>
                                <input type="text" name="sponsor_name" class="form-control" value="<?= e($editData['sponsor_name'] ?? '') ?>" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">مبلغ الكفالة</label>
                                <input type="number" step="0.01" name="amount" class="form-control" value="<?= e($editData['amount'] ?? 0) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">بداية الكفالة</label>
                                <input type="date" name="start_date" class="form-control" value="<?= e($editData['start_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">نها  ة الكفالة</label>
                                <input type="date" name="end_date" class="form-control" value="<?= e($editData['end_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">الحالة</label>
                                <select name="status" class="form-select">
                                    <option value="">اختر</option>
                                    <option value="نشطة" <?= (($editData['status'] ?? '') === 'نشطة') ? 'selected' : '' ?>>نشطة</option>
                                    <option value="منتهية" <?= (($editData['status'] ?? '') === 'منتهية') ? 'selected' : '' ?>>منتهية</option>
                                    <option value="معلقة" <?= (($editData['status'] ?? '') === 'معلقة') ? 'selected' : '' ?>>معلقة</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">طريقة الدفع</label>
                                <input type="text" name="payment_method" class="form-control" value="<?= e($editData['payment_method'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ملاحظات</label>
                                <input type="text" name="notes" class="form-control" value="<?= e($editData['notes'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save2 ms-1"></i>
                                <?= $editData ? 'حفظ التعديلات' : 'إضافة الكفالة' ?>
                            </button>
                            <a href="<?= BASE_PATH ?>/admin/sponsorships.php" class="btn btn-secondary">جديد</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-box">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h4 class="fw-bold mb-0">سجلات الك  الات</h4>
                        <form method="GET" class="d-flex gap-2">
                            <input type="text" name="search" class="form-control" placeholder="بحث..." value="<?= e($search) ?>">
                            <button class="btn btn-outline-primary" type="submit">بحث</button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle text-center">
                            <thead>
                                <tr>
                                    <th>رقم الكفالة</th>
                                    <th>اسم اليتيم</th>
                                    <th>اسم الكافل</th>
                                    <th>المبلغ</th>
                                    <th>بداية الكفالة</th>
                                    <th>نهاية الكفالة</th>
                                    <th>الحالة</th>
                                    <th>طريقة الدفع</th>
                                    <th>التحكم</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$rows): ?>
                                    <tr>
                                        <td colspan="9">لا توجد بيانات</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $row): ?>
                                        <tr>
                                            <td><?= e($row['sponsorship_number']) ?></td>
                                            <td><?= e($row['orphan_name']) ?></td>
                                            <td><?= e($row['sponsor_name']) ?></td>
                                            <td><?= e($row['amount']) ?></td>
                                            <td><?= e($row['start_date']) ?></td>
                                            <td><?= e($row['end_date']) ?></td>
                                            <td><?= e($row['status']) ?></td>
                                            <td><?= e($row['payment_method']) ?></td>
                                            <td>
                                                <a href="<?= BASE_PATH ?>/admin/sponsorships.php?edit=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning">
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