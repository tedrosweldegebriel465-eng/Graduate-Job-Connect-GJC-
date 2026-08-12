<?php
/**
 * Graduate Job Connect — My Applications
 * Phase 5: Full ATS view with withdraw action, filters, pagination
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Controllers\JobController;
use App\Models\ApplicationModel;
use App\Helpers\ViewHelper;

AuthMiddleware::require('graduate', '../login.php');

$pdo        = getDBConnection();
$graduateId = currentUserId();
$controller = new JobController($pdo);
$appModel   = new ApplicationModel($pdo);

$message      = '';
$messageType  = '';

// ─── Withdraw ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_id'])) {
    $result = $controller->withdrawApplication((int) $_POST['withdraw_id'], $graduateId);
    $message     = $result['success'] ? 'Application withdrawn.' : ($result['error'] ?? 'Error.');
    $messageType = $result['success'] ? 'info' : 'error';
}

// ─── Filters ───────────────────────────────────────────────────────────────
$statusFilter = in_array($_GET['status'] ?? '', ['pending','shortlisted','accepted','rejected','withdrawn','all',''])
    ? ($_GET['status'] ?? '') : '';
$page         = max(1, (int) ($_GET['page'] ?? 1));

// ─── Data — status filter passed to DB query, not applied in PHP ───────────
$stats  = $appModel->getGraduateStats($graduateId);
$result = $appModel->getGraduateApplications(
    $graduateId,
    $page,
    15,
    ($statusFilter && $statusFilter !== 'all') ? $statusFilter : ''
);
$apps   = $result['data'];

$csrf       = AuthMiddleware::csrfToken();
$page_title = 'My Applications';
$css_path   = '../assets/css/';
$js_path    = '../assets/js/';
$home_path  = '../';
$logout_path= '../';
include '../includes/header.php';
?>

<div class="container" style="padding:2rem 1rem;">

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
        <div>
            <h1 style="margin:0;">📋 My Applications</h1>
            <p style="color:var(--dark-gray);margin:.25rem 0 0;">
                <?php echo number_format($stats['total'] ?? 0); ?> total application<?php echo ($stats['total'] ?? 0) !== 1 ? 's' : ''; ?>
            </p>
        </div>
        <a href="jobs.php" class="btn btn-primary">💼 Browse More Jobs</a>
    </div>

    <!-- Flash -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible">
        <span><?php echo ViewHelper::e($message); ?></span>
        <button class="alert-close">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Stats row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <?php
        $statItems = [
            ['pending',     '⏳', 'Pending',     '#fff3cd', '#856404'],
            ['shortlisted', '⭐', 'Shortlisted', '#cce5ff', '#004085'],
            ['accepted',    '✅', 'Accepted',    '#d4edda', '#155724'],
            ['rejected',    '❌', 'Rejected',    '#f8d7da', '#721c24'],
        ];
        foreach ($statItems as [$key, $icon, $label, $bg, $color]):
        $count = (int) ($stats[$key] ?? 0);
        ?>
        <div style="background:<?php echo $bg; ?>;color:<?php echo $color; ?>;
                    padding:1rem;border-radius:var(--radius-lg);text-align:center;
                    cursor:pointer;transition:var(--transition);border:2px solid transparent;"
             onclick="filterStatus('<?php echo $key; ?>')"
             title="Filter by <?php echo $label; ?>">
            <div style="font-size:1.5rem;"><?php echo $icon; ?></div>
            <div style="font-size:1.5rem;font-weight:700;"><?php echo $count; ?></div>
            <div style="font-size:.8rem;font-weight:600;"><?php echo $label; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Status filter bar -->
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem;align-items:center;">
        <span style="font-size:.9rem;color:var(--dark-gray);">Filter:</span>
        <?php
        $filterOptions = ['all' => 'All', 'pending' => '⏳ Pending', 'shortlisted' => '⭐ Shortlisted',
                          'accepted' => '✅ Accepted', 'rejected' => '❌ Rejected', 'withdrawn' => '🚫 Withdrawn'];
        foreach ($filterOptions as $val => $lbl):
        $active = ($statusFilter === $val || ($val === 'all' && !$statusFilter));
        ?>
        <a href="my-applications.php?status=<?php echo $val; ?>"
           class="btn btn-sm <?php echo $active ? 'btn-primary' : 'btn-outline'; ?>"
           style="font-size:.82rem;">
            <?php echo $lbl; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Applications list -->
    <?php if (empty($apps)): ?>
    <div class="card" style="text-align:center;padding:3rem 1rem;">
        <div style="font-size:4rem;margin-bottom:1rem;">📭</div>
        <h3>No Applications <?php echo $statusFilter ? 'with this status' : 'Yet'; ?></h3>
        <p style="color:var(--dark-gray);">
            <?php echo $statusFilter ? 'Try a different filter.' : 'Start applying for jobs!'; ?>
        </p>
        <a href="jobs.php" class="btn btn-primary">Browse Jobs</a>
    </div>

    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:1rem;">
        <?php foreach ($apps as $app):
            $days = $app['application_deadline'] ? ViewHelper::daysUntil($app['application_deadline']) : null;
        ?>
        <div class="card" style="border-left:4px solid <?php
            echo match($app['status']) {
                'accepted'    => 'var(--success)',
                'shortlisted' => 'var(--info)',
                'rejected'    => 'var(--danger)',
                'withdrawn'   => 'var(--gray)',
                default       => 'var(--warning)'
            };
        ?>;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">

                <!-- Job info -->
                <div style="flex:1;min-width:200px;">
                    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.4rem;">
                        <div>
                            <h3 style="margin:0;font-size:1.05rem;color:var(--primary);">
                                <?php echo ViewHelper::e($app['job_title']); ?>
                            </h3>
                            <div style="font-size:.85rem;color:var(--dark-gray);">
                                🏢 <?php echo ViewHelper::e($app['company_name'] ?? $app['employer_name'] ?? '—'); ?>
                                · 📍 <?php echo ViewHelper::e($app['location'] ?? ''); ?>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.5rem;">
                        <span class="badge badge-draft" style="text-transform:none;font-size:.75rem;">
                            <?php echo ViewHelper::e(getJobTypes()[$app['job_type']] ?? $app['job_type'] ?? ''); ?>
                        </span>
                        <?php if ($app['salary_min'] || $app['salary_max']): ?>
                        <span class="badge badge-shortlisted" style="text-transform:none;font-size:.75rem;">
                            <?php echo ViewHelper::formatSalary($app['salary_min'], $app['salary_max'], $app['salary_currency'] ?? 'ETB'); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top:.6rem;font-size:.82rem;color:var(--dark-gray);">
                        Applied <?php echo ViewHelper::timeAgo($app['applied_at']); ?>
                        · <?php echo ViewHelper::formatDate($app['applied_at']); ?>
                    </div>
                </div>

                <!-- Status + action -->
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.6rem;flex-shrink:0;">
                    <?php echo ViewHelper::appStatusBadge($app['status']); ?>

                    <?php if (in_array($app['status'], ['pending', 'shortlisted'], true)): ?>
                    <form method="POST" onsubmit="return confirm('Withdraw this application?')">
                        <input type="hidden" name="csrf_token"  value="<?php echo $csrf; ?>">
                        <input type="hidden" name="withdraw_id" value="<?php echo $app['id']; ?>">
                        <button type="submit" class="btn btn-sm"
                                style="background:#f8d7da;color:#721c24;border:none;cursor:pointer;">
                            🚫 Withdraw
                        </button>
                    </form>
                    <?php endif; ?>

                    <a href="jobs.php?view=<?php echo $app['job_id']; ?>"
                       style="font-size:.82rem;color:var(--primary);">View job →</a>
                </div>

            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php echo ViewHelper::pagination($result, 'my-applications.php?' . http_build_query(array_filter(['status' => $statusFilter]))); ?>
    <?php endif; ?>

    <!-- Tips -->
    <div class="card" style="margin-top:2rem;background:var(--light-gray);box-shadow:none;">
        <h4 style="margin:0 0 .75rem;">💡 Tips to Improve Your Success Rate</h4>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;font-size:.9rem;color:var(--dark-gray);">
            <div>📝 Write a tailored cover letter for each application.</div>
            <div>🔄 Keep your skills and CV up to date on your <a href="profile.php">profile</a>.</div>
            <div>📬 Apply to multiple positions that match your skills.</div>
            <div>⏰ Apply early — most positions fill fast.</div>
        </div>
    </div>

</div>

<script>
function filterStatus(status) {
    window.location = 'my-applications.php?status=' + status;
}
document.querySelectorAll('.alert-close').forEach(b => {
    b.addEventListener('click', () => b.closest('.alert').remove());
});
</script>

<?php include '../includes/footer.php'; ?>
