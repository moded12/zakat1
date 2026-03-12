<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/beneficiary_helpers.php';
require_once __DIR__ . '/includes/whatsapp_helpers.php';

requireAdmin();

$pdo = getDB();
$message = '';
$error = '';

// الأقسام المدعومة
$sections = array(
    'poor_families' => 'الأسر الفقيرة',
    'orphans'       => 'الأيتام',
    'sponsorships'  => 'الكفالات',
    'family_salaries'=> 'رواتب الأسر'
);

// --- معالجة طلب الإرسال ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'send_mass') {
        $distribution_id = isset($_POST['distribution_id']) ? (int)$_POST['distribution_id'] : 0;
        $section = isset($_POST['section']) ? $_POST['section'] : '';
        $customMsg = isset($_POST['custom_msg']) ? trim($_POST['custom_msg']) : '';
        $selected = isset($_POST['selected']) ? $_POST['selected'] : array();

        $table       = beneficiaryTable($section);
        $colName     = beneficiaryNameColumn($section);
        $colPhone    = beneficiaryPhoneColumn($section);

        // جلب المستفيدين بالتوزيعة المختارة
        $sql = "
            SELECT b.id AS beneficiary_id, b.{$colName} AS full_name, b.{$colPhone} AS phone, i.cash_amount, i.details_text
            FROM beneficiary_distribution_items i
            INNER JOIN {$table} b ON b.id = i.beneficiary_id
            WHERE i.distribution_id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($distribution_id));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sentCount = 0;
        foreach ($rows as $row) {
            if (!in_array($row['beneficiary_id'], $selected)) continue;

            $phone = $row['phone'];
            $name  = $row['full_name'];

            if (!$phone) continue;

            // تحل placeholders في الرسالة
            $msg = $customMsg;
            $msg = str_replace('[الاسم]', $name, $msg);
            $msg = str_replace('[المبلغ]', isset($row['cash_amount']) ? $row['cash_amount'] : '', $msg);
            $msg = str_replace('[التفاصيل]', isset($row['details_text']) ? $row['details_text'] : '', $msg);

            $result = sendWhatsAppMsg($phone, $msg);
            if ($result) $sentCount++;
        }

        $message = "تم إرسال {$sentCount} رسالة واتساب بنجاح.";
    }

    if ($action === 'send_single') {
        // إرسال فردي لمستفيد واحد
        $beneficiary_id   = isset($_POST['beneficiary_id']) ? (int)$_POST['beneficiary_id'] : 0;
        $distribution_id  = isset($_POST['distribution_id']) ? (int)$_POST['distribution_id'] : 0;
        $section          = isset($_POST['section']) ? $_POST['section'] : '';
        $customMsg        = isset($_POST['custom_msg']) ? trim($_POST['custom_msg']) : '';

        $table   = beneficiaryTable($section);
        $colName = beneficiaryNameColumn($section);
        $colPhone= beneficiaryPhoneColumn($section);

        $sql = "
            SELECT b.id AS beneficiary_id, b.{$colName} AS full_name, b.{$colPhone} AS phone, i.cash_amount, i.details_text
            FROM beneficiary_distribution_items i
            INNER JOIN {$table} b ON b.id = i.beneficiary_id
            WHERE i.distribution_id = ? AND b.id = ?
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($distribution_id, $beneficiary_id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['phone']) {
            $msg = $customMsg;
            $msg = str_replace('[الاسم]', $row['full_name'], $msg);
            $msg = str_replace('[المبلغ]', isset($row['cash_amount']) ? $row['cash_amount'] : '', $msg);
            $msg = str_replace('[التفاصيل]', isset($row['details_text']) ? $row['details_text'] : '', $msg);

            $result = sendWhatsAppMsg($row['phone'], $msg);
            $message = $result ? "تم إرسال الرسالة للمستفيد بنجاح." : "فشل إرسال الرسالة.";
        } else {
            $error = "لا يوجد هاتف أو بيانات لهذا المستفيد.";
        }
    }
}

// --- جمع التوزيعات حسب القسم المختار ---
$chosenSection = isset($_POST['section']) ? $_POST['section'] : (isset($_GET['section']) ? $_GET['section'] : 'orphans');
if (!isset($sections[$chosenSection])) $chosenSection = 'orphans';

$stmtDist = $pdo->prepare("
    SELECT id, title, distribution_date
    FROM beneficiary_distributions
    WHERE beneficiary_type = ?
    ORDER BY id DESC
    LIMIT 30
");
$stmtDist->execute(array($chosenSection));
$distributions = $stmtDist->fetchAll(PDO::FETCH_ASSOC);

$chosenDist = isset($_POST['distribution_id']) ? (int)$_POST['distribution_id'] : (isset($_GET['distribution_id']) ? (int)$_GET['distribution_id'] : 0);

$beneficiaries = array();
if ($chosenDist > 0) {
    $table   = beneficiaryTable($chosenSection);
    $colName = beneficiaryNameColumn($chosenSection);
    $colPhone= beneficiaryPhoneColumn($chosenSection);

    $stmtBen = $pdo->prepare("
        SELECT b.id AS beneficiary_id, b.{$colName} AS full_name, b.{$colPhone} AS phone, i.cash_amount, i.details_text
        FROM beneficiary_distribution_items i
        INNER JOIN {$table} b ON b.id = i.beneficiary_id
        WHERE i.distribution_id = ?
        ORDER BY b.id ASC
    ");
    $stmtBen->execute(array($chosenDist));
    $beneficiaries = $stmtBen->fetchAll(PDO::FETCH_ASSOC);
}

// رسالة افتراضية
$defaultMsg = "السلام عليكم [الاسم]، تم صرف مبلغ [المبلغ] لك من لجنة زكاة مخيم حطين. يمكنك الاستفسار من اللجنة.";

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إرسال رسائل واتساب للمستفيدين</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
<style>
body {background:#f8fafc;}
.whatsapp-page {max-width:1100px;margin:32px auto 0 auto;}
</style>
</head>
<body>
<div class="whatsapp-page card shadow p-4">

    <h1 class="mb-4">إرسال رسائل واتساب للمستفيدين</h1>
    <?php if ($message): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-3 mb-4">
        <div class="col-md-4">
            <label class="form-label">القسم</label>
            <select name="section" class="form-select" onchange="this.form.submit()">
                <?php foreach ($sections as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $chosenSection === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">التوزيعة</label>
            <select name="distribution_id" class="form-select" onchange="this.form.submit()">
                <option value="0">-- اختر التوزيعة --</option>
                <?php foreach ($distributions as $dist): ?>
                    <option value="<?= (int)$dist['id'] ?>" <?= $chosenDist === (int)$dist['id'] ? 'selected' : '' ?>>
                        <?= e($dist['title']) ?> - <?= e($dist['distribution_date']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">نموذج الرسالة</label>
            <textarea name="custom_msg" class="form-control" rows="2"><?= e(isset($_POST['custom_msg']) ? $_POST['custom_msg'] : $defaultMsg) ?></textarea>
        </div>

        <input type="hidden" name="action" value="send_mass">
    </form>

    <?php if ($chosenDist > 0 && $beneficiaries): ?>
        <form method="POST">
            <input type="hidden" name="action" value="send_mass">
            <input type="hidden" name="section" value="<?= e($chosenSection) ?>">
            <input type="hidden" name="distribution_id" value="<?= (int)$chosenDist ?>">
            <input type="hidden" name="custom_msg" value="<?= e(isset($_POST['custom_msg']) ? $_POST['custom_msg'] : $defaultMsg) ?>">

            <div class="mb-3">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-whatsapp ms-1"></i> إرسال جماعي للمستفيدين المحددين
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle text-center">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="checkAll" onclick="toggleAllRows(this.checked)"></th>
                            <th>الاسم</th>
                            <th>رقم الهاتف</th>
                            <th>المبلغ</th>
                            <th>التفاصيل</th>
                            <th>إرسال فردي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($beneficiaries as $ben): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="row-checkbox" name="selected[]" value="<?= (int)$ben['beneficiary_id'] ?>">
                                </td>
                                <td><?= e($ben['full_name']) ?></td>
                                <td><?= e($ben['phone']) ?></td>
                                <td><?= e($ben['cash_amount']) ?></td>
                                <td><?= e($ben['details_text']) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="send_single">
                                        <input type="hidden" name="beneficiary_id" value="<?= (int)$ben['beneficiary_id'] ?>">
                                        <input type="hidden" name="distribution_id" value="<?= (int)$chosenDist ?>">
                                        <input type="hidden" name="section" value="<?= e($chosenSection) ?>">
                                        <input type="hidden" name="custom_msg" value="<?= e(isset($_POST['custom_msg']) ? $_POST['custom_msg'] : $defaultMsg) ?>">
                                        <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-whatsapp"></i> إرسال فردي</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function toggleAllRows(state) {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = state);
    document.getElementById('checkAll').checked = state;
}
</script>

</body>
</html>