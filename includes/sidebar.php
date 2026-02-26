<?php
// ✅ Session valid check
if (empty($_SESSION['id']) || !is_numeric($_SESSION['id'])) {
    header('Location: logout.php');
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$sid  = (int)$_SESSION['id'];
$suser = ['fname' => 'User', 'lname' => '', 'email' => ''];
$rc   = 0;

if ($sid > 0) {
    $sStmt = $con->prepare("SELECT fname, lname, email FROM users WHERE id = ? LIMIT 1");
    $sStmt->bind_param("i", $sid);
    $sStmt->execute();
    $suser = $sStmt->get_result()->fetch_assoc() ?? $suser;
    $sStmt->close();

    $rCount = $con->prepare("SELECT COUNT(*) AS c FROM runner WHERE u_id = ?");
    $rCount->bind_param("i", $sid);
    $rCount->execute();
    $rc = (int)$rCount->get_result()->fetch_assoc()['c'];
    $rCount->close();
}

function isActive($pages, $current) {
    $pages = is_array($pages) ? $pages : [$pages];
    return in_array($current, $pages) ? 'active' : '';
}
?>

<style>
/* ════════════════════════════════════════
   SIDEBAR CUSTOM THEME
════════════════════════════════════════ */
#layoutSidenav #layoutSidenav_nav { width: 225px; }

.sb-sidenav {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%) !important;
    display: flex; flex-direction: column; height: 100%;
}

/* ── Profile Banner ── */
.sidebar-profile {
    margin: 14px 12px 6px;
    background: linear-gradient(135deg,
        rgba(108,99,255,0.2), rgba(245,0,87,0.1));
    border: 1px solid rgba(108,99,255,0.2);
    border-radius: 14px; padding: 12px;
    display: flex; align-items: center; gap: 10px;
}
.sidebar-avatar {
    width: 40px; height: 40px; border-radius: 11px;
    background: linear-gradient(135deg, #6c63ff, #f50057);
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; font-weight: 700; color: #fff;
    flex-shrink: 0; box-shadow: 0 4px 14px rgba(108,99,255,0.45);
}
.sidebar-user-name {
    color: #fff; font-size: 0.84rem; font-weight: 700;
    white-space: nowrap; overflow: hidden;
    text-overflow: ellipsis; max-width: 130px;
}
.sidebar-user-email {
    color: rgba(255,255,255,0.38); font-size: 0.68rem;
    white-space: nowrap; overflow: hidden;
    text-overflow: ellipsis; max-width: 130px;
}

/* ── Section Heading ── */
.sb-sidenav-menu .sb-sidenav-menu-heading {
    color: rgba(255,255,255,0.22) !important;
    font-size: 0.62rem !important;
    letter-spacing: 1.8px !important;
    font-weight: 700 !important;
    padding: 14px 20px 5px !important;
    text-transform: uppercase;
}

/* ── Nav Links ── */
.sb-sidenav-menu .nav-link {
    color: rgba(255,255,255,0.5) !important;
    padding: 10px 16px !important;
    border-left: 3px solid transparent;
    border-radius: 0 10px 10px 0;
    margin-right: 10px;
    display: flex; align-items: center; gap: 2px;
    font-size: 0.88rem;
    transition: all 0.22s ease;
}
.sb-sidenav-menu .nav-link .sb-nav-link-icon {
    color: rgba(255,255,255,0.25) !important;
    margin-right: 8px; font-size: 0.85rem;
    width: 18px; text-align: center;
    transition: color 0.22s ease;
}

/* ── Hover ── */
.sb-sidenav-menu .nav-link:hover {
    color: #fff !important;
    background: rgba(255,255,255,0.06) !important;
    border-left-color: rgba(108,99,255,0.5) !important;
}
.sb-sidenav-menu .nav-link:hover .sb-nav-link-icon {
    color: #6c63ff !important;
}

/* ── Active ── */
.sb-sidenav-menu .nav-link.active {
    color: #fff !important;
    background: linear-gradient(90deg,
        rgba(108,99,255,0.22),
        rgba(108,99,255,0.06)) !important;
    border-left-color: #6c63ff !important;
}
.sb-sidenav-menu .nav-link.active .sb-nav-link-icon {
    color: #6c63ff !important;
}

/* ── Badge ── */
.sidebar-badge {
    margin-left: auto;
    background: linear-gradient(135deg, #6c63ff, #574fd6);
    color: #fff; border-radius: 20px;
    padding: 2px 9px; font-size: 0.68rem;
    font-weight: 700; min-width: 22px;
    text-align: center; line-height: 1.6;
}

/* ── Footer ── */
.sb-sidenav-footer {
    background: rgba(0,0,0,0.25) !important;
    border-top: 1px solid rgba(255,255,255,0.06) !important;
    padding: 12px 16px !important;
    margin-top: auto;
}
.sidebar-footer-inner {
    display: flex; align-items: center; gap: 8px;
}
.online-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #00e5a0;
    box-shadow: 0 0 6px rgba(0,229,160,0.8);
    flex-shrink: 0;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}
.sidebar-footer-text {
    color: rgba(255,255,255,0.32); font-size: 0.72rem;
}
.sidebar-footer-text strong {
    color: rgba(255,255,255,0.6);
}

/* ── Scrollbar ── */
.sb-sidenav-menu::-webkit-scrollbar { width: 3px; }
.sb-sidenav-menu::-webkit-scrollbar-track { background: transparent; }
.sb-sidenav-menu::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.1); border-radius: 10px;
}
.sb-sidenav-menu::-webkit-scrollbar-thumb:hover {
    background: rgba(108,99,255,0.4);
}

/* ── Topbar ── */
.sb-topnav {
    background: linear-gradient(135deg, #1a1a2e, #16213e) !important;
    box-shadow: 0 2px 16px rgba(0,0,0,0.3) !important;
    border-bottom: 1px solid rgba(255,255,255,0.05) !important;
}
.sb-topnav .navbar-brand {
    color: #fff !important; font-weight: 700; letter-spacing: -0.3px;
}
.sb-topnav .navbar-brand:hover { color: #6c63ff !important; }
#sidebarToggle { color: rgba(255,255,255,0.55) !important; transition: color 0.2s; }
#sidebarToggle:hover { color: #6c63ff !important; }

/* ── SweetAlert2 Rounded ── */
.swal2-popup.rounded-4  { border-radius: 20px !important; }
.swal2-confirm.rounded-3,
.swal2-cancel.rounded-3 { border-radius: 10px !important; }
</style>

<div id="layoutSidenav_nav">
    <nav class="sb-sidenav accordion" id="sidenavAccordion">

        <div class="sb-sidenav-menu">
            <div class="nav flex-column">

                <!-- Profile Banner -->
                <div class="sidebar-profile">
                    <div class="sidebar-avatar">
                        <?php echo strtoupper(substr($suser['fname'], 0, 1)); ?>
                    </div>
                    <div style="overflow:hidden;">
                        <div class="sidebar-user-name">
                            <?php echo htmlspecialchars(trim($suser['fname'].' '.$suser['lname'])); ?>
                        </div>
                        <div class="sidebar-user-email">
                            <?php echo htmlspecialchars($suser['email']); ?>
                        </div>
                    </div>
                </div>

                <!-- ══ MAIN ══ -->
                <div class="sb-sidenav-menu-heading">Main</div>

                <a class="nav-link <?php echo isActive('welcome.php', $currentPage); ?>"
                   href="welcome.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                    Dashboard
                </a>

                <a class="nav-link <?php echo isActive('manage-runner.php', $currentPage); ?>"
                   href="manage-runner.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-running"></i></div>
                    Runners
                    <?php if ($rc > 0): ?>
                        <span class="sidebar-badge"><?php echo $rc; ?></span>
                    <?php endif; ?>
                </a>

                <!-- ══ ACCOUNT ══ -->
                <div class="sb-sidenav-menu-heading">Account</div>

                <a class="nav-link <?php echo isActive('profile.php', $currentPage); ?>"
                   href="profile.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-user-circle"></i></div>
                    My Profile
                </a>

                <a class="nav-link <?php echo isActive('change-password.php', $currentPage); ?>"
                   href="change-password.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-key"></i></div>
                    Change Password
                </a>

                <!-- ══ SYSTEM ══ -->
                <div class="sb-sidenav-menu-heading">System</div>

                <a class="nav-link" href="#" onclick="confirmLogout(event)">
                    <div class="sb-nav-link-icon"><i class="fas fa-sign-out-alt"></i></div>
                    Sign Out
                </a>

            </div>
        </div>

        <!-- Sidebar Footer -->
        <div class="sb-sidenav-footer">
            <div class="sidebar-footer-inner">
                <div class="online-dot"></div>
                <div class="sidebar-footer-text">
                    Logged in as
                    <strong><?php echo htmlspecialchars($suser['fname']); ?></strong>
                </div>
            </div>
        </div>

    </nav>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmLogout(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Sign Out?',
        text: 'Are you sure you want to sign out?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor:  '#6c757d',
        confirmButtonText: '<i class="fas fa-sign-out-alt me-1"></i> Yes, Sign Out',
        cancelButtonText:  '<i class="fas fa-times me-1"></i> Cancel',
        background: '#1a1a2e',
        color:      '#f0f0ff',
        backdrop:   'rgba(0,0,0,0.7)',
        customClass: {
            popup:         'rounded-4',
            confirmButton: 'rounded-3',
            cancelButton:  'rounded-3'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
}
</script>
