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
$importedCount = 0;
$errorRows = [];

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'رمز الحماية غير صالح';
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'يرجى اختيار ملف CSV صحيح';
    } else {
        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'csv') {
            $error = 'يسمح فقط برفع ملفات CSV';
        } else {
            $handle = fopen($file['tmp_name'], 'r');

            if ($handle === false) {
                $error = 'تعذر قراءة الملف';
            } else {
                $header = fgetcsv($handle);

                if (!$header) {
                    $error = 'الملف فارغ أو غير صالح';
                } else {
                    $header = array_map('trim', $header);
                    $expected = [
                        'file_number',
                        'head_name',
                        'id_number',
                        'members_count',
                        'mobile',
                        'address',
                        'work_status',
                        'income_amount',
                        'need_type',
                        'notes'
                    ];

                    if ($header !== $expected) {
                        $error = 'عناوين الأعمدة غير مطابقة للنموذج المطلوب';
                    } else {
                        $rowNumber = 1;

                        while (($row = fgetcsv($handle)) !== false) {
                            $rowNumber++;

                            if (count($row) < 10) {
                                $errorRows[] = "السطر {$rowNumber}: عدد الأعمدة غير مكتمل";
                                continue;
                            }

                            $row = array_map(fn($v) => trim((string)$v), $row);

                            [
                                $file_number,
                                $head_name,
                                $id_number,
                                $members_count,
                                $mobile,
                                $address,
                                $work_status,
                                $income_amount,
                                $need_type,
                                $notes
                            ] = $row;

                            if ($file_number === '' || $head_name === '') {
                                $errorRows[] = "السطر {$rowNumber}: رقم الملف واسم رب الأسرة مطلوبان";
                                continue;
                            }

                            try {
                                $stmt = $pdo->prepare("
                                    INSERT INTO poor_families
                                    (file_number, head_name, id_number, members_count, mobile, address, work_status, income_amount, need_type, notes, created_at)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                                ");

                                $stmt->execute([
                                    $file_number,
                                    $head_name,
                                    $id_number,
                                    is_numeric($members_count) ? (int)$members_count : 0,
                                    $mobile,
                                    $address,
                                    $work_status,
                                    is_numeric($income_amount) ? (float)$income_amount : 0,
                                    $need_type,
                                    $notes
                                ]);

                                $importedCount++;
                            } catch (Throwable $e) {
                                $errorRows[] = "السطر {$rowNumber}: فشل الإدخال ربما بسبب تكرار أو خطأ في البيانات";
                            }
                        }

                        fclose($handle);

                        if ($importedCount > 0) {
                            $message = "تم استيراد {$importedCount} سجل بنجاح";
                        }

                        if (!$message && $errorRows) {
                            $error = 'لم يتم استيراد أي سجل، تحقق من البيانات';
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>استيراد الأسر الفقيرة</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<style>
body {
    font-family: 'Cairo', sans-serif;
    background: #f4f7fb;
}
.container-box {
    max-width: 900px;
    margin: 40px auto;
}
.card-box {
    border: none;
    border-radius: 18px;
    box-shadow: 0 10px 28px rgba(0,0,0,.08);
}
.template-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 14px;
    padding: 14px;
    font-size: 14px;
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
            <h2 class="fw-bold mb-3">استيراد الأسر الفقيرة من CSV</h2>
            <p class="text-muted">
                قم برفع ملف CSV محفوظ من Excel بصيغة UTF-8، وسيتم إدخال البيانات تلقائيًا إلى قسم الأسر الفقيرة.
            </p>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= e($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="mb-4">
                <?= csrfInput() ?>
                <div class="mb-3">
                    <label class="form-label">ملف CSV</label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary">استيراد الملف</button>
                    <a href="<?= BASE_PATH ?>/admin/poor_families.php" class="btn btn-secondary">العودة إلى الأسر الفقيرة</a>
                </div>
            </form>

            <div class="template-box mb-3">
                <div class="fw-bold mb-2">عناوين الأعمدة المطلوبة داخل الملف:</div>
                <pre>file_number,head_name,id_number,members_count,mobile,address,work_status,income_amount,need_type,notes</pre>
            </div>

            <div class="template-box mb-3">
                <div class="fw-bold mb-2">مثال صفوف:</div>
                <pre>1,محمد أحمد علي,123456789,5,0799123456,حطين,عاطل,100,مواد غذائية,ملاحظة
2,أحمد محمود حسن,987654321,4,0799988776,حطين,عامل يومي,150,نقدي,</pre>
            </div>

            <?php if ($errorRows): ?>
                <div class="alert alert-warning">
                    <div class="fw-bold mb-2">ملاحظات على بعض الصفوف:</div>
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