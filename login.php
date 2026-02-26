<?php
ob_start();
session_start();
include_once('includes/config.php');

// ── Already logged in ────────────────────────────────────
if (!empty($_SESSION['id'])) {
    header('Location: welcome.php');
    exit();
}

// ── Safe Output ──────────────────────────────────────────
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// ── Handle Login ─────────────────────────────────────────
if (isset($_POST['login'])) {

    $useremail = trim($_POST['uemail']    ?? '');
    $password  = trim($_POST['password'] ?? '');

    if (empty($useremail) || empty($password)) {
        $_SESSION['error'] = 'Please enter email and password.';
        header('Location: login.php');
        exit();
    }

    if (!filter_var($useremail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email format.';
        header('Location: login.php');
        exit();
    }

    // ✅ Fetch user by email only
    $stmt = $con->prepare("SELECT id, fname, password FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $useremail);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        // ✅ Smart: detect plain text vs hashed
        $isHashed = substr($user['password'], 0, 4) === '$2y$';
        $isValid  = $isHashed
                    ? password_verify($password, $user['password'])
                    : ($user['password'] === $password);

        if ($isValid) {
            // ✅ Auto-upgrade plain text → bcrypt on login
            if (!$isHashed) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $con->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->bind_param("si", $newHash, $user['id']);
                $upd->execute();
                $upd->close();
            }

            session_regenerate_id(true);
            $_SESSION['id']   = $user['id'];
            $_SESSION['name'] = $user['fname'];
            header('Location: welcome.php');
            exit();
        }
    }

    $_SESSION['error'] = 'Invalid email or password.';
    header('Location: login.php');
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
    <title>User Login</title>
    <link href="css/styles.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <style>
        /* ════════════════════════════
           RESET + BODY
        ════════════════════════════ */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
            position: relative;
            overflow: hidden;
        }

        /* ── Animated blobs ── */
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
            animation: floatBlob 8s ease-in-out infinite;
            z-index: 0;
        }
        body::before {
            width: 420px; height: 420px;
            background: #6c63ff;
            top: -120px; left: -120px;
        }
        body::after {
            width: 380px; height: 380px;
            background: #f50057;
            bottom: -100px; right: -100px;
            animation-delay: 4s;
        }
        @keyframes floatBlob {
            0%, 100% { transform: translateY(0) scale(1); }
            50%       { transform: translateY(30px) scale(1.05); }
        }

        /* ════════════════════════════
           LAYOUT
        ════════════════════════════ */
        #layoutAuthentication {
            width: 100%;
            z-index: 1;
            position: relative;
            padding: 20px 16px;
        }

        /* ════════════════════════════
           LOGIN CARD
        ════════════════════════════ */
        .login-card {
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            box-shadow: 0 30px 70px rgba(0,0,0,0.45),
                        0 0 0 1px rgba(255,255,255,0.04);
            overflow: hidden;
            max-width: 430px;
            width: 100%;
            margin: 0 auto;
        }

        /* ── Card Header ── */
        .login-card-header {
            padding: 34px 38px 22px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .login-logo {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: linear-gradient(135deg, #6c63ff, #f50057);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: #fff;
            margin: 0 auto 18px;
            box-shadow: 0 10px 28px rgba(108,99,255,0.45);
            animation: logoPulse 3s ease-in-out infinite;
        }
        @keyframes logoPulse {
            0%, 100% { box-shadow: 0 10px 28px rgba(108,99,255,0.45); }
            50%       { box-shadow: 0 10px 40px rgba(108,99,255,0.75); }
        }
        .login-title {
            color: #fff;
            font-size: 1.45rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.3px;
        }
        .login-subtitle {
            color: rgba(255,255,255,0.38);
            font-size: 0.83rem;
            margin-top: 6px;
        }

        /* ── Card Body ── */
        .login-card-body { padding: 28px 38px 22px; }

        /* ── Field Label ── */
        .field-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: rgba(255,255,255,0.45);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .field-label i { color: #6c63ff; font-size: 0.75rem; }

        /* ── Input ── */
        .input-glass {
            width: 100%;
            background: rgba(255,255,255,0.06) !important;
            border: 1.5px solid rgba(255,255,255,0.1) !important;
            border-radius: 12px !important;
            padding: 12px 16px !important;
            font-size: 0.92rem !important;
            color: #fff !important;
            transition: all 0.25s ease !important;
        }
        .input-glass:focus {
            border-color: #6c63ff !important;
            background: rgba(108,99,255,0.1) !important;
            box-shadow: 0 0 0 3px rgba(108,99,255,0.2) !important;
            outline: none !important;
        }
        .input-glass::placeholder {
            color: rgba(255,255,255,0.22) !important;
        }

        /* ── Password Wrap ── */
        .input-wrap { position: relative; }
        .input-wrap .input-glass { padding-right: 46px !important; }
        .toggle-pwd {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255,255,255,0.3);
            cursor: pointer;
            font-size: 14px;
            padding: 0;
            transition: color 0.2s;
        }
        .toggle-pwd:hover { color: #6c63ff; }

        /* ── Forgot Link ── */
        .forgot-link {
            font-size: 0.78rem;
            color: rgba(255,255,255,0.35);
            text-decoration: none;
            transition: color 0.2s;
        }
        .forgot-link:hover { color: #6c63ff; }

        /* ── Login Button ── */
        .btn-login {
            background: linear-gradient(135deg, #6c63ff, #574fd6);
            border: none;
            color: #fff;
            padding: 13px 28px;
            border-radius: 12px;
            font-size: 0.93rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 22px rgba(108,99,255,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            position: relative;
            overflow: hidden;
        }
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg,
                transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s ease;
        }
        .btn-login:hover::before { left: 100%; }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(108,99,255,0.55);
        }
        .btn-login:active { transform: translateY(0); }

        /* ── Card Footer ── */
        .login-card-footer {
            padding: 16px 38px 26px;
            border-top: 1px solid rgba(255,255,255,0.06);
            text-align: center;
        }
        .login-card-footer a {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.35);
            text-decoration: none;
            transition: color 0.2s;
            display: block;
            margin-bottom: 6px;
        }
        .login-card-footer a:last-child { margin-bottom: 0; }
        .login-card-footer a:hover { color: #6c63ff; }
        .login-card-footer a span {
            color: #6c63ff;
            font-weight: 600;
        }

        /* ── Error Alert ── */
        .alert-glass {
            background: rgba(220,53,69,0.1);
            border: 1px solid rgba(220,53,69,0.3);
            border-radius: 12px;
            padding: 11px 16px;
            color: #ff6b7a;
            font-size: 0.83rem;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Copyright ── */
        .copy-text {
            text-align: center;
            color: rgba(255,255,255,0.15);
            font-size: 0.73rem;
            margin-top: 16px;
        }
    </style>
</head>
<body>

<div id="layoutAuthentication">
    <main>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-4 col-lg-5 col-md-7 col-sm-10">

                    <div class="login-card">

                        <!-- ── Header ── -->
                        <div class="login-card-header">
                            <div class="login-logo">
                                <i class="fas fa-running"></i>
                            </div>
                            <h3 class="login-title">Welcome Back</h3>
                            <p class="login-subtitle">
                                Sign in to your account to continue
                            </p>
                        </div>

                        <!-- ── Body ── -->
                        <div class="login-card-body">

                            <!-- Error Alert -->
                            <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert-glass">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo e($_SESSION['error']); ?>
                            </div>
                            <?php unset($_SESSION['error']); endif; ?>

                            <form method="post"
                                  id="loginForm"
                                  autocomplete="off">

                                <!-- Email -->
                                <div class="mb-4">
                                    <label class="field-label">
                                        <i class="fas fa-envelope"></i>
                                        Email Address
                                    </label>
                                    <input class="input-glass"
                                           name="uemail"
                                           type="email"
                                           placeholder="email@example.com"
                                           required />
                                </div>

                                <!-- Password -->
                                <div>
                                    <div class="d-flex justify-content-between
                                                align-items-center mb-2">
                                        <label class="field-label mb-0">
                                            <i class="fas fa-lock"></i>
                                            Password
                                        </label>
                                        <a href="password-recovery.php"
                                           class="forgot-link">
                                            Forgot password?
                                        </a>
                                    </div>
                                    <div class="input-wrap">
                                        <input class="input-glass"
                                               id="loginPwd"
                                               name="password"
                                               type="password"
                                               placeholder="Enter your password"
                                               required />
                                        <button type="button"
                                                class="toggle-pwd"
                                                onclick="togglePwd()">
                                            <i class="fas fa-eye"
                                               id="eyeIcon"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Submit -->
                                <button type="submit"
                                        name="login"
                                        class="btn-login">
                                    <i class="fas fa-sign-in-alt"></i>
                                    Sign In
                                </button>

                            </form>
                        </div>

                        <!-- ── Footer Links ── -->
                        <div class="login-card-footer">
                            <a href="signup.php">
                                Don't have an account? <span>Sign Up</span>
                            </a>
                            <a href="index.php">
                                <i class="fas fa-home me-1"></i> Back to Home
                            </a>
                        </div>

                    </div>

                    <!-- Copyright -->
                    <div class="copy-text">
                        &copy; <?php echo date('Y'); ?>
                        Dinesh &mdash; All rights reserved.
                    </div>

                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="js/scripts.js"></script>
<script>
function togglePwd() {
    const input = document.getElementById('loginPwd');
    const icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>
