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

// ── Get User ID ─────────────────────────────────────────
$uid = isset($_GET['uid']) && is_numeric($_GET['uid']) ? (int)$_GET['uid'] : 0;

if ($uid <= 0) {
    $_SESSION['error'] = 'Invalid user ID.';
    header('Location: manage-users.php');
    exit();
}

// ── Fetch User ──────────────────────────────────────────
$stmt = $con->prepare("SELECT id, fname, lname, email, contactno, posting_date FROM users WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['error'] = 'User not found.';
    header('Location: manage-users.php');
    exit();
}

// ── Handle Update ───────────────────────────────────────
if (isset($_POST['update'])) {

    if (empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token.');
    }

    $fname     = trim($_POST['fname']     ?? '');
    $lname     = trim($_POST['lname']     ?? '');
    $email     = trim($_POST['email']     ?? '');
    $contactno = trim($_POST['contactno'] ?? '');
    $password  = trim($_POST['password']  ?? '');

    // Validate
    if (empty($fname) || empty($lname) || empty($email) || empty($contactno)) {
        $_SESSION['error'] = 'All fields except password are required.';
        header('Location: user-profile.php?uid=' . $uid);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email address.';
        header('Location: user-profile.php?uid=' . $uid);
        exit();
    }

    if (!preg_match('/^[0-9]{10,15}$/', $contactno)) {
        $_SESSION['error'] = 'Contact number must be 10-15 digits.';
        header('Location: user-profile.php?uid=' . $uid);
        exit();
    }

    // Check duplicate email (exclude current)
    $check = $con->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $check->bind_param("si", $email, $uid);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $_SESSION['error'] = 'Email already in use by another user.';
        $check->close();
        header('Location: user-profile.php?uid=' . $uid);
        exit();
    }
    $check->close();

    // Update with or without password
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $_SESSION['error'] = 'Password must be at least 6 characters.';
            header('Location: user-profile.php?uid=' . $uid);
            exit();
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $upd = $con->prepare("UPDATE users SET fname=?, lname=?, email=?, contactno=?, password=? WHERE id=?");
        $upd->bind_param("sssssi", $fname, $lname, $email, $contactno, $hashed, $uid);
    } else {
        $upd = $con->prepare("UPDATE users SET fname=?, lname=?, email=?, contactno=? WHERE id=?");
        $upd->bind_param("ssssi", $fname, $lname, $email, $contactno, $uid);
    }

    if ($upd->execute()) {
        $upd->close();
        $_SESSION['success'] = 'User updated successfully.';
        header('Location: manage-users.php');
        exit();
    }

    $_SESSION['error'] = 'Update failed. Please try again.';
    $upd->close();
    header('Location: user-profile.php?uid=' . $uid);
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
    <title>Edit User</title>
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

        /* ════════════════════════════
           LAYOUT
        ════════════════════════════ */
        .profile-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 24px;
            max-width: 1000px;
            margin: 0 auto;
        }
        @media (max-width: 768px) {
            .profile-layout { grid-template-columns: 1fr; }
        }

        /* ════════════════════════════
           PROFILE CARD (LEFT)
        ════════════════════════════ */
        .profile-sidebar {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            overflow: hidden;
            height: fit-content;
        }
        .profile-sidebar-header {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            padding: 28px 20px;
            text-align: center;
        }
        .profile-avatar-wrap {
            position: relative;
            display: inline-block;
            margin-bottom: 14px;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: linear-gradient(135deg, #6c63ff, #f50057);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            color: #fff;
            box-shadow: 0 8px 24px rgba(108,99,255,0.45);
            margin: 0 auto;
        }
        .profile-online {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #00e5a0;
            border: 3px solid #1a1a2e;
            box-shadow: 0 0 8px rgba(0,229,160,0.6);
        }
        .profile-name {
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            margin: 0;
        }
        .profile-id {
            color: rgba(255,255,255,0.4);
            font-size: 0.75rem;
            margin-top: 3px;
        }
        .profile-sidebar-body {
            background: #fff;
            padding: 20px;
        }
        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .info-row:last-child { border-bottom: none; }
        .info-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
        }
        .info-icon-purple {
            background: rgba(108,99,255,0.1);
            color: #6c63ff;
        }
        .info-icon-blue {
            background: rgba(13,110,253,0.1);
            color: #0d6efd;
        }
        .info-icon-green {
            background: rgba(25,135,84,0.1);
            color: #198754;
        }
        .info-icon-amber {
            background: rgba(245,158,11,0.1);
            color: #f59e0b;
        }
        .info-label {
            font-size: 0.7rem;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
        }
        .info-value {
            font-size: 0.83rem;
            color: #374151;
            font-weight: 500;
            word-break: break-all;
        }

        /* ════════════════════════════
           FORM CARD (RIGHT)
        ════════════════════════════ */
        .form-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            overflow: hidden;
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
            padding: 26px 28px;
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
            margin: 20px 0 18px;
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

        /* Password toggle */
        .input-password-wrap {
            position: relative;
        }
        .input-password-wrap .form-control-custom {
            padding-right: 44px !important;
        }
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 14px;
            padding: 0;
            transition: color 0.2s;
        }
        .toggle-password:hover { color: #6c63ff; }

        /* Buttons */
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
                            customClass: { popup: 'rounded-4', confirmButton: 'rounded-3' }
                        });
                    });
                </script>
                <?php unset($_SESSION['error']); endif; ?>

                <!-- Page Header -->
                <div class="page-header mt-4">
                    <div>
                        <h2>
                            <i class="fas fa-user-edit me-2"></i> Edit User
                        </h2>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">
                                        <i class="fas fa-home me-1"></i> Dashboard
                                    </a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="manage-users.php">Manage Users</a>
                                </li>
                                <li class="breadcrumb-item active">Edit User</li>
                            </ol>
                        </nav>
                    </div>
                    <a href="manage-users.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                </div>

                <!-- Profile Layout -->
                <div class="profile-layout">

                    <!-- ── Left: Profile Sidebar ── -->
                    <div class="profile-sidebar">
                        <div class="profile-sidebar-header">
                            <div class="profile-avatar-wrap">
                                <div class="profile-avatar">
                                    <?php echo strtoupper(substr($user['fname'], 0, 1)); ?>
                                </div>
                                <div class="profile-online"></div>
                            </div>
                            <div class="profile-name">
                                <?php echo e($user['fname'] . ' ' . $user['lname']); ?>
                            </div>
                            <div class="profile-id">User ID: #<?php echo $uid; ?></div>
                        </div>

                        <div class="profile-sidebar-body">
                            <div class="info-row">
                                <div class="info-icon info-icon-purple">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value">
                                        <?php echo e($user['fname'] . ' ' . $user['lname']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon info-icon-blue">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div>
                                    <div class="info-label">Email</div>
                                    <div class="info-value">
                                        <?php echo e($user['email']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon info-icon-green">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div>
                                    <div class="info-label">Contact</div>
                                    <div class="info-value">
                                        <?php echo e($user['contactno']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-icon info-icon-amber">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div>
                                    <div class="info-label">Registered On</div>
                                    <div class="info-value">
                                        <?php echo e($user['posting_date']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Right: Edit Form ── -->
                    <div class="form-card">
                        <div class="card-header">
                            <div class="header-icon">
                                <i class="fas fa-user-pen"></i>
                            </div>
                            <div>
                                <h5>Edit User Details</h5>
                                <p>Update information for User ID: #<?php echo $uid; ?></p>
                            </div>
                        </div>

                        <div class="card-body">
                            <form method="post" autocomplete="off" id="updateForm">
                                <input type="hidden" name="csrf_token"
                                       value="<?php echo e($_SESSION['csrf_token']); ?>" />
                                <input type="hidden" name="update" value="1" />

                                <!-- Row 1: First + Last Name -->
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="field-label" for="fname">
                                            <i class="fas fa-user"></i> First Name
                                        </label>
                                        <input class="form-control form-control-custom"
                                               id="fname"
                                               name="fname"
                                               type="text"
                                               value="<?php echo e($user['fname']); ?>"
                                               placeholder="First name"
                                               maxlength="50"
                                               required />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="field-label" for="lname">
                                            <i class="fas fa-user"></i> Last Name
                                        </label>
                                        <input class="form-control form-control-custom"
                                               id="lname"
                                               name="lname"
                                               type="text"
                                               value="<?php echo e($user['lname']); ?>"
                                               placeholder="Last name"
                                               maxlength="50"
                                               required />
                                    </div>
                                </div>

                                <!-- Row 2: Email + Contact -->
                                <div class="row g-4 mt-1">
                                    <div class="col-md-6">
                                        <label class="field-label" for="email">
                                            <i class="fas fa-envelope"></i> Email
                                        </label>
                                        <input class="form-control form-control-custom"
                                               id="email"
                                               name="email"
                                               type="email"
                                               value="<?php echo e($user['email']); ?>"
                                               placeholder="email@example.com"
                                               required />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="field-label" for="contactno">
                                            <i class="fas fa-phone"></i> Contact No.
                                        </label>
                                        <input class="form-control form-control-custom"
                                               id="contactno"
                                               name="contactno"
                                               type="tel"
                                               value="<?php echo e($user['contactno']); ?>"
                                               placeholder="10-15 digit number"
                                               maxlength="15"
                                               required />
                                    </div>
                                </div>

                                <!-- Divider -->
                                <div class="section-divider">
                                    <span>
                                        <i class="fas fa-lock me-1"></i> Change Password
                                    </span>
                                </div>

                                <!-- Password -->
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="field-label" for="password">
                                            <i class="fas fa-key"></i> New Password
                                        </label>
                                        <div class="input-password-wrap">
                                            <input class="form-control form-control-custom"
                                                   id="password"
                                                   name="password"
                                                   type="password"
                                                   placeholder="Leave blank to keep current" />
                                            <button type="button"
                                                    class="toggle-password"
                                                    onclick="togglePwd('password', this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="field-hint">
                                            <i class="fas fa-info-circle"></i>
                                            Min. 6 characters. Leave blank to keep current.
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="field-label" for="confirm_password">
                                            <i class="fas fa-key"></i> Confirm Password
                                        </label>
                                        <div class="input-password-wrap">
                                            <input class="form-control form-control-custom"
                                                   id="confirm_password"
                                                   name="confirm_password"
                                                   type="password"
                                                   placeholder="Repeat new password" />
                                            <button type="button"
                                                    class="toggle-password"
                                                    onclick="togglePwd('confirm_password', this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Buttons -->
                                <div class="row g-3 mt-3">
                                    <div class="col-md-4">
                                        <a href="manage-users.php" class="btn-cancel">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    </div>
                                    <div class="col-md-8">
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

            </div>
        </main>

        <?php include('../includes/footer.php'); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="../js/scripts.js"></script>
<script>
// ── Password Toggle ──────────────────────────────────────
function togglePwd(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// ── Update Button ────────────────────────────────────────
document.getElementById('updateBtn').addEventListener('click', function () {

    const fname     = document.getElementById('fname').value.trim();
    const lname     = document.getElementById('lname').value.trim();
    const email     = document.getElementById('email').value.trim();
    const contact   = document.getElementById('contactno').value.trim();
    const password  = document.getElementById('password').value.trim();
    const confirm   = document.getElementById('confirm_password').value.trim();

    // Required fields
    if (!fname || !lname || !email || !contact) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Fields',
            text: 'Please fill in all required fields.',
            confirmButtonColor: '#6c63ff',
            customClass: { popup: 'rounded-4', confirmButton: 'rounded-3' }
        });
        return;
    }

    // Email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Email',
            text: 'Please enter a valid email address.',
            confirmButtonColor: '#6c63ff',
            customClass: { popup: 'rounded-4', confirmButton: 'rounded-3' }
        });
        return;
    }

    // Contact format
    const contactRegex = /^[0-9]{10,15}$/;
    if (!contactRegex.test(contact)) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Contact',
            text: 'Contact number must be 10-15 digits.',
            confirmButtonColor: '#6c63ff',
            customClass: { popup: 'rounded-4', confirmButton: 'rounded-3' }
        });
        return;
    }

    // Password match
    if (password !== '' && password !== confirm) {
        Swal.fire({
            icon: 'warning',
            title: 'Password Mismatch',
            text: 'New password and confirm password do not match.',
            confirmButtonColor: '#6c63ff',
            customClass: { popup: 'rounded-4', confirmButton: 'rounded-3' }
        });
        return;
    }

    // Password length
    if (password !== '' && password.length < 6) {
        Swal.fire({
            icon: 'warning',
            title: 'Weak Password',
            text: 'Password must be at least 6 characters.',
            confirmButtonColor: '#6c63ff',
            customClass: { popup: 'rounded-4', confirmButton: 'rounded-3' }
        });
        return;
    }

    // Confirm save
    Swal.fire({
        title: 'Save Changes?',
        text: 'Are you sure you want to update this user?',
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
            document.getElementById('updateForm').submit();
        }
    });
});
</script>
</body>
</html>
