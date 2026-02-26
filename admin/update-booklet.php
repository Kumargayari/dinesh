<?php
ob_start();
session_start();
include_once('../includes/config.php');

if (empty($_SESSION['adminid']) || !is_numeric($_SESSION['adminid'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$bid = isset($_GET['bid']) && is_numeric($_GET['bid']) ? (int)$_GET['bid'] : 0;

if ($bid <= 0) {
    $_SESSION['error'] = 'Invalid booklet ID.';
    header('Location: manage-booklet.php');
    exit();
}

// Fetch existing data
$stmt = $con->prepare("SELECT id, b_name, b_range, b_srnstart, b_srnend FROM booklet WHERE id = ?");
$stmt->bind_param("i", $bid);
$stmt->execute();
$booklet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booklet) {
    $_SESSION['error'] = 'Booklet not found.';
    header('Location: manage-booklet.php');
    exit();
}

// Handle Update
if (isset($_POST['update'])) {

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token.');
    }

    $b_name    = trim($_POST['bname']    ?? '');
    $b_range   = trim($_POST['brange']   ?? '');
    $bsrnstart = trim($_POST['srnstart'] ?? '');
    $srnend    = trim($_POST['srnend']   ?? '');

    if (empty($b_name) || empty($b_range) || empty($bsrnstart) || empty($srnend)) {
        $_SESSION['error'] = 'All fields are required.';
        header('Location: update-booklet.php?bid=' . $bid);
        exit();
    }

    if ((int)$bsrnstart >= (int)$srnend) {
        $_SESSION['error'] = 'Serial End must be greater than Serial Start.';
        header('Location: update-booklet.php?bid=' . $bid);
        exit();
    }

    $check = $con->prepare("SELECT id FROM booklet WHERE b_name = ? AND id != ? LIMIT 1");
    $check->bind_param("si", $b_name, $bid);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $_SESSION['error'] = 'Booklet name already exists.';
        $check->close();
        header('Location: update-booklet.php?bid=' . $bid);
        exit();
    }
    $check->close();

    $upd = $con->prepare("UPDATE booklet SET b_name=?, b_range=?, b_srnstart=?, b_srnend=? WHERE id=?");
    $upd->bind_param("ssssi", $b_name, $b_range, $bsrnstart, $srnend, $bid);

    if ($upd->execute()) {
        $upd->close();
        $_SESSION['success'] = 'Booklet updated successfully.';
        header('Location: manage-booklet.php');
        exit();
    }

    $_SESSION['error'] = 'Update failed. Please try again.';
    $upd->close();
    header('Location: update-booklet.php?bid=' . $bid);
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
    <title>Update Booklet</title>
    <link href="../css/styles.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
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
            font-size: 1.35rem;
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
        .btn-back {
            background: transparent;
            border: 1.5px solid rgba(255,255,255,0.3);
            color: rgba(255,255,255,0.8);
            padding: 8px 18px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-back:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .form-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            overflow: hidden;
            max-width: 780px;
            margin: 0 auto;
        }
        .form-card .card-header {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            border: none;
            padding: 18px 26px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 15px;
            flex-shrink: 0;
        }
        .form-card .card-header h5 {
            color: #fff;
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
        }
        .form-card .card-header p {
            color: rgba(255,255,255,0.45);
            margin: 0;
            font-size: 0.75rem;
        }
        .form-card .card-body {
            padding: 28px 30px;
            background: #fff;
        }
        .field-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #6b7280;
            margin-bottom: 7px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .field-label i { color: #6c63ff; font-size: 0.8rem; }
        .form-control-custom {
            border-radius: 11px !important;
            border: 1.5px solid #e5e7eb !important;
            padding: 11px 14px !important;
            font-size: 0.9rem !important;
            color: #374151 !important;
            transition: all 0.25s ease !important;
            background: #fafbff !important;
        }
        .form-control-custom:focus {
            border-color: #6c63ff !important;
            background: #fff !important;
            box-shadow: 0 0 0 3px rgba(108,99,255,0.15) !important;
            outline: none !important;
        }
        .form-control-custom::placeholder {
            color: #c1c9d2 !important;
            font-size: 0.85rem !important;
        }
        .field-hint {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .section-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 22px 0 20px;
        }
        .section-divider span {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6c63ff;
            white-space: nowrap;
        }
        .section-divider::before,
        .section-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(108,99,255,0.2);
        }
        .current-info {
            background: linear-gradient(135deg,
                rgba(108,99,255,0.05),
                rgba(245,158,11,0.05));
            border: 1px solid rgba(108,99,255,0.12);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .current-info-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 14px;
            flex-shrink: 0;
        }
        .current-info-text {
            font-size: 0.82rem;
            color: #6b7280;
            line-height: 1.6;
        }
        .current-info-text strong {
            color: #1a1a2e;
            font-size: 0.88rem;
        }
        .btn-update {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border: none;
            color: #fff;
            padding: 13px 28px;
            border-radius: 12px;
            font-size: 0.92rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 18px rgba(245,158,11,0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }
        .btn-update::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg,
                transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s ease;
        }
        .btn-update:hover::before { left: 100%; }
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(245,158,11,0.45);
        }
        .btn-update:active { transform: translateY(0); }
        .btn-cancel {
            background: #f3f4f6;
            border: none;
            color: #6b7280;
            padding: 13px 28px;
            border-radius: 12px;
            font-size: 0.92rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-cancel:hover {
            background: #e5e7eb;
            color: #374151;
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
                <?php if (isset($_SESSION['error'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: '<?php echo e($_SESSION['error']); ?>',
                            confirmButtonColor: '#6c63ff',
                            customClass: {
                                popup: 'rounded-4',
                                confirmButton: 'rounded-3'
                            }
                        });
                    });
                </script>
                <?php unset($_SESSION['error']); endif; ?>

                <!-- Page Header -->
                <div class="page-header mt-4">
                    <div>
                        <h2><i class="fas fa-edit me-2"></i> Update Booklet</h2>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">
                                        <i class="fas fa-home me-1"></i> Dashboard
                                    </a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="manage-booklet.php">Manage Booklet</a>
                                </li>
                                <li class="breadcrumb-item active">Update Booklet</li>
                            </ol>
                        </nav>
                    </div>
                    <a href="manage-booklet.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>

                <!-- Form Card -->
                <div class="form-card">
                    <div class="card-header">
                        <div class="header-icon">
                            <i class="fas fa-pen-to-square"></i>
                        </div>
                        <div>
                            <h5>Edit Booklet Details</h5>
                            <p>Update information for Booklet ID: #<?php echo $bid; ?></p>
                        </div>
                    </div>

                    <div class="card-body">

                        <!-- Info Banner -->
                        <div class="current-info">
                            <div class="current-info-icon">
                                <i class="fas fa-book-open"></i>
                            </div>
                            <div class="current-info-text">
                                <strong><?php echo e($booklet['b_name']); ?></strong>
                                &nbsp;&middot;&nbsp; Range:
                                <strong><?php echo e($booklet['b_range']); ?></strong>
                                &nbsp;&middot;&nbsp; Serial:
                                <strong><?php echo e($booklet['b_srnstart']); ?></strong>
                                &nbsp;→&nbsp;
                                <strong><?php echo e($booklet['b_srnend']); ?></strong>
                            </div>
                        </div>

                        <!-- ✅ KEY: hidden input sends 'update' to PHP -->
                        <form method="post" autocomplete="off" id="updateForm">
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo e($_SESSION['csrf_token']); ?>" />
                            <input type="hidden" name="update" value="1" />

                            <!-- Row 1 -->
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="field-label" for="bname">
                                        <i class="fas fa-book"></i> Booklet Name
                                    </label>
                                    <input class="form-control form-control-custom"
                                           id="bname"
                                           name="bname"
                                           type="text"
                                           value="<?php echo e($booklet['b_name']); ?>"
                                           maxlength="100"
                                           required />
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Must be unique across all booklets
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="field-label" for="brange">
                                        <i class="fas fa-layer-group"></i> Range
                                    </label>
                                    <input class="form-control form-control-custom"
                                           id="brange"
                                           name="brange"
                                           type="number"
                                           value="<?php echo e($booklet['b_range']); ?>"
                                           min="1"
                                           required />
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Number of entries in this booklet set
                                    </div>
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="section-divider">
                                <span>
                                    <i class="fas fa-hashtag me-1"></i> Serial Number Range
                                </span>
                            </div>

                            <!-- Row 2 -->
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="field-label" for="srnstart">
                                        <i class="fas fa-play-circle"></i> Serial Start
                                    </label>
                                    <input class="form-control form-control-custom"
                                           id="srnstart"
                                           name="srnstart"
                                           type="number"
                                           value="<?php echo e($booklet['b_srnstart']); ?>"
                                           min="1"
                                           required />
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Starting serial number
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="field-label" for="srnend">
                                        <i class="fas fa-stop-circle"></i> Serial End
                                    </label>
                                    <input class="form-control form-control-custom"
                                           id="srnend"
                                           name="srnend"
                                           type="number"
                                           value="<?php echo e($booklet['b_srnend']); ?>"
                                           min="2"
                                           required />
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Must be greater than Serial Start
                                    </div>
                                </div>
                            </div>

                            <!-- Buttons -->
                            <div class="row g-3 mt-3">
                                <div class="col-md-4">
                                    <a href="manage-booklet.php" class="btn-cancel">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                                <div class="col-md-8">
                                    <!-- ✅ type="button" — prevents default submit -->
                                    <button type="button"
                                            id="updateBtn"
                                            class="btn-update">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </main>

        <?php include('../includes/footer.php'); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="../js/scripts.js"></script>
<script>
document.getElementById('updateBtn').addEventListener('click', function () {

    const bname  = document.getElementById('bname').value.trim();
    const brange = document.getElementById('brange').value.trim();
    const start  = parseInt(document.getElementById('srnstart').value);
    const end    = parseInt(document.getElementById('srnend').value);

    // ── Validation ──────────────────────────────────────
    if (!bname || !brange || isNaN(start) || isNaN(end)) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Fields',
            text: 'Please fill in all fields.',
            confirmButtonColor: '#f59e0b',
            customClass: { popup: 'rounded-4', confirmButton: 'rounded-3' }
        });
        return;
    }

    if (end <= start) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Range',
            text: 'Serial End must be greater than Serial Start.',
            confirmButtonColor: '#f59e0b',
            customClass: { popup: 'rounded-4', confirmButton: 'rounded-3' }
        });
        return;
    }

    // ── Confirm ─────────────────────────────────────────
    Swal.fire({
        title: 'Save Changes?',
        text: 'Are you sure you want to update this booklet?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-save me-1"></i> Yes, Update',
        cancelButtonText: 'Cancel',
        customClass: {
            popup:         'rounded-4',
            confirmButton: 'rounded-3',
            cancelButton:  'rounded-3'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // ✅ type="button" means no event conflict
            // ✅ hidden input name="update" tells PHP what to do
            // ✅ Direct submit → PHP runs → header() redirect works
            document.getElementById('updateForm').submit();
        }
    });
});
</script>
</body>
</html>
