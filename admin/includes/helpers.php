<?php
// ─── Shared helper functions ──────────────────────────────────────────────────

/**
 * Handle a file upload, validate it, and store metadata in the attachments table.
 * Returns true on success, false on failure (with optional error message set).
 */
function handleFileUpload(array $file, string $entityType, int $entityId, PDO $pdo, string &$uploadError = ''): bool {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return false; // No file submitted — not an error condition
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadError = match($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'حجم الملف يتجاوز الحد المسموح به',
            UPLOAD_ERR_PARTIAL   => 'لم يكتمل رفع الملف',
            default              => 'حدث خطأ أثناء رفع الملف',
        };
        return false;
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        $uploadError = 'حجم الملف يتجاوز الحد المسموح به (5 ميجابايت)';
        return false;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        $uploadError = 'نوع الملف غير مسموح به. الأنواع المقبولة: jpg, jpeg, png, pdf, doc, docx';
        return false;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'image/jpeg', 'image/png',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    if (!in_array($mime, $allowedMimes, true)) {
        $uploadError = 'محتوى الملف لا يطابق الامتداد المحدد';
        return false;
    }
    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest    = UPLOAD_DIR . $newName;
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $uploadError = 'فشل حفظ الملف على الخادم';
        return false;
    }
    $stmt = $pdo->prepare('INSERT INTO attachments (entity_type, entity_id, file_name, file_path) VALUES (?,?,?,?)');
    $stmt->execute([$entityType, $entityId, htmlspecialchars(basename($file['name']), ENT_QUOTES, 'UTF-8'), $newName]);
    return true;
}
