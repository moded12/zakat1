<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

requireAdmin();

$pdo = getDB();

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getCount(PDO $pdo, string $table, string $where = ''): int
{
    $sql = "SELECT COUNT(*) FROM {$table}";
    if ($where !== '') {
        $sql .= " WHERE {$where}";
    }
    $stmt = $pdo->query($sql);
    return (int)$stmt->fetchColumn();
}

function safeFetchAll(PDO $pdo, string $sql): array
{
    try {
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function beneficiaryLabel(string $type): string
{
    if ($type === 'poor_families') return 'الأسر الفقيرة';
    if ($type === 'orphans') return 'الأيتام';
    if ($type === 'sponsorships') return 'كفالة الأيتام';
    if ($type === 'family_salaries') return 'رواتب الأسر';
    return $type;
}

function sourceSheetLabel(string $source): string
{
    if ($source === 'poor_families') return 'الأسر الفقيرة';
    if ($source === 'orphans') return 'الأيتام';
    if ($source === 'sponsorships') return 'كفالة الأيتام';
    if ($source === 'family_salaries') return 'رواتب الأسر';
    return $source;
}

$totalFamilies = 0;
$totalOrphans = 0;
$totalSponsorships = 0;
$activeSponsorships = 0;
$totalFamilySalaries = 0;
$totalDistributions = 0;
$totalDistributionItems = 0;
$recentSheetsCount = 0;

try { $totalFamilies = getCount($pdo, 'poor_families'); } catch (Throwable $e) {}
try { $totalOrphans = getCount($pdo, 'orphans'); } catch (Throwable $e) {}
try { $totalSponsorships = getCount($pdo, 'sponsorships'); } catch (Throwable $e) {}
try { $activeSponsorships = getCount($pdo, 'sponsorships', "status = 'نشطة'"); } catch (Throwable $e) {}
try { $totalFamilySalaries = getCount($pdo, 'family_salaries'); } catch (Throwable $e) {}
try { $totalDistributions = getCount($pdo, 'beneficiary_distributions'); } catch (Throwable $e) {}
try { $totalDistributionItems = getCount($pdo, 'beneficiary_distribution_items'); } catch (Throwable $e) {}
try { $recentSheetsCount = getCount($pdo, 'distribution_sheets', "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (Throwable $e) {}

$recentDistributions = safeFetchAll($pdo, "
    SELECT d.id, d.beneficiary_type, d.distribution_date, d.category, d.title,
           COUNT(i.id) AS beneficiaries_count
    FROM beneficiary_distributions d
    LEFT JOIN beneficiary_distribution_items i ON i.distribution_id = d.id
    GROUP BY d.id, d.beneficiary_type, d.distribution_date, d.category, d.title
    ORDER BY d.id DESC
    LIMIT 5
");

$recentSheets = safeFetchAll($pdo, "
    SELECT id, sheet_number, source_type, distribution_type, distribution_date, total_records
    FROM distribution_sheets
    ORDER BY id DESC
    LIMIT 5
");

$alerts = [];
if ($totalFamilies === 0) $alerts[] = ['type' => 'info', 'text' => 'لا توجد سجلات في قسم الأسر الفقيرة حتى الآن.'];
if ($totalOrphans === 0) $alerts[] = ['type' => 'info', 'text' => 'لا توجد سجلات في قسم الأيتام حتى الآن.'];
if ($totalSponsorships === 0) $alerts[] = ['type' => 'warning', 'text' => 'لا توجد سجلات في قسم الكفالات حتى الآن.'];
if ($totalFamilySalaries === 0) $alerts[] = ['type' => 'warning', 'text' => 'لا توجد بيانات في قسم رواتب الأسر ح  ى الآن.'];
if ($totalDistributions === 0) $alerts[] = ['type' => 'secondary', 'text' => 'لم يتم إنشاء أي توزيعة حتى الآن.'];
if ($recentSheetsCount > 0) $alerts[] = ['type' => 'success', 'text' => 'يوجد ' . $recentSheetsCount . ' كشف محفوظ خلال آخر 30 يومًا.'];

adminLayoutStart('لوحة التحكم', 'dashboard');
?>
<style>
.dashboard-page .hero-box{
    background: linear-gradient(135deg, #14345f 0%, #1f4f88 55%, #2b6cb0 100%);
    border-radius: 28px;
    padding: 26px;
    color: #fff;
    box-shadow: 0 16px 40px rgba(20,52,95,.25);
    overflow: hidden;
    position: relative;
}
.dashboard-page .hero-box::after{
    content:'';
    position:absolute;
    left:-40px;
    bottom:-40px;
    width:180px;
    height:180px;
    background:rgba(255,255,255,.08);
    border-radius:50%;
}
.dashboard-page .hero-box::before{
    content:'';
    position:absolute;
    left:120px;
    top:-35px;
    width:120px;
    height:120px;
    background:rgba(255,255,255,.06);
    border-radius:50%;
}
.dashboard-page .hero-title{
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: .45rem;
    position: relative;
    z-index: 1;
}
.dashboard-page .hero-text{
    color: rgba(255,255,255,.88);
    margin-bottom: 0;
    position: relative;
    z-index: 1;
}
.dashboard-page .hero-actions{
    position: relative;
    z-index: 1;
}
.dashboard-page .hero-actions .btn{
    border-radius: 14px;
    padding: 10px 16px;
    font-weight: 700;
}
.dashboard-page .info-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    background:rgba(255,255,255,.12);
    padding:9px 14px;
    border-radius:999px;
    font-size:14px;
    font-weight:700;
    color:#fff;
    position: relative;
    z-index: 1;
}
.dashboard-page .stat-card{
    border:none;
    border-radius:22px;
    background:#fff;
    box-shadow:0 12px 30px rgba(15,23,42,.08);
    height:100%;
    overflow:hidden;
}
.dashboard-page .stat-card .card-body{
    padding: 1.35rem;
}
.dashboard-page .stat-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom: 14px;
}
.dashboard-page .stat-icon{
    width:58px;
    height:58px;
    border-radius:18px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:1.35rem;
}
.dashboard-page .bg-families{ background:#dbeafe; color:#1d4ed8; }
.dashboard-page .bg-orphans{ background:#dcfce7; color:#15803d; }
.dashboard-page .bg-sponsorships{ background:#fef3c7; color:#b45309; }
.dashboard-page .bg-salaries{ background:#ede9fe; color:#7c3aed; }
.dashboard-page .bg-distributions{ background:#fce7f3; color:#be185d; }
.dashboard-page .bg-sheets{ background:#e0f2fe; color:#0369a1; }
.dashboard-page .stat-title{ color:#6b7280; font-weight:700; margin-bottom:4px; }
.dashboard-page .stat-number{ font-size:2rem; font-weight:800; color:#111827; line-height:1.1; }
.dashboard-page .stat-sub{ color:#6b7280; font-size:13px; margin-top:6px; }
.dashboard-page .section-card{
    border:none;
    border-radius:22px;
    background:#fff;
    box-shadow:0 12px 30px rgba(15,23,42,.08);
    height:100%;
}
.dashboard-page .section-card .card-body{ padding:1.35rem; }
.dashboard-page .section-title{ font-size:1.15rem; font-weight:800; color:#111827; margin-bottom:14px; }
.dashboard-page .quick-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:12px;
}
.dashboard-page .quick-link{
    display:flex;
    align-items:center;
    gap:12px;
    text-decoration:none;
    border:1px solid #e5e7eb;
    border-radius:18px;
    padding:14px;
    transition:.2s ease;
    background:#fff;
    color:#111827;
}
.dashboard-page .quick-link:hover{
    transform:translateY(-2px);
    box-shadow:0 10px 25px rgba(15,23,42,.08);
    border-color:#cbd5e1;
}
.dashboard-page .quick-icon{
    width:48px;
    height:48px;
    border-radius:15px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:1.2rem;
    flex:0 0 auto;
}
.dashboard-page .quick-label{ font-weight:800; margin-bottom:2px; }
.dashboard-page .quick-desc{ font-size:13px; color:#6b7280; }
.dashboard-page .alert-item{
    border-radius:16px;
    padding:12px 14px;
    margin-bottom:10px;
    font-weight:700;
    border:1px solid transparent;
}
.dashboard-page .alert-item.info{ background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
.dashboard-page .alert-item.warning{ background:#fffbeb; color:#b45309; border-color:#fde68a; }
.dashboard-page .alert-item.success{ background:#ecfdf5; color:#15803d; border-color:#bbf7d0; }
.dashboard-page .alert-item.secondary{ background:#f3f4f6; color:#374151; border-color:#e5e7eb; }
.dashboard-page .table-wrap{ overflow-x:auto; }
.dashboard-page .table thead th{ background:#eef4ff; border-color:#dbe5f1; font-weight:800; }
.dashboard-page .empty-box{
    border:1px dashed #d1d5db;
    border-radius:16px;
    padding:18px;
    text-align:center;
    color:#6b7280;
    background:#fafafa;
}
@media (max-width: 767.98px){
    .dashboard-page .hero-title{ font-size:1.45rem; }
    .dashboard-page .quick-grid{ grid-template-columns:1fr; }
}
</style>

<div class="dashboard-page container-fluid py-2">

    <div class="hero-box mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-lg-8">
                <div class="hero-title">مرحبًا بك في لوحة إدارة الزكاة والصدقات</div>
                <p class="hero-text">
                    من هنا يمكنك متابعة الأقسام، إنشاء التوزيعات، إدارة رواتب الأسر، الطباعة الموحدة،
                    والاستيراد الجماعي، مع عرض آخر النشاطات والتنبيهات المهمة.
                </p>
            </div>
            <div class="col-lg-4">
                <div class="d-flex flex-column align-items-lg-end gap-2 hero-actions">
                    <span class="info-chip"><i class="bi bi-person-circle"></i> <?= e($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'مدير النظام') ?></span>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= BASE_PATH ?>/admin/distributions.php" class="btn btn-light text-dark">توزيعة جديدة</a>
                        <a href="<?= BASE_PATH ?>/admin/unified_import.php" class="btn btn-outline-light">استيراد</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-4 col-xxl-2">
            <div class="card stat-card"><div class="card-body"><div class="stat-top"><div><div class="stat-title">الأسر الفقيرة</div><div class="stat-number"><?= $totalFamilies ?></div></div><div class="stat-icon bg-families"><i class="bi bi-people-fill"></i></div></div><div class="stat-sub">إجمالي السجلات في القسم</div></div></div>
        </div>
        <div class="col-sm-6 col-xl-4 col-xxl-2">
            <div class="card stat-card"><div class="card-body"><div class="stat-top"><div><div class="stat-title">الأيتام</div><div class="stat-number"><?= $totalOrphans ?></div></div><div class="stat-icon bg-orphans"><i class="bi bi-person-hearts"></i></div></div><div class="stat-sub">إجمالي السجلات في القسم</div></div></div>
        </div>
        <div class="col-sm-6 col-xl-4 col-xxl-2">
            <div class="card stat-card"><div class="card-body"><div class="stat-top"><div><div class="stat-title">الكفالات</div><div class="stat-number"><?= $totalSponsorships ?></div></div><div class="stat-icon bg-sponsorships"><i class="bi bi-cash-coin"></i></div></div><div class="stat-sub">النشطة: <?= $activeSponsorships ?></div></div></div>
        </div>
        <div class="col-sm-6 col-xl-4 col-xxl-2">
            <div class="card stat-card"><div class="card-body"><div class="stat-top"><div><div class="stat-title">رواتب الأسر</div><div class="stat-number"><?= $totalFamilySalaries ?></div></div><div class="stat-icon bg-salaries"><i class="bi bi-wallet2"></i></div></div><div class="stat-sub">إجمالي السجلات في القسم</div></div></div>
        </div>
        <div class="col-sm-6 col-xl-4 col-xxl-2">
            <div class="card stat-card"><div class="card-body"><div class="stat-top"><div><div class="stat-title">التوزيعات</div><div class="stat-number"><?= $totalDistributions ?></div></div><div class="stat-icon bg-distributions"><i class="bi bi-box-seam"></i></div></div><div class="stat-sub">العناصر المسجلة: <?= $totalDistributionItems ?></div></div></div>
        </div>
        <div class="col-sm-6 col-xl-4 col-xxl-2">
            <div class="card stat-card"><div class="card-body"><div class="stat-top"><div><div class="stat-title">الكشوف المحفوظة</div><div class="stat-number"><?= $recentSheetsCount ?></div></div><div class="stat-icon bg-sheets"><i class="bi bi-printer"></i></div></div><div class="stat-sub">خلال آخر 30 يومًا</div></div></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-7">
            <div class="card section-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h3 class="section-title mb-0">آخر التوزيعات</h3>
                        <a href="<?= BASE_PATH ?>/admin/distributions.php" class="btn btn-sm btn-outline-primary">فتح التوزيعات</a>
                    </div>

                    <?php if (!$recentDistributions): ?>
                        <div class="empty-box">لا توجد توزيعات مسجلة ح  ى الآن.</div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table table-bordered align-middle text-center">
                                <thead>
                                    <tr>
                                        <th>العنوان</th>
                                        <th>القسم</th>
                                        <th>النوع</th>
                                        <th>التاريخ</th>
                                        <th>المستفيدون</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentDistributions as $row): ?>
                                        <tr>
                                            <td><?= e($row['title']) ?></td>
                                            <td><?= e(beneficiaryLabel($row['beneficiary_type'])) ?></td>
                                            <td><?= e($row['category']) ?></td>
                                            <td><?= e($row['distribution_date']) ?></td>
                                            <td><?= (int)$row['beneficiaries_count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card section-card h-100">
                <div class="card-body">
                    <h3 class="section-title">تنبيهات وملاحظات النظام</h3>
                    <?php if (!$alerts): ?>
                        <div class="empty-box">لا توجد تنبيهات حالياً.</div>
                    <?php else: ?>
                        <?php foreach ($alerts as $alert): ?>
                            <div class="alert-item <?= e($alert['type']) ?>"><?= e($alert['text']) ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php adminLayoutEnd(); ?>