<?php
/**
 * Graduate Job Connect — Login Page
 */

require_once __DIR__ . '/config/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\BaseController;
use App\Middleware\AuthMiddleware;

// Already logged in → go straight to dashboard
if (isLoggedIn()) {
    $scheme   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir      = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $dir      = rtrim($dir, '/\\');
    $segments = explode('/', $dir);
    $dir      = implode('/', array_map(fn($s) => rawurlencode(rawurldecode($s)), $segments));
    header('Location: ' . $scheme . '://' . $host . $dir . '/' . currentRole() . '/dashboard.php');
    exit();
}

$pdo           = getDBConnection();
$controller    = new AuthController($pdo);
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // handleLogin() redirects internally on success, returns error array on failure
    $result = $controller->handleLogin();
    if (isset($result['error'])) {
        $error_message = $result['error'];
    }
}

$csrf_token = AuthMiddleware::csrfToken();
$page_title = 'Login';
include 'includes/header.php';
?>

<div class="auth-container">
<div class="auth-card">

    <div class="auth-header">
        <div class="auth-logo">
            <span class="logo-flag"></span>
            <span class="auth-logo-text">
                Graduate Job Connect
                <small class="auth-logo-tagline">GJC | Connecting Graduates with Opportunities</small>
            </span>
        </div>
        <h3>Welcome Back</h3>
        <p class="auth-subtitle">Sign in to your account</p>
    </div>

    <!-- Error -->
    <?php if ($error_message): ?>
    <div class="alert alert-error" style="margin-bottom:1.25rem;">
        <span class="alert-icon">⚠️</span>
        <span><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <?php endif; ?>

    <!-- Flash messages (e.g. from password reset) -->
    <?php foreach (BaseController::getFlash() as $flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>" style="margin-bottom:1rem;">
        <span><?php echo htmlspecialchars($flash['message']); ?></span>
    </div>
    <?php endforeach; ?>

    <!-- DB not set up warning -->
    <?php
    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
        $dbOk = true;
    } catch (\Throwable) {
        $dbOk = false;
    }
    if (!$dbOk): ?>
    <div class="alert alert-warning" style="margin-bottom:1rem;">
        <span class="alert-icon">⚠️</span>
        <span>Database not ready.
            <a href="run_migration.php" style="font-weight:700;">Run migration →</a>
        </span>
    </div>
    <?php endif; ?>

    <!-- Login form -->
    <form method="POST" id="loginForm"
          style="background:none;box-shadow:none;padding:0;max-width:none;margin:0;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" class="form-control"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   placeholder="Enter your email"
                   required autofocus autocomplete="email">
        </div>

        <div class="form-group">
            <label for="password"
                   style="display:flex;justify-content:space-between;align-items:center;">
                <span>Password</span>
                <a href="forgot-password.php"
                   style="font-size:.82rem;font-weight:400;color:var(--primary);">
                    Forgot password?
                </a>
            </label>
            <div style="position:relative;">
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Enter your password"
                       required autocomplete="current-password"
                       style="padding-right:3rem;">
                <button type="button" tabindex="-1"
                        onclick="const e=document.getElementById('password');e.type=e.type==='password'?'text':'password'"
                        style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;display:flex;align-items:center;"
                        aria-label="Show/hide password">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--dark-gray);">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-full btn-lg">
                Sign In
            </button>
        </div>
    </form>

    <div class="auth-footer">
        <p>Don't have an account?
            <a href="register.php" class="auth-link">Create one here</a>
        </p>
    </div>
</div>
</div>

<?php include 'includes/footer.php'; ?>
