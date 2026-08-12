<?php
/**
 * Graduate Job Connect — Graduate Profile
 * MVC: ProfileController handles save + CV upload
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Controllers\ProfileController;
use App\Models\GraduateProfileModel;
use App\Helpers\ViewHelper;

AuthMiddleware::require('graduate', '../login.php');

$pdo        = getDBConnection();
$userId     = currentUserId();
$controller = new ProfileController($pdo);
$profModel  = new GraduateProfileModel($pdo);

$message   = '';
$msgType   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result  = $controller->saveGraduateProfile($userId);
    $message = $result['success'] ?? $result['error'] ?? '';
    $msgType = isset($result['success']) ? 'success' : 'error';
}

$profile    = $profModel->getFullProfile($userId);
$completion = $controller->getGraduateCompletion($userId);
$csrf       = AuthMiddleware::csrfToken();

$page_title    = 'My Profile';
$css_path      = '../assets/css/';
$js_path       = '../assets/js/';
$home_path     = '../';
$logout_path   = '../';
$graduate_path = '';
include '../includes/header.php';
?>

<div class="container" style="max-width:840px;padding:2rem 1rem;">

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;
                gap:1rem;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:2px solid var(--light-gray);">
        <div>
            <h1 style="margin:0;">👤 My Profile</h1>
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

        <!-- ── Personal Info ────────────────────────────────────────────────── -->
        <div class="form-section" style="margin-bottom:1.5rem;">
            <h4 class="form-section-title">🎓 Academic Information</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="university">University</label>
                    <input type="text" id="university" name="university" class="form-control"
                           value="<?php echo ViewHelper::e($profile['university'] ?? ''); ?>"
                           placeholder="e.g., Addis Ababa University"
                           list="uni-list">
                    <datalist id="uni-list">
                        <?php foreach (['Addis Ababa University','Bahir Dar University','Haramaya University',
                                        'Jimma University','Mekelle University','Hawassa University',
                                        'Adama Science and Technology University','Gondar University',
                                        'Dire Dawa University','St. Mary\'s University',
                                        'Debre Berhan University','Debre Markos University'] as $u): ?>
                            <option value="<?php echo ViewHelper::e($u); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label for="department">Department / Field of Study</label>
                    <input type="text" id="department" name="department" class="form-control"
                           value="<?php echo ViewHelper::e($profile['department'] ?? ''); ?>"
                           placeholder="e.g., Computer Science, Business Administration">
                </div>
            </div>
            <div class="form-group" style="max-width:220px;">
                <label for="graduation_year">Graduation Year</label>
                <select id="graduation_year" name="graduation_year" class="form-control">
                    <option value="">— Select year —</option>
                    <?php for ($y = (int)date('Y') - 10; $y <= (int)date('Y') + 5; $y++): ?>
                        <option value="<?php echo $y; ?>"
                            <?php echo (($profile['graduation_year'] ?? '') == $y) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <!-- ── Skills & Bio ──────────────────────────────────────────────────── -->
        <div class="form-section" style="margin-bottom:1.5rem;">
            <h4 class="form-section-title">💡 Skills &amp; About Me</h4>
            <div class="form-group">
                <label for="skills">Skills &amp; Competencies <span style="color:var(--ethiopian-red);">*</span></label>
                <input type="text" id="skills" name="skills" class="form-control"
                       value="<?php echo ViewHelper::e($profile['skills'] ?? ''); ?>"
                       placeholder="PHP, JavaScript, Python, MySQL, React (comma-separated)" required>
                <small class="form-hint">Separate with commas. These appear as tags to employers.</small>
                <?php if (!empty($profile['skills'])): ?>
                <div style="margin-top:.6rem;">
                    <?php echo ViewHelper::skillTags($profile['skills']); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="bio">Bio / About Me</label>
                <textarea id="bio" name="bio" class="form-control" rows="4"
                          placeholder="Write a short professional summary about yourself, your goals, and what you bring to an employer…"
                          maxlength="1000"><?php echo ViewHelper::e($profile['bio'] ?? ''); ?></textarea>
                <small class="form-hint">Max 1000 characters.</small>
            </div>
        </div>

        <!-- ── Links ─────────────────────────────────────────────────────────── -->
        <div class="form-section" style="margin-bottom:1.5rem;">
            <h4 class="form-section-title">🔗 Professional Links</h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="linkedin_url">LinkedIn Profile</label>
                    <input type="url" id="linkedin_url" name="linkedin_url" class="form-control"
                           value="<?php echo ViewHelper::e($profile['linkedin_url'] ?? ''); ?>"
                           placeholder="https://linkedin.com/in/yourname">
                </div>
                <div class="form-group">
                    <label for="github_url">GitHub Profile</label>
                    <input type="url" id="github_url" name="github_url" class="form-control"
                           value="<?php echo ViewHelper::e($profile['github_url'] ?? ''); ?>"
                           placeholder="https://github.com/yourname">
                </div>
            </div>
            <div class="form-group">
                <label for="portfolio_url">Portfolio / Personal Website</label>
                <input type="url" id="portfolio_url" name="portfolio_url" class="form-control"
                       value="<?php echo ViewHelper::e($profile['portfolio_url'] ?? ''); ?>"
                       placeholder="https://yourportfolio.com">
            </div>
        </div>

        <!-- ── CV Upload ──────────────────────────────────────────────────────── -->
        <div class="form-section" style="margin-bottom:1.5rem;">
            <h4 class="form-section-title">📄 CV / Resume</h4>

            <?php if (!empty($profile['cv_filename'])): ?>
            <div style="display:flex;align-items:center;gap:1rem;padding:1rem;
                        background:var(--success-bg);border-radius:var(--radius);
                        border-left:4px solid var(--success);margin-bottom:1rem;">
                <span style="font-size:2rem;">📄</span>
                <div style="flex:1;">
                    <div style="font-weight:600;color:var(--success);">CV uploaded ✓</div>
                    <div style="font-size:.85rem;color:var(--dark-gray);">
                        <?php echo ViewHelper::e($profile['cv_filename']); ?>
                    </div>
                </div>
                <a href="../uploads/cv/<?php echo ViewHelper::e($profile['cv_filename']); ?>"
                   target="_blank" class="btn btn-success btn-sm">View CV</a>
            </div>
            <?php else: ?>
            <div style="padding:.75rem;background:var(--warning-bg);border-left:4px solid var(--warning);
                        border-radius:var(--radius);margin-bottom:1rem;font-size:.9rem;color:#856404;">
                ⚠️ No CV uploaded yet. Employers expect a CV — upload one to increase your chances.
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="cv_file">
                    <?php echo !empty($profile['cv_filename']) ? 'Replace CV' : 'Upload CV'; ?>
                    <span style="color:var(--dark-gray);font-weight:400;">(PDF only, max 5 MB)</span>
                </label>
                <input type="file" id="cv_file" name="cv_file" class="form-control"
                       accept=".pdf" style="padding:.5rem;">
                <div id="cv_preview" style="display:none;margin-top:.75rem;"></div>
            </div>
        </div>

        <!-- Submit -->
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary btn-lg">
                💾 Save Profile
            </button>
            <a href="dashboard.php" class="btn btn-outline btn-lg">Cancel</a>
        </div>
    </form>

</div>

<script>
// CV file preview
const cvInput   = document.getElementById('cv_file');
const cvPreview = document.getElementById('cv_preview');
if (cvInput) {
    cvInput.addEventListener('change', function () {
        const file = this.files[0];
        cvPreview.innerHTML = '';
        cvPreview.style.display = 'none';
        if (!file) return;
        if (file.type !== 'application/pdf') {
            cvInput.style.borderColor = 'var(--danger)';
            cvPreview.innerHTML = '<span style="color:var(--danger);font-size:.85rem;">⚠️ Only PDF files are allowed.</span>';
            cvPreview.style.display = 'block';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            cvInput.style.borderColor = 'var(--danger)';
            cvPreview.innerHTML = '<span style="color:var(--danger);font-size:.85rem;">⚠️ File exceeds 5 MB limit.</span>';
            cvPreview.style.display = 'block';
            return;
        }
        cvInput.style.borderColor = 'var(--success)';
        const kb = (file.size / 1024).toFixed(1);
        cvPreview.innerHTML = `
            <div style="display:flex;align-items:center;gap:.75rem;padding:.75rem;
                        background:var(--success-bg);border-radius:var(--radius);">
                <span style="font-size:1.5rem;">📄</span>
                <div>
                    <strong>${file.name}</strong><br>
                    <span style="font-size:.8rem;color:var(--dark-gray);">${kb} KB · PDF</span>
                </div>
            </div>`;
        cvPreview.style.display = 'block';
    });
}
// Bio counter
const bio = document.getElementById('bio');
if (bio) {
    const hint = bio.nextElementSibling;
    bio.addEventListener('input', () => {
        hint.textContent = `${bio.value.length} / 1000 characters`;
    });
}
// Dismiss flash
document.querySelectorAll('.alert-close').forEach(b =>
    b.addEventListener('click', () => b.closest('.alert').remove())
);
</script>

<?php include '../includes/footer.php'; ?>
