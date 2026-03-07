<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
checkAuth();

try {
    $pdo = getDB();

    $familiesCount      = (int) $pdo->query('SELECT COUNT(*) FROM poor_families')->fetchColumn();
    $orphansCount       = (int) $pdo->query('SELECT COUNT(*) FROM orphans')->fetchColumn();
    $activeSponsors     = (int) $pdo->query("SELECT COUNT(*) FROM sponsorships WHERE status='نشطة'")->fetchColumn();
    $monthDistributions = (int) $pdo->query(
        'SELECT COUNT(*) FROM distributions WHERE MONTH(distribution_date)=MONTH(NOW()) AND YEAR(distribution_date)=YEAR(NOW())'
    )->fetchColumn();

    $recentDistributions = $pdo->query(
        'SELECT * FROM distributions ORDER BY created_at DESC LIMIT 5'
    )->fetchAll();
} catch (Exception $e) {
    $familiesCount = $orphansCount = $activeSponsors = $monthDistributions = 0;
    $recentDistributions = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1"><i class="bi bi-speedometer2 text-primary me-2"></i>لوحة التحكم</h4>
    <p class="text-muted small mb-0">نظرة عامة على أنشطة النظام</p>
  </div>
  <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= date('l، j F Y') ?></span>
</div>

<!-- Stat Cards -->
<div class="row g-4 mb-5">
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card border-start border-primary border-4">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
          <i class="bi bi-people-fill text-primary fs-3"></i>
        </div>
        <div>
          <div class="fs-2 fw-bold text-primary"><?= $familiesCount ?></div>
          <div class="text-muted small fw-semibold">الأسر المسجّلة</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card border-start border-success border-4">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-success bg-opacity-10 p-3">
          <i class="bi bi-person-heart text-success fs-3"></i>
        </div>
        <div>
          <div class="fs-2 fw-bold text-success"><?= $orphansCount ?></div>
          <div class="text-muted small fw-semibold">الأيتام المسجّلون</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card border-start border-warning border-4">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-warning bg-opacity-10 p-3">
          <i class="bi bi-hand-thumbs-up-fill text-warning fs-3"></i>
        </div>
        <div>
          <div class="fs-2 fw-bold text-warning"><?= $activeSponsors ?></div>
          <div class="text-muted small fw-semibold">الكفالات النشطة</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card border-start border-info border-4">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-info bg-opacity-10 p-3">
          <i class="bi bi-box-seam-fill text-info fs-3"></i>
        </div>
        <div>
          <div class="fs-2 fw-bold text-info"><?= $monthDistributions ?></div>
          <div class="text-muted small fw-semibold">توزيعات هذا الشهر</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Quick Nav -->
<div class="row g-3 mb-5">
  <div class="col-12">
    <h5 class="fw-bold mb-3"><i class="bi bi-grid-fill text-secondary me-2"></i>الوصول السريع</h5>
  </div>
  <?php
  $quickLinks = [
    ['url' => BASE_PATH . '/admin/families.php',      'icon' => 'bi-people-fill',                 'color' => 'primary', 'label' => 'الأسر الفقيرة',  'desc' => 'إضافة وإدارة الأسر'],
    ['url' => BASE_PATH . '/admin/orphans.php',       'icon' => 'bi-person-heart',                'color' => 'success', 'label' => 'الأيتام',        'desc' => 'سجلات الأيتام'],
    ['url' => BASE_PATH . '/admin/sponsorships.php',  'icon' => 'bi-hand-thumbs-up-fill',         'color' => 'warning', 'label' => 'الكفالات',       'desc' => 'إدارة الكفالات'],
    ['url' => BASE_PATH . '/admin/distributions.php', 'icon' => 'bi-box-seam-fill',               'color' => 'info',    'label' => 'التوزيعات',      'desc' => 'سجل التوزيعات'],
    ['url' => BASE_PATH . '/admin/reports.php',       'icon' => 'bi-file-earmark-bar-graph-fill', 'color' => 'danger',  'label' => 'التقارير',       'desc' => 'تقارير وإحصاءات'],
  ];
  foreach ($quickLinks as $link):
  ?>
  <div class="col-6 col-md-4 col-xl-2">
    <a href="<?= $link['url'] ?>" class="card text-decoration-none text-center p-3 h-100 stat-card">
      <i class="bi <?= $link['icon'] ?> text-<?= $link['color'] ?> fs-2 mb-2"></i>
      <div class="fw-semibold text-dark small"><?= $link['label'] ?></div>
      <div class="text-muted" style="font-size:0.75rem"><?= $link['desc'] ?></div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Recent Distributions -->
<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
    <h6 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary me-2"></i>آخر التوزيعات</h6>
    <a href="<?= BASE_PATH ?>/admin/distributions.php" class="btn btn-sm btn-outline-primary">عرض الكل</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>المستفيد</th>
            <th>نوع المساعدة</th>
            <th>الفئة</th>
            <th>تاريخ التوزيع</th>
            <th>حالة التسليم</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentDistributions)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">لا توجد بيانات بعد</td></tr>
          <?php else: ?>
          <?php foreach ($recentDistributions as $d): ?>
          <tr>
            <td><?= (int)$d['id'] ?></td>
            <td class="fw-semibold"><?= htmlspecialchars($d['beneficiary_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($d['aid_type'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($d['category'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($d['distribution_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?php
              $badge = match($d['delivery_status']) {
                  'تم التسليم'   => 'success',
                  'قيد التسليم'  => 'warning',
                  'لم يُسلَّم'   => 'danger',
                  default        => 'secondary',
              };
              ?>
              <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($d['delivery_status'], ENT_QUOTES, 'UTF-8') ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
