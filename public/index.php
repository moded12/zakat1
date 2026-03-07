<?php
// Public landing page
require_once dirname(__DIR__) . '/admin/includes/config.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>نظام إدارة الزكاة والصدقات</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  body {
    font-family: 'Cairo', sans-serif;
    background: linear-gradient(135deg, #1a365d 0%, #2c5282 50%, #2b6cb0 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
  }
  .landing-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    padding: 2.5rem 2rem;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
    min-height: 260px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
  }
  .landing-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.4);
  }
  .card-icon {
    font-size: 3.5rem;
    margin-bottom: 1rem;
    display: block;
  }
  .card-title-ar {
    font-size: 1.4rem;
    font-weight: 700;
  }
  .card-subtitle-ar {
    font-size: 0.95rem;
    opacity: 0.7;
    margin-top: 0.4rem;
  }
  .main-title {
    color: white;
    text-align: center;
    margin-bottom: 0.75rem;
    font-size: 2.2rem;
    font-weight: 700;
    text-shadow: 0 2px 12px rgba(0, 0, 0, 0.35);
  }
  .main-subtitle {
    color: rgba(255, 255, 255, 0.82);
    font-size: 1rem;
    text-align: center;
    margin-bottom: 3rem;
  }
  a.card-link {
    text-decoration: none;
  }
  .system-version {
    color: rgba(255,255,255,0.5);
    text-align: center;
    font-size: 0.8rem;
    margin-top: 2.5rem;
  }
</style>
</head>
<body>
<div class="container">
  <div class="text-center">
    <h1 class="main-title">
      <i class="bi bi-heart-fill text-warning me-2"></i>
      نظام إدارة الزكاة والصدقات
    </h1>
    <p class="main-subtitle">نظام متكامل لإدارة الأسر الفقيرة والأيتام والكفالات والتوزيعات الخيرية</p>
  </div>

  <div class="row justify-content-center g-4">
    <!-- Admin Card -->
    <div class="col-12 col-sm-8 col-md-5 col-lg-4">
      <a href="<?= BASE_PATH ?>/admin/login.php" class="card-link">
        <div class="landing-card bg-white">
          <span class="card-icon text-primary">
            <i class="bi bi-shield-lock-fill"></i>
          </span>
          <div class="card-title-ar text-dark">دخول الإدارة</div>
          <div class="card-subtitle-ar text-muted">لوحة تحكم المشرفين والإداريين</div>
          <div class="mt-4">
            <span class="btn btn-primary px-4 py-2 rounded-pill">
              دخول <i class="bi bi-arrow-left-circle me-1"></i>
            </span>
          </div>
        </div>
      </a>
    </div>

    <!-- Coming Soon Card -->
    <div class="col-12 col-sm-8 col-md-5 col-lg-4">
      <div class="landing-card bg-white" style="opacity:0.75;cursor:default;">
        <span class="card-icon text-secondary">
          <i class="bi bi-clock-history"></i>
        </span>
        <div class="card-title-ar text-muted">قريباً</div>
        <div class="card-subtitle-ar text-muted">هذا القسم قيد التطوير</div>
        <div class="mt-4">
          <span class="badge bg-secondary fs-6 px-4 py-2 rounded-pill">قريبًا</span>
        </div>
      </div>
    </div>
  </div>

  <p class="system-version">
    نظام إدارة الزكاة والصدقات &copy; <?= date('Y') ?> — الإصدار 1.0
  </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
