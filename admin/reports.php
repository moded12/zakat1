<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

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
        'searchable' => ['file_number', 'head_name', 'mobile', 'address', 'need_type'],
        'date_column' => 'created_at',
        'order_by' => 'CAST(file_number AS UNSIGNED) ASC, id ASC'
    ],
    'orphans' => [
        'title' => 'تقرير الأيتام',
        'table' => 'orphans',
        'columns' => ['file_number', 'name', 'birth_date', 'gender', 'guardian_name', 'contact_info', 'created_at'],
        'labels' => ['رقم الملف', 'الاسم', 'تاريخ الميلاد', 'الجنس', 'الوصي', 'التواصل', 'تاريخ الإضافة'],
        'searchable' => ['file_number', 'name', 'mother_name', 'guardian_name', 'contact_info', 'address'],
        'date_column' => 'created_at',
        'order_by' => 'CAST(file_number AS UNSIGNED) ASC, id ASC'
    ],
    'sponsorships' => [
        'title' => 'تقرير كفالة الأيتام',
        'table' => 'sponsorships',
        'columns' => ['sponsorship_number', 'orphan_name', 'sponsor_name', 'amount', 'status', 'payment_method', 'created_at'],
        'labels' => ['رقم الكفالة', 'اسم اليتيم', 'اسم الكافل', 'المبلغ', 'الحالة', 'طريقة الدفع', 'تاريخ الإضافة'],
        'searchable' => ['sponsorship_number', 'orphan_name', 'sponsor_name', 'status', 'payment_method'],
        'date_column' => 'created_at',
        'order_by' => 'CAST(sponsorship_number AS UNSIGNED) ASC, id ASC'
    ],
    'family_salaries' => [
        'title' => 'تقرير رواتب الأسر',
        'table' => 'family_salaries',
        'columns' => ['salary_number', 'beneficiary_name', 'beneficiary_id_number', 'beneficiary_phone', 'salary_amount'],
        'labels' => ['رقم الراتب', 'الاسم', 'رقم الهوية', 'الهاتف', 'الراتب'],
        'searchable' => ['salary_number', 'beneficiary_name', 'beneficiary_id_number', 'beneficiary_phone'],
        'date_column' => '',
        'order_by' => 'CAST(salary_number AS UNSIGNED) ASC, id ASC'
    ],
    'distributions' => [
        'title' => 'تقرير التوزيعات',
        'table' => 'beneficiary_distributions',
        'columns' => ['id', 'beneficiary_type', 'category', 'title', 'distribution_date', 'created_at'],
        'labels' => ['رقم التوزيعة', 'القسم', 'النوع', 'العنوان', 'تاريخ التوزيع', 'تاريخ الإنشاء'],
        'searchable' => ['beneficiary_type', 'category', 'title', 'notes'],
        'date_column' => 'distribution_date',
        'order_by' => 'id DESC'
    ],
];

if (!isset($allowedSections[$section])) {
    $section = 'poor_families';
}

function sectionLabel(string $value): string
{
    if ($value === 'poor_families') return 'الأسر الفقيرة';
    if ($value === 'orphans') return 'الأيتام';
    if ($value === 'sponsorships') return 'كفالة الأيتام';
    if ($value === 'family_salaries') return 'رواتب الأسر';
    return $value;
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

$dateColumn = $config['date_column'] ?? '';
if ($dateColumn !== '') {
    if ($date_from !== '' && $date_to !== '') {
        $sql .= " AND DATE({$dateColumn}) BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
    } elseif ($date_from !== '') {
        $sql .= " AND DATE({$dateColumn}) >= ?";
        $params[] = $date_from;
    } elseif ($date_to !== '') {
        $sql .= " AND DATE({$dateColumn}) <= ?";
        $params[] = $date_to;
    }
}

$sql .= " ORDER BY {$config['order_by']}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRows = count($rows);

adminLayoutStart('التقارير', 'reports');
?>
<style>
.card-box {
    border: none;
    border-radius: 18px;
    box-shadow: 0 10px 28px rgba(0,0,0,.08);
}
.table thead th {
    background: #eef4ff;
}
.report-summary {
    background: linear-gradient(135deg, #111827, #1f2937);
    color: #fff;
    border-radius: 18px;
    padding: 1rem 1.2rem;
    margin-bottom: 1rem;
}
@media print {
    .no-print,
    .mobile-topbar,
    .sidebar,
    .sidebar-backdrop {
        display: none !important;
    }
    .content {
        padding: 0 !important;
    }
    body {
        background: #fff !important;
    }
    .card-box {
        box-shadow: none !important;
        border: 1px solid #ddd;
    }
}
</style>

<div class="container-fluid py-2">
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

    <div class="report-summary">
        <div class="fw-bold mb-1">ملخص التقرير</div>
        <div>
            القسم الحالي: <?= e($config['title']) ?> |
            عدد النتائج: <?= $totalRows ?>
            <?php if ($search !== ''): ?>
                | البحث: <?= e($search) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card card-box mb-4 no-print">
        <div class="card-body">
            <form method="GET" id="reportFilterForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">القسم</label>
                        <select name="section" class="form-select" onchange="document.getElementById('reportFilterForm').submit()">
                            <option value="poor_families" <?= $section === 'poor_families' ? 'selected' : '' ?>>الأسر الفقيرة</option>
                            <option value="orphans" <?= $section === 'orphans' ? 'selected' : '' ?>>الأيتام</option>
                            <option value="sponsorships" <?= $section === 'sponsorships' ? 'selected' : '' ?>>كفالة الأيتام</option>
                            <option value="family_salaries" <?= $section === 'family_salaries' ? 'selected' : '' ?>>رواتب الأسر</option>
                            <option value="distributions" <?= $section === 'distributions' ? 'selected' : '' ?>>التوزيعات</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">بحث</label>
                        <input type="text" name="search" class="form-control" value="<?= e($search) ?>" placeholder="كلمة بحث">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">من تاريخ</label>
                        <input type="date" name="date_from" class="form-control" value="<?= e($date_from) ?>" <?= $dateColumn === '' ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">إلى تاريخ</label>
                        <input type="date" name="date_to" class="form-control" value="<?= e($date_to) ?>" <?= $dateColumn === '' ? 'disabled' : '' ?>>
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
                                        <?php
                                        $value = $row[$column] ?? '';
                                        if ($section === 'distributions' && $column === 'beneficiary_type') {
                                            $value = sectionLabel((string)$value);
                                        }
                                        ?>
                                        <td><?= e($value) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php adminLayoutEnd(); ?>