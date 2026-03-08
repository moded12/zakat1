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

$search         = trim($_GET['search'] ?? '');
$filterStatus   = trim($_GET['status'] ?? '');
$filterPayment  = trim($_GET['payment_method'] ?? '');

$conditions = [];
$params     = [];

if ($search !== '') {
    $keyword     = '%' . $search . '%';
    $conditions[] = "(sponsorship_number LIKE ? OR orphan_name LIKE ? OR sponsor_name LIKE ?)";
    array_push($params, $keyword, $keyword, $keyword);
}
if ($filterStatus !== '') {
    $conditions[] = "status = ?";
    $params[]     = $filterStatus;
}
if ($filterPayment !== '') {
    $conditions[] = "payment_method = ?";
    $params[]     = $filterPayment;
}

$sql = "SELECT * FROM sponsorships";
if ($conditions) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/index.php"><i class="bi bi-speedometer2 ms-2"></i>لوحة التحكم</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/poor_families.php"><i class="bi bi-people-fill ms-2"></i>الأسر الفقيرة</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/orphans.php"><i class="bi bi-person-hearts ms-2"></i>الأيتام</a>
                <a class="nav-link active" href="<?= BASE_PATH ?>/admin/sponsorships.php"><i class="bi bi-cash-coin ms-2"></i>كفالة الأيتام</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/distributions.php"><i class="bi bi-box-seam ms-2"></i>التوزيعات</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/reports.php"><i class="bi bi-bar-chart-line-fill ms-2"></i>التقارير</a>
                <hr style="border-color:rgba(255,255,255,0.15);margin:.4rem 0;">
                <small class="text-white-50 px-2 mb-1" style="font-size:.72rem;letter-spacing:.04em;">الطباعة</small>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/print_distribution_sheet.php?source=poor_families"><i class="bi bi-printer ms-2"></i>كشف الأسر</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/print_distribution_sheet.php?source=orphans"><i class="bi bi-printer ms-2"></i>كشف الأيتام</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/print_distribution_sheet.php?source=sponsorships"><i class="bi bi-printer ms-2"></i>كشف الكفالات</a>
                <hr style="border-color:rgba(255,255,255,0.15);margin:.4rem 0;">
                <a class="nav-link" style="background:rgba(220,53,69,.18);" href="<?= BASE_PATH ?>/admin/logout.php"><i class="bi bi-box-arrow-right ms-2"></i>تسجيل الخروج</a>
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
                            <label class="form-label">نهاية الكفالة</label>
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
                        <h4 class="fw-bold mb-0">سجلات الكفالات
                            <?php if ($search !== '' || $filterStatus !== '' || $filterPayment !== ''): ?>
                                <span class="badge bg-info text-dark fs-6 ms-2"><?= count($rows) ?> نتيجة</span>
                            <?php endif; ?>
                        </h4>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="<?= BASE_PATH ?>/admin/print_distribution_sheet.php?source=sponsorships" class="btn btn-outline-secondary btn-sm" target="_blank">
                                <i class="bi bi-printer ms-1"></i>طباعة كشف الكفالات
                            </a>
                        </div>
                    </div>

                    <!-- Smart Search -->
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-sm-4">
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="بحث بالرقم أو الاسم..." value="<?= e($search) ?>">
                        </div>
                        <div class="col-sm-3">
                            <select name="status" class="form-select form-select-sm">
                                <option value="">الحالة - الكل</option>
                                <option value="نشطة"   <?= $filterStatus === 'نشطة'   ? 'selected' : '' ?>>نشطة</option>
                                <option value="منتهية" <?= $filterStatus === 'منتهية' ? 'selected' : '' ?>>منتهية</option>
                                <option value="معلقة"  <?= $filterStatus === 'معلقة'  ? 'selected' : '' ?>>معلقة</option>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <select name="payment_method" class="form-select form-select-sm">
                                <option value="">طريقة الدفع - الكل</option>
                                <option value="نقدي"        <?= $filterPayment === 'نقدي'        ? 'selected' : '' ?>>نقدي</option>
                                <option value="تحويل بنكي"  <?= $filterPayment === 'تحويل بنكي'  ? 'selected' : '' ?>>تحويل بنكي</option>
                                <option value="شيك"         <?= $filterPayment === 'شيك'         ? 'selected' : '' ?>>شيك</option>
                            </select>
                        </div>
                        <div class="col-sm-2 d-flex gap-1">
                            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                            <a href="<?= BASE_PATH ?>/admin/sponsorships.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                        </div>
                    </form>

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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>