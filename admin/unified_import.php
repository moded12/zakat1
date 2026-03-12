<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';

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
$previewRows = [];
$previewTarget = '';
$previewMode = '';
$previewPayload = '';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function validImportTarget(string $target): bool
{
    return in_array($target, ['poor_families', 'orphans', 'sponsorships', 'family_salaries'], true);
}

function targetLabel(string $target): string
{
    if ($target === 'poor_families') return 'الأسر الفقيرة';
    if ($target === 'orphans') return 'الأيتام';
    if ($target === 'sponsorships') return 'الكفالات';
    if ($target === 'family_salaries') return 'رواتب الأسر';
    return $target;
}

function isHeaderRow(array $row): bool
{
    $joined = mb_strtolower(implode(' ', array_map('trim', $row)));
    $keywords = [
        'الرقم',
        'الاسم',
        'رقم الهوية',
        'الهوية',
        'الهاتف',
        'الراتب',
        'reference_number',
        'full_name',
        'id_number',
        'phone_number',
        'salary_amount'
    ];

    foreach ($keywords as $keyword) {
        if (mb_strpos($joined, mb_strtolower($keyword)) !== false) {
            return true;
        }
    }

    return false;
}

function isNumericOnly(string $value): bool
{
    return preg_match('/^\d+$/', trim($value)) === 1;
}

function extractNumericSequence(?string $value): int
{
    $value = trim((string)$value);
    if ($value === '') {
        return 0;
    }

    if (preg_match('/(\d+)$/', $value, $matches)) {
        return (int)$matches[1];
    }

    return 0;
}

function getNumberColumnName(string $target): string
{
    if ($target === 'poor_families') return 'file_number';
    if ($target === 'orphans') return 'file_number';
    if ($target === 'family_salaries') return 'salary_number';
    return 'sponsorship_number';
}

function getTableName(string $target): string
{
    if ($target === 'poor_families') return 'poor_families';
    if ($target === 'orphans') return 'orphans';
    if ($target === 'family_salaries') return 'family_salaries';
    return 'sponsorships';
}

function generateNextNumber(PDO $pdo, string $target): string
{
    $table = getTableName($target);
    $column = getNumberColumnName($target);

    $stmt = $pdo->query("SELECT {$column} FROM {$table}");
    $values = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $max = 0;
    foreach ($values as $value) {
        $num = extractNumericSequence((string)$value);
        if ($num > $max) {
            $max = $num;
        }
    }

    return (string)($max + 1);
}

function resequenceTarget(PDO $pdo, string $target): void
{
    $table = getTableName($target);
    $column = getNumberColumnName($target);

    $stmt = $pdo->query("SELECT id FROM {$table} ORDER BY id ASC");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $update = $pdo->prepare("UPDATE {$table} SET {$column} = ? WHERE id = ?");
    $counter = 1;

    foreach ($ids as $id) {
        $update->execute([(string)$counter, (int)$id]);
        $counter++;
    }
}

function normalizeImportedRow(array $row, string $target): array
{
    $row = array_values(array_map(fn($v) => trim((string)$v), $row));
    $row = array_pad($row, 5, '');

    if ($target === 'family_salaries') {
        if ($row[0] !== '' && isNumericOnly($row[0]) && $row[1] !== '') {
            return [
                'full_name'    => $row[1] ?? '',
                'id_number'    => $row[2] ?? '',
                'phone_number' => $row[3] ?? '',
                'salary_amount'=> $row[4] ?? ''
            ];
        }

        if ($row[0] !== '' && $row[1] !== '' && $row[2] !== '' && $row[3] !== '') {
            return [
                'full_name'    => $row[0] ?? '',
                'id_number'    => $row[1] ?? '',
                'phone_number' => $row[2] ?? '',
                'salary_amount'=> $row[3] ?? ''
            ];
        }

        return [
            'full_name'    => $row[1] !== '' ? $row[1] : ($row[0] ?? ''),
            'id_number'    => $row[2] ?? '',
            'phone_number' => $row[3] ?? '',
            'salary_amount'=> $row[4] ?? ''
        ];
    }

    if ($row[0] !== '' && isNumericOnly($row[0]) && $row[1] !== '') {
        return [
            'full_name'    => $row[1] ?? '',
            'id_number'    => $row[2] ?? '',
            'phone_number' => $row[3] ?? '',
            'salary_amount'=> ''
        ];
    }

    if ($row[0] !== '' && $row[1] !== '' && $row[2] !== '' && $row[3] === '') {
        return [
            'full_name'    => $row[0] ?? '',
            'id_number'    => $row[1] ?? '',
            'phone_number' => $row[2] ?? '',
            'salary_amount'=> ''
        ];
    }

    return [
        'full_name'    => $row[1] !== '' ? $row[1] : ($row[0] ?? ''),
        'id_number'    => $row[2] ?? '',
        'phone_number' => $row[3] ?? '',
        'salary_amount'=> ''
    ];
}

function parsePastedTextToRows(string $pastedData): array
{
    $lines = preg_split("/\r\n|\n|\r/", trim($pastedData));
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

    return $rows;
}

function parseImportFile(string $fullPath): array
{
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
        return $rows;
    }

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

    return $rows;
}

function buildPreviewRows(PDO $pdo, string $target, array $rows): array
{
    $preview = [];
    $nextNumber = (int)generateNextNumber($pdo, $target);

    foreach ($rows as $index => $row) {
        if (!$row || count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
            continue;
        }

        if ($index === 0 && isHeaderRow($row)) {
            continue;
        }

        $normalized = normalizeImportedRow($row, $target);
        $full_name = trim($normalized['full_name'] ?? '');
        $id_number = trim($normalized['id_number'] ?? '');
        $phone_number = trim($normalized['phone_number'] ?? '');
        $salary_amount = trim((string)($normalized['salary_amount'] ?? ''));

        if ($full_name === '' && $id_number === '' && $phone_number === '' && $salary_amount === '') {
            continue;
        }

        $hasError = false;
        $errorText = '';

        if ($full_name === '') {
            $hasError = true;
            $errorText = 'الاسم مطلوب';
        } elseif ($target === 'family_salaries' && ($salary_amount === '' || !is_numeric($salary_amount))) {
            $hasError = true;
            $errorText = 'الراتب مطلوب ويجب أن يكون رقمًا';
        }

        $preview[] = [
            'line_no' => $index + 1,
            'number' => (string)$nextNumber,
            'name' => $full_name,
            'id_number' => $id_number,
            'phone' => $phone_number,
            'salary_amount' => $salary_amount,
            'has_error' => $hasError,
            'error' => $errorText
        ];

        $nextNumber++;
    }

    return $preview;
}

function importRowsByTarget(PDO $pdo, string $target, array $rows, array &$errorRows, int &$importedCount): void
{
    foreach ($rows as $index => $row) {
        $rowNumber = $index + 1;

        if (!$row || count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
            continue;
        }

        if ($index === 0 && isHeaderRow($row)) {
            continue;
        }

        $normalized = normalizeImportedRow($row, $target);

        $reference_number = generateNextNumber($pdo, $target);

        $full_name = trim($normalized['full_name'] ?? '');
        $id_number = trim($normalized['id_number'] ?? '');
        $phone_number = trim($normalized['phone_number'] ?? '');
        $salary_amount = trim((string)($normalized['salary_amount'] ?? ''));

        if ($full_name === '') {
            $errorRows[] = "السطر {$rowNumber}: الاسم مطلوب";
            continue;
        }

        if ($target === 'family_salaries' && ($salary_amount === '' || !is_numeric($salary_amount))) {
            $errorRows[] = "السطر {$rowNumber}: الراتب مطلوب ويجب أن يكون رقمًا";
            continue;
        }

        try {
            if ($target === 'poor_families') {
                $stmt = $pdo->prepare("
                    INSERT INTO poor_families
                    (file_number, head_name, id_number, mobile, members_count, address, work_status, income_amount, need_type, notes, created_at)
                    VALUES (?, ?, ?, ?, 0, '', '', 0, '', '', NOW())
                ");
                $stmt->execute([
                    $reference_number,
                    $full_name,
                    $id_number,
                    $phone_number
                ]);
            }

            if ($target === 'orphans') {
                $stmt = $pdo->prepare("
                    INSERT INTO orphans
                    (file_number, name, id_number, contact_info, birth_date, gender, mother_name, guardian_name, address, education_status, health_status, notes, created_at)
                    VALUES (?, ?, ?, ?, NULL, '', '', '', '', '', '', '', NOW())
                ");
                $stmt->execute([
                    $reference_number,
                    $full_name,
                    $id_number,
                    $phone_number
                ]);
            }

            if ($target === 'sponsorships') {
                $stmt = $pdo->prepare("
                    INSERT INTO sponsorships
                    (sponsorship_number, orphan_name, beneficiary_id_number, beneficiary_phone, sponsor_name, amount, start_date, end_date, status, payment_method, notes, created_at)
                    VALUES (?, ?, ?, ?, '', 0, NULL, NULL, 'معلقة', '', '', NOW())
                ");
                $stmt->execute([
                    $reference_number,
                    $full_name,
                    $id_number,
                    $phone_number
                ]);
            }

            if ($target === 'family_salaries') {
                $stmt = $pdo->prepare("
                    INSERT INTO family_salaries
                    (salary_number, beneficiary_name, beneficiary_id_number, beneficiary_phone, salary_amount)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $reference_number,
                    $full_name,
                    $id_number,
                    $phone_number,
                    (float)$salary_amount
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

        if (!validImportTarget($target)) {
            $target = 'poor_families';
        }

        if ($mode === 'preview_paste') {
            $pastedData = trim($_POST['pasted_data'] ?? '');
            if ($pastedData === '') {
                $error = 'يرجى لصق البيانات أولًا';
            } else {
                $rows = parsePastedTextToRows($pastedData);
                if (!$rows) {
                    $error = 'لم يتم العثور على صفوف صالحة';
                } else {
                    $previewRows = buildPreviewRows($pdo, $target, $rows);
                    $previewTarget = $target;
                    $previewMode = 'paste';
                    $previewPayload = $pastedData;
                }
            }
        }

        if ($mode === 'confirm_paste_import') {
            $pastedData = trim($_POST['payload_data'] ?? '');
            if ($pastedData === '') {
                $error = 'لا توجد بيانات معتمدة للاستيراد';
            } else {
                $rows = parsePastedTextToRows($pastedData);
                if (!$rows) {
                    $error = 'لم يتم العثور على صفوف صالحة';
                } else {
                    $pdo->beginTransaction();
                    try {
                        importRowsByTarget($pdo, $target, $rows, $errorRows, $importedCount);

                        if ($importedCount > 0) {
                            resequenceTarget($pdo, $target);
                        }

                        $pdo->commit();

                        if ($importedCount > 0) {
                            $message = "تم استيراد {$importedCount} سجل بنجاح إلى قسم " . targetLabel($target) . " مع إعادة ترتيب الأرقام";
                        } elseif ($errorRows) {
                            $error = 'لم يتم استيراد أي سجل';
                        }
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $error = 'حدث خطأ أثناء الاستيراد';
                    }
                }
            }
        }

        if ($mode === 'preview_file') {
            $selectedFile = basename($_POST['import_file'] ?? '');
            $fullPath = $importDir . $selectedFile;

            if ($selectedFile === '' || !is_file($fullPath)) {
                $error = 'الملف المحدد غير موجود';
            } else {
                $rows = parseImportFile($fullPath);
                if (!$rows) {
                    $error = 'الملف فارغ أو غير صالح';
                } else {
                    $previewRows = buildPreviewRows($pdo, $target, $rows);
                    $previewTarget = $target;
                    $previewMode = 'file';
                    $previewPayload = $selectedFile;
                }
            }
        }

        if ($mode === 'confirm_file_import') {
            $selectedFile = basename($_POST['payload_data'] ?? '');
            $fullPath = $importDir . $selectedFile;

            if ($selectedFile === '' || !is_file($fullPath)) {
                $error = 'الملف المحدد غير موجود';
            } else {
                $rows = parseImportFile($fullPath);
                if (!$rows) {
                    $error = 'الملف فارغ أو غير صالح';
                } else {
                    $pdo->beginTransaction();
                    try {
                        importRowsByTarget($pdo, $target, $rows, $errorRows, $importedCount);

                        if ($importedCount > 0) {
                            resequenceTarget($pdo, $target);
                        }

                        $pdo->commit();

                        if ($importedCount > 0) {
                            $message = "تم استيراد {$importedCount} سجل من الملف: {$selectedFile} إلى قسم " . targetLabel($target) . " مع إعادة ترتيب الأرقام";
                        } elseif ($errorRows) {
                            $error = 'لم يتم استيراد أي سجل من الملف';
                        }
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $error = 'حدث خطأ أثناء الاستيراد من الملف';
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

adminLayoutStart('الاستيراد الجماعي الموحد', 'import');
?>
<style>
.card-box { border: none; border-radius: 18px; box-shadow: 0 10px 28px rgba(0,0,0,.08); }
.help-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 14px; padding: 14px; font-size: 14px; }
textarea { min-height: 240px; font-family: monospace; direction: ltr; text-align: left; }
pre { direction: ltr; text-align: left; background: #111827; color: #f9fafb; border-radius: 12px; padding: 12px; overflow: auto; }
.preview-table th { background: #eef4ff; }
.preview-error { background: #fff1f2; }
.small-note { font-size: 13px; color: #6b7280; }
</style>

<div class="container-fluid py-2">
    <div class="card card-box">
        <div class="card-body p-4">
            <h2 class="fw-bold mb-3">الاستيراد الجماعي الموحد</h2>
            <p class="text-muted">الاستيراد متوافق مع الترقيم التسلسلي: بعد الاستيراد يعاد ترتيب الأرقام بدون قفز، مع دعم رواتب الأسر أيضًا.</p>

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
                            <input type="hidden" name="mode" value="preview_paste">

                            <div class="mb-3">
                                <label class="form-label">وجهة الاستيراد</label>
                                <select name="target" class="form-select" required>
                                    <option value="poor_families">الأسر الفقيرة</option>
                                    <option value="orphans">الأيتام</option>
                                    <option value="sponsorships">الكفالات</option>
                                    <option value="family_salaries">رواتب الأسر</option>
                                </select>
                            </div>

                            <label class="form-label">ألصق البيانات هنا</label>
                            <textarea name="pasted_data" class="form-control" placeholder="الصق الصفوف المنسوخة من Excel هنا..."></textarea>

                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-primary">معاينة قبل الاستيراد</button>
                                <a href="<?= BASE_PATH ?>/admin/index.php" class="btn btn-secondary">العودة</a>
                            </div>
                            <div class="small-note mt-2">
                                للأقسام العادية: ترقيم | الاسم | رقم الهوية | الهاتف<br>
                                لرواتب الأسر: ترقيم | الاسم | رقم الهوية | الهاتف | الراتب
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="border rounded-4 p-3 h-100 bg-white">
                        <h4 class="fw-bold mb-3">2) استيراد من مجلد import</h4>
                        <form method="POST">
                            <?= csrfInput() ?>
                            <input type="hidden" name="mode" value="preview_file">

                            <div class="mb-3">
                                <label class="form-label">وجهة الاستيراد</label>
                                <select name="target" class="form-select" required>
                                    <option value="poor_families">الأسر الفقيرة</option>
                                    <option value="orphans">الأيتام</option>
                                    <option value="sponsorships">الكفالات</option>
                                    <option value="family_salaries">رواتب الأسر</option>
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
                                <button type="submit" class="btn btn-success">معاينة الملف</button>
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
                <div class="fw-bold mb-2">الصيغة المعتمدة للأسر/الأيتام/الكفالات:</div>
                <pre class="mb-2">ترقيم | الاسم | رقم الهوية | الهاتف</pre>
                <div class="fw-bold mb-2">أو:</div>
                <pre class="mb-2">الاسم | رقم الهوية | الهاتف</pre>

                <div class="fw-bold mb-2">الصيغة المعتمدة لرواتب الأسر:</div>
                <pre class="mb-2">ترقيم | الاسم | رقم الهوية | الهاتف | الراتب</pre>
                <div class="fw-bold mb-2">أو:</div>
                <pre class="mb-2">الاسم | رقم الهوية | الهاتف | الراتب</pre>

                <div class="fw-bold mb-2">مهم:</div>
                <div>بعد تأكيد الاستيراد سيتم إعادة ترتيب الأرقام بالكامل بدون فجوات.</div>
            </div>

            <?php if ($previewRows): ?>
                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h4 class="fw-bold mb-0">معاينة قبل الاستيراد - <?= e(targetLabel($previewTarget)) ?></h4>
                        <span class="badge bg-primary fs-6"><?= count($previewRows) ?> صف</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle text-center preview-table">
                            <thead>
                                <tr>
                                    <th>السطر</th>
                                    <th>الرقم الجديد</th>
                                    <th>الاسم</th>
                                    <th>رقم الهوية</th>
                                    <th>الهاتف</th>
                                    <?php if ($previewTarget === 'family_salaries'): ?>
                                        <th>الراتب</th>
                                    <?php endif; ?>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($previewRows as $row): ?>
                                    <tr class="<?= $row['has_error'] ? 'preview-error' : '' ?>">
                                        <td><?= (int)$row['line_no'] ?></td>
                                        <td><?= e($row['number']) ?></td>
                                        <td><?= e($row['name']) ?></td>
                                        <td><?= e($row['id_number']) ?></td>
                                        <td><?= e($row['phone']) ?></td>
                                        <?php if ($previewTarget === 'family_salaries'): ?>
                                            <td><?= e($row['salary_amount']) ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <?php if ($row['has_error']): ?>
                                                <span class="badge bg-danger"><?= e($row['error']) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success">جاهز</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" class="mt-3 d-flex gap-2 flex-wrap">
                        <?= csrfInput() ?>
                        <input type="hidden" name="target" value="<?= e($previewTarget) ?>">
                        <input type="hidden" name="payload_data" value="<?= e($previewPayload) ?>">
                        <input type="hidden" name="mode" value="<?= $previewMode === 'file' ? 'confirm_file_import' : 'confirm_paste_import' ?>">
                        <button type="submit" class="btn btn-success">تأكيد الاستيراد</button>
                        <a href="<?= BASE_PATH ?>/admin/unified_import.php" class="btn btn-outline-secondary">إلغاء المعاينة</a>
                    </form>
                </div>
            <?php endif; ?>

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

<?php adminLayoutEnd(); ?>