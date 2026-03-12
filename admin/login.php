<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE_PATH . '/admin/index.php');
    exit;
}

$pdo = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'رمز الحماية غير صالح';
    } else {
        $username = 'admin';
        $password = trim($_POST['password'] ?? '');

        if ($password === '') {
            $error = 'يرجى إدخال كلمة المرور';
        } else {
            if (loginAdmin($username, $password, $pdo)) {
                header('Location: ' . BASE_PATH . '/admin/index.php');
                exit;
            } else {
                $error = 'كلمة المرور غير صحيحة';
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
<title>تسجيل الدخول</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
body {
    margin: 0;
    min-height: 100vh;
    font-family: 'Cairo', sans-serif;
    background: linear-gradient(135deg, #1d4f88, #2c5f9b, #356cab);
    display: flex;
    align-items: center;
    justify-content: center;
}
.login-card {
    width: 100%;
    max-width: 430px;
    background: #fff;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0,0,0,.18);
}
.login-header {
    background: linear-gradient(180deg, #1f4d85 0%, #2d5c97 100%);
    color: #fff;
    text-align: center;
    padding: 2rem 1.5rem;
}
.login-header .icon {
    font-size: 3rem;
    color: #fbbf24;
    margin-bottom: .75rem;
}
.login-header h1 {
    font-size: 1.9rem;
    font-weight: 800;
    margin-bottom: .35rem;
}
.login-header p {
    margin: 0;
    color: rgba(255,255,255,.85);
    font-size: 1rem;
}
.login-body {
    padding: 2rem;
}
.form-label {
    font-weight: 700;
    color: #1f2937;
}
.form-control, .input-group-text {
    border-radius: 14px;
    min-height: 50px;
}
.fixed-user {
    background: #f8fafc;
    font-weight: 700;
}
.btn-login {
    background: #244d86;
    border: none;
    border-radius: 14px;
    min-height: 52px;
    font-size: 1.15rem;
    font-weight: 700;
}
.btn-login:hover {
    background: #1e4375;
}
.login-footer {
    text-align: center;
    color: #64748b;
    font-size: .95rem;
    padding: 1rem 1.5rem 1.3rem;
    border-top: 1px solid #e5e7eb;
    background: #f8fafc;
}
</style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="icon"><i class="bi bi-heart-fill"></i></div>
            <h1>نظام إدارة الزكاة والصدقات</h1>
            <p>تسجيل دخول المدير</p>
        </div>

        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger text-center"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfInput() ?>

                <div class="mb-3">
                    <label class="form-label">اسم المستخدم</label>
                    <input type="text" class="form-control fixed-user" value="admin" readonly>
                </div>

                <div class="mb-4">
                    <label class="form-label">كلمة المرور</label>
                    <input type="password" name="password" class="form-control" placeholder="أدخل كلمة المرور" required autofocus>
                </div>

                <button type="submit" class="btn btn-primary btn-login w-100">
                    <i class="bi bi-box-arrow-in-left ms-1"></i>
                    تسجيل الدخول
                </button>
            </form>
        </div>

        <div class="login-footer">
            نظام إدارة الزكاة والصدقات © 2026
        </div>
    </div>
</body>
</html>