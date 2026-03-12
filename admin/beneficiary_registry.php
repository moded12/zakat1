<?php

function getBeneficiaryRegistry(): array
{
    return [
        'poor_families' => [
            'label' => 'الأسر الفقيرة',
            'table' => 'poor_families',
            'number_col' => 'file_number',
            'name_col' => 'head_name',
            'id_col' => 'id_number',
            'phone_col' => 'mobile',
        ],
        'orphans' => [
            'label' => 'الأيتام',
            'table' => 'orphans',
            'number_col' => 'file_number',
            'name_col' => 'name',
            'id_col' => 'id_number',
            'phone_col' => 'contact_info',
        ],
        'sponsorships' => [
            'label' => 'الكفالات',
            'table' => 'sponsorships',
            'number_col' => 'sponsorship_number',
            'name_col' => 'orphan_name',
            'id_col' => 'beneficiary_id_number',
            'phone_col' => 'beneficiary_phone',
        ],
        'family_salaries' => [
            'label' => 'رواتب الأسر',
            'table' => 'family_salaries',
            'number_col' => 'salary_number',
            'name_col' => 'beneficiary_name',
            'id_col' => 'beneficiary_id_number',
            'phone_col' => 'beneficiary_phone',
        ],
    ];
}

function validBeneficiaryType(string $type): bool
{
    $reg = getBeneficiaryRegistry();
    return isset($reg[$type]);
}

function beneficiaryTypeLabel(string $type): string
{
    $reg = getBeneficiaryRegistry();
    return $reg[$type]['label'] ?? $type;
}

function beneficiaryMeta(string $type): array
{
    $reg = getBeneficiaryRegistry();
    return $reg[$type] ?? [];
}