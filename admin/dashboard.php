<?php
ob_start();
session_start();

if (empty($_SESSION['adminid']) || !is_numeric($_SESSION['adminid'])) {
    session_unset(); session_destroy();
    header('Location: index.php'); exit();
}

$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset(); session_destroy();
    header('Location: index.php?reason=timeout'); exit();
}
$_SESSION['last_activity'] = time();

include_once('../includes/config.php');

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ── Total Runners
$r = $con->query("SELECT COUNT(*) AS c FROM runner")->fetch_assoc();
$totalRunners = (int)$r['c'];

// ── Today Runners
$r = $con->query("SELECT COUNT(*) AS c FROM runner WHERE DATE(reg_dt) = CURDATE()")->fetch_assoc();
$todayRunners = (int)$r['c'];

// ── Public Runners
$r = $con->query("SELECT COUNT(*) AS c FROM runner WHERE reg_type = 'public'")->fetch_assoc();
$publicRunners = (int)$r['c'];

// ── Booklet Runners
$r = $con->query("SELECT COUNT(*) AS c FROM runner WHERE reg_type = 'booklet' OR reg_type IS NULL")->fetch_assoc();
$bookletRunners = (int)$r['c'];

// ── Total Users (volunteers)
$r = $con->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc();
$totalUsers = (int)$r['c'];

// ── Total Booklets
$r = $con->query("SELECT COUNT(*) AS c FROM booklet")->fetch_assoc();
$totalBooklets = (int)$r['c'];

// ── This Week Runners
$r = $con->query("SELECT COUNT(*) AS c FROM runner WHERE reg_dt >= NOW() - INTERVAL 7 DAY")->fetch_assoc();
$weekRunners = (int)$r['c'];

// ── This Month Runners
$r = $con->query("SELECT COUNT(*) AS c FROM runner WHERE MONTH(reg_dt) = MONTH(NOW()) AND YEAR(reg_dt) = YEAR(NOW())")->fetch_assoc();
$monthRunners = (int)$r['c'];

// ── Category Stats
$catResult = $con->query("
    SELECT r_catgry, COUNT(*) AS cnt
    FROM runner
    WHERE r_catgry != '' AND r_catgry IS NOT NULL
    GROUP BY r_catgry
    ORDER BY cnt DESC
");
$catLabels = []; $catCounts = [];
while ($row = $catResult->fetch_assoc()) {
    $catLabels[] = $row['r_catgry'];
    $catCounts[] = (int)$row['cnt'];
}

// ── Gender Stats
$genResult = $con->query("
    SELECT r_gender, COUNT(*) AS cnt
    FROM runner
    WHERE r_gender != '' AND r_gender IS NOT NULL
    GROUP BY r_gender
");
$genLabels = []; $genCounts = [];
while ($row = $genResult->fetch_assoc()) {
    $genLabels[] = $row['r_gender'];
    $genCounts[] = (int)$row['cnt'];
}

// ── T-Shirt Stats
$tshirtResult = $con->query("
    SELECT r_tshirt_sz, COUNT(*) AS cnt
    FROM runner
    WHERE r_tshirt_sz != '' AND r_tshirt_sz IS NOT NULL
    GROUP BY r_tshirt_sz
    ORDER BY FIELD(r_tshirt_sz,'XS','S','M','L','XL','XXL','XXXL')
");
$tshirtLabels = []; $tshirtCounts = [];
while ($row = $tshirtResult->fetch_assoc()) {
    $tshirtLabels[] = $row['r_tshirt_sz'];
    $tshirtCounts[] = (int)$row['cnt'];
}

// ── Last 7 Days Daily Count
$dailyResult = $con->query("
    SELECT DATE(reg_dt) AS day, COUNT(*) AS cnt
    FROM runner
    WHERE reg_dt >= NOW() - INTERVAL 6 DAY
    GROUP BY DATE(reg_dt)
    ORDER BY day ASC
");
$dailyLabels = []; $dailyCounts = [];
while ($row = $dailyResult->fetch_assoc()) {
    $dailyLabels[] = date('D d M', strtotime($row['day']));
    $dailyCounts[] = (int)$row['cnt'];
}

// ── User-wise Runner Count
$userResult = $con->query("
    SELECT u.fname, u.lname, COUNT(r.r_id) AS cnt
    FROM users u
    LEFT JOIN runner r ON r.u_id = u.id AND r.reg_type = 'booklet'
    GROUP BY u.id
    ORDER BY cnt DESC
    LIMIT 8
");

// ── Recent Registrations (last 10)
$recentResult = $con->query("
    SELECT r.r_name, r.r_email, r.r_contact, r.r_gender,
           r.r_catgry, r.r_tshirt_sz, r.reg_type, r.reg_dt,
           b.b_name,
           u.fname, u.lname
    FROM runner r
    LEFT JOIN booklet b ON b.id = r.b_id
    LEFT JOIN users   u ON u.id = r.u_id
    ORDER BY r.r_id DESC
    LIMIT 10
");

// ── Blood Group Stats
$bloodResult = $con->query("
    SELECT r_bdgp, COUNT(*) AS cnt
    FROM runner
    WHERE r_bdgp IS NOT NULL AND r_bdgp != ''
    GROUP BY r_bdgp
    ORDER BY cnt DESC
");
$bloodLabels = []; $bloodCounts = [];
while ($row = $bloodResult->fetch_assoc()) {
    $bloodLabels[] = $row['r_bdgp'];
    $bloodCounts[] = (int)$row['cnt'];
}

// ── Top Referrers (users)
$topRefResult = $con->query("
    SELECT u.fname, u.lname, u.referral_code,
           COUNT(r.r_id) AS ref_count
    FROM users u
    LEFT JOIN runner r ON r.referred_by_user = u.id
    GROUP BY u.id
    HAVING ref_count > 0
    ORDER BY ref_count DESC
    LIMIT 5
");

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="../css/styles.css" rel="stylesheet" onerror="this.remove()"/>
    <style>
        * { font-family: 'Inter', sans-serif; }

        #layoutSidenav_content { display:flex; flex-direction:column; min-height:calc(100vh - 56px); }
        #layoutSidenav_content main { flex:1; }
        body { background:#f4f6fb; }

        /* ── Page Header */
        .page-header {
            background: linear-gradient(135deg,#1a1a2e 0%,#6c63ff 60%,#f50057 100%);
            border-radius:16px; padding:24px 28px;
            display:flex; align-items:center;
            justify-content:space-between; flex-wrap:wrap; gap:12px;
            margin-bottom:28px;
            box-shadow:0 10px 30px rgba(108,99,255,0.3);
        }
        .page-header h2 { color:#fff; font-size:1.4rem; font-weight:800; margin:0; }
        .page-header p  { color:rgba(255,255,255,0.6); font-size:0.82rem; margin:4px 0 0; }
        .ph-right { display:flex; align-items:center; gap:10px; }
        .ph-badge {
            background:rgba(255,255,255,0.15); color:#fff;
            border:1px solid rgba(255,255,255,0.2);
            border-radius:20px; padding:5px 14px;
            font-size:0.78rem; font-weight:700;
        }

        /* ── Stat Cards */
        .stat-card {
            border:none; border-radius:16px;
            padding:22px 22px 18px;
            position:relative; overflow:hidden;
            transition:transform 0.25s, box-shadow 0.25s;
            cursor:default; margin-bottom:24px;
            text-decoration:none; display:block;
        }
        .stat-card:hover { transform:translateY(-4px); }
        .stat-card::before {
            content:''; position:absolute;
            top:-25px; right:-25px;
            width:90px; height:90px; border-radius:50%;
            background:rgba(255,255,255,0.08);
        }
        .stat-card::after {
            content:''; position:absolute;
            bottom:-15px; right:15px;
            width:60px; height:60px; border-radius:50%;
            background:rgba(255,255,255,0.05);
        }
        .sc-purple { background:linear-gradient(135deg,#6c63ff,#574fd6); box-shadow:0 8px 24px rgba(108,99,255,0.35); }
        .sc-pink    { background:linear-gradient(135deg,#f50057,#c2003d); box-shadow:0 8px 24px rgba(245,0,87,0.3);   }
        .sc-teal    { background:linear-gradient(135deg,#00b4d8,#0077b6); box-shadow:0 8px 24px rgba(0,180,216,0.3);  }
        .sc-amber   { background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow:0 8px 24px rgba(245,158,11,0.3); }
        .sc-green   { background:linear-gradient(135deg,#22c55e,#15803d); box-shadow:0 8px 24px rgba(34,197,94,0.3);  }
        .sc-indigo  { background:linear-gradient(135deg,#6366f1,#4338ca); box-shadow:0 8px 24px rgba(99,102,241,0.3); }
        .sc-rose    { background:linear-gradient(135deg,#fb7185,#e11d48); box-shadow:0 8px 24px rgba(251,113,133,0.3);}
        .sc-cyan    { background:linear-gradient(135deg,#06b6d4,#0e7490); box-shadow:0 8px 24px rgba(6,182,212,0.3);  }

        .stat-icon {
            width:44px; height:44px; border-radius:11px;
            background:rgba(255,255,255,0.15);
            display:flex; align-items:center; justify-content:center;
            font-size:18px; color:#fff; margin-bottom:14px;
        }
        .stat-label  { color:rgba(255,255,255,0.65); font-size:0.73rem; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; }
        .stat-value  { color:#fff; font-size:2.2rem; font-weight:900; line-height:1; margin:6px 0 8px; }
        .stat-footer { color:rgba(255,255,255,0.55); font-size:0.73rem; }

        /* ── Chart Cards */
        .chart-card {
            background:#fff; border:none; border-radius:16px;
            box-shadow:0 4px 24px rgba(0,0,0,0.07);
            overflow:hidden; margin-bottom:24px;
        }
        .chart-header {
            background:linear-gradient(135deg,#1a1a2e,#16213e);
            padding:14px 20px;
            display:flex; align-items:center; gap:10px;
        }
        .chart-icon {
            width:30px; height:30px; border-radius:8px;
            display:flex; align-items:center; justify-content:center;
            font-size:12px; color:#fff;
        }
        .chart-header h6 { color:#fff; margin:0; font-size:0.88rem; font-weight:700; }
        .chart-header p  { color:rgba(255,255,255,0.4); margin:0; font-size:0.7rem; }
        .chart-body { padding:20px; }

        /* ── Table Cards */
        .table-card {
            background:#fff; border:none; border-radius:16px;
            box-shadow:0 4px 24px rgba(0,0,0,0.07);
            overflow:hidden; margin-bottom:24px;
        }
        .table-header {
            background:linear-gradient(135deg,#1a1a2e,#16213e);
            padding:14px 20px;
            display:flex; align-items:center;
            justify-content:space-between; gap:10px; flex-wrap:wrap;
        }
        .table-header h6 { color:#fff; margin:0; font-size:0.88rem; font-weight:700; }
        .tbl-badge {
            background:#6c63ff; color:#fff;
            border-radius:20px; padding:3px 12px;
            font-size:0.75rem; font-weight:700;
        }

        /* ── Custom Table */
        .ctbl { width:100%; border-collapse:collapse; }
        .ctbl thead th {
            background:#f8f7ff; color:#6c63ff;
            font-size:0.7rem; font-weight:700;
            text-transform:uppercase; letter-spacing:0.8px;
            padding:11px 14px; border-bottom:2px solid #ede9ff;
            white-space:nowrap;
        }
        .ctbl tbody td {
            padding:11px 14px; font-size:0.84rem; color:#374151;
            border-bottom:1px solid #f3f4f6; vertical-align:middle;
        }
        .ctbl tbody tr:hover { background:#faf9ff; }
        .ctbl tbody tr:last-child td { border-bottom:none; }

        /* ── Badges */
        .bp {
            display:inline-block; border-radius:7px;
            padding:3px 9px; font-size:0.72rem; font-weight:700;
        }
        .bp-pub  { background:#dcfce7; color:#166534; }
        .bp-bklt { background:#dbeafe; color:#1e40af; }
        .bp-male { background:#dbeafe; color:#1e40af; }
        .bp-fem  { background:#fce7f3; color:#9d174d; }
        .bp-oth  { background:#ede9fe; color:#5b21b6; }

        .row-num {
            width:24px; height:24px; border-radius:6px;
            background:linear-gradient(135deg,#6c63ff,#574fd6);
            color:#fff; font-size:0.68rem; font-weight:800;
            display:inline-flex; align-items:center; justify-content:center;
        }

        /* ── Progress */
        .prog-wrap { min-width:80px; }
        .prog-bar  { height:5px; border-radius:10px; background:#f0efff; overflow:hidden; margin-top:4px; }
        .prog-fill { height:100%; border-radius:10px; background:linear-gradient(90deg,#6c63ff,#f50057); }
        .prog-txt  { font-size:0.68rem; color:#9ca3af; display:flex; justify-content:space-between; }

        /* ── Top Referrer Card */
        .ref-item {
            display:flex; align-items:center;
            gap:12px; padding:12px 20px;
            border-bottom:1px solid #f3f4f6; transition:background 0.2s;
        }
        .ref-item:last-child { border-bottom:none; }
        .ref-item:hover { background:#faf9ff; }
        .ref-avatar {
            width:38px; height:38px; border-radius:10px;
            background:linear-gradient(135deg,#6c63ff,#574fd6);
            display:flex; align-items:center; justify-content:center;
            color:#fff; font-size:14px; font-weight:700; flex-shrink:0;
        }
        .ref-name  { font-size:0.86rem; font-weight:700; color:#1f2937; }
        .ref-code  { font-size:0.72rem; color:#9ca3af; letter-spacing:1px; }
        .ref-count {
            margin-left:auto;
            background:linear-gradient(135deg,#6c63ff,#f50057);
            color:#fff; border-radius:20px;
            padding:4px 12px; font-size:0.75rem; font-weight:800;
            white-space:nowrap;
        }

        /* ── Empty */
        .empty-st { text-align:center; padding:30px; color:#9ca3af; }
        .empty-st i { font-size:2rem; opacity:.3; display:block; margin-bottom:8px; }
        .empty-st p { font-size:0.82rem; margin:0; }

        /* ── Quick Actions */
        .qa-btn {
            display:flex; flex-direction:column;
            align-items:center; justify-content:center; gap:8px;
            padding:18px 12px; border-radius:14px;
            background:#fff; border:2px solid #f3f4f6;
            text-decoration:none; transition:all 0.2s;
            font-size:0.78rem; font-weight:700; color:#374151;
        }
        .qa-btn:hover { border-color:#6c63ff; background:#f5f3ff; color:#6c63ff; transform:translateY(-2px); }
        .qa-btn i { font-size:22px; }
        .qa-btn.purple i { color:#6c63ff; }
        .qa-btn.pink   i { color:#f50057; }
        .qa-btn.teal   i { color:#00b4d8; }
        .qa-btn.amber  i { color:#f59e0b; }
        .qa-btn.green  i { color:#22c55e; }
        .qa-btn.indigo i { color:#6366f1; }
    </style>
</head>
<body class="sb-nav-fixed">

<?php include_once('includes/navbar.php'); ?>

<div id="layoutSidenav">
    <?php include_once('includes/sidebar.php'); ?>

    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4 pb-4">

                <!-- ── Page Header -->
                <div class="page-header mt-4">
                    <div>
                        <h2><i class="fas fa-chart-line me-2"></i>Admin Dashboard</h2>
                        <p>
                            <i class="fas fa-clock me-1"></i>
                            <?php echo date('D, d M Y h:i A'); ?> IST
                        </p>
                    </div>
                    <div class="ph-right">
                        <span class="ph-badge"><i class="fas fa-running me-1"></i><?php echo $totalRunners; ?> Total Runners</span>
                        <a href="all-runner.php" class="btn btn-sm"
                           style="background:rgba(255,255,255,0.15);color:#fff;border-radius:8px;font-weight:600;border:1px solid rgba(255,255,255,0.2);">
                            <i class="fas fa-eye me-1"></i> View All
                        </a>
                    </div>
                </div>

                <!-- ── Stat Cards Row 1 -->
                <div class="row g-0">
                    <div class="col-xl-3 col-md-6 pe-3">
                        <a href="all-runner.php" class="stat-card sc-purple">
                            <div class="stat-icon"><i class="fas fa-users"></i></div>
                            <div class="stat-label">Total Runners</div>
                            <div class="stat-value"><?php echo number_format($totalRunners); ?></div>
                            <div class="stat-footer"><i class="fas fa-arrow-right me-1"></i>View All Runners</div>
                        </a>
                    </div>
                    <div class="col-xl-3 col-md-6 pe-3">
                        <a href="all-runner.php?date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="stat-card sc-pink">
                            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                            <div class="stat-label">Today's Registrations</div>
                            <div class="stat-value"><?php echo number_format($todayRunners); ?></div>
                            <div class="stat-footer"><i class="fas fa-arrow-right me-1"></i>View Today</div>
                        </a>
                    </div>
                    <div class="col-xl-3 col-md-6 pe-3">
                        <a href="all-runner.php" class="stat-card sc-teal">
                            <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                            <div class="stat-label">This Week</div>
                            <div class="stat-value"><?php echo number_format($weekRunners); ?></div>
                            <div class="stat-footer"><i class="fas fa-arrow-right me-1"></i>Last 7 Days</div>
                        </a>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <a href="all-runner.php" class="stat-card sc-amber">
                            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                            <div class="stat-label">This Month</div>
                            <div class="stat-value"><?php echo number_format($monthRunners); ?></div>
                            <div class="stat-footer"><i class="fas fa-arrow-right me-1"></i><?php echo date('F Y'); ?></div>
                        </a>
                    </div>
                </div>

                <!-- ── Stat Cards Row 2 -->
                <div class="row g-0">
                    <div class="col-xl-3 col-md-6 pe-3">
                        <a href="manage-users.php" class="stat-card sc-green">
                            <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                            <div class="stat-label">Volunteers/Users</div>
                            <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                            <div class="stat-footer"><i class="fas fa-arrow-right me-1"></i>Manage Users</div>
                        </a>
                    </div>
                    <div class="col-xl-3 col-md-6 pe-3">
                        <a href="manage-booklet.php" class="stat-card sc-indigo">
                            <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                            <div class="stat-label">Total Booklets</div>
                            <div class="stat-value"><?php echo number_format($totalBooklets); ?></div>
                            <div class="stat-footer"><i class="fas fa-arrow-right me-1"></i>Manage Booklets</div>
                        </a>
                    </div>
                    <div class="col-xl-3 col-md-6 pe-3">
                        <a href="all-runner.php?reg_type=public" class="stat-card sc-rose">
                            <div class="stat-icon"><i class="fas fa-globe"></i></div>
                            <div class="stat-label">Public Registrations</div>
                            <div class="stat-value"><?php echo number_format($publicRunners); ?></div>
                            <div class="stat-footer"><i class="fas fa-arrow-right me-1"></i>Online Form</div>
                        </a>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <a href="all-runner.php?reg_type=booklet" class="stat-card sc-cyan">
                            <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                            <div class="stat-label">Booklet Registrations</div>
                            <div class="stat-value"><?php echo number_format($bookletRunners); ?></div>
                            <div class="stat-footer"><i class="fas fa-arrow-right me-1"></i>Via Volunteers</div>
                        </a>
                    </div>
                </div>

                <!-- ── Quick Actions -->
                <div class="chart-card mb-4">
                    <div class="chart-header">
                        <div class="chart-icon" style="background:rgba(108,99,255,0.4);">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div>
                            <h6>Quick Actions</h6>
                            <p>Frequently used shortcuts</p>
                        </div>
                    </div>
                    <div class="chart-body">
                        <div class="row g-3">
                            <div class="col-6 col-md-2">
                                <a href="all-runner.php" class="qa-btn purple">
                                    <i class="fas fa-running"></i> All Runners
                                </a>
                            </div>
                            <div class="col-6 col-md-2">
                                <a href="manage-booklet.php" class="qa-btn indigo">
                                    <i class="fas fa-book"></i> Booklets
                                </a>
                            </div>
                            <div class="col-6 col-md-2">
                                <a href="manage-users.php" class="qa-btn teal">
                                    <i class="fas fa-users-cog"></i> Users
                                </a>
                            </div>
                            <div class="col-6 col-md-2">
                                <a href="../open-runner-registration.php" target="_blank" class="qa-btn green">
                                    <i class="fas fa-globe"></i> Public Form
                                </a>
                            </div>
                            <div class="col-6 col-md-2">
                                <a href="all-runner.php?date_from=<?php echo date('Y-m-d'); ?>" class="qa-btn amber">
                                    <i class="fas fa-calendar-day"></i> Today
                                </a>
                            </div>
                            <div class="col-6 col-md-2">
                                <a href="reports.php" class="qa-btn pink">
                                    <i class="fas fa-file-alt"></i> Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Charts Row 1 -->
                <div class="row">

                    <!-- Daily Registration Chart -->
                    <div class="col-xl-8">
                        <div class="chart-card">
                            <div class="chart-header">
                                <div class="chart-icon" style="background:linear-gradient(135deg,#6c63ff,#574fd6);">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <div>
                                    <h6>Daily Registrations — Last 7 Days</h6>
                                    <p>Runner count per day</p>
                                </div>
                            </div>
                            <div class="chart-body">
                                <canvas id="dailyChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Gender Chart -->
                    <div class="col-xl-4">
                        <div class="chart-card">
                            <div class="chart-header">
                                <div class="chart-icon" style="background:linear-gradient(135deg,#f50057,#c2003d);">
                                    <i class="fas fa-venus-mars"></i>
                                </div>
                                <div>
                                    <h6>Gender Distribution</h6>
                                    <p>Male / Female / Other</p>
                                </div>
                            </div>
                            <div class="chart-body">
                                <canvas id="genderChart" height="180"></canvas>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ── Charts Row 2 -->
                <div class="row">

                    <!-- Category Chart -->
                    <div class="col-xl-5">
                        <div class="chart-card">
                            <div class="chart-header">
                                <div class="chart-icon" style="background:linear-gradient(135deg,#00b4d8,#0077b6);">
                                    <i class="fas fa-flag-checkered"></i>
                                </div>
                                <div>
                                    <h6>Category-wise Distribution</h6>
                                    <p>Runners per race category</p>
                                </div>
                            </div>
                            <div class="chart-body">
                                <canvas id="catChart" height="170"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- T-Shirt Chart -->
                    <div class="col-xl-4">
                        <div class="chart-card">
                            <div class="chart-header">
                                <div class="chart-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                                    <i class="fas fa-tshirt"></i>
                                </div>
                                <div>
                                    <h6>T-Shirt Size Distribution</h6>
                                    <p>Sizes ordered by runners</p>
                                </div>
                            </div>
                            <div class="chart-body">
                                <canvas id="tshirtChart" height="170"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Blood Group Chart -->
                    <div class="col-xl-3">
                        <div class="chart-card">
                            <div class="chart-header">
                                <div class="chart-icon" style="background:linear-gradient(135deg,#dc2626,#b91c1c);">
                                    <i class="fas fa-tint"></i>
                                </div>
                                <div>
                                    <h6>Blood Groups</h6>
                                    <p>Distribution</p>
                                </div>
                            </div>
                            <div class="chart-body">
                                <canvas id="bloodChart" height="170"></canvas>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ── Bottom Row -->
                <div class="row">

                    <!-- Recent Registrations -->
                    <div class="col-xl-8">
                        <div class="table-card">
                            <div class="table-header">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-clock text-white"></i>
                                    <h6 style="margin:0;">Recent Registrations</h6>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="tbl-badge">Last 10</span>
                                    <a href="all-runner.php" class="btn btn-sm"
                                       style="background:rgba(255,255,255,0.15);color:#fff;border-radius:7px;font-size:.78rem;font-weight:600;">
                                        View All <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                            <div style="overflow-x:auto;">
                                <?php if ($recentResult->num_rows > 0): ?>
                                <table class="ctbl">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Runner</th>
                                            <th>Contact</th>
                                            <th>Category</th>
                                            <th>Gender</th>
                                            <th>Type</th>
                                            <th>Volunteer</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php $sno = 1; while ($row = $recentResult->fetch_assoc()):
                                        $gClass = ($row['r_gender'] === 'Male') ? 'bp-male'
                                                : (($row['r_gender'] === 'Female') ? 'bp-fem' : 'bp-oth');
                                        $tClass = ($row['reg_type'] === 'public') ? 'bp-pub' : 'bp-bklt';
                                        $tLabel = ($row['reg_type'] === 'public') ? '<i class="fas fa-globe me-1"></i>Public' : '<i class="fas fa-clipboard me-1"></i>Booklet';
                                    ?>
                                    <tr>
                                        <td><span class="row-num"><?php echo $sno++; ?></span></td>
                                        <td>
                                            <strong style="color:#1f2937;"><?php echo e($row['r_name']); ?></strong>
                                            <div style="font-size:.7rem;color:#9ca3af;"><?php echo e($row['r_email']); ?></div>
                                        </td>
                                        <td style="font-size:.82rem;"><?php echo e($row['r_contact']); ?></td>
                                        <td>
                                            <span class="bp" style="background:#f0fdf4;color:#166534;">
                                                <?php echo e($row['r_catgry'] ?: '—'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="bp <?php echo $gClass; ?>">
                                                <?php echo e($row['r_gender'] ?: '—'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="bp <?php echo $tClass; ?>">
                                                <?php echo $tLabel; ?>
                                            </span>
                                        </td>
                                        <td style="font-size:.78rem;color:#6b7280;">
                                            <?php
                                            if (!empty($row['fname'])) {
                                                echo e(trim($row['fname'].' '.$row['lname']));
                                            } else {
                                                echo '<span style="color:#d1d5db;">—</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span style="font-size:.75rem;color:#6b7280;white-space:nowrap;">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('d M y', strtotime($row['reg_dt'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    </tbody>
                                </table>
                                <?php else: ?>
                                <div class="empty-st">
                                    <i class="fas fa-running"></i>
                                    <p>No registrations yet.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="col-xl-4">

                        <!-- Top Referrers -->
                        <div class="table-card">
                            <div class="table-header">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-share-alt text-white"></i>
                                    <h6 style="margin:0;">Top Referrers</h6>
                                </div>
                            </div>
                            <?php
                            $topRefRows = [];
                            while ($row = $topRefResult->fetch_assoc()) {
                                $topRefRows[] = $row;
                            }
                            ?>
                            <?php if (!empty($topRefRows)): ?>
                            <?php foreach ($topRefRows as $i => $ref): ?>
                            <div class="ref-item">
                                <div class="ref-avatar">
                                    <?php echo strtoupper(substr($ref['fname'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="ref-name"><?php echo e($ref['fname'].' '.$ref['lname']); ?></div>
                                    <div class="ref-code"><?php echo e($ref['referral_code']); ?></div>
                                </div>
                                <div class="ref-count">
                                    <i class="fas fa-users me-1"></i><?php echo $ref['ref_count']; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="empty-st">
                                <i class="fas fa-share-alt"></i>
                                <p>No referrals yet.</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- User-wise Runners -->
                        <div class="table-card">
                            <div class="table-header">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-user-tie text-white"></i>
                                    <h6 style="margin:0;">Volunteer Performance</h6>
                                </div>
                            </div>
                            <div style="padding:16px;">
                                <?php while ($row = $userResult->fetch_assoc()):
                                    // Max for progress bar
                                    $maxCount = $totalRunners > 0 ? $totalRunners : 1;
                                    $pct = min(100, round(($row['cnt'] / $maxCount) * 100));
                                ?>
                                <div style="margin-bottom:14px;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                                        <span style="font-size:.82rem;font-weight:700;color:#1f2937;">
                                            <?php echo e($row['fname'].' '.$row['lname']); ?>
                                        </span>
                                        <span style="font-size:.78rem;font-weight:700;color:#6c63ff;">
                                            <?php echo $row['cnt']; ?>
                                        </span>
                                    </div>
                                    <div class="prog-bar">
                                        <div class="prog-fill" style="width:<?php echo $pct; ?>%"></div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </main>
        <?php include_once('../includes/footer.php'); ?>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/scripts.js"></script>

<script>
Chart.defaults.font.family = 'Inter';
Chart.defaults.color = '#6b7280';

// ── Daily Registrations Bar Chart
new Chart(document.getElementById('dailyChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($dailyLabels); ?>,
        datasets: [{
            label: 'Registrations',
            data:  <?php echo json_encode($dailyCounts); ?>,
            backgroundColor: 'rgba(108,99,255,0.15)',
            borderColor:     '#6c63ff',
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1a1a2e',
                titleColor: '#fff',
                bodyColor: 'rgba(255,255,255,0.7)',
                cornerRadius: 10,
                padding: 12
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { size: 11 } }
            },
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { stepSize: 1, font: { size: 11 } }
            }
        }
    }
});

// ── Gender Doughnut Chart
new Chart(document.getElementById('genderChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($genLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($genCounts); ?>,
            backgroundColor: ['#6c63ff','#f50057','#06b6d4','#f59e0b'],
            borderWidth: 3,
            borderColor: '#fff',
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 14, font: { size: 11 } }
            },
            tooltip: {
                backgroundColor: '#1a1a2e',
                cornerRadius: 10, padding: 12,
                titleColor:'#fff', bodyColor:'rgba(255,255,255,0.7)'
            }
        }
    }
});

// ── Category Bar Chart (horizontal)
new Chart(document.getElementById('catChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($catLabels); ?>,
        datasets: [{
            label: 'Runners',
            data:  <?php echo json_encode($catCounts); ?>,
            backgroundColor: [
                'rgba(108,99,255,0.8)','rgba(245,0,87,0.8)',
                'rgba(0,180,216,0.8)','rgba(245,158,11,0.8)',
                'rgba(34,197,94,0.8)','rgba(99,102,241,0.8)'
            ],
            borderRadius: 6
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1a1a2e',
                cornerRadius: 10, padding: 12,
                titleColor:'#fff', bodyColor:'rgba(255,255,255,0.7)'
            }
        },
        scales: {
            x: { beginAtZero: true, grid: { color:'rgba(0,0,0,0.04)' }, ticks: { font:{size:10} } },
            y: { grid: { display: false }, ticks: { font:{size:11} } }
        }
    }
});

// ── T-Shirt Bar Chart
new Chart(document.getElementById('tshirtChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($tshirtLabels); ?>,
        datasets: [{
            label: 'Runners',
            data:  <?php echo json_encode($tshirtCounts); ?>,
            backgroundColor: 'rgba(245,158,11,0.2)',
            borderColor: '#f59e0b',
            borderWidth: 2,
            borderRadius: 7
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor:'#1a1a2e', cornerRadius:10, padding:12,
                titleColor:'#fff', bodyColor:'rgba(255,255,255,0.7)'
            }
        },
        scales: {
            x: { grid:{display:false}, ticks:{font:{size:11}} },
            y: { beginAtZero:true, grid:{color:'rgba(0,0,0,0.04)'}, ticks:{stepSize:1,font:{size:10}} }
        }
    }
});

// ── Blood Group Pie Chart
new Chart(document.getElementById('bloodChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($bloodLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($bloodCounts); ?>,
            backgroundColor: [
                '#dc2626','#6c63ff','#00b4d8','#f59e0b',
                '#22c55e','#f50057','#6366f1','#06b6d4'
            ],
            borderWidth: 3,
            borderColor: '#fff',
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding:10, font:{size:10} }
            },
            tooltip: {
                backgroundColor:'#1a1a2e', cornerRadius:10, padding:10,
                titleColor:'#fff', bodyColor:'rgba(255,255,255,0.7)'
            }
        }
    }
});
</script>
</body>
</html>
