<?php

if (!function_exists('normalizeBeneficiaryType')) {
    function normalizeBeneficiaryType($type)
    {
        $type = trim((string)$type);

        $map = array(
            '1'               => 'family_salaries',

            'poor_families'   => 'poor_families',
            'poor_family'     => 'poor_families',
            'family'          => 'poor_families',
            'families'        => 'poor_families',

            'orphans'         => 'orphans',
            'orphan'          => 'orphans',

            'sponsorships'    => 'sponsorships',
            'sponsorship'     => 'sponsorships',

            'family_salaries' => 'family_salaries',
            'salary'          => 'family_salaries',
            'salaries'        => 'family_salaries',
        );

        return isset($map[$type]) ? $map[$type] : $type;
    }
}

if (!function_exists('validBeneficiaryType')) {
    function validBeneficiaryType($type)
    {
        return in_array($type, array('poor_families', 'orphans', 'sponsorships', 'family_salaries'), true);
    }
}

if (!function_exists('beneficiaryTypeLabel')) {
    function beneficiaryTypeLabel($type)
    {
        if ($type === 'poor_families') return 'الأسر الفقيرة';
        if ($type === 'orphans') return 'الأيتام';
        if ($type === 'sponsorships') return 'الكفالات';
        if ($type === 'family_salaries') return 'رواتب الأسر';
        return $type;
    }
}

if (!function_exists('beneficiaryTable')) {
    function beneficiaryTable($type)
    {
        if ($type === 'poor_families') return 'poor_families';
        if ($type === 'orphans') return 'orphans';
        if ($type === 'sponsorships') return 'sponsorships';
        return 'family_salaries';
    }
}

if (!function_exists('beneficiaryNameColumn')) {
    function beneficiaryNameColumn($type)
    {
        if ($type === 'poor_families') return 'head_name';
        if ($type === 'orphans') return 'name';
        if ($type === 'sponsorships') return 'orphan_name';
        return 'beneficiary_name';
    }
}

if (!function_exists('beneficiaryNumberColumn')) {
    function beneficiaryNumberColumn($type)
    {
        if ($type === 'sponsorships') return 'sponsorship_number';
        if ($type === 'family_salaries') return 'salary_number';
        return 'file_number';
    }
}

if (!function_exists('beneficiaryIdNumberColumn')) {
    function beneficiaryIdNumberColumn($type)
    {
        if ($type === 'sponsorships') return 'beneficiary_id_number';
        if ($type === 'family_salaries') return 'beneficiary_id_number';
        return 'id_number';
    }
}

if (!function_exists('beneficiaryPhoneColumn')) {
    function beneficiaryPhoneColumn($type)
    {
        if ($type === 'poor_families') return 'mobile';
        if ($type === 'orphans') return 'contact_info';
        if ($type === 'sponsorships') return 'beneficiary_phone';
        return 'beneficiary_phone';
    }
}

if (!function_exists('guessBeneficiaryTypeFromTitle')) {
    function guessBeneficiaryTypeFromTitle($title)
    {
        $title = trim((string)$title);

        if ($title === '') {
            return '';
        }

        if (mb_strpos($title, 'راتب') !== false || mb_strpos($title, 'رواتب') !== false) {
            return 'family_salaries';
        }

        if (mb_strpos($title, 'كفالة') !== false || mb_strpos($title, 'كفالات') !== false) {
            return 'sponsorships';
        }

        if (mb_strpos($title, 'يتيم') !== false || mb_strpos($title, 'أيتام') !== false) {
            return 'orphans';
        }

        if (mb_strpos($title, 'أسرة') !== false || mb_strpos($title, 'أسر') !== false) {
            return 'poor_families';
        }

        return '';
    }
}

if (!function_exists('resolveBeneficiaryType')) {
    function resolveBeneficiaryType($type, $title)
    {
        $normalized = normalizeBeneficiaryType((string)$type);

        if ($normalized !== '') {
            return $normalized;
        }

        return guessBeneficiaryTypeFromTitle((string)$title);
    }
}