<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Run for Equility</title>
   
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1f2e 0%, #2d3561 50%, #1a1f2e 100%);
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            flex-direction: column;
        }

        /* ════════ NAVBAR ════════ */
        .top-nav {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 14px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .nav-brand {
            color: #fff;
            font-size: 1.1rem;
            font-weight: 800;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .nav-brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: #fff;
        }
        .nav-time {
            color: rgba(255,255,255,0.5);
            font-size: 0.82rem;
        }

        /* ════════ HERO SECTION ════════ */
        .hero-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px 90px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        /* Background decorative circles */
        .hero-section::before {
            content: '';
            position: absolute; top: -100px; right: -100px;
            width: 400px; height: 400px; border-radius: 50%;
            background: rgba(102,126,234,0.08);
            pointer-events: none;
        }
        .hero-section::after {
            content: '';
            position: absolute; bottom: -80px; left: -80px;
            width: 350px; height: 350px; border-radius: 50%;
            background: rgba(118,75,162,0.08);
            pointer-events: none;
        }

        /* Badge */
        .hero-badge {
            background: rgba(102,126,234,0.15);
            border: 1px solid rgba(102,126,234,0.3);
            color: #a5b4fc;
            border-radius: 20px; padding: 5px 16px;
            font-size: 0.78rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.08em;
            margin-bottom: 20px;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .hero-badge i { font-size: 0.7rem; }

        /* Title */
        .hero-title {
            color: #fff;
            font-size: 2.4rem; font-weight: 900;
            line-height: 1.15;
            margin-bottom: 16px;
            max-width: 640px;
        }
        .hero-title span {
            background: linear-gradient(135deg, #667eea, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Subtitle */
        .hero-sub {
            color: rgba(255,255,255,0.5);
            font-size: 1rem; max-width: 480px;
            line-height: 1.6; margin-bottom: 48px;
        }

        /* ════════ CARDS ROW ════════ */
        .cards-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
            max-width: 900px;
            width: 100%;
            z-index: 1;
        }

        /* Card */
        .action-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 32px 28px;
            width: 260px;
            text-align: center;
            transition: transform .25s, background .25s, border-color .25s;
            position: relative;
            overflow: hidden;
        }
        .action-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: 16px 16px 0 0;
        }
        .action-card:hover {
            transform: translateY(-6px);
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
        }

        /* Card color themes */
        .card-signup::before { background: linear-gradient(90deg, #667eea, #764ba2); }
        .card-login::before  { background: linear-gradient(90deg, #f59e0b, #f97316); }
        .card-admin::before  { background: linear-gradient(90deg, #ef4444, #dc2626); }

        /* Card icon */
        .card-icon {
            width: 62px; height: 62px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: #fff;
            margin: 0 auto 18px;
        }
        .card-signup .card-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
        .card-login  .card-icon { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .card-admin  .card-icon { background: linear-gradient(135deg, #ef4444, #dc2626); }

        /* Card text */
        .card-title {
            color: #fff; font-size: 1.1rem;
            font-weight: 800; margin-bottom: 8px;
        }
        .card-desc {
            color: rgba(255,255,255,0.45);
            font-size: 0.83rem; line-height: 1.5;
            margin-bottom: 22px;
        }

        /* Card button */
        .card-btn {
            display: inline-flex; align-items: center;
            gap: 7px; padding: 10px 22px;
            border-radius: 8px; font-size: 0.88rem;
            font-weight: 700; text-decoration: none;
            transition: opacity .2s, transform .15s;
            color: #fff;
        }
        .card-btn:hover { opacity: 0.88; transform: scale(1.03); color: #fff; }
        .card-signup .card-btn { background: linear-gradient(135deg, #667eea, #764ba2); }
        .card-login  .card-btn { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .card-admin  .card-btn { background: linear-gradient(135deg, #ef4444, #dc2626); }

        /* ════════ FOOTER FIXED ════════ */
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

        /* ════════ RESPONSIVE ════════ */
        @media (max-width: 600px) {
            .hero-title   { font-size: 1.7rem; }
            .action-card  { width: 100%; max-width: 320px; }
            .top-nav      { padding: 12px 18px; }
            .nav-time     { display: none; }
        }
    </style>
</head>

<body>

    <!-- ════════ NAVBAR ════════ -->
    <nav class="top-nav">
        <a class="nav-brand" href="index.php">
            <div class="nav-brand-icon">
                <i class="fas fa-users"></i>
            </div>
           Run for Equility
        </a>
        <div class="nav-time" id="liveTime"></div>
    </nav>

    <!-- ════════ HERO ════════ -->
    <div class="hero-section">

     

        <!-- ════════ CARDS ════════ -->
        <div class="cards-row">

            <!-- Signup -->
            <div class="action-card card-signup">
                <div class="card-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="card-title">New User?</div>
                <div class="card-desc">
                   Register here for Runner with Refferal Code
                </div>
                <a href="open-runner-registration.php" class="card-btn">
                    <i class="fas fa-user-plus"></i> Submit form Here
                </a>
            </div>

            <!-- Login -->
            <div class="action-card card-login">
                <div class="card-icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="card-title">Already Registered?</div>
                <div class="card-desc">
                    Login to your account and manage your profile &amp; runners.
                </div>
                <a href="login.php" class="card-btn">
                    <i class="fas fa-sign-in-alt"></i> Login Here
                </a>
            </div>

            <!-- Admin -->
            <div class="action-card card-admin">
                <div class="card-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="card-title">Admin Panel</div>
                <div class="card-desc">
                    Access the admin dashboard to manage all users &amp; data.
                </div>
                <a href="admin" class="card-btn">
                    <i class="fas fa-lock"></i> Admin Login
                </a>
            </div>

        </div>
        <!-- /cards-row -->

    </div>
    <!-- /hero-section -->

    <!-- ════════ FOOTER FIXED ════════ -->
    <div class="home-footer">
        <?php include_once('includes/footer.php'); ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="js/scripts.js"></script>

    <script>
        // Live clock
        function updateTime() {
            var now    = new Date();
            var h      = now.getHours().toString().padStart(2,'0');
            var m      = now.getMinutes().toString().padStart(2,'0');
            var s      = now.getSeconds().toString().padStart(2,'0');
            var days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            var months = ['Jan','Feb','Mar','Apr','May','Jun',
                          'Jul','Aug','Sep','Oct','Nov','Dec'];
            document.getElementById('liveTime').textContent =
                days[now.getDay()] + ', ' +
                now.getDate() + ' ' + months[now.getMonth()] + ' ' +
                now.getFullYear() + ' — ' + h + ':' + m + ':' + s;
        }
        updateTime();
        setInterval(updateTime, 1000);
    </script>

</body>
</html>
