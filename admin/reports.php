<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$pdo = getDB();

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$section = $_GET['section'] ?? 'poor_families';
$search = trim($_GET['search'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

$allowedSections = [
    'poor_families' => [
        'title' => 'تقرير الأسر الفقيرة',
        'table' => 'poor_families',
        'columns' => ['file_number', 'head_name', 'members_count', 'mobile', 'need_type', 'created_at'],
        'labels' => ['رقم الملف', 'اسم رب الأسرة', 'عدد الأفراد', 'الجوال', 'نوع الاحتياج', 'تاريخ الإضافة'],
        'searchable' => ['file_number', 'head_name', 'mobile', 'address', 'need_type']
    ],
    'orphans' => [
        'title' => 'تقرير الأيتام',
        'table' => 'orphans',
        'columns' => ['file_number', 'name', 'birth_date', 'gender', 'guardian_name', 'contact_info', 'created_at'],
        'labels' => ['رقم الملف', 'الاسم', 'تاريخ الميلاد', 'الجنس', 'الوصي', 'التواصل', 'تاريخ الإضافة'],
        'searchable' => ['file_number', 'name', 'mother_name', 'guardian_name', 'contact_info', 'address']
    ],
    'sponsorships' => [
        'title' => 'تقرير كفالة الأيتام',
        'table' => 'sponsorships',
        'columns' => ['sponsorship_number', 'orphan_name', 'sponsor_name', 'amount', 'status', 'payment_method', 'created_at'],
        'labels' => ['رقم الكفالة', 'اسم اليتيم', 'اسم الكافل', 'المبلغ', 'الحالة', 'طريقة الدفع', 'تاريخ الإضافة'],
        'searchable' => ['sponsorship_number', 'orphan_name', 'sponsor_name', 'status', 'payment_method']
    ],
    'distributions' => [
        'title' => 'تقرير التوزيعات',
        'table' => 'distributions',
        'columns' => ['assistance_type', 'beneficiary_name', 'category_name', 'distribution_date', 'quantity_or_amount', 'delivery_status', 'responsible_person'],
        'labels' => ['نوع المساعدة', 'اسم المستفيد', 'الفئة', 'تاريخ التوزيع', 'الكمية أو المبلغ', 'حالة التسليم', 'المسؤول'],
        'searchable' => ['assistance_type', 'beneficiary_name', 'category_name', 'delivery_status', 'responsible_person']
    ],
];

if (!isset($allowedSections[$section])) {
    $section = 'poor_families';
}

$config = $allowedSections[$section];
$sql = "SELECT * FROM {$config['table']} WHERE 1=1";
$params = [];

if ($search !== '') {
    $searchParts = [];
    foreach ($config['searchable'] as $column) {
        $searchParts[] = "{$column} LIKE ?";
        $params[] = '%' . $search . '%';
    }
    if ($searchParts) {
        $sql .= " AND (" . implode(' OR ', $searchParts) . ")";
    }
}

if ($date_from !== '' && $date_to !== '') {
    if ($section === 'distributions') {
        $sql .= " AND distribution_date BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
    } else {
        $sql .= " AND DATE(created_at) BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
    }
} elseif ($date_from !== '') {
    if ($section === 'distributions') {
        $sql .= " AND distribution_date >= ?";
        $params[] = $date_from;
    } else {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $date_from;
    }
} elseif ($date_to !== '') {
    if ($section === 'distributions') {
        $sql .= " AND distribution_date <= ?";
        $params[] = $date_to;
    } else {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $date_to;
    }
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRows = count($rows);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>التقارير</title>
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
    .sidebar,
    .no-print,
    .btn,
    form {
        display: none !important;
    }
    .content {
        width: 100% !important;
        padding: 0 !important;
    }
    body {
        background: white;
    }
    .card-box {
        box-shadow: none !important;
        border: 1px solid #ddd;
    }
}
</style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-lg-3 col-xl-2 sidebar no-print">
            <h4 class="fw-bold mb-4"><i class="bi bi-heart-fill text-warning ms-2"></i>إدارة الزكاة</h4>
            <nav class="nav flex-column">
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/index.php">لوحة التحكم</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/poor_families.php">الأسر الفقيرة</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/orphans.php">الأيتام</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/sponsorships.php">كفالة الأيتام</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/distributions.php">التوزيعات</a>
                <a class="nav-link active" href="<?= BASE_PATH ?>/admin/reports.php">التقارير</a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/logout.php">تسجيل الخروج</a>
            </nav>
        </aside>

        <main class="col-lg-9 col-xl-10 content">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h1 class="fw-bold mb-1">التقارير والإحصائيات</h1>
                    <p class="text-muted mb-0"><?= e($config['title']) ?></p>
                </div>
                <div class="no-print">
                    <button onclick="window.print()" class="btn btn-dark">
                        <i class="bi bi-printer ms-1"></i> طباعة
                    </button>
                </div>
            </div>

            <div class="card card-box mb-4 no-print">
                <div class="card-body">
                    <form method="GET">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">القسم</label>
                                <select name="section" class="form-select">
                                    <option value="poor_families" <?= $section === 'poor_families' ? 'selected' : '' ?>>الأسر الفقيرة</option>
                                    <option value="orphans" <?= $section === 'orphans' ? 'selected' : '' ?>>الأيتام</option>
                                    <option value="sponsorships" <?= $section === 'sponsorships' ? 'selected' : '' ?>>كفالة الأيتام</option>
                                    <option value="distributions" <?= $section === 'distributions' ? 'selected' : '' ?>>التوزيعات</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">بحث</label>
                                <input type="text" name="search" class="form-control" value="<?= e($search) ?>" placeholder="كلمة بحث">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">من تاريخ</label>
                                <input type="date" name="date_from" class="form-control" value="<?= e($date_from) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">إلى تاريخ</label>
                                <input type="date" name="date_to" class="form-control" value="<?= e($date_to) ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">عرض التقرير</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-box">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h4 class="fw-bold mb-0"><?= e($config['title']) ?></h4>
                        <span class="badge bg-primary fs-6">عدد النتائج: <?= $totalRows ?></span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle text-center">
                            <thead>
                                <tr>
                                    <?php foreach ($config['labels'] as $label): ?>
                                        <th><?= e($label) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$rows): ?>
                                    <tr>
                                        <td colspan="<?= count($config['labels']) ?>">لا توجد نتائج</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $row): ?>
                                        <tr>
                                            <?php foreach ($config['columns'] as $column): ?>
                                                <td><?= e($row[$column] ?? '') ?></td>
                                            <?php endforeach; ?>
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