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

// ── ✅ FIXED Delete Runner
if (isset($_GET['del']) && is_numeric($_GET['del'])) {
    $delid = (int)$_GET['del'];
    $nameQ = $con->prepare("SELECT r_name FROM runner WHERE r_id = ?");
    $nameQ->bind_param("i", $delid);
    $nameQ->execute();
    $nameRes = $nameQ->get_result()->fetch_assoc();
    $nameQ->close();

    if ($nameRes) {
        $del = $con->prepare("DELETE FROM runner WHERE r_id = ?");
        $del->bind_param("i", $delid);
        $del->execute();
        $del->close();
        $_SESSION['del_success'] = 'Runner "' . $nameRes['r_name'] . '" deleted successfully!';
    } else {
        $_SESSION['del_error'] = 'Runner not found.';
    }
    header('Location: all-runner.php'); exit();
}

// ── Filters
$search   = trim($_GET['search']    ?? '');
$gender   = trim($_GET['gender']    ?? '');
$category = trim($_GET['category']  ?? '');
$tshirt   = trim($_GET['tshirt']    ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to']   ?? '');

// ── Build WHERE
$where  = "WHERE 1=1";
$types  = '';
$params = [];

if ($search !== '') {
    $where .= " AND (r.r_name LIKE ? OR r.r_email LIKE ?
                     OR r.r_contact LIKE ? OR b.b_name LIKE ?)";
    $s = "%$search%";
    $types .= 'ssss';
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($gender   !== '') { $where .= " AND r.r_gender    = ?"; $types .= 's'; $params[] = $gender;   }
if ($category !== '') { $where .= " AND r.r_catgry    = ?"; $types .= 's'; $params[] = $category; }
if ($tshirt   !== '') { $where .= " AND r.r_tshirt_sz = ?"; $types .= 's'; $params[] = $tshirt;   }
if ($dateFrom !== '') { $where .= " AND DATE(r.reg_dt) >= ?"; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo   !== '') { $where .= " AND DATE(r.reg_dt) <= ?"; $types .= 's'; $params[] = $dateTo;   }

$baseJoin = "FROM runner r
             LEFT JOIN booklet b ON b.id = r.b_id
             LEFT JOIN users   u ON u.id = r.u_id
             $where";

// ── Total count
$cntStmt = $con->prepare("SELECT COUNT(*) $baseJoin");
if (!empty($params)) $cntStmt->bind_param($types, ...$params);
$cntStmt->execute();
$totalRows = (int)$cntStmt->get_result()->fetch_row()[0];
$cntStmt->close();

// ── Fetch ALL rows
$sql = "SELECT r.r_id, r.r_srn, r.r_name, r.r_email, r.r_contact,
               r.r_dob, r.r_gender, r.r_bdgp, r.r_catgry,
               r.r_tshirt_sz, r.r_emrg_con, r.r_med_dt,
               r.r_fee, r.reg_dt,
               b.b_name,
               u.fname, u.lname
        $baseJoin
        ORDER BY r.r_id DESC";

$stmt = $con->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// ── Distinct filter options
function getDistinct($con, $col, $table) {
    $res  = $con->query("SELECT DISTINCT $col FROM $table WHERE $col != '' AND $col IS NOT NULL ORDER BY $col");
    $data = [];
    while ($r = $res->fetch_row()) $data[] = $r[0];
    return $data;
}
$genderOpts   = getDistinct($con, 'r_gender',    'runner');
$categoryOpts = getDistinct($con, 'r_catgry',    'runner');
$tshirtOpts   = getDistinct($con, 'r_tshirt_sz', 'runner');

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Manage Runners — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet"/>
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet"/>
    <link href="../css/styles.css" rel="stylesheet" onerror="this.remove()"/>
    <style>
        body { background: #f4f6fb; }
        #layoutSidenav_content { display:flex; flex-direction:column; min-height:calc(100vh - 56px); }
        #layoutSidenav_content main { flex:1; }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg,#1a1a2e,#6c63ff);
            border-radius:16px; padding:20px 28px;
            display:flex; align-items:center;
            justify-content:space-between;
            margin-bottom:24px; flex-wrap:wrap; gap:10px;
        }
        .page-header h2 { color:#fff; font-weight:700; margin:0; font-size:1.3rem; }
        .page-header p  { color:rgba(255,255,255,.6); margin:4px 0 0; font-size:.82rem; }
        .header-badge {
            background:rgba(255,255,255,.15); color:#fff;
            border-radius:30px; padding:6px 18px;
            font-size:.82rem; font-weight:700;
            border:1px solid rgba(255,255,255,.2);
        }

        /* Filter Card */
        .filter-card { border:none; border-radius:14px; box-shadow:0 2px 16px rgba(0,0,0,.07); margin-bottom:20px; }
        .filter-card .card-header {
            background:linear-gradient(135deg,#1a1a2e,#16213e);
            color:#fff; border-radius:14px 14px 0 0;
            padding:12px 20px; font-weight:700; font-size:.88rem; border:none;
        }
        .filter-card .card-body { padding:16px 20px; background:#fafafa; border-radius:0 0 14px 14px; }
        .filter-card .form-label { font-size:.76rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }
        .filter-card .form-control,
        .filter-card .form-select { font-size:.84rem; border-radius:8px; border:1px solid #e5e7eb; }
        .filter-card .form-control:focus,
        .filter-card .form-select:focus { border-color:#6c63ff; box-shadow:0 0 0 3px rgba(108,99,255,.12); }

        .btn-filter { background:linear-gradient(135deg,#6c63ff,#574fd6); color:#fff; border:none; border-radius:8px; padding:8px 20px; font-size:.84rem; font-weight:600; }
        .btn-filter:hover { opacity:.88; color:#fff; }
        .btn-reset { background:#f3f4f6; color:#374151; border:1px solid #e5e7eb; border-radius:8px; padding:8px 16px; font-size:.84rem; font-weight:600; }
        .btn-reset:hover { background:#e5e7eb; }

        /* Table Card */
        .table-card { border:none; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.08); overflow:hidden; margin-bottom:30px; }
        .table-card .card-header {
            background:linear-gradient(135deg,#1a1a2e,#16213e);
            color:#fff; padding:16px 22px; border:none;
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;
        }
        .tbl-badge { background:#6c63ff; color:#fff; border-radius:20px; padding:3px 12px; font-size:.78rem; font-weight:700; }

        /* Table */
        .runner-table thead th {
            background:#f8f7ff; font-size:.73rem; font-weight:700;
            text-transform:uppercase; letter-spacing:.7px;
            border-bottom:2px solid #e8e4ff; padding:12px 10px; white-space:nowrap;
        }
        .runner-table tbody td { font-size:.83rem; padding:10px; vertical-align:middle; border-bottom:1px solid #f3f4f6; }
        .runner-table tbody tr:hover { background:#f8f7ff; }

        /* Badges */
        .bp { border-radius:8px; padding:3px 9px; font-size:.71rem; font-weight:700; display:inline-block; white-space:nowrap; }

        /* Action Buttons */
        .btn-view   { background:#ede9fe; color:#6c63ff; border:none; }
        .btn-view:hover   { background:#6c63ff; color:#fff; }
        .btn-delete { background:#fee2e2; color:#dc2626; border:none; }
        .btn-delete:hover { background:#dc2626; color:#fff; }
        .btn-act { border-radius:8px; padding:5px 10px; font-size:.75rem; font-weight:600; transition:all .2s; }

        /* Export buttons */
        .btn-excel-custom { background:linear-gradient(135deg,#166534,#15803d); color:#fff; border:none; border-radius:8px; font-size:.8rem; font-weight:600; }
        .btn-excel-custom:hover { opacity:.85; color:#fff; }
        .btn-csv-custom   { background:linear-gradient(135deg,#0e7490,#0369a1); color:#fff; border:none; border-radius:8px; font-size:.8rem; font-weight:600; }
        .btn-csv-custom:hover { opacity:.85; color:#fff; }
        .btn-pdf-custom   { background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; border:none; border-radius:8px; font-size:.8rem; font-weight:600; }
        .btn-pdf-custom:hover { opacity:.85; color:#fff; }

        /* DataTables */
        div.dataTables_wrapper div.dataTables_length select,
        div.dataTables_wrapper div.dataTables_filter input {
            border-radius:8px; border:1px solid #e5e7eb; padding:5px 10px; font-size:.82rem;
        }
        div.dataTables_wrapper div.dataTables_info { font-size:.8rem; color:#6b7280; }
        .dataTables_paginate .paginate_button { border-radius:8px !important; margin:0 2px !important; font-size:.82rem !important; }
        .dataTables_paginate .paginate_button.current,
        .dataTables_paginate .paginate_button.current:hover {
            background:#6c63ff !important; color:#fff !important; border-color:#6c63ff !important;
        }
        .dataTables_paginate .paginate_button:hover {
            background:#ede9fe !important; color:#6c63ff !important; border-color:#ede9fe !important;
        }

        /* Empty state */
        .empty-state { text-align:center; padding:60px 20px; color:#aaa; }
        .empty-state i { font-size:3rem; color:#ddd; margin-bottom:14px; display:block; }

        /* Modal */
        .modal-header-custom { background:linear-gradient(135deg,#1a1a2e,#16213e); color:#fff; border-radius:14px 14px 0 0; }
        .modal-content { border:none; border-radius:14px; }
        .info-row { display:flex; padding:9px 0; border-bottom:1px solid #f3f4f6; }
        .info-row:last-child { border-bottom:none; }
        .info-label { font-size:.75rem; font-weight:700; color:#9ca3af; text-transform:uppercase; width:140px; flex-shrink:0; }
        .info-value { font-size:.88rem; color:#1f2937; font-weight:500; }
    </style>
</head>

<body class="sb-nav-fixed">

<?php include_once('includes/navbar.php'); ?>

<div id="layoutSidenav">
    <?php include_once('includes/sidebar.php'); ?>

    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4">

                <h1 class="mt-4">Manage Runners</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Runners</li>
                </ol>

                <!-- ✅ Delete Success/Error Alerts -->
                <?php if (!empty($_SESSION['del_success'])): ?>
                <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon:'success', title:'Deleted!',
                        text:'<?php echo e($_SESSION['del_success']); ?>',
                        confirmButtonColor:'#6c63ff',
                        timer:2500, timerProgressBar:true
                    });
                });
                </script>
                <?php unset($_SESSION['del_success']); endif; ?>

                <?php if (!empty($_SESSION['del_error'])): ?>
                <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon:'error', title:'Error!',
                        text:'<?php echo e($_SESSION['del_error']); ?>',
                        confirmButtonColor:'#dc2626'
                    });
                });
                </script>
                <?php unset($_SESSION['del_error']); endif; ?>

                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h2><i class="fas fa-running me-2"></i>All Runners</h2>
                        <p>View, filter, export and manage all registered runners</p>
                    </div>
                    <div class="header-badge">
                        <i class="fas fa-users me-1"></i>
                        <?php echo $totalRows; ?> Total Runners
                    </div>
                </div>

                <!-- Filter Card -->
                <div class="card filter-card">
                    <div class="card-header">
                        <i class="fas fa-filter me-2"></i> Filter Runners
                    </div>
                    <div class="card-body">
                        <form method="GET" action="all-runner.php">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" class="form-control"
                                           placeholder="Name, email, contact, booklet..."
                                           value="<?php echo e($search); ?>"/>
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
                                    <label class="form-label">T-Shirt</label>
                                    <select name="tshirt" class="form-select">
                                        <option value="">All Sizes</option>
                                        <?php foreach ($tshirtOpts as $t): ?>
                                        <option value="<?php echo e($t); ?>" <?php echo ($tshirt===$t)?'selected':''; ?>>
                                            <?php echo e($t); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">From</label>
                                    <input type="date" name="date_from" class="form-control"
                                           value="<?php echo e($dateFrom); ?>"/>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">To</label>
                                    <input type="date" name="date_to" class="form-control"
                                           value="<?php echo e($dateTo); ?>"/>
                                </div>
                                <div class="col-md-1 d-flex gap-2">
                                    <button type="submit" class="btn btn-filter w-100">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <a href="all-runner.php" class="btn btn-reset w-100">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Table Card -->
                <div class="card table-card">
                    <div class="card-header">
                        <span><i class="fas fa-list me-2"></i>Runner Records</span>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button onclick="exportExcel()" class="btn btn-sm btn-excel-custom">
                                <i class="fas fa-file-excel me-1"></i>Excel
                            </button>
                            <button onclick="exportCSV()" class="btn btn-sm btn-csv-custom">
                                <i class="fas fa-file-csv me-1"></i>CSV
                            </button>
                            <button onclick="exportPDF()" class="btn btn-sm btn-pdf-custom">
                                <i class="fas fa-file-pdf me-1"></i>PDF
                            </button>
                            <span class="tbl-badge"><?php echo $totalRows; ?> Records</span>
                        </div>
                    </div>
                    <div class="card-body p-0">

                        <?php if ($totalRows > 0): $sno = 1; ?>
                        <div class="table-responsive p-3">
                            <table id="runnersTable" class="table runner-table mb-0" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Booklet</th>
                                        <th>S.No.</th>
                                        <th>Runner Name</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Gender</th>
                                        <th>Blood Grp</th>
                                        <th>Category</th>
                                        <th>T-Shirt</th>
                                        <th>Fee</th>
                                        <th>Reg. By</th>
                                        <th>Reg. Date</th>
                                        <th class="noExport">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php while ($row = $result->fetch_assoc()):
                                    $gStyle = ($row['r_gender'] === 'Male')
                                        ? 'background:#dbeafe;color:#1e40af;'
                                        : (($row['r_gender'] === 'Female')
                                            ? 'background:#fce7f3;color:#9d174d;'
                                            : 'background:#ede9fe;color:#5b21b6;');
                                ?>
                                <tr>
                                    <td><?php echo $sno++; ?></td>
                                    <td><strong><?php echo e($row['b_name'] ?? '—'); ?></strong></td>
                                    <td>
                                        <span class="bp" style="background:#ede9fe;color:#4c1d95;">
                                            <?php echo e($row['r_srn']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo e($row['r_name']); ?></strong></td>
                                    <td style="font-size:.78rem;color:#6b7280;"><?php echo e($row['r_email']); ?></td>
                                    <td><?php echo e($row['r_contact']); ?></td>
                                    <td>
                                        <span class="bp" style="<?php echo $gStyle; ?>">
                                            <?php echo e($row['r_gender']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="bp" style="background:#fff7ed;color:#c2410c;">
                                            <?php echo e($row['r_bdgp']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="bp" style="background:#dcfce7;color:#166534;">
                                            <?php echo e($row['r_catgry']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="bp" style="background:#fef9c3;color:#854d0e;">
                                            <?php echo e($row['r_tshirt_sz']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['r_fee'])): ?>
                                        <span class="bp" style="background:#dcfce7;color:#166534;">
                                            &#8377;<?php echo e($row['r_fee']); ?>
                                        </span>
                                        <?php else: ?><span style="color:#d1d5db;">—</span><?php endif; ?>
                                    </td>
                                    <td style="font-size:.78rem;">
                                        <?php echo e(trim(($row['fname']??'').' '.($row['lname']??''))); ?>
                                    </td>
                                    <td style="font-size:.78rem;color:#6b7280;white-space:nowrap;">
                                        <?php echo date('d M Y', strtotime($row['reg_dt'])); ?>
                                    </td>
                                    <td class="noExport">
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-act btn-view" title="View"
                                                onclick="viewRunner(
                                                    '<?php echo e($row['r_name']); ?>',
                                                    '<?php echo e($row['r_email']); ?>',
                                                    '<?php echo e($row['r_contact']); ?>',
                                                    '<?php echo e($row['r_dob']); ?>',
                                                    '<?php echo e($row['r_gender']); ?>',
                                                    '<?php echo e($row['r_bdgp']); ?>',
                                                    '<?php echo e($row['r_catgry']); ?>',
                                                    '<?php echo e($row['r_tshirt_sz']); ?>',
                                                    '<?php echo e($row['r_emrg_con']); ?>',
                                                    '<?php echo e($row['r_med_dt'] ?: 'None'); ?>',
                                                    '<?php echo e($row['r_fee'] ?: 'N/A'); ?>',
                                                    '<?php echo e($row['b_name'] ?? '—'); ?>',
                                                    '<?php echo e($row['r_srn']); ?>',
                                                    '<?php echo date('d M Y h:i A', strtotime($row['reg_dt'])); ?>'
                                                )">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-act btn-delete" title="Delete"
                                                onclick="confirmDelete(<?php echo (int)$row['r_id']; ?>, '<?php echo e($row['r_name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-running"></i>
                            <p>No runners found.</p>
                            <?php if ($search||$gender||$category||$tshirt||$dateFrom||$dateTo): ?>
                            <a href="all-runner.php" class="btn btn-sm btn-filter mt-2">
                                <i class="fas fa-times me-1"></i> Clear Filters
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>

            </div>
        </main>
        <?php include_once('../includes/footer.php'); ?>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title"><i class="fas fa-id-card me-2"></i>Runner Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3" id="modalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-reset" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/scripts.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
var table;

$(document).ready(function () {
    table = $('#runnersTable').DataTable({
        pageLength: 25,
        lengthMenu: [[10,25,50,100,-1],[10,25,50,100,'All']],
        order: [],
        columnDefs: [{ orderable:false, targets:[13] }],
        language: {
            search: '<i class="fas fa-search me-1"></i>',
            searchPlaceholder: 'Search in table...',
            lengthMenu:  'Show _MENU_ runners',
            info:        'Showing _START_–_END_ of _TOTAL_ runners',
            paginate: {
                first:    '<i class="fas fa-angle-double-left"></i>',
                last:     '<i class="fas fa-angle-double-right"></i>',
                next:     '<i class="fas fa-angle-right"></i>',
                previous: '<i class="fas fa-angle-left"></i>'
            }
        },
        dom: 'lBfrtip',
        buttons: [
            {
                extend:'excelHtml5', text:'Excel', className:'btn-excel d-none',
                title:'Runners List', exportOptions:{ columns:':not(.noExport)' }
            },
            {
                extend:'csvHtml5', text:'CSV', className:'btn-csv d-none',
                title:'Runners List', exportOptions:{ columns:':not(.noExport)' }
            },
            {
                extend:'pdfHtml5', text:'PDF', className:'btn-pdf d-none',
                title:'Runners List', orientation:'landscape', pageSize:'A4',
                exportOptions:{ columns:':not(.noExport)' },
                customize: function(doc) {
                    doc.styles.tableHeader.fillColor = '#6c63ff';
                    doc.defaultStyle.fontSize = 8;
                }
            }
        ]
    });
});

function exportExcel() { table.button('.buttons-excel').trigger(); }
function exportCSV()   { table.button('.buttons-csv').trigger();   }
function exportPDF()   { table.button('.buttons-pdf').trigger();   }

// ── View Runner Modal
function viewRunner(name,email,contact,dob,gender,blood,
                    category,tshirt,emrg,medical,fee,
                    booklet,srn,regDate) {
    var gColor = gender === 'Male'
        ? 'background:#dbeafe;color:#1e40af;'
        : (gender === 'Female'
            ? 'background:#fce7f3;color:#9d174d;'
            : 'background:#ede9fe;color:#5b21b6;');

    document.getElementById('modalBody').innerHTML = `
        <div class="row g-0">
            <div class="col-md-6">
                <div class="info-row"><div class="info-label">Full Name</div><div class="info-value"><strong>${name}</strong></div></div>
                <div class="info-row"><div class="info-label">Email</div><div class="info-value">${email}</div></div>
                <div class="info-row"><div class="info-label">Contact</div><div class="info-value">${contact}</div></div>
                <div class="info-row"><div class="info-label">Date of Birth</div><div class="info-value">${dob}</div></div>
                <div class="info-row">
                    <div class="info-label">Gender</div>
                    <div class="info-value">
                        <span style="${gColor} border-radius:7px;padding:2px 10px;font-size:.75rem;font-weight:700;">${gender}</span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Blood Group</div>
                    <div class="info-value">
                        <span style="background:#fff7ed;color:#c2410c;border-radius:7px;padding:2px 10px;font-size:.75rem;font-weight:700;">${blood}</span>
                    </div>
                </div>
                <div class="info-row"><div class="info-label">Emrg. Contact</div><div class="info-value">${emrg}</div></div>
            </div>
            <div class="col-md-6" style="padding-left:20px;border-left:1px solid #f3f4f6;">
                <div class="info-row"><div class="info-label">Booklet</div><div class="info-value"><strong>${booklet}</strong></div></div>
                <div class="info-row">
                    <div class="info-label">Form S.No.</div>
                    <div class="info-value">
                        <span style="background:#ede9fe;color:#4c1d95;border-radius:7px;padding:2px 10px;font-size:.75rem;font-weight:700;">${srn}</span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Category</div>
                    <div class="info-value">
                        <span style="background:#dcfce7;color:#166534;border-radius:7px;padding:2px 10px;font-size:.75rem;font-weight:700;">${category}</span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">T-Shirt Size</div>
                    <div class="info-value">
                        <span style="background:#fef9c3;color:#854d0e;border-radius:7px;padding:2px 10px;font-size:.75rem;font-weight:700;">${tshirt}</span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Fee</div>
                    <div class="info-value">
                        <span style="background:#dcfce7;color:#166534;border-radius:7px;padding:2px 10px;font-size:.75rem;font-weight:700;">₹${fee}</span>
                    </div>
                </div>
                <div class="info-row"><div class="info-label">Medical</div><div class="info-value">${medical}</div></div>
                <div class="info-row"><div class="info-label">Registered On</div><div class="info-value" style="color:#6b7280;font-size:.82rem;">${regDate}</div></div>
            </div>
        </div>`;
    new bootstrap.Modal(document.getElementById('viewModal')).show();
}

// ── ✅ FIXED Delete
function confirmDelete(id, name) {
    Swal.fire({
        title: 'Delete Runner?',
        html: 'Delete <strong>' + name + '</strong>?<br><small class="text-danger">This cannot be undone.</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor:  '#6b7280',
        confirmButtonText:  '<i class="fas fa-trash me-1"></i> Yes, Delete',
        cancelButtonText:   '<i class="fas fa-times me-1"></i> Cancel',
        background: '#1a1a2e',
        color: '#f0f0ff',
        backdrop: 'rgba(0,0,0,0.7)'
    }).then(function(r) {
        if (r.isConfirmed)
            // ✅ FIXED: same page + param 'del'
            window.location.href = 'all-runner.php?del=' + id;
    });
}
</script>

</body>
</html>
