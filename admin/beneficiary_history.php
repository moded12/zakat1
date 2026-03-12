<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

/**
 * توحيد السايدبار: إذا كان عندك layout.php (الذي أرسلناه سابقًا) ضعه هنا.
 * إذا لم يكن موجودًا بعد، أنشئه أولاً في: admin/includes/layout.php
 */
require_once __DIR__ . '/includes/layout.php';

requireAdmin();

$pdo = getDB();

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * أضفنا family_salaries هنا فقط (مؤقتًا).
 * لاحقًا سنوحّدها بملف registry واحد لكل الصفحات.
 */
function validBeneficiaryType(string $type): bool
{
    return in_array($type, ['poor_families', 'orphans', 'sponsorships', 'family_salaries'], true);
}

function beneficiaryTypeLabel(string $type): string
{
    if ($type === 'poor_families') return 'الأسر الفقيرة';
    if ($type === 'orphans') return 'الأيتام';
    if ($type === 'sponsorships') return 'الكفالات';
    if ($type === 'family_salaries') return 'رواتب الأسر';
    return $type;
}

function beneficiaryTable(string $type): string
{
    if ($type === 'poor_families') return 'poor_families';
    if ($type === 'orphans') return 'orphans';
    if ($type === 'sponsorships') return 'sponsorships';
    return 'family_salaries';
}

function beneficiaryNameColumn(string $type): string
{
    if ($type === 'poor_families') return 'head_name';
    if ($type === 'orphans') return 'name';
    if ($type === 'sponsorships') return 'orphan_name';
    return 'beneficiary_name';
}

function beneficiaryNumberColumn(string $type): string
{
    if ($type === 'sponsorships') return 'sponsorship_number';
    if ($type === 'family_salaries') return 'salary_number';
    return 'file_number';
}

function beneficiaryIdNumberColumn(string $type): string
{
    if ($type === 'sponsorships') return 'beneficiary_id_number';
    if ($type === 'family_salaries') return 'beneficiary_id_number';
    return 'id_number';
}

function beneficiaryPhoneColumn(string $type): string
{
    if ($type === 'poor_families') return 'mobile';
    if ($type === 'orphans') return 'contact_info';
    if ($type === 'sponsorships') return 'beneficiary_phone';
    return 'beneficiary_phone';
}

$type = trim($_GET['type'] ?? 'orphans');
$id = (int)($_GET['id'] ?? 0);

if (!validBeneficiaryType($type)) {
    $type = 'orphans';
}
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$table = beneficiaryTable($type);
$colName = beneficiaryNameColumn($type);
$colNumber = beneficiaryNumberColumn($type);
$colIdNumber = beneficiaryIdNumberColumn($type);
$colPhone = beneficiaryPhoneColumn($type);

/** جلب بيانات المنتفع */
$stmt = $pdo->prepare("
    SELECT id,
           {$colNumber} AS ref_number,
           {$colName} AS full_name,
           {$colIdNumber} AS id_number,
           {$colPhone} AS phone
    FROM {$table}
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$beneficiary = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$beneficiary) {
    header('Location: index.php');
    exit;
}

$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$category = trim($_GET['category'] ?? '');

$categoryList = ['نقد', 'مواد عينية', 'منظفات', 'ملابس', 'أخرى'];

$conditions = [];
$params = [];

$conditions[] = "d.beneficiary_type = ?";
$params[] = $type;

$conditions[] = "i.beneficiary_id = ?";
$params[] = $id;

if ($from !== '') {
    $conditions[] = "d.distribution_date >= ?";
    $params[] = $from;
}
if ($to !== '') {
    $conditions[] = "d.distribution_date <= ?";
    $params[] = $to;
}
if ($category !== '' && in_array($category, $categoryList, true)) {
    $conditions[] = "d.category = ?";
    $params[] = $category;
}

$where = $conditions ? ("WHERE " . implode(" AND ", $conditions)) : "";

$sql = "
    SELECT
        d.id AS distribution_id,
        d.distribution_date,
        d.category,
        d.title,
        d.notes AS distribution_notes,
        d.created_at,
        a.full_name AS admin_name,

        i.cash_amount,
        i.details_text,
        i.notes AS item_notes
    FROM beneficiary_distribution_items i
    INNER JOIN beneficiary_distributions d ON d.id = i.distribution_id
    LEFT JOIN admins a ON a.id = d.created_by
    {$where}
    ORDER BY d.distribution_date DESC, d.id DESC, i.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/** إجماليات سريعة */
$totalCash = 0.0;
foreach ($rows as $r) {
    if ($r['cash_amount'] !== null && $r['cash_amount'] !== '') {
        $totalCash += (float)$r['cash_amount'];
    }
}

adminLayoutStart('سجل الاستلامات', '');
?>
<style>
/* نحافظ على نفس ستايلك مع layout */
.card-box { border:none; border-radius:18px; box-shadow:0 10px 28px rgba(0,0,0,.08); }
.table thead th { background:#eef4ff; }
.muted { color:#6b7280; }
.kv { display:flex; gap:12px; flex-wrap:wrap; }
.kv .item { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:10px 12px; }
</style>

<div class="container py-3">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h2 class="fw-bold mb-1">سجل الاستلامات</h2>
            <div class="muted">يعرض كل ما استلمه المنتفع خلال فترة تحددها</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-primary" href="<?= BASE_PATH ?>/admin/distributions.php?target=<?= e($type) ?>">
                <i class="bi bi-plus-circle ms-1"></i> إضافة توزيعة
            </a>
            <a class="btn btn-secondary" href="javascript:history.back()">رجوع</a>
        </div>
    </div>

    <div class="card card-box mb-3">
        <div class="card-body">
            <div class="kv">
                <div class="item"><strong>القسم:</strong> <?= e(beneficiaryTypeLabel($type)) ?></div>
                <div class="item"><strong>الرقم:</strong> <?= e($beneficiary['ref_number'] ?? '') ?></div>
                <div class="item"><strong>الاسم:</strong> <?= e($beneficiary['full_name'] ?? '') ?></div>
                <div class="item"><strong>الهوية:</strong> <?= e($beneficiary['id_number'] ?? '') ?></div>
                <div class="item"><strong>الهاتف:</strong> <?= e($beneficiary['phone'] ?? '') ?></div>
                <div class="item"><strong>إجمالي النقد ضمن النتائج:</strong> <?= number_format($totalCash, 2) ?></div>
            </div>
        </div>
    </div>

    <div class="card card-box mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="type" value="<?= e($type) ?>">
                <input type="hidden" name="id" value="<?= (int)$id ?>">

                <div class="col-md-3">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">النوع</label>
                    <select name="category" class="form-select">
                        <option value="">الكل</option>
                        <?php foreach ($categoryList as $c): ?>
                            <option value="<?= e($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search ms-1"></i> عرض</button>
                    <a class="btn btn-outline-secondary w-100" href="<?= BASE_PATH ?>/admin/beneficiary_history.php?type=<?= e($type) ?>&id=<?= (int)$id ?>">
                        مسح
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-box">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h4 class="fw-bold mb-0">النتائج</h4>
                <span class="badge bg-primary fs-6"><?= count($rows) ?> سجل</span>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle text-center">
                    <thead>
                        <tr>
                            <th style="width:120px;">التاريخ</th>
                            <th style="width:120px;">النوع</th>
                            <th>العنوان</th>
                            <th style="width:140px;">نقد</th>
                            <th>تفاصيل (غير نقد)</th>
                            <th>ملاحظات</th>
                            <th style="width:160px;">أدخلها</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="7">لا توجد استلامات ضمن هذه الفترة</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= e($r['distribution_date']) ?></td>
                                    <td><?= e($r['category']) ?></td>
                                    <td>
                                        <div class="fw-bold"><?= e($r['title']) ?></div>
                                        <?php if (!empty($r['distribution_notes'])): ?>
                                            <div class="muted" style="font-size:12px;"><?= e($r['distribution_notes']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $r['cash_amount'] !== null && $r['cash_amount'] !== '' ? number_format((float)$r['cash_amount'], 2) : '-' ?></td>
                                    <td style="text-align:right;"><?= !empty($r['details_text']) ? e($r['details_text']) : '-' ?></td>
                                    <td style="text-align:right;"><?= !empty($r['item_notes']) ? e($r['item_notes']) : '-' ?></td>
                                    <td><?= e($r['admin_name'] ?? 'غير معروف') ?></td>
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