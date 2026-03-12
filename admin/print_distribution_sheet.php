<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';

requireAdmin();

$pdo = getDB();

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function generateSheetNumber(PDO $pdo): string
{
    $prefix = 'DS-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM distribution_sheets WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $count = (int)$stmt->fetchColumn() + 1;
    return $prefix . str_pad((string)$count, 4, '0', STR_PAD_LEFT);
}

function getSourceTitle(string $source): string
{
    $map = [
        'poor_families' => 'الأسر الفقيرة',
        'orphans' => 'الأيتام',
        'sponsorships' => 'كفالة الأيتام',
        'family_salaries' => 'رواتب الأسر',
    ];
    return $map[$source] ?? 'كشف توزيع';
}

function getHeaders(): array
{
    return ['الرقم', 'الاسم', 'رقم الهوية', 'الهاتف', 'التوقيع'];
}

function fetchListRows(PDO $pdo, string $source, string $search): array
{
    if ($source === 'poor_families') {
        $sql = "SELECT id, file_number, head_name, id_number, mobile
                FROM poor_families
                WHERE 1=1";
        $params = [];
        if ($search !== '') {
            $sql .= " AND (file_number LIKE ? OR head_name LIKE ? OR id_number LIKE ? OR mobile LIKE ?)";
            $keyword = '%' . $search . '%';
            $params = [$keyword, $keyword, $keyword, $keyword];
        }
        $sql .= " ORDER BY CAST(file_number AS UNSIGNED) ASC, id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($source === 'orphans') {
        $sql = "SELECT id, file_number, name, id_number, contact_info
                FROM orphans
                WHERE 1=1";
        $params = [];
        if ($search !== '') {
            $sql .= " AND (file_number LIKE ? OR name LIKE ? OR id_number LIKE ? OR contact_info LIKE ?)";
            $keyword = '%' . $search . '%';
            $params = [$keyword, $keyword, $keyword, $keyword];
        }
        $sql .= " ORDER BY CAST(file_number AS UNSIGNED) ASC, id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($source === 'family_salaries') {
        $sql = "SELECT id, salary_number, beneficiary_name, beneficiary_id_number, beneficiary_phone, salary_amount
                FROM family_salaries
                WHERE 1=1";
        $params = [];
        if ($search !== '') {
            $sql .= " AND (salary_number LIKE ? OR beneficiary_name LIKE ? OR beneficiary_id_number LIKE ? OR beneficiary_phone LIKE ?)";
            $keyword = '%' . $search . '%';
            $params = [$keyword, $keyword, $keyword, $keyword];
        }
        $sql .= " ORDER BY CAST(salary_number AS UNSIGNED) ASC, id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $sql = "SELECT id, sponsorship_number, orphan_name, beneficiary_id_number, beneficiary_phone
            FROM sponsorships
            WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (sponsorship_number LIKE ? OR orphan_name LIKE ? OR beneficiary_id_number LIKE ? OR beneficiary_phone LIKE ?)";
        $keyword = '%' . $search . '%';
        $params = [$keyword, $keyword, $keyword, $keyword];
    }
    $sql .= " ORDER BY CAST(sponsorship_number AS UNSIGNED) ASC, id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchRowsByIds(PDO $pdo, string $source, array $ids): array
{
    if (!$ids) return [];

    $ids = array_values(array_map('intval', $ids));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    if ($source === 'poor_families') {
        $sql = "SELECT id, file_number, head_name, id_number, mobile
                FROM poor_families
                WHERE id IN ($placeholders)
                ORDER BY CAST(file_number AS UNSIGNED) ASC, id ASC";
    } elseif ($source === 'orphans') {
        $sql = "SELECT id, file_number, name, id_number, contact_info
                FROM orphans
                WHERE id IN ($placeholders)
                ORDER BY CAST(file_number AS UNSIGNED) ASC, id ASC";
    } elseif ($source === 'family_salaries') {
        $sql = "SELECT id, salary_number, beneficiary_name, beneficiary_id_number, beneficiary_phone, salary_amount
                FROM family_salaries
                WHERE id IN ($placeholders)
                ORDER BY CAST(salary_number AS UNSIGNED) ASC, id ASC";
    } else {
        $sql = "SELECT id, sponsorship_number, orphan_name, beneficiary_id_number, beneficiary_phone
                FROM sponsorships
                WHERE id IN ($placeholders)
                ORDER BY CAST(sponsorship_number AS UNSIGNED) ASC, id ASC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecordLabel(string $source, array $row): string
{
    if ($source === 'poor_families') return (string)($row['head_name'] ?? '');
    if ($source === 'orphans') return (string)($row['name'] ?? '');
    if ($source === 'family_salaries') return (string)($row['beneficiary_name'] ?? '');
    return (string)($row['orphan_name'] ?? '');
}

function getUnifiedNumber(string $source, array $row): string
{
    if ($source === 'poor_families') return (string)($row['file_number'] ?? '');
    if ($source === 'orphans') return (string)($row['file_number'] ?? '');
    if ($source === 'family_salaries') return (string)($row['salary_number'] ?? '');
    return (string)($row['sponsorship_number'] ?? '');
}

function getUnifiedName(string $source, array $row): string
{
    if ($source === 'poor_families') return (string)($row['head_name'] ?? '');
    if ($source === 'orphans') return (string)($row['name'] ?? '');
    if ($source === 'family_salaries') return (string)($row['beneficiary_name'] ?? '');
    return (string)($row['orphan_name'] ?? '');
}

function getUnifiedIdNumber(string $source, array $row): string
{
    if ($source === 'poor_families') return (string)($row['id_number'] ?? '');
    if ($source === 'orphans') return (string)($row['id_number'] ?? '');
    if ($source === 'family_salaries') return (string)($row['beneficiary_id_number'] ?? '');
    return (string)($row['beneficiary_id_number'] ?? '');
}

function getUnifiedPhone(string $source, array $row): string
{
    if ($source === 'poor_families') return (string)($row['mobile'] ?? '');
    if ($source === 'orphans') return (string)($row['contact_info'] ?? '');
    if ($source === 'family_salaries') return (string)($row['beneficiary_phone'] ?? '');
    return (string)($row['beneficiary_phone'] ?? '');
}

$source = trim($_REQUEST['source'] ?? 'poor_families');
$distribution_type = trim($_REQUEST['distribution_type'] ?? 'نقداً');
$distribution_date = trim($_REQUEST['distribution_date'] ?? date('Y-m-d'));
$search = trim($_REQUEST['search'] ?? '');
$selectedIds = $_REQUEST['selected_ids'] ?? [];
$selectedIds = is_array($selectedIds) ? array_values(array_filter($selectedIds, fn($v) => ctype_digit((string)$v))) : [];
$sheetId = isset($_GET['sheet_id']) && ctype_digit($_GET['sheet_id']) ? (int)$_GET['sheet_id'] : 0;
$printMode = isset($_REQUEST['print_mode']) && $_REQUEST['print_mode'] === '1';
$showRecent = isset($_GET['show_recent']) && $_GET['show_recent'] === '1';

$message = '';
$error = '';

$allowedSources = ['poor_families', 'orphans', 'sponsorships', 'family_salaries'];
if (!in_array($source, $allowedSources, true)) $source = 'poor_families';

$title = getSourceTitle($source);
$headers = getHeaders();
$listRows = [];
$printRows = [];
$sheetData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_sheet') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'رمز الحماية غير صالح';
    } elseif (!$selectedIds) {
        $error = 'يرجى اختيار سجل واحد على الأقل';
    } else {
        try {
            $pdo->beginTransaction();

            $rowsToSave = fetchRowsByIds($pdo, $source, $selectedIds);
            $sheetNumber = generateSheetNumber($pdo);

            $stmt = $pdo->prepare("
                INSERT INTO distribution_sheets
                (sheet_number, source_type, distribution_type, distribution_date, total_records, notes, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $sheetNumber,
                $source,
                $distribution_type,
                $distribution_date,
                count($rowsToSave),
                trim($_POST['notes'] ?? ''),
                $_SESSION['admin_id'] ?? null
            ]);

            $newSheetId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare("
                INSERT INTO distribution_sheet_items
                (sheet_id, record_id, record_label, created_at)
                VALUES (?, ?, ?, NOW())
            ");

            foreach ($rowsToSave as $row) {
                $itemStmt->execute([
                    $newSheetId,
                    (int)$row['id'],
                    getRecordLabel($source, $row)
                ]);
            }

            $pdo->commit();
            header('Location: ' . BASE_PATH . '/admin/print_distribution_sheet.php?sheet_id=' . $newSheetId);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'تعذر حفظ الكشف';
        }
    }
}

if ($sheetId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM distribution_sheets WHERE id = ? LIMIT 1");
    $stmt->execute([$sheetId]);
    $sheetData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sheetData) {
        $source = $sheetData['source_type'];
        $distribution_type = $sheetData['distribution_type'];
        $distribution_date = $sheetData['distribution_date'];
        $title = getSourceTitle($source);

        $stmt = $pdo->prepare("SELECT record_id FROM distribution_sheet_items WHERE sheet_id = ? ORDER BY id ASC");
        $stmt->execute([$sheetId]);
        $savedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $printRows = fetchRowsByIds($pdo, $source, $savedIds);
        $printMode = true;
    } else {
        $error = 'الكشف المطلوب غير موجود';
    }
}

if (!$sheetId) {
    $listRows = fetchListRows($pdo, $source, $search);

    if ($printMode && $selectedIds) {
        $printRows = fetchRowsByIds($pdo, $source, $selectedIds);
    }
}

$perPage = 20;
$totalPrintRows = count($printRows);
$totalPages = max(1, (int)ceil($totalPrintRows / $perPage));

$recentSheets = [];
if (!$printMode && $showRecent) {
    try {
        $recentSheetsStmt = $pdo->query("SELECT * FROM distribution_sheets ORDER BY id DESC LIMIT 10");
        $recentSheets = $recentSheetsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $recentSheets = [];
    }
}

adminLayoutStart('كشوفات الطباعة الموحدة', 'print_sheets');
?>
<style>
.wrapper{ max-width:1200px; margin:0 auto; padding:0; }
.panel{
    background:#fff;
    border-radius:18px;
    padding:18px;
    box-shadow:0 8px 20px rgba(0,0,0,.08);
    margin-bottom:18px;
}
.filter-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr) 220px;
    gap:12px;
    align-items:end;
}
.sheet-label{ display:block; margin-bottom:6px; font-weight:700; font-size:14px; }
.sheet-input, .sheet-select, .sheet-textarea, .sheet-btn{
    width:100%;
    padding:10px 12px;
    border:1px solid #d1d5db;
    border-radius:10px;
    font-family:'Cairo',sans-serif;
    font-size:14px;
    box-sizing:border-box;
}
.sheet-btn{
    background:#1d4f88;
    color:#fff;
    border:none;
    cursor:pointer;
    font-weight:700;
}
.sheet-btn-dark{ background:#111827!important; }
.sheet-btn-green{ background:#15803d!important; }
.list-table,.print-table{ width:100%; border-collapse:collapse; table-layout:fixed; }
.list-table th,.list-table td,
.print-table th,.print-table td{ border:1px solid #1f2937; text-align:center; vertical-align:middle; }
.list-table th,.list-table td{ padding:8px 6px; font-size:13px; }
.list-table th{ background:#eef4ff; }
.print-sheet{
    background:#fff;
    padding:4mm 4mm 3mm;
    box-shadow:none;
    margin:0 0 6px 0;
    break-inside:avoid;
    page-break-inside:avoid;
}
.print-header{ text-align:center; margin-bottom:2mm; }
.print-header h1{ margin:0; font-size:16px; font-weight:800; line-height:1.2; }
.print-header h2{ margin:1px 0 0; font-size:13px; font-weight:800; line-height:1.2; }
.print-meta{
    margin-top:2mm;
    display:flex;
    justify-content:space-between;
    gap:8px;
    font-size:10px;
    font-weight:700;
}
.print-meta div{ white-space:nowrap; }
.print-table th{ background:#eef2f7; font-weight:800; font-size:12px; padding:3px 2px; line-height:1.1; }
.print-table td{ font-size:12px; padding:2px 2px; height:9.6mm; line-height:1.15; }
.print-table .col-index{width:9%}
.print-table .col-name{width:28%}
.print-table .col-id{width:19%}
.print-table .col-phone{width:16%}
.print-table .col-sign{width:28%}
.print-table td.name-cell{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:600; }
.print-table td.signature{ background:#fff; }
.page-number{ text-align:center; margin-top:2mm; font-size:10px; font-weight:700; }
.actions-bar{ display:flex; gap:10px; flex-wrap:wrap; }
.count-badge{
    display:inline-block;
    background:#1d4f88;
    color:#fff;
    padding:6px 12px;
    border-radius:999px;
    font-size:13px;
    font-weight:700;
}
.alert{ padding:12px 14px; border-radius:12px; margin-bottom:14px; }
.alert-success{background:#dcfce7;color:#166534}
.alert-danger{background:#fee2e2;color:#991b1b}
.recent-list a{
    display:block;
    padding:10px 12px;
    border:1px solid #e5e7eb;
    border-radius:10px;
    margin-bottom:8px;
    text-decoration:none;
    color:#111827;
    background:#fafafa;
}
.recent-list a:hover{background:#f3f4f6}
.no-wrap{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

@media (max-width:992px){
    .filter-grid{grid-template-columns:1fr}
}
@media print{
    .no-print{display:none!important}
    .print-sheet{ margin:0!important; box-shadow:none!important; }
    @page{ size:A4 portrait; margin:4mm }
}
</style>

<div class="wrapper">

    <?php if ($message): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="panel no-print">
        <form method="GET" id="filterForm">
            <div class="filter-grid">
                <div>
                    <label class="sheet-label">مصدر الكشف</label>
                    <select class="sheet-select" name="source" onchange="document.getElementById('filterForm').submit();">
                        <option value="poor_families" <?= $source === 'poor_families' ? 'selected' : '' ?>>الأسر الفقيرة</option>
                        <option value="orphans" <?= $source === 'orphans' ? 'selected' : '' ?>>الأيتام</option>
                        <option value="sponsorships" <?= $source === 'sponsorships' ? 'selected' : '' ?>>كفالة الأيتام</option>
                        <option value="family_salaries" <?= $source === 'family_salaries' ? 'selected' : '' ?>>رواتب الأسر</option>
                    </select>
                </div>
                <div>
                    <label class="sheet-label">نوع التوزيعة</label>
                    <select class="sheet-select" name="distribution_type">
                        <option value="نقداً" <?= $distribution_type === 'نقداً' ? 'selected' : '' ?>>نقداً</option>
                        <option value="مواد" <?= $distribution_type === 'مواد' ? 'selected' : '' ?>>مواد</option>
                    </select>
                </div>
                <div>
                    <label class="sheet-label">تاريخ التوزيعة</label>
                    <input class="sheet-input" type="date" name="distribution_date" value="<?= e($distribution_date) ?>">
                </div>
                <div>
                    <label class="sheet-label">بحث</label>
                    <input class="sheet-input" type="text" name="search" value="<?= e($search) ?>" placeholder="بحث في السجلات">
                </div>
                <div class="actions-bar">
                    <button class="sheet-btn" type="submit">عرض السجلات</button>
                </div>
            </div>

            <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <span class="count-badge">قائمة “آخر الكشوف” مخفية افتراضيًا</span>
                <a class="count-badge" style="text-decoration:none; background:#111827;"
                   href="<?= BASE_PATH ?>/admin/print_distribution_sheet.php?show_recent=1&source=<?= e($source) ?>">
                    عرض آخر الكشوف (اختياري)
                </a>
            </div>
        </form>
    </div>

    <?php if (!$printMode): ?>

        <?php if ($showRecent): ?>
            <div class="panel no-print">
                <h3 style="margin-top:0;">آخر الكشوف المحفوظة</h3>
                <div class="recent-list">
                    <?php if (!$recentSheets): ?>
                        <div>لا توجد كشوف محفوظة بعد</div>
                    <?php else: ?>
                        <?php foreach ($recentSheets as $sheet): ?>
                            <a href="<?= BASE_PATH ?>/admin/print_distribution_sheet.php?sheet_id=<?= (int)$sheet['id'] ?>">
                                <?= e($sheet['sheet_number']) ?> —
                                <?= e(getSourceTitle($sheet['source_type'])) ?> —
                                <?= e($sheet['distribution_type']) ?> —
                                <?= e($sheet['distribution_date']) ?> —
                                عدد السجلات: <?= (int)$sheet['total_records'] ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="no-print">
            <div class="panel">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                    <h3 style="margin:0;">اختر السجلات لإنشاء كشف محفوظ - <?= e($title) ?></h3>
                    <span class="count-badge">عدد السجلات: <?= count($listRows) ?></span>
                </div>

                <?= csrfInput() ?>
                <input type="hidden" name="action" value="save_sheet">
                <input type="hidden" name="source" value="<?= e($source) ?>">
                <input type="hidden" name="distribution_type" value="<?= e($distribution_type) ?>">
                <input type="hidden" name="distribution_date" value="<?= e($distribution_date) ?>">
                <input type="hidden" name="search" value="<?= e($search) ?>">

                <div style="margin-bottom:12px; display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="button" class="sheet-btn sheet-btn-dark" onclick="toggleAll(true)">تحديد الكل</button>
                    <button type="button" class="sheet-btn sheet-btn-dark" onclick="toggleAll(false)">إلغاء التحديد</button>
                    <button type="submit" class="sheet-btn sheet-btn-green">حفظ الكشف</button>
                </div>

                <div style="margin-bottom:12px;">
                    <label class="sheet-label">ملاحظات الكشف</label>
                    <textarea class="sheet-textarea" name="notes" rows="2" placeholder="ملاحظات إضافية على الكشف إن وجدت"></textarea>
                </div>

                <div style="overflow-x:auto;">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th style="width:70px;">اختيار</th>
                                <th style="width:80px;">الرقم</th>
                                <th>الاسم</th>
                                <th style="width:170px;">رقم الهوية</th>
                                <th style="width:150px;">الهاتف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$listRows): ?>
                                <tr><td colspan="5">لا توجد سجلات</td></tr>
                            <?php else: ?>
                                <?php foreach ($listRows as $row): ?>
                                    <tr>
                                        <td><input type="checkbox" class="row-check" name="selected_ids[]" value="<?= (int)$row['id'] ?>"></td>
                                        <td class="no-wrap"><?= e(getUnifiedNumber($source, $row)) ?></td>
                                        <td><?= e(getUnifiedName($source, $row)) ?></td>
                                        <td class="no-wrap"><?= e(getUnifiedIdNumber($source, $row)) ?></td>
                                        <td class="no-wrap"><?= e(getUnifiedPhone($source, $row)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

    <?php else: ?>

        <div class="panel no-print" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <div>
                <?php if ($sheetData): ?>
                    <strong>رقم الكشف:</strong> <?= e($sheetData['sheet_number']) ?> |
                    <strong>عدد السجلات:</strong> <?= (int)$sheetData['total_records'] ?>
                <?php else: ?>
                    <strong>معاينة كشف غير محفوظ</strong>
                <?php endif; ?>
            </div>
            <div class="actions-bar">
                <button type="button" class="sheet-btn sheet-btn-dark" onclick="window.print()">طباعة</button>
                <a href="<?= BASE_PATH ?>/admin/print_distribution_sheet.php" style="text-decoration:none;">
                    <button type="button" class="sheet-btn">كشف جديد</button>
                </a>
            </div>
        </div>

        <?php if (!$printRows): ?>
            <div class="print-sheet">
                <div class="print-header">
                    <h1>لجنة زكاة مخيم حطين</h1>
                    <h2>كشف توزيع - <?= e($title) ?></h2>
                    <div class="print-meta">
                        <div>تاريخ التوزيعة: <?= e($distribution_date) ?></div>
                        <div>نوع التوزيعة: <?= e($distribution_type) ?></div>
                    </div>
                </div>
                <p style="text-align:center;">لا توجد بيانات في هذا الكشف</p>
            </div>
        <?php else: ?>
            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                <?php
                $offset = ($page - 1) * $perPage;
                $pageRows = array_slice($printRows, $offset, $perPage);
                ?>
                <div class="print-sheet">
                    <div class="print-header">
                        <h1>لجنة زكاة مخيم حطين</h1>
                        <h2>كشف توزيع - <?= e($title) ?></h2>
                        <div class="print-meta">
                            <div>
                                تاريخ التوزيعة: <?= e($distribution_date) ?>
                                <?php if ($sheetData): ?>
                                    | رقم الكشف: <?= e($sheetData['sheet_number']) ?>
                                <?php endif; ?>
                            </div>
                            <div>نوع التوزيعة: <?= e($distribution_type) ?></div>
                        </div>
                    </div>

                    <table class="print-table">
                        <thead>
                            <tr>
                                <th class="col-index"><?= e($headers[0]) ?></th>
                                <th class="col-name"><?= e($headers[1]) ?></th>
                                <th class="col-id"><?= e($headers[2]) ?></th>
                                <th class="col-phone"><?= e($headers[3]) ?></th>
                                <th class="col-sign"><?= e($headers[4]) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pageRows as $index => $row): ?>
                                <tr>
                                    <td><?= $offset + $index + 1 ?></td>
                                    <td class="name-cell"><?= e(getUnifiedName($source, $row)) ?></td>
                                    <td><?= e(getUnifiedIdNumber($source, $row)) ?></td>
                                    <td><?= e(getUnifiedPhone($source, $row)) ?></td>
                                    <td class="signature"></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php for ($i = count($pageRows); $i < $perPage; $i++): ?>
                                <tr>
                                    <td></td>
                                    <td class="name-cell"></td>
                                    <td></td>
                                    <td></td>
                                    <td class="signature"></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>

                    <div class="page-number">
                        الصفحة <?= $page ?> من <?= $totalPages ?>
                    </div>
                </div>
            <?php endfor; ?>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
function toggleAll(state) {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = state);
}
</script>

<?php adminLayoutEnd(); ?>