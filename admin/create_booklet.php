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

// ── Handle Form Submit ───────────────────────────────────
if (isset($_POST['submit'])) {

    // CSRF Check
    if (empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token. Please refresh and try again.');
    }

    $bname    = trim($_POST['bname']    ?? '');
    $brange   = trim($_POST['brange']   ?? '');
    $bsrnstart = trim($_POST['srnstart'] ?? '');
    $srnend   = trim($_POST['srnend']   ?? '');

    // Validate
    if (empty($bname) || empty($brange) || empty($bsrnstart) || empty($srnend)) {
        $_SESSION['error'] = 'All fields are required.';
        header('Location: create_booklet.php');
        exit();
    }

    if ((int)$bsrnstart >= (int)$srnend) {
        $_SESSION['error'] = 'Serial End must be greater than Serial Start.';
        header('Location: create_booklet.php');
        exit();
    }

    // Check duplicate
    $check = $con->prepare("SELECT id FROM booklet WHERE b_name = ? LIMIT 1");
    $check->bind_param("s", $bname);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $_SESSION['error'] = 'Booklet already exists. Please try a different name.';
        $check->close();
        header('Location: create_booklet.php');
        exit();
    }
    $check->close();

    // Insert
    $stmt = $con->prepare("INSERT INTO booklet (u_id, b_name, b_range, b_srnstart, b_srnend)
                           VALUES (NULL, ?, ?, ?, ?)");
    $stmt->bind_param("ssss", $bname, $brange, $bsrnstart, $srnend);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Booklet created successfully.';
        $stmt->close();
        header('Location: manage-booklet.php');
        exit();
    } else {
        $_SESSION['error'] = 'Something went wrong. Please try again.';
    }
    $stmt->close();
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Create Booklet</title>
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

        /* ════════════════════════════
           FORM CARD
        ════════════════════════════ */
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

        .form-card .card-header .header-icon {
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

        /* ════════════════════════════
           FORM FIELDS
        ════════════════════════════ */
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

        .field-label i {
            color: #6c63ff;
            font-size: 0.8rem;
        }

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

        /* ════════════════════════════
           SECTION DIVIDER
        ════════════════════════════ */
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
            background: linear-gradient(to right, transparent, rgba(108,99,255,0.2));
        }

        .section-divider::after {
            background: linear-gradient(to left, transparent, rgba(108,99,255,0.2));
        }

        /* ════════════════════════════
           SUBMIT BUTTON
        ════════════════════════════ */
        .btn-submit {
            background: linear-gradient(135deg, #6c63ff, #574fd6);
            border: none;
            color: #fff;
            padding: 13px 28px;
            border-radius: 12px;
            font-size: 0.92rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 18px rgba(108,99,255,0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent);
            transition: left 0.5s ease;
        }

        .btn-submit:hover::before { left: 100%; }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(108,99,255,0.45);
        }

        .btn-submit:active { transform: translateY(0); }

        /* Back button */
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
            border-color: rgba(255,255,255,0.5);
        }

        /* ════════════════════════════
           INPUT HINTS
        ════════════════════════════ */
        .field-hint {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ════════════════════════════
           ALERT
        ════════════════════════════ */
        .alert-custom {
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            border: none;
        }

        .alert-danger-custom {
            background: rgba(220,53,69,0.08);
            border: 1px solid rgba(220,53,69,0.2);
            color: #dc3545;
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
                <?php if (isset($_SESSION['error'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops!',
                            text: '<?php echo e($_SESSION['error']); ?>',
                            confirmButtonColor: '#6c63ff',
                            customClass: { popup: 'rounded-4', confirmButton: 'rounded-3' }
                        });
                    });
                </script>
                <?php unset($_SESSION['error']); endif; ?>

                <!-- ── Page Header ── -->
                <div class="page-header mt-4">
                    <div>
                        <h2><i class="fas fa-plus-circle me-2"></i> Create Booklet</h2>
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
                                <li class="breadcrumb-item active">Create Booklet</li>
                            </ol>
                        </nav>
                    </div>
                    <a href="manage-booklet.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>

                <!-- ── Form Card ── -->
                <div class="form-card">

                    <!-- Card Header -->
                    <div class="card-header">
                        <div class="header-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div>
                            <h5>New Booklet Details</h5>
                            <p>Fill in the details below to create a new booklet</p>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body">
                        <form method="post" name="createbooklet" autocomplete="off">

                            <!-- CSRF -->
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo e($_SESSION['csrf_token']); ?>" />

                            <!-- ── Row 1: Name + Range ── -->
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="field-label" for="bname">
                                        <i class="fas fa-book"></i> Booklet Name
                                    </label>
                                    <input class="form-control form-control-custom"
                                           id="bname"
                                           name="bname"
                                           type="text"
                                           placeholder="e.g. Booklet A - Set 1"
                                           maxlength="100"
                                           required />
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Enter a unique name for this booklet
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
                                           placeholder="e.g. 100"
                                           min="1"
                                           required />
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Number of entries in this booklet set
                                    </div>
                                </div>
                            </div>

                            <!-- ── Divider ── -->
                            <div class="section-divider">
                                <span><i class="fas fa-hashtag me-1"></i> Serial Number Range</span>
                            </div>

                            <!-- ── Row 2: Serial Start + End ── -->
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="field-label" for="srnstart">
                                        <i class="fas fa-play-circle"></i> Serial Start
                                    </label>
                                    <input class="form-control form-control-custom"
                                           id="srnstart"
                                           name="srnstart"
                                           type="number"
                                           placeholder="e.g. 1001"
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
                                           placeholder="e.g. 1100"
                                           min="2"
                                           required />
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Must be greater than Serial Start
                                    </div>
                                </div>
                            </div>

                            <!-- ── Submit ── -->
                            <div class="mt-4">
                                <button type="submit" class="btn-submit" name="submit">
                                    <i class="fas fa-plus-circle"></i>
                                    Create Booklet
                                </button>
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
// ── Client-side validation: srnend > srnstart ──
document.querySelector('form[name="createbooklet"]').addEventListener('submit', function(e) {
    const start = parseInt(document.getElementById('srnstart').value);
    const end   = parseInt(document.getElementById('srnend').value);

    if (end <= start) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Range',
            text: 'Serial End must be greater than Serial Start.',
            confirmButtonColor: '#6c63ff',
            customClass: { popup: 'rounded-4', confirmButton: 'rounded-3' }
        });
    }
});
</script>
</body>
</html>
