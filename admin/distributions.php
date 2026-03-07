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

$search           = trim($_GET['search'] ?? '');
$filterStatus     = trim($_GET['delivery_status'] ?? '');
$filterType       = trim($_GET['assistance_type'] ?? '');
$filterDateFrom   = trim($_GET['date_from'] ?? '');
$filterDateTo     = trim($_GET['date_to'] ?? '');

$conditions = [];
$params     = [];

if ($search !== '') {
    $keyword     = '%' . $search . '%';
    $conditions[] = "(beneficiary_name LIKE ? OR responsible_person LIKE ? OR category_name LIKE ?)";
    array_push($params, $keyword, $keyword, $keyword);
}
if ($filterType !== '') {
    $conditions[] = "assistance_type = ?";
    $params[]     = $filterType;
}
if ($filterStatus !== '') {
    $conditions[] = "delivery_status = ?";
    $params[]     = $filterStatus;
}
if ($filterDateFrom !== '') {
    $conditions[] = "distribution_date >= ?";
    $params[]     = $filterDateFrom;
}
if ($filterDateTo !== '') {
    $conditions[] = "distribution_date <= ?";
    $params[]     = $filterDateTo;
}

$sql = "SELECT * FROM distributions";
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
@media print {
    .sidebar, .no-print { display: none !important; }
    .col-lg-9, .col-xl-10 { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }
    .content { padding: .5rem !important; }
    body { background: #fff !important; }
    .card-box { box-shadow: none !important; border: 1px solid #ccc !important; }
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
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/sponsorships.php"><i class="bi bi-cash-coin ms-2"></i>كفالة الأيتام</a>
                <a class="nav-link active" href="<?= BASE_PATH ?>/admin/distributions.php"><i class="bi bi-box-seam ms-2"></i>التوزيعات</a>
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

            <div class="card card-box mb-4 no-print">
                <div class="card-body">
                    <h4 class="fw-bold mb-3"><?= $editData ? 'تعديل سجل التوزيع' : 'إضافة توزيع جديد' ?></h4>
                    <form method="POST">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= e($editData['id'] ?? '') ?>">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">نوع المساعدة</label>
                                <select name="assistance_type" class="form-select" required>
                                    <option value="">اختر النوع</option>
                                    <?php foreach (['سلة غذائية','مساعدة مالية','ملابس','أدوية','مستلزمات مدرسية','أخرى'] as $opt): ?>
                                        <option value="<?= e($opt) ?>" <?= (($editData['assistance_type'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                        <h4 class="fw-bold mb-0">سجلات التوزيعات
                            <?php if ($search !== '' || $filterStatus !== '' || $filterType !== '' || $filterDateFrom !== '' || $filterDateTo !== ''): ?>
                                <span class="badge bg-info text-dark fs-6 ms-2"><?= count($rows) ?> نتيجة</span>
                            <?php endif; ?>
                        </h4>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                            <i class="bi bi-printer ms-1"></i>طباعة القائمة
                        </button>
                    </div>

                    <!-- Smart Search -->
                    <form method="GET" class="row g-2 mb-3 no-print">
                        <div class="col-sm-3">
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="بحث بالاسم أو الفئة..." value="<?= e($search) ?>">
                        </div>
                        <div class="col-sm-2">
                            <select name="assistance_type" class="form-select form-select-sm">
                                <option value="">نوع المساعدة - الكل</option>
                                <?php foreach (['سلة غذائية','مساعدة مالية','ملابس','أدوية','مستلزمات مدرسية','أخرى'] as $opt): ?>
                                    <option value="<?= e($opt) ?>" <?= $filterType === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <select name="delivery_status" class="form-select form-select-sm">
                                <option value="">حالة التسليم - الكل</option>
                                <option value="تم التسليم"  <?= $filterStatus === 'تم التسليم'  ? 'selected' : '' ?>>تم التسليم</option>
                                <option value="معلق"        <?= $filterStatus === 'معلق'        ? 'selected' : '' ?>>معلق</option>
                                <option value="ملغي"        <?= $filterStatus === 'ملغي'        ? 'selected' : '' ?>>ملغي</option>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <input type="date" name="date_from" class="form-control form-control-sm" title="من تاريخ" value="<?= e($filterDateFrom) ?>">
                        </div>
                        <div class="col-sm-2">
                            <input type="date" name="date_to" class="form-control form-control-sm" title="إلى تاريخ" value="<?= e($filterDateTo) ?>">
                        </div>
                        <div class="col-sm-1 d-flex gap-1">
                            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
                            <a href="<?= BASE_PATH ?>/admin/distributions.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                        </div>
                    </form>

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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>