<?php

function normalizeJordanPhone(?string $phone): ?string
{
    if ($phone === null) {
        return null;
    }

    $phone = trim($phone);

    if ($phone === '') {
        return null;
    }

    $phone = str_replace([" ", "-", "(", ")", "\t", "\n", "\r"], '', $phone);

    if (preg_match('/^07\d{8}$/', $phone)) {
        return '962' . substr($phone, 1);
    }

    if (preg_match('/^7\d{8}$/', $phone)) {
        return '962' . $phone;
    }

    if (preg_match('/^\+9627\d{8}$/', $phone)) {
        return substr($phone, 1);
    }

    if (preg_match('/^009627\d{8}$/', $phone)) {
        return substr($phone, 2);
    }

    if (preg_match('/^9627\d{8}$/', $phone)) {
        return $phone;
    }

    return null;
}

function buildWhatsAppWaMeLink(string $phone, string $message): string
{
    // محاولة فتح تطبيق واتساب مباشرة بدل الويب
    return 'whatsapp://send?phone=' . rawurlencode($phone) . '&text=' . rawurlencode($message);
}

function getBeneficiarySourceMap(): array
{
    return [
        'orphans' => [
            'table' => 'orphans',
            'name_column' => 'name',
            'phone_column' => 'contact_info',
        ],
        'poor_families' => [
            'table' => 'poor_families',
            'name_column' => 'head_name',
            'phone_column' => 'mobile',
        ],
        'sponsorships' => [
            'table' => 'sponsorships',
            'name_column' => 'orphan_name',
            'phone_column' => 'beneficiary_phone',
        ],
        'family_salaries' => [
            'table' => 'family_salaries',
            'name_column' => 'beneficiary_name',
            'phone_column' => 'beneficiary_phone',
        ],
    ];
}

function renderMessageTemplate(string $template, array $data): string
{
    $replacements = [
        '{name}' => (string)($data['name'] ?? ''),
        '{title}' => (string)($data['title'] ?? ''),
        '{date}' => (string)($data['date'] ?? ''),
        '{category}' => (string)($data['category'] ?? ''),
        '{amount}' => (string)($data['amount'] ?? ''),
        '{details}' => (string)($data['details'] ?? ''),
    ];

    return strtr($template, $replacements);
}

function getDefaultMessageTemplate(): string
{
    return "السلام عليكم {name}\n"
        . "نود إشعاركم بخصوص توزيع: {title}\n"
        . "نوع المساعدة: {category}\n"
        . "تاريخ الاستلام: {date}\n"
        . "قيمة المساعدة: {amount}\n"
        . "تفاصيل: {details}\n"
        . "يرجى مراجعة اللجنة في الموعد المحدد.";
}