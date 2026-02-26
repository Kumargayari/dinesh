<?php
session_start();
include_once('../includes/config.php');

if (empty($_SESSION['adminid'])) {
    header('Location: index.php'); exit();
}

if (empty($_SESSION['csrf_token']))
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token.');
    }

    $fname   = trim($_POST['fname']    ?? '');
    $lname   = trim($_POST['lname']    ?? '');
    $email   = strtolower(trim($_POST['email']   ?? ''));
    $pwd     = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm']  ?? '');
    $contact = trim($_POST['contact']  ?? '');
    $type    = (int)($_POST['type']    ?? 0);

    if (!$fname || !$lname || !$email || !$pwd || !$contact || $type <= 0) {
        $_SESSION['error'] = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email address.';
    } elseif (!preg_match('/^[0-9]{10,15}$/', $contact)) {
        $_SESSION['error'] = 'Contact must be 10-15 digits.';
    } elseif (strlen($pwd) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters.';
    } elseif ($pwd !== $confirm) {
        $_SESSION['error'] = 'Passwords do not match.';
    } else {

        $chk = $con->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param('s', $email);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $_SESSION['error'] = 'Email already exists!';
        } else {
            do {
                $ref = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
                $rc  = $con->prepare("SELECT id FROM users WHERE referral_code = ?");
                $rc->bind_param('s', $ref);
                $rc->execute();
                $dup = $rc->get_result()->num_rows > 0;
                $rc->close();
            } while ($dup);

            $hashed = password_hash($pwd, PASSWORD_DEFAULT);

            $stmt = $con->prepare("
                INSERT INTO users (u_type, fname, lname, email, password, contactno, referral_code)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                $_SESSION['error'] = 'Prepare Error: ' . $con->error;
            } else {
                $stmt->bind_param('issssss', $type, $fname, $lname, $email, $hashed, $contact, $ref);
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'User "' . $fname . ' ' . $lname . '" created! Code: ' . $ref;
                    $stmt->close();
                    header('Location: manage-users.php'); exit();
                } else {
                    $_SESSION['error'] = 'Insert Failed: ' . $stmt->error;
                    $stmt->close();
                }
            }
        }
        $chk->close();
    }
    header('Location: create-admin.php'); exit();
}

$types = $con->query("SELECT id, type FROM type ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Create User — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="../css/styles.css" rel="stylesheet" onerror="this.remove()"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f4f6fb; }
        #layoutSidenav_content { display:flex; flex-direction:column; min-height:calc(100vh - 56px); }
        #layoutSidenav_content main { flex:1; }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #6c63ff 0%, #574fd6 50%, #f50057 100%);
            border-radius: 16px; padding: 22px 28px; margin-bottom: 28px;
            box-shadow: 0 8px 24px rgba(108,99,255,0.3);
            display: flex; align-items: center;
            justify-content: space-between; flex-wrap: wrap; gap: 12px;
        }
        .page-header h2 { color: #fff; font-size: 1.35rem; font-weight: 800; margin: 0; }
        .page-header .breadcrumb { margin: 4px 0 0; background: none; padding: 0; }
        .page-header .breadcrumb-item a { color: rgba(255,255,255,0.7); text-decoration: none; font-size: .8rem; }
        .page-header .breadcrumb-item.active { color: #fff; font-size: .8rem; }
        .page-header .breadcrumb-item+.breadcrumb-item::before { color: rgba(255,255,255,0.5); }
        .btn-back {
            background: transparent; border: 1.5px solid rgba(255,255,255,0.3);
            color: rgba(255,255,255,.85); padding: 8px 18px; border-radius: 10px;
            font-size: .82rem; font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px; transition: all .2s;
        }
        .btn-back:hover { background: rgba(255,255,255,.1); color: #fff; }

        /* Form Card */
        .form-card {
            border: none; border-radius: 18px;
            box-shadow: 0 6px 30px rgba(0,0,0,.09);
            overflow: hidden; max-width: 820px; margin: 0 auto;
        }
        .card-hdr {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            padding: 18px 28px; display: flex; align-items: center; gap: 14px;
        }
        .hdr-icon {
            width: 42px; height: 42px; border-radius: 11px;
            background: linear-gradient(135deg, #6c63ff, #574fd6);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 16px; flex-shrink: 0;
        }
        .card-hdr h5 { color: #fff; margin: 0; font-size: .97rem; font-weight: 700; }
        .card-hdr p  { color: rgba(255,255,255,.4); margin: 0; font-size: .75rem; }
        .card-bdy { padding: 32px 34px; background: #fff; }

        /* Labels */
        .fl {
            font-size: .74rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .7px; color: #6b7280; margin-bottom: 7px;
            display: flex; align-items: center; gap: 6px;
        }
        .fl i { color: #6c63ff; font-size: .8rem; }
        .req { color: #f50057; }

        /* Inputs */
        .fc {
            border-radius: 11px !important; border: 1.5px solid #e5e7eb !important;
            padding: 11px 14px !important; font-size: .89rem !important;
            color: #374151 !important; background: #fafbff !important;
            width: 100%; display: block; transition: all .25s !important;
        }
        .fc:focus {
            border-color: #6c63ff !important; background: #fff !important;
            box-shadow: 0 0 0 3px rgba(108,99,255,.12) !important; outline: none !important;
        }
        .fc::placeholder { color: #c1c9d2 !important; font-size: .84rem !important; }

        select.fc {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236c63ff' d='M6 8L1 3h10z'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 14px center !important;
            padding-right: 36px !important; cursor: pointer;
        }

        /* Password wrap */
        .pw-wrap { position: relative; }
        .pw-wrap .fc { padding-right: 44px !important; }
        .pw-toggle {
            position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #9ca3af;
            cursor: pointer; font-size: 14px; padding: 0; transition: color .2s;
        }
        .pw-toggle:hover { color: #6c63ff; }

        /* Section Divider */
        .sec-div {
            display: flex; align-items: center; gap: 12px;
            margin: 24px 0 20px;
        }
        .sec-div span {
            font-size: .72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 1px; color: #6c63ff; white-space: nowrap;
        }
        .sec-div::before, .sec-div::after {
            content: ''; flex: 1; height: 1.5px;
            background: linear-gradient(90deg, transparent, rgba(108,99,255,.2), transparent);
        }

        /* Strength bar */
        .str-bar { height: 4px; border-radius: 4px; background: #e5e7eb; margin-top: 8px; overflow: hidden; }
        .str-fill { height: 100%; border-radius: 4px; transition: all .3s; width: 0%; }
        .str-txt  { font-size: .72rem; margin-top: 4px; font-weight: 700; min-height: 16px; }

        /* Match hint */
        .match-hint { font-size: .74rem; margin-top: 5px; min-height: 20px; display: flex; align-items: center; gap: 4px; }

        /* Info box */
        .info-box {
            background: linear-gradient(135deg, #f5f3ff, #ede9fe);
            border: 1px solid rgba(108,99,255,.2); border-radius: 12px;
            padding: 12px 16px; margin-bottom: 22px;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .info-box i { color: #6c63ff; margin-top: 2px; flex-shrink: 0; }
        .info-box p { margin: 0; font-size: .81rem; color: #4b5563; line-height: 1.5; }

        /* Buttons */
        .btn-submit {
            background: linear-gradient(135deg, #6c63ff, #574fd6);
            border: none; color: #fff; padding: 13px 28px;
            border-radius: 12px; font-size: .92rem; font-weight: 700;
            width: 100%; cursor: pointer; transition: all .3s;
            box-shadow: 0 6px 20px rgba(108,99,255,.35);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(108,99,255,.45); }
        .btn-submit:disabled { opacity: .7; transform: none; cursor: not-allowed; }

        .btn-cancel {
            background: #f3f4f6; border: none; color: #6b7280;
            padding: 13px 28px; border-radius: 12px;
            font-size: .92rem; font-weight: 700; width: 100%;
            text-decoration: none; display: flex;
            align-items: center; justify-content: center; gap: 8px; transition: all .2s;
        }
        .btn-cancel:hover { background: #e5e7eb; color: #374151; }
    </style>
</head>
<body class="sb-nav-fixed">

<?php include_once('includes/navbar.php'); ?>
<div id="layoutSidenav">
    <?php include_once('includes/sidebar.php'); ?>
    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4 pb-5">

                <h1 class="mt-4">Create User</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="manage-users.php">Users</a></li>
                    <li class="breadcrumb-item active">Create</li>
                </ol>

                <!-- SweetAlert -->
                <?php if (!empty($_SESSION['error'])): ?>
                <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({ icon:'error', title:'Error!',
                        text: '<?php echo e($_SESSION['error']); ?>',
                        confirmButtonColor: '#6c63ff',
                        customClass: { popup:'rounded-4', confirmButton:'rounded-3' }
                    });
                });
                </script>
                <?php unset($_SESSION['error']); endif; ?>

                <?php if (!empty($_SESSION['success'])): ?>
                <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({ icon:'success', title:'Created!',
                        text: '<?php echo e($_SESSION['success']); ?>',
                        confirmButtonColor: '#6c63ff',
                        customClass: { popup:'rounded-4', confirmButton:'rounded-3' }
                    });
                });
                </script>
                <?php unset($_SESSION['success']); endif; ?>

                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h2><i class="fas fa-user-plus me-2"></i>Create User Account</h2>
                        <nav><ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="manage-users.php">Manage Users</a></li>
                            <li class="breadcrumb-item active">Create</li>
                        </ol></nav>
                    </div>
                    <a href="manage-users.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                </div>

                <!-- Form Card -->
                <div class="form-card">
                    <div class="card-hdr">
                        <div class="hdr-icon"><i class="fas fa-user-plus"></i></div>
                        <div>
                            <h5>New User Details</h5>
                            <p>Fill all fields to create a new user account</p>
                        </div>
                    </div>
                    <div class="card-bdy">

                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <p>A unique <strong>referral code</strong> will be auto-generated for this user.</p>
                        </div>

                        <form method="POST" id="createForm" autocomplete="off">
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo e($_SESSION['csrf_token']); ?>"/>

                            <!-- Personal Info -->
                            <div class="sec-div">
                                <span><i class="fas fa-user me-1"></i>Personal Information</span>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="fl"><i class="fas fa-user"></i>First Name <span class="req">*</span></label>
                                    <input type="text" name="fname" class="fc"
                                           placeholder="Enter first name" maxlength="100" required/>
                                </div>
                                <div class="col-md-6">
                                    <label class="fl"><i class="fas fa-user"></i>Last Name <span class="req">*</span></label>
                                    <input type="text" name="lname" class="fc"
                                           placeholder="Enter last name" maxlength="100" required/>
                                </div>
                            </div>

                            <div class="row g-4 mt-1">
                                <div class="col-md-6">
                                    <label class="fl"><i class="fas fa-envelope"></i>Email Address <span class="req">*</span></label>
                                    <input type="email" name="email" class="fc"
                                           placeholder="user@example.com" required/>
                                </div>
                                <div class="col-md-6">
                                    <label class="fl"><i class="fas fa-phone"></i>Contact Number <span class="req">*</span></label>
                                    <input type="tel" name="contact" class="fc"
                                           placeholder="10–15 digit number"
                                           maxlength="15" required/>
                                    <div style="font-size:.74rem;color:#9ca3af;margin-top:5px;">
                                        <i class="fas fa-info-circle me-1"></i>Numbers only, 10–15 digits
                                    </div>
                                </div>
                            </div>

                            <!-- User Role -->
                            <div class="sec-div">
                                <span><i class="fas fa-shield-alt me-1"></i>User Role</span>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="fl"><i class="fas fa-shield-alt"></i>User Type <span class="req">*</span></label>
                                    <select name="type" id="typeSelect" class="fc" required>
                                        <option value="">── Select User Type ──</option>
                                        <?php
                                        if ($types && $types->num_rows > 0) {
                                            while ($t = $types->fetch_assoc())
                                                echo '<option value="' . (int)$t['id'] . '">'
                                                   . e($t['type']) . '</option>';
                                        } else {
                                            echo '<option value="1">Super Admin</option>';
                                            echo '<option value="2">Admin</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="sec-div">
                                <span><i class="fas fa-lock me-1"></i>Set Password</span>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="fl"><i class="fas fa-key"></i>Password <span class="req">*</span></label>
                                    <div class="pw-wrap">
                                        <input type="password" id="pwd" name="password" class="fc"
                                               placeholder="Min. 6 characters" required/>
                                        <button type="button" class="pw-toggle"
                                                onclick="togglePwd('pwd', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="str-bar"><div class="str-fill" id="strFill"></div></div>
                                    <div class="str-txt"  id="strTxt"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="fl"><i class="fas fa-key"></i>Confirm Password <span class="req">*</span></label>
                                    <div class="pw-wrap">
                                        <input type="password" id="cpwd" name="confirm" class="fc"
                                               placeholder="Repeat password" required/>
                                        <button type="button" class="pw-toggle"
                                                onclick="togglePwd('cpwd', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="match-hint" id="matchHint"></div>
                                </div>
                            </div>

                            <!-- Buttons -->
                            <div class="row g-3 mt-4">
                                <div class="col-md-4">
                                    <a href="manage-users.php" class="btn-cancel">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                                <div class="col-md-8">
                                    <button type="button" id="submitBtn" class="btn-submit">
                                        <i class="fas fa-user-plus"></i> Create Account
                                    </button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </main>
        <?php include_once('../includes/footer.php'); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/scripts.js"></script>
<script>
// Password Toggle
function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    const ico = btn.querySelector('i');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ico.classList.toggle('fa-eye');
    ico.classList.toggle('fa-eye-slash');
}

// Password Strength
document.getElementById('pwd').addEventListener('input', function () {
    const v = this.value;
    const fill = document.getElementById('strFill');
    const txt  = document.getElementById('strTxt');
    let s = 0;
    if (v.length >= 6)          s++;
    if (/[A-Z]/.test(v))        s++;
    if (/[0-9]/.test(v))        s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;

    const lv = [
        { w:'0%',   c:'#e5e7eb', l:'',       cl:'' },
        { w:'25%',  c:'#dc3545', l:'Weak',   cl:'text-danger' },
        { w:'50%',  c:'#fd7e14', l:'Fair',   cl:'text-warning' },
        { w:'75%',  c:'#0d6efd', l:'Good',   cl:'text-primary' },
        { w:'100%', c:'#198754', l:'Strong', cl:'text-success' },
    ][s] || { w:'0%', c:'#e5e7eb', l:'', cl:'' };

    fill.style.width = lv.w;
    fill.style.background = lv.c;
    txt.textContent = lv.l;
    txt.className = 'str-txt ' + lv.cl;
});

// Confirm Password Match
document.getElementById('cpwd').addEventListener('input', function () {
    const hint = document.getElementById('matchHint');
    if (!this.value) { hint.innerHTML = ''; return; }
    hint.innerHTML = this.value === document.getElementById('pwd').value
        ? '<i class="fas fa-check-circle text-success me-1"></i><span class="text-success">Passwords match</span>'
        : '<i class="fas fa-times-circle text-danger me-1"></i><span class="text-danger">Do not match</span>';
});

// Submit with Confirmation
document.getElementById('submitBtn').addEventListener('click', function () {
    const fname   = document.querySelector('[name=fname]').value.trim();
    const lname   = document.querySelector('[name=lname]').value.trim();
    const email   = document.querySelector('[name=email]').value.trim();
    const contact = document.querySelector('[name=contact]').value.trim();
    const type    = document.getElementById('typeSelect').value;
    const pwd     = document.getElementById('pwd').value;
    const cpwd    = document.getElementById('cpwd').value;

    const warn = (title, text) => Swal.fire({
        icon: 'warning', title, text,
        confirmButtonColor: '#6c63ff',
        customClass: { popup:'rounded-4', confirmButton:'rounded-3' }
    });

    if (!fname || !lname || !email || !contact || !type || !pwd || !cpwd)
        return warn('Missing Fields', 'Please fill in all required fields.');

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
        return warn('Invalid Email', 'Please enter a valid email address.');

    if (!/^[0-9]{10,15}$/.test(contact))
        return warn('Invalid Contact', 'Contact number must be 10–15 digits.');

    if (pwd.length < 6)
        return warn('Weak Password', 'Password must be at least 6 characters.');

    if (pwd !== cpwd)
        return warn('Password Mismatch', 'Passwords do not match.');

    Swal.fire({
        title: 'Create Account?',
        html: 'Create account for <strong>' + fname + ' ' + lname + '</strong>?',
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#6c63ff', cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-user-plus me-1"></i>Yes, Create!',
        cancelButtonText: 'Cancel',
        customClass: { popup:'rounded-4', confirmButton:'rounded-3', cancelButton:'rounded-3' }
    }).then(r => {
        if (r.isConfirmed) {
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';
            btn.disabled = true;
            document.getElementById('createForm').submit();
        }
    });
});
</script>
</body>
</html>
