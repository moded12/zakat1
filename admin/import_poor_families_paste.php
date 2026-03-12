<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

requireAdmin();

$pdo = getDB();

$importDir = dirname(__DIR__) . '/import/';
if (!is_dir($importDir)) {
    mkdir($importDir, 0775, true);
}

$message = '';
$error = '';
$errorRows = [];
$importedCount = 0;

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function isHeaderRow(array $row): bool
{
    $joined = mb_strtolower(implode(' ', array_map('trim', $row)));
    $keywords = [
        'الرقم',
        'الاسم',
        'الهاتف',
        'الرقم الوطني',
        'إثبات',
        'file_number',
        'head_name',
        'mobile'
    ];

    foreach ($keywords as $keyword) {
        if (mb_strpos($joined, mb_strtolower($keyword)) !== false) {
            return true;
        }
    }

    return false;
}

function importRowsByTarget(PDO $pdo, string $target, array $rows, array &$errorRows, int &$importedCount): void
{
    foreach ($rows as $index => $row) {
        $rowNumber = $index + 1;

        $row = array_values(array_map(fn($v) => trim((string)$v), $row));
        $row = array_pad($row, 10, '');

        if (count(array_filter($row, fn($v) => $v !== '')) === 0) {
            continue;
        }

        if ($index === 0 && isHeaderRow($row)) {
            continue;
        }

        $col1 = $row[0] ?? '';
        $col2 = $row[1] ?? '';
        $col3 = $row[2] ?? '';
        $col4 = $row[3] ?? '';
        $col5 = $row[4] ?? '';
        $col6 = $row[5] ?? '';
        $col7 = $row[6] ?? '';
        $col8 = $row[7] ?? '';
        $col9 = $row[8] ?? '';
        $col10 = $row[9] ?? '';

        if ($col1 === '' || $col2 === '') {
            $errorRows[] = "السطر {$rowNumber}: أول خانتين مطلوبتان على الأقل";
            continue;
        }

        try {
            if ($target === 'poor_families') {
                // يدعم 4 أعمدة أو 10 أعمدة
                $stmt = $pdo->prepare("
                    INSERT INTO poor_families
                    (file_number, head_name, id_number, members_count, mobile, address, work_status, income_amount, need_type, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $col1,
                    $col2,
                    $col3,
                    is_numeric($col4) && $col5 !== '' ? (int)$col4 : 0,
                    is_numeric($col4) && $col5 !== '' ? $col5 : $col4,
                    is_numeric($col4) && $col5 !== '' ? $col6 : '',
                    is_numeric($col4) && $col5 !== '' ? $col7 : '',
                    is_numeric($col4) && $col5 !== '' && is_numeric($col8) ? (float)$col8 : 0,
                    is_numeric($col4) && $col5 !== '' ? $col9 : '',
                    is_numeric($col4) && $col5 !== '' ? $col10 : ''
                ]);
            }

            if ($target === 'orphans') {
                $stmt = $pdo->prepare("
                    INSERT INTO orphans
                    (file_number, name, guardian_name, contact_info, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $col1,
                    $col2,
                    '',
                    $col4 !== '' ? $col4 : '',
                    $col3 !== '' ? 'رقم الهوية / الإثبات: ' . $col3 : ''
                ]);
            }

            if ($target === 'sponsorships') {
                $stmt = $pdo->prepare("
                    INSERT INTO sponsorships
                    (sponsorship_number, orphan_name, sponsor_name, amount, start_date, end_date, status, payment_method, notes, created_at)
                    VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $col1,
                    $col2,
                    '',
                    0,
                    'معلقة',
                    '',
                    'رقم الهوية / الإثبات: ' . $col3 . ' | الهاتف: ' . $col4
                ]);
            }

            $importedCount++;
        } catch (Throwable $e) {
            $errorRows[] = "السطر {$rowNumber}: فشل الإدخال أو يوجد تكرار أو عدم توافق مع بنية الجدول";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'رمز الحماية غير صالح';
    } else {
        $mode = $_POST['mode'] ?? '';
        $target = $_POST['target'] ?? 'poor_families';

        if (!in_array($target, ['poor_families', 'orphans', 'sponsorships'], true)) {
            $target = 'poor_families';
        }

        if ($mode === 'paste') {
            $pastedData = trim($_POST['pasted_data'] ?? '');

            if ($pastedData === '') {
                $error = 'يرجى لصق البيانات أولًا';
            } else {
                $lines = preg_split("/\r\n|\n|\r/", $pastedData);
                $rows = [];

                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }

                    $columns = preg_split("/\t+/", $line);
                    if (count($columns) < 2) {
                        $columns = str_getcsv($line);
                    }
                    $rows[] = $columns;
                }

                if (!$rows) {
                    $error = 'لم يتم العثور على صفوف صالحة';
                } else {
                    importRowsByTarget($pdo, $target, $rows, $errorRows, $importedCount);

                    if ($importedCount > 0) {
                        $message = "تم استيراد {$importedCount} سجل بنجاح";
                    } elseif ($errorRows) {
                        $error = 'لم يتم استيراد أي سجل';
                    }
                }
            }
        }

        if ($mode === 'file') {
            $selectedFile = basename($_POST['import_file'] ?? '');
            $fullPath = $importDir . $selectedFile;

            if ($selectedFile === '' || !is_file($fullPath)) {
                $error = 'الملف المحدد غير موجود';
            } else {
                $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                $rows = [];

                if ($ext === 'csv') {
                    if (($handle = fopen($fullPath, 'r')) !== false) {
                        while (($data = fgetcsv($handle)) !== false) {
                            if (!$data || count(array_filter($data, fn($v) => trim((string)$v) !== '')) === 0) {
                                continue;
                            }
                            $rows[] = $data;
                        }
                        fclose($handle);
                    }
                } else {
                    $lines = file($fullPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }

                        $columns = preg_split("/\t+/", $line);
                        if (count($columns) < 2) {
                            $columns = str_getcsv($line);
                        }
                        $rows[] = $columns;
                    }
                }

                if (!$rows) {
                    $error = 'الملف فارغ أو غير صالح';
                } else {
                    importRowsByTarget($pdo, $target, $rows, $errorRows, $importedCount);

                    if ($importedCount > 0) {
                        $message = "تم استيراد {$importedCount} سجل من الملف: {$selectedFile}";
                    } elseif ($errorRows) {
                        $error = 'لم يتم استيراد أي سجل من الملف';
                    }
                }
            }
        }
    }
}

$files = [];
foreach (scandir($importDir) as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }
    if (is_file($importDir . $file)) {
        $files[] = $file;
    }
}
sort($files);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>أداة الاستيراد العامة</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<style>
body {
    font-family: 'Cairo', sans-serif;
    background: #f4f7fb;
}
.container-box {
    max-width: 1100px;
    margin: 30px auto;
}
.card-box {
    border: none;
    border-radius: 18px;
    box-shadow: 0 10px 28px rgba(0,0,0,.08);
}
.help-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 14px;
    padding: 14px;
    font-size: 14px;
}
textarea {
    min-height: 240px;
    font-family: monospace;
    direction: ltr;
    text-align: left;
}
pre {
    direction: ltr;
    text-align: left;
    background: #111827;
    color: #f9fafb;
    border-radius: 12px;
    padding: 12px;
    overflow: auto;
}
</style>
</head>
<body>
<div class="container container-box">
    <div class="card card-box">
        <div class="card-body p-4">
            <h2 class="fw-bold mb-3">أداة الاستيراد العامة</h2>
            <p class="text-muted">
                يمكنك تحديد وجهة الاستيراد ثم إما لصق البيانات مباشرة من Excel أو اختيار ملف من مجلد import.
            </p>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= e($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="border rounded-4 p-3 h-100 bg-white">
                        <h4 class="fw-bold mb-3">1) نسخ / لصق مباشر من Excel</h4>
                        <form method="POST">
                            <?= csrfInput() ?>
                            <input type="hidden" name="mode" value="paste">

                            <div class="mb-3">
                                <label class="form-label">وجهة الاستيراد</label>
                                <select name="target" class="form-select" required>
                                    <option value="poor_families">الأسر الفقيرة</option>
                                    <option value="orphans">الأيتام</option>
                                    <option value="sponsorships">الكفالات</option>
                                </select>
                            </div>

                            <label class="form-label">ألصق البيانات هنا</label>
                            <textarea name="pasted_data" class="form-control" placeholder="الصق الصفوف المنسوخة من Excel هنا..."></textarea>

                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-primary">استيراد من اللصق</button>
                                <a href="<?= BASE_PATH ?>/admin/poor_families.php" class="btn btn-secondary">العودة</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="border rounded-4 p-3 h-100 bg-white">
                        <h4 class="fw-bold mb-3">2) استيراد من مجلد import</h4>
                        <form method="POST">
                            <?= csrfInput() ?>
                            <input type="hidden" name="mode" value="file">

                            <div class="mb-3">
                                <label class="form-label">وجهة الاستيراد</label>
                                <select name="target" class="form-select" required>
                                    <option value="poor_families">الأسر الفقيرة</option>
                                    <option value="orphans">الأيتام</option>
                                    <option value="sponsorships">الكفالات</option>
                                </select>
                            </div>

                            <label class="form-label">اختر ملفًا من المجلد</label>
                            <select name="import_file" class="form-select">
                                <option value="">-- اختر ملفًا --</option>
                                <?php foreach ($files as $file): ?>
                                    <option value="<?= e($file) ?>"><?= e($file) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-success">استيراد من الملف</button>
                            </div>

                            <div class="mt-3 small text-muted">
                                المجلد المستخدم:
                                <br>
                                <code><?= e($importDir) ?></code>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="help-box mt-4">
                <div class="fw-bold mb-2">صيغة الصورة المدعومة مباشرة:</div>
                <pre class="mb-2">الرقم المتسلسل | الاسم الرباعي | الرقم الوطني / إثبات الشخصية | رقم الهاتف</pre>

                <div class="fw-bold mb-2">مثال للصق مباشر من Excel:</div>
                <pre class="mb-2">81	وعد عامر مصطفى البرقاوي	780161339	07932069350
82	جهاد جمال مسلم المنايطة	T413942	0786074028
83	مرزوق جميل خليل أبو الحاج	9711002610	0795884753</pre>

                <div class="fw-bold mb-2">كما يدعم أيضًا الصيغة الكاملة:</div>
                <pre class="mb-0">file_number | head_name | id_number | members_count | mobile | address | work_status | income_amount | need_type | notes</pre>
            </div>

            <?php if ($errorRows): ?>
                <div class="alert alert-warning mt-4">
                    <div class="fw-bold mb-2">الأخطاء أو الملاحظات:</div>
                    <ul class="mb-0">
                        <?php foreach ($errorRows as $rowError): ?>
                            <li><?= e($rowError) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>