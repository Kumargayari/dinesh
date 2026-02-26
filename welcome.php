<?php
ob_start();
session_start();
include_once('includes/config.php');

if (empty($_SESSION['id']) || !is_numeric($_SESSION['id'])) {
    session_unset(); session_destroy();
    header('Location: login.php'); exit();
}

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$userid = (int)$_SESSION['id'];

// User info + referral code
$stmt = $con->prepare("SELECT fname, lname, referral_code FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Auto generate referral code if missing
if (empty($user['referral_code'])) {
    do {
        $code = strtoupper(substr(md5($userid . microtime() . rand()), 0, 8));
        $chk  = $con->prepare("SELECT id FROM users WHERE referral_code = ?");
        $chk->bind_param('s', $code);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();
    } while ($exists);

    $upd = $con->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
    $upd->bind_param('si', $code, $userid);
    $upd->execute();
    $upd->close();
    $user['referral_code'] = $code;
}

$myRefCode = $user['referral_code'];

// Booklet count
$stmt = $con->prepare("SELECT COUNT(*) AS c FROM booklet WHERE u_id = ?");
$stmt->bind_param("i", $userid);
$stmt->execute();
$booklet = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// Total runners
$stmt = $con->prepare("SELECT COUNT(*) AS c FROM runner WHERE u_id = ?");
$stmt->bind_param("i", $userid);
$stmt->execute();
$runner = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// Today runners
$stmt = $con->prepare("SELECT COUNT(*) AS c FROM runner WHERE u_id = ? AND DATE(reg_dt) = CURDATE()");
$stmt->bind_param("i", $userid);
$stmt->execute();
$tdrnr = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// Referral count — kitne runners ne is user ka code use kiya
$stmt = $con->prepare("SELECT COUNT(*) AS c FROM runner WHERE referred_by_user = ?");
$stmt->bind_param("i", $userid);
$stmt->execute();
$refCount = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// Booklet status
$bStmt = $con->prepare("
    SELECT b.id, b.b_name, b.b_range, COUNT(r.r_id) AS used
    FROM booklet b
    LEFT JOIN runner r ON r.b_id = b.id
    WHERE b.u_id = ?
    GROUP BY b.id ORDER BY b.id ASC
");
$bStmt->bind_param("i", $userid);
$bStmt->execute();
$bookletResult = $bStmt->get_result();
$bStmt->close();

// Last 7 days runners
$rStmt = $con->prepare("
    SELECT r_name, r_contact, r_catgry, reg_dt
    FROM runner
    WHERE u_id = ? AND DATE(reg_dt) >= NOW() - INTERVAL 7 DAY
    ORDER BY reg_dt DESC
");
$rStmt->bind_param("i", $userid);
$rStmt->execute();
$runner7Result = $rStmt->get_result();
$rStmt->close();

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="css/styles.css" rel="stylesheet" onerror="this.remove()"/>
    <style>
        * { font-family: 'Inter', sans-serif; }

        #layoutSidenav_content { display:flex; flex-direction:column; min-height:calc(100vh - 56px); }
        #layoutSidenav_content main { flex:1; }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg,#6c63ff 0%,#574fd6 50%,#f50057 100%);
            border-radius:16px; padding:22px 28px;
            margin-bottom:28px; box-shadow:0 8px 24px rgba(108,99,255,0.3);
            display:flex; align-items:center;
            justify-content:space-between; flex-wrap:wrap; gap:12px;
        }
        .page-header h2 { color:#fff; font-size:1.4rem; font-weight:700; margin:0; }
        .page-header p  { color:rgba(255,255,255,0.6); font-size:0.82rem; margin:4px 0 0; }

        /* Stat Cards */
        .stat-card {
            border:none; border-radius:16px; padding:22px 24px;
            position:relative; overflow:hidden;
            transition:transform 0.25s ease, box-shadow 0.25s ease;
            cursor:pointer; text-decoration:none; display:block;
            margin-bottom:24px;
        }
        .stat-card:hover { transform:translateY(-4px); }
        .stat-card::before {
            content:''; position:absolute; top:-30px; right:-30px;
            width:100px; height:100px; border-radius:50%;
            background:rgba(255,255,255,0.08);
        }
        .stat-card::after {
            content:''; position:absolute; bottom:-20px; right:20px;
            width:70px; height:70px; border-radius:50%;
            background:rgba(255,255,255,0.05);
        }
        .stat-card-purple { background:linear-gradient(135deg,#6c63ff,#574fd6); box-shadow:0 8px 24px rgba(108,99,255,0.35); }
        .stat-card-pink   { background:linear-gradient(135deg,#f50057,#c2003d); box-shadow:0 8px 24px rgba(245,0,87,0.35); }
        .stat-card-teal   { background:linear-gradient(135deg,#00b4d8,#0077b6); box-shadow:0 8px 24px rgba(0,180,216,0.35); }
        .stat-card-amber  { background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow:0 8px 24px rgba(245,158,11,0.35); }

        .stat-icon {
            width:46px; height:46px; border-radius:12px;
            background:rgba(255,255,255,0.15);
            display:flex; align-items:center; justify-content:center;
            font-size:18px; color:#fff; margin-bottom:14px;
        }
        .stat-label  { color:rgba(255,255,255,0.7); font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.8px; }
        .stat-value  { color:#fff; font-size:2rem; font-weight:800; line-height:1.1; margin:4px 0 8px; }
        .stat-footer { color:rgba(255,255,255,0.6); font-size:0.75rem; display:flex; align-items:center; gap:5px; }

        /* Referral Card */
        .referral-card {
            border:none; border-radius:16px; overflow:hidden;
            background:linear-gradient(135deg,#1a1a2e,#6c63ff);
            box-shadow:0 8px 30px rgba(108,99,255,0.35);
            margin-bottom:24px;
        }
        .referral-card-body {
            padding:20px 24px;
            display:flex; align-items:center;
            justify-content:space-between; flex-wrap:wrap; gap:16px;
        }
        .ref-label { font-size:0.72rem; font-weight:700; color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:1px; }
        .ref-code  { font-size:1.6rem; font-weight:900; color:#fff; letter-spacing:4px; margin-top:4px; }
        .ref-sub   { font-size:0.75rem; color:rgba(255,255,255,0.5); margin-top:4px; }
        .ref-btn {
            background:rgba(255,255,255,0.15); color:#fff;
            border:1px solid rgba(255,255,255,0.25);
            border-radius:8px; padding:8px 16px;
            font-size:0.82rem; font-weight:600;
            transition:all 0.2s; cursor:pointer;
            text-decoration:none; display:inline-flex;
            align-items:center; gap:6px;
        }
        .ref-btn:hover { background:rgba(255,255,255,0.25); color:#fff; }

        /* Table Cards */
        .table-card { border:none; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,0.07); overflow:hidden; margin-bottom:24px; }
        .table-card-header {
            background:linear-gradient(135deg,#1a1a2e,#16213e);
            padding:16px 22px; display:flex; align-items:center; gap:10px;
        }
        .table-card-icon {
            width:32px; height:32px; border-radius:8px;
            display:flex; align-items:center; justify-content:center;
            font-size:13px; color:#fff; flex-shrink:0;
        }
        .table-card-header h6 { color:#fff; margin:0; font-size:0.9rem; font-weight:700; }
        .table-card-header p  { color:rgba(255,255,255,0.4); margin:0; font-size:0.72rem; }

        /* Custom Table */
        .custom-table { width:100%; border-collapse:collapse; }
        .custom-table thead th {
            background:linear-gradient(135deg,#f8f7ff,#f0efff);
            color:#6c63ff; font-size:0.71rem; font-weight:700;
            text-transform:uppercase; letter-spacing:0.8px;
            padding:12px 16px; border-bottom:2px solid rgba(108,99,255,0.12);
            white-space:nowrap;
        }
        .custom-table tbody tr:hover { background:rgba(108,99,255,0.03); }
        .custom-table tbody td {
            padding:12px 16px; font-size:0.85rem; color:#374151;
            border-bottom:1px solid #f3f4f6; vertical-align:middle;
        }
        .custom-table tbody tr:last-child td { border-bottom:none; }

        .row-num {
            width:26px; height:26px; border-radius:7px;
            background:linear-gradient(135deg,#6c63ff,#574fd6);
            color:#fff; font-size:0.7rem; font-weight:700;
            display:inline-flex; align-items:center; justify-content:center;
        }

        /* Progress */
        .progress-wrap { min-width:100px; }
        .progress-custom { height:6px; border-radius:10px; background:#f0efff; overflow:hidden; margin-top:5px; }
        .progress-fill { height:100%; border-radius:10px; background:linear-gradient(90deg,#6c63ff,#f50057); transition:width 0.6s; }
        .progress-text { font-size:0.7rem; color:#6b7280; display:flex; justify-content:space-between; }

        /* Badges */
        .cat-badge {
            display:inline-flex; align-items:center; gap:4px;
            background:rgba(108,99,255,0.08); color:#6c63ff;
            border-radius:20px; padding:3px 10px;
            font-size:0.74rem; font-weight:600;
            border:1px solid rgba(108,99,255,0.15);
        }
        .contact-badge {
            display:inline-flex; align-items:center; gap:4px;
            background:rgba(25,135,84,0.07); color:#198754;
            border-radius:20px; padding:3px 10px; font-size:0.74rem;
        }
        .date-badge {
            display:inline-flex; align-items:center; gap:4px;
            background:#f8f9fa; color:#6b7280;
            border-radius:20px; padding:3px 10px;
            font-size:0.74rem; border:1px solid #e9ecef;
        }

        .empty-state { text-align:center; padding:32px 20px; color:#9ca3af; }
        .empty-state i { font-size:2.5rem; margin-bottom:10px; opacity:0.3; }
        .empty-state p { font-size:0.85rem; margin:0; }
    </style>
</head>
<body class="sb-nav-fixed">

<?php include_once('includes/navbar.php'); ?>

<div id="layoutSidenav">
    <?php include_once('includes/sidebar.php'); ?>

    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4 pb-4">

                <!-- Page Header -->
                <div class="page-header mt-4">
                    <div>
                        <h2><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h2>
                        <p>Welcome back, <strong style="color:#fff;"><?php echo e($user['fname'].' '.$user['lname']); ?></strong> 👋</p>
                    </div>
                    <nav>
                        <ol class="breadcrumb mb-0" style="background:none;">
                            <li class="breadcrumb-item active" style="color:rgba(255,255,255,0.7);">
                                <i class="fas fa-home me-1"></i> Dashboard
                            </li>
                        </ol>
                    </nav>
                </div>

                <!-- Stat Cards -->
                <div class="row">

                    <div class="col-xl-3 col-md-6">
                        <a href="profile.php" class="stat-card stat-card-purple">
                            <div class="stat-icon"><i class="fas fa-user"></i></div>
                            <div class="stat-label">Logged In As</div>
                            <div class="stat-value" style="font-size:1.1rem;margin-top:6px;">
                                <?php echo e($user['fname']); ?>
                            </div>
                            <div class="stat-footer"><i class="fas fa-arrow-right"></i> View Profile</div>
                        </a>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <a href="my-booklets.php" class="stat-card stat-card-pink">
                            <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                            <div class="stat-label">Assigned Booklets</div>
                            <div class="stat-value"><?php echo $booklet; ?></div>
                            <div class="stat-footer"><i class="fas fa-arrow-right"></i> View Details</div>
                        </a>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <a href="manage-runner.php" class="stat-card stat-card-teal">
                            <div class="stat-icon"><i class="fas fa-running"></i></div>
                            <div class="stat-label">Total Runners</div>
                            <div class="stat-value"><?php echo $runner; ?></div>
                            <div class="stat-footer"><i class="fas fa-arrow-right"></i> View All</div>
                        </a>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <a href="today-runners.php" class="stat-card stat-card-amber">
                            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                            <div class="stat-label">Today's Runners</div>
                            <div class="stat-value"><?php echo $tdrnr; ?></div>
                            <div class="stat-footer"><i class="fas fa-arrow-right"></i> View Today</div>
                        </a>
                    </div>

                </div>

                <!-- Referral Card -->
                <div class="referral-card">
                    <div class="referral-card-body">
                        <div>
                            <div class="ref-label">
                                <i class="fas fa-share-alt me-1"></i> Your Referral Code
                            </div>
                            <div class="ref-code" id="myRefCode">
                                <?php echo e($myRefCode); ?>
                            </div>
                            <div class="ref-sub">
                                <i class="fas fa-users me-1"></i>
                                <?php echo $refCount; ?> runner(s) registered using your code
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button onclick="copyRefCode()" id="copyRefBtn" class="ref-btn">
                                <i class="fas fa-copy"></i> Copy Code
                            </button>
                            <a href="open-runner-registration.php?ref=<?php echo e($myRefCode); ?>"
                               target="_blank" class="ref-btn">
                                <i class="fas fa-external-link-alt"></i> Share Link
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Tables Row -->
                <div class="row">

                    <!-- Booklet Status -->
                    <div class="col-xl-6">
                        <div class="table-card">
                            <div class="table-card-header">
                                <div class="table-card-icon"
                                     style="background:linear-gradient(135deg,#6c63ff,#574fd6);">
                                    <i class="fas fa-book-open"></i>
                                </div>
                                <div>
                                    <h6>Your Booklets Status</h6>
                                    <p>Forms used vs available per booklet</p>
                                </div>
                            </div>
                            <div style="background:#fff;">
                                <?php if ($bookletResult->num_rows > 0): ?>
                                <table class="custom-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Booklet</th>
                                            <th>Total</th>
                                            <th>Available</th>
                                            <th>Usage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php $bcnt = 1; while ($row1 = $bookletResult->fetch_assoc()):
                                        $total     = (int)$row1['b_range'];
                                        $used      = (int)$row1['used'];
                                        $available = $total - $used;
                                        $pct       = $total > 0 ? round(($used / $total) * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><span class="row-num"><?php echo $bcnt; ?></span></td>
                                        <td><strong style="color:#1a1a2e;"><?php echo e($row1['b_name']); ?></strong></td>
                                        <td><?php echo $total; ?></td>
                                        <td>
                                            <strong style="color:<?php echo $available > 0 ? '#198754' : '#dc3545'; ?>;">
                                                <?php echo $available; ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <div class="progress-wrap">
                                                <div class="progress-text">
                                                    <span><?php echo $used; ?> used</span>
                                                    <span><?php echo $pct; ?>%</span>
                                                </div>
                                                <div class="progress-custom">
                                                    <div class="progress-fill" style="width:<?php echo $pct; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $bcnt++; endwhile; ?>
                                    </tbody>
                                </table>
                                <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-book-open d-block"></i>
                                    <p>No booklets assigned yet.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Last 7 Days Runners -->
                    <div class="col-xl-6">
                        <div class="table-card">
                            <div class="table-card-header">
                                <div class="table-card-icon"
                                     style="background:linear-gradient(135deg,#00b4d8,#0077b6);">
                                    <i class="fas fa-running"></i>
                                </div>
                                <div>
                                    <h6>Last 7 Days Runners</h6>
                                    <p>Recently registered runners</p>
                                </div>
                            </div>
                            <div style="background:#fff;">
                                <?php if ($runner7Result->num_rows > 0): ?>
                                <table class="custom-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Runner Name</th>
                                            <th>Contact</th>
                                            <th>Category</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php $rcnt = 1; while ($row7 = $runner7Result->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="row-num"><?php echo $rcnt; ?></span></td>
                                        <td><strong style="color:#1a1a2e;"><?php echo e($row7['r_name']); ?></strong></td>
                                        <td>
                                            <span class="contact-badge">
                                                <i class="fas fa-phone"></i>
                                                <?php echo e($row7['r_contact']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="cat-badge">
                                                <i class="fas fa-flag"></i>
                                                <?php echo e($row7['r_catgry']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="date-badge">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo date('d M', strtotime($row7['reg_dt'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php $rcnt++; endwhile; ?>
                                    </tbody>
                                </table>
                                <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-running d-block"></i>
                                    <p>No runners in the last 7 days.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </main>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/scripts.js"></script>
<script>
function copyRefCode() {
    var code = document.getElementById('myRefCode')?.innerText?.trim() || '';
    navigator.clipboard.writeText(code).then(function () {
        var btn = document.getElementById('copyRefBtn');
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.style.background = 'rgba(34,197,94,0.3)';
        setTimeout(function () {
            btn.innerHTML = '<i class="fas fa-copy"></i> Copy Code';
            btn.style.background = 'rgba(255,255,255,0.15)';
        }, 2500);
    });
}
</script>
</body>
</html>
