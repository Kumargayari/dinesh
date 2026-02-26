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

// ── Filters
$reportType = trim($_GET['report_type'] ?? 'all');
$dateFrom   = trim($_GET['date_from']   ?? '');
$dateTo     = trim($_GET['date_to']     ?? '');
$gender     = trim($_GET['gender']      ?? '');
$category   = trim($_GET['category']    ?? '');
$regType    = trim($_GET['reg_type']    ?? '');
$userId     = trim($_GET['user_id']     ?? '');
$tshirt     = trim($_GET['tshirt']      ?? '');
$bloodGroup = trim($_GET['blood_group'] ?? '');

// ── Build WHERE
$where  = "WHERE 1=1";
$types  = '';
$params = [];

if (!empty($dateFrom)) { $where .= " AND DATE(r.reg_dt) >= ?"; $types .= 's'; $params[] = $dateFrom; }
if (!empty($dateTo))   { $where .= " AND DATE(r.reg_dt) <= ?"; $types .= 's'; $params[] = $dateTo;   }
if (!empty($gender))   { $where .= " AND r.r_gender = ?";      $types .= 's'; $params[] = $gender;   }
if (!empty($category)) { $where .= " AND r.r_catgry = ?";      $types .= 's'; $params[] = $category; }
if (!empty($regType))  { $where .= " AND r.reg_type = ?";      $types .= 's'; $params[] = $regType;  }
if (!empty($tshirt))   { $where .= " AND r.r_tshirt_sz = ?";   $types .= 's'; $params[] = $tshirt;   }
if (!empty($bloodGroup)){ $where .= " AND r.r_bdgp = ?";       $types .= 's'; $params[] = $bloodGroup; }
if (!empty($userId))   { $where .= " AND r.u_id = ?";          $types .= 's'; $params[] = $userId;   }

// ── Total with filters
$cntStmt = $con->prepare("SELECT COUNT(*) AS c FROM runner r LEFT JOIN users u ON u.id = r.u_id $where");
if (!empty($params)) $cntStmt->bind_param($types, ...$params);
$cntStmt->execute();
$filteredTotal = (int)$cntStmt->get_result()->fetch_assoc()['c'];
$cntStmt->close();

// ── Main data
$mainSQL = "
    SELECT r.r_id, r.r_name, r.r_email, r.r_contact,
           r.r_gender, r.r_dob, r.r_bdgp, r.r_catgry,
           r.r_tshirt_sz, r.r_emrg_con, r.r_med_dt,
           r.reg_type, r.referral_code, r.reg_dt,
           b.b_name,
           u.fname, u.lname
    FROM runner r
    LEFT JOIN booklet b ON b.id = r.b_id
    LEFT JOIN users   u ON u.id = r.u_id
    $where
    ORDER BY r.r_id DESC
";
$mainStmt = $con->prepare($mainSQL);
if (!empty($params)) $mainStmt->bind_param($types, ...$params);
$mainStmt->execute();
$mainResult = $mainStmt->get_result();
$mainStmt->close();

// ── Summary Stats (filtered)
$summarySQL = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN r_gender='Male'   THEN 1 ELSE 0 END) AS male,
        SUM(CASE WHEN r_gender='Female' THEN 1 ELSE 0 END) AS female,
        SUM(CASE WHEN r_gender='Other'  THEN 1 ELSE 0 END) AS other_g,
        SUM(CASE WHEN reg_type='public' THEN 1 ELSE 0 END) AS public_r,
        SUM(CASE WHEN reg_type='booklet' OR reg_type IS NULL THEN 1 ELSE 0 END) AS booklet_r,
        SUM(CASE WHEN DATE(reg_dt) = CURDATE() THEN 1 ELSE 0 END) AS today
    FROM runner r $where
";
$sumStmt = $con->prepare($summarySQL);
if (!empty($params)) $sumStmt->bind_param($types, ...$params);
$sumStmt->execute();
$summary = $sumStmt->get_result()->fetch_assoc();
$sumStmt->close();

// ── Category Breakdown
$catSQL = "
    SELECT r_catgry, COUNT(*) AS cnt
    FROM runner r $where AND r_catgry IS NOT NULL AND r_catgry != ''
    GROUP BY r_catgry ORDER BY cnt DESC
";
$catStmt = $con->prepare($catSQL);
if (!empty($params)) $catStmt->bind_param($types, ...$params);
$catStmt->execute();
$catBreak = $catStmt->get_result();
$catStmt->close();

// ── T-Shirt Breakdown
$tshirtSQL = "
    SELECT r_tshirt_sz, COUNT(*) AS cnt
    FROM runner r $where AND r_tshirt_sz IS NOT NULL AND r_tshirt_sz != ''
    GROUP BY r_tshirt_sz ORDER BY FIELD(r_tshirt_sz,'XS','S','M','L','XL','XXL','XXXL')
";
$tshirtStmt = $con->prepare($tshirtSQL);
if (!empty($params)) $tshirtStmt->bind_param($types, ...$params);
$tshirtStmt->execute();
$tshirtBreak = $tshirtStmt->get_result();
$tshirtStmt->close();

// ── Blood Group Breakdown
$bloodSQL = "
    SELECT r_bdgp, COUNT(*) AS cnt
    FROM runner r $where AND r_bdgp IS NOT NULL AND r_bdgp != ''
    GROUP BY r_bdgp ORDER BY cnt DESC
";
$bloodStmt = $con->prepare($bloodSQL);
if (!empty($params)) $bloodStmt->bind_param($types, ...$params);
$bloodStmt->execute();
$bloodBreak = $bloodStmt->get_result();
$bloodStmt->close();

// ── Daily Trend (filtered range or last 30 days)
$trendFrom = !empty($dateFrom) ? $dateFrom : date('Y-m-d', strtotime('-29 days'));
$trendTo   = !empty($dateTo)   ? $dateTo   : date('Y-m-d');
$trendResult = $con->query("
    SELECT DATE(reg_dt) AS day, COUNT(*) AS cnt
    FROM runner
    WHERE DATE(reg_dt) BETWEEN '$trendFrom' AND '$trendTo'
    GROUP BY DATE(reg_dt)
    ORDER BY day ASC
");
$trendLabels = []; $trendCounts = [];
while ($row = $trendResult->fetch_assoc()) {
    $trendLabels[] = date('d M', strtotime($row['day']));
    $trendCounts[] = (int)$row['cnt'];
}

// ── Volunteer-wise stats
$volResult = $con->query("
    SELECT u.id, u.fname, u.lname,
           COUNT(r.r_id) AS total,
           SUM(CASE WHEN DATE(r.reg_dt)=CURDATE() THEN 1 ELSE 0 END) AS today
    FROM users u
    LEFT JOIN runner r ON r.u_id = u.id AND r.reg_type = 'booklet'
    GROUP BY u.id
    ORDER BY total DESC
");

// ── Filter dropdowns
function getDistinct($con, $col, $table = 'runner') {
    $res = $con->query("SELECT DISTINCT $col FROM $table WHERE $col IS NOT NULL AND $col != '' ORDER BY $col");
    $data = [];
    while ($r = $res->fetch_row()) $data[] = $r[0];
    return $data;
}
$genderOpts   = getDistinct($con, 'r_gender');
$categoryOpts = getDistinct($con, 'r_catgry');
$tshirtOpts   = getDistinct($con, 'r_tshirt_sz');
$bloodOpts    = getDistinct($con, 'r_bdgp');
$usersResult  = $con->query("SELECT id, fname, lname FROM users ORDER BY fname");

// ── Chart data arrays
$catChartLabels = []; $catChartData = [];
$catResultChart = $con->query("SELECT r_catgry, COUNT(*) AS cnt FROM runner WHERE r_catgry IS NOT NULL AND r_catgry != '' GROUP BY r_catgry ORDER BY cnt DESC");
while ($row = $catResultChart->fetch_assoc()) {
    $catChartLabels[] = $row['r_catgry'];
    $catChartData[]   = (int)$row['cnt'];
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Reports — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet"/>
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet"/>
    <link href="../css/styles.css" rel="stylesheet" onerror="this.remove()"/>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f4f6fb; }

        #layoutSidenav_content { display:flex; flex-direction:column; min-height:calc(100vh - 56px); }
        #layoutSidenav_content main { flex:1; }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg,#1a1a2e,#6c63ff 60%,#f50057);
            border-radius:16px; padding:22px 28px;
            display:flex; align-items:center;
            justify-content:space-between; flex-wrap:wrap; gap:12px;
            margin-bottom:28px;
            box-shadow:0 10px 30px rgba(108,99,255,0.3);
        }
        .page-header h2 { color:#fff; font-size:1.4rem; font-weight:800; margin:0; }
        .page-header p  { color:rgba(255,255,255,0.6); font-size:0.82rem; margin:4px 0 0; }
        .ph-badge {
            background:rgba(255,255,255,0.15); color:#fff;
            border:1px solid rgba(255,255,255,0.2);
            border-radius:20px; padding:5px 14px;
            font-size:0.78rem; font-weight:700;
        }

        /* Filter Card */
        .filter-card {
            background:#fff; border:none; border-radius:16px;
            box-shadow:0 4px 20px rgba(0,0,0,0.07);
            overflow:hidden; margin-bottom:24px;
        }
        .filter-header {
            background:linear-gradient(135deg,#1a1a2e,#16213e);
            padding:14px 22px; display:flex;
            align-items:center; gap:10px;
        }
        .filter-header h6 { color:#fff; margin:0; font-size:0.88rem; font-weight:700; }
        .filter-body { padding:20px 22px; }

        .form-label {
            font-size:0.72rem; font-weight:700;
            color:#6b7280; text-transform:uppercase; letter-spacing:0.5px;
            margin-bottom:5px;
        }
        .form-control, .form-select {
            border-radius:9px; border:1.5px solid #e5e7eb;
            font-size:0.84rem; color:#374151;
            padding:8px 12px; background:#fafafa;
            transition:all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color:#6c63ff;
            box-shadow:0 0 0 3px rgba(108,99,255,0.1);
            background:#fff;
        }

        .btn-filter {
            background:linear-gradient(135deg,#6c63ff,#574fd6);
            color:#fff; border:none; border-radius:9px;
            padding:9px 20px; font-size:0.84rem; font-weight:700;
        }
        .btn-filter:hover { opacity:.88; color:#fff; }
        .btn-reset {
            background:#f3f4f6; color:#374151;
            border:1px solid #e5e7eb; border-radius:9px;
            padding:9px 16px; font-size:0.84rem; font-weight:600;
        }
        .btn-reset:hover { background:#e5e7eb; }

        /* Summary Cards */
        .sum-card {
            border:none; border-radius:14px; padding:18px 20px;
            position:relative; overflow:hidden;
            margin-bottom:20px; text-decoration:none; display:block;
            transition:transform 0.2s;
        }
        .sum-card:hover { transform:translateY(-3px); }
        .sum-card::before {
            content:''; position:absolute; top:-20px; right:-20px;
            width:70px; height:70px; border-radius:50%;
            background:rgba(255,255,255,0.08);
        }
        .sc-1 { background:linear-gradient(135deg,#6c63ff,#574fd6); box-shadow:0 6px 20px rgba(108,99,255,0.35); }
        .sc-2 { background:linear-gradient(135deg,#00b4d8,#0077b6); box-shadow:0 6px 20px rgba(0,180,216,0.3);   }
        .sc-3 { background:linear-gradient(135deg,#f50057,#c2003d); box-shadow:0 6px 20px rgba(245,0,87,0.3);    }
        .sc-4 { background:linear-gradient(135deg,#22c55e,#15803d); box-shadow:0 6px 20px rgba(34,197,94,0.3);   }
        .sc-5 { background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow:0 6px 20px rgba(245,158,11,0.3);  }
        .sc-6 { background:linear-gradient(135deg,#6366f1,#4338ca); box-shadow:0 6px 20px rgba(99,102,241,0.3);  }

        .sc-icon {
            width:38px; height:38px; border-radius:9px;
            background:rgba(255,255,255,0.15);
            display:flex; align-items:center; justify-content:center;
            font-size:15px; color:#fff; margin-bottom:12px;
        }
        .sc-label { color:rgba(255,255,255,0.65); font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; }
        .sc-value { color:#fff; font-size:1.9rem; font-weight:900; line-height:1; margin:5px 0; }
        .sc-sub   { color:rgba(255,255,255,0.5); font-size:0.7rem; }

        /* Chart / Table Cards */
        .panel {
            background:#fff; border:none; border-radius:16px;
            box-shadow:0 4px 20px rgba(0,0,0,0.07);
            overflow:hidden; margin-bottom:24px;
        }
        .panel-header {
            background:linear-gradient(135deg,#1a1a2e,#16213e);
            padding:14px 20px;
            display:flex; align-items:center;
            justify-content:space-between; gap:10px; flex-wrap:wrap;
        }
        .panel-icon {
            width:30px; height:30px; border-radius:8px;
            display:flex; align-items:center; justify-content:center;
            font-size:12px; color:#fff;
        }
        .panel-header h6 { color:#fff; margin:0; font-size:0.88rem; font-weight:700; }
        .panel-header p  { color:rgba(255,255,255,0.4); margin:0; font-size:0.7rem; }
        .panel-body { padding:20px; }

        /* Breakdown rows */
        .break-item {
            display:flex; align-items:center; gap:10px;
            padding:9px 20px; border-bottom:1px solid #f3f4f6;
        }
        .break-item:last-child { border-bottom:none; }
        .break-label { font-size:0.84rem; font-weight:600; color:#374151; min-width:80px; }
        .break-bar-wrap { flex:1; }
        .break-bar { height:7px; border-radius:10px; background:#f3f4f6; overflow:hidden; }
        .break-fill { height:100%; border-radius:10px; background:linear-gradient(90deg,#6c63ff,#f50057); transition:width 0.6s; }
        .break-count { font-size:0.82rem; font-weight:800; color:#6c63ff; min-width:40px; text-align:right; }

        /* Main Table */
        .ctbl { width:100%; border-collapse:collapse; }
        .ctbl thead th {
            background:#f8f7ff; color:#6c63ff;
            font-size:0.7rem; font-weight:700;
            text-transform:uppercase; letter-spacing:0.8px;
            padding:11px 14px; border-bottom:2px solid #ede9ff;
            white-space:nowrap;
        }
        .ctbl tbody td {
            padding:10px 14px; font-size:0.83rem; color:#374151;
            border-bottom:1px solid #f3f4f6; vertical-align:middle;
        }
        .ctbl tbody tr:hover { background:#faf9ff; }
        .ctbl tbody tr:last-child td { border-bottom:none; }

        /* Badges */
        .bp { display:inline-block; border-radius:7px; padding:3px 9px; font-size:0.72rem; font-weight:700; }
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

        /* Export buttons */
        .btn-excel { background:linear-gradient(135deg,#166534,#15803d)!important; color:#fff!important; border:none!important; border-radius:8px!important; font-size:.8rem!important; font-weight:600!important; padding:7px 14px!important; }
        .btn-csv   { background:linear-gradient(135deg,#0e7490,#0369a1)!important; color:#fff!important; border:none!important; border-radius:8px!important; font-size:.8rem!important; font-weight:600!important; padding:7px 14px!important; }
        .btn-pdf   { background:linear-gradient(135deg,#dc2626,#b91c1c)!important; color:#fff!important; border:none!important; border-radius:8px!important; font-size:.8rem!important; font-weight:600!important; padding:7px 14px!important; }
        .btn-print { background:linear-gradient(135deg,#374151,#1f2937)!important; color:#fff!important; border:none!important; border-radius:8px!important; font-size:.8rem!important; font-weight:600!important; padding:7px 14px!important; }

        /* DataTables override */
        div.dataTables_wrapper div.dataTables_filter input,
        div.dataTables_wrapper div.dataTables_length select {
            border-radius:8px; border:1.5px solid #e5e7eb; padding:5px 10px; font-size:.82rem;
        }
        div.dataTables_wrapper div.dataTables_info { font-size:.78rem; color:#9ca3af; }
        .dataTables_paginate .paginate_button { border-radius:8px!important; margin:0 2px!important; font-size:.8rem!important; }
        .dataTables_paginate .paginate_button.current,
        .dataTables_paginate .paginate_button.current:hover { background:#6c63ff!important; color:#fff!important; border-color:#6c63ff!important; }
        .dataTables_paginate .paginate_button:hover { background:#ede9fe!important; color:#6c63ff!important; border-color:#ede9fe!important; }

        /* Volunteer row */
        .vol-item {
            display:flex; align-items:center; gap:12px;
            padding:11px 20px; border-bottom:1px solid #f3f4f6;
        }
        .vol-item:last-child { border-bottom:none; }
        .vol-avatar {
            width:36px; height:36px; border-radius:9px;
            background:linear-gradient(135deg,#6c63ff,#574fd6);
            display:flex; align-items:center; justify-content:center;
            color:#fff; font-size:13px; font-weight:700; flex-shrink:0;
        }
        .vol-name  { font-size:.85rem; font-weight:700; color:#1f2937; }
        .vol-today { font-size:.72rem; color:#9ca3af; margin-top:1px; }
        .vol-count {
            margin-left:auto; background:linear-gradient(135deg,#6c63ff,#f50057);
            color:#fff; border-radius:20px; padding:3px 12px;
            font-size:.75rem; font-weight:800;
        }

        .empty-st { text-align:center; padding:30px; color:#9ca3af; }
        .empty-st i { font-size:2rem; opacity:.3; display:block; margin-bottom:8px; }

        /* Print */
        @media print {
            .no-print, #layoutSidenav_nav, nav, .filter-card, .page-header { display:none!important; }
            body { background:#fff!important; }
            .panel { box-shadow:none!important; border:1px solid #e5e7eb!important; }
        }
    </style>
</head>
<body class="sb-nav-fixed">

<?php include_once('includes/navbar.php'); ?>

<div id="layoutSidenav">
    <?php include_once('includes/sidebar.php'); ?>

    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4 pb-5">

                <h1 class="mt-4">Reports</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Reports</li>
                </ol>

                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h2><i class="fas fa-file-alt me-2"></i>Runner Reports</h2>
                        <p>Filter, analyze and export runner data</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <span class="ph-badge"><i class="fas fa-filter me-1"></i><?php echo number_format($filteredTotal); ?> Results</span>
                        <button onclick="window.print()" class="btn btn-sm no-print"
                                style="background:rgba(255,255,255,0.15);color:#fff;border-radius:8px;font-weight:600;border:1px solid rgba(255,255,255,0.2);">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                    </div>
                </div>

                <!-- ── Filter Card -->
                <div class="filter-card no-print">
                    <div class="filter-header">
                        <i class="fas fa-sliders-h" style="color:#6c63ff;font-size:14px;"></i>
                        <h6>Filter Report</h6>
                    </div>
                    <div class="filter-body">
                        <form method="GET" action="reports.php">
                            <div class="row g-3 align-items-end">

                                <div class="col-md-2">
                                    <label class="form-label">From Date</label>
                                    <input type="date" name="date_from" class="form-control"
                                           value="<?php echo e($dateFrom); ?>"/>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">To Date</label>
                                    <input type="date" name="date_to" class="form-control"
                                           value="<?php echo e($dateTo); ?>"/>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="">All Genders</option>
                                        <?php foreach ($genderOpts as $g): ?>
                                        <option value="<?php echo e($g); ?>" <?php echo ($gender===$g)?'selected':''; ?>>
                                            <?php echo e($g); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-select">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categoryOpts as $c): ?>
                                        <option value="<?php echo e($c); ?>" <?php echo ($category===$c)?'selected':''; ?>>
                                            <?php echo e($c); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Reg. Type</label>
                                    <select name="reg_type" class="form-select">
                                        <option value="">All Types</option>
                                        <option value="public"  <?php echo ($regType==='public') ?'selected':''; ?>>Public (Online)</option>
                                        <option value="booklet" <?php echo ($regType==='booklet')?'selected':''; ?>>Booklet (Volunteer)</option>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">T-Shirt Size</label>
                                    <select name="tshirt" class="form-select">
                                        <option value="">All Sizes</option>
                                        <?php foreach ($tshirtOpts as $t): ?>
                                        <option value="<?php echo e($t); ?>" <?php echo ($tshirt===$t)?'selected':''; ?>>
                                            <?php echo e($t); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Blood Group</label>
                                    <select name="blood_group" class="form-select">
                                        <option value="">All Groups</option>
                                        <?php foreach ($bloodOpts as $b): ?>
                                        <option value="<?php echo e($b); ?>" <?php echo ($bloodGroup===$b)?'selected':''; ?>>
                                            <?php echo e($b); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Volunteer</label>
                                    <select name="user_id" class="form-select">
                                        <option value="">All Volunteers</option>
                                        <?php while ($uRow = $usersResult->fetch_assoc()): ?>
                                        <option value="<?php echo $uRow['id']; ?>"
                                            <?php echo ($userId == $uRow['id'])?'selected':''; ?>>
                                            <?php echo e($uRow['fname'].' '.$uRow['lname']); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <!-- Date quick filters -->
                                <div class="col-md-4">
                                    <label class="form-label">Quick Date</label>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="reports.php?date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>"
                                           class="btn btn-sm" style="background:#ede9fe;color:#6c63ff;border-radius:7px;font-size:.78rem;font-weight:600;">
                                            Today
                                        </a>
                                        <a href="reports.php?date_from=<?php echo date('Y-m-d', strtotime('this week')); ?>&date_to=<?php echo date('Y-m-d'); ?>"
                                           class="btn btn-sm" style="background:#dbeafe;color:#1e40af;border-radius:7px;font-size:.78rem;font-weight:600;">
                                            This Week
                                        </a>
                                        <a href="reports.php?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>"
                                           class="btn btn-sm" style="background:#dcfce7;color:#166534;border-radius:7px;font-size:.78rem;font-weight:600;">
                                            This Month
                                        </a>
                                        <a href="reports.php?date_from=<?php echo date('Y-01-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>"
                                           class="btn btn-sm" style="background:#fff7ed;color:#c2410c;border-radius:7px;font-size:.78rem;font-weight:600;">
                                            This Year
                                        </a>
                                    </div>
                                </div>

                                <div class="col-md-2 d-flex gap-2">
                                    <button type="submit" class="btn btn-filter w-100">
                                        <i class="fas fa-search me-1"></i> Generate
                                    </button>
                                    <a href="reports.php" class="btn btn-reset">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>

                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── Summary Stats -->
                <div class="row">
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="sum-card sc-1">
                            <div class="sc-icon"><i class="fas fa-users"></i></div>
                            <div class="sc-label">Total</div>
                            <div class="sc-value"><?php echo number_format((int)$summary['total']); ?></div>
                            <div class="sc-sub">Filtered results</div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="sum-card sc-2">
                            <div class="sc-icon"><i class="fas fa-mars"></i></div>
                            <div class="sc-label">Male</div>
                            <div class="sc-value"><?php echo number_format((int)$summary['male']); ?></div>
                            <div class="sc-sub">
                                <?php $t = (int)$summary['total'];
                                echo $t > 0 ? round(($summary['male']/$t)*100).'%' : '0%'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="sum-card sc-3">
                            <div class="sc-icon"><i class="fas fa-venus"></i></div>
                            <div class="sc-label">Female</div>
                            <div class="sc-value"><?php echo number_format((int)$summary['female']); ?></div>
                            <div class="sc-sub">
                                <?php echo $t > 0 ? round(($summary['female']/$t)*100).'%' : '0%'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="sum-card sc-4">
                            <div class="sc-icon"><i class="fas fa-globe"></i></div>
                            <div class="sc-label">Public</div>
                            <div class="sc-value"><?php echo number_format((int)$summary['public_r']); ?></div>
                            <div class="sc-sub">Online form</div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="sum-card sc-5">
                            <div class="sc-icon"><i class="fas fa-book"></i></div>
                            <div class="sc-label">Booklet</div>
                            <div class="sc-value"><?php echo number_format((int)$summary['booklet_r']); ?></div>
                            <div class="sc-sub">Via volunteers</div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="sum-card sc-6">
                            <div class="sc-icon"><i class="fas fa-calendar-day"></i></div>
                            <div class="sc-label">Today</div>
                            <div class="sc-value"><?php echo number_format((int)$summary['today']); ?></div>
                            <div class="sc-sub"><?php echo date('d M Y'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- ── Trend Chart + Breakdowns -->
                <div class="row">

                    <!-- Trend Chart -->
                    <div class="col-xl-8">
                        <div class="panel">
                            <div class="panel-header">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="panel-icon" style="background:linear-gradient(135deg,#6c63ff,#574fd6);">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div>
                                        <h6>Registration Trend</h6>
                                        <p>
                                            <?php echo date('d M Y', strtotime($trendFrom)); ?>
                                            — <?php echo date('d M Y', strtotime($trendTo)); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="panel-body">
                                <canvas id="trendChart" height="110"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Breakdowns -->
                    <div class="col-xl-4">

                        <!-- Category Breakdown -->
                        <div class="panel">
                            <div class="panel-header">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="panel-icon" style="background:linear-gradient(135deg,#00b4d8,#0077b6);">
                                        <i class="fas fa-flag-checkered"></i>
                                    </div>
                                    <div>
                                        <h6>Category Breakdown</h6>
                                        <p>Runners per race type</p>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $catRows = []; $catMax = 1;
                            while ($row = $catBreak->fetch_assoc()) {
                                $catRows[] = $row;
                                if ((int)$row['cnt'] > $catMax) $catMax = (int)$row['cnt'];
                            }
                            if (!empty($catRows)):
                            foreach ($catRows as $row):
                                $pct = round(((int)$row['cnt'] / $catMax) * 100);
                            ?>
                            <div class="break-item">
                                <div class="break-label"><?php echo e($row['r_catgry']); ?></div>
                                <div class="break-bar-wrap">
                                    <div class="break-bar">
                                        <div class="break-fill" style="width:<?php echo $pct; ?>%"></div>
                                    </div>
                                </div>
                                <div class="break-count"><?php echo $row['cnt']; ?></div>
                            </div>
                            <?php endforeach;
                            else: ?>
                            <div class="empty-st"><i class="fas fa-flag"></i><p>No data</p></div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

                <!-- ── T-Shirt + Blood + Volunteer -->
                <div class="row">

                    <!-- T-Shirt -->
                    <div class="col-xl-4">
                        <div class="panel">
                            <div class="panel-header">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="panel-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                                        <i class="fas fa-tshirt"></i>
                                    </div>
                                    <div>
                                        <h6>T-Shirt Sizes</h6>
                                        <p>Required per size</p>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $tshirtRows = []; $tshirtMax = 1;
                            while ($row = $tshirtBreak->fetch_assoc()) {
                                $tshirtRows[] = $row;
                                if ((int)$row['cnt'] > $tshirtMax) $tshirtMax = (int)$row['cnt'];
                            }
                            if (!empty($tshirtRows)):
                            foreach ($tshirtRows as $row):
                                $pct = round(((int)$row['cnt'] / $tshirtMax) * 100);
                            ?>
                            <div class="break-item">
                                <div class="break-label">
                                    <span style="background:#fef9c3;color:#854d0e;border-radius:6px;padding:2px 8px;font-size:.72rem;font-weight:700;">
                                        <?php echo e($row['r_tshirt_sz']); ?>
                                    </span>
                                </div>
                                <div class="break-bar-wrap">
                                    <div class="break-bar">
                                        <div class="break-fill" style="width:<?php echo $pct; ?>%;background:linear-gradient(90deg,#f59e0b,#d97706);"></div>
                                    </div>
                                </div>
                                <div class="break-count" style="color:#d97706;"><?php echo $row['cnt']; ?></div>
                            </div>
                            <?php endforeach;
                            else: ?>
                            <div class="empty-st"><i class="fas fa-tshirt"></i><p>No data</p></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Blood Group -->
                    <div class="col-xl-4">
                        <div class="panel">
                            <div class="panel-header">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="panel-icon" style="background:linear-gradient(135deg,#dc2626,#b91c1c);">
                                        <i class="fas fa-tint"></i>
                                    </div>
                                    <div>
                                        <h6>Blood Groups</h6>
                                        <p>Distribution</p>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $bloodRows = []; $bloodMax = 1;
                            while ($row = $bloodBreak->fetch_assoc()) {
                                $bloodRows[] = $row;
                                if ((int)$row['cnt'] > $bloodMax) $bloodMax = (int)$row['cnt'];
                            }
                            if (!empty($bloodRows)):
                            foreach ($bloodRows as $row):
                                $pct = round(((int)$row['cnt'] / $bloodMax) * 100);
                            ?>
                            <div class="break-item">
                                <div class="break-label">
                                    <span style="background:#fff7ed;color:#c2410c;border-radius:6px;padding:2px 8px;font-size:.72rem;font-weight:700;">
                                        <?php echo e($row['r_bdgp']); ?>
                                    </span>
                                </div>
                                <div class="break-bar-wrap">
                                    <div class="break-bar">
                                        <div class="break-fill" style="width:<?php echo $pct; ?>%;background:linear-gradient(90deg,#dc2626,#b91c1c);"></div>
                                    </div>
                                </div>
                                <div class="break-count" style="color:#dc2626;"><?php echo $row['cnt']; ?></div>
                            </div>
                            <?php endforeach;
                            else: ?>
                            <div class="empty-st"><i class="fas fa-tint"></i><p>No data</p></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Volunteer Performance -->
                    <div class="col-xl-4">
                        <div class="panel">
                            <div class="panel-header">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="panel-icon" style="background:linear-gradient(135deg,#22c55e,#15803d);">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div>
                                        <h6>Volunteer Performance</h6>
                                        <p>Booklet registrations</p>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $volRows = [];
                            while ($row = $volResult->fetch_assoc()) $volRows[] = $row;
                            if (!empty($volRows)):
                            foreach ($volRows as $row): ?>
                            <div class="vol-item">
                                <div class="vol-avatar">
                                    <?php echo strtoupper(substr($row['fname'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="vol-name"><?php echo e($row['fname'].' '.$row['lname']); ?></div>
                                    <div class="vol-today">Today: <?php echo $row['today']; ?></div>
                                </div>
                                <div class="vol-count">
                                    <i class="fas fa-running me-1"></i><?php echo $row['total']; ?>
                                </div>
                            </div>
                            <?php endforeach;
                            else: ?>
                            <div class="empty-st"><i class="fas fa-user-tie"></i><p>No volunteers</p></div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- ── Main Data Table -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="panel-icon" style="background:linear-gradient(135deg,#6c63ff,#574fd6);">
                                <i class="fas fa-table"></i>
                            </div>
                            <div>
                                <h6>Detailed Runner Data</h6>
                                <p><?php echo number_format($filteredTotal); ?> runners found</p>
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap no-print">
                            <button onclick="exportExcel()" class="btn btn-excel">
                                <i class="fas fa-file-excel me-1"></i>Excel
                            </button>
                            <button onclick="exportCSV()" class="btn btn-csv">
                                <i class="fas fa-file-csv me-1"></i>CSV
                            </button>
                            <button onclick="exportPDF()" class="btn btn-pdf">
                                <i class="fas fa-file-pdf me-1"></i>PDF
                            </button>
                            <button onclick="exportPrint()" class="btn btn-print">
                                <i class="fas fa-print me-1"></i>Print
                            </button>
                        </div>
                    </div>
                    <div class="panel-body p-0">
                        <?php if ($filteredTotal > 0): $sno = 1; ?>
                        <div class="table-responsive p-3">
                            <table id="reportTable" class="ctbl" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Gender</th>
                                        <th>DOB</th>
                                        <th>Blood</th>
                                        <th>Category</th>
                                        <th>T-Shirt</th>
                                        <th>Emrg. Contact</th>
                                        <th>Medical</th>
                                        <th>Type</th>
                                        <th>Booklet</th>
                                        <th>Volunteer</th>
                                        <th>Reg. Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php while ($row = $mainResult->fetch_assoc()):
                                    $gClass = ($row['r_gender'] === 'Male') ? 'bp-male' : (($row['r_gender'] === 'Female') ? 'bp-fem' : 'bp-oth');
                                    $tClass = ($row['reg_type'] === 'public') ? 'bp-pub' : 'bp-bklt';
                                    $tLabel = ($row['reg_type'] === 'public')
                                        ? '<i class="fas fa-globe me-1"></i>Public'
                                        : '<i class="fas fa-clipboard me-1"></i>Booklet';
                                ?>
                                <tr>
                                    <td><span class="row-num"><?php echo $sno++; ?></span></td>
                                    <td>
                                        <strong style="color:#1f2937;white-space:nowrap;">
                                            <?php echo e($row['r_name']); ?>
                                        </strong>
                                    </td>
                                    <td style="font-size:.78rem;color:#6b7280;"><?php echo e($row['r_email']); ?></td>
                                    <td style="white-space:nowrap;"><?php echo e($row['r_contact']); ?></td>
                                    <td>
                                        <span class="bp <?php echo $gClass; ?>">
                                            <?php echo e($row['r_gender'] ?: '—'); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:.78rem;white-space:nowrap;">
                                        <?php echo !empty($row['r_dob']) ? date('d M Y', strtotime($row['r_dob'])) : '—'; ?>
                                    </td>
                                    <td>
                                        <span class="bp" style="background:#fff7ed;color:#c2410c;">
                                            <?php echo e($row['r_bdgp'] ?: '—'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="bp" style="background:#dcfce7;color:#166534;">
                                            <?php echo e($row['r_catgry'] ?: '—'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="bp" style="background:#fef9c3;color:#854d0e;">
                                            <?php echo e($row['r_tshirt_sz'] ?: '—'); ?>
                                        </span>
                                    </td>
                                    <td style="white-space:nowrap;"><?php echo e($row['r_emrg_con'] ?: '—'); ?></td>
                                    <td style="font-size:.75rem;color:#9ca3af;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        <?php echo e($row['r_med_dt'] ?: '—'); ?>
                                    </td>
                                    <td>
                                        <span class="bp <?php echo $tClass; ?>"><?php echo $tLabel; ?></span>
                                    </td>
                                    <td style="font-size:.78rem;"><?php echo e($row['b_name'] ?? '—'); ?></td>
                                    <td style="font-size:.78rem;white-space:nowrap;">
                                        <?php echo !empty($row['fname']) ? e(trim($row['fname'].' '.$row['lname'])) : '<span style="color:#d1d5db;">—</span>'; ?>
                                    </td>
                                    <td style="font-size:.75rem;white-space:nowrap;color:#6b7280;">
                                        <?php echo date('d M Y', strtotime($row['reg_dt'])); ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty-st py-5">
                            <i class="fas fa-search d-block mb-3" style="font-size:2.5rem;"></i>
                            <p style="font-size:.9rem;">No runners found with selected filters.</p>
                            <a href="reports.php" class="btn btn-sm btn-filter mt-2">
                                <i class="fas fa-times me-1"></i> Clear Filters
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
        <?php include_once('../includes/footer.php'); ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/scripts.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
Chart.defaults.font.family = 'Inter';
Chart.defaults.color = '#6b7280';

// ── Trend Line Chart
var trendLabels = <?php echo json_encode($trendLabels); ?>;
var trendCounts = <?php echo json_encode($trendCounts); ?>;

if (trendLabels.length > 0) {
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Registrations',
                data:  trendCounts,
                borderColor: '#6c63ff',
                backgroundColor: 'rgba(108,99,255,0.08)',
                borderWidth: 2.5,
                pointBackgroundColor: '#6c63ff',
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4
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
                    cornerRadius: 10, padding: 12
                }
            },
            scales: {
                x: { grid:{display:false}, ticks:{font:{size:10}, maxTicksLimit:10} },
                y: { beginAtZero:true, grid:{color:'rgba(0,0,0,0.04)'}, ticks:{stepSize:1,font:{size:10}} }
            }
        }
    });
} else {
    document.getElementById('trendChart').parentElement.innerHTML =
        '<div class="empty-st"><i class="fas fa-chart-line d-block"></i><p>No data for this period.</p></div>';
}

// ── DataTable
var table;
$(document).ready(function () {
    table = $('#reportTable').DataTable({
        pageLength: 25,
        lengthMenu: [[10,25,50,100,-1],[10,25,50,100,'All']],
        order: [],
        dom: 'lBfrtip',
        buttons: [
            {
                extend: 'excelHtml5', text: 'Excel',
                className: 'd-none',
                title: 'Runner Report — <?php echo date("d M Y"); ?>',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'csvHtml5', text: 'CSV',
                className: 'd-none',
                title: 'Runner Report',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'pdfHtml5', text: 'PDF',
                className: 'd-none',
                title: 'Runner Report — <?php echo date("d M Y"); ?>',
                orientation: 'landscape', pageSize: 'A4',
                exportOptions: { columns: ':visible' },
                customize: function(doc) {
                    doc.styles.tableHeader.fillColor = '#6c63ff';
                    doc.defaultStyle.fontSize = 7;
                }
            },
            {
                extend: 'print', text: 'Print',
                className: 'd-none',
                title: 'Runner Report — <?php echo date("d M Y"); ?>',
                exportOptions: { columns: ':visible' }
            }
        ],
        language: {
            search: '<i class="fas fa-search me-1"></i>',
            searchPlaceholder: 'Search in table...',
            info: 'Showing _START_–_END_ of _TOTAL_ runners',
            paginate: {
                first: '<i class="fas fa-angle-double-left"></i>',
                last:  '<i class="fas fa-angle-double-right"></i>',
                next:  '<i class="fas fa-angle-right"></i>',
                previous: '<i class="fas fa-angle-left"></i>'
            }
        }
    });
});

function exportExcel() { table.button('.buttons-excel').trigger(); }
function exportCSV()   { table.button('.buttons-csv').trigger();   }
function exportPDF()   { table.button('.buttons-pdf').trigger();   }
function exportPrint() { table.button('.buttons-print').trigger(); }
</script>
</body>
</html>
