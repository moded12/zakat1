<?php
require_once __DIR__ . '/config.php';

/**
 * تجنب خطأ Cannot redeclare e()
 */
if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function adminLayoutStart(string $title, string $active = ''): void
{
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
        <style>
            :root{
                --sidebar-bg-1:#14345f;
                --sidebar-bg-2:#1f4f88;
                --sidebar-text:#eaf2ff;
                --sidebar-muted:rgba(255,255,255,.68);
                --sidebar-hover:rgba(255,255,255,.09);
                --sidebar-active:rgba(255,255,255,.16);
                --sidebar-border:rgba(255,255,255,.12);
                --content-bg:#f4f7fb;
                --card-bg:#ffffff;
                --shadow:0 12px 28px rgba(15,23,42,.10);
            }

            *{ box-sizing:border-box; }

            body{
                font-family:'Cairo',sans-serif;
                background:var(--content-bg);
                margin:0;
                color:#1f2937;
            }

            .app{
                display:flex;
                min-height:100vh;
            }

            .sidebar{
                width:300px;
                background:linear-gradient(180deg,var(--sidebar-bg-1) 0%, var(--sidebar-bg-2) 100%);
                color:#fff;
                padding:18px 14px;
                position:sticky;
                top:0;
                height:100vh;
                overflow-y:auto;
                box-shadow:0 0 0 1px rgba(255,255,255,.03) inset;
            }

            .sidebar::-webkit-scrollbar{ width:8px; }
            .sidebar::-webkit-scrollbar-thumb{
                background:rgba(255,255,255,.18);
                border-radius:999px;
            }

            .brand{
                padding:10px 8px 16px;
                border-bottom:1px solid var(--sidebar-border);
                margin-bottom:14px;
            }

            .brand-title{
                display:flex;
                align-items:center;
                gap:8px;
                font-weight:800;
                font-size:28px;
                line-height:1.1;
                margin:0 0 4px 0;
            }

            .brand-title .heart{
                color:#facc15;
                font-size:24px;
            }

            .brand-sub{
                color:var(--sidebar-muted);
                font-size:15px;
                font-weight:600;
            }

            .side-group{
                margin-bottom:14px;
            }

            .side-group-toggle{
                width:100%;
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:10px;
                background:transparent;
                color:#fff;
                border:none;
                border-radius:14px;
                padding:12px 12px;
                font-size:16px;
                font-weight:800;
                cursor:pointer;
                transition:.2s ease;
                text-align:right;
            }

            .side-group-toggle:hover{
                background:var(--sidebar-hover);
            }

            .side-group-toggle.active{
                background:var(--sidebar-active);
            }

            .side-group-title{
                display:flex;
                align-items:center;
                gap:10px;
            }

            .side-group-title i{
                font-size:18px;
            }

            .side-group-arrow{
                transition:transform .2s ease;
                font-size:14px;
                color:var(--sidebar-muted);
            }

            .side-group.open .side-group-arrow{
                transform:rotate(180deg);
            }

            .side-children{
                display:none;
                margin-top:8px;
                padding:0 8px 0 0;
            }

            .side-group.open .side-children{
                display:block;
            }

            .side-link{
                display:flex;
                align-items:center;
                gap:10px;
                color:var(--sidebar-text);
                text-decoration:none;
                border-radius:14px;
                padding:11px 12px;
                margin-bottom:6px;
                border:1px solid transparent;
                transition:.2s ease;
                font-weight:700;
            }

            .side-link:hover{
                background:var(--sidebar-hover);
                color:#fff;
            }

            .side-link.active{
                background:var(--sidebar-active);
                color:#fff;
                border-color:rgba(255,255,255,.08);
            }

            .side-link.child{
                margin-right:8px;
                font-size:15px;
                font-weight:700;
                background:rgba(255,255,255,.03);
            }

            .side-link.child i{
                font-size:16px;
                color:#dbeafe;
            }

            .side-separator{
                border-top:1px solid var(--sidebar-border);
                margin:14px 4px;
            }

            .logout-link{
                background:rgba(220,53,69,.20);
            }

            .logout-link:hover{
                background:rgba(220,53,69,.32);
            }

            .content{
                flex:1;
                padding:20px;
                min-width:0;
            }

            .mobile-topbar{
                display:none;
                position:sticky;
                top:0;
                z-index:1040;
                background:#fff;
                box-shadow:0 8px 20px rgba(0,0,0,.06);
                padding:10px 12px;
                align-items:center;
                justify-content:space-between;
                gap:10px;
            }

            .mobile-topbar .title{
                font-weight:800;
                font-size:18px;
                color:#163d6b;
            }

            .mobile-menu-btn{
                border:none;
                background:#163d6b;
                color:#fff;
                width:42px;
                height:42px;
                border-radius:12px;
                display:inline-flex;
                align-items:center;
                justify-content:center;
                font-size:20px;
                cursor:pointer;
            }

            .sidebar-backdrop{
                display:none;
            }

            @media print {
                .sidebar,
                .sidebar-backdrop,
                .mobile-topbar,
                .no-print{
                    display:none !important;
                }
                .content{
                    padding:0 !important;
                }
                body{
                    background:#fff !important;
                }
            }

            @media (max-width: 991.98px) {
                .mobile-topbar{
                    display:flex;
                }

                .app{
                    display:block;
                }

                .sidebar{
                    position:fixed;
                    right:0;
                    top:0;
                    width:300px;
                    max-width:86vw;
                    height:100vh;
                    z-index:1050;
                    transform:translateX(100%);
                    transition:transform .25s ease;
                    border-top-left-radius:18px;
                    border-bottom-left-radius:18px;
                }

                body.sidebar-open .sidebar{
                    transform:translateX(0);
                }

                .sidebar-backdrop{
                    position:fixed;
                    inset:0;
                    background:rgba(0,0,0,.45);
                    z-index:1045;
                }

                body.sidebar-open .sidebar-backdrop{
                    display:block;
                }

                .content{
                    padding:14px;
                }
            }
        </style>
    </head>
    <body>
    <div class="mobile-topbar no-print">
        <button type="button" class="mobile-menu-btn" onclick="toggleSidebar(true)">
            <i class="bi bi-list"></i>
        </button>
        <div class="title"><?= e($title) ?></div>
        <div style="width:42px;"></div>
    </div>

    <div class="sidebar-backdrop no-print" onclick="toggleSidebar(false)"></div>

    <div class="app">
        <?php adminSidebar($active); ?>
        <div class="content">
    <?php
}

function adminSidebar(string $active = ''): void
{
    $beneficiariesChildren = [
        ['key' => 'poor_families', 'label' => 'الأسر الفقيرة', 'icon' => 'bi-people-fill', 'href' => BASE_PATH . '/admin/poor_families.php'],
        ['key' => 'orphans', 'label' => 'الأيتام', 'icon' => 'bi-person-hearts', 'href' => BASE_PATH . '/admin/orphans.php'],
        ['key' => 'sponsorships', 'label' => 'كفالة الأيتام', 'icon' => 'bi-cash-coin', 'href' => BASE_PATH . '/admin/sponsorships.php'],
        ['key' => 'family_salaries', 'label' => 'رواتب الأسر', 'icon' => 'bi-wallet2', 'href' => BASE_PATH . '/admin/family_salaries.php'],
    ];

    $distributionChildren = [
        ['key' => 'distributions', 'label' => 'التوزيعات', 'icon' => 'bi-box-seam', 'href' => BASE_PATH . '/admin/distributions.php'],
        ['key' => 'print_sheets', 'label' => 'كشوف الطباعة الموحدة', 'icon' => 'bi-printer', 'href' => BASE_PATH . '/admin/print_distribution_sheet.php'],
        ['key' => 'reports', 'label' => 'التقارير', 'icon' => 'bi-bar-chart-line-fill', 'href' => BASE_PATH . '/admin/reports.php'],
    ];

    $commsChildren = [
        ['key' => 'whatsapp_notify', 'label' => 'تبليغ التوزيعات (واتس)', 'icon' => 'bi-whatsapp', 'href' => BASE_PATH . '/admin/whatsapp_notify.php'],
    ];

    $toolsChildren = [
        ['key' => 'import', 'label' => 'الاستيراد الجماعي', 'icon' => 'bi-upload', 'href' => BASE_PATH . '/admin/unified_import.php'],
    ];

    $isBeneficiariesOpen = in_array($active, ['poor_families', 'orphans', 'sponsorships', 'family_salaries'], true);
    $isDistributionsOpen = in_array($active, ['distributions', 'print_sheets', 'reports'], true);
    $isCommsOpen = in_array($active, ['whatsapp_notify'], true);
    $isToolsOpen = in_array($active, ['import'], true);
    ?>
    <aside class="sidebar no-print">
        <div class="brand">
            <div class="brand-title">
                <span class="heart">💛</span>
                <span>إدارة الزكاة</span>
            </div>
            <div class="brand-sub">
                مرحبًا، <?= e($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'مدير النظام') ?>
            </div>
        </div>

        <a class="side-link <?= $active === 'dashboard' ? 'active' : '' ?>" href="<?= e(BASE_PATH . '/admin/index.php') ?>">
            <i class="bi bi-speedometer2"></i>
            <span>لوحة التحكم</span>
        </a>

        <div class="side-separator"></div>

        <div class="side-group <?= $isBeneficiariesOpen ? 'open' : '' ?>">
            <button type="button" class="side-group-toggle <?= $isBeneficiariesOpen ? 'active' : '' ?>" onclick="toggleSideGroup(this)">
                <span class="side-group-title">
                    <i class="bi bi-people"></i>
                    <span>المستفيدون</span>
                </span>
                <i class="bi bi-chevron-down side-group-arrow"></i>
            </button>
            <div class="side-children">
                <?php foreach ($beneficiariesChildren as $item): ?>
                    <a class="side-link child <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                        <i class="bi <?= e($item['icon']) ?>"></i>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="side-group <?= $isDistributionsOpen ? 'open' : '' ?>">
            <button type="button" class="side-group-toggle <?= $isDistributionsOpen ? 'active' : '' ?>" onclick="toggleSideGroup(this)">
                <span class="side-group-title">
                    <i class="bi bi-box2-heart"></i>
                    <span>التوزيعات والكشوف</span>
                </span>
                <i class="bi bi-chevron-down side-group-arrow"></i>
            </button>
            <div class="side-children">
                <?php foreach ($distributionChildren as $item): ?>
                    <a class="side-link child <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                        <i class="bi <?= e($item['icon']) ?>"></i>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="side-group <?= $isCommsOpen ? 'open' : '' ?>">
            <button type="button" class="side-group-toggle <?= $isCommsOpen ? 'active' : '' ?>" onclick="toggleSideGroup(this)">
                <span class="side-group-title">
                    <i class="bi bi-chat-dots"></i>
                    <span>التواصل</span>
                </span>
                <i class="bi bi-chevron-down side-group-arrow"></i>
            </button>
            <div class="side-children">
                <?php foreach ($commsChildren as $item): ?>
                    <a class="side-link child <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                        <i class="bi <?= e($item['icon']) ?>"></i>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="side-group <?= $isToolsOpen ? 'open' : '' ?>">
            <button type="button" class="side-group-toggle <?= $isToolsOpen ? 'active' : '' ?>" onclick="toggleSideGroup(this)">
                <span class="side-group-title">
                    <i class="bi bi-gear"></i>
                    <span>الأدوات</span>
                </span>
                <i class="bi bi-chevron-down side-group-arrow"></i>
            </button>
            <div class="side-children">
                <?php foreach ($toolsChildren as $item): ?>
                    <a class="side-link child <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                        <i class="bi <?= e($item['icon']) ?>"></i>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="side-separator"></div>

        <a class="side-link logout-link" href="<?= e(BASE_PATH . '/admin/logout.php') ?>">
            <i class="bi bi-box-arrow-right"></i>
            <span>تسجيل الخروج</span>
        </a>
    </aside>
    <?php
}

function adminLayoutEnd(): void
{
    ?>
        </div>
    </div>

    <script>
    function toggleSideGroup(button) {
        const group = button.closest('.side-group');
        if (!group) return;
        group.classList.toggle('open');
        button.classList.toggle('active');
    }

    function toggleSidebar(state) {
        if (state) {
            document.body.classList.add('sidebar-open');
        } else {
            document.body.classList.remove('sidebar-open');
        }
    }
    </script>
    </body>
    </html>
    <?php
}