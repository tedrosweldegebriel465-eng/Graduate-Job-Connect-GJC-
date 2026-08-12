<?php
/**
 * Graduate Job Connect — Saved Jobs
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Models\SavedJobModel;
use App\Helpers\ViewHelper;

AuthMiddleware::require('graduate', '../login.php');

$pdo        = getDBConnection();
$graduateId = currentUserId();
$savedModel = new SavedJobModel($pdo);
$page       = max(1, (int)($_GET['page'] ?? 1));
$result     = $savedModel->getSavedJobs($graduateId, $page, 12);

$page_title = 'Saved Jobs';
$css_path   = '../assets/css/';
$home_path  = '../';
$logout_path= '../';
include '../includes/header.php';
?>

<div class="container" style="padding:2rem 1rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem;">
        <h1 style="margin:0;">❤️ Saved Jobs</h1>
        <a href="jobs.php" class="btn btn-primary">💼 Browse More</a>
    </div>

    <?php if (empty($result['data'])): ?>
    <div class="card" style="text-align:center;padding:3rem 1rem;">
        <div style="font-size:4rem;margin-bottom:1rem;">🤍</div>
        <h3>No saved jobs yet</h3>
        <p style="color:var(--dark-gray);">Tap the heart icon on any job to save it for later.</p>
        <a href="jobs.php" class="btn btn-primary">Browse Jobs</a>
    </div>
    <?php else: ?>
    <p style="color:var(--dark-gray);margin-bottom:1.5rem;">
        <?php echo number_format($result['total']); ?> saved job<?php echo $result['total'] !== 1 ? 's' : ''; ?>
    </p>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.5rem;margin-bottom:2rem;">
        <?php foreach ($result['data'] as $job):
            $days = $job['days_remaining'] ?? null;
        ?>
        <div class="card" style="border-left:4px solid var(--primary);display:flex;flex-direction:column;gap:.75rem;">
            <div>
                <h3 style="margin:0 0 .25rem;color:var(--primary);font-size:1.05rem;">
                    <?php echo ViewHelper::e($job['title']); ?>
                </h3>
                <div style="font-size:.85rem;color:var(--dark-gray);">
                    🏢 <?php echo ViewHelper::e($job['company_name'] ?? ''); ?>
                    · 📍 <?php echo ViewHelper::e($job['location']); ?>
                </div>
            </div>
            <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                <span class="badge badge-draft" style="text-transform:none;font-size:.75rem;">
                    <?php echo ViewHelper::e(getJobTypes()[$job['job_type']] ?? $job['job_type']); ?>
                </span>
                <?php if ($days !== null && $days >= 0 && $days <= 7): ?>
                <span class="badge badge-pending" style="font-size:.75rem;">⚠️ <?php echo $days; ?>d left</span>
                <?php elseif ($days !== null && $days < 0): ?>
                <span class="badge badge-closed" style="font-size:.75rem;">Expired</span>
                <?php endif; ?>
                <?php if ($job['has_applied']): ?>
                <span class="badge badge-active" style="font-size:.75rem;">✅ Applied</span>
                <?php endif; ?>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:auto;">
                <span style="font-size:.8rem;color:var(--dark-gray);">
                    Saved <?php echo ViewHelper::timeAgo($job['saved_at']); ?>
                </span>
                <?php if (!$job['has_applied'] && ($days === null || $days >= 0)): ?>
                <a href="apply.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary btn-sm">Apply →</a>
                <?php else: ?>
                <a href="jobs.php" class="btn btn-outline btn-sm">Browse</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php echo ViewHelper::pagination($result, 'saved-jobs.php'); ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
