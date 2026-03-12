<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/beneficiary_helpers.php';

requireAdmin();

$pdo = getDB();

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function numberToArabicWords($number)
{
    $number = (int)$number;

    $words = array(
        0 => 'صفر',
        1 => 'واحد',
        2 => 'اثنان',
        3 => 'ثلاثة',
        4 => 'أربعة',
        5 => 'خمسة',
        6 => 'ستة',
        7 => 'سبعة',
        8 => 'ثمانية',
        9 => 'تسعة',
        10 => 'عشرة',
        11 => 'أحد عشر',
        12 => 'اثنا عشر',
        13 => 'ثلاثة عشر',
        14 => 'أربعة عشر',
        15 => 'خمسة عشر',
        16 => 'ستة عشر',
        17 => 'سبعة عشر',
        18 => 'ثمانية عشر',
        19 => 'تسعة عشر',
        20 => 'عشرون',
        30 => 'ثلاثون',
        40 => 'أربعون',
        50 => 'خمسون',
        60 => 'ستون',
        70 => 'سبعون',
        80 => 'ثمانون',
        90 => 'تسعون',
        100 => 'مائة',
        200 => 'مائتان',
        300 => 'ثلاثمائة',
        400 => 'أربعمائة',
        500 => 'خمسمائة',
        600 => 'ستمائة',
        700 => 'سبعمائة',
        800 => 'ثمانمائة',
        900 => 'تسعمائة',
        1000 => 'ألف',
        2000 => 'ألفان',
    );

    if (isset($words[$number])) {
        return $words[$number];
    }

    if ($number < 100) {
        $tens = ((int)floor($number / 10)) * 10;
        $ones = $number % 10;
        return $words[$ones] . ' و' . $words[$tens];
    }

    if ($number < 1000) {
        $hundreds = ((int)floor($number / 100)) * 100;
        $rest = $number % 100;

        if ($rest === 0) {
            return $words[$hundreds];
        }

        return $words[$hundreds] . ' و' . numberToArabicWords($rest);
    }

    if ($number < 1000000) {
        $thousands = (int)floor($number / 1000);
        $rest = $number % 1000;

        if ($thousands === 1) {
            $prefix = 'ألف';
        } elseif ($thousands === 2) {
            $prefix = 'ألفان';
        } elseif ($thousands <= 10) {
            $prefix = numberToArabicWords($thousands) . ' آلاف';
        } else {
            $prefix = numberToArabicWords($thousands) . ' ألف';
        }

        if ($rest === 0) {
            return $prefix;
        }

        return $prefix . ' و' . numberToArabicWords($rest);
    }

    return (string)$number;
}

function shortAmountWords($amount)
{
    $amount = (float)$amount;
    $integer = (int)floor($amount);
    $fraction = (int)round(($amount - $integer) * 100);

    $text = numberToArabicWords($integer);

    if ($fraction > 0) {
        $text .= ' و' . numberToArabicWords($fraction) . ' قرش';
    }

    return $text;
}

function assistanceTitle($category)
{
    if ($category === 'نقد') return 'المساعدات النقدية';
    if ($category === 'مواد عينية') return 'المساعدات العينية';
    if ($category === 'منظفات') return 'مساعدات المنظفات';
    if ($category === 'ملابس') return 'مساعدات الملابس';
    return 'مساعدات أخرى';
}

$distributionId = (int)(isset($_GET['id']) ? $_GET['id'] : 0);

if ($distributionId <= 0) {
    die('رقم التوزيعة غير صالح.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM beneficiary_distributions
    WHERE id = ?
    LIMIT 1
");
$stmt->execute(array($distributionId));
$distribution = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$distribution) {
    die('لم يتم العثور على التوزيعة المطلوبة.');
}

$type = resolveBeneficiaryType(
    isset($distribution['beneficiary_type']) ? $distribution['beneficiary_type'] : '',
    isset($distribution['title']) ? $distribution['title'] : ''
);

if (!validBeneficiaryType($type)) {
    die('تعذر تحديد نوع القسم لهذه التوزيعة. يرجى تحديث بيانات beneficiary_type في قاعدة البيانات.');
}

$table = beneficiaryTable($type);
$colName = beneficiaryNameColumn($type);
$colNumber = beneficiaryNumberColumn($type);
$colIdNumber = beneficiaryIdNumberColumn($type);
$colPhone = beneficiaryPhoneColumn($type);

$sql = "
    SELECT
        i.id,
        i.beneficiary_id,
        i.cash_amount,
        i.details_text,
        i.notes,
        b.{$colNumber} AS ref_number,
        b.{$colName} AS full_name,
        b.{$colIdNumber} AS id_number,
        b.{$colPhone} AS phone
    FROM beneficiary_distribution_items i
    INNER JOIN {$table} b ON b.id = i.beneficiary_id
    WHERE i.distribution_id = ?
    ORDER BY CAST(b.{$colNumber} AS UNSIGNED) ASC, b.id ASC
";
$stmtItems = $pdo->prepare($sql);
$stmtItems->execute(array($distributionId));
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

$category = isset($distribution['category']) ? $distribution['category'] : '';
$isCash = ($category === 'نقد');

$itemsPerPage = 20;
$chunks = array_chunk($items, $itemsPerPage);
if (!$chunks) {
    $chunks = array(array());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>كشف توزيع رقم <?=(int)$distributionId ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    @page {
        size: A4 portrait;
        margin: 6mm;
    }

    * { box-sizing: border-box; }

    html, body {
        margin: 0;
        padding: 0;
        direction: rtl;
        background: #f5f7fb;
        color: #000;
        font-family: 'Cairo', sans-serif;
    }

    body {
        font-size: 12px;
        line-height: 1.15;
    }

    .toolbar {
        padding: 14px 16px;
        border-bottom: 1px solid #e5e7eb;
        background: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: sticky;
        top: 0;
        z-index: 10;
        box-shadow: 0 8px 20px rgba(15,23,42,.06);
    }

    .toolbar-title {
        font-size: 16px;
        font-weight: 800;
        color: #111827;
    }

    .toolbar-sub {
        font-size: 13px;
        color: #6b7280;
        margin-top: 3px;
    }

    .toolbar .actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 9px 15px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #fff;
        color: #111;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
    }

    .btn-primary {
        background: #166534;
        border-color: #166534;
        color: #fff;
    }

    .btn-dark {
        background: #111827;
        border-color: #111827;
        color: #fff;
    }

    .pages-wrap {
        padding: 10px 0 16px;
    }

    .page {
        width: 198mm;
        min-height: 285mm;
        margin: 0 auto 10px auto;
        background: #fff;
        box-shadow: 0 10px 30px rgba(0,0,0,.08);
        border-radius: 8px;
        overflow: hidden;
        page-break-after: always;
        break-after: page;
    }

    .page:last-child {
        page-break-after: auto;
        break-after: auto;
    }

    .report-box {
        width: 100%;
        min-height: 285mm;
        padding: 3mm 3mm 3mm;
        display: flex;
        flex-direction: column;
    }

    .header {
        text-align: center;
        margin-bottom: 1.6mm;
    }

    .header .line {
        font-size: 6mm;
        font-weight: 700;
        line-height: 1.05;
        margin: 0 0 0.4mm 0;
    }

    .header .line.small {
        font-size: 5.3mm;
    }

    .header .line.title {
        font-size: 7.1mm;
        font-weight: 800;
        margin-top: .7mm;
    }

    .header .line.sub-title {
        font-size: 5.1mm;
        font-weight: 700;
        margin-top: .5mm;
    }

    .meta-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 1.4mm;
    }

    .meta-table td {
        padding: 0.2mm 1mm;
        font-size: 4mm;
        vertical-align: middle;
        white-space: nowrap;
    }

    .meta-label {
        font-weight: 800;
    }

    .notes-box {
        margin-top: 1px;
        margin-bottom: 2px;
        padding: 5px 8px;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        background: #f8fafc;
        font-size: 11px;
        color: #334155;
    }

    .table-wrap {
        flex: 1 1 auto;
    }

    .report-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .report-table th,
    .report-table td {
        border: 0.2mm solid #000;
        text-align: center;
        vertical-align: middle;
        padding: 0;
        overflow: hidden;
    }

    .report-table th {
        background: #efefef;
        font-size: 3.9mm;
        font-weight: 800;
        height: 7.2mm;
    }

    .report-table td {
        font-size: 3.55mm;
        height: 7.2mm;
    }

    .sum-row td {
        font-weight: 800;
        background: #fafafa;
        height: 7.2mm;
    }

    .sum-label,
    .sum-value {
        color: #d11a1a;
        font-weight: 800;
    }

    .col-number      { width: 7%; }
    .col-name        { width: 26%; }
    .col-id          { width: 14%; }
    .col-phone       { width: 14%; }
    .col-value       { width: 10%; }
    .col-value-words { width: 13%; }
    .col-sign        { width: 16%; }

    .cell-text {
        padding: 0 0.8mm;
        white-space: normal;
        word-break: break-word;
        line-height: 1.02;
    }

    .signature-cell {
        height: 7.2mm;
        min-width: 20mm;
    }

    .footer-area {
        margin-top: auto;
        padding-top: 2mm;
    }

    .footer-note {
        margin-top: 1.8mm;
        text-align: center;
        font-size: 4mm;
        font-weight: 700;
    }

    .footer-signatures {
        width: 100%;
        border-collapse: collapse;
        margin-top: 2mm;
    }

    .footer-signatures td {
        width: 33.333%;
        text-align: center;
        font-size: 4mm;
        padding-top: 2mm;
        white-space: nowrap;
    }

    .page-number {
        margin-top: 1.8mm;
        text-align: center;
        font-size: 3.4mm;
    }

    @media print {
        .toolbar {
            display: none !important;
        }

        html, body {
            width: 210mm;
            height: auto;
            background: #fff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .pages-wrap {
            padding: 0 !important;
        }

        .page {
            width: auto !important;
            min-height: auto !important;
            margin: 0 !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            background: #fff !important;
            page-break-after: always;
            break-after: page;
        }

        .page:last-child {
            page-break-after: auto;
            break-after: auto;
        }

        .report-box {
            min-height: auto !important;
            padding: 2mm 2mm 2mm !important;
        }
    }
</style>
</head>
<body>

<div class="toolbar">
    <div>
        <div class="toolbar-title">كشف توزيع رقم #<?=(int)$distributionId ?></div>
        <div class="toolbar-sub">
            <?=e(beneficiaryTypeLabel($type)) ?> •
            <?=e(isset($distribution['category']) ? $distribution['category'] : '') ?> •
            <?=e(isset($distribution['distribution_date']) ? $distribution['distribution_date'] : '') ?>
        </div>
    </div>
    <div class="actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">طباعة الآن</button>
        <a href="<?=BASE_PATH ?>/admin/distributions.php" class="btn btn-dark">العودة للتوزيعات</a>
    </div>
</div>

<div class="pages-wrap">
<?php foreach ($chunks as $pageIndex => $chunk): ?>
    <?php
    $pageCashTotal = 0.0;
    foreach ($chunk as $rowForSum) {
        $pageCashTotal += (float)(isset($rowForSum['cash_amount']) ? $rowForSum['cash_amount'] : 0);
    }
    ?>
    <div class="page">
        <div class="report-box">
            <div class="header">
                <div class="line small">وزارة الأوقاف والشؤون والمقدسات الإسلامية</div>
                <div class="line small">صندوق الزكاة</div>
                <div class="line small"><?=e(assistanceTitle($category)) ?></div>
                <div class="line small">لجنة زكاة وصدقات - مخيم حطين المركزية</div>
                <div class="line title">كشف توزيع</div>
                <div class="line sub-title"><?=e(isset($distribution['title']) ? $distribution['title'] : '') ?></div>
            </div>

            <table class="meta-table">
                <tr>
                    <td><span class="meta-label">رقم قرار اللجنة:</span> .......................................</td>
                    <td><span class="meta-label">تاريخ التوزيع:</span> <?=e(isset($distribution['distribution_date']) ? $distribution['distribution_date'] : '') ?></td>
                </tr>
                <tr>
                    <td><span class="meta-label">تاريخ القرار:</span> .........................................</td>
                    <td><span class="meta-label">رقم الكشف:</span> <?=(int)$distribution['id'] ?></td>
                </tr>
                <tr>
                    <td><span class="meta-label">القسم:</span> <?=e(beneficiaryTypeLabel($type)) ?></td>
                    <td><span class="meta-label">نوع التوزيعة:</span> <?=e(isset($distribution['category']) ? $distribution['category'] : '') ?></td>
                </tr>
            </table>

            <?php if (!empty($distribution['notes']) && $pageIndex === 0): ?>
                <div class="notes-box">
                    <strong>ملاحظات عامة:</strong>
                    <?=nl2br(e($distribution['notes'])) ?>
                </div>
            <?php endif; ?>

            <div class="table-wrap">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th class="col-number">الرقم</th>
                            <th class="col-name">الاسم</th>
                            <th class="col-id">الهوية</th>
                            <th class="col-phone">الهاتف</th>
                            <th class="col-value"><?=$isCash ? 'المبلغ' : 'التفاصيل' ?></th>
                            <th class="col-value-words"><?=$isCash ? 'المبلغ كتابة' : 'بيان' ?></th>
                            <th class="col-sign">التوقيع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < $itemsPerPage; $i++): ?>
                            <?php $row = isset($chunk[$i]) ? $chunk[$i] : null; ?>
                            <tr>
                                <td><?=$i + 1 ?></td>
                                <td><div class="cell-text"><?=$row ? e(isset($row['full_name']) ? $row['full_name'] : '') : '' ?></div></td>
                                <td><div class="cell-text"><?=$row ? e(isset($row['id_number']) ? $row['id_number'] : '') : '' ?></div></td>
                                <td><div class="cell-text"><?=$row ? e(isset($row['phone']) ? $row['phone'] : '') : '' ?></div></td>
                                <td>
                                    <div class="cell-text">
                                        <?php
                                        if ($row) {
                                            echo $isCash
                                                ? number_format((float)(isset($row['cash_amount']) ? $row['cash_amount'] : 0), 2)
                                                : e(isset($row['details_text']) ? $row['details_text'] : '');
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-text">
                                        <?php
                                        if ($row) {
                                            echo $isCash
                                                ? e(shortAmountWords((float)(isset($row['cash_amount']) ? $row['cash_amount'] : 0)))
                                                : '';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="signature-cell"></td>
                            </tr>
                        <?php endfor; ?>

                        <?php if ($isCash): ?>
                            <tr class="sum-row">
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="sum-label">المجموع</td>
                                <td class="sum-value"><?=number_format($pageCashTotal, 2) ?></td>
                                <td class="sum-label"><?=e(shortAmountWords($pageCashTotal)) ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="footer-area">
                <div class="footer-note">
                    أشهد بأني قمت بتسليم المبالغ المذكورة أعلاه إلى ذوي الاستحقاق كلٌّ باسمه وحسب الأصول
                </div>

                <table class="footer-signatures">
                    <tr>
                        <td>عضو اللجنة ....................................</td>
                        <td>توقيع أمين صندوق اللجنة ....................................</td>
                        <td>ختم اللجنة</td>
                    </tr>
                </table>

                <div class="page-number">
                    صفحة <?=$pageIndex + 1 ?> من <?=count($chunks) ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

</body>
</html>