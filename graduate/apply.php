<?php
/**
 * Graduate Job Connect — Apply for Job
 * Phase 5: Cover letter + CSRF + new MVC wiring
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Controllers\JobController;
use App\Models\JobModel;
use App\Models\GraduateProfileModel;
use App\Helpers\ViewHelper;

AuthMiddleware::require('graduate', '../login.php');

$pdo        = getDBConnection();
$graduateId = currentUserId();
$controller = new JobController($pdo);
$jobModel   = new JobModel($pdo);
$profModel  = new GraduateProfileModel($pdo);

// job_id required
if (empty($_GET['job_id']) || !is_numeric($_GET['job_id'])) {
    header('Location: jobs.php'); exit();
}
$jobId = (int) $_GET['job_id'];

// Load job
$job = $jobModel->getJobDetail($jobId);
if (!$job || $job['status'] !== 'active') {
    header('Location: jobs.php'); exit();
}

// Already applied?
if ($controller->getApplicationModel()->hasApplied($jobId, $graduateId)) {
    header("Location: my-applications.php"); exit();
}

// Load graduate profile
$profile     = $profModel->findByUserId($graduateId);
$error       = '';
$submitted   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $controller->applyForJob($graduateId, $jobId);
    if ($result['success']) {
        $submitted = true;
    } else {
        $error = $result['error'];
    }
}

$csrf       = AuthMiddleware::csrfToken();
$page_title = 'Apply — ' . ($job['title'] ?? 'Job');
$css_path   = '../assets/css/';
$js_path    = '../assets/js/';
$home_path  = '../';
$logout_path= '../';
include '../includes/header.php';
?>

<div class="container" style="max-width:760px;padding:2rem 1rem;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem;">
        <h1 style="margin:0;">Apply for Job</h1>
        <a href="jobs.php?view=<?php echo $jobId; ?>" class="btn btn-outline btn-sm">← Back</a>
    </div>

    <?php if ($submitted): ?>
    <!-- ── Success state ──────────────────────────────────────────────────── -->
    <div class="card" style="text-align:center;padding:3rem 1rem;">
        <div style="font-size:4rem;margin-bottom:1rem;">🎉</div>
        <h2 style="color:var(--success);">Application Submitted!</h2>
        <p style="color:var(--dark-gray);max-width:420px;margin:0 auto 1.5rem;">
            Your application for <strong><?php echo ViewHelper::e($job['title']); ?></strong> has been sent.
            The employer will review it and update your status.
        </p>
        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
            <a href="my-applications.php" class="btn btn-primary">📋 Track Applications</a>
            <a href="jobs.php"            class="btn btn-outline">🔍 Browse More Jobs</a>
        </div>
    </div>

    <?php else: ?>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <span class="alert-icon">⚠️</span>
        <span><?php echo ViewHelper::e($error); ?></span>
    </div>
    <?php endif; ?>

    <!-- ── Job summary card ───────────────────────────────────────────────── -->
    <div class="card" style="border-left:4px solid var(--primary);margin-bottom:1.5rem;">
        <div style="display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
            <?php if (!empty($job['logo_filename'])): ?>
            <img src="../uploads/logos/<?php echo ViewHelper::e($job['logo_filename']); ?>"
                 alt="logo" style="width:56px;height:56px;border-radius:8px;object-fit:cover;
                                   border:1px solid var(--gray);flex-shrink:0;">
            <?php else: ?>
            <div style="width:56px;height:56px;border-radius:8px;background:var(--primary-gradient);
                        display:flex;align-items:center;justify-content:center;
                        color:#fff;font-size:1.5rem;flex-shrink:0;">🏢</div>
            <?php endif; ?>
            <div style="flex:1;">
                <h2 style="margin:0 0 .25rem;font-size:1.3rem;color:var(--primary);">
                    <?php echo ViewHelper::e($job['title']); ?>
                </h2>
                <div style="color:var(--dark-gray);font-size:.9rem;display:flex;gap:1rem;flex-wrap:wrap;">
                    <span>🏢 <?php echo ViewHelper::e($job['company_name'] ?? $job['employer_name']); ?></span>
                    <span>📍 <?php echo ViewHelper::e($job['location']); ?></span>
                    <span>💼 <?php echo ViewHelper::e(getJobTypes()[$job['job_type']] ?? $job['job_type']); ?></span>
                    <?php if ($job['salary_min'] || $job['salary_max']): ?>
                    <span>💰 <?php echo ViewHelper::formatSalary($job['salary_min'], $job['salary_max'], $job['salary_currency'] ?? 'ETB'); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($job['days_remaining']) && $job['days_remaining'] >= 0): ?>
                    <span style="color:<?php echo $job['days_remaining'] <= 7 ? 'var(--warning)' : 'inherit'; ?>">
                        ⏰ <?php echo $job['days_remaining']; ?> days left
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── No profile warning ────────────────────────────────────────────── -->
    <?php if (!$profile): ?>
    <div class="card" style="text-align:center;padding:2rem;border-left:4px solid var(--warning);">
        <div style="font-size:3rem;margin-bottom:.75rem;">⚠️</div>
        <h3>Complete Your Profile First</h3>
        <p style="color:var(--dark-gray);">You need a graduate profile before applying for jobs.</p>
        <a href="profile.php" class="btn btn-primary">Create Profile</a>
    </div>

    <?php else: ?>

    <!-- ── Profile info preview ───────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:1.5rem;background:var(--light-gray);">
        <h4 style="margin:0 0 .75rem;font-size:.95rem;color:var(--primary);">📎 Your Profile Information</h4>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;font-size:.9rem;">
            <div><strong>Name:</strong> <?php echo ViewHelper::e($_SESSION['user_name']); ?></div>
            <div><strong>Email:</strong> <?php echo ViewHelper::e($_SESSION['user_email']); ?></div>
            <?php if ($profile['university']): ?>
            <div><strong>University:</strong> <?php echo ViewHelper::e($profile['university']); ?></div>
            <?php endif; ?>
            <?php if ($profile['skills']): ?>
            <div style="grid-column:1/-1;">
                <strong>Skills:</strong> <?php echo ViewHelper::e(ViewHelper::truncate($profile['skills'], 80)); ?>
            </div>
            <?php endif; ?>
            <div>
                <strong>CV:</strong>
                <?php if ($profile['cv_filename']): ?>
                    <span style="color:var(--success);">✅ Uploaded</span>
                <?php else: ?>
                    <span style="color:var(--warning);">⚠️ Not uploaded
                        (<a href="profile.php">upload CV</a>)
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div style="margin-top:.75rem;">
            <a href="profile.php" style="font-size:.85rem;color:var(--primary);">Edit profile →</a>
        </div>
    </div>

    <!-- ── Application form ───────────────────────────────────────────────── -->
    <form method="POST" class="card" style="box-shadow:none;margin:0;padding:1.5rem;max-width:100%;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

        <h4 style="margin:0 0 1rem;color:var(--primary);">✍️ Cover Letter</h4>
        <div class="form-group">
            <textarea id="cover_letter" name="cover_letter" class="form-control"
                      rows="7"
                      placeholder="Introduce yourself and explain why you're a great fit for this role. Mention specific skills, achievements, or experiences that match the job requirements."
                      style="resize:vertical;"
                      maxlength="2000"><?php echo ViewHelper::e($_POST['cover_letter'] ?? ''); ?></textarea>
            <small class="form-hint">Optional but strongly recommended. Max 2000 characters.</small>
        </div>

        <div style="padding:1rem;background:var(--info-bg);border-radius:var(--radius);
                    border-left:4px solid var(--info);margin-bottom:1.5rem;font-size:.9rem;">
            <strong>By submitting, you confirm:</strong>
            <ul style="margin:.5rem 0 0 1rem;padding:0;color:var(--dark-gray);">
                <li>Your profile information is accurate</li>
                <li>You meet the position requirements</li>
                <li>You are genuinely interested in this role</li>
            </ul>
        </div>

        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary btn-lg">
                🚀 Submit Application
            </button>
            <a href="jobs.php" class="btn btn-outline btn-lg">Cancel</a>
        </div>
    </form>

    <?php endif; // profile check ?>
    <?php endif; // submitted ?>

</div>

<script>
// Character counter for cover letter
const ta  = document.getElementById('cover_letter');
if (ta) {
    const hint = ta.nextElementSibling;
    ta.addEventListener('input', () => {
        const left = 2000 - ta.value.length;
        hint.textContent = `${ta.value.length} / 2000 characters`;
        hint.style.color = left < 100 ? 'var(--warning)' : 'var(--dark-gray)';
    });
}
</script>

<?php include '../includes/footer.php'; ?>
