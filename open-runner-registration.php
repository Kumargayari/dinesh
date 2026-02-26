<?php
session_start();
include_once('includes/config.php');

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $runner_name       = trim($_POST['runner_name']        ?? '');
    $email             = strtolower(trim($_POST['email']   ?? ''));
    $phone             = trim($_POST['phone']              ?? '');
    $gender            = trim($_POST['gender']             ?? '');
    $dob               = trim($_POST['dob']                ?? '');
    $blood_group       = trim($_POST['blood_group']        ?? '');
    $event             = trim($_POST['event']              ?? '');
    $tshirt_size       = trim($_POST['tshirt_size']        ?? '');
    $emergency_contact = trim($_POST['emergency_contact']  ?? '');
    $medical_info      = trim($_POST['medical_info']       ?? 'None');
    $referral_code     = strtoupper(trim($_POST['referral_code'] ?? ''));

    $ip         = $_SERVER['REMOTE_ADDR']     ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $reg_type   = 'public';
    $admin_id   = null;
    $bib_number = null;

    if (!$runner_name || !$email || !$phone || !$gender ||
        !$dob || !$blood_group || !$event || !$tshirt_size ||
        !$emergency_contact || !$referral_code) {
        $_SESSION['reg_error'] = 'All required fields must be filled.';
        header('Location: open-runner-registration.php'); exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['reg_error'] = 'Invalid email address.';
        header('Location: open-runner-registration.php'); exit();
    }

    if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $_SESSION['reg_error'] = 'Phone number must be 10–15 digits.';
        header('Location: open-runner-registration.php'); exit();
    }

    if (!$medical_info) $medical_info = 'None';

    $rChk = $con->prepare("SELECT id FROM users WHERE referral_code = ? LIMIT 1");
    $rChk->bind_param('s', $referral_code);
    $rChk->execute();
    $rRes = $rChk->get_result();

    if ($rRes->num_rows === 0) {
        $_SESSION['reg_error'] = 'Invalid referral code. Please check and try again.';
        $rChk->close();
        header('Location: open-runner-registration.php'); exit();
    }

    $u_id = (int)$rRes->fetch_assoc()['id'];
    $rChk->close();

	$dupChk = $con->prepare("SELECT r_id FROM runner WHERE r_email = ? LIMIT 1");
	$dupChk->bind_param('s', $email);
    $dupChk->execute();
    $dupChk->store_result();
    if ($dupChk->num_rows > 0) {
        $_SESSION['reg_error'] = 'This email is already registered for this event.';
        $dupChk->close();
        header('Location: open-runner-registration.php'); exit();
    }
    $dupChk->close();
$stmt = $con->prepare("
    INSERT INTO runner
        (u_id, r_name, r_email, r_contact, r_gender, r_dob,
         r_bdgp, r_tshirt_sz, r_emrg_con, r_med_dt,
         reg_type, referral_code, ip_addr, user_agent)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    $_SESSION['reg_error'] = 'DB Prepare Error: ' . $con->error;
    header('Location: open-runner-registration.php'); exit();
}

// ✅ 14 columns = 14 variables = 'issssssssssss' (1i + 13s)
$stmt->bind_param('isssssssssssss',
    $u_id,               // i (1)
    $runner_name,        // s (2)  → r_name
    $email,              // s (3)  → r_email
    $phone,              // s (4)  → r_contact
    $gender,             // s (5)  → r_gender
    $dob,                // s (6)  → r_dob
    $blood_group,        // s (7)  → r_bdgp
    $tshirt_size,        // s (8)  → r_tshirt_sz
    $emergency_contact,  // s (9)  → r_emrg_con
    $medical_info,       // s (10) → r_med_dt
    $reg_type,           // s (11)
    $referral_code,      // s (12)
    $ip,                 // s (13) → ip_addr
    $user_agent          // s (14)
);


    if ($stmt->execute()) {
        $stmt->close();
        $_SESSION['reg_success'] = 'Registration successful! Welcome, ' . $runner_name . '!';
        header('Location: open-runner-registration.php'); exit();
    }

    $_SESSION['reg_error'] = 'Registration failed: ' . $stmt->error;
    $stmt->close();
    header('Location: open-runner-registration.php'); exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Runner Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { font-family: 'Inter', sans-serif; box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh; padding: 40px 16px;
        }

        /* ── Back Button ── */
        .back-btn {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.1);
            border: 1.5px solid rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.85);
            padding: 9px 20px; border-radius: 10px;
            font-size: .84rem; font-weight: 600;
            text-decoration: none; transition: all .25s;
            font-family: 'Inter', sans-serif;
            margin-bottom: 16px;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.2);
            color: #fff;
            transform: translateX(-3px);
        }
        .back-btn i { font-size: .8rem; }

        /* ── Hero ── */
        .reg-hero {
            background: linear-gradient(135deg, #6c63ff 0%, #574fd6 50%, #f50057 100%);
            border-radius: 20px; padding: 36px 32px;
            text-align: center; margin-bottom: 20px;
            box-shadow: 0 12px 40px rgba(108,99,255,0.4);
            position: relative; overflow: hidden;
        }
        .reg-hero::before {
            content: ''; position: absolute; top: -40px; right: -40px;
            width: 160px; height: 160px; border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }
        .reg-hero::after {
            content: ''; position: absolute; bottom: -30px; left: -30px;
            width: 120px; height: 120px; border-radius: 50%;
            background: rgba(255,255,255,0.04);
        }
        .reg-hero .icon-wrap {
            width: 64px; height: 64px; border-radius: 18px;
            background: rgba(255,255,255,0.15);
            display: inline-flex; align-items: center;
            justify-content: center; font-size: 26px;
            color: #fff; margin-bottom: 16px;
        }
        .reg-hero h1 { color: #fff; font-size: 1.8rem; font-weight: 800; margin: 0 0 8px; }
        .reg-hero p  { color: rgba(255,255,255,0.7); margin: 0; font-size: .92rem; }
        .hero-badges { margin-top: 16px; display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
        .hero-badge {
            background: rgba(255,255,255,0.12); color: #fff;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 20px; padding: 5px 14px; font-size: .78rem; font-weight: 600;
        }

        /* ── Form Card ── */
        .reg-card {
            background: #fff; border-radius: 20px; overflow: hidden;
            box-shadow: 0 8px 40px rgba(0,0,0,0.2);
            max-width: 820px; margin: 0 auto;
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
        .card-bdy { padding: 32px 34px; }

        /* ── Section Divider ── */
        .sec-div {
            display: flex; align-items: center; gap: 12px;
            margin: 28px 0 20px;
        }
        .sec-div:first-child { margin-top: 0; }
        .sec-div span {
            font-size: .72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px;
            color: #6c63ff; white-space: nowrap;
        }
        .sec-div::before, .sec-div::after {
            content: ''; flex: 1; height: 1.5px;
            background: linear-gradient(90deg, transparent, rgba(108,99,255,.25), transparent);
        }

        /* ── Labels & Inputs ── */
        .fl {
            font-size: .74rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .7px;
            color: #6b7280; margin-bottom: 7px;
            display: flex; align-items: center; gap: 6px;
        }
        .fl i { color: #6c63ff; font-size: .8rem; }
        .req { color: #f50057; }

        .fc {
            border-radius: 11px !important;
            border: 1.5px solid #e5e7eb !important;
            padding: 11px 14px !important;
            font-size: .89rem !important; color: #374151 !important;
            background: #fafbff !important; width: 100%; display: block;
            transition: all .25s !important;
        }
        .fc:focus {
            border-color: #6c63ff !important; background: #fff !important;
            box-shadow: 0 0 0 3px rgba(108,99,255,.12) !important;
            outline: none !important;
        }
        .fc::placeholder { color: #c1c9d2 !important; font-size: .84rem !important; }

        select.fc {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236c63ff' d='M6 8L1 3h10z'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 14px center !important;
            padding-right: 36px !important; cursor: pointer;
        }

        .fhint { font-size: .73rem; color: #9ca3af; margin-top: 5px; display: flex; align-items: center; gap: 4px; }

        /* ── Referral Box ── */
        .ref-box {
            background: linear-gradient(135deg, #f5f3ff, #ede9fe);
            border: 1.5px solid rgba(108,99,255,.25);
            border-radius: 14px; padding: 20px;
            margin-bottom: 24px;
            display: flex; align-items: flex-start; gap: 14px;
        }
        .ref-box-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: linear-gradient(135deg, #6c63ff, #574fd6);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 16px; flex-shrink: 0;
        }
        .ref-box h6 { color: #4c1d95; font-weight: 700; margin: 0 0 4px; font-size: .88rem; }
        .ref-box p  { color: #6b7280; margin: 0; font-size: .8rem; line-height: 1.5; }

        /* ── Submit Button ── */
        .btn-submit {
            background: linear-gradient(135deg, #6c63ff, #574fd6);
            border: none; color: #fff; padding: 14px 28px;
            border-radius: 13px; font-size: .95rem; font-weight: 700;
            width: 100%; cursor: pointer; transition: all .3s;
            box-shadow: 0 6px 20px rgba(108,99,255,.4);
            display: flex; align-items: center; justify-content: center; gap: 8px;
            position: relative; overflow: hidden;
        }
        .btn-submit::before {
            content: ''; position: absolute;
            top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.12), transparent);
            transition: left .5s;
        }
        .btn-submit:hover::before { left: 100%; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(108,99,255,.5); }
        .btn-submit:disabled { opacity: .7; transform: none; cursor: not-allowed; }

        .foot-note {
            text-align: center; color: rgba(255,255,255,.4);
            font-size: .78rem; margin-top: 20px;
        }

        @media (max-width: 576px) {
            .card-bdy { padding: 20px 18px; }
            .reg-hero { padding: 28px 20px; }
        }
    </style>
</head>
<body>

    <!-- SweetAlert Messages -->
    <?php if (!empty($_SESSION['reg_error'])): ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        Swal.fire({
            icon: 'error', title: 'Registration Failed!',
            text: '<?php echo e($_SESSION['reg_error']); ?>',
            confirmButtonColor: '#6c63ff',
            customClass: { popup:'rounded-4', confirmButton:'rounded-3' }
        });
    });
    </script>
    <?php unset($_SESSION['reg_error']); endif; ?>

    <?php if (!empty($_SESSION['reg_success'])): ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        Swal.fire({
            icon: 'success', title: 'Registered! 🎉',
            html: '<?php echo e($_SESSION['reg_success']); ?><br><small style="color:#9ca3af">Check your email for confirmation.</small>',
            confirmButtonColor: '#6c63ff',
            customClass: { popup:'rounded-4', confirmButton:'rounded-3' }
        });
    });
    </script>
    <?php unset($_SESSION['reg_success']); endif; ?>

    <div class="container" style="max-width:860px;">

        <!-- ✅ Hero -->
        <div class="reg-hero">
            <div class="icon-wrap"><i class="fas fa-running"></i></div>
            <h1>Runner Registration</h1>
            <p>Fill in your details to register for the event</p>
            <div class="hero-badges">
                <span class="hero-badge"><i class="fas fa-shield-alt me-1"></i>Secure</span>
                <span class="hero-badge"><i class="fas fa-bolt me-1"></i>Instant Confirmation</span>
                <span class="hero-badge"><i class="fas fa-ticket-alt me-1"></i>Open Registration</span>
            </div>
        </div>
        <!-- /reg-hero -->

        <!-- ✅ BACK BUTTON — hero ke baad, reg-card se pehle -->
        <div style="max-width:820px; margin: 0 auto;">
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>

        <!-- ✅ Form Card -->
        <div class="reg-card">
            <div class="card-hdr">
                <div class="hdr-icon"><i class="fas fa-clipboard-list"></i></div>
                <div>
                    <h5>Registration Form</h5>
                    <p>All fields marked with * are required</p>
                </div>
            </div>

            <div class="card-bdy">

                <!-- Referral Info Box -->
                <div class="ref-box">
                    <div class="ref-box-icon"><i class="fas fa-tag"></i></div>
                    <div>
                        <h6>Referral Code Required</h6>
                        <p>You need a valid referral code from your volunteer to complete registration. Contact your volunteer to get a code.</p>
                    </div>
                </div>

                <form method="POST" id="regForm" autocomplete="off" novalidate>

                    <!-- Personal Info -->
                    <div class="sec-div">
                        <span><i class="fas fa-user me-1"></i>Personal Information</span>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="fl"><i class="fas fa-user"></i>Full Name <span class="req">*</span></label>
                            <input type="text" id="runner_name" name="runner_name" class="fc"
                                   placeholder="Enter full name" maxlength="150" required/>
                        </div>
                        <div class="col-md-6">
                            <label class="fl"><i class="fas fa-envelope"></i>Email Address <span class="req">*</span></label>
                            <input type="email" id="email" name="email" class="fc"
                                   placeholder="your@email.com" required/>
                        </div>
                    </div>

                    <div class="row g-4 mt-1">
                        <div class="col-md-6">
                            <label class="fl"><i class="fas fa-phone"></i>Phone Number <span class="req">*</span></label>
                            <input type="tel" id="phone" name="phone" class="fc"
                                   placeholder="10–15 digit number" maxlength="10" required/>
                            <div class="fhint"><i class="fas fa-info-circle"></i>Numbers only, 10 digits</div>
                        </div>
                        <div class="col-md-6">
                            <label class="fl"><i class="fas fa-venus-mars"></i>Gender <span class="req">*</span></label>
                            <select name="gender" class="fc" required>
                                <option value="">── Select Gender ──</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-4 mt-1">
                        <div class="col-md-6">
                            <label class="fl"><i class="fas fa-calendar-alt"></i>Date of Birth <span class="req">*</span></label>
                            <input type="date" name="dob" class="fc" required
                                   max="<?php echo date('Y-m-d', strtotime('-5 years')); ?>"/>
                        </div>
                        <div class="col-md-6">
                            <label class="fl"><i class="fas fa-tint"></i>Blood Group <span class="req">*</span></label>
                            <select name="blood_group" class="fc" required>
                                <option value="">── Select Blood Group ──</option>
                                <option>A+</option><option>A-</option>
                                <option>B+</option><option>B-</option>
                                <option>AB+</option><option>AB-</option>
                                <option>O+</option><option>O-</option>
                            </select>
                        </div>
                    </div>

                    <!-- Event Details -->
                    <div class="sec-div">
                        <span><i class="fas fa-flag-checkered me-1"></i>Event Details</span>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="fl"><i class="fas fa-running"></i>Event Category <span class="req">*</span></label>
                            <select name="event" class="fc" required>
                                <option value="">── Select Event ──</option>
                                <option value="5 KM">5 KM </option>
                                <option value="10 KM">10 KM </option>
                                <option value="21.9 KM">21.9 KM</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="fl"><i class="fas fa-tshirt"></i>T-Shirt Size <span class="req">*</span></label>
                            <select name="tshirt_size" class="fc" required>
                                <option value="">── Select Size ──</option>
                                <option value="XS">XS — Extra Small</option>
                                <option value="S">S — Small</option>
                                <option value="M">M — Medium</option>
                                <option value="L">L — Large</option>
                                <option value="XL">XL — Extra Large</option>
                                <option value="XXL">XXL — Double XL</option>
                            </select>
                        </div>
                    </div>

                    <!-- Emergency & Medical -->
                    <div class="sec-div">
                        <span><i class="fas fa-heartbeat me-1"></i>Emergency & Medical</span>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="fl"><i class="fas fa-phone-alt"></i>Emergency Contact <span class="req">*</span></label>
                            <input type="tel" name="emergency_contact" class="fc"
                                   placeholder="Emergency phone number" maxlength="10" required/>
                            <div class="fhint"><i class="fas fa-info-circle"></i>Contact in case of emergency</div>
                        </div>
                        <div class="col-md-6">
                            <label class="fl"><i class="fas fa-notes-medical"></i>Medical Conditions</label>
                            <input type="text" name="medical_info" class="fc"
                                   placeholder="None / Diabetes / Asthma etc."
                                   value="None" maxlength="255"/>
                            <div class="fhint"><i class="fas fa-info-circle"></i>Leave "None" if no conditions</div>
                        </div>
                    </div>

                    <!-- Referral Code -->
                    <div class="sec-div">
                        <span><i class="fas fa-tag me-1"></i>Referral Code</span>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="fl"><i class="fas fa-hashtag"></i>Referral Code <span class="req">*</span></label>
                            <input type="text" id="referral_code" name="referral_code" class="fc"
                                   placeholder="e.g. AB12CD34" maxlength="8"
                                   style="text-transform:uppercase;letter-spacing:2px;font-weight:700;" required/>
                            <div class="fhint"><i class="fas fa-info-circle"></i>8-character code from your volunteer</div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="mt-4">
                        <button type="button" id="submitBtn" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Registration
                        </button>
                    </div>

                </form>
            </div>
        </div>
        <!-- /reg-card -->

        <div class="foot-note">
            &copy; <?php echo date('Y'); ?> — Runner Registration System
        </div>

    </div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

document.getElementById('referral_code').addEventListener('input', function(){
    this.value = this.value.toUpperCase();
});

document.getElementById('submitBtn').addEventListener('click', function(){

    const name  = document.getElementById('runner_name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const ref   = document.getElementById('referral_code').value.trim();
    const sel   = (n) => document.querySelector('[name=' + n + ']').value;

    const warn = (title, text) => Swal.fire({
        icon: 'warning', title, text,
        confirmButtonColor: '#6c63ff',
        customClass: { popup:'rounded-4', confirmButton:'rounded-3' }
    });

    if (!name || !email || !phone || !sel('gender') || !sel('dob') ||
        !sel('blood_group') || !sel('event') || !sel('tshirt_size') ||
        !sel('emergency_contact') || !ref) {
        return warn('Missing Fields', 'Please fill in all required fields.');
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
        return warn('Invalid Email', 'Please enter a valid email address.');

    if (!/^[0-9]{10,15}$/.test(phone))
        return warn('Invalid Phone', 'Phone must be 10–15 digits.');

    if (ref.length !== 8)
        return warn('Invalid Code', 'Referral code must be exactly 8 characters.');

    Swal.fire({
        title: 'Confirm Registration?',
        html: 'Register <strong>' + name + '</strong><br>for <strong>' + sel('event') + '</strong>?',
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#6c63ff', cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-paper-plane me-1"></i>Yes, Register!',
        cancelButtonText: 'Review Again',
        customClass: { popup:'rounded-4', confirmButton:'rounded-3', cancelButton:'rounded-3' }
    }).then(r => {
        if (r.isConfirmed) {
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            btn.disabled = true;
            document.getElementById('regForm').submit();
        }
    });
});
</script>
</body>
</html>
