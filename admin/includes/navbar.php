<?php
if (empty($_SESSION['adminid'])) {
    header('Location: index.php');
    exit();
}
?>

<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">

    <!-- ── Brand ── -->
    <a class="navbar-brand ps-3 d-flex align-items-center gap-2" href="dashboard.php">
        <div style="
            width:30px; height:30px; border-radius:8px;
            background:linear-gradient(135deg,#6c63ff,#574fd6);
            display:inline-flex; align-items:center; justify-content:center;
            font-size:13px; color:#fff; flex-shrink:0;">
            <i class="fas fa-shield-alt"></i>
        </div>
        <span style="font-size:0.92rem; font-weight:600;">Run for Equility</span>
    </a>

    <!-- ── Sidebar Toggle ── -->
    <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0"
            id="sidebarToggle" type="button" title="Toggle Sidebar">
        <i class="fas fa-bars"></i>
    </button>

    <!-- ── Search ── -->
    <form class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0"
          method="post" action="search-result.php" autocomplete="off">
        <input type="hidden" name="csrf_token"
               value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>"/>
        <div class="input-group">
            <input class="form-control" type="text" name="searchkey"
                   placeholder="Search by name, email or contact..."
                   maxlength="100" aria-label="Search"
                   aria-describedby="btnNavbarSearch"
                   style="min-width:280px;"/>
            <button class="btn btn-primary" id="btnNavbarSearch" type="submit">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </form>

    <!-- ── Right Navbar ── -->
    <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4 d-flex flex-row align-items-center gap-1">

        <!-- Notifications -->
        <li class="nav-item dropdown me-1">
            <a class="nav-link position-relative"
               href="#" id="notifDropdown" role="button"
               data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="fas fa-bell fa-fw"></i>
                <span class="position-absolute top-0 start-75 translate-middle
                             badge rounded-pill bg-danger"
                      style="font-size:0.58rem; padding:2px 5px;">3</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow"
                aria-labelledby="notifDropdown"
                style="min-width:270px; border-radius:12px;">
                <li>
                    <h6 class="dropdown-header d-flex justify-content-between">
                        Notifications
                        <span class="badge bg-primary rounded-pill">3</span>
                    </h6>
                </li>
                <li><hr class="dropdown-divider m-0"/></li>
                <li>
                    <a class="dropdown-item py-2" href="manage-users.php">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle bg-primary text-white
                                         d-flex align-items-center justify-content-center flex-shrink-0"
                                  style="width:30px;height:30px;font-size:11px;">
                                <i class="fas fa-user-plus"></i>
                            </span>
                            <div>
                                <div style="font-size:0.82rem;font-weight:500;">New user registered</div>
                                <div class="text-muted" style="font-size:0.73rem;">Just now</div>
                            </div>
                        </div>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2" href="bwdates-report-ds.php">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle bg-warning text-white
                                         d-flex align-items-center justify-content-center flex-shrink-0"
                                  style="width:30px;height:30px;font-size:11px;">
                                <i class="fas fa-chart-bar"></i>
                            </span>
                            <div>
                                <div style="font-size:0.82rem;font-weight:500;">Report ready</div>
                                <div class="text-muted" style="font-size:0.73rem;">1 hour ago</div>
                            </div>
                        </div>
                    </a>
                </li>
                <li><hr class="dropdown-divider m-0"/></li>
                <li>
                    <a class="dropdown-item text-center small text-primary py-2" href="#">
                        View all notifications
                    </a>
                </li>
            </ul>
        </li>

        <!-- User Dropdown -->
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2"
               id="navbarDropdown" href="#" role="button"
               data-bs-toggle="dropdown" aria-expanded="false">
                <div style="
                    width:32px; height:32px; border-radius:50%;
                    background:linear-gradient(135deg,#6c63ff,#f50057);
                    display:inline-flex; align-items:center; justify-content:center;
                    font-size:13px; font-weight:700; color:#fff; flex-shrink:0;">
                    <?php echo strtoupper(substr($_SESSION['login'] ?? 'A', 0, 1)); ?>
                </div>
                <span class="d-none d-lg-inline" style="font-size:0.85rem;">
                    <?php echo htmlspecialchars($_SESSION['login'] ?? 'Admin'); ?>
                </span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow"
                aria-labelledby="navbarDropdown"
                style="border-radius:12px; min-width:200px;">
                <!-- User Info -->
                <li>
                    <div class="px-3 py-2 border-bottom">
                        <div style="font-size:0.85rem; font-weight:600;">
                            <?php echo htmlspecialchars($_SESSION['login'] ?? 'Admin'); ?>
                        </div>
                        <div class="text-muted" style="font-size:0.73rem;">Administrator</div>
                    </div>
                </li>
                <li>
                    <a class="dropdown-item py-2" href="change-password.php">
                        <i class="fas fa-key fa-fw me-2 text-warning"></i> Change Password
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"/></li>
                <!-- ✅ SweetAlert Logout -->
                <li>
                    <a class="dropdown-item py-2 text-danger"
                       href="#" onclick="confirmLogout(event)">
                        <i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </li>

    </ul>
</nav>

<style>
    .sb-topnav.bg-dark {
        background: linear-gradient(90deg, #1a1a2e, #16213e) !important;
        border-bottom: 1px solid rgba(255,255,255,0.07);
        box-shadow: 0 2px 12px rgba(0,0,0,0.3);
    }
    .sb-topnav .navbar-brand { color: #fff !important; }
    .sb-topnav .nav-link {
        color: rgba(255,255,255,0.65) !important;
        padding: 6px 10px; border-radius: 8px;
        transition: all 0.2s ease;
    }
    .sb-topnav .nav-link:hover {
        color: #fff !important;
        background: rgba(255,255,255,0.08);
    }
    .sb-topnav .form-control {
        background: rgba(255,255,255,0.08) !important;
        border-color: rgba(255,255,255,0.15) !important;
        color: #fff !important;
    }
    .sb-topnav .form-control::placeholder { color: rgba(255,255,255,0.3) !important; }
    .sb-topnav .form-control:focus {
        background: rgba(255,255,255,0.12) !important;
        border-color: #6c63ff !important;
        box-shadow: 0 0 0 3px rgba(108,99,255,0.2) !important;
        color: #fff !important;
    }
    #sidebarToggle       { color: rgba(255,255,255,0.65) !important; }
    #sidebarToggle:hover { color: #fff !important; }
    .dropdown-menu { animation: dropFade 0.18s ease; }
    @keyframes dropFade {
        from { opacity:0; transform:translateY(-6px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .dropdown-item { font-size:0.84rem; transition:background 0.15s; }
    .dropdown-item:hover { background: rgba(108,99,255,0.08); }
</style>

<!-- ✅ SweetAlert2 — sirf ek baar load (sidebar mein already nahi hai to yahan) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function confirmLogout(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Logout?',
        text: 'Are you sure you want to sign out?',
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
