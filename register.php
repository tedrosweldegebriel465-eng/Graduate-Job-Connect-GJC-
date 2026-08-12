<?php
/**
 * Graduate Job Connect — Registration Page
 */

require_once __DIR__ . '/config/bootstrap.php';

use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;
use App\Helpers\ViewHelper;

// Already logged in → go to dashboard
if (isLoggedIn()) {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base   = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header('Location: ' . $base . '/' . currentRole() . '/dashboard.php');
    exit();
}

$pdo             = getDBConnection();
$controller      = new AuthController($pdo);
$error_message   = '';
$success_message = '';
$form_data       = [];
$error_is_html   = false; // flag — set true when error contains safe HTML (migration link)

// Pre-select role from URL ?role=graduate or ?role=employer
$selected_role = in_array($_GET['role'] ?? '', ['graduate', 'employer'])
    ? $_GET['role'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = $_POST;
    $result    = $controller->handleRegister();

    if (isset($result['error'])) {
        $error_message = $result['error'];
        // If error contains an anchor tag it's our safe HTML (migration link)
        $error_is_html = str_contains($error_message, '<a ');
    } elseif (isset($result['success'])) {
        $success_message = $result['success'];
        $form_data       = [];
    }
}

$csrf_token   = AuthMiddleware::csrfToken();
$page_title   = 'Create Account';
$current_year = (int) date('Y');
include 'includes/header.php';
?>

<div class="auth-container" style="padding:2rem 1rem;">
<div class="auth-card" style="max-width:720px;width:100%;">

    <div class="auth-header">
        <div class="auth-logo">
            <span class="logo-flag"></span>
            <span class="auth-logo-text">
                Graduate Job Connect
                <small class="auth-logo-tagline">GJC | Connecting Graduates with Opportunities</small>
            </span>
        </div>
        <h3>Create Your Account</h3>
        <p class="auth-subtitle">Join graduates and employers across Ethiopia</p>
    </div>

    <!-- ── Error message ─────────────────────────────────────────────────── -->
    <?php if ($error_message): ?>
    <div class="alert alert-error" style="margin-bottom:1.25rem;">
        <span class="alert-icon">⚠️</span>
        <div>
            <?php
            // Safe HTML (our own migration link) is rendered as-is;
            // plain text errors are escaped.
            if ($error_is_html) {
                echo $error_message; // already safe — our own code generated it
            } else {
                // nl2br so multi-line validation errors display cleanly
                echo nl2br(htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'));
            }
            ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Success message ───────────────────────────────────────────────── -->
    <?php if ($success_message): ?>
    <div class="alert alert-success" style="margin-bottom:1.25rem;">
        <span class="alert-icon">✅</span>
        <div>
            <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
            <div style="margin-top:.75rem;">
                <a href="login.php" class="btn btn-primary btn-sm">Sign In Now</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$success_message): ?>
    <!-- ── Registration form ─────────────────────────────────────────────── -->
    <form method="POST" id="registrationForm"
          style="background:none;box-shadow:none;padding:0;max-width:none;margin:0;"
          novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <!-- Basic Info -->
        <div class="form-section">
            <h4 class="form-section-title">Basic Information</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="name">
                        Full Name <span style="color:var(--ethiopian-red)">*</span>
                    </label>
                    <input type="text" id="name" name="name" class="form-control"
                           value="<?php echo ViewHelper::e($form_data['name'] ?? ''); ?>"
                           placeholder="Your full name"
                           required minlength="2" maxlength="100" autofocus>
                </div>
                <div class="form-group">
                    <label for="email">
                        Email Address <span style="color:var(--ethiopian-red)">*</span>
                    </label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?php echo ViewHelper::e($form_data['email'] ?? ''); ?>"
                           placeholder="you@example.com"
                           required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control"
                           value="<?php echo ViewHelper::e($form_data['phone'] ?? ''); ?>"
                           placeholder="09XXXXXXXX or +251XXXXXXXXX">
                    <small class="form-hint">Optional · Ethiopian or international format</small>
                </div>
                <div class="form-group">
                    <label for="role">
                        I am a <span style="color:var(--ethiopian-red)">*</span>
                    </label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="">— Select role —</option>
                        <option value="graduate"
                            <?php echo (($form_data['role'] ?? $selected_role) === 'graduate') ? 'selected' : ''; ?>>
                            🎓 Graduate (Job Seeker)
                        </option>
                        <option value="employer"
                            <?php echo (($form_data['role'] ?? $selected_role) === 'employer') ? 'selected' : ''; ?>>
                            🏢 Employer (Recruiter)
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Graduate Fields -->
        <div id="graduateFields" class="form-section" style="display:none;">
            <h4 class="form-section-title">🎓 Graduate Information</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="university">
                        University <span style="color:var(--ethiopian-red)">*</span>
                    </label>
                    <input type="text" id="university" name="university" class="form-control"
                           value="<?php echo ViewHelper::e($form_data['university'] ?? ''); ?>"
                           placeholder="e.g., Addis Ababa University"
                           list="uni-list">
                    <datalist id="uni-list">
                        <?php foreach ([
                            'Addis Ababa University','Bahir Dar University',
                            'Haramaya University','Jimma University',
                            'Mekelle University','Hawassa University',
                            'Adama Science and Technology University',
                            'Gondar University','Dire Dawa University',
                            'Debre Berhan University','Debre Markos University',
                            "St. Mary's University"
                        ] as $u): ?>
                            <option value="<?php echo ViewHelper::e($u); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label for="department">Department / Field of Study</label>
                    <input type="text" id="department" name="department" class="form-control"
                           value="<?php echo ViewHelper::e($form_data['department'] ?? ''); ?>"
                           placeholder="e.g., Computer Science, Business Administration">
                </div>
            </div>
            <div class="form-group" style="max-width:260px;">
                <label for="graduation_year">
                    Graduation Year <span style="color:var(--ethiopian-red)">*</span>
                </label>
                <select id="graduation_year" name="graduation_year" class="form-control">
                    <option value="">— Select year —</option>
                    <?php for ($y = $current_year - 10; $y <= $current_year + 5; $y++): ?>
                        <option value="<?php echo $y; ?>"
                            <?php echo (($form_data['graduation_year'] ?? '') == $y) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <!-- Employer Fields -->
        <div id="employerFields" class="form-section" style="display:none;">
            <h4 class="form-section-title">🏢 Company Information</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="company_name">
                        Company Name <span style="color:var(--ethiopian-red)">*</span>
                    </label>
                    <input type="text" id="company_name" name="company_name" class="form-control"
                           value="<?php echo ViewHelper::e($form_data['company_name'] ?? ''); ?>"
                           placeholder="Your company name">
                </div>
                <div class="form-group">
                    <label for="company_website">Company Website</label>
                    <input type="url" id="company_website" name="company_website" class="form-control"
                           value="<?php echo ViewHelper::e($form_data['company_website'] ?? ''); ?>"
                           placeholder="https://yourcompany.com">
                    <small class="form-hint">Optional</small>
                </div>
            </div>
        </div>

        <!-- Password -->
        <div class="form-section">
            <h4 class="form-section-title">🔐 Password</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">
                        Password <span style="color:var(--ethiopian-red)">*</span>
                    </label>
                    <div style="position:relative;">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Create a strong password"
                               required minlength="8"
                               style="padding-right:3rem;">
                        <button type="button" tabindex="-1"
                                onclick="togglePw('password')"
                                style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;display:flex;align-items:center;"
                                aria-label="Toggle password visibility">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--dark-gray);">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <div style="margin-top:.5rem;">
                        <div class="strength-bar" id="strengthBar"></div>
                        <div style="font-size:.78rem;margin-top:.25rem;color:var(--dark-gray);">
                            Strength: <span id="strengthText">—</span>
                        </div>
                    </div>
                    <small class="form-hint">
                        Min 8 chars · uppercase letter · number · special character (!@#$…)
                    </small>
                </div>
                <div class="form-group">
                    <label for="confirm_password">
                        Confirm Password <span style="color:var(--ethiopian-red)">*</span>
                    </label>
                    <div style="position:relative;">
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="form-control"
                               placeholder="Repeat your password"
                               required
                               style="padding-right:3rem;">
                        <button type="button" tabindex="-1"
                                onclick="togglePw('confirm_password')"
                                style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;display:flex;align-items:center;"
                                aria-label="Toggle password visibility">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--dark-gray);">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <div id="matchMsg" style="font-size:.78rem;margin-top:.3rem;min-height:18px;"></div>
                </div>
            </div>
        </div>

        <!-- Terms -->
        <div class="form-group" style="display:flex;align-items:flex-start;gap:.75rem;margin-bottom:1.25rem;">
            <input type="checkbox" id="agree_terms" name="agree_terms" value="1"
                   style="width:18px;height:18px;accent-color:var(--primary);cursor:pointer;flex-shrink:0;margin-top:2px;"
                   <?php echo !empty($form_data['agree_terms']) ? 'checked' : ''; ?>
                   required>
            <label for="agree_terms" style="cursor:pointer;font-size:.9rem;line-height:1.5;margin:0;">
                I agree to the
                <a href="#" style="color:var(--primary);">Terms of Service</a>
                and
                <a href="#" style="color:var(--primary);">Privacy Policy</a>
                <span style="color:var(--ethiopian-red);">*</span>
            </label>
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-full btn-lg" id="submitBtn">
                Create Account
            </button>
        </div>

        <p style="text-align:center;font-size:.82rem;color:var(--dark-gray);margin-top:.75rem;">
            By creating an account you agree to our terms. Your data is stored securely.
        </p>
    </form>
    <?php endif; ?>

    <div class="auth-footer">
        <p>Already have an account?
            <a href="login.php" class="auth-link">Sign in here</a>
        </p>
    </div>
</div>
</div>

<script>
(function () {
    'use strict';

    // ── Role field toggle ──────────────────────────────────────────────────
    const roleSelect = document.getElementById('role');
    const gradDiv    = document.getElementById('graduateFields');
    const empDiv     = document.getElementById('employerFields');
    const uniInput   = document.getElementById('university');
    const yearSel    = document.getElementById('graduation_year');
    const compInput  = document.getElementById('company_name');

    function toggleFields() {
        const v = roleSelect ? roleSelect.value : '';
        if (gradDiv) gradDiv.style.display = v === 'graduate' ? 'block' : 'none';
        if (empDiv)  empDiv.style.display  = v === 'employer' ? 'block' : 'none';
        if (uniInput)  uniInput.required  = v === 'graduate';
        if (yearSel)   yearSel.required   = v === 'graduate';
        if (compInput) compInput.required = v === 'employer';
    }

    if (roleSelect) {
        roleSelect.addEventListener('change', toggleFields);
        toggleFields(); // run on page load to restore state
    }

    // ── Password strength ─────────────────────────────────────────────────
    const pwInput = document.getElementById('password');
    const bar     = document.getElementById('strengthBar');
    const txt     = document.getElementById('strengthText');

    function checkStrength(p) {
        let s = 0;
        if (p.length >= 8)           s++;
        if (/[a-z]/.test(p))         s++;
        if (/[A-Z]/.test(p))         s++;
        if (/[0-9]/.test(p))         s++;
        if (/[^A-Za-z0-9]/.test(p))  s++;
        return s;
    }

    if (pwInput && bar && txt) {
        pwInput.addEventListener('input', function () {
            const s = checkStrength(this.value);
            const levels = ['', 'weak',  'fair',   'good',   'strong', 'strong'];
            const labels = ['—','Weak ❌','Fair ⚠️','Good 👍','Strong 💪','Strong 💪'];
            bar.className  = 'strength-bar ' + (levels[s] || '');
            txt.textContent = labels[s] || '—';
        });
    }

    // ── Password match indicator ──────────────────────────────────────────
    const cpwInput = document.getElementById('confirm_password');
    const matchMsg = document.getElementById('matchMsg');

    function checkMatch() {
        if (!cpwInput || !pwInput || !matchMsg) return;
        if (!cpwInput.value) { matchMsg.textContent = ''; return; }
        const ok = cpwInput.value === pwInput.value;
        matchMsg.textContent   = ok ? '✅ Passwords match' : '❌ Passwords do not match';
        matchMsg.style.color   = ok ? 'var(--success)' : 'var(--danger)';
        cpwInput.style.borderColor = ok ? 'var(--success)' : 'var(--ethiopian-red)';
    }

    if (cpwInput) cpwInput.addEventListener('input', checkMatch);
    if (pwInput)  pwInput.addEventListener('input',  checkMatch);

    // ── Show/hide password toggle ─────────────────────────────────────────
    window.togglePw = function (id) {
        const el = document.getElementById(id);
        if (el) el.type = el.type === 'password' ? 'text' : 'password';
    };

    // ── Submit guard ──────────────────────────────────────────────────────
    const form = document.getElementById('registrationForm');
    const btn  = document.getElementById('submitBtn');
    if (form && btn) {
        form.addEventListener('submit', function () {
            setTimeout(function () {
                btn.disabled    = true;
                btn.textContent = 'Creating account…';
            }, 10);
        });
    }

})();
</script>

<?php include 'includes/footer.php'; ?>
