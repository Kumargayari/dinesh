<nav class="sb-topnav navbar navbar-expand navbar-dark navbar-custom">

    <!-- Navbar Brand -->
    <a class="navbar-brand ps-3" href="welcome.php">Run for Equility</a>

    <!-- Sidebar Toggle -->
    <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0"
            id="sidebarToggle" type="button">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Spacer -->
    <form class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0">
        <div class="input-group">&nbsp;</div>
    </form>

    <?php
        $userid  = (int)$_SESSION['id'];
        $stmt    = $con->prepare("SELECT fname, lname FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $uresult = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    ?>

    <div style="color:white; padding-top:10px;">
        <h6><?php echo htmlspecialchars(
            ($uresult['fname'] ?? '') . ' ' . ($uresult['lname'] ?? ''),
            ENT_QUOTES, 'UTF-8'
        ); ?></h6>
    </div>

    <!-- Navbar Dropdown -->
    <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle"
               id="navbarDropdown" href="#"
               role="button"
               data-bs-toggle="dropdown"
               aria-expanded="false">
                <i class="fas fa-user fa-fw"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end"
                aria-labelledby="navbarDropdown">
                <li>
                    <a class="dropdown-item" href="profile.php">
                        <i class="fas fa-user-circle me-2 text-primary"></i> Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="change-password.php">
                        <i class="fas fa-key me-2 text-warning"></i> Change Password
                    </a>
                </li>
                <li><hr class="dropdown-divider"/></li>
                <li>
                    <a class="dropdown-item text-danger"
                       href="#"
                       onclick="confirmLogout(event)">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </li>
    </ul>
</nav>

<!-- ✅ SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- ✅ Script tag wrap kiya — YAHI FIX HAI -->
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
