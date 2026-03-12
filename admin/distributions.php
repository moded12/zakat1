<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/beneficiary_helpers.php'; // << تمت الإضافة هنا

requireAdmin();

$pdo = getDB();
$message = '';
$error = '';
$savedDistributionId = 0;

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function currentAdminId()
{
    return !empty($_SESSION['admin_id']) && ctype_digit((string)$_SESSION['admin_id'])
        ? (int)$_SESSION['admin_id']
        : null;
}

// --- تم حذف جميع دوال المستفيدين المكررة هنا ---
function fetchBeneficiaries($pdo, $type, $search = '')
{
    $table = beneficiaryTable($type);
    $colName = beneficiaryNameColumn($type);
    $colNumber = beneficiaryNumberColumn($type);
    $colIdNumber = beneficiaryIdNumberColumn($type);
    $colPhone = beneficiaryPhoneColumn($type);

    $params = array();
    $sql = "SELECT id,
                   {$colNumber} AS ref_number,
                   {$colName} AS full_name,
                   {$colIdNumber} AS id_number,
                   {$colPhone} AS phone
            FROM {$table}";

    if ($search !== '') {
        $keyword = '%' . $search . '%';
        $sql .= " WHERE {$colNumber} LIKE ?
                  OR {$colName} LIKE ?
                  OR {$colIdNumber} LIKE ?
                  OR {$colPhone} LIKE ?";
        $params = array($keyword, $keyword, $keyword, $keyword);
    }

    $sql .= " ORDER BY CAST({$colNumber} AS UNSIGNED) ASC, id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function categoryList()
{
    return array('نقد', 'مواد عينية', 'منظفات', 'ملابس', 'أخرى');
}

function cashPresets()
{
    return array(20, 30, 40, 50);
}

function inKindPresets()
{
    return array('طرد', 'أرز');
}

function detergentsPresets()
{
    return array('منظفات');
}

function normalizePostedDate($value)
{
    $value = trim($value);
    if ($value === '') return '';

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;

    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
        $parts = explode('/', $value);
        if (count($parts) === 3) {
            $m = $parts[0];
            $d = $parts[1];
            $y = $parts[2];
            return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
        }
    }

    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : '';
}

function categoryBadgeClass($category)
{
    if ($category === 'نقد') return 'badge-cash';
    if ($category === 'مواد عينية') return 'badge-kind';
    if ($category === 'منظفات') return 'badge-detergent';
    if ($category === 'ملابس') return 'badge-clothes';
    return 'badge-other';
}

$target = trim(isset($_GET['target']) ? $_GET['target'] : (isset($_POST['beneficiary_type']) ? $_POST['beneficiary_type'] : 'orphans'));
if (!validBeneficiaryType($target)) {
    $target = 'orphans';
}

$search = trim(isset($_GET['search']) ? $_GET['search'] : '');
$categories = categoryList();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $error = 'رمز الحماية غير صالح';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        if ($action === 'create_distribution') {
            $target = trim(isset($_POST['beneficiary_type']) ? $_POST['beneficiary_type'] : 'orphans');
            if (!validBeneficiaryType($target)) $target = 'orphans';

            $distribution_date = normalizePostedDate(isset($_POST['distribution_date']) ? $_POST['distribution_date'] : '');
            $category = trim(isset($_POST['category']) ? $_POST['category'] : '');
            $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
            $notes = trim(isset($_POST['notes']) ? $_POST['notes'] : '');

            $selectedIds = isset($_POST['selected_ids']) ? $_POST['selected_ids'] : array();
            $selectedIds = is_array($selectedIds)
                ? array_values(array_filter($selectedIds, function($v){ return ctype_digit((string)$v) && (int)$v > 0; }))
                : array();

            $cashAmounts = isset($_POST['cash_amount']) ? $_POST['cash_amount'] : array();
            $detailsText = isset($_POST['details_text']) ? $_POST['details_text'] : array();
            $itemNotes = isset($_POST['item_notes']) ? $_POST['item_notes'] : array();

            if ($distribution_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $distribution_date)) {
                $error = 'يرجى إدخال تاريخ صحيح للتوزيعة';
            } elseif ($title === '') {
                $error = 'يرجى إدخال عنوان التوزيعة';
            } elseif (!in_array($category, $categories, true)) {
                $error = 'يرجى اختيار نوع التوزيعة';
            } elseif (!$selectedIds) {
                $error = 'يرجى تحديد مستفيد واحد على الأقل';
            } else {
                if ($category === 'نقد') {
                    foreach ($selectedIds as $bid) {
                        $value = isset($cashAmounts[$bid]) ? $cashAmounts[$bid] : '';
                        if ($value === '' || !is_numeric($value) || (float)$value <= 0) {
                            $error = 'في التوزيع النقدي يجب إدخال مبلغ أكبر من صفر لكل مستفيد محدد';
                            break;
                        }
                    }
                } else {
                    foreach ($selectedIds as $bid) {
                        $value = trim((string)(isset($detailsText[$bid]) ? $detailsText[$bid] : ''));
                        if ($value === '') {
                            $error = 'في التوزيع غير النقدي يجب اختيار/إدخال التفاصيل لكل مستفيد محدد';
                            break;
                        }
                    }
                }
            }

            if ($error === '') {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("
                        INSERT INTO beneficiary_distributions
                        (beneficiary_type, distribution_date, category, title, notes, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute(array(
                        $target,
                        $distribution_date,
                        $category,
                        $title,
                        $notes,
                        currentAdminId()
                    ));

                    $savedDistributionId = (int)$pdo->lastInsertId();
                    if ($savedDistributionId <= 0) {
                        throw new RuntimeException('تعذر حفظ سجل التوزيعة الرئيسي');
                    }

                    $stmtItem = $pdo->prepare("
                        INSERT INTO beneficiary_distribution_items
                        (distribution_id, beneficiary_id, cash_amount, details_text, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");

                    foreach ($selectedIds as $bid) {
                        $cash = null;
                        $details = null;

                        if ($category === 'نقد') {
                            $cash = (float)$cashAmounts[$bid];
                        } else {
                            $details = trim((string)(isset($detailsText[$bid]) ? $detailsText[$bid] : ''));
                        }

                        $note = trim((string)(isset($itemNotes[$bid]) ? $itemNotes[$bid] : ''));

                        $stmtItem->execute(array(
                            $savedDistributionId,
                            (int)$bid,
                            $cash,
                            $details,
                            $note
                        ));
                    }

                    $pdo->commit();
                    $message = 'تم حفظ التوزيعة بنجاح';
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = 'فشل إنشاء التوزيعة: ' . $e->getMessage();
                }
            }
        }
    }
}

$beneficiaries = fetchBeneficiaries($pdo, $target, $search);

$stmtRecent = $pdo->query("
    SELECT d.id, d.beneficiary_type, d.distribution_date, d.category, d.title, d.created_at,
           COUNT(i.id) AS beneficiaries_count
    FROM beneficiary_distributions d
    LEFT JOIN beneficiary_distribution_items i ON i.distribution_id = d.id
    GROUP BY d.id, d.beneficiary_type, d.distribution_date, d.category, d.title, d.created_at
    ORDER BY d.id DESC
    LIMIT 10
");
$recentDistributions = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

$cashPresets = cashPresets();
$inKindPresets = inKindPresets();
$detergentsPresets = detergentsPresets();

adminLayoutStart('إدارة التوزيعات', 'distributions');
?>

<style>
.distributions-page .hero-box{
    background: linear-gradient(135deg, #14345f 0%, #1f4f88 60%, #2b6cb0 100%);
    border-radius: 28px;
    padding: 24px;
    color: #fff;
    box-shadow: 0 16px 40px rgba(20,52,95,.24);
    margin-bottom: 1.25rem;
}
.distributions-page .hero-title{
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: .35rem;
}
.distributions-page .hero-text{
    color: rgba(255,255,255,.88);
    margin-bottom: 0;
}
.distributions-page .card-box {
    border: none;
    border-radius: 22px;
    box-shadow: 0 10px 28px rgba(0,0,0,.08);
}
.distributions-page .table thead th { background: #eef4ff; }
.distributions-page .small-note { font-size: 12px; color: #6b7280; }
.distributions-page .row-selected { background: #eefbf3 !important; }
.distributions-page .btn-history { background: #0ea5e9; color: #fff; border: none; }
.distributions-page .btn-history:hover { background: #0284c7; color: #fff; }
.distributions-page .section-title { font-weight: 800; margin-bottom: 1rem; }
.distributions-page .counter-badge {
    background: #1d4ed8; color: #fff; border-radius: 999px; padding: .45rem .9rem; font-size: .95rem; font-weight: 700;
}
.distributions-page .print-after-save {
    background: #ecfdf5; border: 1px solid #bbf7d0; color: #166534; border-radius: 16px; padding: 14px;
}
.distributions-page .sticky-actions {
    position: sticky; bottom: 0; background: #fff; padding-top: 12px;
}
.distributions-page .preset-box {
    background:#f8fafc; border:1px solid #e5e7eb; border-radius:16px; padding:10px 12px;
}
.distributions-page .recent-card{
    border:1px solid #e5e7eb;
    border-radius:20px;
    padding:16px;
    background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);
    transition:.2s ease;
}
.distributions-page .recent-card:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 24px rgba(15,23,42,.08);
}
.distributions-page .recent-title{
    font-weight:800;
    font-size:1.05rem;
    color:#111827;
    margin-bottom:6px;
}
.distributions-page .recent-meta{
    color:#6b7280;
    font-size:13px;
    margin-bottom:10px;
}
.distributions-page .recent-count{
    font-size:13px;
    font-weight:700;
    color:#111827;
}
.distributions-page .badge-soft{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.distributions-page .badge-cash{ background:#dcfce7; color:#15803d; }
.distributions-page .badge-kind{ background:#dbeafe; color:#1d4ed8; }
.distributions-page .badge-detergent{ background:#ede9fe; color:#7c3aed; }
.distributions-page .badge-clothes{ background:#fce7f3; color:#be185d; }
.distributions-page .badge-other{ background:#f3f4f6; color:#111827; }

@media (max-width: 767.98px){
    .distributions-page .hero-title{ font-size:1.5rem; }
}
</style>

<div class="distributions-page container-fluid py-2">

    <div class="hero-box">
        <div class="row g-3 align-items-center">
            <div class="col-lg-8">
                <div class="hero-title">إدارة التوزيعات</div>
                <p class="hero-text">إنشاء توزيعة جديدة، اختيار المستفيدين، تحديد نوع التوزيع، ثم حفظها وطباعة كشف التوزيعة مباشرة.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <span class="counter-badge">آخر التوزيعات: <?= count($recentDistributions) ?></span>
            </div>
        </div>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <?php if ($savedDistributionId > 0): ?>
        <div class="print-after-save mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="fw-bold">تم حفظ التوزيعة بنجاح</div>
                <div>يمكنك الآن طباعة كشف التوزيعة.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= BASE_PATH ?>/admin/print_distribution_event.php?id=<?= $savedDistributionId ?>" class="btn btn-success" target="_blank">
                    <i class="bi bi-printer ms-1"></i> طباعة التوزيعة
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card card-box">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h3 class="section-title mb-0">توزيعة جديدة</h3>
                        <span class="counter-badge">عدد الأسماء: <?= count($beneficiaries) ?></span>
                    </div>

                    <form method="GET" class="row g-2 align-items-end mb-4">
                        <div class="col-md-4">
                            <label class="form-label">القسم</label>
                            <select name="target" class="form-select" onchange="this.form.submit()">
                                <option value="poor_families" <?= $target === 'poor_families' ? 'selected' : '' ?>>الأسر الفقيرة</option>
                                <option value="orphans" <?= $target === 'orphans' ? 'selected' : '' ?>>الأيتام</option>
                                <option value="sponsorships" <?= $target === 'sponsorships' ? 'selected' : '' ?>>الكفالات</option>
                                <option value="family_salaries" <?= $target === 'family_salaries' ? 'selected' : '' ?>>رواتب الأسر</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">بحث داخل القسم</label>
                            <input type="text" name="search" class="form-control" value="<?= e($search) ?>" placeholder="بحث بالرقم أو الاسم أو الهوية أو الهاتف">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-search ms-1"></i>بحث</button>
                        </div>
                    </form>

                    <form method="POST" onsubmit="return confirm('هل تريد حفظ التوزيعة والاستلامات؟');">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="create_distribution">

                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">القسم</label>
                                <select name="beneficiary_type" class="form-select" required>
                                    <option value="poor_families" <?= $target === 'poor_families' ? 'selected' : '' ?>>الأسر الفقيرة</option>
                                    <option value="orphans" <?= $target === 'orphans' ? 'selected' : '' ?>>الأيتام</option>
                                    <option value="sponsorships" <?= $target === 'sponsorships' ? 'selected' : '' ?>>الكفالات</option>
                                    <option value="family_salaries" <?= $target === 'family_salaries' ? 'selected' : '' ?>>رواتب الأسر</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">تاريخ التوزيعة</label>
                                <input type="date" name="distribution_date" class="form-control" required value="<?= e($_POST['distribution_date'] ?? date('Y-m-d')) ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">النوع</label>
                                <select name="category" class="form-select" required id="categorySelect">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= e($cat) ?>" <?= (($_POST['category'] ?? '') === $cat) ? 'selected' : '' ?>><?= e($cat) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">عنوان</label>
                                <input type="text" name="title" class="form-control" required value="<?= e($_POST['title'] ?? '') ?>" placeholder="مثال: توزيع رمضان 26">
                            </div>

                            <div class="col-12">
                                <label class="form-label">ملاحظات عامة</label>
                                <textarea name="notes" class="form-control" rows="2"><?= e($_POST['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="preset-box mb-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-lg-4">
                                    <label class="form-label mb-1">نقد: Default for all</label>
                                    <div class="d-flex gap-2">
                                        <select class="form-select" id="cashPresetSelect">
                                            <?php foreach ($cashPresets as $p): ?>
                                                <option value="<?= (float)$p ?>" <?= (int)$p === 20 ? 'selected' : '' ?>><?= (int)$p ?></option>
                                            <?php endforeach; ?>
                                            <option value="custom">مبلغ آخر...</option>
                                        </select>
                                        <input type="number" step="0.01" min="0.01" id="cashCustomInput" class="form-control" placeholder="مبلغ آخر" style="display:none; max-width:140px;">
                                        <button type="button" class="btn btn-outline-primary" onclick="applyCashDefaultToAll()">تطبيق</button>
                                    </div>
                                    <div class="small-note mt-1">سيتم تعبئة مبلغ النقد لكل الصفوف.</div>
                                </div>

                                <div class="col-lg-4">
                                    <label class="form-label mb-1">مواد عينية: Default for all</label>
                                    <div class="d-flex gap-2">
                                        <select class="form-select" id="inKindPresetSelect">
                                            <?php foreach ($inKindPresets as $p): ?>
                                                <option value="<?= e($p) ?>" <?= $p === 'طرد' ? 'selected' : '' ?>><?= e($p) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-primary" onclick="applyInKindDefaultToAll()">تطبيق</button>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <label class="form-label mb-1">منظفات: Default for all</label>
                                    <div class="d-flex gap-2">
                                        <select class="form-select" id="detergentsPresetSelect">
                                            <?php foreach ($detergentsPresets as $p): ?>
                                                <option value="<?= e($p) ?>" selected><?= e($p) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-primary" onclick="applyDetergentsDefaultToAll()">تطبيق</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div class="small-note">
                                القسم المختار: <strong><?= e(beneficiaryTypeLabel($target)) ?></strong>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllRows(true)">تحديد الكل</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllRows(false)">إلغاء التحديد</button>
                                <span class="counter-badge" id="selectedCount">المحدد: 0</span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle text-center">
                                <thead>
                                <tr>
                                    <th style="width:50px;"><input type="checkbox" id="checkAll" onclick="toggleAllRows(this.checked)"></th>
                                    <th style="width:90px;">الرقم</th>
                                    <th>الاسم</th>
                                    <th style="width:150px;">الهوية</th>
                                    <th style="width:150px;">الهاتف</th>
                                    <th style="width:160px;" class="cash-col">المبلغ (نقد)</th>
                                    <th class="details-col">التفاصيل (غير نقد)</th>
                                    <th style="width:180px;">ملاحظات</th>
                                    <th style="width:110px;">السجل</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (!$beneficiaries): ?>
                                    <tr><td colspan="9">لا توجد بيانات ضمن هذا القسم</td></tr>
                                <?php else: ?>
                                    <?php foreach ($beneficiaries as $b): ?>
                                        <?php $bid = (int)$b['id']; ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="row-checkbox" name="selected_ids[]" value="<?= $bid ?>" onchange="markRow(this)">
                                            </td>
                                            <td><?= e($b['ref_number'] ?? '') ?></td>
                                            <td><?= e($b['full_name'] ?? '') ?></td>
                                            <td><?= e($b['id_number'] ?? '') ?></td>
                                            <td><?= e($b['phone'] ?? '') ?></td>

                                            <td class="cash-col">
                                                <input type="number"
                                                       step="0.01"
                                                       min="0.01"
                                                       name="cash_amount[<?= $bid ?>]"
                                                       class="form-control form-control-sm cash-input"
                                                       value="20"
                                                       placeholder="0.00">
                                            </td>

                                            <td class="details-col">
                                                <input type="text"
                                                       name="details_text[<?= $bid ?>]"
                                                       class="form-control form-control-sm details-input"
                                                       value=""
                                                       placeholder="مثال: طرد / أرز / منظفات...">
                                            </td>

                                            <td>
                                                <input type="text" name="item_notes[<?= $bid ?>]" class="form-control form-control-sm" placeholder="ملا  ظة">
                                            </td>

                                            <td>
                                                <a href="<?= BASE_PATH ?>/admin/beneficiary_history.php?type=<?= e($target) ?>&id=<?= $bid ?>" class="btn btn-sm btn-history">
                                                    سجل
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="sticky-actions mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="small-note">
                                - عند اختيار <strong>نقد</strong> سيتم اعتماد المبلغ.<br>
                                - عند اختيار <strong>مواد عينية/منظفات/ملابس/أخرى</strong> سيتم اعتماد التفاصيل.
                            </div>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check2-circle ms-1"></i> حفظ التوزيعة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card card-box h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="section-title mb-0">التوزيعات السابقة</h3>
                        <span class="badge bg-dark"><?= count($recentDistributions) ?></span>
                    </div>

                    <?php if (!$recentDistributions): ?>
                        <div class="text-muted">لا توجد توزيعات سابقة حتى الآن.</div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($recentDistributions as $dist): ?>
                                <div class="recent-card">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <div class="recent-title"><?= e($dist['title']) ?></div>
                                        <span class="badge-soft <?= e(categoryBadgeClass($dist['category'])) ?>">
                                            <?= e($dist['category']) ?>
                                        </span>
                                    </div>

                                    <div class="recent-meta">
                                        <?= e(beneficiaryTypeLabel($dist['beneficiary_type'])) ?> •
                                        <?= e($dist['distribution_date']) ?>
                                    </div>

                                    <div class="recent-count mb-3">
                                        عدد المستفيدين: <?= (int)$dist['beneficiaries_count'] ?>
                                    </div>

                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="<?= BASE_PATH ?>/admin/print_distribution_event.php?id=<?= (int)$dist['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                                            <i class="bi bi-printer ms-1"></i> طباعة
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-secondary w-100" disabled>عرض الكل قريبًا</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function updateSelectedCounter() {
    const checked = document.querySelectorAll('.row-checkbox:checked').length;
    const counter = document.getElementById('selectedCount');
    if (counter) counter.textContent = 'المحدد: ' + checked;
}

function markRow(checkbox) {
    const row = checkbox.closest('tr');
    if (row) row.classList.toggle('row-selected', checkbox.checked);
    updateSelectedCounter();
}

function toggleAllRows(state) {
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = state;
        markRow(cb);
    });
    const master = document.getElementById('checkAll');
    if (master) master.checked = state;
    updateSelectedCounter();
}

function updateColumnsVisibility() {
    const category = document.getElementById('categorySelect').value;
    const isCash = category === 'نقد';

    document.querySelectorAll('.cash-col').forEach(el => el.style.display = isCash ? '' : 'none');
    document.querySelectorAll('.details-col').forEach(el => el.style.display = isCash ? 'none' : '');

    if (!isCash) {
        if (category === 'مواد عينية') {
            applyInKindDefaultToAll(true);
        } else if (category === 'منظفات') {
            applyDetergentsDefaultToAll(true);
        }
    }
}

function applyCashDefaultToAll(silent=false) {
    const preset = document.getElementById('cashPresetSelect').value;
    let value = preset;

    if (preset === 'custom') {
        value = document.getElementById('cashCustomInput').value;
    }
    const num = parseFloat(value);
    if (!(num > 0)) {
        if (!silent) alert('يرجى إدخال مبلغ صحيح.');
        return;
    }

    document.querySelectorAll('.cash-input').forEach(inp => inp.value = num.toFixed(2));
}

function applyInKindDefaultToAll(silent=false) {
    const value = document.getElementById('inKindPresetSelect').value;
    if (!value) {
        if (!silent) alert('اختر قيمة للمواد العينية');
        return;
    }
    document.querySelectorAll('.details-input').forEach(inp => {
        if (inp.value.trim() === '' || silent) inp.value = value;
    });
}

function applyDetergentsDefaultToAll(silent=false) {
    const value = document.getElementById('detergentsPresetSelect').value || 'منظفات';
    document.querySelectorAll('.details-input').forEach(inp => {
        if (inp.value.trim() === '' || silent) inp.value = value;
    });
}

document.getElementById('cashPresetSelect').addEventListener('change', function () {
    const custom = document.getElementById('cashCustomInput');
    if (this.value === 'custom') {
        custom.style.display = '';
        custom.focus();
    } else {
        custom.style.display = 'none';
        custom.value = '';
    }
});

document.getElementById('categorySelect').addEventListener('change', updateColumnsVisibility);
updateColumnsVisibility();
updateSelectedCounter();
</script>

<?php adminLayoutEnd(); ?>