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

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ── Fetch Booklet Runners
$bookletQ = $con->query("SELECT * FROM runner WHERE reg_type = 'booklet' OR reg_type IS NULL ORDER BY r_id DESC");
$bookletRunners = $bookletQ ? $bookletQ->fetch_all(MYSQLI_ASSOC) : [];

// ── Fetch Public Runners
$publicQ = $con->query("SELECT * FROM runner WHERE reg_type = 'public' ORDER BY r_id DESC");
$publicRunners = $publicQ ? $publicQ->fetch_all(MYSQLI_ASSOC) : [];

$totalBooklet = count($bookletRunners);
$totalPublic  = count($publicRunners);
$totalAll     = $totalBooklet + $totalPublic;

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Runner Registration Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="../css/styles.css" rel="stylesheet" onerror="this.remove()"/>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f4f6fb; }
        #layoutSidenav_content { display:flex; flex-direction:column; min-height:calc(100vh - 56px); }
        #layoutSidenav_content main { flex:1; }

        /* ── Page Header ── */
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

        /* ── Stat Cards ── */
        .stat-card {
            border:none; border-radius:16px; padding:22px;
            position:relative; overflow:hidden;
            transition:transform 0.25s; margin-bottom:24px;
            display:block; text-decoration:none;
        }
        .stat-card:hover { transform:translateY(-4px); }
        .stat-card::before {
            content:''; position:absolute; top:-25px; right:-25px;
            width:90px; height:90px; border-radius:50%;
            background:rgba(255,255,255,0.08);
        }
        .sc-purple { background:linear-gradient(135deg,#6c63ff,#574fd6); box-shadow:0 8px 24px rgba(108,99,255,0.35); }
        .sc-amber  { background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow:0 8px 24px rgba(245,158,11,0.3);  }
        .sc-green  { background:linear-gradient(135deg,#22c55e,#15803d); box-shadow:0 8px 24px rgba(34,197,94,0.3);   }
        .stat-icon {
            width:44px; height:44px; border-radius:11px;
            background:rgba(255,255,255,0.15);
            display:flex; align-items:center; justify-content:center;
            font-size:18px; color:#fff; margin-bottom:14px;
        }
        .stat-label { color:rgba(255,255,255,0.65); font-size:0.73rem; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; }
        .stat-value { color:#fff; font-size:2.2rem; font-weight:900; line-height:1; margin:6px 0 4px; }
        .stat-footer{ color:rgba(255,255,255,0.55); font-size:0.73rem; }

        /* ── Tab Nav ── */
        .tab-nav { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
        .tab-btn {
            padding:10px 22px; border-radius:10px; border:none;
            font-size:.85rem; font-weight:700; cursor:pointer;
            transition:all .25s; display:flex; align-items:center; gap:8px;
        }
        .tab-btn.booklet-tab { background:#fff; color:#92400e; border:2px solid #f59e0b; }
        .tab-btn.booklet-tab.active {
            background:linear-gradient(135deg,#f59e0b,#d97706);
            color:#fff; border-color:transparent;
            box-shadow:0 4px 14px rgba(245,158,11,0.4);
        }
        .tab-btn.public-tab { background:#fff; color:#065f46; border:2px solid #10b981; }
        .tab-btn.public-tab.active {
            background:linear-gradient(135deg,#10b981,#059669);
            color:#fff; border-color:transparent;
            box-shadow:0 4px 14px rgba(16,185,129,0.4);
        }
        .tab-badge {
            background:rgba(255,255,255,0.25);
            border-radius:20px; padding:2px 9px; font-size:.72rem; font-weight:700;
        }
        .tab-btn:not(.active) .tab-badge { background:rgba(0,0,0,0.08); }

        /* ── Table Card ── */
        .tbl-card {
            background:#fff; border:none; border-radius:16px;
            box-shadow:0 4px 24px rgba(0,0,0,0.07); overflow:hidden;
        }
        .tbl-hdr {
            padding:16px 22px; display:flex;
            align-items:center; justify-content:space-between;
            flex-wrap:wrap; gap:12px;
        }
        .tbl-hdr.booklet { background:linear-gradient(135deg,#fffbeb,#fef3c7); border-bottom:2px solid #f59e0b; }
        .tbl-hdr.public  { background:linear-gradient(135deg,#ecfdf5,#d1fae5); border-bottom:2px solid #10b981; }
        .tbl-hdr h5 { margin:0; font-size:.95rem; font-weight:700; }
        .tbl-hdr.booklet h5 { color:#92400e; }
        .tbl-hdr.public  h5 { color:#065f46; }
        .tbl-hdr p { margin:0; font-size:.75rem; color:#6b7280; }

        /* Search */
        .search-box {
            display:flex; align-items:center; gap:8px;
            background:#fff; border:1.5px solid #e5e7eb;
            border-radius:9px; padding:7px 14px; min-width:220px;
        }
        .search-box input { border:none; outline:none; width:100%; font-size:.83rem; color:#374151; }
        .search-box i { color:#9ca3af; }

        /* Print Button */
        .btn-print {
            display:inline-flex; align-items:center; gap:6px;
            padding:7px 16px; border-radius:9px; border:none;
            font-size:.8rem; font-weight:600; cursor:pointer; transition:all .2s;
        }
        .btn-print.booklet { background:#f59e0b; color:#fff; }
        .btn-print.booklet:hover { background:#d97706; }
        .btn-print.public  { background:#10b981; color:#fff; }
        .btn-print.public:hover  { background:#059669; }

        /* Table */
        .report-table { width:100%; border-collapse:collapse; font-size:.82rem; }
        .report-table thead th {
            background:#f8fafc; color:#6b7280;
            font-size:.7rem; font-weight:700;
            text-transform:uppercase; letter-spacing:.5px;
            padding:12px 16px; border-bottom:1.5px solid #e5e7eb; white-space:nowrap;
        }
        .report-table tbody tr:hover { background:#f8fafc; }
        .report-table tbody td {
            padding:12px 16px; border-bottom:1px solid #f1f5f9;
            color:#374151; vertical-align:middle;
        }
        .report-table tbody tr:last-child td { border-bottom:none; }

        /* Avatar */
        .r-avatar {
            width:34px; height:34px; border-radius:10px;
            display:inline-flex; align-items:center; justify-content:center;
            font-size:.85rem; font-weight:700; color:#fff; flex-shrink:0;
        }
        .r-avatar.booklet { background:linear-gradient(135deg,#f59e0b,#d97706); }
        .r-avatar.public  { background:linear-gradient(135deg,#10b981,#059669); }
        .name-cell { display:flex; align-items:center; gap:10px; }
        .name-cell strong { display:block; font-weight:600; color:#1f2937; }
        .name-cell span   { font-size:.73rem; color:#9ca3af; }

        /* Badges */
        .badge-gender { padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; }
        .badge-m { background:#dbeafe; color:#1e40af; }
        .badge-f { background:#fce7f3; color:#9d174d; }
        .badge-o { background:#f3e8ff; color:#6b21a8; }
        .badge-size {
            background:#f1f5f9; color:#475569;
            padding:3px 10px; border-radius:6px;
            font-size:.72rem; font-weight:700;
        }
        .ref-code {
            font-family:monospace; font-size:.82rem;
            background:#f8fafc; border:1px solid #e5e7eb;
            padding:3px 8px; border-radius:6px;
            color:#6c63ff; font-weight:700; letter-spacing:1px;
        }

        /* Empty */
        .empty-st { text-align:center; padding:50px 20px; color:#9ca3af; }
        .empty-st i { font-size:2.5rem; opacity:.3; display:block; margin-bottom:10px; }
        .empty-st p { font-size:.84rem; margin:0; }

        /* Tab Panels */
        .tab-panel { display:none; }
        .tab-panel.active { display:block; }

        @media print {
            .no-print { display:none !important; }
            .tab-panel { display:block !important; }
            .tbl-card  { box-shadow:none; }
        }
    </style>
</head>
<body class="sb-nav-fixed">

<?php include_once('includes/navbar.php'); ?>

<div id="layoutSidenav">
    <?php include_once('includes/sidebar.php'); ?>

    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4 pb-4">

                <!-- ── Page Header ── -->
                <div class="page-header mt-4">
                    <div>
                        <h2><i class="fas fa-chart-bar me-2"></i>Runner Registration Report</h2>
                        <p><i class="fas fa-clock me-1"></i><?php echo date('D, d M Y h:i A'); ?> IST</p>
                    </div>
                    <div>
                        <a href="javascript:history.back()"
                           style="background:rgba(255,255,255,0.15);color:#fff;border-radius:8px;
                                  font-weight:600;border:1px solid rgba(255,255,255,0.2);
                                  padding:8px 16px; text-decoration:none; font-size:.84rem;
                                  display:inline-flex; align-items:center; gap:6px;">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <!-- ── Stat Cards ── -->
                <div class="row g-0">
                    <div class="col-xl-4 col-md-4 pe-3">
                        <div class="stat-card sc-purple">
                            <div class="stat-icon"><i class="fas fa-users"></i></div>
                            <div class="stat-label">Total Registrations</div>
                            <div class="stat-value"><?php echo $totalAll; ?></div>
                            <div class="stat-footer"><i class="fas fa-layer-group me-1"></i>Booklet + Public</div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-4 pe-3">
                        <div class="stat-card sc-amber">
                            <div class="stat-icon"><i class="fas fa-book"></i></div>
                            <div class="stat-label">Booklet Registrations</div>
                            <div class="stat-value"><?php echo $totalBooklet; ?></div>
                            <div class="stat-footer"><i class="fas fa-clipboard-list me-1"></i>Via Volunteers</div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-4">
                        <div class="stat-card sc-green">
                            <div class="stat-icon"><i class="fas fa-globe"></i></div>
                            <div class="stat-label">Public Registrations</div>
                            <div class="stat-value"><?php echo $totalPublic; ?></div>
                            <div class="stat-footer"><i class="fas fa-link me-1"></i>Online Form</div>
                        </div>
                    </div>
                </div>

                <!-- ── Tab Navigation ── -->
                <div class="tab-nav no-print">
                    <button class="tab-btn booklet-tab active" onclick="switchTab('booklet')">
                        <i class="fas fa-book"></i> Booklet Runners
                        <span class="tab-badge"><?php echo $totalBooklet; ?></span>
                    </button>
                    <button class="tab-btn public-tab" onclick="switchTab('public')">
                        <i class="fas fa-globe"></i> Public Form Runners
                        <span class="tab-badge"><?php echo $totalPublic; ?></span>
                    </button>
                </div>

                <!-- ══ BOOKLET TAB ══ -->
                <div class="tab-panel active" id="tab-booklet">
                    <div class="tbl-card">
                        <div class="tbl-hdr booklet">
                            <div>
                                <h5><i class="fas fa-book me-2"></i>Booklet Registered Runners</h5>
                                <p>Total: <?php echo $totalBooklet; ?> runners registered via booklet</p>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap no-print">
                                <div class="search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="searchBooklet"
                                           placeholder="Search name, email..."
                                           onkeyup="searchTable('booklet')"/>
                                </div>
                                <button class="btn-print booklet" onclick="printTab('booklet')">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                        <div style="overflow-x:auto;">
                            <?php if (empty($bookletRunners)): ?>
                            <div class="empty-st">
                                <i class="fas fa-book-open"></i>
                                <p>No booklet registrations found.</p>
                            </div>
                            <?php else: ?>
                            <table class="report-table" id="bookletTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Runner</th>
                                        <th>Contact</th>
                                        <th>Gender</th>
                                        <th>DOB</th>
                                        <th>Blood</th>
                                        <th>T-Shirt</th>
                                        <th>Emergency</th>
                                        <th>Medical</th>
                                        <th>Ref Code</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($bookletRunners as $i => $r): ?>
                                    <tr>
                                        <td><?php echo $i+1; ?></td>
                                        <td>
                                            <div class="name-cell">
                                                <div class="r-avatar booklet">
                                                    <?php echo strtoupper(substr(e($r['r_name']),0,1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo e($r['r_name']); ?></strong>
                                                    <span><?php echo e($r['r_email']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo e($r['r_contact']); ?></td>
                                        <td>
                                            <?php $g=$r['r_gender']; $cls=$g==='Male'?'badge-m':($g==='Female'?'badge-f':'badge-o'); ?>
                                            <span class="badge-gender <?php echo $cls; ?>"><?php echo e($g); ?></span>
                                        </td>
                                        <td><?php echo e($r['r_dob']); ?></td>
                                        <td><strong><?php echo e($r['r_bdgp']); ?></strong></td>
                                        <td><span class="badge-size"><?php echo e($r['r_tshirt_sz']); ?></span></td>
                                        <td><?php echo e($r['r_emrg_con']); ?></td>
                                        <td><?php echo e($r['r_med_dt'] ?: 'None'); ?></td>
                                        <td><span class="ref-code"><?php echo e($r['referral_code']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ══ PUBLIC TAB ══ -->
                <div class="tab-panel" id="tab-public">
                    <div class="tbl-card">
                        <div class="tbl-hdr public">
                            <div>
                                <h5><i class="fas fa-globe me-2"></i>Public Form Registered Runners</h5>
                                <p>Total: <?php echo $totalPublic; ?> runners registered via public form</p>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap no-print">
                                <div class="search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="searchPublic"
                                           placeholder="Search name, email..."
                                           onkeyup="searchTable('public')"/>
                                </div>
                                <button class="btn-print public" onclick="printTab('public')">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                        <div style="overflow-x:auto;">
                            <?php if (empty($publicRunners)): ?>
                            <div class="empty-st">
                                <i class="fas fa-globe"></i>
                                <p>No public form registrations found.</p>
                            </div>
                            <?php else: ?>
                            <table class="report-table" id="publicTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Runner</th>
                                        <th>Contact</th>
                                        <th>Gender</th>
                                        <th>DOB</th>
                                        <th>Blood</th>
                                        <th>T-Shirt</th>
                                        <th>Emergency</th>
                                        <th>Medical</th>
                                        <th>Ref Code</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($publicRunners as $i => $r): ?>
                                    <tr>
                                        <td><?php echo $i+1; ?></td>
                                        <td>
                                            <div class="name-cell">
                                                <div class="r-avatar public">
                                                    <?php echo strtoupper(substr(e($r['r_name']),0,1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo e($r['r_name']); ?></strong>
                                                    <span><?php echo e($r['r_email']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo e($r['r_contact']); ?></td>
                                        <td>
                                            <?php $g=$r['r_gender']; $cls=$g==='Male'?'badge-m':($g==='Female'?'badge-f':'badge-o'); ?>
                                            <span class="badge-gender <?php echo $cls; ?>"><?php echo e($g); ?></span>
                                        </td>
                                        <td><?php echo e($r['r_dob']); ?></td>
                                        <td><strong><?php echo e($r['r_bdgp']); ?></strong></td>
                                        <td><span class="badge-size"><?php echo e($r['r_tshirt_sz']); ?></span></td>
                                        <td><?php echo e($r['r_emrg_con']); ?></td>
                                        <td><?php echo e($r['r_med_dt'] ?: 'None'); ?></td>
                                        <td><span class="ref-code"><?php echo e($r['referral_code']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div><!-- /container-fluid -->
        </main>
        <?php include_once('../includes/footer.php'); ?>
    </div><!-- /layoutSidenav_content -->
</div><!-- /layoutSidenav -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/scripts.js"></script>
<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    document.querySelector('.' + tab + '-tab').classList.add('active');
}

function searchTable(type) {
    const input  = document.getElementById('search' + type.charAt(0).toUpperCase() + type.slice(1));
    const filter = input.value.toLowerCase();
    const rows   = document.getElementById(type + 'Table').getElementsByTagName('tr');
    for (let i = 1; i < rows.length; i++) {
        rows[i].style.display = rows[i].textContent.toLowerCase().includes(filter) ? '' : 'none';
    }
}

function printTab(type) {
    const content = document.getElementById('tab-' + type).innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`
        <html><head>
        <title>${type === 'booklet' ? 'Booklet' : 'Public'} Runner Report</title>
        <style>
            body { font-family:Arial,sans-serif; padding:20px; }
            table { width:100%; border-collapse:collapse; font-size:12px; }
            th { background:#f8fafc; padding:8px 10px; border:1px solid #e5e7eb; font-size:10px; text-transform:uppercase; }
            td { padding:8px 10px; border:1px solid #f1f5f9; }
            .no-print, button, .search-box { display:none !important; }
        </style>
        </head><body>${content}</body></html>
    `);
    w.document.close();
    w.print();
}
</script>
</body>
</html>
