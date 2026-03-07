<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

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
        'sponsorships' => 'كفالة الأيتام'
    ];
    return $map[$source] ?? 'كشف توزيع';
}

function getHeadersBySource(string $source): array
{
    if ($source === 'poor_families') {
        return ['م', 'رقم الملف', 'الاسم', 'رقم الهوية', 'رقم الهاتف', 'عدد الأفراد', 'التوقيع'];
    }
    if ($source === 'orphans') {
        return ['م', 'رقم الملف', 'اسم اليتيم', 'الوصي', 'رقم الهاتف', 'ملاحظات', 'التوقيع'];
    }
    return ['م', 'رقم الكفالة', 'اسم اليتيم', 'اسم الكافل', 'المبلغ', 'الحالة', 'التوقيع'];
}

function fetchListRows(PDO $pdo, string $source, string $search): array
{
    if ($source === 'poor_families') {
        $sql = "SELECT id, file_number, head_name, id_number, mobile, members_count
                FROM poor_families
                WHERE 1=1";
        $params = [];
        if ($search !== '') {
            $sql .= " AND (file_number LIKE ? OR head_name LIKE ? OR mobile LIKE ? OR id_number LIKE ?)";
            $keyword = '%' . $search . '%';
            $params = [$keyword, $keyword, $keyword, $keyword];
        }
        $sql .= " ORDER BY id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($source === 'orphans') {
        $sql = "SELECT id, file_number, name, guardian_name, contact_info
                FROM orphans
                WHERE 1=1";
        $params = [];
        if ($search !== '') {
            $sql .= " AND (file_number LIKE ? OR name LIKE ? OR guardian_name LIKE ? OR contact_info LIKE ?)";
            $keyword = '%' . $search . '%';
            $params = [$keyword, $keyword, $keyword, $keyword];
        }
        $sql .= " ORDER BY id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $sql = "SELECT id, sponsorship_number, orphan_name, sponsor_name, amount, status
            FROM sponsorships
            WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (sponsorship_number LIKE ? OR orphan_name LIKE ? OR sponsor_name LIKE ? OR status LIKE ?)";
        $keyword = '%' . $search . '%';
        $params = [$keyword, $keyword, $keyword, $keyword];
    }
    $sql .= " ORDER BY id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchRowsByIds(PDO $pdo, string $source, array $ids): array
{
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    if ($source === 'poor_families') {
        $sql = "SELECT id, file_number, head_name, id_number, mobile, members_count
                FROM poor_families
                WHERE id IN ($placeholders)
                ORDER BY id ASC";
    } elseif ($source === 'orphans') {
        $sql = "SELECT id, file_number, name, guardian_name, contact_info
                FROM orphans
                WHERE id IN ($placeholders)
                ORDER BY id ASC";
    } else {
        $sql = "SELECT id, sponsorship_number, orphan_name, sponsor_name, amount, status
                FROM sponsorships
                WHERE id IN ($placeholders)
                ORDER BY id ASC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecordLabel(string $source, array $row): string
{
    if ($source === 'poor_families') {
        return $row['head_name'] ?? '';
    }
    if ($source === 'orphans') {
        return $row['name'] ?? '';
    }
    return $row['orphan_name'] ?? '';
}

$source = trim($_REQUEST['source'] ?? 'poor_families');
$distribution_type = trim($_REQUEST['distribution_type'] ?? 'نقداً');
$distribution_date = trim($_REQUEST['distribution_date'] ?? date('Y-m-d'));
$search = trim($_REQUEST['search'] ?? '');
$selectedIds = $_REQUEST['selected_ids'] ?? [];
$selectedIds = is_array($selectedIds) ? array_values(array_filter($selectedIds, fn($v) => ctype_digit((string)$v))) : [];
$sheetId = isset($_GET['sheet_id']) && ctype_digit($_GET['sheet_id']) ? (int)$_GET['sheet_id'] : 0;
$printMode = isset($_REQUEST['print_mode']) && $_REQUEST['print_mode'] === '1';
$message = '';
$error = '';

$allowedSources = ['poor_families', 'orphans', 'sponsorships'];
if (!in_array($source, $allowedSources, true)) {
    $source = 'poor_families';
}

$title = getSourceTitle($source);
$headers = getHeadersBySource($source);
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
            $pdo->rollBack();
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
        $headers = getHeadersBySource($source);

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

$recentSheetsStmt = $pdo->query("SELECT * FROM distribution_sheets ORDER BY id DESC LIMIT 10");
$recentSheets = $recentSheetsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>كشوفات التوزيع المحفوظة</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
body{font-family:'Cairo',sans-serif;background:#f3f4f6;margin:0;color:#111827}
.wrapper{max-width:1200px;margin:20px auto;padding:20px}
.panel{background:#fff;border-radius:16px;padding:18px;box-shadow:0 8px 20px rgba(0,0,0,.08);margin-bottom:18px}
.filter-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;align-items:end}
label{display:block;margin-bottom:6px;font-weight:700;font-size:14px}
input,select,textarea,button{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-family:'Cairo',sans-serif;font-size:14px}
button{background:#1d4f88;color:#fff;border:none;cursor:pointer;font-weight:700}
.btn-dark{background:#111827!important}
.btn-green{background:#15803d!important}
.btn-orange{background:#c2410c!important}
.list-table,.print-table{width:100%;border-collapse:collapse;table-layout:fixed}
.list-table th,.list-table td,.print-table th,.print-table td{border:1px solid #000;text-align:center;vertical-align:middle}
.list-table th,.list-table td{padding:8px 6px;font-size:13px}
.list-table th{background:#eef4ff}
.print-sheet{background:#fff;margin-bottom:10px;padding:8px 10px 6px;box-shadow:0 10px 25px rgba(0,0,0,.08)}
.print-header{text-align:center;margin-bottom:5px}
.print-header h1{margin:0;font-size:20px;font-weight:800}
.print-header h2{margin:3px 0 0;font-size:15px;font-weight:800}
.print-meta{margin-top:4px;display:flex;justify-content:space-between;font-size:11px;font-weight:700}
.print-table th{background:#f3f4f6;font-weight:800;font-size:13px;height:22px;padding:1px 2px}
.print-table td{font-size:13px;height:24px;padding:1px 2px}
.print-table td.signature{height:24px}
.page-number{text-align:center;margin-top:4px;font-size:11px;font-weight:700}
.actions-bar{display:flex;gap:10px;flex-wrap:wrap}
.count-badge{display:inline-block;background:#1d4f88;color:#fff;padding:6px 12px;border-radius:999px;font-size:13px;font-weight:700}
.alert{padding:12px 14px;border-radius:12px;margin-bottom:14px}
.alert-success{background:#dcfce7;color:#166534}
.alert-danger{background:#fee2e2;color:#991b1b}
.recent-list a{display:block;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:8px;text-decoration:none;color:#111827;background:#fafafa}
.recent-list a:hover{background:#f3f4f6}
@media (max-width:992px){.filter-grid{grid-template-columns:1fr}}
@media print{
    .no-print{display:none!important}
    body{background:#fff}
    .wrapper{max-width:100%;margin:0;padding:0}
    .print-sheet{box-shadow:none;margin:0;padding:5mm 5mm 3mm;min-height:286mm;page-break-inside:avoid}
    .print-sheet:not(:last-child){page-break-after:always;break-after:page}
    .print-sheet:last-child{page-break-after:auto;break-after:auto}
    .print-header h1{font-size:17px}
    .print-header h2{font-size:14px}
    .print-meta{font-size:11px}
    .print-table th{font-size:14px;height:20px}
    .print-table td{font-size:14px;height:11.5mm;padding:0}
    .print-table td.signature{height:11.5mm}
    .page-number{font-size:11px}
    @page{size:A4 portrait;margin:4mm}
}
</style>
</head>
<body>
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
                    <label>مصدر الكشف</label>
                    <select name="source" onchange="document.getElementById('filterForm').submit();">
                        <option value="poor_families" <?= $source === 'poor_families' ? 'selected' : '' ?>>الأسر الفقيرة</option>
                        <option value="orphans" <?= $source === 'orphans' ? 'selected' : '' ?>>الأيتام</option>
                        <option value="sponsorships" <?= $source === 'sponsorships' ? 'selected' : '' ?>>كفالة الأيتام</option>
                    </select>
                </div>
                <div>
                    <label>نوع التوزيعة</label>
                    <select name="distribution_type">
                        <option value="نقداً" <?= $distribution_type === 'نقداً' ? 'selected' : '' ?>>نقداً</option>
                        <option value="مواد" <?= $distribution_type === 'مواد' ? 'selected' : '' ?>>مواد</option>
                    </select>
                </div>
                <div>
                    <label>تاريخ التوزيعة</label>
                    <input type="date" name="distribution_date" value="<?= e($distribution_date) ?>">
                </div>
                <div>
                    <label>بحث</label>
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="بحث في السجلات">
                </div>
                <div class="actions-bar">
                    <button type="submit">عرض السجلات</button>
                </div>
            </div>
        </form>
    </div>

    <?php if (!$printMode): ?>
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
                    <button type="button" class="btn-dark" onclick="toggleAll(true)">تحديد الكل</button>
                    <button type="button" class="btn-dark" onclick="toggleAll(false)">إلغاء التحديد</button>
                    <button type="submit" class="btn-green">حفظ الكشف</button>
                </div>

                <div style="margin-bottom:12px;">
                    <label>ملاحظات الكشف</label>
                    <textarea name="notes" rows="2" placeholder="ملاحظات إضافية على الكشف إن وجدت"></textarea>
                </div>

                <div style="overflow-x:auto;">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th style="width:60px;">اختيار</th>
                                <th style="width:70px;">م</th>
                                <?php if ($source === 'poor_families'): ?>
                                    <th>رقم الملف</th><th>الاسم</th><th>رقم الهوية</th><th>رقم الهاتف</th><th>عدد الأفراد</th>
                                <?php elseif ($source === 'orphans'): ?>
                                    <th>رقم الملف</th><th>اسم اليتيم</th><th>الوصي</th><th>رقم الهاتف</th>
                                <?php else: ?>
                                    <th>رقم الكفالة</th><th>اسم اليتيم</th><th>اسم الكافل</th><th>المبلغ</th><th>الحالة</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$listRows): ?>
                                <tr><td colspan="8">لا توجد سجلات</td></tr>
                            <?php else: ?>
                                <?php foreach ($listRows as $index => $row): ?>
                                    <tr>
                                        <td><input type="checkbox" class="row-check" name="selected_ids[]" value="<?= (int)$row['id'] ?>"></td>
                                        <td><?= $index + 1 ?></td>
                                        <?php if ($source === 'poor_families'): ?>
                                            <td><?= e($row['file_number']) ?></td>
                                            <td><?= e($row['head_name']) ?></td>
                                            <td><?= e($row['id_number'] ?? '') ?></td>
                                            <td><?= e($row['mobile']) ?></td>
                                            <td><?= e($row['members_count']) ?></td>
                                        <?php elseif ($source === 'orphans'): ?>
                                            <td><?= e($row['file_number']) ?></td>
                                            <td><?= e($row['name']) ?></td>
                                            <td><?= e($row['guardian_name']) ?></td>
                                            <td><?= e($row['contact_info']) ?></td>
                                        <?php else: ?>
                                            <td><?= e($row['sponsorship_number']) ?></td>
                                            <td><?= e($row['orphan_name']) ?></td>
                                            <td><?= e($row['sponsor_name']) ?></td>
                                            <td><?= e($row['amount']) ?></td>
                                            <td><?= e($row['status']) ?></td>
                                        <?php endif; ?>
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
                <button type="button" class="btn-dark" onclick="window.print()">طباعة</button>
                <a href="<?= BASE_PATH ?>/admin/print_distribution_sheet.php" style="text-decoration:none;"><button type="button">كشف جديد</button></a>
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
                <?php $offset = ($page - 1) * $perPage; $pageRows = array_slice($printRows, $offset, $perPage); ?>
                <div class="print-sheet">
                    <div class="print-header">
                        <h1>لجنة زكاة مخيم حطين</h1>
                        <h2>كشف توزيع - <?= e($title) ?></h2>
                        <div class="print-meta">
                            <div>
                                تاريخ التوزيعة: <?= e($distribution_date) ?>
                                <?php if ($sheetData): ?> | رقم الكشف: <?= e($sheetData['sheet_number']) ?><?php endif; ?>
                            </div>
                            <div>نوع التوزيعة: <?= e($distribution_type) ?></div>
                        </div>
                    </div>

                    <table class="print-table">
                        <thead>
                            <tr>
                                <?php foreach ($headers as $head): ?>
                                    <th><?= e($head) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pageRows as $index => $row): ?>
                                <tr>
                                    <?php if ($source === 'poor_families'): ?>
                                        <td><?= $offset + $index + 1 ?></td>
                                        <td><?= e($row['file_number']) ?></td>
                                        <td><?= e($row['head_name']) ?></td>
                                        <td><?= e($row['id_number'] ?? '') ?></td>
                                        <td><?= e($row['mobile']) ?></td>
                                        <td><?= e($row['members_count']) ?></td>
                                        <td class="signature"></td>
                                    <?php elseif ($source === 'orphans'): ?>
                                        <td><?= $offset + $index + 1 ?></td>
                                        <td><?= e($row['file_number']) ?></td>
                                        <td><?= e($row['name']) ?></td>
                                        <td><?= e($row['guardian_name']) ?></td>
                                        <td><?= e($row['contact_info']) ?></td>
                                        <td></td>
                                        <td class="signature"></td>
                                    <?php else: ?>
                                        <td><?= $offset + $index + 1 ?></td>
                                        <td><?= e($row['sponsorship_number']) ?></td>
                                        <td><?= e($row['orphan_name']) ?></td>
                                        <td><?= e($row['sponsor_name']) ?></td>
                                        <td><?= e($row['amount']) ?></td>
                                        <td><?= e($row['status']) ?></td>
                                        <td class="signature"></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>

                            <?php for ($i = count($pageRows); $i < $perPage; $i++): ?>
                                <tr>
                                    <td><?= $offset + $i + 1 ?></td>
                                    <td></td><td></td><td></td><td></td><td></td><td class="signature"></td>
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
</body>
</html>