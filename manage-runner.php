<?php
ob_start();
session_start();
include_once('includes/config.php');

// ── Auth ──────────────────────────────────────────
if (empty($_SESSION['id']) || !is_numeric($_SESSION['id'])) {
    session_unset(); session_destroy();
    header('Location: login.php'); exit();
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$userid = (int)$_SESSION['id'];

// ── Delete Runner ─────────────────────────────────
if (isset($_GET['del']) && is_numeric($_GET['del'])) {
    $delid = (int)$_GET['del'];
    $del = $con->prepare("DELETE FROM runner WHERE r_id = ? AND u_id = ?");
    $del->bind_param("ii", $delid, $userid);
    $del->execute();
    $del->close();
    header('Location: manage-runner.php?deleted=1'); exit();
}

// ── Fetch Runners ─────────────────────────────────
$rStmt = $con->prepare("
    SELECT r.*, b.b_name
    FROM runner r
    LEFT JOIN booklet b ON b.id = r.b_id
    WHERE r.u_id = ?
    ORDER BY r.reg_dt DESC
");
$rStmt->bind_param("i", $userid);
$rStmt->execute();
$runnerResult = $rStmt->get_result();
$rStmt->close();

// ── Fetch Booklets ────────────────────────────────
$bStmt = $con->prepare("
    SELECT b.id, b.b_name, b.b_range,
           COUNT(r.r_id) AS used
    FROM booklet b
    LEFT JOIN runner r ON r.b_id = b.id
    WHERE b.u_id = ?
    GROUP BY b.id
    ORDER BY b.id ASC
");
$bStmt->bind_param("i", $userid);
$bStmt->execute();
$bookletResult = $bStmt->get_result();
$bStmt->close();

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Manage Runner</title>
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet"/>
    <link href="css/styles.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <style>

    /* ════════ PAGE HEADER ════════ */
    .page-header {
        background: linear-gradient(135deg,#6c63ff,#574fd6 50%,#f50057);
        border-radius: 16px; padding: 22px 28px;
        margin-bottom: 26px;
        display: flex; align-items: center;
        justify-content: space-between;
        flex-wrap: wrap; gap: 12px;
        box-shadow: 0 8px 24px rgba(108,99,255,0.3);
    }
    .page-header h2 { color:#fff; font-size:1.35rem; font-weight:700; margin:0; }
    .page-header p  { color:rgba(255,255,255,0.55); font-size:0.8rem; margin:3px 0 0; }
    .breadcrumb { background:none; padding:0; margin:0; }
    .breadcrumb-item a { color:rgba(255,255,255,0.55); font-size:0.78rem; text-decoration:none; }
    .breadcrumb-item.active { color:#fff; font-size:0.78rem; }
    .breadcrumb-item+.breadcrumb-item::before { color:rgba(255,255,255,0.35); }

    /* ════════ SECTION CARD ════════ */
    .section-card {
        border:none; border-radius:16px;
        box-shadow:0 4px 24px rgba(0,0,0,0.07);
        overflow:hidden; margin-bottom:26px;
    }
    .section-card-header {
        background:linear-gradient(135deg,#1a1a2e,#16213e);
        padding:15px 22px;
        display:flex; align-items:center;
        justify-content:space-between;
        flex-wrap:wrap; gap:10px;
    }
    .section-card-header-left { display:flex; align-items:center; gap:10px; }
    .s-icon {
        width:34px; height:34px; border-radius:9px;
        display:flex; align-items:center;
        justify-content:center; font-size:14px;
        color:#fff; flex-shrink:0;
    }
    .section-card-header h6 { color:#fff; margin:0; font-size:0.92rem; font-weight:700; }
    .section-card-header p  { color:rgba(255,255,255,0.38); margin:0; font-size:0.71rem; }

    /* ════════ TABLE ════════ */
    .custom-table { width:100%; border-collapse:collapse; }
    .custom-table thead th {
        background:linear-gradient(135deg,#f8f7ff,#f0efff);
        color:#6c63ff; font-size:0.68rem; font-weight:700;
        text-transform:uppercase; letter-spacing:0.8px;
        padding:11px 14px;
        border-bottom:2px solid rgba(108,99,255,0.1);
        white-space:nowrap;
    }
    .custom-table tbody tr { transition:background 0.15s; }
    .custom-table tbody tr:hover { background:rgba(108,99,255,0.03); }
    .custom-table tbody td {
        padding:11px 14px; font-size:0.83rem;
        color:#374151; border-bottom:1px solid #f3f4f6;
        vertical-align:middle;
    }
    .custom-table tbody tr:last-child td { border-bottom:none; }
    .row-num {
        width:24px; height:24px; border-radius:7px;
        background:linear-gradient(135deg,#6c63ff,#574fd6);
        color:#fff; font-size:0.68rem; font-weight:700;
        display:inline-flex; align-items:center; justify-content:center;
    }

    /* ════════ BADGES ════════ */
    .badge-cat    { background:rgba(108,99,255,0.09); color:#6c63ff; border:1px solid rgba(108,99,255,0.18); border-radius:20px; padding:3px 9px; font-size:0.71rem; font-weight:600; }
    .badge-gender { background:rgba(0,180,216,0.09); color:#0077b6; border:1px solid rgba(0,180,216,0.2); border-radius:20px; padding:3px 9px; font-size:0.71rem; font-weight:600; }
    .badge-tshirt { background:rgba(245,158,11,0.1); color:#d97706; border:1px solid rgba(245,158,11,0.2); border-radius:20px; padding:3px 9px; font-size:0.71rem; font-weight:600; }
    .badge-avail  { font-size:0.8rem; font-weight:700; padding:3px 12px; border-radius:20px; }
    .badge-avail.ok   { background:#d1fae5; color:#065f46; }
    .badge-avail.low  { background:#fef3c7; color:#92400e; }
    .badge-avail.full { background:#fee2e2; color:#991b1b; }

    /* ✅ Payment Badges */
    .badge-pay {
        display:inline-flex; align-items:center; gap:5px;
        border-radius:20px; padding:3px 10px;
        font-size:0.71rem; font-weight:700;
        white-space:nowrap;
    }
    .bp-online        { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
    .bp-offline       { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
    .bp-complementary { background:#ede9fe; color:#5b21b6; border:1px solid #ddd6fe; }
    .bp-pending       { background:#f3f4f6; color:#6b7280; border:1px solid #e5e7eb; }

    /* ════════ PROGRESS ════════ */
    .prog { height:5px; border-radius:10px; background:#f0efff; overflow:hidden; margin-top:4px; }
    .prog-fill { height:100%; border-radius:10px; background:linear-gradient(90deg,#6c63ff,#f50057); }

    /* ════════ BUTTONS ════════ */
    .btn-add {
        background:linear-gradient(135deg,#6c63ff,#574fd6);
        color:#fff; border:none; border-radius:10px;
        padding:8px 18px; font-size:0.82rem; font-weight:600;
        display:inline-flex; align-items:center; gap:6px;
        cursor:pointer; transition:all 0.2s;
        box-shadow:0 4px 14px rgba(108,99,255,0.35);
        text-decoration:none;
    }
    .btn-add:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(108,99,255,0.45); color:#fff; }
    .btn-fill {
        background:linear-gradient(135deg,#6c63ff,#574fd6);
        color:#fff; border:none; border-radius:8px;
        padding:5px 14px; font-size:0.75rem; font-weight:600;
        cursor:pointer; transition:all 0.2s;
        display:inline-flex; align-items:center; gap:5px;
        box-shadow:0 3px 10px rgba(108,99,255,0.3);
        white-space:nowrap;
    }
    .btn-fill:hover { transform:translateY(-1px); color:#fff; opacity:0.9; }
    .btn-edit {
        background:rgba(25,135,84,0.09); color:#198754;
        border:1px solid rgba(25,135,84,0.2); border-radius:8px;
        padding:5px 11px; font-size:0.75rem; font-weight:600;
        text-decoration:none; display:inline-flex;
        align-items:center; gap:4px; transition:all 0.2s;
    }
    .btn-edit:hover { background:#198754; color:#fff; }
    .btn-del {
        background:rgba(220,53,69,0.09); color:#dc3545;
        border:1px solid rgba(220,53,69,0.2); border-radius:8px;
        padding:5px 11px; font-size:0.75rem; font-weight:600;
        text-decoration:none; display:inline-flex;
        align-items:center; gap:4px; transition:all 0.2s; cursor:pointer;
    }
    .btn-del:hover { background:#dc3545; color:#fff; }

    /* ════════ EMPTY STATE ════════ */
    .empty-state { text-align:center; padding:36px 20px; color:#9ca3af; }
    .empty-state i { font-size:2.8rem; opacity:0.25; display:block; margin-bottom:10px; }
    .empty-state p { font-size:0.85rem; margin:0; }

    /* ════════ BOOKLET POPUP (CSS :target) ════════ */
    .popup-overlay {
        visibility:hidden; opacity:0;
        transition:all 0.25s ease;
        position:fixed; z-index:1055;
        left:0; top:0; width:100%; height:100%;
        background:rgba(15,12,41,0.65);
        display:flex; align-items:center;
        justify-content:center;
        backdrop-filter:blur(4px);
    }
    .popup-overlay:target { visibility:visible; opacity:1; }
    .popup-box {
        background:#fff; border-radius:20px;
        width:90%; max-width:720px;
        max-height:88vh; overflow-y:auto;
        box-shadow:0 30px 80px rgba(0,0,0,0.3);
        animation:popIn 0.25s ease;
    }
    @keyframes popIn {
        from { transform:scale(0.92); opacity:0; }
        to   { transform:scale(1);    opacity:1; }
    }
    .popup-header {
        background:linear-gradient(135deg,#1a1a2e,#16213e);
        padding:16px 24px; border-radius:20px 20px 0 0;
        display:flex; align-items:center;
        justify-content:space-between;
        position:sticky; top:0; z-index:1;
    }
    .popup-header h5 { color:#fff; margin:0; font-size:0.98rem; font-weight:700; }
    .popup-close {
        color:rgba(255,255,255,0.5); font-size:24px;
        text-decoration:none; line-height:1; transition:color 0.2s;
    }
    .popup-close:hover { color:#fff; }
    .popup-body { padding:22px 24px; }
    .popup-table { width:100%; border-collapse:collapse; }
    .popup-table thead th {
        background:linear-gradient(135deg,#f8f7ff,#f0efff);
        color:#6c63ff; font-size:0.68rem; font-weight:700;
        text-transform:uppercase; letter-spacing:0.8px;
        padding:10px 14px;
        border-bottom:2px solid rgba(108,99,255,0.1);
    }
    .popup-table tbody td {
        padding:11px 14px; font-size:0.84rem;
        color:#374151; border-bottom:1px solid #f3f4f6;
        vertical-align:middle;
    }
    .popup-table tbody tr:last-child td { border-bottom:none; }
    .popup-table tbody tr:hover { background:rgba(108,99,255,0.03); }
    .popup-box::-webkit-scrollbar { width:4px; }
    .popup-box::-webkit-scrollbar-thumb { background:rgba(108,99,255,0.3); border-radius:10px; }

    /* ════════ PAYMENT POPUP (JS .show) ════════ */
    .pay-overlay {
        visibility:hidden; opacity:0;
        transition:visibility 0s 0.25s, opacity 0.25s ease;
        position:fixed; z-index:2000;
        left:0; top:0; width:100%; height:100%;
        background:rgba(15,12,41,0.72);
        display:flex; align-items:center;
        justify-content:center;
        backdrop-filter:blur(5px);
    }
    .pay-overlay.show {
        visibility:visible; opacity:1;
        transition:visibility 0s 0s, opacity 0.25s ease;
    }
    .pay-box {
        background:#fff; border-radius:20px;
        width:90%; max-width:400px;
        box-shadow:0 30px 80px rgba(0,0,0,0.35);
        animation:popIn 0.25s ease; overflow:hidden;
    }
    .pay-header {
        background:linear-gradient(135deg,#1a1a2e,#16213e);
        padding:15px 20px;
        display:flex; align-items:center; justify-content:space-between;
    }
    .pay-header h6 { color:#fff; margin:0; font-weight:700; font-size:0.95rem; }
    .pay-body { padding:20px; }

    .pay-option {
        display:flex; align-items:center; gap:13px;
        padding:12px 14px; border-radius:11px;
        margin-bottom:10px; cursor:pointer;
        border:2px solid #f0efff; transition:all 0.18s;
        user-select:none;
    }
    .pay-option:last-of-type { margin-bottom:0; }
    .pay-option:hover  { border-color:#6c63ff; background:#f8f7ff; }
    .pay-option.active {
        border-color:#6c63ff; background:#f8f7ff;
        box-shadow:0 0 0 3px rgba(108,99,255,0.12);
    }
    .pay-ico {
        width:40px; height:40px; border-radius:10px;
        display:flex; align-items:center; justify-content:center;
        font-size:16px; flex-shrink:0;
    }
    .pay-lbl  { font-size:0.87rem; font-weight:700; color:#1a1a2e; }
    .pay-desc { font-size:0.73rem; color:#9ca3af; }
    .pay-radio {
        margin-left:auto; width:17px; height:17px;
        border-radius:50%; border:2px solid #d1d5db;
        display:flex; align-items:center; justify-content:center;
        flex-shrink:0; transition:all 0.18s;
    }
    .pay-option.active .pay-radio { border-color:#6c63ff; background:#6c63ff; }
    .pay-option.active .pay-radio::after {
        content:''; width:6px; height:6px;
        border-radius:50%; background:#fff; display:block;
    }
    .btn-proceed {
        width:100%; margin-top:16px;
        background:linear-gradient(135deg,#6c63ff,#574fd6);
        color:#fff; border:none; border-radius:11px;
        padding:12px; font-size:0.9rem; font-weight:700;
        cursor:pointer; transition:all 0.2s;
        box-shadow:0 4px 14px rgba(108,99,255,0.3);
        display:flex; align-items:center; justify-content:center; gap:8px;
    }
    .btn-proceed:disabled { opacity:0.4; cursor:not-allowed; box-shadow:none; }
    .btn-proceed:not(:disabled):hover {
        transform:translateY(-2px);
        box-shadow:0 8px 20px rgba(108,99,255,0.4);
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
                        <h2><i class="fas fa-running me-2"></i>Manage Runners</h2>
                        <p>Register and manage all your runners</p>
                    </div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="welcome.php"><i class="fas fa-home me-1"></i>Dashboard</a>
                            </li>
                            <li class="breadcrumb-item active">Manage Runner</li>
                        </ol>
                    </nav>
                </div>

                <!-- ── Alerts ── -->
                <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-info alert-dismissible fade show rounded-3 border-0 shadow-sm mb-3">
                    <i class="fas fa-info-circle me-2"></i> Runner deleted successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- ════════ RUNNERS TABLE ════════ -->
                <div class="section-card">
                    <div class="section-card-header">
                        <div class="section-card-header-left">
                            <div class="s-icon" style="background:linear-gradient(135deg,#6c63ff,#574fd6);">
                                <i class="fas fa-running"></i>
                            </div>
                            <div>
                                <h6>Registered Runners</h6>
                                <p>All runners under your account</p>
                            </div>
                        </div>
                        <!-- ✅ Opens booklet popup -->
                        <a href="#popup1" class="btn-add">
                            <i class="fas fa-plus"></i> Register Runner
                        </a>
                    </div>

                    <div style="background:#fff; overflow-x:auto;">
                        <?php if ($runnerResult->num_rows > 0): ?>
                        <table class="custom-table" id="datatablesSimple">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Booklet</th>
                                    <th>Form S.No.</th>
                                    <th>Runner Name</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Gender</th>
                                    <th>Category</th>
                                    <th>T-Shirt</th>
                                    <th>Payment</th>
                                    <th>Emergency</th>
                                    <th>Medical</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php $cnt = 1; while ($row = $runnerResult->fetch_assoc()): ?>
                            <tr>
                                <td><span class="row-num"><?php echo $cnt; ?></span></td>

                                <td>
                                    <span style="color:#6c63ff;font-size:0.82rem;font-weight:600;">
                                        <?php echo e($row['b_name'] ?: '—'); ?>
                                    </span>
                                </td>

                                <td style="color:#9ca3af;font-size:0.8rem;">
                                    <?php echo e($row['r_srn'] ?? '—'); ?>
                                </td>

                                <td>
                                    <strong style="color:#1a1a2e;font-size:0.85rem;">
                                        <?php echo e($row['r_name']); ?>
                                    </strong>
                                </td>

                                <td style="font-size:0.8rem;color:#6b7280;">
                                    <?php echo e($row['r_email'] ?: '—'); ?>
                                </td>

                                <td style="font-size:0.82rem;color:#059669;font-weight:500;">
                                    <?php echo e($row['r_contact'] ?: '—'); ?>
                                </td>

                                <td>
                                    <span class="badge-gender">
                                        <?php echo e($row['r_gender'] ?: '—'); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge-cat">
                                        <?php echo e($row['r_catgry'] ?: '—'); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge-tshirt">
                                        <?php echo e($row['r_tshirt_sz'] ?: '—'); ?>
                                    </span>
                                </td>

                                <!-- ✅ Payment Badge -->
                                <td>
                                    <?php $pay = $row['r_payment_status'] ?? ''; ?>
                                    <?php if ($pay === 'online'): ?>
                                        <span class="badge-pay bp-online">
                                            <i class="fas fa-credit-card"></i> Online
                                        </span>
                                    <?php elseif ($pay === 'offline'): ?>
                                        <span class="badge-pay bp-offline">
                                            <i class="fas fa-money-bill-wave"></i> Offline
                                        </span>
                                    <?php elseif ($pay === 'complementary'): ?>
                                        <span class="badge-pay bp-complementary">
                                            <i class="fas fa-gift"></i> Complementary
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-pay bp-pending">
                                            <i class="fas fa-clock"></i> Pending
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td style="font-size:0.8rem;color:#6b7280;">
                                    <?php echo e($row['r_emrg_con'] ?: '—'); ?>
                                </td>

                                <td style="font-size:0.8rem;color:#6b7280;
                                           max-width:110px;overflow:hidden;
                                           text-overflow:ellipsis;white-space:nowrap;"
                                    title="<?php echo e($row['r_med_dt'] ?? ''); ?>">
                                    <?php echo e($row['r_med_dt'] ?: '—'); ?>
                                </td>

                                <td style="white-space:nowrap;">
                                    <a href="update-runner.php?rid=<?php echo (int)$row['r_id']; ?>"
                                       class="btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    &nbsp;
                                    <a href="#" class="btn-del"
                                       onclick="confirmDelete(<?php echo (int)$row['r_id']; ?>, event)">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php $cnt++; endwhile; ?>
                            </tbody>
                        </table>

                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-running"></i>
                            <p>No runners yet. Click <strong>Register Runner</strong> to add one.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<!-- ════════════════════════════════════
     BOOKLET SELECT POPUP (CSS :target)
════════════════════════════════════ -->
<div id="popup1" class="popup-overlay">
    <div class="popup-box">
        <div class="popup-header">
            <h5><i class="fas fa-book-open me-2"></i>Select Booklet for Runner Registration</h5>
            <a href="#" class="popup-close" title="Close">&times;</a>
        </div>
        <div class="popup-body">
            <?php if ($bookletResult->num_rows > 0): ?>
            <table class="popup-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Booklet Name</th>
                        <th>Total</th>
                        <th>Available</th>
                        <th>Usage</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $bcnt = 1;
                while ($row1 = $bookletResult->fetch_assoc()):
                    $total  = (int)$row1['b_range'];
                    $used   = (int)$row1['used'];
                    $avail  = $total - $used;
                    $pct    = $total > 0 ? round(($used / $total) * 100) : 0;
                    $isFull = $avail <= 0;
                    $isLow  = !$isFull && $avail <= 5;
                    $ac     = $isFull ? 'full' : ($isLow ? 'low' : 'ok');
                    $bid    = (int)$row1['id'];
                ?>
                <tr>
                    <td><span class="row-num"><?php echo $bcnt; ?></span></td>
                    <td>
                        <strong style="color:#1a1a2e;font-size:0.86rem;">
                            <?php echo e($row1['b_name']); ?>
                        </strong>
                    </td>
                    <td><?php echo $total; ?></td>
                    <td>
                        <span class="badge-avail <?php echo $ac; ?>">
                            <?php echo $avail; ?>
                        </span>
                    </td>
                    <td style="min-width:90px;">
                        <div style="font-size:0.68rem;color:#6b7280;
                             display:flex;justify-content:space-between;">
                            <span><?php echo $used; ?>/<?php echo $total; ?></span>
                            <span><?php echo $pct; ?>%</span>
                        </div>
                        <div class="prog">
                            <div class="prog-fill" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                    </td>
                    <td>
                        <?php if ($isFull): ?>
                            <span style="color:#9ca3af;font-size:0.78rem;">
                                <i class="fas fa-lock me-1"></i>Completed
                            </span>
                        <?php else: ?>
                            <!-- ✅ Opens payment popup instead of direct redirect -->
                            <button type="button" class="btn-fill"
                                    onclick="openPayPopup(<?php echo $bid; ?>)">
                                <i class="fas fa-running"></i> Fill Form
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php $bcnt++; endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <p>No booklets assigned yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════
     ✅ PAYMENT SELECT POPUP (JS .show)
════════════════════════════════════ -->
<div class="pay-overlay" id="payPopup">
    <div class="pay-box">

        <div class="pay-header">
            <div class="d-flex align-items-center gap-2">
                <h6 style="margin:0;">
                    <i class="fas fa-wallet me-2"></i>Select Payment Type
                </h6>
            </div>
            <button type="button" onclick="closePayPopup()"
                    style="background:none;border:none;color:rgba(255,255,255,0.5);
                           font-size:22px;cursor:pointer;line-height:1;padding:0;">
                &times;
            </button>
        </div>

        <div class="pay-body">
            <input type="hidden" id="sel_bid" value=""/>
            <input type="hidden" id="sel_pay" value=""/>

            <!-- Online -->
            <div class="pay-option" id="opt_online" onclick="selectPay('online', this)">
                <div class="pay-ico" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);">
                    <i class="fas fa-credit-card" style="color:#059669;"></i>
                </div>
                <div>
                    <div class="pay-lbl">Online Payment</div>
                    <div class="pay-desc">UPI, Card, Net Banking</div>
                </div>
                <div class="pay-radio"></div>
            </div>

            <!-- Offline -->
            <div class="pay-option" id="opt_offline" onclick="selectPay('offline', this)">
                <div class="pay-ico" style="background:linear-gradient(135deg,#fef3c7,#fde68a);">
                    <i class="fas fa-money-bill-wave" style="color:#d97706;"></i>
                </div>
                <div>
                    <div class="pay-lbl">Offline Payment</div>
                    <div class="pay-desc">Cash, Cheque, DD</div>
                </div>
                <div class="pay-radio"></div>
            </div>

            <!-- Complementary -->
            <div class="pay-option" id="opt_complementary" onclick="selectPay('complementary', this)">
                <div class="pay-ico" style="background:linear-gradient(135deg,#ede9fe,#ddd6fe);">
                    <i class="fas fa-gift" style="color:#7c3aed;"></i>
                </div>
                <div>
                    <div class="pay-lbl">Complementary</div>
                    <div class="pay-desc">Free / Sponsored / VIP</div>
                </div>
                <div class="pay-radio"></div>
            </div>

            <button class="btn-proceed" id="proceedBtn" onclick="proceedToForm()" disabled>
                <i class="fas fa-arrow-right"></i> Proceed to Registration
            </button>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/scripts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest"></script>
<script src="js/datatables-simple-demo.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// ── DataTable ──
if (document.getElementById('datatablesSimple')) {
    new simpleDatatables.DataTable('#datatablesSimple', {
        searchable: true, fixedHeight: false, perPage: 10
    });
}

// ── Delete Confirm ──
function confirmDelete(id, e) {
    e.preventDefault();
    Swal.fire({
        title: 'Delete Runner?',
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash me-1"></i> Yes, Delete',
        cancelButtonText: 'Cancel',
        customClass: { popup:'rounded-4', confirmButton:'rounded-3', cancelButton:'rounded-3' }
    }).then(function(r) {
        if (r.isConfirmed)
            window.location.href = 'manage-runner.php?del=' + id;
    });
}

// ── Auto dismiss alerts ──
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(a) {
        var inst = bootstrap.Alert.getOrCreateInstance(a);
        if (inst) inst.close();
    });
}, 4000);

// ════════════════════════
// ✅ PAYMENT POPUP LOGIC
// ════════════════════════
function openPayPopup(bid) {
    document.getElementById('sel_bid').value = bid;
    document.getElementById('sel_pay').value = '';
    // Reset options
    document.querySelectorAll('.pay-option').forEach(function(o) {
        o.classList.remove('active');
    });
    document.getElementById('proceedBtn').disabled = true;
    document.getElementById('payPopup').classList.add('show');
}

function closePayPopup() {
    document.getElementById('payPopup').classList.remove('show');
}

function selectPay(type, el) {
    document.querySelectorAll('.pay-option').forEach(function(o) {
        o.classList.remove('active');
    });
    el.classList.add('active');
    document.getElementById('sel_pay').value = type;
    document.getElementById('proceedBtn').disabled = false;
}

function proceedToForm() {
    var bid = document.getElementById('sel_bid').value;
    var pay = document.getElementById('sel_pay').value;
    if (!bid || !pay) return;
    // ✅ Redirect to add-runner.php with bid, uid and payment
    window.location.href = 'add-runner.php'
        + '?bid=' + encodeURIComponent(bid)
        + '&uid=<?php echo $userid; ?>'
        + '&payment=' + encodeURIComponent(pay);
}

// Close payment popup on outside click
document.getElementById('payPopup').addEventListener('click', function(e) {
    if (e.target === this) closePayPopup();
});

// Escape key closes both popups
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePayPopup();
        if (window.location.hash === '#popup1') {
            history.pushState('', document.title, window.location.pathname);
        }
    }
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>
