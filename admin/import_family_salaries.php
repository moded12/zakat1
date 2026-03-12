<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';

requireAdmin();
$pdo = getDB();

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$message = '';
$error = '';

function normalizeHeader(string $h): string
{
    $h = trim($h);
    $h = str_replace(["\xEF\xBB\xBF"], '', $h); // remove BOM if exists
    return $h;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'رمز الحماية غير صالح';
    } elseif (empty($_FILES['csv']['tmp_name'])) {
        $error = 'يرجى اختيار ملف CSV';
    } else {
        $tmp = $_FILES['csv']['tmp_name'];
        $fh = fopen($tmp, 'r');
        if (!$fh) {
            $error = 'تعذر قراءة الملف';
        } else {
            $pdo->beginTransaction();
            try {
                // Read header
                $header = fgetcsv($fh);
                if (!$header) {
                    throw new RuntimeException('الملف فارغ');
                }

                $header = array_map(fn($h) => normalizeHeader((string)$h), $header);

                $required = ['الرقم', 'الاسم', 'رقم الهوية', 'الهاتف', 'الراتب'];
                foreach ($required as $req) {
                    if (!in_array($req, $header, true)) {
                        throw new RuntimeException('عمود مفقود في الملف: ' . $req);
                    }
                }

                $idx = array_flip($header);

                // Ensure unique index for upsert
                $pdo->exec("ALTER TABLE family_salaries ADD UNIQUE KEY uq_family_salaries_salary_number (salary_number)");

                $stmt = $pdo->prepare("
                    INSERT INTO family_salaries
                      (salary_number, beneficiary_name, beneficiary_id_number, beneficiary_phone, salary_amount, notes, created_at)
                    VALUES
                      (?, ?, ?, ?, ?, NULL, NOW())
                    ON DUPLICATE KEY UPDATE
                      beneficiary_name = VALUES(beneficiary_name),
                      beneficiary_id_number = VALUES(beneficiary_id_number),
                      beneficiary_phone = VALUES(beneficiary_phone),
                      salary_amount = VALUES(salary_amount)
                ");

                $count = 0;
                while (($row = fgetcsv($fh)) !== false) {
                    $salaryNumber = trim((string)($row[$idx['الرقم']] ?? ''));
                    $name = trim((string)($row[$idx['الاسم']] ?? ''));
                    $idNumber = trim((string)($row[$idx['رقم الهوية']] ?? ''));
                    $phone = trim((string)($row[$idx['الهاتف']] ?? ''));
                    $salary = trim((string)($row[$idx['الراتب']] ?? ''));

                    if ($salaryNumber === '' || $name === '') {
                        continue; // skip invalid
                    }

                    $salaryFloat = is_numeric($salary) ? (float)$salary : 0.0;

                    $stmt->execute([$salaryNumber, $name, $idNumber, $phone, $salaryFloat]);
                    $count++;
                }

                fclose($fh);
                $pdo->commit();

                $message = "تم استيراد/تحديث {$count} سجل لرواتب الأسر بنجاح.";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'فشل الاستيراد: ' . $e->getMessage();
            }
        }
    }
}

adminLayoutStart('استيراد رواتب الأسر', 'family_salaries');
?>
<div class="container-fluid py-2">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h2 class="fw-bold mb-1">استيراد رواتب الأسر</h2>
            <div class="text-muted">ملف CSV بالأعمدة: الرقم, الاسم, رقم الهوية, الهاتف, الراتب</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-secondary" href="<?= BASE_PATH ?>/admin/family_salaries.php">عودة لرواتب الأسر</a>
        </div>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm" style="border-radius:18px;">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <?= csrfInput() ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">ملف CSV</label>
                    <input type="file" name="csv" class="form-control" accept=".csv,text/csv">
                </div>
                <button class="btn btn-primary" type="submit">استيراد</button>
            </form>
        </div>
    </div>
</div>
<?php adminLayoutEnd(); ?>