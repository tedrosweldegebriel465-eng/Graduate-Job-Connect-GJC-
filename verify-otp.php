<?php
/**
 * Graduate Job Connect — OTP Verification Page
 * Secure 6-digit email OTP verification for registration & password reset
 */

require_once __DIR__ . '/config/bootstrap.php';

use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;
use App\Models\OtpModel;

$pdo        = getDBConnection();
$controller = new AuthController($pdo);

$email   = strtolower(trim($_GET['email'] ?? $_POST['email'] ?? ''));
$purpose = trim($_GET['purpose'] ?? $_POST['purpose'] ?? OtpModel::PURPOSE_EMAIL_VERIFICATION);
if (!in_array($purpose, [OtpModel::PURPOSE_EMAIL_VERIFICATION, OtpModel::PURPOSE_PASSWORD_RESET], true)) {
    $purpose = OtpModel::PURPOSE_EMAIL_VERIFICATION;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'resend') {
        $res = $controller->handleResendOtp();
        if (isset($res['error'])) $error = $res['error'];
        if (isset($res['success'])) $success = $res['success'];
    } else {
        $res = $controller->handleVerifyOtp();
        if (isset($res['error'])) $error = $res['error'];
    }
}

$csrf = AuthMiddleware::csrfToken();

function maskEmail(string $email): string {
    $parts = explode('@', $email);
    if (count($parts) < 2) return htmlspecialchars($email);
    $name   = $parts[0];
    $domain = $parts[1];
    $len    = strlen($name);
    if ($len <= 2) {
        $masked = substr($name, 0, 1) . '*';
    } else {
        $masked = substr($name, 0, 1) . str_repeat('*', min(4, $len - 2)) . substr($name, -1);
    }
    return htmlspecialchars($masked . '@' . $domain);
}

$page_title = ($purpose === OtpModel::PURPOSE_PASSWORD_RESET) ? 'Password Reset Verification' : 'Verify Email';
include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card" style="max-width: 460px;">

        <div class="auth-header">
            <div class="auth-logo">
                <span class="logo-flag"></span>
                <span class="auth-logo-text">
                    Graduate Job Connect
                    <small class="auth-logo-tagline">GJC | Connecting Graduates with Opportunities</small>
                </span>
            </div>
            <h3><?php echo ($purpose === OtpModel::PURPOSE_PASSWORD_RESET) ? 'Password Reset Verification' : 'Verify Your Email'; ?></h3>
            <p class="auth-subtitle">
                We've sent a 6-digit verification code to<br>
                <strong style="color:var(--text-dark);"><?php echo maskEmail($email); ?></strong>
            </p>
        </div>

        <?php
        $devOtp = $_SESSION['latest_otp_' . $email] ?? '';
        $isLocalDev = (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) || str_contains($_SERVER['HTTP_HOST'] ?? '', 'localhost'));
        if ($devOtp && $isLocalDev):
        ?>
        <!-- Real-Feel Email Push Notification Toast -->
        <div id="emailNotificationToast" class="email-toast">
            <div class="email-toast-header">
                <div class="email-toast-title">
                    <span class="email-toast-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    </span>
                    <strong>New Email Notification</strong>
                </div>
                <button type="button" class="email-toast-close" onclick="closeToast()">&times;</button>
            </div>
            <div class="email-toast-body">
                <div class="email-toast-sender">Graduate Job Connect</div>
                <div class="email-toast-subject">Your verification code: <strong class="email-toast-code"><?php echo htmlspecialchars($devOtp); ?></strong></div>
                <div class="email-toast-time">To: <?php echo htmlspecialchars($email); ?> · Just now</div>
            </div>
            <div class="email-toast-actions">
                <button type="button" class="btn-toast-autofill" onclick="autoFillOtp('<?php echo htmlspecialchars($devOtp); ?>')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Auto-Fill Verification Code
                </button>
            </div>
        </div>

        <style>
        .email-toast {
            position: fixed;
            top: 24px;
            right: 24px;
            width: 340px;
            background: #0f172a;
            color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.35), 0 10px 10px -5px rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 14px 16px;
            z-index: 99999;
            animation: toastSlideIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            font-family: system-ui, -apple-system, sans-serif;
        }
        @keyframes toastSlideIn {
            from { opacity: 0; transform: translateY(-20px) scale(0.95); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .email-toast-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            font-size: 0.78rem;
            color: #94a3b8;
        }
        .email-toast-title {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .email-toast-icon {
            display: inline-flex;
            align-items: center;
            color: #38bdf8;
        }
        .email-toast-close {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.2rem;
            cursor: pointer;
            line-height: 1;
            padding: 0 4px;
        }
        .email-toast-close:hover { color: #ffffff; }
        .email-toast-sender {
            font-size: 0.85rem;
            font-weight: 700;
            color: #f8fafc;
        }
        .email-toast-subject {
            font-size: 0.88rem;
            color: #cbd5e1;
            margin-top: 2px;
        }
        .email-toast-code {
            font-family: monospace;
            font-size: 1.1rem;
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.18);
            padding: 2px 6px;
            border-radius: 4px;
            letter-spacing: 2px;
        }
        .email-toast-time {
            font-size: 0.72rem;
            color: #64748b;
            margin-top: 4px;
        }
        .email-toast-actions {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .btn-toast-autofill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: 100%;
            justify-content: center;
            background: #0284c7;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 7px 12px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .btn-toast-autofill:hover { background: #0369a1; }
        @media (max-width: 480px) {
            .email-toast { top: 12px; right: 12px; left: 12px; width: auto; }
        }
        </style>

        <script>
        function closeToast() {
            const toast = document.getElementById('emailNotificationToast');
            if (toast) toast.style.display = 'none';
        }
        function autoFillOtp(code) {
            const container = document.getElementById('otpInputs');
            if (!container || !code) return;
            const boxes = Array.from(container.querySelectorAll('.otp-box'));
            for (let i = 0; i < boxes.length; i++) {
                if (code[i]) {
                    boxes[i].value = code[i];
                }
            }
            const form = document.getElementById('otpForm');
            if (form) form.submit();
        }
        </script>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" id="otpForm" class="auth-form" style="margin-bottom:1.5rem;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <input type="hidden" name="purpose" value="<?php echo htmlspecialchars($purpose); ?>">

            <div class="form-group" style="text-align:center;">
                <label style="display:block;margin-bottom:1rem;font-weight:600;">Enter 6-Digit Code</label>

                <div class="otp-input-group" id="otpInputs" style="display:flex;gap:8px;justify-content:center;margin-bottom:1rem;">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="text"
                           name="otp_code[]"
                           class="otp-box"
                           maxlength="1"
                           inputmode="numeric"
                           pattern="[0-9]*"
                           autocomplete="off"
                           required
                           aria-label="Digit <?php echo $i + 1; ?>"
                           style="width:48px;height:56px;text-align:center;font-size:1.4rem;font-weight:700;
                                  border:2px solid var(--border-color, #cbd5e1);border-radius:8px;
                                  background:var(--white);color:var(--text-dark);transition:all 0.2s ease;">
                    <?php endfor; ?>
                </div>

                <div style="font-size:0.85rem;color:var(--dark-gray);margin-top:0.5rem;">
                    Code expires in <span id="expiryTimer" style="font-weight:700;color:var(--primary);">10:00</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:1rem;">
                Verify Email
            </button>
        </form>

        <form method="POST" id="resendForm" style="text-align:center;padding-top:1rem;border-top:1px solid var(--light-gray);">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <input type="hidden" name="purpose" value="<?php echo htmlspecialchars($purpose); ?>">
            <input type="hidden" name="action" value="resend">

            <p style="font-size:0.9rem;color:var(--dark-gray);margin-bottom:0.5rem;">
                Didn't receive the code?
            </p>
            <button type="submit" id="resendBtn" class="btn btn-outline btn-sm" disabled>
                Resend Code <span id="cooldownTimer">(60s)</span>
            </button>
        </form>

        <div class="auth-footer" style="margin-top:1.5rem;">
            <p><a href="login.php" class="auth-link">&larr; Back to Login</a></p>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('otpInputs');
    if (!container) return;

    const boxes = Array.from(container.querySelectorAll('.otp-box'));
    const form  = document.getElementById('otpForm');

    // Focus first box
    boxes[0].focus();

    boxes.forEach((box, idx) => {
        box.addEventListener('input', function (e) {
            const val = this.value.replace(/[^0-9]/g, '');
            this.value = val;

            if (val && idx < boxes.length - 1) {
                boxes[idx + 1].focus();
            }

            // Auto submit when all 6 filled
            const filled = boxes.map(b => b.value).join('');
            if (filled.length === 6) {
                form.submit();
            }
        });

        box.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !this.value && idx > 0) {
                boxes[idx - 1].focus();
                boxes[idx - 1].value = '';
            } else if (e.key === 'ArrowLeft' && idx > 0) {
                boxes[idx - 1].focus();
            } else if (e.key === 'ArrowRight' && idx < boxes.length - 1) {
                boxes[idx + 1].focus();
            }
        });

        box.addEventListener('paste', function (e) {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '').trim();
            if (!pasteData) return;

            for (let i = 0; i < boxes.length; i++) {
                if (pasteData[i]) {
                    boxes[i].value = pasteData[i];
                }
            }
            const lastIdx = Math.min(pasteData.length - 1, boxes.length - 1);
            boxes[lastIdx].focus();

            if (pasteData.length >= 6) {
                form.submit();
            }
        });
    });

    // 10-minute expiry countdown
    let expirySecs = 600;
    const timerElem = document.getElementById('expiryTimer');
    const interval = setInterval(function () {
        expirySecs--;
        if (expirySecs <= 0) {
            clearInterval(interval);
            if (timerElem) timerElem.textContent = '00:00 (Expired)';
            return;
        }
        const m = String(Math.floor(expirySecs / 60)).padStart(2, '0');
        const s = String(expirySecs % 60).padStart(2, '0');
        if (timerElem) timerElem.textContent = `${m}:${s}`;
    }, 1000);

    // 60-second resend cooldown
    let cooldownSecs = 60;
    const resendBtn = document.getElementById('resendBtn');
    const cooldownElem = document.getElementById('cooldownTimer');

    const cooldownInterval = setInterval(function () {
        cooldownSecs--;
        if (cooldownSecs <= 0) {
            clearInterval(cooldownInterval);
            if (resendBtn) resendBtn.disabled = false;
            if (cooldownElem) cooldownElem.textContent = '';
            return;
        }
        if (cooldownElem) cooldownElem.textContent = `(${cooldownSecs}s)`;
    }, 1000);
});
</script>

<?php include 'includes/footer.php'; ?>
