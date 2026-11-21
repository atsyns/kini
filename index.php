<?php
declare(strict_types=1);

session_start();

// ตรวจสอบสิทธิ์ก่อนแสดงข้อมูลในหน้าแดชบอร์ด
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// โหลดการเชื่อมต่อฐานข้อมูลหากมีไฟล์ config; หากไม่มีให้แสดงข้อมูลสำรอง
$pdo = null;
$pdoConfig = __DIR__ . '/config/conn_pdo.php';
if (is_file($pdoConfig)) {
    require_once $pdoConfig;
}

// สร้างข้อมูลเริ่มต้นไว้ใช้สำรองกรณีฐานข้อมูลยังไม่มีตารางหรือข้อมูล
$stats = [
    ['label' => 'ผู้ใช้งาน', 'value' => 1284, 'icon' => 'users', 'tone' => 'blue'],
    ['label' => 'รายการทั้งหมด', 'value' => 846, 'icon' => 'database', 'tone' => 'teal'],
    ['label' => 'กำลังดำเนินการ', 'value' => 73, 'icon' => 'clock', 'tone' => 'amber'],
    ['label' => 'เสร็จสิ้น', 'value' => 612, 'icon' => 'check-circle', 'tone' => 'green'],
];
$chartValues = [42, 58, 49, 72, 64, 88, 76, 94, 82, 98, 91, 100];
$latestRows = [
    ['code' => 'REQ-2026-118', 'title' => 'คำขอเพิ่มข้อมูลลูกค้า', 'owner' => 'พิมพ์ใจ ศรีสวัสดิ์', 'status' => 'เสร็จสิ้น', 'tone' => 'success', 'date' => '21 พ.ย. 2026'],
    ['code' => 'DOC-2026-075', 'title' => 'ตรวจสอบเอกสารประจำเดือน', 'owner' => 'กิตติพงศ์ แสงทอง', 'status' => 'กำลังดำเนินการ', 'tone' => 'warning', 'date' => '20 พ.ย. 2026'],
    ['code' => 'USR-2026-042', 'title' => 'อนุมัติบัญชีผู้ใช้งาน', 'owner' => 'นภา วัฒนะ', 'status' => 'รออนุมัติ', 'tone' => 'pending', 'date' => '19 พ.ย. 2026'],
    ['code' => 'SET-2026-031', 'title' => 'ปรับปรุงข้อมูลระบบ', 'owner' => 'ชานนท์ ดวงดี', 'status' => 'ยกเลิก', 'tone' => 'danger', 'date' => '18 พ.ย. 2026'],
];

// อ่านข้อมูลจาก PDO โดยยึดชื่อตารางสาธารณะที่พบบ่อย และหลีกเลี่ยง error เมื่อตารางยังไม่มี
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $statQueries = [
            0 => 'SELECT COUNT(*) FROM users',
            1 => 'SELECT COUNT(*) FROM records',
            2 => "SELECT COUNT(*) FROM records WHERE status = 'processing'",
            3 => "SELECT COUNT(*) FROM records WHERE status = 'completed'",
        ];
        foreach ($statQueries as $index => $sql) {
            try {
                $value = (int) $pdo->query($sql)->fetchColumn();
                $stats[$index]['value'] = $value;
            } catch (Throwable $e) {
                // เก็บค่าสำรองไว้แทนหากตารางชั่วคราวยังไม่มี
            }
        }

        $rowQuery = 'SELECT id, title, owner, status, created_at
                     FROM recent_activities
                     ORDER BY created_at DESC
                     LIMIT 5';
        $rows = $pdo->query($rowQuery)->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $latestRows = [];
            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? '');
                $statusLower = mb_strtolower($status, 'UTF-8');
                $tone = 'pending';
                if (str_contains($statusLower, 'เสร็จ') || str_contains($statusLower, 'complete')) {
                    $tone = 'success';
                } elseif (str_contains($statusLower, 'ดำเนิน') || str_contains($statusLower, 'process')) {
                    $tone = 'warning';
                } elseif (str_contains($statusLower, 'ยกเลิก') || str_contains($statusLower, 'cancel')) {
                    $tone = 'danger';
                }

                $createdAt = $row['created_at'] ? new DateTime((string) $row['created_at']) : null;
                $latestRows[] = [
                    'code' => 'ACT-' . str_pad((string) ($row['id'] ?? ''), 4, '0', STR_PAD_LEFT),
                    'title' => (string) ($row['title'] ?? '-'),
                    'owner' => (string) ($row['owner'] ?? '-'),
                    'status' => $status ?: '-',
                    'tone' => $tone,
                    'date' => $createdAt ? $createdAt->format('d/m/Y') : '-',
                ];
            }
        }
    } catch (Throwable $e) {
        // ใช้ข้อมูลสำรองเพื่อให้หน้าแสดงผลต่อแม้ฐานข้อมูลไม่พร้อม
    }
}

$user = $_SESSION['user'];
$displayName = is_array($user)
    ? (string) ($user['name'] ?? $user['username'] ?? 'ผู้ใช้งาน')
    : (string) $user;
$displayEmail = is_array($user) ? (string) ($user['email'] ?? '') : '';
$thaiMonths = [
    'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม',
];
$today = date('j') . ' ' . $thaiMonths[(int) date('n') - 1] . ' ' . ((int) date('Y') + 543);
$safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($displayEmail, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ศูนย์จัดการข้อมูล</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/@iconify/iconify@1.11.3/dist/iconify.min.js"></script>
</head>
<body>
    <div class="top-gradient" aria-hidden="true"></div>
    <div class="navigation-progress" id="navigationProgress" aria-hidden="true"></div>

    <aside class="sidebar" id="sidebar" aria-label="แถบเมนูหลัก">
        <div class="sidebar-brand">
            <a class="brand-link" href="index.php" aria-label="กลับหน้าแรก">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v13a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 18.5v-13Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                </span>
                <span class="brand-copy"><strong>TTM</strong><small>Data Manager</small></span>
            </a>
        </div>

        <nav class="sidebar-nav" aria-label="เมนูระบบ">
            <div class="nav-indicator" id="navIndicator" aria-hidden="true"></div>
            <section class="nav-section">
                <h2 class="nav-heading">ภาพรวม</h2>
                <a class="nav-item active" href="index.php" aria-current="page" data-tooltip="ภาพรวม">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3.75 3.75h6.5v6.5h-6.5zM13.75 3.75h6.5v6.5h-6.5zM3.75 13.75h6.5v6.5h-6.5zM13.75 13.75h6.5v6.5h-6.5z"/></svg>
                    <span class="nav-label">ภาพรวม</span>
                </a>
            </section>

            <section class="nav-section">
                <h2 class="nav-heading">จัดการข้อมูล</h2>
                <button class="nav-item nav-accordion" type="button" aria-expanded="true" aria-controls="dataSubmenu" data-tooltip="ข้อมูลหลัก">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="5" rx="7.5" ry="3"/><path d="M4.5 5v6c0 1.66 3.36 3 7.5 3s7.5-1.34 7.5-3V5M4.5 11v6c0 1.66 3.36 3 7.5 3s7.5-1.34 7.5-3v-6"/></svg>
                    <span class="nav-label">ข้อมูลหลัก</span>
                    <svg class="nav-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m8 10 4 4 4-4"/></svg>
                </button>
                <div class="nav-submenu open" id="dataSubmenu">
                    <a class="nav-subitem" href="?page=users">รายชื่อผู้ใช้</a>
                    <a class="nav-subitem" href="?page=records">รายการข้อมูล</a>
                    <a class="nav-subitem" href="?page=categories">หมวดหมู่</a>
                </div>

                <button class="nav-item nav-accordion" type="button" aria-expanded="false" aria-controls="reportSubmenu" data-tooltip="รายงาน">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l3 3v15H6zM15 3v4h4M9 12h6M9 16h6M9 8h2"/></svg>
                    <span class="nav-label">รายงาน</span>
                    <svg class="nav-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m8 10 4 4 4-4"/></svg>
                </button>
                <div class="nav-submenu" id="reportSubmenu">
                    <a class="nav-subitem" href="?page=report-daily">รายงานประจำวัน</a>
                    <a class="nav-subitem" href="?page=report-monthly">รายงานประจำเดือน</a>
                </div>
            </section>

            <section class="nav-section">
                <h2 class="nav-heading">ระบบ</h2>
                <a class="nav-item" href="?page=settings" data-tooltip="ตั้งค่าระบบ">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-1.86 1.86-.06-.06A1.7 1.7 0 0 0 16 18.4a1.7 1.7 0 0 0-1 .6l-.04.1V21h-6v-1.9l-.04-.1a1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.88.34l-.06.06-1.86-1.86.06-.06A1.7 1.7 0 0 0 4.52 15a1.7 1.7 0 0 0-.6-1H2v-4h1.92a1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.88l-.06-.06L5.98 5.2l.06.06A1.7 1.7 0 0 0 7.92 5a1.7 1.7 0 0 0 1-.6L9 4V2h6v2l.04.4a1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.88-.34l.06-.06 1.86 1.86-.06.06A1.7 1.7 0 0 0 19.48 8c.12.39.34.73.6 1H22v4h-1.92a1.7 1.7 0 0 0-.68 2Z"/></svg>
                    <span class="nav-label">ตั้งค่าระบบ</span>
                </a>
            </section>
        </nav>

        <div class="sidebar-user">
            <div class="user-avatar" aria-hidden="true"><?= htmlspecialchars(mb_substr($safeName, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sidebar-user-copy">
                <strong><?= $safeName ?></strong>
                <?php if ($safeEmail !== ''): ?><small><?= $safeEmail ?></small><?php endif; ?>
            </div>
            <a class="sidebar-logout" href="logout.php" aria-label="ออกจากระบบ" data-tooltip="ออกจากระบบ">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5H5v14h5M14 8l4 4-4 4M8 12h10"/></svg>
            </a>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <div class="app-shell" id="appShell">
        <header class="topbar">
            <div class="topbar-left">
                <button class="icon-button sidebar-toggle" id="sidebarToggle" type="button" aria-label="ย่อหรือขยายเมนู" aria-expanded="true">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <span class="page-title">ภาพรวม</span>
            </div>

            <div class="topbar-actions">
                <label class="search-box" aria-label="ค้นหา">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg>
                    <input type="search" id="globalSearch" placeholder="ค้นหา..." autocomplete="off">
                </label>

                <button class="icon-button notification-button" type="button" aria-label="การแจ้งเตือน" aria-expanded="false" id="notificationButton">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                    <span class="notification-dot" aria-label="มีการแจ้งเตือนใหม่"></span>
                </button>

                <div class="user-menu">
                    <button class="user-menu-trigger" type="button" id="userMenuTrigger" aria-expanded="false" aria-controls="userMenuDropdown">
                        <span class="topbar-avatar" aria-hidden="true"><?= htmlspecialchars(mb_substr($safeName, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="user-menu-name"><?= $safeName ?></span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 10 4 4 4-4"/></svg>
                    </button>
                    <div class="dropdown-menu" id="userMenuDropdown" role="menu">
                        <a href="?page=profile" role="menuitem"><span>โปรไฟล์</span></a>
                        <a href="?page=settings" role="menuitem"><span>ตั้งค่าบัญชี</span></a>
                        <a class="dropdown-danger" href="logout.php" role="menuitem"><span>ออกจากระบบ</span></a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main-content">
            <section class="welcome-block stagger" aria-labelledby="welcomeTitle">
                <div>
                    <p class="eyebrow">ศูนย์ควบคุมระบบ</p>
                    <h1 id="welcomeTitle">สวัสดี, <?= $safeName ?></h1>
                </div>
                <div class="date-chip">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3v3M18 3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z"/></svg>
                    <time datetime="<?= date('Y-m-d') ?>"><?= htmlspecialchars((string) $today, ENT_QUOTES, 'UTF-8') ?></time>
                </div>
            </section>

            <section class="stats-grid" aria-label="สรุปสถิติ">
                <?php foreach ($stats as $index => $stat): ?>
                    <article class="stat-card stagger card-hover" style="--stagger-index: <?= $index + 1 ?>">
                        <div class="stat-icon tone-<?= htmlspecialchars($stat['tone'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><use href="#icon-<?= htmlspecialchars($stat['icon'], ENT_QUOTES, 'UTF-8') ?>"></use></svg>
                        </div>
                        <div class="stat-copy">
                            <p><?= htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8') ?></p>
                            <strong class="count-up" data-target="<?= (int) $stat['value'] ?>">0</strong>
                        </div>
                        <span class="trend-badge">+<?= 5 + $index ?>%</span>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="dashboard-grid">
                <article class="panel chart-panel stagger card-hover" aria-labelledby="chartTitle">
                    <div class="panel-header">
                        <div><p class="panel-kicker">ประจำปี</p><h2 id="chartTitle">สถิติการใช้งาน</h2></div>
                        <button class="period-button" type="button">12 เดือน <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 10 4 4 4-4"/></svg></button>
                    </div>
                    <div class="chart-wrap">
                        <div class="chart-scale" aria-hidden="true"><span>100</span><span>75</span><span>50</span><span>25</span><span>0</span></div>
                        <div class="bar-chart" role="img" aria-label="กราฟสถิติการใช้งานรายเดือน">
                            <?php foreach ($chartValues as $index => $value): ?>
                                <div class="bar-column">
                                    <div class="bar-track"><span class="bar-fill" style="--bar-height: <?= (int) $value ?>%; --bar-index: <?= $index ?>"></span></div>
                                    <span class="bar-label"><?= htmlspecialchars(['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][$index], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </article>

                <article class="panel activity-panel stagger card-hover" aria-labelledby="activityTitle">
                    <div class="panel-header">
                        <div><p class="panel-kicker">อัปเดตล่าสุด</p><h2 id="activityTitle">สถานะระบบ</h2></div>
                        <span class="live-status"><i></i> พร้อมใช้งาน</span>
                    </div>
                    <div class="activity-summary">
                        <div class="donut" role="img" aria-label="ความสำเร็จ 78 เปอร์เซ็นต์"><span><strong>78%</strong><small>สำเร็จ</small></span></div>
                        <div class="activity-legend">
                            <div><i class="legend-success"></i><span>เสร็จสิ้น</span><strong>612</strong></div>
                            <div><i class="legend-warning"></i><span>กำลังดำเนินการ</span><strong>73</strong></div>
                            <div><i class="legend-pending"></i><span>รออนุมัติ</span><strong>161</strong></div>
                        </div>
                    </div>
                </article>
            </section>

            <section class="panel table-panel stagger" aria-labelledby="latestTitle">
                <div class="panel-header table-header">
                    <div><p class="panel-kicker">ข้อมูลล่าสุด</p><h2 id="latestTitle">รายการล่าสุด</h2></div>
                    <a class="view-all" href="?page=records">ดูทั้งหมด <span aria-hidden="true">→</span></a>
                </div>

                <div class="table-skeleton" id="tableSkeleton" aria-label="กำลังโหลดข้อมูล">
                    <div class="skeleton-line"></div><div class="skeleton-line"></div><div class="skeleton-line"></div><div class="skeleton-line"></div>
                </div>
                <div class="table-scroll" id="tableContent" hidden>
                    <table>
                        <thead><tr><th>รหัส</th><th>รายการ</th><th>ผู้รับผิดชอบ</th><th>สถานะ</th><th>วันที่</th></tr></thead>
                        <tbody>
                        <?php foreach ($latestRows as $row): ?>
                            <tr>
                                <td><span class="record-code"><?= htmlspecialchars((string) $row['code'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars((string) $row['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['owner'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge badge-<?= htmlspecialchars((string) $row['tone'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars((string) $row['date'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <footer class="footer">
            <span>© <?= date('Y') + 543 ?> TTM Data Manager</span>
            <span>เวอร์ชัน 1.0.0</span>
        </footer>
    </div>

    <svg class="svg-sprite" aria-hidden="true">
        <symbol id="icon-users" viewBox="0 0 24 24"><path d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-6A3.5 3.5 0 0 0 3 18.5V20M9.5 11.5A3.5 3.5 0 1 0 9.5 4a3.5 3.5 0 0 0 0 7.5ZM16 11a3 3 0 0 0 0-6M17 15a3.5 3.5 0 0 1 4 3.45V20"/></symbol>
        <symbol id="icon-database" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v7c0 1.66 3.58 3 8 3s8-1.34 8-3V5M4 12v7c0 1.66 3.58 3 8 3s8-1.34 8-3v-7"/></symbol>
        <symbol id="icon-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
        <symbol id="icon-check-circle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16.5 8"/></symbol>
    </svg>

    <script src="js/main.js"></script>
</body>
</html>
