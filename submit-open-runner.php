<?php
ob_start();
include_once('includes/config.php');

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$success = '';
$errors  = [];
$old     = [];

// ── Categories & Sizes
$categories  = ['3K Run', '5K Run', '10K Run', '21K Run', 'Half Marathon', 'Full Marathon'];
$tshirtSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
$bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Sanitize inputs
    $r_name      = trim($_POST['r_name']      ?? '');
    $r_email     = trim($_POST['r_email']     ?? '');
    $r_contact   = trim($_POST['r_contact']   ?? '');
    $r_gender    = trim($_POST['r_gender']    ?? '');
    $r_dob       = trim($_POST['r_dob']       ?? '');
    $r_bdgp      = trim($_POST['r_bdgp']      ?? '');
    $r_catgry    = trim($_POST['r_catgry']    ?? '');
    $r_tshirt_sz = trim($_POST['r_tshirt_sz'] ?? '');
    $r_emrg_con  = trim($_POST['r_emrg_con']  ?? '');
    $r_med_dt    = trim($_POST['r_med_dt']    ?? '');
    $referral    = trim($_POST['referral_code'] ?? '');

    $old = compact('r_name','r_email','r_contact','r_gender',
                   'r_dob','r_bdgp','r_catgry','r_tshirt_sz',
                   'r_emrg_con','r_med_dt','referral');

    // ── Validation
    if (empty($r_name))                     $errors[] = 'Full name is required.';
    elseif (strlen($r_name) < 3)            $errors[] = 'Name must be at least 3 characters.';

    if (empty($r_email))                    $errors[] = 'Email address is required.';
    elseif (!filter_var($r_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';

    if (empty($r_contact))                  $errors[] = 'Contact number is required.';
    elseif (!preg_match('/^[6-9]\d{9}$/', $r_contact)) $errors[] = 'Enter a valid 10-digit mobile number.';

    if (empty($r_gender))                   $errors[] = 'Please select your gender.';
    if (empty($r_dob))                      $errors[] = 'Date of birth is required.';
    if (empty($r_bdgp))                     $errors[] = 'Please select your blood group.';
    if (empty($r_catgry))                   $errors[] = 'Please select a run category.';
    if (empty($r_tshirt_sz))                $errors[] = 'Please select a T-shirt size.';

    if (empty($r_emrg_con))                 $errors[] = 'Emergency contact is required.';
    elseif (!preg_match('/^[6-9]\d{9}$/', $r_emrg_con)) $errors[] = 'Enter a valid emergency contact number.';

    if ($r_contact === $r_emrg_con)         $errors[] = 'Emergency contact must be different from your contact.';

    // ── DOB age check (must be 5+)
    if (!empty($r_dob)) {
        $age = (int)date_diff(date_create($r_dob), date_create('today'))->y;
        if ($age < 5)  $errors[] = 'Minimum age is 5 years.';
        if ($age > 90) $errors[] = 'Please enter a valid date of birth.';
    }

    // ── Check duplicate email
    if (empty($errors)) {
        $chk = $con->prepare("SELECT id FROM open_registrations WHERE r_email = ?");
        $chk->bind_param('s', $r_email);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0)
            $errors[] = 'This email is already registered.';
        $chk->close();
    }

    // ── Check duplicate contact
    if (empty($errors)) {
        $chk2 = $con->prepare("SELECT id FROM open_registrations WHERE r_contact = ?");
        $chk2->bind_param('s', $r_contact);
        $chk2->execute();
        if ($chk2->get_result()->num_rows > 0)
            $errors[] = 'This contact number is already registered.';
        $chk2->close();
    }

    // ── Referral code → referrer_id
    $referrer_id = null;
    if (!empty($referral)) {
        $ref = $con->prepare("SELECT id FROM open_registrations WHERE referral_code = ?");
        $ref->bind_param('s', $referral);
        $ref->execute();
        $refRow = $ref->get_result()->fetch_assoc();
        $ref->close();
        if ($refRow) {
            $referrer_id = (int)$refRow['id'];
        } else {
            $errors[] = 'Invalid referral code.';
        }
    }

    // ── Generate unique referral code for this user
    $myReferralCode = strtoupper(substr(md5($r_email . time()), 0, 8));

    // ── Capture IP & User Agent
    $ip_addr    = $_SERVER['HTTP_CF_CONNECTING_IP']
               ?? $_SERVER['HTTP_X_FORWARDED_FOR']
               ?? $_SERVER['REMOTE_ADDR']
               ?? '';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    // ── INSERT
    if (empty($errors)) {
        $ins = $con->prepare("
            INSERT INTO open_registrations
                (r_name, r_email, r_contact, r_gender, r_dob,
                 r_bdgp, r_catgry, r_tshirt_sz, r_emrg_con, r_med_dt,
                 referral_code, referrer_id, ip_addr, user_agent)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $ins->bind_param(
            'sssssssssssiss',
            $r_name, $r_email, $r_contact, $r_gender, $r_dob,
            $r_bdgp, $r_catgry, $r_tshirt_sz, $r_emrg_con, $r_med_dt,
            $myReferralCode, $referrer_id, $ip_addr, $user_agent
        );

        if ($ins->execute()) {
            $success = $myReferralCode;
            $old     = [];
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
        $ins->close();
    }
}

// Pre-fill referral from URL
$refFromUrl = trim($_GET['ref'] ?? '');

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Runner Registration</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>

    <style>
        * { font-family: 'Inter', sans-serif; }

        body {
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
            padding: 30px 16px 60px;
        }

        /* ── Hero Banner */
        .hero-banner {
            background: linear-gradient(135deg, #6c63ff, #f50057);
            border-radius: 20px;
            padding: 36px 30px;
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 16px 48px rgba(108,99,255,0.4);
        }
        .hero-banner::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }
        .hero-banner::after {
            content: '';
            position: absolute;
            bottom: -40px; left: -40px;
            width: 150px; height: 150px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }
        .hero-banner h1 {
            color: #fff; font-size: 2rem;
            font-weight: 800; margin: 0;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .hero-banner p {
            color: rgba(255,255,255,0.75);
            font-size: 0.92rem; margin: 8px 0 0;
        }
        .hero-icon {
            width: 70px; height: 70px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex; align-items: center;
            justify-content: center;
            font-size: 30px; color: #fff;
            margin: 0 auto 16px;
            border: 2px solid rgba(255,255,255,0.25);
        }

        /* ── Form Card */
        .form-card {
            background: rgba(255,255,255,0.97);
            border-radius: 20px;
            padding: 36px 36px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        /* ── Section titles */
        .section-title {
            font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1.2px;
            color: #6c63ff; margin-bottom: 18px;
            display: flex; align-items: center; gap: 8px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f3f0ff;
        }

        /* ── Inputs */
        .form-label {
            font-size: 0.78rem; font-weight: 700;
            color: #374151; margin-bottom: 6px;
            text-transform: uppercase; letter-spacing: 0.4px;
        }
        .form-label .req { color: #f50057; margin-left: 2px; }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1.5px solid #e5e7eb;
            padding: 10px 14px;
            font-size: 0.88rem;
            color: #1f2937;
            transition: all 0.2s;
            background: #fafafa;
        }
        .form-control:focus, .form-select:focus {
            border-color: #6c63ff;
            box-shadow: 0 0 0 3px rgba(108,99,255,0.12);
            background: #fff;
        }
        .form-control.is-invalid, .form-select.is-invalid {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.08);
        }
        .form-control::placeholder { color: #9ca3af; }
        .input-icon-wrap { position: relative; }
        .input-icon-wrap .fa {
            position: absolute; left: 12px; top: 50%;
            transform: translateY(-50%); color: #9ca3af;
            font-size: 14px; pointer-events: none;
        }
        .input-icon-wrap .form-control,
        .input-icon-wrap .form-select { padding-left: 36px; }

        /* ── Gender Selector */
        .gender-options { display: flex; gap: 10px; }
        .gender-opt { flex: 1; }
        .gender-opt input[type="radio"] { display: none; }
        .gender-opt label {
            display: flex; align-items: center;
            justify-content: center; gap: 8px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px; padding: 10px;
            cursor: pointer; font-size: 0.84rem;
            font-weight: 600; color: #6b7280;
            transition: all 0.2s; background: #fafafa;
        }
        .gender-opt input:checked + label {
            border-color: #6c63ff;
            background: #f5f3ff; color: #6c63ff;
            box-shadow: 0 0 0 3px rgba(108,99,255,0.12);
        }
        .gender-opt.female input:checked + label {
            border-color: #f50057;
            background: #fff1f5; color: #f50057;
            box-shadow: 0 0 0 3px rgba(245,0,87,0.1);
        }
        .gender-opt.other input:checked + label {
            border-color: #7c3aed;
            background: #f5f3ff; color: #7c3aed;
        }

        /* ── Size Selector */
        .size-options { display: flex; gap: 8px; flex-wrap: wrap; }
        .size-opt { }
        .size-opt input[type="radio"] { display: none; }
        .size-opt label {
            display: flex; align-items: center; justify-content: center;
            width: 46px; height: 46px;
            border: 1.5px solid #e5e7eb; border-radius: 10px;
            cursor: pointer; font-size: 0.82rem; font-weight: 700;
            color: #6b7280; transition: all 0.2s; background: #fafafa;
        }
        .size-opt input:checked + label {
            border-color: #6c63ff; background: #6c63ff;
            color: #fff; box-shadow: 0 4px 12px rgba(108,99,255,0.35);
        }

        /* ── Submit Button */
        .btn-submit {
            background: linear-gradient(135deg, #6c63ff, #f50057);
            color: #fff; border: none; border-radius: 12px;
            padding: 14px 32px; font-size: 1rem; font-weight: 700;
            width: 100%; letter-spacing: 0.5px;
            transition: all 0.3s;
            box-shadow: 0 8px 24px rgba(108,99,255,0.35);
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(108,99,255,0.45);
            color: #fff;
        }
        .btn-submit:active { transform: translateY(0); }

        /* ── Alert */
        .alert-custom {
            border-radius: 12px; padding: 14px 18px;
            font-size: 0.85rem; border: none;
        }
        .alert-danger-custom {
            background: #fef2f2; color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        .alert-success-custom {
            background: #f0fdf4; color: #166534;
            border-left: 4px solid #22c55e;
        }
        .alert-custom ul { margin: 8px 0 0; padding-left: 18px; }
        .alert-custom ul li { margin-bottom: 4px; }

        /* ── Success Card */
        .success-card {
            text-align: center; padding: 50px 30px;
        }
        .success-icon {
            width: 90px; height: 90px; border-radius: 50%;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            display: flex; align-items: center;
            justify-content: center; font-size: 38px;
            color: #fff; margin: 0 auto 20px;
            box-shadow: 0 12px 30px rgba(34,197,94,0.35);
        }
        .success-card h3 { color: #1f2937; font-weight: 800; font-size: 1.6rem; }
        .success-card p  { color: #6b7280; font-size: 0.9rem; }
        .referral-box {
            background: linear-gradient(135deg, #f5f3ff, #ede9fe);
            border: 2px dashed #6c63ff;
            border-radius: 14px; padding: 18px 24px;
            margin: 20px 0;
            display: inline-block;
        }
        .referral-box .label { font-size: 0.72rem; font-weight: 700; color: #7c3aed; text-transform: uppercase; letter-spacing: 1px; }
        .referral-box .code  { font-size: 1.8rem; font-weight: 900; color: #6c63ff; letter-spacing: 4px; margin-top: 4px; }
        .referral-box .hint  { font-size: 0.75rem; color: #9ca3af; margin-top: 6px; }

        /* ── Steps Indicator */
        .steps { display: flex; gap: 0; margin-bottom: 28px; }
        .step {
            flex: 1; text-align: center; padding: 10px 6px;
            font-size: 0.72rem; font-weight: 700;
            color: #9ca3af; position: relative;
            border-bottom: 2px solid #e5e7eb;
            text-transform: uppercase; letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        .step.active { color: #6c63ff; border-bottom-color: #6c63ff; }
        .step .step-num {
            width: 24px; height: 24px; border-radius: 50%;
            background: #e5e7eb; color: #9ca3af;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem; font-weight: 800;
            margin: 0 auto 4px;
        }
        .step.active .step-num { background: #6c63ff; color: #fff; }

        /* ── Responsive */
        @media (max-width: 576px) {
            .form-card { padding: 20px 16px; }
            .hero-banner h1 { font-size: 1.4rem; }
            .gender-options { flex-wrap: wrap; }
            .gender-opt { min-width: calc(50% - 5px); }
        }
    </style>
</head>
<body>
<div class="container" style="max-width:780px;">

    <!-- Hero Banner -->
    <div class="hero-banner">
        <div class="hero-icon">
            <i class="fas fa-running"></i>
        </div>
        <h1>Runner Registration</h1>
        <p><i class="fas fa-map-marker-alt me-1"></i> Register yourself for the upcoming race event</p>
    </div>

    <?php if ($success): ?>
    <!-- ── Success State -->
    <div class="form-card">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h3>Registration Successful! 🎉</h3>
            <p>You have been successfully registered as a runner.<br>Please save your referral code below.</p>

            <div class="referral-box">
                <div class="label"><i class="fas fa-tag me-1"></i> Your Referral Code</div>
                <div class="code" id="refCode"><?php echo e($success); ?></div>
                <div class="hint">Share this code with friends to refer them!</div>
            </div>

            <div class="d-flex gap-3 justify-content-center mt-4 flex-wrap">
                <button onclick="copyCode()" class="btn btn-outline-primary" style="border-radius:10px;font-weight:600;">
                    <i class="fas fa-copy me-1"></i> Copy Code
                </button>
                <a href="register.php" class="btn btn-submit" style="width:auto;padding:10px 24px;">
                    <i class="fas fa-user-plus me-1"></i> Register Another
                </a>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ── Form -->
    <div class="form-card">

        <!-- Steps -->
        <div class="steps mb-4">
            <div class="step active"><div class="step-num">1</div>Personal Info</div>
            <div class="step active"><div class="step-num">2</div>Race Details</div>
            <div class="step active"><div class="step-num">3</div>Emergency</div>
        </div>

        <!-- Errors -->
        <?php if (!empty($errors)): ?>
        <div class="alert alert-custom alert-danger-custom mb-4">
            <strong><i class="fas fa-exclamation-circle me-1"></i> Please fix the following errors:</strong>
            <ul>
                <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="register.php" novalidate>

            <!-- ═══ SECTION 1: Personal Info ═══ -->
            <div class="section-title">
                <i class="fas fa-user"></i> Personal Information
            </div>

            <div class="row g-3 mb-4">

                <!-- Name -->
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="req">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="fa fa-user"></i>
                        <input type="text" name="r_name" class="form-control <?php echo (in_array('Full name is required.', $errors) || in_array('Name must be at least 3 characters.', $errors)) ? 'is-invalid' : ''; ?>"
                               placeholder="Enter your full name"
                               value="<?php echo e($old['r_name'] ?? ''); ?>"
                               maxlength="120" required/>
                    </div>
                </div>

                <!-- Email -->
                <div class="col-md-6">
                    <label class="form-label">Email Address <span class="req">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="fa fa-envelope"></i>
                        <input type="email" name="r_email" class="form-control"
                               placeholder="yourname@email.com"
                               value="<?php echo e($old['r_email'] ?? ''); ?>"
                               maxlength="120" required/>
                    </div>
                </div>

                <!-- Contact -->
                <div class="col-md-6">
                    <label class="form-label">Mobile Number <span class="req">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="fa fa-phone"></i>
                        <input type="tel" name="r_contact" class="form-control"
                               placeholder="10-digit mobile number"
                               value="<?php echo e($old['r_contact'] ?? ''); ?>"
                               maxlength="10" pattern="[6-9]\d{9}" required/>
                    </div>
                </div>

                <!-- DOB -->
                <div class="col-md-6">
                    <label class="form-label">Date of Birth <span class="req">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="fa fa-calendar"></i>
                        <input type="date" name="r_dob" class="form-control"
                               value="<?php echo e($old['r_dob'] ?? ''); ?>"
                               max="<?php echo date('Y-m-d', strtotime('-5 years')); ?>"
                               required/>
                    </div>
                </div>

                <!-- Blood Group -->
                <div class="col-md-6">
                    <label class="form-label">Blood Group <span class="req">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="fa fa-tint"></i>
                        <select name="r_bdgp" class="form-select" required>
                            <option value="">Select Blood Group</option>
                            <?php foreach ($bloodGroups as $bg): ?>
                            <option value="<?php echo e($bg); ?>"
                                <?php echo (($old['r_bdgp'] ?? '') === $bg) ? 'selected' : ''; ?>>
                                <?php echo e($bg); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Gender -->
                <div class="col-md-6">
                    <label class="form-label">Gender <span class="req">*</span></label>
                    <div class="gender-options">
                        <div class="gender-opt male">
                            <input type="radio" name="r_gender" id="gMale" value="Male"
                                <?php echo (($old['r_gender'] ?? '') === 'Male') ? 'checked' : ''; ?>/>
                            <label for="gMale"><i class="fas fa-mars"></i> Male</label>
                        </div>
                        <div class="gender-opt female">
                            <input type="radio" name="r_gender" id="gFemale" value="Female"
                                <?php echo (($old['r_gender'] ?? '') === 'Female') ? 'checked' : ''; ?>/>
                            <label for="gFemale"><i class="fas fa-venus"></i> Female</label>
                        </div>
                        <div class="gender-opt other">
                            <input type="radio" name="r_gender" id="gOther" value="Other"
                                <?php echo (($old['r_gender'] ?? '') === 'Other') ? 'checked' : ''; ?>/>
                            <label for="gOther"><i class="fas fa-genderless"></i> Other</label>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ═══ SECTION 2: Race Details ═══ -->
            <div class="section-title">
                <i class="fas fa-flag-checkered"></i> Race Details
            </div>

            <div class="row g-3 mb-4">

                <!-- Category -->
                <div class="col-md-6">
                    <label class="form-label">Run Category <span class="req">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="fa fa-running"></i>
                        <select name="r_catgry" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo e($cat); ?>"
                                <?php echo (($old['r_catgry'] ?? '') === $cat) ? 'selected' : ''; ?>>
                                <?php echo e($cat); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- T-Shirt Size -->
                <div class="col-md-6">
                    <label class="form-label">T-Shirt Size <span class="req">*</span></label>
                    <div class="size-options">
                        <?php foreach ($tshirtSizes as $sz): ?>
                        <div class="size-opt">
                            <input type="radio" name="r_tshirt_sz"
                                   id="sz<?php echo $sz; ?>" value="<?php echo $sz; ?>"
                                   <?php echo (($old['r_tshirt_sz'] ?? '') === $sz) ? 'checked' : ''; ?>/>
                            <label for="sz<?php echo $sz; ?>"><?php echo $sz; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <!-- ═══ SECTION 3: Emergency & Medical ═══ -->
            <div class="section-title">
                <i class="fas fa-heartbeat"></i> Emergency & Medical
            </div>

            <div class="row g-3 mb-4">

                <!-- Emergency Contact -->
                <div class="col-md-6">
                    <label class="form-label">Emergency Contact <span class="req">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="fa fa-phone-alt"></i>
                        <input type="tel" name="r_emrg_con" class="form-control"
                               placeholder="Emergency contact number"
                               value="<?php echo e($old['r_emrg_con'] ?? ''); ?>"
                               maxlength="10" pattern="[6-9]\d{9}" required/>
                    </div>
                    <small class="text-muted" style="font-size:.72rem;">Must be different from your number</small>
                </div>

                <!-- Medical Details -->
                <div class="col-md-6">
                    <label class="form-label">Medical Conditions <span style="color:#9ca3af;font-weight:500;">(Optional)</span></label>
                    <div class="input-icon-wrap">
                        <i class="fa fa-notes-medical"></i>
                        <input type="text" name="r_med_dt" class="form-control"
                               placeholder="Any medical conditions or allergies..."
                               value="<?php echo e($old['r_med_dt'] ?? ''); ?>"
                               maxlength="255"/>
                    </div>
                </div>

            </div>

            <!-- ═══ SECTION 4: Referral ═══ -->
            <div class="section-title">
                <i class="fas fa-share-alt"></i> Referral (Optional)
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Referral Code</label>
                    <div class="input-icon-wrap">
                        <i class="fa fa-tag"></i>
                        <input type="text" name="referral_code" class="form-control"
                               placeholder="Enter referral code (if any)"
                               value="<?php echo e($old['referral'] ?? $refFromUrl); ?>"
                               maxlength="40" style="text-transform:uppercase;letter-spacing:2px;"/>
                    </div>
                    <small class="text-muted" style="font-size:.72rem;">
                        Got a referral code from a friend? Enter it here.
                    </small>
                </div>
            </div>

            <!-- ═══ TERMS + SUBMIT ═══ -->
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="termsCheck" required/>
                <label class="form-check-label" for="termsCheck" style="font-size:.84rem;color:#374151;">
                    I agree to the <a href="#" style="color:#6c63ff;font-weight:600;">Terms & Conditions</a>
                    and confirm all information is accurate.
                </label>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-paper-plane me-2"></i>
                Complete Registration
            </button>

        </form>
    </div>
    <?php endif; ?>

    <!-- Footer note -->
    <div class="text-center mt-4" style="color:rgba(255,255,255,0.4);font-size:.78rem;">
        <i class="fas fa-shield-alt me-1"></i>
        Your data is safe and will only be used for race event purposes.
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Uppercase referral input
document.querySelector('input[name="referral_code"]')?.addEventListener('input', function () {
    this.value = this.value.toUpperCase();
});

// ── Terms checkbox validation
document.querySelector('form')?.addEventListener('submit', function (e) {
    const terms = document.getElementById('termsCheck');
    if (!terms.checked) {
        e.preventDefault();
        terms.closest('.form-check').querySelector('label').style.color = '#dc2626';
        terms.style.borderColor = '#dc2626';
        terms.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

// ── Copy referral code
function copyCode() {
    var code = document.getElementById('refCode')?.innerText || '';
    navigator.clipboard.writeText(code).then(function () {
        var btn = document.querySelector('[onclick="copyCode()"]');
        btn.innerHTML = '<i class="fas fa-check me-1"></i> Copied!';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-outline-primary');
        setTimeout(function () {
            btn.innerHTML = '<i class="fas fa-copy me-1"></i> Copy Code';
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
        }, 2000);
    });
}

// ── Submit loading state
document.getElementById('submitBtn')?.addEventListener('click', function () {
    var form = document.querySelector('form');
    if (form.checkValidity()) {
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
        this.disabled = true;
        setTimeout(() => { this.disabled = false; this.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Complete Registration'; }, 8000);
    }
});
</script>
</body>
</html>
