<?php
/**
 * Graduate Job Connect — Forgot Password
 * Initiates password reset via 6-digit email OTP.
 */

require_once __DIR__ . '/config/bootstrap.php';

use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;

if (isLoggedIn()) {
    header('Location: ' . currentRole() . '/dashboard.php');
    exit();
}

$pdo        = getDBConnection();
$controller = new AuthController($pdo);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $controller->handleForgotPassword();
    if (isset($result['error'])) {
        $error = $result['error'];
    }
}

$csrf       = AuthMiddleware::csrfToken();
$page_title = 'Reset Password';
include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card" style="max-width: 440px;">

        <div class="auth-header">
            <div class="auth-logo">
                <span class="logo-flag"></span>
                <span class="auth-logo-text">
                    Graduate Job Connect
                    <small class="auth-logo-tagline">GJC | Connecting Graduates with Opportunities</small>
                </span>
            </div>
            <h3>Reset Password</h3>
            <p class="auth-subtitle">Enter your email and we will send a 6-digit verification code</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" class="auth-form" style="margin-bottom:1rem;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email"
                       id="email"
                       name="email"
                       class="form-control"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       placeholder="Enter your registered email"
                       required
                       autofocus>
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-lg">Send Verification Code</button>
        </form>

        <div class="auth-footer">
            <p><a href="login.php" class="auth-link">&larr; Back to Login</a></p>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
