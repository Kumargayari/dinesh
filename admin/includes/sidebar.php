<?php
function isActive($page) {
    $current = basename($_SERVER['PHP_SELF']);
    return ($current === $page) ? 'active' : '';
}
?>

<div id="layoutSidenav_nav">
    <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
        <div class="sb-sidenav-menu">
            <div class="nav">

                <!-- Admin Profile Block -->
                <div class="admin-profile-block">
                    <div class="admin-avatar">
                        <?php echo strtoupper(substr($_SESSION['login'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div class="admin-info">
                        <div class="admin-name">
                            <?php echo htmlspecialchars($_SESSION['login'] ?? 'Admin'); ?>
                        </div>
                        <div class="admin-role">
                            <span class="online-dot"></span> Administrator
                        </div>
                    </div>
                </div>

                <!-- CORE -->
                <div class="sb-sidenav-menu-heading">Core</div>
                <a class="nav-link <?php echo isActive('dashboard.php'); ?>" href="dashboard.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                    Dashboard
                </a>

                <!-- MANAGEMENT -->
                <div class="sb-sidenav-menu-heading">Management</div>
                <a class="nav-link <?php echo isActive('manage-booklet.php'); ?>" href="manage-booklet.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-book-open"></i></div>
                    Booklet
                </a>
                <a class="nav-link <?php echo isActive('manage-users.php'); ?>" href="manage-users.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                    Manage Users
                </a>

                <!-- REPORTS -->
                <div class="sb-sidenav-menu-heading">Reports</div>
                <a class="nav-link <?php echo isActive('all-runner.php'); ?>" href="all-runner.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-calendar-alt"></i></div>
                    Runner Report
                </a>
                <a class="nav-link <?php echo isActive('book-pub-rnr-rep.php'); ?>" href="book-pub-rnr-rep.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-calendar-alt"></i></div>
                   Booklet & public report
                </a>

                <div class="signout-divider"></div>

                <!-- ✅ SIGN OUT — SweetAlert -->
                <a class="nav-link nav-link-signout"
                   href="#"
                   onclick="confirmLogout(event)">
                    <div class="sb-nav-link-icon"><i class="fas fa-sign-out-alt"></i></div>
                    Sign Out
                </a>

            </div>
        </div>

        <!-- Sidebar Footer -->
        <div class="sb-sidenav-footer">
            <i class="fas fa-shield-alt me-1" style="color:#6c63ff;"></i>
            Logged in as <strong><?php echo htmlspecialchars($_SESSION['login'] ?? 'Admin'); ?></strong>
        </div>

    </nav>
</div>

<style>
    #layoutSidenav_nav .sb-sidenav {
        background: linear-gradient(180deg, #0f0f1a 0%, #1a1a2e 40%, #16213e 100%) !important;
        border-right: 1px solid rgba(108,99,255,0.15);
    }
    .admin-profile-block {
        display: flex; align-items: center; gap: 12px;
        padding: 20px 16px 16px;
        background: rgba(108,99,255,0.08);
        border-bottom: 1px solid rgba(255,255,255,0.06);
        margin-bottom: 6px;
    }
    .admin-avatar {
        width: 44px; height: 44px; border-radius: 12px;
        background: linear-gradient(135deg, #6c63ff, #f50057);
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; font-weight: 700; color: #fff;
        flex-shrink: 0; box-shadow: 0 4px 12px rgba(108,99,255,0.4);
    }
    .admin-name { font-size: 0.88rem; font-weight: 600; color: #fff; line-height: 1.2; }
    .admin-role { font-size: 0.72rem; color: rgba(255,255,255,0.45); display: flex; align-items: center; gap: 5px; margin-top: 2px; }
    .online-dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: #00e5a0; display: inline-block;
        box-shadow: 0 0 6px #00e5a0; animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50%       { opacity: 0.4; }
    }
    .sb-sidenav-dark .sb-sidenav-menu-heading {
        color: rgba(108,99,255,0.7) !important;
        font-size: 0.65rem !important; font-weight: 700 !important;
        letter-spacing: 1.5px !important; text-transform: uppercase !important;
        padding: 14px 18px 5px !important;
    }
    .sb-sidenav-dark .nav-link {
        color: rgba(255,255,255,0.55) !important;
        padding: 10px 16px !important;
        margin: 2px 10px 2px 8px;
        border-radius: 10px !important;
        border-left: 3px solid transparent !important;
        transition: all 0.22s ease !important;
        font-size: 0.875rem; font-weight: 400;
    }
    .sb-sidenav-dark .nav-link:hover {
        color: #fff !important;
        background: rgba(108,99,255,0.15) !important;
        border-left-color: rgba(108,99,255,0.5) !important;
        padding-left: 20px !important;
    }
    .sb-sidenav-dark .nav-link.active {
        color: #fff !important;
        background: rgba(108,99,255,0.22) !important;
        border-left: 3px solid #6c63ff !important;
        font-weight: 600 !important;
        box-shadow: inset 0 0 20px rgba(108,99,255,0.08);
    }
    .sb-sidenav-dark .nav-link.active .sb-nav-link-icon { color: #6c63ff !important; }
    .sb-sidenav-dark .sb-nav-link-icon {
        color: rgba(255,255,255,0.35) !important;
        width: 22px; text-align: center;
        transition: color 0.2s ease; margin-right: 10px;
    }
    .sb-sidenav-dark .nav-link:hover .sb-nav-link-icon { color: #6c63ff !important; }
    .signout-divider {
        height: 1px;
        background: linear-gradient(to right, transparent, rgba(255,255,255,0.08), transparent);
        margin: 10px 16px;
    }
    .nav-link-signout { color: rgba(255,100,100,0.7) !important; margin-top: 4px !important; }
    .nav-link-signout:hover {
        color: #ff6b6b !important;
        background: rgba(255,77,109,0.12) !important;
        border-left-color: #ff4d6d !important;
    }
    .nav-link-signout .sb-nav-link-icon { color: rgba(255,100,100,0.5) !important; }
    .nav-link-signout:hover .sb-nav-link-icon { color: #ff6b6b !important; }
    .sb-sidenav-footer {
        background: rgba(0,0,0,0.25) !important;
        border-top: 1px solid rgba(255,255,255,0.06) !important;
        padding: 12px 16px !important;
        font-size: 0.78rem !important;
        color: rgba(255,255,255,0.4) !important;
    }
    .sb-sidenav-footer strong { color: rgba(255,255,255,0.75); }
    #layoutSidenav_nav ::-webkit-scrollbar { width: 4px; }
    #layoutSidenav_nav ::-webkit-scrollbar-track { background: transparent; }
    #layoutSidenav_nav ::-webkit-scrollbar-thumb { background: rgba(108,99,255,0.3); border-radius: 4px; }
    #layoutSidenav_nav ::-webkit-scrollbar-thumb:hover { background: rgba(108,99,255,0.6); }
</style>

<!-- ✅ SweetAlert Logout -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmLogout(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Sign Out?',
        text: 'Are you sure you want to sign out ?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff4d6d',
        cancelButtonColor:  '#6c757d',
        confirmButtonText: '<i class="fas fa-sign-out-alt me-1"></i> Yes, Logout',
        cancelButtonText:  '<i class="fas fa-times me-1"></i> Cancel',
        background: '#1a1a2e',
        color: '#f0f0ff',
        customClass: {
            popup:         'rounded-4',
            confirmButton: 'rounded-3',
            cancelButton:  'rounded-3'
        },
        backdrop: 'rgba(0,0,0,0.7)'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
}
</script>
