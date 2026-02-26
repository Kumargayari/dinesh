<?php
session_start();
include_once('includes/config.php');

if (empty($_SESSION['id'])) {
    header('location:logout.php');
    exit();
}

$uid = mysqli_real_escape_string($con, $_SESSION['id']);

$update_success      = false;
$update_error        = '';
$change_pass_success = false;
$change_pass_error   = '';

// ── UPDATE PROFILE ──
if (isset($_POST['update_profile'])) {
    $fname   = mysqli_real_escape_string($con, $_POST['fname']   ?? '');
    $lname   = mysqli_real_escape_string($con, $_POST['lname']   ?? '');
    $contact = mysqli_real_escape_string($con, $_POST['contact'] ?? '');

    $msg = mysqli_query($con,
        "UPDATE `users` SET
            `fname`='$fname', `lname`='$lname', `contactno`='$contact'
         WHERE `id`='$uid'"
    );
    if ($msg) { $update_success = true; }
    else       { $update_error  = mysqli_error($con); }
}


// ── CHANGE PASSWORD ──
if (isset($_POST['change_password'])) {
    $old_pass  = $_POST['old_pass']  ?? '';
    $new_pass  = $_POST['new_pass']  ?? '';
    $conf_pass = $_POST['conf_pass'] ?? '';

    $pres = mysqli_query($con, "SELECT `password` FROM `users` WHERE `id`='$uid'");
    $prow = mysqli_fetch_assoc($pres);

    // ✅ bcrypt verify (password_verify)
    if (!password_verify($old_pass, $prow['password'])) {
        $change_pass_error = 'Current password is incorrect.';
    } elseif (strlen($new_pass) < 6) {
        $change_pass_error = 'Password must be at least 6 characters.';
    } elseif ($new_pass !== $conf_pass) {
        $change_pass_error = 'Passwords do not match.';
    } else {
        // ✅ bcrypt  new password 
        $new_hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        $new_hashed  = mysqli_real_escape_string($con, $new_hashed);
        $pmsg = mysqli_query($con,
            "UPDATE `users` SET `password`='$new_hashed' WHERE `id`='$uid'"
        );
        if ($pmsg) { $change_pass_success = true; }
        else        { $change_pass_error   = mysqli_error($con); }
    }
}


// ── FETCH USER ──
$user = [];
$ures = mysqli_query($con, "SELECT * FROM `users` WHERE `id`='$uid'");
if ($ures && mysqli_num_rows($ures) > 0) {
    $user = mysqli_fetch_assoc($ures);
} else {
    header('location:logout.php');
    exit();
}

$full_name  = trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? ''));
$initials   = strtoupper(
    substr($user['fname'] ?? 'U', 0, 1) .
    substr($user['lname'] ?? '',  0, 1)
);
$days_since = !empty($user['posting_date'])
    ? floor((time() - strtotime($user['posting_date'])) / 86400)
    : '—';

// ✅ Booklet count
$bcnt     = mysqli_query($con, "SELECT COUNT(*) as c FROM booklet WHERE u_id='$uid'");
$bcnt_row = $bcnt ? mysqli_fetch_assoc($bcnt) : ['c' => 0];


// Runner count
$rcnt     = mysqli_query($con, "SELECT COUNT(*) as c FROM runner WHERE u_id='$uid'");
$rcnt_row = $rcnt ? mysqli_fetch_assoc($rcnt) : ['c' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>My Profile</title>

    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet"/>
    <link href="css/styles.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>

    <style>
        /* ════════════ HERO ════════════ */
        .profile-hero {
            background: #343a40;
            border-radius: 10px;
            padding: 28px 26px;
            display: flex;
            align-items: center;
            gap: 22px;
            flex-wrap: wrap;
            margin-bottom: 22px;
            position: relative;
            overflow: hidden;
        }
        .profile-hero::before {
            content: '';
            position: absolute; top: -50px; right: -50px;
            width: 180px; height: 180px; border-radius: 50%;
            background: rgba(255,255,255,0.03);
            pointer-events: none;
        }
        .profile-hero::after {
            content: '';
            position: absolute; bottom: -60px; left: 30%;
            width: 220px; height: 220px; border-radius: 50%;
            background: rgba(13,110,253,0.07);
            pointer-events: none;
        }

        /* avatar */
        .avatar-wrap { position: relative; flex-shrink: 0; z-index: 1; }
        .avatar-circle {
            width: 80px; height: 80px; border-radius: 50%;
            background: linear-gradient(135deg, #0d6efd, #6610f2);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.75rem; color: #fff; font-weight: 800;
            border: 3px solid rgba(255,255,255,0.15);
        }
        .avatar-dot {
            width: 14px; height: 14px; background: #198754;
            border: 3px solid #343a40; border-radius: 50%;
            position: absolute; bottom: 2px; right: 2px;
        }

        /* hero text */
        .hero-body { flex: 1; min-width: 180px; z-index: 1; }
        .hero-body h4 { color: #fff; font-weight: 700; margin: 0 0 3px; font-size: 1.2rem; }
        .hero-body p  { color: rgba(255,255,255,0.45); margin: 0 0 12px; font-size: 0.85rem; }
        .hero-chips   { display: flex; gap: 7px; flex-wrap: wrap; }
        .hero-chip {
            background: rgba(255,255,255,0.09);
            border: 1px solid rgba(255,255,255,0.13);
            color: rgba(255,255,255,0.7);
            border-radius: 20px; padding: 3px 12px;
            font-size: 0.76rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .hero-chip i { color: #6ea8fe; font-size: 0.67rem; }

        /* stat boxes */
        .hero-stats { display: flex; gap: 10px; flex-wrap: wrap; z-index: 1; }
        .stat-box {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px; padding: 11px 18px;
            text-align: center; min-width: 80px;
        }
        .stat-box .s-val { font-size: 1.45rem; font-weight: 800; color: #fff; line-height: 1; }
        .stat-box .s-lbl {
            font-size: 0.67rem; color: rgba(255,255,255,0.42);
            margin-top: 3px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
        }

        /* ════════════ CARD ════════════ */
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 16px rgba(0,0,0,0.08); }
        .card-header-custom {
            background: #343a40; color: #fff;
            padding: 13px 20px; border-radius: 10px 10px 0 0;
            display: flex; align-items: center;
            justify-content: space-between; flex-wrap: wrap; gap: 8px;
        }
        .card-header-custom h6 { margin: 0; font-size: 0.97rem; font-weight: 700; }
        .hdr-badge {
            background: rgba(255,255,255,0.13);
            border: 1px solid rgba(255,255,255,0.22);
            color: #fff; padding: 3px 12px;
            border-radius: 20px; font-size: 0.74rem; font-weight: 700;
        }

        /* ════════════ SECTION LABELS ════════════ */
        .sec-label {
            font-size: 0.72rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.09em;
            color: #6c757d; margin-bottom: 8px;
            padding-bottom: 6px; border-bottom: 2px solid #e9ecef;
        }
        .form-section { margin-bottom: 1.25rem; }

        /* ════════════ INPUTS ════════════ */
        .form-label { font-size: 0.82rem; font-weight: 600; color: #495057; margin-bottom: 4px; }
        .form-control {
            border-radius: 8px; border: 1.5px solid #dee2e6;
            padding: 9px 13px; font-size: 0.9rem;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.12);
        }
        .input-group-text {
            background: #f8f9fa; border: 1.5px solid #dee2e6;
            border-right: 0; border-radius: 8px 0 0 8px;
            color: #6c757d; padding: 9px 12px;
        }
        .input-group .form-control { border-left: 0; border-radius: 0 8px 8px 0; }
        .input-group .btn-eye {
            border: 1.5px solid #dee2e6; border-left: 0;
            border-radius: 0 8px 8px 0; background: #f8f9fa;
            color: #6c757d; padding: 0 12px; font-size: 0.82rem;
            cursor: pointer; transition: background .15s;
        }
        .input-group .btn-eye:hover { background: #e9ecef; color: #212529; }
        .input-group.has-eye .form-control { border-radius: 0; }
        .input-group:focus-within .input-group-text { border-color: #0d6efd; }
        .input-group:focus-within .form-control     { border-color: #0d6efd; box-shadow: none; }

        /* readonly box */
        .info-box {
            background: #f8f9fa; border: 1.5px solid #e9ecef;
            border-radius: 8px; padding: 9px 14px;
            font-size: 0.88rem; color: #495057;
            display: flex; align-items: center; gap: 8px;
        }
        .info-box i { color: #0d6efd; width: 14px; }

        /* ════════════ BUTTONS ════════════ */
        .btn-save {
            background: #343a40; color: #fff; border: none;
            border-radius: 6px; padding: 10px 28px;
            font-size: 0.92rem; font-weight: 700;
            transition: background .2s;
        }
        .btn-save:hover { background: #23272b; color: #fff; }

        .btn-chpass {
            background: #0d6efd; color: #fff; border: none;
            border-radius: 6px; padding: 11px 0;
            font-size: 0.92rem; font-weight: 700; width: 100%;
            transition: background .2s;
        }
        .btn-chpass:hover { background: #0a58ca; color: #fff; }

        /* ════════════ STRENGTH BAR ════════════ */
        .strength-bar {
            height: 5px; border-radius: 4px;
            background: #e9ecef; overflow: hidden; margin-top: 7px;
        }
        .strength-fill { height: 100%; width: 0; border-radius: 4px; transition: width .3s, background .3s; }
        .strength-lbl  { font-size: 0.75rem; font-weight: 700; margin-top: 3px; }

        /* ════════════ TIPS ════════════ */
        .tip-list { list-style: none; padding: 0; margin: 0; }
        .tip-list li {
            font-size: 0.83rem; color: #6c757d;
            padding: 5px 0; display: flex;
            align-items: flex-start; gap: 8px;
        }
        .tip-list li i { color: #0d6efd; margin-top: 2px; flex-shrink: 0; }

        /* ════════════ MISC ════════════ */
        .breadcrumb-item a { text-decoration: none; }
        .req { color: #dc3545; }

        @media (max-width: 576px) {
            .profile-hero  { padding: 18px 14px; gap: 14px; }
            .avatar-circle { width: 64px; height: 64px; font-size: 1.35rem; }
            .hero-body h4  { font-size: 1rem; }
        }
    </style>
</head>

<body class="sb-nav-fixed">
<?php include_once('includes/navbar.php'); ?>

<div id="layoutSidenav">
    <?php include_once('includes/sidebar.php'); ?>

    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4">

                <h1 class="mt-4">My Profile</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">My Profile</li>
                </ol>

                <!-- ════════════ HERO BANNER ════════════ -->
                <div class="profile-hero">
                    <div class="avatar-wrap">
                        <div class="avatar-circle"><?php echo $initials; ?></div>
                        <div class="avatar-dot"></div>
                    </div>
                    <div class="hero-body">
                        <h4><?php echo htmlspecialchars($full_name ?: 'User'); ?></h4>
                        <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                        <div class="hero-chips">
                            <span class="hero-chip">
                                <i class="fas fa-id-badge"></i>
                                ID #<?php echo htmlspecialchars($user['id'] ?? ''); ?>
                            </span>
                            <span class="hero-chip">
                                <i class="fas fa-phone"></i>
                                <?php echo htmlspecialchars($user['contactno'] ?? 'N/A'); ?>
                            </span>
                            <span class="hero-chip">
                                <i class="fas fa-calendar-check"></i>
                                Joined: <?php echo isset($user['posting_date']) ? date('d M Y', strtotime($user['posting_date'])) : 'N/A'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="hero-stats">
                        <div class="stat-box">
                            <div class="s-val"><?php echo $rcnt_row['c']; ?></div>
                            <div class="s-lbl">Runners</div>
                        </div>
                       <div class="stat-box">
							<div class="s-val"><?php echo $bcnt_row['c']; ?></div>
							<div class="s-lbl">Booklets</div>
						</div>
                    </div>
                </div>

                <!-- ════════════ MAIN ROW ════════════ -->
                <div class="row g-4">

                    <!-- ── LEFT : Edit Profile ── -->
                    <div class="col-lg-7">
                        <div class="card mb-4">
                            <div class="card-header-custom">
                                <h6><i class="fas fa-user-edit me-2"></i>Edit Profile</h6>
                                <span class="hdr-badge"><i class="fas fa-pencil-alt me-1"></i>Editable</span>
                            </div>
                            <div class="card-body px-4 py-4">
                                <form method="post" action="profile.php">

                                    <!-- Name -->
                                    <div class="sec-label"><i class="fas fa-user me-1"></i> Personal Details</div>
                                    <div class="row g-3 form-section">
                                        <div class="col-md-6">
                                            <label class="form-label">First Name <span class="req">*</span></label>
                                            <input class="form-control" name="fname" type="text"
                                                placeholder="First name"
                                                value="<?php echo htmlspecialchars($user['fname'] ?? ''); ?>"
                                                required/>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Last Name <span class="req">*</span></label>
                                            <input class="form-control" name="lname" type="text"
                                                placeholder="Last name"
                                                value="<?php echo htmlspecialchars($user['lname'] ?? ''); ?>"
                                                required/>
                                        </div>
                                    </div>

                                    <!-- Contact -->
                                    <div class="sec-label"><i class="fas fa-address-book me-1"></i> Contact Details</div>
                                    <div class="row g-3 form-section">
                                        <div class="col-md-6">
                                            <label class="form-label">Mobile Number <span class="req">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-phone fa-sm"></i></span>
                                                <input class="form-control" name="contact" type="tel"
                                                    placeholder="10-digit number"
                                                    pattern="[0-9]{10}" maxlength="10"
                                                    value="<?php echo htmlspecialchars($user['contactno'] ?? ''); ?>"
                                                    required/>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email Address</label>
                                            <div class="info-box">
                                                <i class="fas fa-envelope"></i>
                                                <?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Account readonly -->
                                    <div class="sec-label"><i class="fas fa-shield-alt me-1"></i> Account Info</div>
                                    <div class="row g-3 form-section">
                                        <div class="col-md-6">
                                            <label class="form-label">User ID</label>
                                            <div class="info-box">
                                                <i class="fas fa-id-card"></i>
                                                #<?php echo htmlspecialchars($user['id'] ?? ''); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Registration Date</label>
                                            <div class="info-box">
                                                <i class="fas fa-calendar-alt"></i>
                                                <?php echo isset($user['posting_date']) ? date('d M Y', strtotime($user['posting_date'])) : 'N/A'; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" name="update_profile" class="btn-save">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>

                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- ── RIGHT : Password + Tips ── -->
                    <div class="col-lg-5">

                        <!-- Change Password -->
                        <div class="card mb-4">
                            <div class="card-header-custom">
                                <h6><i class="fas fa-lock me-2"></i>Change Password</h6>
                                <span class="hdr-badge"><i class="fas fa-shield-alt me-1"></i>Secure</span>
                            </div>
                            <div class="card-body px-4 py-4">
                                <form method="post" action="profile.php" onsubmit="return validatePassword();">

                                    <div class="form-section">
                                        <label class="form-label">Current Password <span class="req">*</span></label>
                                        <div class="input-group has-eye">
                                            <span class="input-group-text"><i class="fas fa-key fa-sm"></i></span>
                                            <input class="form-control" name="old_pass" id="old_pass"
                                                type="password" placeholder="Enter current password" required/>
                                            <button type="button" class="btn-eye" onclick="togglePass('old_pass',this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="form-section">
                                        <label class="form-label">New Password <span class="req">*</span></label>
                                        <div class="input-group has-eye">
                                            <span class="input-group-text"><i class="fas fa-lock fa-sm"></i></span>
                                            <input class="form-control" name="new_pass" id="new_pass"
                                                type="password" placeholder="Min 6 characters"
                                                oninput="checkStrength(this.value)" required/>
                                            <button type="button" class="btn-eye" onclick="togglePass('new_pass',this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="strength-bar">
                                            <div class="strength-fill" id="sFill"></div>
                                        </div>
                                        <div class="strength-lbl" id="sLbl"></div>
                                    </div>

                                    <div class="form-section">
                                        <label class="form-label">Confirm Password <span class="req">*</span></label>
                                        <div class="input-group has-eye">
                                            <span class="input-group-text"><i class="fas fa-lock fa-sm"></i></span>
                                            <input class="form-control" name="conf_pass" id="conf_pass"
                                                type="password" placeholder="Re-enter new password" required/>
                                            <button type="button" class="btn-eye" onclick="togglePass('conf_pass',this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <button type="submit" name="change_password" class="btn-chpass">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>

                                </form>
                            </div>
                        </div>

                        <!-- Tips Card -->
                        <div class="card mb-4">
                            <div class="card-header-custom">
                                <h6><i class="fas fa-lightbulb me-2"></i>Password Tips</h6>
                            </div>
                            <div class="card-body px-4 py-3">
                                <ul class="tip-list">
                                    <li><i class="fas fa-check-circle"></i> Use at least 8 characters</li>
                                    <li><i class="fas fa-check-circle"></i> Mix uppercase &amp; lowercase letters</li>
                                    <li><i class="fas fa-check-circle"></i> Include numbers (0–9)</li>
                                    <li><i class="fas fa-check-circle"></i> Add special characters (!@#$%)</li>
                                    <li><i class="fas fa-check-circle"></i> Avoid your name or birthdate</li>
                                    <li><i class="fas fa-check-circle"></i> Never reuse old passwords</li>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div><!-- /row -->

            </div><!-- /container -->
        </main>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/scripts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
<script src="js/datatables-simple-demo.js"></script>

<!-- Popups -->
<?php if ($update_success): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'success', title: 'Profile Updated!',
        text: 'Your profile has been saved successfully.',
        confirmButtonColor: '#343a40',
        timer: 2500, timerProgressBar: true
    }).then(function () { window.location.href = 'profile.php'; });
});
</script>
<?php elseif (!empty($update_error)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'error', title: 'Update Failed!',
        html: '<code><?php echo addslashes(htmlspecialchars($update_error)); ?></code>',
        confirmButtonColor: '#dc3545'
    });
});
</script>
<?php endif; ?>

<?php if ($change_pass_success): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'success', title: 'Password Changed!',
        text: 'Password updated. Please login again.',
        confirmButtonColor: '#343a40'
    }).then(function () { window.location.href = 'logout.php'; });
});
</script>
<?php elseif (!empty($change_pass_error)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'error', title: 'Error!',
        text: '<?php echo addslashes($change_pass_error); ?>',
        confirmButtonColor: '#dc3545'
    });
});
</script>
<?php endif; ?>

<script>
function togglePass(id, btn) {
    var f = document.getElementById(id);
    var i = btn.querySelector('i');
    if (f.type === 'password') {
        f.type = 'text';
        i.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        f.type = 'password';
        i.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function checkStrength(val) {
    var fill  = document.getElementById('sFill');
    var label = document.getElementById('sLbl');
    var s = 0;
    if (val.length >= 6)           s++;
    if (val.length >= 10)          s++;
    if (/[A-Z]/.test(val))         s++;
    if (/[0-9]/.test(val))         s++;
    if (/[^A-Za-z0-9]/.test(val))  s++;

    var levels = [
        { w:'0%',   bg:'#e9ecef', lbl:'',      cls:'' },
        { w:'25%',  bg:'#dc3545', lbl:'Weak',   cls:'text-danger' },
        { w:'50%',  bg:'#fd7e14', lbl:'Fair',   cls:'text-warning' },
        { w:'75%',  bg:'#ffc107', lbl:'Good',   cls:'text-info' },
        { w:'100%', bg:'#198754', lbl:'Strong', cls:'text-success' }
    ];
    var l = levels[Math.min(s, 4)];
    fill.style.width      = l.w;
    fill.style.background = l.bg;
    label.textContent     = l.lbl;
    label.className       = 'strength-lbl ' + l.cls;
}

function validatePassword() {
    var np = document.getElementById('new_pass').value;
    var cp = document.getElementById('conf_pass').value;
    if (np.length < 6) {
        Swal.fire({ icon:'warning', title:'Too Short',
            text:'Password must be at least 6 characters.',
            confirmButtonColor:'#343a40' });
        return false;
    }
    if (np !== cp) {
        Swal.fire({ icon:'warning', title:'Mismatch!',
            text:'Passwords do not match.',
            confirmButtonColor:'#343a40' });
        return false;
    }
    return true;
}
</script>

</body>
</html>
