<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
checkAuth();

// Flash messages
$flash = '';
if (isset($_SESSION['flash'])) {
    $type = htmlspecialchars($_SESSION['flash']['type'], ENT_QUOTES, 'UTF-8');
    $msg  = htmlspecialchars($_SESSION['flash']['message'], ENT_QUOTES, 'UTF-8');
    $flash = "<div class=\"alert alert-{$type} alert-dismissible fade show\" role=\"alert\">"
           . $msg
           . "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"إغلاق\"></button></div>";
    unset($_SESSION['flash']);
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>نظام إدارة الزكاة والصدقات</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
</head>
<body>
<div class="d-flex" id="wrapper">

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <i class="bi bi-heart-fill text-warning"></i>
    <span>نظام الزكاة</span>
  </div>
  <nav class="sidebar-nav">
    <a href="<?= BASE_PATH ?>/admin/index.php" class="nav-item <?= $currentPage === 'index' ? 'active' : '' ?>">
      <i class="bi bi-speedometer2"></i> لوحة التحكم
    </a>
    <a href="<?= BASE_PATH ?>/admin/families.php" class="nav-item <?= $currentPage === 'families' ? 'active' : '' ?>">
      <i class="bi bi-people-fill"></i> الأسر الفقيرة
    </a>
    <a href="<?= BASE_PATH ?>/admin/orphans.php" class="nav-item <?= $currentPage === 'orphans' ? 'active' : '' ?>">
      <i class="bi bi-person-heart"></i> الأيتام
    </a>
    <a href="<?= BASE_PATH ?>/admin/sponsorships.php" class="nav-item <?= $currentPage === 'sponsorships' ? 'active' : '' ?>">
      <i class="bi bi-hand-thumbs-up-fill"></i> الكفالات
    </a>
    <a href="<?= BASE_PATH ?>/admin/distributions.php" class="nav-item <?= $currentPage === 'distributions' ? 'active' : '' ?>">
      <i class="bi bi-box-seam-fill"></i> التوزيعات
    </a>
    <a href="<?= BASE_PATH ?>/admin/reports.php" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
      <i class="bi bi-file-earmark-bar-graph-fill"></i> التقارير
    </a>
    <hr style="border-color:rgba(255,255,255,0.15);margin:0.5rem 1rem;">
    <a href="<?= BASE_PATH ?>/admin/logout.php" class="nav-item text-danger mt-1">
      <i class="bi bi-box-arrow-right"></i> تسجيل الخروج
    </a>
  </nav>
</div>

<!-- Page Content -->
<div id="page-content-wrapper">
  <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-3">
    <button class="btn btn-sm btn-outline-secondary" id="sidebarToggle" aria-label="تبديل القائمة الجانبية">
      <i class="bi bi-list fs-5"></i>
    </button>
    <span class="ms-3 text-muted small">
      <i class="bi bi-person-circle me-1"></i>
      مرحباً، <?= htmlspecialchars($_SESSION['admin_name'] ?? 'المدير', ENT_QUOTES, 'UTF-8') ?>
    </span>
    <span class="ms-auto badge bg-light text-dark border small">
      <i class="bi bi-clock me-1"></i><?= date('Y/m/d') ?>
    </span>
  </nav>
  <div class="container-fluid p-4">
    <?= $flash ?>
