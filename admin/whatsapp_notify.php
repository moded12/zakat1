<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/whatsapp.php';

requireAdmin();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (Throwable $e) {
        die('فشل الاتصال بقاعدة البيانات: ' . htmlspecialchars($e->getMessage()));
    }
}

$errorMessage = '';
$successMessage = '';
$previewRows = [];
$distributions = [];
$currentDistribution = null;

$selectedDistributionId = (int)($_REQUEST['distribution_id'] ?? 0);
$messageTemplate = trim((string)($_POST['message_template'] ?? ''));

if ($messageTemplate === '') {
    $messageTemplate = getDefaultMessageTemplate();
}

try {
    $stmt = $pdo->query("
        SELECT
            id,
            beneficiary_type,
            distribution_date,
            category,
            title,
            notes
        FROM beneficiary_distributions
        ORDER BY id DESC
    ");
    $distributions = $stmt->fetchAll();
} catch (Throwable $e) {
    $errorMessage = 'تعذر تحميل التوزيعات: ' . $e->getMessage();
}

if ($selectedDistributionId > 0) {
    foreach ($distributions as $dist) {
        if ((int)$dist['id'] === $selectedDistributionId) {
            $currentDistribution = $dist;
            break;
        }
    }
}

function fetchDistributionItemsWithBeneficiaries(PDO $pdo, array $distribution): array
{
    $type = trim((string)($distribution['beneficiary_type'] ?? ''));
    $map = getBeneficiarySourceMap();

    if (!isset($map[$type])) {
        throw new Exception('نوع المستفيد غير مدعوم: ' . $type);
    }

    $table = $map[$type]['table'];
    $nameColumn = $map[$type]['name_column'];
    $phoneColumn = $map[$type]['phone_column'];

    $sql = "
        SELECT
            i.id,
            i.distribution_id,
            i.beneficiary_id,
            i.cash_amount,
            i.details_text,
            i.notes,
            b.`{$nameColumn}` AS beneficiary_name,
            b.`{$phoneColumn}` AS beneficiary_phone
        FROM beneficiary_distribution_items i
        INNER JOIN `{$table}` b ON b.id = i.beneficiary_id
        WHERE i.distribution_id = ?
        ORDER BY i.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$distribution['id']]);

    return $stmt->fetchAll();
}

if ($currentDistribution) {
    try {
        $items = fetchDistributionItemsWithBeneficiaries($pdo, $currentDistribution);

        foreach ($items as $item) {
            $beneficiaryName = trim((string)($item['beneficiary_name'] ?? ''));
            $phoneOriginal = trim((string)($item['beneficiary_phone'] ?? ''));
            $phoneNormalized = normalizeJordanPhone($phoneOriginal);

            $amountValue = '';
            if (isset($item['cash_amount']) && $item['cash_amount'] !== null && $item['cash_amount'] !== '') {
                $amountValue = (string)$item['cash_amount'];
            }

            $detailsValue = trim((string)($item['details_text'] ?? ''));

            $messageText = renderMessageTemplate($messageTemplate, [
                'name' => $beneficiaryName,
                'title' => (string)($currentDistribution['title'] ?? ''),
                'date' => (string)($currentDistribution['distribution_date'] ?? ''),
                'category' => (string)($currentDistribution['category'] ?? ''),
                'amount' => $amountValue,
                'details' => $detailsValue,
            ]);

            $waLink = $phoneNormalized
                ? buildWhatsAppWaMeLink($phoneNormalized, $messageText)
                : null;

            $previewRows[] = [
                'item_id' => (int)$item['id'],
                'beneficiary_id' => (int)$item['beneficiary_id'],
                'beneficiary_name' => $beneficiaryName,
                'phone_original' => $phoneOriginal,
                'phone_normalized' => $phoneNormalized,
                'cash_amount' => $amountValue,
                'details_text' => $detailsValue,
                'message_text' => $messageText,
                'wa_link' => $waLink,
                'is_valid' => $phoneNormalized !== null,
            ];
        }
    } catch (Throwable $e) {
        $errorMessage = 'تعذر تحميل المستفيدين: ' . $e->getMessage();
    }
}

$totalCount = count($previewRows);
$validCount = count(array_filter($previewRows, fn($r) => $r['is_valid']));
$invalidCount = $totalCount - $validCount;

adminLayoutStart('تبليغ التوزيعات (واتس)', 'whatsapp_notify');
?>

<div class="container-fluid">
    <div class="card border-0 shadow-sm" style="border-radius: 18px;">
        <div class="card-body">
            <h3 class="fw-bold mb-2">تبليغ التوزيعات عبر واتساب</h3>
            <p class="text-muted mb-4">
                نسخة مجانية جزئية: كتابة رسالة نصية مخصصة وفتح واتساب للمستفيدين المحددين.
            </p>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>

            <div class="alert alert-info">
                هذه الصفحة لا ترسل تلقائيًا من السيرفر. هي فقط تجهّز نص الرسالة وتفتح واتساب للمستفيدين المحددين.
            </div>

            <form method="post" id="distributionForm">
                <div class="row g-3 align-items-end mb-4">
                    <div class="col-lg-6">
                        <label class="form-label fw-bold">اختر التوزيع</label>
                        <select name="distribution_id" class="form-select" onchange="this.form.submit()">
                            <option value="">-- اختر التوزيع --</option>
                            <?php foreach ($distributions as $dist): ?>
                                <option value="<?= (int)$dist['id'] ?>" <?= $selectedDistributionId === (int)$dist['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(
                                        ($dist['beneficiary_type'] ?? '-') . ' | ' .
                                        ($dist['distribution_date'] ?? '-') . ' | ' .
                                        ($dist['title'] ?? '-')
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-6">
                        <?php if ($currentDistribution): ?>
                            <div class="p-3 bg-light rounded">
                                <div><strong>العنوان:</strong> <?= htmlspecialchars($currentDistribution['title'] ?? '-') ?></div>
                                <div><strong>نوع المستفيد:</strong> <?= htmlspecialchars($currentDistribution['beneficiary_type'] ?? '-') ?></div>
                                <div><strong>التاريخ:</strong> <?= htmlspecialchars($currentDistribution['distribution_date'] ?? '-') ?></div>
                                <div><strong>التصنيف:</strong> <?= htmlspecialchars($currentDistribution['category'] ?? '-') ?></div>
                                <div><strong>ملاحظات:</strong> <?= htmlspecialchars($currentDistribution['notes'] ?? '-') ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($selectedDistributionId > 0): ?>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label fw-bold">محتوى الرسالة النصية</label>
                            <textarea
                                name="message_template"
                                id="message_template"
                                class="form-control"
                                rows="8"
                                placeholder="اكتب الرسالة هنا..."
                            ><?= htmlspecialchars($messageTemplate) ?></textarea>

                            <div class="form-text mt-2">
                                يمكنك استخدام المتغيرات التالية داخل النص:
                                <code>{name}</code>
                                <code>{title}</code>
                                <code>{date}</code>
                                <code>{category}</code>
                                <code>{amount}</code>
                                <code>{details}</code>
                            </div>
                        </div>

                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary">
                                تحديث المعاينة
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleSelectAll(true)">
                                تحديد الكل
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleSelectAll(false)">
                                إلغاء التحديد
                            </button>
                            <button type="button" class="btn btn-success" onclick="openSelectedWhatsApp()">
                                فتح واتساب للمحدد
                            </button>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <div class="text-muted">إجمالي السجلات</div>
                                    <div class="fs-3 fw-bold"><?= (int)$totalCount ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <div class="text-muted">أرقام صالحة</div>
                                    <div class="fs-3 fw-bold text-success"><?= (int)$validCount ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <div class="text-muted">أرقام غير صالحة</div>
                                    <div class="fs-3 fw-bold text-danger"><?= (int)$invalidCount ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">
                                        <input type="checkbox" id="checkAll" onclick="toggleFromHeader(this)">
                                    </th>
                                    <th>#</th>
                                    <th>اسم المستفيد</th>
                                    <th>الهاتف الأصلي</th>
                                    <th>الهاتف بعد التنسيق</th>
                                    <th>المبلغ</th>
                                    <th>التفاصيل</th>
                                    <th>الرسالة</th>
                                    <th>الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($previewRows)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">لا توجد بيانات داخل هذا التوزيع.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($previewRows as $index => $row): ?>
                                        <tr>
                                            <td>
                                                <?php if ($row['is_valid'] && $row['wa_link']): ?>
                                                    <input
                                                        type="checkbox"
                                                        class="beneficiary-check"
                                                        value="<?= htmlspecialchars($row['wa_link']) ?>"
                                                    >
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($row['beneficiary_name']) ?></td>
                                            <td><?= htmlspecialchars($row['phone_original']) ?></td>
                                            <td><?= htmlspecialchars($row['phone_normalized'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars((string)($row['cash_amount'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string)($row['details_text'] ?? '')) ?></td>
                                            <td style="min-width: 320px; white-space: pre-line;">
                                                <?= htmlspecialchars($row['message_text']) ?>
                                            </td>
                                            <td>
                                                <?php if ($row['is_valid'] && $row['wa_link']): ?>
                                                    <a href="<?= htmlspecialchars($row['wa_link']) ?>" target="_blank" class="btn btn-success btn-sm">
                                                        فتح واتساب
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">رقم غير صالح</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
function toggleSelectAll(state) {
    const checks = document.querySelectorAll('.beneficiary-check');
    checks.forEach(function (checkbox) {
        checkbox.checked = state;
    });

    const headerCheck = document.getElementById('checkAll');
    if (headerCheck) {
        headerCheck.checked = state;
    }
}

function toggleFromHeader(el) {
    toggleSelectAll(el.checked);
}

function openSelectedWhatsApp() {
    const checks = document.querySelectorAll('.beneficiary-check:checked');

    if (checks.length === 0) {
        alert('يرجى تحديد مستفيد واحد على الأقل.');
        return;
    }

    const links = Array.from(checks).map(function (checkbox) {
        return checkbox.value;
    });

    const win = window.open('', '_blank');

    if (!win) {
        alert('المتصفح منع فتح النافذة. اسمح بالنوافذ المنبثقة ثم أعد المحاولة.');
        return;
    }

    let html = `
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>روابط واتساب المحددة</title>
            <style>
                body {
                    font-family: Tahoma, Arial, sans-serif;
                    background: #f7f7f7;
                    padding: 20px;
                    line-height: 1.8;
                    direction: rtl;
                }
                .box {
                    max-width: 900px;
                    margin: auto;
                    background: #fff;
                    border-radius: 12px;
                    padding: 20px;
                    box-shadow: 0 2px 10px rgba(0,0,0,.08);
                }
                h2 {
                    margin-top: 0;
                }
                .note {
                    background: #e8f7ff;
                    border: 1px solid #bfe8ff;
                    padding: 12px 14px;
                    border-radius: 10px;
                    margin-bottom: 16px;
                }
                .item {
                    padding: 10px 0;
                    border-bottom: 1px solid #eee;
                }
                .btn {
                    display: inline-block;
                    background: #198754;
                    color: #fff;
                    text-decoration: none;
                    padding: 8px 14px;
                    border-radius: 8px;
                    margin-left: 8px;
                }
                .btn:hover {
                    opacity: .9;
                }
                .actions {
                    margin-bottom: 16px;
                }
                button {
                    padding: 8px 14px;
                    border: none;
                    border-radius: 8px;
                    background: #0d6efd;
                    color: #fff;
                    cursor: pointer;
                }
                .small {
                    color: #666;
                    font-size: 13px;
                    margin-right: 8px;
                }
            </style>
        </head>
        <body>
            <div class="box">
                <h2>روابط واتساب للمستفيدين المحددين</h2>
                <div class="note">
                    اضغط على الروابط واحدًا واحدًا، أو استخدم زر فتح الكل بالتتابع. هذا أكثر استقرارًا من محاولة فتح كل المحادثات دفعة واحدة.
                </div>
                <div class="actions">
                    <button onclick="openAllSequential()">فتح الكل بالتتابع</button>
                    <span class="small">عدد الروابط: ${links.length}</span>
                </div>
    `;

    links.forEach(function(link, index) {
        html += `
            <div class="item">
                <a class="btn wa-link" href="${link}" target="_blank">فتح واتساب ${index + 1}</a>
            </div>
        `;
    });

    html += `
            </div>

            <script>
                function openAllSequential() {
                    const anchors = document.querySelectorAll('.wa-link');
                    let i = 0;

                    function openNext() {
                        if (i >= anchors.length) return;
                        window.open(anchors[i].href, '_blank');
                        i++;
                        setTimeout(openNext, 1200);
                    }

                    openNext();
                }
            <\/script>
        </body>
        </html>
    `;

    win.document.open();
    win.document.write(html);
    win.document.close();
}
</script>

<?php adminLayoutEnd(); ?>