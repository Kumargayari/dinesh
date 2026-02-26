<?php
session_start();
include_once('includes/config.php');

if (empty($_SESSION['id'])) {
    header('location:logout.php');
    exit();
}

$rid = isset($_GET['rid']) ? mysqli_real_escape_string($con, $_GET['rid']) : '';

if (empty($rid)) {
    header('location:manage-runner.php');
    exit();
}

$update_success = false;
$update_error   = '';

// ── UPDATE LOGIC ──
if (isset($_POST['submit'])) {
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

    $msg = mysqli_query($con,
        "UPDATE `runner` SET
            `r_name`      = '$rname',
            `r_contact`   = '$contact',
            `r_dob`       = '$dob',
            `r_gender`    = '$gender',
            `r_bdgp`      = '$bg',
            `r_email`     = '$email',
            `r_catgry`    = '$rct',
            `r_tshirt_sz` = '$tss',
            `r_emrg_con`  = '$econtact',
            `r_med_dt`    = '$mdc'
         WHERE `r_id` = '$rid'"
    );

    if ($msg) {
        $update_success = true;
    } else {
        $update_error = mysqli_error($con);
    }
}

// ── FETCH EXISTING RUNNER DATA ──
$runner = [];
$fetch = mysqli_query($con, "SELECT * FROM `runner` WHERE `r_id` = '$rid'");
if ($fetch && mysqli_num_rows($fetch) > 0) {
    $runner = mysqli_fetch_assoc($fetch);
} else {
    header('location:manage-runner.php');
    exit();
}

// Pre-split existing tss and rct for checkbox pre-selection
$existing_tss = array_map('trim', explode(',', $runner['r_tshirt_sz'] ?? ''));
$existing_rct = array_map('trim', explode(',', $runner['r_catgry']    ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Update Runner</title>

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
        .card-header-custom h6 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        /* ── Runner info badge in header ── */
        .runner-badge {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.35);
            color: #fff;
            padding: 4px 14px;
            border-radius: 30px;
            font-size: 0.82rem;
            font-weight: 700;
        }

        /* ── Input group ── */
        .input-group-text {
            background: #f8f9fa;
            border-right: 0;
        }
        .input-group .form-control {
            border-left: 0;
        }
        .input-group:focus-within .input-group-text,
        .input-group:focus-within .form-control {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13,110,253,.25);
        }
        .input-group .form-control:focus {
            border-color: #ced4da;
            box-shadow: none;
        }

        /* ── Buttons ── */
        .btn-update-runner {
            background: #0d6efd;
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
        .btn-update-runner:hover {
            background: #0a58ca;
            color: #fff;
        }
        .btn-cancel-runner {
            background: #6c757d;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 11px 0;
            width: 100%;
            font-size: 0.95rem;
            font-weight: 600;
            transition: background .2s;
            text-align: center;
            display: block;
            text-decoration: none;
        }
        .btn-cancel-runner:hover {
            background: #565e64;
            color: #fff;
        }

        .breadcrumb-item a { text-decoration: none; }
        .req { color: #dc3545; }

        /* ── Runner info strip ── */
        .runner-info-strip {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 18px;
            margin-bottom: 1.4rem;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .runner-info-strip .info-item {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 0.88rem;
            color: #495057;
        }
        .runner-info-strip .info-item i {
            color: #0d6efd;
            width: 16px;
        }
        .runner-info-strip .info-item strong {
            color: #212529;
        }

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
                    <li class="breadcrumb-item active">Update Runner</li>
                </ol>

                <div class="card mb-4">

                    <!-- Card Header -->
                    <div class="card-header-custom">
                        <h6>
                            <i class="fas fa-user-edit me-2"></i>Update Runner Details
                        </h6>
                        <span class="runner-badge">
                            <i class="fas fa-hashtag me-1"></i>SR No:&nbsp;
                            <strong><?php echo htmlspecialchars($runner['r_srn']); ?></strong>
                        </span>
                    </div>

                    <div class="card-body px-4 py-4">

                        <!-- ── Runner Info Strip ── -->
                        <div class="runner-info-strip">
                            <div class="info-item">
                                <i class="fas fa-id-badge"></i>
                                Runner ID: <strong>#<?php echo htmlspecialchars($runner['r_id']); ?></strong>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-book"></i>
                                Booklet ID: <strong><?php echo htmlspecialchars($runner['b_id']); ?></strong>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-calendar-alt"></i>
                                Registered:
                                <strong>
                                    <?php echo date('d M Y', strtotime($runner['reg_dt'])); ?>
                                </strong>
                            </div>
                        </div>

                        <form
                            method="post"
                            name="updateRunnerForm"
                            action="update-runner.php?rid=<?php echo urlencode($rid); ?>"
                            onsubmit="return validateForm();"
                        >
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
                                        value="<?php echo htmlspecialchars($runner['r_name']); ?>"
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
                                        value="<?php echo htmlspecialchars($runner['r_dob']); ?>"
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
                                            value="<?php echo htmlspecialchars($runner['r_contact']); ?>"
                                            required
                                        />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">
                                        Email Address <span class="req">*</span>
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
                                            value="<?php echo htmlspecialchars($runner['r_email']); ?>"
                                            required
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
                                            value="<?php echo htmlspecialchars($runner['r_emrg_con']); ?>"
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
                                        value="<?php echo htmlspecialchars($runner['r_med_dt']); ?>"
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
                                    <?php foreach (['Male','Female','Other'] as $g): ?>
                                        <label class="opt-item <?php echo ($runner['r_gender'] == $g) ? 'selected' : ''; ?>">
                                            <input
                                                type="radio"
                                                name="gender"
                                                value="<?php echo $g; ?>"
                                                <?php echo ($runner['r_gender'] == $g) ? 'checked' : ''; ?>
                                            >
                                            <?php
                                            $icons = ['Male'=>'fa-mars','Female'=>'fa-venus','Other'=>'fa-genderless'];
                                            echo '<i class="fas '.$icons[$g].' fa-sm"></i> '.$g;
                                            ?>
                                        </label>
                                    <?php endforeach; ?>
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
                                        <label class="opt-item <?php echo ($runner['r_bdgp'] == $bg_val) ? 'selected' : ''; ?>">
                                            <input
                                                type="radio"
                                                name="bg"
                                                value="<?php echo $bg_val; ?>"
                                                <?php echo ($runner['r_bdgp'] == $bg_val) ? 'checked' : ''; ?>
                                            >
                                            <?php echo $bg_val; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- ══════════════════════════════════
                                 Row 6 : T-Shirt + Run Category
                            ══════════════════════════════════ -->
                            <div class="row g-3 form-section">
                                <div class="col-md-6">
                                    <div class="sec-label">
                                        <i class="fas fa-tshirt me-1"></i> T-Shirt Size
                                        <span class="req">*</span>
                                    </div>
                                    <div class="opt-group" id="tssGroup">
                                        <?php foreach (['S','M','L','XL','XXL'] as $sz): ?>
                                            <label class="opt-item <?php echo in_array($sz, $existing_tss) ? 'selected' : ''; ?>">
                                                <input
                                                    type="checkbox"
                                                    name="tss[]"
                                                    value="<?php echo $sz; ?>"
                                                    <?php echo in_array($sz, $existing_tss) ? 'checked' : ''; ?>
                                                >
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
                                            <label class="opt-item <?php echo in_array($cat, $existing_rct) ? 'selected' : ''; ?>">
                                                <input
                                                    type="checkbox"
                                                    name="rct[]"
                                                    value="<?php echo $cat; ?>"
                                                    <?php echo in_array($cat, $existing_rct) ? 'checked' : ''; ?>
                                                >
                                                <?php echo $cat; ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- ══════════════════════════
                                 BUTTONS : Update + Cancel
                            ══════════════════════════ -->
                            <div class="row g-3 mt-2">
                                <div class="col-md-4 offset-md-2 col-6">
                                    <button type="submit" class="btn-update-runner" name="submit">
                                        <i class="fas fa-save me-2"></i>Update Runner
                                    </button>
                                </div>
                                <div class="col-md-4 col-6">
                                    <a href="manage-runner.php" class="btn-cancel-runner">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
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

<!-- ── Success / Error popup ── -->
<?php if ($update_success): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'success',
        title: 'Runner Updated!',
        text: 'Runner details have been updated successfully.',
        confirmButtonColor: '#0d6efd',
        confirmButtonText: 'OK'
    }).then(function () {
        window.location.href = 'manage-runner.php';
    });
});
</script>
<?php elseif (!empty($update_error)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'error',
        title: 'Database Error!',
        html: '<code><?php echo addslashes(htmlspecialchars($update_error)); ?></code>',
        confirmButtonColor: '#dc3545'
    });
});
</script>
<?php endif; ?>

<script>
// ── Pill button visual toggle ──
document.querySelectorAll('.opt-item input').forEach(function (input) {
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

// ── Validation ──
function validateForm() {
    var gender = document.querySelector('input[name="gender"]:checked');
    var bg     = document.querySelector('input[name="bg"]:checked');
    var tss    = document.querySelectorAll('input[name="tss[]"]:checked');
    var rct    = document.querySelectorAll('input[name="rct[]"]:checked');

    if (!gender) {
        Swal.fire({ icon: 'warning', title: 'Select Gender', text: 'Please select a gender.' });
        return false;
    }
    if (!bg) {
        Swal.fire({ icon: 'warning', title: 'Select Blood Group', text: 'Please select a blood group.' });
        return false;
    }
    if (tss.length === 0) {
        Swal.fire({ icon: 'warning', title: 'T-Shirt Size', text: 'Please select at least one T-shirt size.' });
        return false;
    }
    if (rct.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Run Category', text: 'Please select at least one run category.' });
        return false;
    }
    return true;
}
</script>

</body>
</html>
