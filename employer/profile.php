<?php
/**
 * Graduate Job Connect — Employer Profile
 * MVC: ProfileController handles save + logo upload
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Controllers\ProfileController;
use App\Models\EmployerProfileModel;
use App\Helpers\ViewHelper;

AuthMiddleware::require('employer', '../login.php');

$pdo        = getDBConnection();
$userId     = currentUserId();
$controller = new ProfileController($pdo);
$profModel  = new EmployerProfileModel($pdo);

$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result  = $controller->saveEmployerProfile($userId);
    $message = $result['success'] ?? $result['error'] ?? '';
    $msgType = isset($result['success']) ? 'success' : 'error';
}

$profile    = $profModel->getFullProfile($userId);
$completion = $controller->getEmployerCompletion($userId);
$csrf       = AuthMiddleware::csrfToken();

$page_title    = 'Company Profile';
$css_path      = '../assets/css/';
$js_path       = '../assets/js/';
$home_path     = '../';
$logout_path   = '../';
$employer_path = '';
include '../includes/header.php';
?>

<div class="container" style="max-width:840px;padding:2rem 1rem;">

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;
                gap:1rem;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:2px solid var(--light-gray);">
        <div>
            <h1 style="margin:0;">🏢 Company Profile</h1>
            <p style="margin:.25rem 0 0;color:var(--dark-gray);">
                Profile completion:
                <strong style="color:<?php echo $completion >= 80 ? 'var(--success)' : ($completion >= 50 ? 'var(--warning)' : 'var(--danger)'); ?>">
                    <?php echo $completion; ?>%
                </strong>
            </p>
            <div style="margin-top:.4rem;max-width:300px;">
                <?php echo ViewHelper::completionBar($completion); ?>
            </div>
        </div>
        <a href="dashboard.php" class="btn btn-outline btn-sm">← Dashboard</a>
    </div>

    <!-- Flash -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $msgType; ?> alert-dismissible" style="margin-bottom:1.5rem;">
        <span class="alert-icon"><?php echo $msgType === 'success' ? '✅' : '⚠️'; ?></span>
        <span><?php echo ViewHelper::e($message); ?></span>
        <button class="alert-close">&times;</button>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data"
          style="background:none;box-shadow:none;padding:0;max-width:none;margin:0;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

        <!-- ── Company basics ────────────────────────────────────────────────── -->
        <div class="form-section" style="margin-bottom:1.5rem;">
            <h4 class="form-section-title">🏢 Company Information</h4>

            <div class="form-group">
                <label for="company_name">Company Name <span style="color:var(--ethiopian-red);">*</span></label>
                <input type="text" id="company_name" name="company_name" class="form-control"
                       value="<?php echo ViewHelper::e($profile['company_name'] ?? ''); ?>"
                       placeholder="Your company's legal or trading name" required>
            </div>

            <div class="form-group">
                <label for="company_description">About the Company</label>
                <textarea id="company_description" name="company_description" class="form-control" rows="5"
                          placeholder="Describe your company, culture, mission, and what makes it a great place to work…"
                          maxlength="1500"><?php echo ViewHelper::e($profile['company_description'] ?? ''); ?></textarea>
                <small class="form-hint">Max 1500 characters. Shown to graduates on your job listings.</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="company_location">Company Location</label>
                    <input type="text" id="company_location" name="company_location" class="form-control"
                           value="<?php echo ViewHelper::e($profile['company_location'] ?? ''); ?>"
                           placeholder="e.g., Addis Ababa, Ethiopia">
                </div>
                <div class="form-group">
                    <label for="phone">Contact Phone</label>
                    <input type="tel" id="phone" name="phone" class="form-control"
                           value="<?php echo ViewHelper::e($profile['phone'] ?? ''); ?>"
                           placeholder="+251XXXXXXXXX">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="industry">Industry</label>
                    <select id="industry" name="industry" class="form-control">
                        <option value="">— Select industry —</option>
                        <?php foreach (getIndustries() as $ind): ?>
                            <option value="<?php echo ViewHelper::e($ind); ?>"
                                <?php echo (($profile['industry'] ?? '') === $ind) ? 'selected' : ''; ?>>
                                <?php echo ViewHelper::e($ind); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="company_size">Company Size</label>
                    <select id="company_size" name="company_size" class="form-control">
                        <option value="">— Select size —</option>
                        <?php foreach (getCompanySizes() as $val => $lbl): ?>
                            <option value="<?php echo ViewHelper::e($val); ?>"
                                <?php echo (($profile['company_size'] ?? '') === $val) ? 'selected' : ''; ?>>
                                <?php echo ViewHelper::e($lbl); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="website">Company Website</label>
                <input type="url" id="website" name="website" class="form-control"
                       value="<?php echo ViewHelper::e($profile['website'] ?? ''); ?>"
                       placeholder="https://www.yourcompany.com">
            </div>
        </div>

        <!-- ── Logo upload ────────────────────────────────────────────────────── -->
        <div class="form-section" style="margin-bottom:1.5rem;">
            <h4 class="form-section-title">🖼️ Company Logo</h4>

            <?php if (!empty($profile['logo_filename'])): ?>
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
                <img src="../uploads/logos/<?php echo ViewHelper::e($profile['logo_filename']); ?>"
                     alt="Company logo"
                     style="width:80px;height:80px;border-radius:var(--radius);
                            object-fit:cover;border:2px solid var(--gray);">
                <div>
                    <div style="font-weight:600;color:var(--success);">Logo uploaded ✓</div>
                    <div style="font-size:.82rem;color:var(--dark-gray);">
                        <?php echo ViewHelper::e($profile['logo_filename']); ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div style="padding:.75rem;background:var(--info-bg);border-left:4px solid var(--info);
                        border-radius:var(--radius);margin-bottom:1rem;font-size:.9rem;color:#0c5460;">
                ℹ️ No logo yet. A logo helps graduates recognize and trust your brand.
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="logo_file">
                    <?php echo !empty($profile['logo_filename']) ? 'Replace Logo' : 'Upload Logo'; ?>
                    <span style="color:var(--dark-gray);font-weight:400;">
                        (JPEG/PNG/WebP, max 5 MB)
                    </span>
                </label>
                <input type="file" id="logo_file" name="logo_file" class="form-control"
                       accept=".jpg,.jpeg,.png,.webp" style="padding:.5rem;">
                <div id="logo_preview" style="display:none;margin-top:.75rem;"></div>
            </div>
        </div>

        <!-- Submit -->
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary btn-lg">💾 Save Profile</button>
            <a href="dashboard.php" class="btn btn-outline btn-lg">Cancel</a>
        </div>
    </form>

</div>

<script>
function logoPreview(input) {
    const preview = document.getElementById('logo_preview');
    preview.innerHTML = ''; preview.style.display = 'none';
    const file = input.files[0];
    if (!file) return;
    const allowed = ['image/jpeg','image/png','image/webp'];
    if (!allowed.includes(file.type)) {
        preview.innerHTML = '<span style="color:var(--danger);font-size:.85rem;">⚠️ Only JPEG/PNG/WebP allowed.</span>';
        preview.style.display = 'block'; return;
    }
    if (file.size > 5*1024*1024) {
        preview.innerHTML = '<span style="color:var(--danger);font-size:.85rem;">⚠️ File exceeds 5 MB.</span>';
        preview.style.display = 'block'; return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        preview.innerHTML = `<img src="${e.target.result}" alt="preview"
            style="width:80px;height:80px;border-radius:var(--radius);object-fit:cover;
                   border:2px solid var(--success);">`;
        preview.style.display = 'block';
    };
    reader.readAsDataURL(file);
}
document.getElementById('logo_file')?.addEventListener('change', function(){ logoPreview(this); });

const desc = document.getElementById('company_description');
if (desc) {
    const hint = desc.nextElementSibling;
    desc.addEventListener('input', () => {
        hint.textContent = `${desc.value.length} / 1500 characters`;
    });
}
document.querySelectorAll('.alert-close').forEach(b =>
    b.addEventListener('click', () => b.closest('.alert').remove())
);
</script>

<?php include '../includes/footer.php'; ?>
