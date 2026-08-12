<?php
/**
 * Graduate Job Connect — Reset Password
 * Updates user password following successful 6-digit OTP verification.
 */

require_once __DIR__ . '/config/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Models\UserModel;

if (isLoggedIn()) {
    header('Location: ' . currentRole() . '/dashboard.php');
    exit();
}

$pdo       = getDBConnection();
$userModel = new UserModel($pdo);

$email      = $_SESSION['otp_reset_verified_email'] ?? '';
$message    = '';
$msgType    = '';
$resetValid = !empty($email);

if (!$resetValid && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $message = 'Please request a password reset code first.';
    $msgType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetValid) {
    AuthMiddleware::verifyCsrf($_POST['csrf_token'] ?? '');

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $message = 'Password must be at least 8 characters and include uppercase, number, and special character.';
        $msgType = 'error';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
        $msgType = 'error';
    } else {
        $user = $userModel->findByEmail($email);
        if ($user) {
            $userModel->changePassword((int) $user['id'], $password);

            // Unset session verification token
            unset($_SESSION['otp_reset_verified_email']);

            $message    = 'Password updated successfully! You can now sign in with your new password.';
            $msgType    = 'success';
            $resetValid = false;
        } else {
            $message = 'User account not found.';
            $msgType = 'error';
        }
    }
}

$csrf       = AuthMiddleware::csrfToken();
$page_title = 'Set New Password';
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
            <h3>Set New Password</h3>
            <?php if ($resetValid): ?>
            <p class="auth-subtitle">Create a strong new password for <strong><?php echo htmlspecialchars($email); ?></strong></p>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $msgType; ?>">
            <span><?php echo htmlspecialchars($message); ?></span>
            <?php if ($msgType === 'success'): ?>
            <div style="margin-top:.75rem;">
                <a href="login.php" class="btn btn-primary btn-sm">Sign In Now</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($resetValid): ?>
        <form method="POST" class="auth-form" style="margin-bottom:1rem;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="At least 8 chars, uppercase, number, special" required minlength="8">
                <div class="password-strength" style="margin-top:.4rem;">
                    <div class="strength-bar"></div>
                    <div class="strength-text" style="font-size:.78rem;margin-top:.2rem;">
                        Strength: <span>—</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       class="form-control" placeholder="Repeat new password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-lg">Update Password</button>
        </form>
        <?php elseif ($msgType !== 'success'): ?>
        <div style="text-align:center;margin-top:1rem;">
            <a href="forgot-password.php" class="btn btn-primary btn-sm">Request Password Reset</a>
        </div>
        <?php endif; ?>

        <div class="auth-footer" style="margin-top:1.5rem;">
            <p><a href="login.php" class="auth-link">&larr; Back to Login</a></p>
        </div>

    </div>
</div>

<script>
const pw  = document.getElementById('password');
const bar = document.querySelector('.strength-bar');
const txt = document.querySelector('.strength-text span');
if (pw && bar) {
    pw.addEventListener('input', function () {
        const p = this.value; let s = 0;
        if (p.length >= 8)          s++;
        if (/[a-z]/.test(p))        s++;
        if (/[A-Z]/.test(p))        s++;
        if (/[0-9]/.test(p))        s++;
        if (/[^A-Za-z0-9]/.test(p)) s++;
        const lvl = ['','weak','fair','good','strong','strong'];
        const lbl = ['—','Weak','Fair','Good','Strong','Strong'];
        bar.className = 'strength-bar ' + (lvl[s]||'');
        txt.textContent = lbl[s]||'—';
    });
}
const cpw = document.getElementById('confirm_password');
if (cpw && pw) {
    cpw.addEventListener('input', function () {
        this.style.borderColor = (this.value === pw.value && this.value.length > 0) ? 'var(--success)' : 'var(--border-color)';
    });
}
</script>

<?php include 'includes/footer.php'; ?>
