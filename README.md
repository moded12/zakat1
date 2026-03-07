# نظام إدارة الزكاة والصدقات
## Zakat & Charity Management System

نظام متكامل لإدارة الأسر الفقيرة والأيتام والكفالات والتوزيعات الخيرية.
A complete Arabic RTL charity management system built with PHP and MySQL.

---

## المتطلبات / Requirements

- **PHP** 7.4 أو أحدث / PHP 7.4+
- **MySQL** 5.7 أو أحدث (أو MariaDB 10.3+) / MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` enabled
- PHP extensions: `PDO`, `PDO_MySQL`, `fileinfo`

---

## هيكل المشروع / Project Structure

```
zakat1/
├── admin/
│   ├── includes/
│   │   ├── config.php        # Database & app configuration
│   │   ├── db.php            # PDO singleton database class
│   │   ├── auth.php          # Authentication functions
│   │   ├── csrf.php          # CSRF protection helpers
│   │   ├── header.php        # Shared HTML header + sidebar
│   │   └── footer.php        # Shared HTML footer
│   ├── index.php             # Dashboard
│   ├── login.php             # Admin login page
│   ├── logout.php            # Logout handler
│   ├── families.php          # Poor families CRUD
│   ├── orphans.php           # Orphans CRUD
│   ├── sponsorships.php      # Sponsorships CRUD
│   ├── distributions.php     # Aid distributions CRUD
│   └── reports.php           # Reports & statistics
├── assets/
│   ├── css/style.css         # Custom RTL styles
│   └── js/app.js             # Frontend JavaScript
├── public/
│   └── index.php             # Public landing page
├── upload_files/             # Uploaded attachments (auto-created)
├── database.sql              # Full database schema & seed data
├── .htaccess                 # Apache rewrite rules
└── index.php                 # Root redirect
```

---

## التثبيت / Installation

### 1. إعداد قاعدة البيانات / Database Setup

```bash
mysql -u root -p < database.sql
```

أو استورد الملف `database.sql` عبر phpMyAdmin.

### 2. إنشاء مستخدم قاعدة البيانات / Create DB User

```sql
CREATE USER 'zakat1'@'localhost' IDENTIFIED BY 'Tvvcrtv1610@';
GRANT ALL PRIVILEGES ON zakat1.* TO 'zakat1'@'localhost';
FLUSH PRIVILEGES;
```

### 3. ضبط الإعدادات / Configuration

عدّل الملف `admin/includes/config.php` إذا لزم تغيير بيانات الاتصال:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'zakat1');
define('DB_USER', 'zakat1');
define('DB_PASS', 'your_password');
define('BASE_PATH', '/zakat1');  // URL base path
```

### 4. صلاحيات المجلدات / Folder Permissions

```bash
chmod 755 upload_files/
```

### 5. تفعيل mod_rewrite / Enable mod_rewrite

```bash
a2enmod rewrite
# Ensure AllowOverride All is set for the directory in Apache config
```

---

## بيانات الدخول الافتراضية / Default Login

| الحقل / Field     | القيمة / Value |
|-------------------|----------------|
| اسم المستخدم      | `admin`        |
| كلمة المرور       | `123@123`      |

> **تنبيه أمني**: غيّر كلمة المرور فور التثبيت.
> **Security Notice**: Change the default password immediately after installation.

---

## المميزات / Features

- 🏠 **الأسر الفقيرة** – إضافة وتعديل وحذف وبحث مع رفع مستندات
- 👶 **الأيتام** – إدارة كاملة لسجلات الأيتام مع مرفقات
- 🤝 **الكفالات** – ربط الكفالات بالأيتام مع متابعة حالة الكفالة
- 📦 **التوزيعات** – تسجيل وتتبع توزيعات المساعدات
- 📊 **التقارير** – تقارير شاملة مع فلاتر التاريخ والبحث
- 🔒 **الأمان** – CSRF protection, password hashing, PDO prepared statements
- 🖨️ **الطباعة** – دعم طباعة التقارير
- 📱 **متجاوب** – يعمل على الأجهزة المحمولة والحاسوب
- 🌍 **RTL عربي** – واجهة عربية كاملة من اليمين إلى اليسار

---

## التقنيات المستخدمة / Technologies

- **Backend**: PHP 7.4+ (PDO, OOP)
- **Database**: MySQL / MariaDB
- **Frontend**: Bootstrap 5.3 RTL, Bootstrap Icons, Cairo Font (Google Fonts)
- **Security**: CSRF tokens, `password_hash()`, prepared statements, `htmlspecialchars()`

---

## الترخيص / License

هذا المشروع مفتوح المصدر للاستخدام الخيري.
This project is open-source for charitable use.
