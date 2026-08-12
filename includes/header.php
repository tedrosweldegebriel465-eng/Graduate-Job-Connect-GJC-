<?php
/**
 * Graduate Job Connect — Shared Header
 */

if (!function_exists('isLoggedIn')) {
    require_once dirname(__DIR__) . '/config/bootstrap.php';
}

// Notification count
$_notifCount = 0;
if (isLoggedIn()) {
    try {
        $notifModel  = new App\Models\NotificationModel(getDBConnection());
        $_notifCount = $notifModel->countUnread(currentUserId());
    } catch (\Throwable) {
        $_notifCount = 0;
    }
}

// ─── Reliable prefix calculation ─────────────────────────────────────────────
// Uses SCRIPT_FILENAME (physical path) to count depth relative to project root.
$_projectRoot = str_replace('\\', '/', dirname(__DIR__));
$_scriptDir   = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
$_prefix      = '';

if ($_scriptDir !== $_projectRoot) {
    $depth   = substr_count(
        str_replace($_projectRoot, '', $_scriptDir),
        '/'
    );
    $_prefix = str_repeat('../', max(0, $depth));
}

// CSS path — use $css_path if the page set it, otherwise compute from prefix
$_cssHref = isset($css_path) ? $css_path . 'style.css'
                              : $_prefix . 'assets/css/style.css';
$_jsBase  = isset($js_path)  ? $js_path
                              : $_prefix . 'assets/js/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="GJC — Graduate Job Connect | Connecting Graduates with Opportunities. Find jobs, post vacancies, and manage your recruitment workflow.">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' — ' : ''; ?>GJC | Graduate Job Connect</title>
    <link rel="stylesheet" href="<?php echo $_cssHref; ?>">
    <?php if (isset($extra_head)) echo $extra_head; ?>
    <style>
    /* ── Hide native browser password reveal & clear buttons (MS Edge, Chrome, Opera) ── */
    input[type="password"]::-ms-reveal,
    input[type="password"]::-ms-clear,
    input[type="password"]::-webkit-contacts-auto-fill-button,
    input[type="password"]::-webkit-credentials-auto-fill-button,
    input[type="password"]::-webkit-clear-button {
        display: none !important;
        visibility: hidden !important;
        pointer-events: none !important;
        width: 0 !important;
        height: 0 !important;
        appearance: none !important;
        -webkit-appearance: none !important;
    }
    /* ── Compact nav fix ── */
    .nav-menu { gap: .25rem; }
    .nav-link  { padding: .45rem .75rem; font-size: .88rem; }
    .nav-welcome { padding: .3rem .75rem; font-size: .85rem; }
    @media (max-width: 900px) {
        .nav-welcome { display: none; }          /* hide name on small screens */
    }
    </style>
</head>
<body>

<header class="header" role="banner">
    <nav class="navbar" aria-label="Main navigation">
        <div class="nav-container">

            <!-- Logo -->
            <div class="nav-logo">
                <a href="<?php echo $_prefix; ?>index.php" aria-label="Home">
                    <span class="logo-flag" aria-hidden="true"></span>
                    <span class="logo-text">
                        Graduate Job Connect
                        <span class="logo-tagline">GJC | Connecting Graduates with Opportunities</span>
                    </span>
                </a>
            </div>

            <!-- Mobile hamburger -->
            <button class="nav-toggle" id="navToggle"
                    aria-controls="navMenu" aria-expanded="false" aria-label="Open menu">
                <span></span><span></span><span></span>
            </button>

            <!-- Nav links -->
            <ul class="nav-menu" id="navMenu" role="list">

                <?php if (isLoggedIn()):
                    $role = $_SESSION['user_role'] ?? '';

                    // Determine dashboard path relative to current depth
                    $dashPaths = [
                        'admin'    => 'admin/dashboard.php',
                        'employer' => 'employer/dashboard.php',
                        'graduate' => 'graduate/dashboard.php',
                    ];
                    $dashIcons = [
                        'admin'    => '📊',
                        'employer' => '🏢',
                        'graduate' => '🎓',
                    ];
                ?>

                    <!-- Welcome name -->
                    <li class="nav-item">
                        <span class="nav-welcome">
                            <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>
                        </span>
                    </li>

                    <!-- Dashboard -->
                    <?php if (isset($dashPaths[$role])): ?>
                    <li class="nav-item">
                        <a href="<?php echo $_prefix . $dashPaths[$role]; ?>" class="nav-link">
                            <?php echo $dashIcons[$role]; ?> Dashboard
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Role-specific extra links -->
                    <?php if ($role === 'graduate'): ?>
                        <li class="nav-item">
                            <a href="<?php echo $_prefix; ?>graduate/jobs.php" class="nav-link">
                                💼 Jobs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $_prefix; ?>graduate/my-applications.php" class="nav-link">
                                📋 Applications
                            </a>
                        </li>
                    <?php elseif ($role === 'employer'): ?>
                        <li class="nav-item">
                            <a href="<?php echo $_prefix; ?>employer/post-job.php" class="nav-link">
                                ➕ Post Job
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $_prefix; ?>employer/applicants.php" class="nav-link">
                                📨 Applicants
                            </a>
                        </li>
                    <?php elseif ($role === 'admin'): ?>
                        <li class="nav-item">
                            <a href="<?php echo $_prefix; ?>admin/users.php" class="nav-link">
                                👥 Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $_prefix; ?>admin/jobs.php" class="nav-link">
                                💼 Jobs
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Notifications bell -->
                    <li class="nav-item" style="position:relative;">
                        <a href="<?php echo $_prefix; ?>notifications.php" class="nav-link"
                           title="Notifications<?php echo $_notifCount > 0 ? " ({$_notifCount} unread)" : ''; ?>">
                            🔔<?php if ($_notifCount > 0): ?>
                            <span style="position:absolute;top:0;right:0;background:#da121a;color:#fff;
                                         border-radius:50%;width:16px;height:16px;font-size:.6rem;
                                         font-weight:700;display:flex;align-items:center;justify-content:center;">
                                <?php echo min($_notifCount, 9); ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <!-- Logout — always visible -->
                    <li class="nav-item">
                        <a href="<?php echo $_prefix; ?>logout.php" class="nav-link"
                           style="background:rgba(255,255,255,.15);border-radius:var(--radius);">
                            🚪 Logout
                        </a>
                    </li>

                <?php else: ?>

                    <li class="nav-item">
                        <a href="<?php echo $_prefix; ?>login.php" class="nav-link">Login</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $_prefix; ?>register.php?role=graduate" class="nav-link"
                           style="background:var(--secondary);color:var(--primary-dark);font-weight:700;">
                            Get Started
                        </a>
                    </li>

                <?php endif; ?>

            </ul>
        </div>
    </nav>
</header>

<main class="main-content" id="main-content" role="main">

<?php
// Flash messages
$_flashes = App\Controllers\BaseController::getFlash();
if (!empty($_flashes)):
?>
<div class="container" style="padding-top:1rem;">
    <?php foreach ($_flashes as $_f): ?>
    <div class="alert alert-<?php echo htmlspecialchars($_f['type']); ?> alert-dismissible">
        <span><?php echo htmlspecialchars($_f['message']); ?></span>
        <button class="alert-close" aria-label="Close">&times;</button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
