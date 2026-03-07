<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: ' . BASE_PATH . '/admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'رمز الأمان غير صحيح، يرجى المحاولة مجدداً';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($username === '' || $password === '') {
            $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
        } else {
            try {
                $pdo = getDB();
                if (loginAdmin($username, $password, $pdo)) {
                    header('Location: ' . BASE_PATH . '/admin/index.php');
                    exit;
                } else {
                    $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
                }
            } catch (Exception $e) {
                $error = 'حدث خطأ في الاتصال بقاعدة البيانات';
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
<title>تسجيل الدخول – نظام إدارة الزكاة والصدقات</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
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
  }
  .login-wrapper {
    width: 100%;
    max-width: 440px;
    padding: 1rem;
  }
  .login-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.35);
    overflow: hidden;
  }
  .login-header {
    background: linear-gradient(135deg, #1a365d, #2c5282);
    color: white;
    padding: 2.5rem 2rem 2rem;
    text-align: center;
  }
  .login-header .logo-icon {
    font-size: 3.5rem;
    display: block;
    margin-bottom: 0.75rem;
  }
  .login-header h1 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
  }
  .login-header p {
    font-size: 0.9rem;
    opacity: 0.8;
    margin: 0.3rem 0 0;
  }
  .login-body {
    padding: 2rem;
    background: white;
  }
  .form-label {
    font-weight: 600;
    color: #2d3748;
  }
  .form-control {
    border-radius: 10px;
    padding: 0.65rem 1rem;
    border-color: #cbd5e0;
    font-family: 'Cairo', sans-serif;
  }
  .form-control:focus {
    border-color: #2c5282;
    box-shadow: 0 0 0 0.2rem rgba(44,82,130,0.2);
  }
  .input-group-text {
    border-radius: 10px 0 0 10px;
    background: #edf2f7;
    border-color: #cbd5e0;
    color: #4a5568;
  }
  .btn-login {
    background: linear-gradient(135deg, #1a365d, #2c5282);
    border: none;
    border-radius: 10px;
    font-size: 1.05rem;
    font-weight: 600;
    padding: 0.75rem;
    letter-spacing: 0.5px;
  }
  .btn-login:hover {
    background: linear-gradient(135deg, #2c5282, #2b6cb0);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(44,82,130,0.4);
  }
  .alert {
    border-radius: 10px;
    font-size: 0.92rem;
  }
  .login-footer {
    text-align: center;
    padding: 1rem 2rem;
    background: #f7fafc;
    border-top: 1px solid #e2e8f0;
    font-size: 0.82rem;
    color: #718096;
  }
</style>
</head>
<body>
<div class="login-wrapper">
  <div class="login-card card">
    <div class="login-header">
      <span class="logo-icon"><i class="bi bi-heart-fill text-warning"></i></span>
      <h1>نظام إدارة الزكاة والصدقات</h1>
      <p>تسجيل دخول المشرفين والإداريين</p>
    </div>
    <div class="login-body">
      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>
      <form method="POST" action="" novalidate>
        <?= csrfInput() ?>
        <div class="mb-3">
          <label for="username" class="form-label">
            <i class="bi bi-person-fill me-1 text-primary"></i> اسم المستخدم
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input
              type="text"
              class="form-control"
              id="username"
              name="username"
              placeholder="أدخل اسم المستخدم"
              value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              required
              autocomplete="username"
              autofocus
            >
          </div>
        </div>
        <div class="mb-4">
          <label for="password" class="form-label">
            <i class="bi bi-lock-fill me-1 text-primary"></i> كلمة المرور
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input
              type="password"
              class="form-control"
              id="password"
              name="password"
              placeholder="أدخل كلمة المرور"
              required
              autocomplete="current-password"
            >
            <button class="btn btn-outline-secondary" type="button" id="togglePassword" tabindex="-1" title="إظهار/إخفاء كلمة المرور">
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>
        <div class="d-grid">
          <button type="submit" class="btn btn-primary btn-login text-white">
            <i class="bi bi-box-arrow-in-right me-2"></i> تسجيل الدخول
          </button>
        </div>
      </form>
    </div>
    <div class="login-footer">
      نظام إدارة الزكاة والصدقات &copy; <?= date('Y') ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('togglePassword').addEventListener('click', function () {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
      pwd.type = 'text';
      icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
      pwd.type = 'password';
      icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
  });
</script>
</body>
</html>
