-- نظام إدارة الزكاة والصدقات
-- Arabic RTL Charity Management System Database Schema

CREATE DATABASE IF NOT EXISTS zakat1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE zakat1;

-- =============================================
-- جدول المشرفين
-- =============================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admins (username, password, full_name) VALUES
('admin', '$2y$10$YMG4oj9hUKJANyqMcvGVzekc36ZWEVjDnmMdlcmmufxmG5QUzdF8a', 'المدير')
ON DUPLICATE KEY UPDATE password=VALUES(password);

-- =============================================
-- جدول الأسر الفقيرة
-- =============================================
CREATE TABLE IF NOT EXISTS poor_families (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_number VARCHAR(20) UNIQUE,
    head_name VARCHAR(100) NOT NULL,
    members_count INT DEFAULT 1,
    phone VARCHAR(20),
    address TEXT,
    work_status ENUM('يعمل','لا يعمل','متوفى','متقاعد') DEFAULT 'لا يعمل',
    income DECIMAL(10,2) DEFAULT 0.00,
    need_type ENUM('غذائية','مالية','علاجية','تعليمية','مختلطة') DEFAULT 'غذائية',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- جدول الأيتام
-- =============================================
CREATE TABLE IF NOT EXISTS orphans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_number VARCHAR(20) UNIQUE,
    name VARCHAR(100) NOT NULL,
    birth_date DATE,
    gender ENUM('ذكر','أنثى') DEFAULT 'ذكر',
    mother_name VARCHAR(100),
    guardian_name VARCHAR(100),
    contact VARCHAR(20),
    address TEXT,
    education ENUM('روضة','ابتدائي','متوسط','ثانوي','جامعي','لا يتعلم') DEFAULT 'ابتدائي',
    health TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- جدول الكفالات
-- =============================================
CREATE TABLE IF NOT EXISTS sponsorships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sponsorship_number VARCHAR(20) UNIQUE,
    orphan_id INT,
    sponsor_name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) DEFAULT 0.00,
    start_date DATE,
    end_date DATE,
    status ENUM('نشطة','منتهية','موقوفة') DEFAULT 'نشطة',
    payment_method ENUM('نقدي','تحويل بنكي','شيك') DEFAULT 'نقدي',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sponsorship_orphan FOREIGN KEY (orphan_id) REFERENCES orphans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- جدول التوزيعات
-- =============================================
CREATE TABLE IF NOT EXISTS distributions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aid_type ENUM('سلة غذائية','مساعدة مالية','ملابس','أدوية','مستلزمات مدرسية','أخرى') DEFAULT 'سلة غذائية',
    beneficiary_name VARCHAR(100) NOT NULL,
    category ENUM('أسرة فقيرة','يتيم','أخرى') DEFAULT 'أسرة فقيرة',
    distribution_date DATE,
    quantity_amount VARCHAR(50),
    delivery_status ENUM('تم التسليم','قيد التسليم','لم يُسلَّم') DEFAULT 'قيد التسليم',
    responsible VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- جدول المرفقات
-- =============================================
CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('family','orphan') NOT NULL,
    entity_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
