<?php
session_start();
include_once('includes/config.php');

if (empty($_SESSION['id'])) {
    header('location:logout.php');
    exit();
}

$bid     = isset($_GET['bid'])     ? mysqli_real_escape_string($con, $_GET['bid'])     : '';
$uid     = isset($_GET['uid'])     ? mysqli_real_escape_string($con, $_GET['uid'])     : '';
$pre_pay = isset($_GET['payment']) ? mysqli_real_escape_string($con, $_GET['payment']) : '';

$allowed_pay = ['online', 'offline', 'complementary'];
if (!in_array($pre_pay, $allowed_pay)) $pre_pay = '';

$insert_success = false;
$insert_error   = '';

// ── INSERT LOGIC ──────────────────────────────────
if (isset($_POST['submit'])) {

    $sno      = mysqli_real_escape_string($con, $_POST['sno']      ?? '');
    $rname    = mysqli_real_escape_string($con, $_POST['rname']    ?? '');
    $contact  = mysqli_real_escape_string($con, $_POST['contact']  ?? '');
    $dob      = mysqli_real_escape_string($con, $_POST['dob']      ?? '');
    $gender   = mysqli_real_escape_string($con, $_POST['gender']   ?? '');
    $bg       = mysqli_real_escape_string($con, $_POST['bg']       ?? '');
    $email    = mysqli_real_escape_string($con, $_POST['email']    ?? '');
    $econtact = mysqli_real_escape_string($con, $_POST['econtact'] ?? '');
    $mdc      = mysqli_real_escape_string($con, $_POST['mdc']      ?? '');
    $tss      = isset($_POST['tss']) ? implode(', ', array_map('htmlspecialchars', $_POST['tss'])) : '';
    $rct      = isset($_POST['rct']) ? implode(', ', array_map('htmlspecialchars', $_POST['rct'])) : '';
    $pay_st   = mysqli_real_escape_string($con, $_POST['payment_status'] ?? '');

    // ✅ Fee field
    $fee = mysqli_real_escape_string($con, $_POST['fee'] ?? '0');
    if (!is_numeric($fee) || (float)$fee < 0) $fee = '0';

    if (!in_array($pay_st, $allowed_pay)) $pay_st = '';

    $msg = mysqli_query($con,
        "INSERT INTO `runner`
         (`r_id`,`u_id`,`b_id`,`r_srn`,`r_name`,`r_contact`,`r_dob`,
          `r_gender`,`r_bdgp`,`r_email`,`r_catgry`,`r_tshirt_sz`,
          `r_emrg_con`,`r_med_dt`,`r_fee`,`r_payment_status`,`reg_dt`)
         VALUES
         (NULL,'$uid','$bid','$sno','$rname','$contact','$dob',
          '$gender','$bg','$email','$rct','$tss','$econtact','$mdc',
          '$fee','$pay_st',NOW())"
    );

    if ($msg) {
        $insert_success = true;
    } else {
        $insert_error = mysqli_error($con);
    }
}

// ── SERIAL NUMBER LOGIC ───────────────────────────
$r_sno = '';
$brnrc = mysqli_query($con,
    "SELECT r_srn FROM runner WHERE b_id='$bid' ORDER BY r_id DESC LIMIT 1"
);
if ($brnrc && mysqli_num_rows($brnrc) == 0) {
    $bresult = mysqli_query($con, "SELECT b_srnstart FROM booklet WHERE id='$bid'");
    if ($bresult && $row1 = mysqli_fetch_assoc($bresult)) {
        $r_sno = $row1['b_srnstart'];
    }
} else {
    if ($brnrc && $row = mysqli_fetch_assoc($brnrc)) {
        $r_sno = intval($row['r_srn']) + 1;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Add Runner</title>

    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>

    <style>
        /* ── Pill option buttons ── */
        .opt-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .opt-group input[type="radio"],
        .opt-group input[type="checkbox"] {
            display: none;
        }
        .opt-item {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #f8f9fa;
            border: 1.5px solid #dee2e6;
            border-radius: 6px;
            padding: 6px 16px;
            cursor: pointer;
            font-size: 0.88rem;
            transition: all .18s;
            user-select: none;
            font-weight: 500;
        }
        .opt-item:hover {
            border-color: #0d6efd;
            background: #eef4ff;
            color: #0d6efd;
        }
        .opt-item.selected {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
            font-weight: 600;
        }

        /* ✅ Payment pill colors */
        .opt-item.pay-online.selected    { background:#10b981; border-color:#10b981; }
        .opt-item.pay-online:hover       { border-color:#10b981; background:#ecfdf5; color:#10b981; }
        .opt-item.pay-offline.selected   { background:#f59e0b; border-color:#f59e0b; }
        .opt-item.pay-offline:hover      { border-color:#f59e0b; background:#fffbeb; color:#f59e0b; }
        .opt-item.pay-comp.selected      { background:#8b5cf6; border-color:#8b5cf6; }
        .opt-item.pay-comp:hover         { border-color:#8b5cf6; background:#f5f3ff; color:#8b5cf6; }

        /* ── Section label ── */
        .sec-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6c757d;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e9ecef;
        }

        .form-section { margin-bottom: 1.4rem; }

        /* ── SR badge ── */
        .sr-badge {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.35);
            color: #fff;
            padding: 4px 14px;
            border-radius: 30px;
            font-size: 0.82rem;
            font-weight: 700;
        }

        /* ── Card header ── */
        .card-header-custom {
            background: #343a40;
            color: #fff;
            padding: 14px 20px;
            border-radius: 0.35rem 0.35rem 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }
        .card-header-custom h6 { margin: 0; font-size: 1rem; font-weight: 600; }

        .req { color: #dc3545; }

        /* ── Input group ── */
        .input-group-text { background: #f8f9fa; border-right: 0; }
        .input-group .form-control { border-left: 0; }
        .input-group .form-control:focus { border-color: #ced4da; box-shadow: none; }
        .input-group:focus-within .input-group-text,
        .input-group:focus-within .form-control {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13,110,253,.25);
        }

        /* ✅ Fee input highlight */
        .fee-group .input-group-text {
            background: linear-gradient(135deg,#d1fae5,#a7f3d0);
            border-color: #6ee7b7;
            color: #065f46;
            font-weight: 700;
            font-size: 0.95rem;
        }
        .fee-group .form-control {
            border-color: #6ee7b7;
            font-weight: 600;
            color: #065f46;
        }
        .fee-group .form-control:focus {
            border-color: #10b981 !important;
            box-shadow: 0 0 0 0.25rem rgba(16,185,129,.2) !important;
        }
        .fee-group.locked .input-group-text {
            background: #f3f4f6;
            border-color: #e5e7eb;
            color: #9ca3af;
        }
        .fee-group.locked .form-control {
            background: #f3f4f6;
            border-color: #e5e7eb;
            color: #9ca3af;
            cursor: not-allowed;
        }

        /* ── Submit button ── */
        .btn-submit-runner {
            background: #343a40;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 11px 0;
            width: 100%;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            transition: background .2s;
        }
        .btn-submit-runner:hover { background: #23272b; color: #fff; }

        .breadcrumb-item a { text-decoration: none; }

        /* ✅ Payment info chip */
        .pay-info-chip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 14px; border-radius: 30px;
            font-size: 0.82rem; font-weight: 600;
            margin-bottom: 14px;
        }
        .pay-info-online  { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
        .pay-info-offline { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
        .pay-info-comp    { background:#ede9fe; color:#5b21b6; border:1px solid #ddd6fe; }
        .pay-info-none    { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

        @media (max-width: 576px) {
            .opt-group { gap: 6px; }
            .opt-item  { padding: 5px 10px; font-size: 0.82rem; }
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

                <h1 class="mt-4">Manage Runner</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item">
                        <a href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="manage-runner.php">Manage Runner</a>
                    </li>
                    <li class="breadcrumb-item active">Register Runner</li>
                </ol>

                <div class="card mb-4">

                    <!-- Card Header -->
                    <div class="card-header-custom">
                        <h6>
                            <i class="fas fa-user-plus me-2"></i>Register Runner
                        </h6>
                        <span class="sr-badge">
                            <i class="fas fa-hashtag me-1"></i>Form SR No:&nbsp;
                            <strong><?php echo htmlspecialchars($r_sno); ?></strong>
                        </span>
                    </div>

                    <div class="card-body px-4 py-4">

                        <!-- ✅ Payment pre-selection chip -->
                        <?php if ($pre_pay === 'online'): ?>
                            <div class="pay-info-chip pay-info-online">
                                <i class="fas fa-credit-card"></i>
                                Payment Mode: <strong>Online</strong> (pre-selected)
                            </div>
                        <?php elseif ($pre_pay === 'offline'): ?>
                            <div class="pay-info-chip pay-info-offline">
                                <i class="fas fa-money-bill-wave"></i>
                                Payment Mode: <strong>Offline</strong> (pre-selected)
                            </div>
                        <?php elseif ($pre_pay === 'complementary'): ?>
                            <div class="pay-info-chip pay-info-comp">
                                <i class="fas fa-gift"></i>
                                Payment Mode: <strong>Complementary</strong> (pre-selected)
                            </div>
                        <?php else: ?>
                            <div class="pay-info-chip pay-info-none">
                                <i class="fas fa-exclamation-triangle"></i>
                                No payment mode pre-selected — please choose below.
                            </div>
                        <?php endif; ?>

                        <form
                            method="post"
                            name="runnerForm"
                            action="add-runner.php?uid=<?php echo urlencode($uid); ?>&bid=<?php echo urlencode($bid); ?>&payment=<?php echo urlencode($pre_pay); ?>"
                            onsubmit="return validateForm();"
                        >
                            <!-- Hidden SR No -->
                            <input type="hidden" name="sno" value="<?php echo htmlspecialchars($r_sno); ?>">

                            <!-- ══════════════════════════════
                                 Row 1 : Name + DOB
                            ══════════════════════════════ -->
                            <div class="row g-3 form-section">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">
                                        Runner Full Name <span class="req">*</span>
                                    </label>
                                    <input
                                        class="form-control"
                                        name="rname"
                                        type="text"
                                        placeholder="Enter runner full name"
                                        required
                                    />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">
                                        Date of Birth <span class="req">*</span>
                                    </label>
                                    <input
                                        class="form-control"
                                        name="dob"
                                        type="date"
                                        value="<?php echo date('Y-m-d'); ?>"
                                        required
                                    />
                                </div>
                            </div>

                            <!-- ══════════════════════════════
                                 Row 2 : Contact + Email
                            ══════════════════════════════ -->
                            <div class="row g-3 form-section">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">
                                        Contact Number <span class="req">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-phone fa-sm"></i>
                                        </span>
                                        <input
                                            class="form-control"
                                            name="contact"
                                            type="tel"
                                            placeholder="10-digit mobile number"
                                            pattern="[0-9]{10}"
                                            maxlength="10"
                                            title="10 digits only"
                                            required
                                        />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">
                                        Email Address
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-envelope fa-sm"></i>
                                        </span>
                                        <input
                                            class="form-control"
                                            name="email"
                                            type="email"
                                            placeholder="example@email.com"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- ══════════════════════════════
                                 Row 3 : Emergency + Medical
                            ══════════════════════════════ -->
                            <div class="row g-3 form-section">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">
                                        Emergency Contact <span class="req">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-phone-volume fa-sm"></i>
                                        </span>
                                        <input
                                            class="form-control"
                                            name="econtact"
                                            type="tel"
                                            placeholder="10-digit emergency number"
                                            pattern="[0-9]{10}"
                                            maxlength="10"
                                            title="10 digits only"
                                            required
                                        />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">
                                        Medical Condition
                                    </label>
                                    <input
                                        class="form-control"
                                        name="mdc"
                                        type="text"
                                        placeholder="e.g. Asthma, Diabetes or None"
                                    />
                                </div>
                            </div>

                            <!-- ══════════════════════════════
                                 Row 4 : Gender
                            ══════════════════════════════ -->
                            <div class="form-section">
                                <div class="sec-label">
                                    <i class="fas fa-venus-mars me-1"></i> Gender
                                    <span class="req">*</span>
                                </div>
                                <div class="opt-group" id="genderGroup">
                                    <label class="opt-item">
                                        <input type="radio" name="gender" value="Male">
                                        <i class="fas fa-mars fa-sm"></i> Male
                                    </label>
                                    <label class="opt-item">
                                        <input type="radio" name="gender" value="Female">
                                        <i class="fas fa-venus fa-sm"></i> Female
                                    </label>
                                    <label class="opt-item">
                                        <input type="radio" name="gender" value="Other">
                                        <i class="fas fa-genderless fa-sm"></i> Other
                                    </label>
                                </div>
                            </div>

                            <!-- ══════════════════════════════
                                 Row 5 : Blood Group
                            ══════════════════════════════ -->
                            <div class="form-section">
                                <div class="sec-label">
                                    <i class="fas fa-tint me-1"></i> Blood Group
                                    <span class="req">*</span>
                                </div>
                                <div class="opt-group" id="bgGroup">
                                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg_val): ?>
                                        <label class="opt-item">
                                            <input type="radio" name="bg" value="<?php echo $bg_val; ?>">
                                            <?php echo $bg_val; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- ══════════════════════════════
                                 Row 6 : T-Shirt + Run Category
                            ══════════════════════════════ -->
                            <div class="row g-3 form-section">
                                <div class="col-md-6">
                                    <div class="sec-label">
                                        <i class="fas fa-tshirt me-1"></i> T-Shirt Size
                                        <span class="req">*</span>
                                    </div>
                                    <div class="opt-group" id="tssGroup">
                                        <?php foreach (['S','M','L','XL','XXL'] as $sz): ?>
                                            <label class="opt-item">
                                                <input type="checkbox" name="tss[]" value="<?php echo $sz; ?>">
                                                <?php echo $sz; ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="sec-label">
                                        <i class="fas fa-flag-checkered me-1"></i> Run Category
                                        <span class="req">*</span>
                                    </div>
                                    <div class="opt-group" id="rctGroup">
                                        <?php foreach (['5 KM','10 KM','21.09 KM'] as $cat): ?>
                                            <label class="opt-item">
                                                <input type="checkbox" name="rct[]" value="<?php echo $cat; ?>">
                                                <?php echo $cat; ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- ══════════════════════════════
                                 ✅ Row 7 : Registration Fee
                            ══════════════════════════════ -->
                            <div class="row g-3 form-section">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">
                                        Registration Fee (₹) <span class="req">*</span>
                                    </label>
                                    <div class="input-group fee-group" id="feeGroup">
                                        <span class="input-group-text">
                                            <i class="fas fa-rupee-sign fa-sm"></i>
                                        </span>
                                        <input
                                            class="form-control"
                                            name="fee"
                                            id="feeInput"
                                            type="number"
                                            placeholder="Enter fee amount"
                                            min="0"
                                            step="1"
                                            value="<?php
                                                // ✅ Auto 0 if complementary pre-selected
                                                echo ($pre_pay === 'complementary') ? '0' : '0';
                                            ?>"
                                            <?php echo ($pre_pay === 'complementary') ? 'readonly' : ''; ?>
                                            required
                                        />
                                    </div>
                                    <div class="form-text text-muted" style="font-size:0.75rem;" id="feeHint">
                                        <?php if ($pre_pay === 'complementary'): ?>
                                            <i class="fas fa-info-circle me-1 text-purple"></i>
                                            Complementary entry — fee is 0.
                                        <?php else: ?>
                                            Enter 0 for free / complementary entry.
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- ══════════════════════════════
                                 ✅ Row 8 : Payment Type
                            ══════════════════════════════ -->
                            <div class="form-section">
                                <div class="sec-label">
                                    <i class="fas fa-wallet me-1"></i> Payment Type
                                    <span class="req">*</span>
                                </div>
                                <div class="opt-group" id="payGroup">

                                    <label class="opt-item pay-online
                                        <?php echo ($pre_pay === 'online') ? 'selected' : ''; ?>">
                                        <input type="radio" name="payment_status"
                                               value="online"
                                               <?php echo ($pre_pay === 'online') ? 'checked' : ''; ?>>
                                        <i class="fas fa-credit-card fa-sm"></i> Online
                                    </label>

                                    <label class="opt-item pay-offline
                                        <?php echo ($pre_pay === 'offline') ? 'selected' : ''; ?>">
                                        <input type="radio" name="payment_status"
                                               value="offline"
                                               <?php echo ($pre_pay === 'offline') ? 'checked' : ''; ?>>
                                        <i class="fas fa-money-bill-wave fa-sm"></i> Offline
                                    </label>

                                    <label class="opt-item pay-comp
                                        <?php echo ($pre_pay === 'complementary') ? 'selected' : ''; ?>">
                                        <input type="radio" name="payment_status"
                                               value="complementary"
                                               <?php echo ($pre_pay === 'complementary') ? 'checked' : ''; ?>>
                                        <i class="fas fa-gift fa-sm"></i> Complementary
                                    </label>

                                </div>
                            </div>

                            <!-- ══════════════════
                                 SUBMIT
                            ══════════════════ -->
                            <div class="row mt-3">
                                <div class="col-md-4 offset-md-4 col-12">
                                    <button type="submit" class="btn-submit-runner" name="submit">
                                        <i class="fas fa-user-plus me-2"></i>Register Runner
                                    </button>
                                </div>
                            </div>

                        </form>
                    </div><!-- /card-body -->
                </div><!-- /card -->

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

<!-- ✅ Success / Error SweetAlert -->
<?php if ($insert_success): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'success',
        title: 'Runner Registered!',
        text: 'Runner has been added successfully.',
        confirmButtonColor: '#343a40',
        confirmButtonText: 'OK'
    }).then(function () {
        window.location.href = 'manage-runner.php';
    });
});
</script>
<?php elseif (!empty($insert_error)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'error',
        title: 'Database Error!',
        html: '<code><?php echo addslashes(htmlspecialchars($insert_error)); ?></code>',
        confirmButtonColor: '#dc3545'
    });
});
</script>
<?php endif; ?>

<script>
// ── Pill button visual toggle ──────────────────────
document.querySelectorAll('.opt-item input').forEach(function (input) {
    if (input.checked) input.closest('.opt-item').classList.add('selected');

    input.addEventListener('change', function () {
        if (this.type === 'radio') {
            document.querySelectorAll('input[name="' + this.name + '"]').forEach(function (r) {
                r.closest('.opt-item').classList.remove('selected');
            });
        }
        if (this.checked) {
            this.closest('.opt-item').classList.add('selected');
        } else {
            this.closest('.opt-item').classList.remove('selected');
        }
    });
});

// ── Only numbers in tel inputs ─────────────────────
document.querySelectorAll('input[type="tel"]').forEach(function(el) {
    el.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
});

// ✅ Auto set fee = 0 and lock when Complementary selected ──
document.querySelectorAll('input[name="payment_status"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        var feeInput  = document.getElementById('feeInput');
        var feeGroup  = document.getElementById('feeGroup');
        var feeHint   = document.getElementById('feeHint');

        if (this.value === 'complementary') {
            feeInput.value = '0';
            feeInput.setAttribute('readonly', true);
            feeGroup.classList.add('locked');
            feeHint.innerHTML = '<i class="fas fa-info-circle me-1"></i> Complementary entry — fee is 0.';
        } else {
            feeInput.removeAttribute('readonly');
            feeGroup.classList.remove('locked');
            feeHint.innerHTML = 'Enter 0 for free / complementary entry.';
            // Clear 0 for paid types so user enters amount
            if (feeInput.value === '0') feeInput.value = '';
        }
    });
});

// ── Validation ─────────────────────────────────────
function validateForm() {
    var gender = document.querySelector('input[name="gender"]:checked');
    var bg     = document.querySelector('input[name="bg"]:checked');
    var tss    = document.querySelectorAll('input[name="tss[]"]:checked');
    var rct    = document.querySelectorAll('input[name="rct[]"]:checked');
    var pay    = document.querySelector('input[name="payment_status"]:checked');
    var fee    = document.getElementById('feeInput').value.trim();

    if (!gender) {
        Swal.fire({ icon:'warning', title:'Select Gender',
            text:'Please select a gender.', confirmButtonColor:'#343a40' });
        return false;
    }
    if (!bg) {
        Swal.fire({ icon:'warning', title:'Select Blood Group',
            text:'Please select a blood group.', confirmButtonColor:'#343a40' });
        return false;
    }
    if (tss.length === 0) {
        Swal.fire({ icon:'warning', title:'T-Shirt Size',
            text:'Please select at least one T-shirt size.', confirmButtonColor:'#343a40' });
        return false;
    }
    if (rct.length === 0) {
        Swal.fire({ icon:'warning', title:'Run Category',
            text:'Please select at least one run category.', confirmButtonColor:'#343a40' });
        return false;
    }
    // ✅ Fee check
    if (fee === '' || isNaN(fee) || parseFloat(fee) < 0) {
        Swal.fire({ icon:'warning', title:'Invalid Fee',
            text:'Please enter a valid registration fee (0 or more).', confirmButtonColor:'#343a40' });
        return false;
    }
    if (!pay) {
        Swal.fire({ icon:'warning', title:'Payment Type',
            text:'Please select a payment type.', confirmButtonColor:'#343a40' });
        return false;
    }
    return true;
}
</script>

</body>
</html>
