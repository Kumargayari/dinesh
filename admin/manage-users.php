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

// ── Safe Output ─────────────────────────────────────────
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// ── DELETE User ─────────────────────────────────────────
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    $stmt = $con->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success'] = 'User deleted successfully.';
    header('Location: manage-users.php');
    exit();
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Manage Users</title>
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
           ADD BUTTON
        ════════════════════════════ */
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
           CARD
        ════════════════════════════ */
        .users-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .users-card .card-header {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            border: none;
            padding: 16px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .users-card .header-title {
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .users-card .header-title i { color: #6c63ff; }
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
        #datatablesSimple { width: 100% !important; }

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

        /* User avatar */
        .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: linear-gradient(135deg, #6c63ff, #f50057);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }
        .user-name {
            font-weight: 600;
            color: #1a1a2e;
        }

        /* Badges */
        .email-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(13,110,253,0.07);
            color: #0d6efd;
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .contact-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(25,135,84,0.07);
            color: #198754;
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .date-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #f8f9fa;
            color: #6b7280;
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 0.75rem;
            border: 1px solid #e9ecef;
        }

        /* Booklet count badges */
        .booklet-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg,
                rgba(108,99,255,0.1),
                rgba(108,99,255,0.05));
            color: #6c63ff;
            border: 1px solid rgba(108,99,255,0.2);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .booklet-badge-none {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(156,163,175,0.1);
            color: #9ca3af;
            border: 1px solid rgba(156,163,175,0.2);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.78rem;
            font-weight: 600;
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

                <!-- SweetAlert Messages -->
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

                <!-- Page Header -->
                <div class="page-header mt-4">
                    <div>
                        <h2><i class="fas fa-users me-2"></i> Manage Users</h2>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">
                                        <i class="fas fa-home me-1"></i> Dashboard
                                    </a>
                                </li>
                                <li class="breadcrumb-item active">Manage Users</li>
                            </ol>
                        </nav>
                    </div>
                    <a href="create-admin.php" class="btn-add">
                        <i class="fas fa-user-plus"></i> Add User
                    </a>
                </div>

                <!-- Total Count -->
                <?php
                $countRes   = $con->query("SELECT COUNT(*) as c FROM users");
                $totalUsers = (int)$countRes->fetch_assoc()['c'];
                ?>

                <!-- Main Card -->
                <div class="card users-card mb-4">
                    <div class="card-header">
                        <div class="header-title">
                            <i class="fas fa-table"></i>
                            Registered Admin Users
                            <span class="record-count"><?php echo $totalUsers; ?> users</span>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <table id="datatablesSimple">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Reg. Date</th>
                                    <th>Booklets</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            // ── Fetch users WITH booklet count ──────────────
                            $ret = $con->prepare("
                                SELECT u.id, u.fname, u.lname, u.email,
                                       u.contactno, u.posting_date,
                                       COUNT(b.id) AS total_booklets
                                FROM users u
                                LEFT JOIN booklet b ON b.u_id = u.id
                                GROUP BY u.id
                                ORDER BY u.id ASC
                            ");
                            $ret->execute();
                            $result = $ret->get_result();
                            $cnt = 1;

                            while ($row = $result->fetch_assoc()):
                                $initial       = strtoupper(substr($row['fname'] ?? 'U', 0, 1));
                                $totalBooklets = (int)$row['total_booklets'];
                            ?>
                            <tr>
                                <!-- # -->
                                <td>
                                    <span class="row-num"><?php echo $cnt; ?></span>
                                </td>

                                <!-- User -->
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar"><?php echo $initial; ?></div>
                                        <div>
                                            <div class="user-name">
                                                <?php echo e($row['fname'] . ' ' . $row['lname']); ?>
                                            </div>
                                            <div style="font-size:0.73rem; color:#9ca3af;">
                                                ID: #<?php echo (int)$row['id']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Email -->
                                <td>
                                    <span class="email-badge">
                                        <i class="fas fa-envelope"></i>
                                        <?php echo e($row['email']); ?>
                                    </span>
                                </td>

                                <!-- Contact -->
                                <td>
                                    <span class="contact-badge">
                                        <i class="fas fa-phone"></i>
                                        <?php echo e($row['contactno']); ?>
                                    </span>
                                </td>

                                <!-- Reg Date -->
                                <td>
                                    <span class="date-badge">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo e($row['posting_date']); ?>
                                    </span>
                                </td>

                                <!-- Booklets Allocated -->
                                <td>
                                    <?php if ($totalBooklets > 0): ?>
                                        <span class="booklet-badge">
                                            <i class="fas fa-book-open" style="font-size:11px;"></i>
                                            <?php echo $totalBooklets; ?>
                                            booklet<?php echo ($totalBooklets > 1) ? 's' : ''; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="booklet-badge-none">
                                            <i class="fas fa-minus" style="font-size:10px;"></i>
                                            None
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td>
                                    <div class="action-wrap">
                                        <a href="user-profile.php?uid=<?php echo (int)$row['id']; ?>"
                                           class="btn-action btn-action-edit"
                                           title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button"
                                                class="btn-action btn-action-delete"
                                                title="Delete User"
                                                onclick="confirmDelete(
                                                    <?php echo (int)$row['id']; ?>,
                                                    '<?php echo e($row['fname'] . ' ' . $row['lname']); ?>',
                                                    <?php echo $totalBooklets; ?>
                                                )">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php $cnt++; endwhile; $ret->close(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>

        <?php include('../includes/footer.php'); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="../js/scripts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
<script src="../js/datatables-simple-demo.js"></script>
<script>
function confirmDelete(id, name, booklets) {

    // Extra warning if user has booklets assigned
    const bookletWarn = booklets > 0
        ? '<br><small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>This user has <strong>'
          + booklets + ' booklet(s)</strong> assigned!</small>'
        : '';

    Swal.fire({
        title: 'Delete User?',
        html: 'Are you sure you want to delete <strong>' + name + '</strong>?'
              + '<br><small class="text-muted">This cannot be undone.</small>'
              + bookletWarn,
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
            window.location.href = 'manage-users.php?id=' + id;
        }
    });
}
</script>
</body>
</html>
