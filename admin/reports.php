<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
checkAuth();

$pdo = getDB();

// ─── Filters ──────────────────────────────────────────────────────────────────
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$search   = strip_tags(trim($_GET['search'] ?? ''));

// Validate date filters
$validateDate = function (string $d): string {
    if ($d === '') return '';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return ($dt && $dt->format('Y-m-d') === $d) ? $d : '';
};
$dateFrom = $validateDate($dateFrom);
$dateTo   = $validateDate($dateTo);

// ─── Helper: build WHERE clause ───────────────────────────────────────────────
function buildWhere(string $dateCol, string $dateFrom, string $dateTo, string $search, array $searchCols): array {
    $where  = [];
    $params = [];
    if ($dateFrom !== '') { $where[] = "$dateCol >= ?"; $params[] = $dateFrom; }
    if ($dateTo   !== '') { $where[] = "$dateCol <= ?"; $params[] = $dateTo; }
    if ($search   !== '') {
        $likeParts = array_map(fn($c) => "$c LIKE ?", $searchCols);
        $where[]   = '(' . implode(' OR ', $likeParts) . ')';
        $like = '%' . $search . '%';
        foreach ($searchCols as $_) { $params[] = $like; }
    }
    $sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    return [$sql, $params];
}

// ─── Families ────────────────────────────────────────────────────────────────
[$fWhere, $fParams] = buildWhere('created_at', $dateFrom, $dateTo, $search, ['head_name','file_number','phone']);
$families     = $pdo->prepare("SELECT * FROM poor_families $fWhere ORDER BY created_at DESC");
$families->execute($fParams);
$families     = $families->fetchAll();
$familiesTotal = count($families);

// ─── Orphans ──────────────────────────────────────────────────────────────────
[$oWhere, $oParams] = buildWhere('created_at', $dateFrom, $dateTo, $search, ['name','file_number','guardian_name']);
$orphans     = $pdo->prepare("SELECT * FROM orphans $oWhere ORDER BY created_at DESC");
$orphans->execute($oParams);
$orphans     = $orphans->fetchAll();
$orphansTotal = count($orphans);

// ─── Sponsorships ─────────────────────────────────────────────────────────────
[$sWhere, $sParams] = buildWhere('s.created_at', $dateFrom, $dateTo, $search, ['s.sponsor_name','s.sponsorship_number','o.name']);
$sponsorships = $pdo->prepare(
    "SELECT s.*, o.name AS orphan_name
     FROM sponsorships s LEFT JOIN orphans o ON s.orphan_id = o.id
     $sWhere ORDER BY s.created_at DESC"
);
$sponsorships->execute($sParams);
$sponsorships     = $sponsorships->fetchAll();
$sponsorshipsTotal = count($sponsorships);
$activeSponsors    = count(array_filter($sponsorships, fn($r) => $r['status'] === 'نشطة'));

// ─── Distributions ────────────────────────────────────────────────────────────
[$dWhere, $dParams] = buildWhere('distribution_date', $dateFrom, $dateTo, $search, ['beneficiary_name','responsible','aid_type']);
$distributions = $pdo->prepare("SELECT * FROM distributions $dWhere ORDER BY distribution_date DESC, created_at DESC");
$distributions->execute($dParams);
$distributions     = $distributions->fetchAll();
$distributionsTotal = count($distributions);
$deliveredCount     = count(array_filter($distributions, fn($r) => $r['delivery_status'] === 'تم التسليم'));

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
  <div>
    <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-bar-graph-fill text-danger me-2"></i>التقارير والإحصاءات</h4>
    <p class="text-muted small mb-0">استعراض وتصفية جميع البيانات</p>
  </div>
  <button class="btn btn-outline-dark" onclick="window.print()">
    <i class="bi bi-printer me-1"></i> طباعة
  </button>
</div>

<!-- Filter Form -->
<div class="card shadow-sm mb-4 no-print">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label fw-semibold small">من تاريخ</label>
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold small">إلى تاريخ</label>
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold small">بحث</label>
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="ابحث في جميع الأقسام…"
               value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel me-1"></i>تصفية</button>
      </div>
      <?php if ($dateFrom || $dateTo || $search): ?>
      <div class="col-12">
        <a href="<?= BASE_PATH ?>/admin/reports.php" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-x-circle me-1"></i>مسح الفلتر
        </a>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Print Header -->
<div class="d-none d-print-block text-center mb-4">
  <h3 class="fw-bold">نظام إدارة الزكاة والصدقات</h3>
  <p class="text-muted">تقرير شامل – <?= date('Y/m/d') ?></p>
  <?php if ($dateFrom || $dateTo): ?>
    <p>الفترة: <?= htmlspecialchars($dateFrom ?: '–', ENT_QUOTES, 'UTF-8') ?> إلى <?= htmlspecialchars($dateTo ?: '–', ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4 no-print" id="reportTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-families" data-bs-toggle="tab" data-bs-target="#panel-families" type="button" role="tab">
      <i class="bi bi-people-fill me-1 text-primary"></i> الأسر
      <span class="badge bg-primary ms-1"><?= $familiesTotal ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-orphans" data-bs-toggle="tab" data-bs-target="#panel-orphans" type="button" role="tab">
      <i class="bi bi-person-heart me-1 text-success"></i> الأيتام
      <span class="badge bg-success ms-1"><?= $orphansTotal ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-sponsorships" data-bs-toggle="tab" data-bs-target="#panel-sponsorships" type="button" role="tab">
      <i class="bi bi-hand-thumbs-up-fill me-1 text-warning"></i> الكفالات
      <span class="badge bg-warning text-dark ms-1"><?= $sponsorshipsTotal ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-distributions" data-bs-toggle="tab" data-bs-target="#panel-distributions" type="button" role="tab">
      <i class="bi bi-box-seam-fill me-1 text-info"></i> التوزيعات
      <span class="badge bg-info text-dark ms-1"><?= $distributionsTotal ?></span>
    </button>
  </li>
</ul>

<div class="tab-content" id="reportTabsContent">

  <!-- ══════ FAMILIES PANEL ══════ -->
  <div class="tab-pane fade show active" id="panel-families" role="tabpanel">
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card border-primary border-2 text-center p-3">
          <div class="fs-3 fw-bold text-primary"><?= $familiesTotal ?></div>
          <div class="small text-muted">إجمالي الأسر</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-success border-2 text-center p-3">
          <div class="fs-3 fw-bold text-success">
            <?= count(array_filter($families, fn($r) => $r['work_status'] === 'لا يعمل')) ?>
          </div>
          <div class="small text-muted">لا يعمل</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-info border-2 text-center p-3">
          <div class="fs-3 fw-bold text-info">
            <?= array_sum(array_column($families, 'members_count')) ?>
          </div>
          <div class="small text-muted">إجمالي الأفراد</div>
        </div>
      </div>
    </div>
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 table-sm">
            <thead>
              <tr>
                <th>#</th><th>رقم الملف</th><th>اسم رب الأسرة</th>
                <th>عدد الأفراد</th><th>الهاتف</th><th>حالة العمل</th>
                <th>نوع الاحتياج</th><th>الدخل</th><th>تاريخ الإضافة</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($families)): ?>
              <tr><td colspan="9" class="text-center text-muted py-4">لا توجد بيانات</td></tr>
              <?php else: ?>
              <?php foreach ($families as $f): ?>
              <tr>
                <td><?= (int)$f['id'] ?></td>
                <td><?= htmlspecialchars($f['file_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($f['head_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (int)$f['members_count'] ?></td>
                <td><?= htmlspecialchars($f['phone'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($f['work_status'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($f['need_type'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= number_format((float)$f['income'], 2) ?></td>
                <td><?= htmlspecialchars(substr($f['created_at'] ?? '', 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════ ORPHANS PANEL ══════ -->
  <div class="tab-pane fade" id="panel-orphans" role="tabpanel">
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card border-success border-2 text-center p-3">
          <div class="fs-3 fw-bold text-success"><?= $orphansTotal ?></div>
          <div class="small text-muted">إجمالي الأيتام</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-primary border-2 text-center p-3">
          <div class="fs-3 fw-bold text-primary">
            <?= count(array_filter($orphans, fn($r) => $r['gender'] === 'ذكر')) ?>
          </div>
          <div class="small text-muted">ذكور</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-danger border-2 text-center p-3">
          <div class="fs-3 fw-bold text-danger">
            <?= count(array_filter($orphans, fn($r) => $r['gender'] === 'أنثى')) ?>
          </div>
          <div class="small text-muted">إناث</div>
        </div>
      </div>
    </div>
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 table-sm">
            <thead>
              <tr>
                <th>#</th><th>رقم الملف</th><th>الاسم</th>
                <th>تاريخ الميلاد</th><th>الجنس</th><th>اسم الولي</th>
                <th>المرحلة الدراسية</th><th>تاريخ الإضافة</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($orphans)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">لا توجد بيانات</td></tr>
              <?php else: ?>
              <?php foreach ($orphans as $o): ?>
              <tr>
                <td><?= (int)$o['id'] ?></td>
                <td><?= htmlspecialchars($o['file_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($o['birth_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($o['gender'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($o['guardian_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($o['education'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(substr($o['created_at'] ?? '', 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════ SPONSORSHIPS PANEL ══════ -->
  <div class="tab-pane fade" id="panel-sponsorships" role="tabpanel">
    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="card border-warning border-2 text-center p-3">
          <div class="fs-3 fw-bold text-warning"><?= $sponsorshipsTotal ?></div>
          <div class="small text-muted">إجمالي الكفالات</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-success border-2 text-center p-3">
          <div class="fs-3 fw-bold text-success"><?= $activeSponsors ?></div>
          <div class="small text-muted">كفالات نشطة</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-info border-2 text-center p-3">
          <div class="fs-3 fw-bold text-info">
            <?= number_format(array_sum(array_column($sponsorships, 'amount')), 2) ?>
          </div>
          <div class="small text-muted">إجمالي المبالغ (ريال)</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-secondary border-2 text-center p-3">
          <div class="fs-3 fw-bold text-secondary">
            <?= count(array_filter($sponsorships, fn($r) => $r['status'] === 'منتهية')) ?>
          </div>
          <div class="small text-muted">كفالات منتهية</div>
        </div>
      </div>
    </div>
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 table-sm">
            <thead>
              <tr>
                <th>#</th><th>رقم الكفالة</th><th>اسم الكفيل</th>
                <th>اليتيم</th><th>المبلغ</th><th>من</th><th>إلى</th>
                <th>الحالة</th><th>طريقة الدفع</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($sponsorships)): ?>
              <tr><td colspan="9" class="text-center text-muted py-4">لا توجد بيانات</td></tr>
              <?php else: ?>
              <?php foreach ($sponsorships as $sp): ?>
              <tr>
                <td><?= (int)$sp['id'] ?></td>
                <td><?= htmlspecialchars($sp['sponsorship_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($sp['sponsor_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($sp['orphan_name'] ?? 'غير محدد', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= number_format((float)$sp['amount'], 2) ?></td>
                <td><?= htmlspecialchars($sp['start_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($sp['end_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($sp['status'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($sp['payment_method'], ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════ DISTRIBUTIONS PANEL ══════ -->
  <div class="tab-pane fade" id="panel-distributions" role="tabpanel">
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card border-info border-2 text-center p-3">
          <div class="fs-3 fw-bold text-info"><?= $distributionsTotal ?></div>
          <div class="small text-muted">إجمالي التوزيعات</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-success border-2 text-center p-3">
          <div class="fs-3 fw-bold text-success"><?= $deliveredCount ?></div>
          <div class="small text-muted">تم التسليم</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-danger border-2 text-center p-3">
          <div class="fs-3 fw-bold text-danger">
            <?= count(array_filter($distributions, fn($r) => $r['delivery_status'] === 'لم يُسلَّم')) ?>
          </div>
          <div class="small text-muted">لم يُسلَّم</div>
        </div>
      </div>
    </div>
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 table-sm">
            <thead>
              <tr>
                <th>#</th><th>نوع المساعدة</th><th>المستفيد</th>
                <th>الفئة</th><th>تاريخ التوزيع</th><th>الكمية/المبلغ</th>
                <th>حالة التسليم</th><th>المسؤول</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($distributions)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">لا توجد بيانات</td></tr>
              <?php else: ?>
              <?php foreach ($distributions as $dist): ?>
              <tr>
                <td><?= (int)$dist['id'] ?></td>
                <td><?= htmlspecialchars($dist['aid_type'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($dist['beneficiary_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($dist['category'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($dist['distribution_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($dist['quantity_amount'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($dist['delivery_status'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($dist['responsible'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div><!-- /.tab-content -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
