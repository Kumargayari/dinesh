<?php
ob_start();
session_start();
include_once('../includes/config.php');

// ── Auth Check ──────────────────────────────────────────
if (empty($_SESSION['adminid']) || !is_numeric($_SESSION['adminid'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit();
}

// ── CSRF Token ──────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Safe Output Helper ───────────────────────────────────
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// ── DELETE ──────────────────────────────────────────────
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    $stmt = $con->prepare("DELETE FROM booklet WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success'] = 'Booklet deleted successfully.';
    header('Location: manage-booklet.php');
    exit();
}

// ── ALLOCATE ─────────────────────────────────────────────
if (isset($_POST['allocate'])) {
    if (empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token.');
    }
    $bid  = isset($_POST['bid'])  && is_numeric($_POST['bid'])  ? (int)$_POST['bid']  : 0;
    $u_id = isset($_POST['u_id']) && is_numeric($_POST['u_id']) ? (int)$_POST['u_id'] : 0;

    if ($bid > 0 && $u_id > 0) {
        $stmt = $con->prepare("UPDATE booklet SET u_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $u_id, $bid);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'Booklet allocated successfully.';
        header('Location: manage-booklet.php');
        exit();
    }
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Manage Booklet</title>
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
    <link href="../css/styles.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ════════════════════════════
           PAGE HEADER
        ════════════════════════════ */
        .page-header {
            background: linear-gradient(135deg, #6c63ff 0%, #574fd6 50%, #f50057 100%);
            border-radius: 16px;
            padding: 22px 28px;
            margin-bottom: 24px;
            box-shadow: 0 8px 24px rgba(108,99,255,0.3);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-header h2 {
            color: #fff;
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0;
        }

        .page-header .breadcrumb {
            margin: 4px 0 0;
            background: none;
            padding: 0;
        }

        .page-header .breadcrumb-item a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 0.8rem;
        }

        .page-header .breadcrumb-item.active {
            color: #fff;
            font-size: 0.8rem;
        }

        .page-header .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255,255,255,0.5);
        }

        /* ════════════════════════════
           CARD
        ════════════════════════════ */
        .booklet-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .booklet-card .card-header {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            border: none;
            padding: 16px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .booklet-card .header-title {
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .booklet-card .header-title i {
            color: #6c63ff;
        }

        .record-count {
            background: rgba(108,99,255,0.2);
            color: #a78bfa;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* ════════════════════════════
           TABLE
        ════════════════════════════ */
        #datatablesSimple {
            width: 100% !important;
        }

        #datatablesSimple thead tr {
            background: linear-gradient(135deg, #f8f7ff, #f0efff);
        }

        #datatablesSimple thead th {
            color: #6c63ff !important;
            font-size: 0.72rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.8px !important;
            padding: 14px 16px !important;
            border-bottom: 2px solid rgba(108,99,255,0.15) !important;
            white-space: nowrap;
        }

        #datatablesSimple tbody tr {
            transition: all 0.2s ease;
        }

        #datatablesSimple tbody tr:hover {
            background: rgba(108,99,255,0.04) !important;
        }

        #datatablesSimple tbody td {
            padding: 13px 16px !important;
            vertical-align: middle !important;
            font-size: 0.875rem;
            color: #374151;
            border-bottom: 1px solid #f1f3f9 !important;
        }

        /* Row number */
        .row-num {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: linear-gradient(135deg, #6c63ff, #574fd6);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Booklet name */
        .booklet-name {
            font-weight: 600;
            color: #1a1a2e;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .booklet-name i { color: #6c63ff; font-size: 0.85rem; }

        /* Badges */
        .badge-allocated {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(25,135,84,0.08);
            color: #198754;
            border: 1px solid rgba(25,135,84,0.2);
            border-radius: 20px;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .range-badge {
            background: rgba(13,202,240,0.1);
            color: #0097a7;
            border: 1px solid rgba(13,202,240,0.25);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .sr-num {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #495057;
            background: #f8f9fa;
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 0.8rem;
            border: 1px solid #e9ecef;
        }

        /* ✅ NEW: Available count badge */
        .available-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(16,185,129,0.1);
            color: #059669;
            border: 1px solid rgba(16,185,129,0.25);
            border-radius: 20px;
            padding: 4px 11px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .available-badge i {
            font-size: 0.7rem;
        }

        /* Action buttons */
        .action-wrap { display: flex; gap: 6px; align-items: center; }

        .btn-action {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-action-edit {
            background: rgba(13,110,253,0.1);
            color: #0d6efd;
        }
        .btn-action-edit:hover {
            background: #0d6efd;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(13,110,253,0.35);
        }

        .btn-action-delete {
            background: rgba(220,53,69,0.1);
            color: #dc3545;
        }
        .btn-action-delete:hover {
            background: #dc3545;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(220,53,69,0.35);
        }

        .btn-allocate {
            height: 30px;
            padding: 0 12px;
            border-radius: 8px;
            background: rgba(108,99,255,0.1);
            color: #6c63ff;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid rgba(108,99,255,0.2);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-allocate:hover {
            background: #6c63ff;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(108,99,255,0.35);
        }

        /* Add button */
        .btn-add {
            background: linear-gradient(135deg, #6c63ff, #574fd6);
            border: none;
            color: #fff;
            padding: 9px 20px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 12px rgba(108,99,255,0.35);
            text-decoration: none;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(108,99,255,0.45);
            color: #fff;
        }

        /* ════════════════════════════
           MODAL
        ════════════════════════════ */
        .modal-content {
            border: none;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            padding: 18px 22px;
            border: none;
        }

        .modal-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #6c63ff, #574fd6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 15px;
            flex-shrink: 0;
        }

        .modal-body-custom {
            padding: 24px 26px 20px;
        }

        .modal-footer-custom {
            padding: 0 26px 22px;
            border: none;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .form-label-custom {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #6b7280;
            margin-bottom: 6px;
            display: block;
        }

        .form-select-custom {
            border-radius: 10px !important;
            border: 1.5px solid #e5e7eb !important;
            font-size: 0.88rem !important;
            padding: 10px 14px !important;
            transition: all 0.2s ease !important;
        }

        .form-select-custom:focus {
            border-color: #6c63ff !important;
            box-shadow: 0 0 0 3px rgba(108,99,255,0.15) !important;
        }

        /* Modal backdrop blur */
        .modal-backdrop.show {
            opacity: 0.6;
        }

        /* ════════════════════════════
           DATATABLE OVERRIDES
        ════════════════════════════ */
        .dataTable-wrapper .dataTable-top {
            padding: 14px 20px !important;
            background: #fafbff;
            border-bottom: 1px solid #f0efff;
        }

        .dataTable-wrapper .dataTable-bottom {
            padding: 12px 20px !important;
            background: #fafbff;
            border-top: 1px solid #f0efff;
        }

        .dataTable-search input,
        .dataTable-selector {
            border-radius: 10px !important;
            border: 1.5px solid #e5e7eb !important;
            padding: 7px 12px !important;
            font-size: 0.84rem !important;
        }

        .dataTable-search input:focus {
            border-color: #6c63ff !important;
            box-shadow: 0 0 0 3px rgba(108,99,255,0.12) !important;
            outline: none !important;
        }

        .dataTable-pagination a {
            border-radius: 8px !important;
            font-size: 0.82rem !important;
        }

        .dataTable-pagination a.active {
            background: #6c63ff !important;
            color: #fff !important;
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

                <!-- ── SweetAlert Messages ── -->
                <?php if (isset($_SESSION['success'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: '<?php echo e($_SESSION['success']); ?>',
                            timer: 2000,
                            showConfirmButton: false,
                            customClass: { popup: 'rounded-4' }
                        });
                    });
                </script>
                <?php unset($_SESSION['success']); endif; ?>

                <!-- ── Page Header ── -->
                <div class="page-header mt-4">
                    <div>
                        <h2><i class="fas fa-book-open me-2"></i> Manage Booklet</h2>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">
                                        <i class="fas fa-home me-1"></i> Dashboard
                                    </a>
                                </li>
                                <li class="breadcrumb-item active">Manage Booklet</li>
                            </ol>
                        </nav>
                    </div>
                    <a href="create_booklet.php" class="btn-add">
                        <i class="fas fa-plus"></i> Add Booklet
                    </a>
                </div>

                <!-- ── Main Card ── -->
                <?php
                $countRes = $con->query("SELECT COUNT(*) as c FROM booklet");
                $totalCount = (int)$countRes->fetch_assoc()['c'];
                ?>
                <div class="card booklet-card mb-4">
                    <div class="card-header">
                        <div class="header-title">
                            <i class="fas fa-table"></i>
                            Booklet List
                            <span class="record-count"><?php echo $totalCount; ?> records</span>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <table id="datatablesSimple">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Booklet Name</th>
                                    <th>Assigned Admin</th>
                                    <th>Range</th>
                                    <th>Sr. Start</th>
                                    <th>Sr. End</th>
                                    <th>Available</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $ret = $con->prepare("
                                SELECT b.id, b.u_id, b.b_name,
                                       u.fname, u.lname,
                                       b.b_range, b.b_srnstart, b.b_srnend
                                FROM booklet b
                                LEFT JOIN users u ON b.u_id = u.id
                                ORDER BY b.id ASC
                            ");
                            $ret->execute();
                            $result = $ret->get_result();
                            $cnt = 1;

                            while ($row = $result->fetch_assoc()):
                                $modalId = 'modal_' . (int)$row['id'];
                                
                                // ✅ Calculate Available (bache hue booklets)
                                $bookletId = (int)$row['id'];
                                $usedStmt = $con->prepare("SELECT COUNT(*) as used_count FROM runner WHERE b_id = ?");
                                $usedStmt->bind_param("i", $bookletId);
                                $usedStmt->execute();
                                $usedCount = (int)$usedStmt->get_result()->fetch_assoc()['used_count'];
                                $usedStmt->close();
                                
                                $totalRange = (int)$row['b_range'];
                                $available = $totalRange - $usedCount;
                            ?>
                            <tr>
                                <!-- # -->
                                <td><span class="row-num"><?php echo $cnt; ?></span></td>

                                <!-- Name -->
                                <td>
                                    <div class="booklet-name">
                                        <i class="fas fa-book-open"></i>
                                        <?php echo e($row['b_name']); ?>
                                    </div>
                                </td>

                                <!-- Assigned Admin -->
                                <td>
                                    <?php if (empty($row['u_id'])): ?>
                                        <button type="button"
                                                class="btn-allocate"
                                                data-bs-toggle="modal"
                                                data-bs-target="#<?php echo $modalId; ?>">
                                            <i class="fas fa-user-plus"></i> Allocate
                                        </button>
                                    <?php else: ?>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <span class="badge-allocated">
                                                <i class="fas fa-user-check"></i>
                                                <?php echo e($row['fname'] . ' ' . $row['lname']); ?>
                                            </span>
                                            <button type="button"
                                                    class="border-0 bg-transparent p-0"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#<?php echo $modalId; ?>"
                                                    title="Change Admin"
                                                    style="color:#6c63ff;cursor:pointer;">
                                                <i class="fas fa-pen" style="font-size:11px;"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Range -->
                                <td><span class="range-badge"><?php echo e($row['b_range']); ?></span></td>

                                <!-- Sr Start -->
                                <td><span class="sr-num"><?php echo e($row['b_srnstart']); ?></span></td>

                                <!-- Sr End -->
                                <td><span class="sr-num"><?php echo e($row['b_srnend']); ?></span></td>

                                <!-- ✅ Available Count -->
                                <td>
                                    <span class="available-badge">
                                        <i class="fas fa-check-circle"></i>
                                        <?php echo $available; ?> / <?php echo $totalRange; ?>
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td>
                                    <div class="action-wrap">
                                        <a href="update-booklet.php?bid=<?php echo (int)$row['id']; ?>"
                                           class="btn-action btn-action-edit"
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button"
                                                class="btn-action btn-action-delete"
                                                title="Delete"
                                                onclick="confirmDelete(<?php echo (int)$row['id']; ?>, '<?php echo e($row['b_name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- ════════════════════════════
                                 BOOTSTRAP MODAL
                            ════════════════════════════ -->
                            <div class="modal fade"
                                 id="<?php echo $modalId; ?>"
                                 tabindex="-1"
                                 aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">

                                        <!-- Header -->
                                        <div class="modal-header-custom d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="modal-icon">
                                                    <i class="fas fa-user-cog"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 text-white fw-bold" style="font-size:0.95rem;">
                                                        <?php echo empty($row['u_id']) ? 'Allocate to Admin' : 'Change Admin'; ?>
                                                    </h6>
                                                    <small style="color:rgba(255,255,255,0.5);font-size:0.75rem;">
                                                        <?php echo e($row['b_name']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <button type="button"
                                                    class="btn-close btn-close-white"
                                                    data-bs-dismiss="modal"
                                                    aria-label="Close">
                                            </button>
                                        </div>

                                        <!-- Body + Form -->
                                        <form method="post" autocomplete="off">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?php echo e($_SESSION['csrf_token']); ?>" />
                                            <input type="hidden" name="bid"
                                                   value="<?php echo (int)$row['id']; ?>" />

                                            <div class="modal-body-custom">
                                                <label class="form-label-custom">Select Admin User</label>
                                                <?php
                                                $ls = $con->prepare("SELECT id, fname, lname FROM users ORDER BY fname ASC");
                                                $ls->execute();
                                                $lr = $ls->get_result();
                                                ?>
                                                <select name="u_id"
                                                        class="form-select form-select-custom"
                                                        required>
                                                    <option value="">-- Select Admin --</option>
                                                    <?php while ($u = $lr->fetch_assoc()): ?>
                                                        <option value="<?php echo (int)$u['id']; ?>"
                                                            <?php echo ((int)$row['u_id'] === (int)$u['id']) ? 'selected' : ''; ?>>
                                                            <?php echo e($u['fname'] . ' ' . $u['lname']); ?>
                                                        </option>
                                                    <?php endwhile; $ls->close(); ?>
                                                </select>
                                            </div>

                                            <div class="modal-footer-custom">
                                                <button type="button"
                                                        class="btn btn-outline-secondary btn-sm px-4"
                                                        style="border-radius:10px;"
                                                        data-bs-dismiss="modal">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                        name="allocate"
                                                        class="btn btn-sm px-4 text-white"
                                                        style="background:linear-gradient(135deg,#6c63ff,#574fd6);
                                                               border:none;border-radius:10px;
                                                               box-shadow:0 4px 12px rgba(108,99,255,0.3);">
                                                    <i class="fas fa-check me-1"></i>
                                                    <?php echo empty($row['u_id']) ? 'Allocate' : 'Update'; ?>
                                                </button>
                                            </div>
                                        </form>

                                    </div>
                                </div>
                            </div>
                            <!-- /Modal -->

                            <?php $cnt++; endwhile; $ret->close(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    <?php include_once('../includes/footer.php'); ?>
    </div>
	
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="../js/scripts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
<script src="../js/datatables-simple-demo.js"></script>

<script>
// ── SweetAlert2 Delete Confirm ──────────────────────────
function confirmDelete(id, name) {
    Swal.fire({
        title: 'Delete Booklet?',
        html: 'Are you sure you want to delete <strong>' + name + '</strong>?<br><small class="text-muted">This cannot be undone.</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash me-1"></i> Yes, Delete',
        cancelButtonText: 'Cancel',
        customClass: {
            popup:         'rounded-4',
            confirmButton: 'rounded-3',
            cancelButton:  'rounded-3'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'manage-booklet.php?id=' + id;
        }
    });
}
</script>

</body>
</html>
