<?php
session_start();
include_once('../includes/config.php');

if (isset($_POST['login'])) {
    $adminusername = trim($_POST['username']);
    $pass = $_POST['password'];

    $stmt = $con->prepare("SELECT id, username, password FROM admin WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $adminusername);
    $stmt->execute();
    $result = $stmt->get_result();
    $num = $result->fetch_assoc();
    $stmt->close();

    if ($num) {
        $stored_hash = $num['password'];
        if (preg_match('/^[a-f0-9]{32}$/i', $stored_hash)) {
            if (md5($pass) === $stored_hash) {
                $new_hash = password_hash($pass, PASSWORD_DEFAULT);
                $update_stmt = $con->prepare("UPDATE admin SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_hash, $num['id']);
                $update_stmt->execute();
                $update_stmt->close();
                $_SESSION['login']   = $adminusername;
                $_SESSION['adminid'] = $num['id'];
                header("Location: dashboard.php");
                exit();
            }
        } else {
            if (password_verify($pass, $stored_hash)) {
                $_SESSION['login']   = $adminusername;
                $_SESSION['adminid'] = $num['id'];
                header("Location: dashboard.php");
                exit();
            }
        }
    }
    $_SESSION['error'] = 'Invalid username or password';
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Admin Login</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>

    <style>
        :root {
            --primary:      #6c63ff;
            --primary-dark: #574fd6;
            --primary-glow: rgba(108,99,255,0.35);
            --secondary:    #f50057;
            --bg-dark:      #0d0d1a;
            --bg-card:      rgba(255,255,255,0.04);
            --border-color: rgba(255,255,255,0.1);
            --text-primary: #f0f0ff;
            --text-muted:   rgba(255,255,255,0.45);
            --input-bg:     rgba(255,255,255,0.06);
            --input-focus:  rgba(108,99,255,0.2);
            --font-main:    'Poppins', sans-serif;
            --font-sub:     'Inter', sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ✅ KEY FIX: html + body full height flex column */
        html { height: 100%; }

        body {
            min-height: 100vh;
            height: 100%;
            font-family: var(--font-main);
            background-color: var(--bg-dark);
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
        }

        /* ════════ BACKGROUND ════════ */
        body::before {
            content: '';
            position: fixed; inset: 0;
            background:
                radial-gradient(ellipse at 20% 20%, rgba(108,99,255,0.18) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 80%, rgba(245,0,87,0.12) 0%, transparent 60%),
                radial-gradient(ellipse at 50% 50%, rgba(0,229,160,0.06) 0%, transparent 70%);
            z-index: 0; pointer-events: none;
        }
        body::after {
            content: '';
            position: fixed;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(108,99,255,0.15), transparent 70%);
            border-radius: 50%;
            top: -100px; right: -100px;
            z-index: 0;
            animation: orb 8s ease-in-out infinite alternate;
            pointer-events: none;
        }
        @keyframes orb {
            0%   { transform: translate(0,0) scale(1); }
            100% { transform: translate(-30px,30px) scale(1.1); }
        }

        /* ════════ LAYOUT ════════ */
        /* ✅ FIX: No padding-bottom, flex column properly */
        #layoutAuthentication {
            flex: 1;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
        }

        #layoutAuthentication_content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }

        /* ════════ LOGIN CARD ════════ */
        .login-wrapper { width: 100%; max-width: 440px; }

        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 44px 40px 36px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow:
                0 0 0 1px rgba(108,99,255,0.08),
                0 30px 60px rgba(0,0,0,0.5),
                0 0 80px rgba(108,99,255,0.07);
            animation: fadeUp 0.5s ease both;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Header ── */
        .login-header { text-align: center; margin-bottom: 32px; }

        .login-avatar {
            width: 72px; height: 72px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 28px; color: #fff;
            margin-bottom: 18px;
            box-shadow: 0 10px 30px var(--primary-glow);
            position: relative;
        }
        .login-avatar::after {
            content: '';
            position: absolute; inset: -3px;
            border-radius: 23px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            z-index: -1; opacity: 0.3; filter: blur(8px);
        }
        .login-title {
            font-size: 1.65rem; font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.5px; margin-bottom: 6px;
        }
        .login-subtitle {
            font-family: var(--font-sub);
            font-size: 0.85rem;
            color: var(--text-muted); font-weight: 400;
        }

        /* ── Divider ── */
        .login-divider {
            height: 1px;
            background: linear-gradient(to right, transparent, var(--border-color), transparent);
            margin: 0 -40px 28px;
        }

        /* ── Error ── */
        .error-box {
            background: rgba(255,77,109,0.1);
            border: 1px solid rgba(255,77,109,0.3);
            border-radius: 12px; padding: 11px 14px;
            font-size: 0.83rem; color: #ff8fa3;
            font-family: var(--font-sub);
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 22px;
            animation: shake 0.4s ease;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25%       { transform: translateX(-6px); }
            75%       { transform: translateX(6px); }
        }

        /* ── Inputs ── */
        .input-group-custom { margin-bottom: 18px; }
        .input-label {
            font-size: 0.78rem; font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.8px;
            margin-bottom: 8px; display: block;
            font-family: var(--font-sub);
        }
        .input-field-wrap { position: relative; }
        .input-icon-left {
            position: absolute; left: 15px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted); font-size: 14px;
            pointer-events: none; transition: color 0.3s;
        }
        .input-field {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 13px 42px;
            color: var(--text-primary);
            font-size: 0.92rem;
            font-family: var(--font-main);
            outline: none;
            transition: all 0.3s ease;
        }
        .input-field::placeholder { color: rgba(255,255,255,0.2); }
        .input-field:focus {
            border-color: var(--primary);
            background: var(--input-focus);
            box-shadow: 0 0 0 4px rgba(108,99,255,0.15);
        }
        .input-field-wrap:focus-within .input-icon-left { color: var(--primary); }

        .toggle-pass {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted); cursor: pointer;
            font-size: 14px; transition: color 0.2s;
            background: none; border: none; padding: 0; z-index: 2;
        }
        .toggle-pass:hover { color: var(--primary); }

        /* ── Login Button ── */
        .btn-login {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none; border-radius: 12px;
            color: #fff; font-size: 0.95rem; font-weight: 600;
            font-family: var(--font-main); cursor: pointer;
            letter-spacing: 0.4px; margin-top: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px var(--primary-glow);
            position: relative; overflow: hidden;
        }
        .btn-login::before {
            content: '';
            position: absolute; top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }
        .btn-login:hover::before { left: 100%; }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px var(--primary-glow);
        }
        .btn-login:active { transform: translateY(0); }

        /* ── Bottom Links ── */
        .login-links {
            display: flex; justify-content: space-between;
            align-items: center; margin-top: 20px;
        }
        .login-links a {
            font-family: var(--font-sub); font-size: 0.8rem;
            color: var(--text-muted); text-decoration: none;
            transition: color 0.2s;
        }
        .login-links a:hover { color: var(--primary); }

        /* ════════ FOOTER ✅ FIX ════════ */
       .home-footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            text-align: center;
            color: rgba(255,255,255,0.3);
            font-size: 0.78rem;
            padding: 12px 20px;
            background: rgba(0,0,0,0.35);
            backdrop-filter: blur(8px);
            border-top: 1px solid rgba(255,255,255,0.07);
            z-index: 999;
        }

        @media (max-width: 480px) {
            .login-card    { padding: 32px 24px 28px; border-radius: 18px; }
            .login-divider { margin: 0 -24px 24px; }
        }
    </style>
</head>

<body>

<!-- ✅ layoutAuthentication body ke direct child hai -->
<div id="layoutAuthentication">

    <div id="layoutAuthentication_content">
        <main>
            <div class="login-wrapper">
                <div class="login-card">

                    <div class="login-header">
                        <div class="login-avatar">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h4 class="login-title">Welcome Back</h4>
                        <p class="login-subtitle">Sign in to your admin panel</p>
                    </div>

                    <div class="login-divider"></div>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="error-box">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" autocomplete="off">

                        <div class="input-group-custom">
                            <label class="input-label" for="inputUsername">Username</label>
                            <div class="input-field-wrap">
                                <i class="fas fa-user input-icon-left"></i>
                                <input class="input-field" id="inputUsername"
                                    name="username" type="text"
                                    placeholder="Enter your username"
                                    required autocomplete="off"/>
                            </div>
                        </div>

                        <div class="input-group-custom">
                            <label class="input-label" for="inputPassword">Password</label>
                            <div class="input-field-wrap">
                                <i class="fas fa-lock input-icon-left"></i>
                                <input class="input-field" id="inputPassword"
                                    name="password" type="password"
                                    placeholder="Enter your password"
                                    required/>
                                <button type="button" class="toggle-pass"
                                    onclick="togglePassword()" title="Show/Hide">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <button class="btn-login" name="login" type="submit">
                            <i class="fas fa-sign-in-alt me-2"></i> Sign In
                        </button>

                        <div class="login-links">
                            <a href="password-recovery.php">
                                <i class="fas fa-key me-1"></i> Forgot Password?
                            </a>
                            <a href="../index.php">
                                <i class="fas fa-home me-1"></i> Back to Home
                            </a>
                        </div>

                    </form>
                </div>
            </div>
        </main>
    </div>

</div>
<!-- ✅ Footer -->
<div class="home-footer">
    <?php include_once('../includes/footer.php'); ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="../js/scripts.js"></script>
<script>
    function togglePassword() {
        var input = document.getElementById('inputPassword');
        var icon  = document.getElementById('toggleIcon');
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
