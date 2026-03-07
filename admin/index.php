<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$pdo = getDB();

function getCount(PDO $pdo, string $table, string $where = ''): int
{
    $sql = "SELECT COUNT(*) FROM {$table}";
    if ($where !== '') {
        $sql .= " WHERE {$where}";
    }
    return (int) $pdo->query($sql)->fetchColumn();
}

$totalFamilies = getCount($pdo, 'poor_families');
$totalOrphans = getCount($pdo, 'orphans');
$totalSponsorships = getCount($pdo, 'sponsorships');
$activeSponsorships = getCount($pdo, 'sponsorships', "status = 'نشطة'");
$totalDistributions = getCount($pdo, 'distributions');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>لوحة التحكم - نظام إدارة الزكاة والصدقات</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
    body {
        font-family: 'Cairo', sans-serif;
        background: #f4f7fb;
        color: #1f2937;
    }
    .sidebar {
        min-height: 100vh;
        background: linear-gradient(180deg, #163d6b 0%, #1d4f88 100%);
        color: #fff;
        padding: 1.25rem 1rem;
        position: sticky;
        top: 0;
    }
    .brand {
        padding: 1rem 0 1.5rem;
        border-bottom: 1px solid rgba(255,255,255,0.15);
        margin-bottom: 1rem;
    }
    .brand h3 {
        margin: 0;
        font-weight: 800;
        font-size: 1.2rem;
    }
    .brand small {
        color: rgba(255,255,255,0.8);
    }
    .nav-link {
        color: #e5eefb;
        border-radius: 12px;
        padding: 0.85rem 1rem;
        margin-bottom: 0.45rem;
        transition: all .2s ease;
        font-weight: 600;
    }
    .nav-link:hover,
    .nav-link.active {
        background: rgba(255,255,255,0.14);
        color: #fff;
    }
    .nav-link.logout {
        background: rgba(220, 53, 69, 0.18);
        color: #fff;
    }
    .content {
        padding: 2rem;
    }
    .page-title h1 {
        font-size: 1.8rem;
        font-weight: 800;
        margin-bottom: .35rem;
    }
    .page-title p {
        color: #6b7280;
        margin-bottom: 0;
    }
    .stat-card {
        border: none;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .stat-card .card-body {
        padding: 1.4rem;
    }
    .stat-icon {
        width: 58px;
        height: 58px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        margin-bottom: 1rem;
    }
    .icon-families { background: #dbeafe; color: #1d4ed8; }
    .icon-orphans { background: #dcfce7; color: #15803d; }
    .icon-sponsorships { background: #fef3c7; color: #b45309; }
    .icon-distributions { background: #fce7f3; color: #be185d; }

    .stat-number {
        font-size: 2rem;
        font-weight: 800;
        color: #111827;
    }
    .quick-card {
        border: none;
        border-radius: 18px;
        box-shadow: 0 10px 24px rgba(0,0,0,0.06);
    }
    .quick-card a {
        text-decoration: none;
    }
    .welcome-box {
        background: linear-gradient(135deg, #163d6b, #2b6cb0);
        color: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 14px 35px rgba(22, 61, 107, 0.25);
    }
    .welcome-box h2 {
        font-weight: 800;
        margin-bottom: .4rem;
    }
    .welcome-box p {
        margin-bottom: 0;
        color: rgba(255,255,255,0.85);
    }
    @media (max-width: 991.98px) {
        .sidebar {
            min-height: auto;
            position: relative;
        }
        .content {
            padding: 1rem;
        }
    }
</style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-lg-3 col-xl-2 sidebar">
            <div class="brand">
                <h3><i class="bi bi-heart-fill text-warning ms-2"></i>إدارة الزكاة</h3>
                <small>مرحبًا، <?= htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_username'], ENT_QUOTES, 'UTF-8') ?></small>
            </div>

            <nav class="nav flex-column">
                <a class="nav-link active" href="<?= BASE_PATH ?>/admin/index.php">
                    <i class="bi bi-speedometer2 ms-2"></i> لوحة التحكم
                </a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/poor_families.php">
                    <i class="bi bi-people-fill ms-2"></i> الأسر الفقيرة
                </a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/orphans.php">
                    <i class="bi bi-person-hearts ms-2"></i> الأيتام
                </a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/sponsorships.php">
                    <i class="bi bi-cash-coin ms-2"></i> كفالة الأيتام
                </a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/distributions.php">
                    <i class="bi bi-box-seam ms-2"></i> التوزيعات
                </a>
                <a class="nav-link" href="<?= BASE_PATH ?>/admin/reports.php">
                    <i class="bi bi-bar-chart-line-fill ms-2"></i> التقارير
                </a>
                <a class="nav-link logout" href="<?= BASE_PATH ?>/admin/logout.php">
                    <i class="bi bi-box-arrow-right ms-2"></i> تسجيل الخروج
                </a>
            </nav>
        </aside>

        <main class="col-lg-9 col-xl-10 content">
            <div class="page-title mb-4">
                <h1>لوحة التحكم</h1>
                <p>إحصائيات مختصرة وإدارة أقسام النظام</p>
            </div>

            <div class="welcome-box mb-4">
                <h2>مرحبًا بك في نظام إدارة الزكاة والصدقات</h2>
                <p>يمكنك من هنا إدارة الأسر الفقيرة والأيتام والكفالات والتوزيعات والتقارير بسهولة.</p>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="stat-icon icon-families">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <h6 class="text-muted mb-2">الأسر الفقيرة</h6>
                            <div class="stat-number"><?= $totalFamilies ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="stat-icon icon-orphans">
                                <i class="bi bi-person-hearts"></i>
                            </div>
                            <h6 class="text-muted mb-2">الأيتام</h6>
                            <div class="stat-number"><?= $totalOrphans ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="stat-icon icon-sponsorships">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                            <h6 class="text-muted mb-2">الكفالات</h6>
                            <div class="stat-number"><?= $totalSponsorships ?></div>
                            <small class="text-success">النشطة: <?= $activeSponsorships ?></small>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="stat-icon icon-distributions">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <h6 class="text-muted mb-2">التوزيعات</h6>
                            <div class="stat-number"><?= $totalDistributions ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-xl-4">
                    <div class="card quick-card h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">إدارة الأسر الفقيرة</h5>
                            <p class="text-muted">إضافة وتعديل وحذف وعرض والبحث في ملفات الأسر المستفيدة.</p>
                            <a href="<?= BASE_PATH ?>/admin/poor_families.php" class="btn btn-primary">
                                الانتقال للقسم
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-4">
                    <div class="card quick-card h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">إدارة الأيتام</h5>
                            <p class="text-muted">متابعة بيانات الأيتام وحفظ الملاحظات والمرفقات الخاصة بهم.</p>
                            <a href="<?= BASE_PATH ?>/admin/orphans.php" class="btn btn-success">
                                الانتقال للقسم
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-4">
                    <div class="card quick-card h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">إدارة الكفالات</h5>
                            <p class="text-muted">تنظيم بيانات الكفلاء ومبالغ الكفالة والحالة وتواريخ البداية والنهاية.</p>
                            <a href="<?= BASE_PATH ?>/admin/sponsorships.php" class="btn btn-warning text-dark">
                                الانتقال للقسم
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-6">
                    <div class="card quick-card h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">إدارة التوزيعات</h5>
                            <p class="text-muted">تسجيل أنواع المساعدات والمستفيدين وحالة التسليم والمسؤولين.</p>
                            <a href="<?= BASE_PATH ?>/admin/distributions.php" class="btn btn-info text-white">
                                الانتقال للقسم
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-6">
                    <div class="card quick-card h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">التقارير والإحصائيات</h5>
                            <p class="text-muted">عرض تقارير قابلة للبحث والطباعة حسب الأقسام والتواريخ والحالة.</p>
                            <a href="<?= BASE_PATH ?>/admin/reports.php" class="btn btn-dark">
                                عرض التقارير
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>