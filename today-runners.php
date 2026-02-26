<?php
session_start();
include_once 'includes/config.php';

if (empty($_SESSION['id']) || !is_numeric($_SESSION['id'])) {
    header('Location: logout.php');
    exit();
}

$userid = (int)$_SESSION['id'];
$today  = date('Y-m-d');

// Total Count
$cntStmt = $con->prepare("
    SELECT COUNT(*) AS total FROM runner
    WHERE u_id = ? AND DATE(reg_dt) = ?
");
$cntStmt->bind_param("is", $userid, $today);
$cntStmt->execute();
$totalToday = (int)$cntStmt->get_result()->fetch_assoc()['total'];
$cntStmt->close();

// Gender Count
$gStmt = $con->prepare("
    SELECT r_gender, COUNT(*) AS cnt FROM runner
    WHERE u_id = ? AND DATE(reg_dt) = ?
    GROUP BY r_gender
");
$gStmt->bind_param("is", $userid, $today);
$gStmt->execute();
$gResult = $gStmt->get_result();
$gCounts = [];
while ($gr = $gResult->fetch_assoc()) {
    $gCounts[$gr['r_gender']] = $gr['cnt'];
}
$gStmt->close();

// Category Count
$catStmt = $con->prepare("
    SELECT r_catgry, COUNT(*) AS cnt FROM runner
    WHERE u_id = ? AND DATE(reg_dt) = ?
    GROUP BY r_catgry
");
$catStmt->bind_param("is", $userid, $today);
$catStmt->execute();
$catResult = $catStmt->get_result();
$catCounts = [];
while ($crow = $catResult->fetch_assoc()) {
    $catCounts[$crow['r_catgry']] = $crow['cnt'];
}
$catStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Today's Runners</title>
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet"/>
    <link href="css/styles.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js"
            crossorigin="anonymous"></script>
    <style>
        /* Page Header */
        .today-header {
            background: linear-gradient(135deg, #1a1a2e, #6c63ff);
            border-radius: 16px;
            padding: 20px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .today-header h2 {
            color: #fff; font-weight: 700;
            margin: 0; font-size: 1.4rem;
        }
        .date-pill {
            background: rgba(255,255,255,0.15);
            color: #fff; border-radius: 30px;
            padding: 6px 16px; font-size: 0.82rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.2);
        }

        /* Stat Cards */
        .stat-card {
            border-radius: 14px; padding: 18px 22px;
            color: #fff; display: flex;
            align-items: center; gap: 14px;
            height: 100%;
        }
        .stat-icon {
            width: 52px; height: 52px;
            border-radius: 13px;
            background: rgba(255,255,255,0.18);
            display: flex; align-items: center;
            justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
        }
        .stat-number { font-size: 2rem; font-weight: 800; line-height: 1; }
        .stat-label  { font-size: 0.78rem; opacity: 0.8; margin-top: 3px; }
        .bg-purple  { background: linear-gradient(135deg, #6c63ff, #574fd6); }
        .bg-crimson { background: linear-gradient(135deg, #f50057, #c51162); }
        .bg-teal    { background: linear-gradient(135deg, #00bfa5, #00897b); }

        /* Table Card */
        .runner-card {
            border: none; border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .runner-card .card-header {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #fff; padding: 16px 22px;
            font-weight: 700; border: none;
            display: flex; align-items: center;
            justify-content: space-between;
        }
        .badge-count {
            background: #6c63ff; color: #fff;
            border-radius: 20px; padding: 3px 12px;
            font-size: 0.78rem; font-weight: 700;
        }

        /* Table */
        #todayTable thead th {
            background: #f8f7ff;
            font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 2px solid #e8e4ff;
            padding: 12px 10px; white-space: nowrap;
        }
        #todayTable tbody td {
            font-size: 0.84rem; padding: 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }
        #todayTable tbody tr:hover { background: #f8f7ff; }

        /* Inline Badges */
        .bp {
            border-radius: 8px; padding: 3px 10px;
            font-size: 0.72rem; font-weight: 700;
            display: inline-block; white-space: nowrap;
        }

        /* Empty State */
        .empty-state {
            text-align: center; padding: 60px 20px; color: #aaa;
        }
        .empty-state i {
            font-size: 3.5rem; color: #ddd;
            margin-bottom: 16px; display: block;
        }
        .empty-state p { font-size: 1rem; font-weight: 500; }
    </style>
</head>
<body class="sb-nav-fixed">

<?php include_once 'includes/navbar.php'; ?>

<div id="layoutSidenav">
    <?php include_once 'includes/sidebar.php'; ?>

    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4 py-4">

                <!-- Breadcrumb -->
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item">
                        <a href="welcome.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">Today's Runners</li>
                </ol>

                <!-- Page Header -->
                <div class="today-header">
                    <div>
                        <h2><i class="fas fa-running me-2"></i>Today's Runners</h2>
                        <div style="color:rgba(255,255,255,0.6);font-size:0.8rem;margin-top:4px;">
                            All runners registered today
                        </div>
                    </div>
                    <div class="date-pill">
                        <i class="fas fa-calendar-day me-1"></i>
                        <?php echo date('d M Y'); ?>
                    </div>
                </div>

                <!-- Stat Cards -->
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="stat-card bg-purple">
                            <div class="stat-icon"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $totalToday; ?></div>
                                <div class="stat-label">Total Runners Today</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card bg-crimson">
                            <div class="stat-icon"><i class="fas fa-mars"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $gCounts['Male'] ?? 0; ?></div>
                                <div class="stat-label">Male Runners</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card bg-teal">
                            <div class="stat-icon"><i class="fas fa-venus"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $gCounts['Female'] ?? 0; ?></div>
                                <div class="stat-label">Female Runners</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category Summary Pills -->
                <?php if (!empty($catCounts)): ?>
                <div class="mb-3 d-flex flex-wrap gap-2">
                    <?php foreach ($catCounts as $cat => $cnt): ?>
                    <span class="bp"
                          style="background:#e8f5e9;color:#2e7d32;
                                 font-size:0.82rem;padding:7px 16px;">
                        <i class="fas fa-flag-checkered me-1"></i>
                        <?php echo htmlspecialchars($cat); ?>:
                        <strong><?php echo $cnt; ?></strong>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Runner Table -->
                <div class="card runner-card">
                    <div class="card-header">
                        <span>
                            <i class="fas fa-list me-2"></i>Runner List &mdash;
                            <span style="color:rgba(255,255,255,0.55);font-size:0.8rem;">
                                <?php echo date('d M Y'); ?>
                            </span>
                        </span>
                        <span class="badge-count"><?php echo $totalToday; ?> Runners</span>
                    </div>
                    <div class="card-body p-0">

                        <?php if ($totalToday > 0):
                            $stmt = $con->prepare("
                                SELECT r.r_id,        r.r_srn,       r.r_name,
                                       r.r_email,     r.r_contact,   r.r_dob,
                                       r.r_gender,    r.r_bdgp,      r.r_catgry,
                                       r.r_tshirt_sz, r.r_emrg_con,  r.r_med_dt,
                                       r.r_fee,       r.reg_dt,
                                       b.b_name,      b.b_srnstart,  b.b_srnend
                                FROM runner r
                                LEFT JOIN booklet b ON b.id = r.b_id
                                WHERE r.u_id = ?
                                  AND DATE(r.reg_dt) = ?
                                ORDER BY r.r_id DESC
                            ");
                            $stmt->bind_param("is", $userid, $today);
                            $stmt->execute();
                            $rows = $stmt->get_result();
                            $stmt->close();
                            $sno = 1;
                        ?>

                        <div class="table-responsive">
                            <table id="todayTable" class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Booklet</th>
                                        <th>S.No.</th>
                                        <th>Runner Name</th>
                                        <th>Contact</th>
                                        <th>DOB</th>
                                        <th>Gender</th>
                                        <th>Blood Grp</th>
                                        <th>Email</th>
                                        <th>Category</th>
                                        <th>T-Shirt</th>
                                        <th>Emrg. Contact</th>
                                        <th>Medical</th>
                                      
                                    </tr>
                                </thead>
                                <tbody>
                                <?php while ($row = $rows->fetch_assoc()):
                                    $gStyle = ($row['r_gender'] === 'Male')
                                        ? 'background:#e3f2fd;color:#1565c0;'
                                        : 'background:#fce4ec;color:#880e4f;';
                                ?>
                                <tr>
                                    <td><?php echo $sno++; ?></td>

                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars($row['b_name'] ?? '—'); ?>
                                        </strong>
                                        <?php if (!empty($row['b_srnstart']) && !empty($row['b_srnend'])): ?>
                                        <small style="color:#999;display:block;font-size:0.7rem;">
                                            <?php echo htmlspecialchars($row['b_srnstart'])
                                                  . ' – ' .
                                                  htmlspecialchars($row['b_srnend']); ?>
                                        </small>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="bp" style="background:#ede7f6;color:#4527a0;">
                                            <?php echo htmlspecialchars($row['r_srn']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <strong><?php echo htmlspecialchars($row['r_name']); ?></strong>
                                    </td>

                                    <td><?php echo htmlspecialchars($row['r_contact']); ?></td>

                                    <td style="font-size:0.8rem;color:#666;">
                                        <?php echo htmlspecialchars($row['r_dob']); ?>
                                    </td>

                                    <td>
                                        <span class="bp" style="<?php echo $gStyle; ?>">
                                            <?php echo htmlspecialchars($row['r_gender']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="bp" style="background:#fff3e0;color:#bf360c;">
                                            <?php echo htmlspecialchars($row['r_bdgp']); ?>
                                        </span>
                                    </td>

                                    <td style="font-size:0.78rem;">
                                        <?php echo htmlspecialchars($row['r_email']); ?>
                                    </td>

                                    <td>
                                        <span class="bp" style="background:#e8f5e9;color:#2e7d32;">
                                            <?php echo htmlspecialchars($row['r_catgry']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="bp" style="background:#fff8e1;color:#f57f17;">
                                            <?php echo htmlspecialchars($row['r_tshirt_sz']); ?>
                                        </span>
                                    </td>

                                    <td><?php echo htmlspecialchars($row['r_emrg_con']); ?></td>

                                    <td style="font-size:0.8rem;">
                                        <?php echo htmlspecialchars($row['r_med_dt'] ?: '—'); ?>
                                    </td>

                                  
                                </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-running"></i>
                            <p>No runners registered today.</p>
                            <a href="manage-runner.php" class="btn btn-primary btn-sm mt-2">
                                <i class="fas fa-plus me-1"></i> Register Runner
                            </a>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>

            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
<script src="js/scripts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest"
        crossorigin="anonymous"></script>
<script>
    const tableEl = document.getElementById('todayTable');
    if (tableEl) {
        new simpleDatatables.DataTable(tableEl, {
            searchable: true,
            fixedHeight: false,
            perPage: 25,
            labels: {
                placeholder: "Search runners...",
                perPage:     "{select} per page",
                noRows:      "No runners found",
                info:        "Showing {start} to {end} of {rows} entries"
            }
        });
    }
</script>
</body>
</html>
