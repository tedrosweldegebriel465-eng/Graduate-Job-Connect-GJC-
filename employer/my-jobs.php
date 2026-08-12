<?php
/**
 * Graduate Job Connect — Employer: My Jobs
 * Full job management with status toggle, delete, search, pagination
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Controllers\JobController;
use App\Models\JobModel;
use App\Helpers\ViewHelper;

AuthMiddleware::require('employer', '../login.php');

$pdo        = getDBConnection();
$employerId = currentUserId();
$controller = new JobController($pdo);
$jobModel   = new JobModel($pdo);

$message     = '';
$messageType = '';

// ─── Status toggle (POST — prevents CSRF via GET) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_job_id'])) {
    AuthMiddleware::verifyCsrf($_POST['csrf_token'] ?? '');
    $result      = $controller->toggleJobStatus((int)$_POST['toggle_job_id'], $employerId);
    $message     = $result['success']
        ? '✅ Job status changed to ' . ucfirst($result['status'] ?? '') . '.'
        : ('❌ ' . ($result['error'] ?? 'Error'));
    $messageType = $result['success'] ? 'success' : 'error';
}

// ─── Delete ───────────────────────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    AuthMiddleware::verifyCsrf($_GET['csrf'] ?? '');
    $result      = $controller->deleteJob((int)$_GET['delete'], $employerId, 'employer');
    $message     = $result['success'] ? '✅ Job deleted.' : ('❌ ' . ($result['error'] ?? 'Error'));
    $messageType = $result['success'] ? 'success' : 'error';
}

// ─── Filters ──────────────────────────────────────────────────────────────────
$statusFilter = trim($_GET['status'] ?? '');
$sort         = in_array($_GET['sort'] ?? '', ['created_at DESC','created_at ASC','application_count DESC'])
                ? $_GET['sort'] : 'created_at DESC';
$page         = max(1, (int)($_GET['page'] ?? 1));

$result = $jobModel->getEmployerJobs($employerId, $statusFilter, $sort, $page, 12);
$jobs   = $result['data'];
$stats  = $jobModel->getEmployerStats($employerId);
$csrf   = AuthMiddleware::csrfToken();

$page_title    = 'My Job Postings';
$css_path      = '../assets/css/';
$js_path       = '../assets/js/';
$home_path     = '../';
$logout_path   = '../';
$employer_path = '';
include '../includes/header.php';
?>

<div class="container" style="padding:2rem 1rem;">

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;
                gap:1rem;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:2px solid var(--light-gray);">
        <div>
            <h1 style="margin:0;">💼 My Job Postings</h1>
            <p style="color:var(--dark-gray);margin:.25rem 0 0;">
                <?php echo number_format($stats['total_jobs'] ?? 0); ?> job<?php echo ($stats['total_jobs'] ?? 0) != 1 ? 's' : ''; ?> posted
            </p>
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <a href="post-job.php"   class="btn btn-primary">➕ Post New Job</a>
            <a href="dashboard.php"  class="btn btn-outline">← Dashboard</a>
        </div>
    </div>

    <!-- Quick stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:.75rem;margin-bottom:1.5rem;">
        <?php foreach ([
            ['total_jobs',  '💼','Total',    '#e8f0fe','var(--primary)'],
            ['active_jobs', '✅','Active',   '#d4edda','var(--success)'],
            ['closed_jobs', '🔒','Closed',   '#f8d7da','var(--danger)'],
            ['draft_jobs',  '📝','Draft',    '#e2e3e5','#383d41'],
            ['total_views', '👁️','Views',    '#cce5ff','var(--info)'],
        ] as [$key,$icon,$lbl,$bg,$color]):
        $v = number_format($stats[$key] ?? 0);
        ?>
        <div style="background:<?php echo $bg;?>;color:<?php echo $color;?>;padding:.85rem;
                    border-radius:var(--radius-lg);text-align:center;">
            <div style="font-size:1.1rem;"><?php echo $icon;?></div>
            <div style="font-size:1.3rem;font-weight:700;line-height:1.2;"><?php echo $v;?></div>
            <div style="font-size:.75rem;font-weight:600;"><?php echo $lbl;?></div>
        </div>
        <?php endforeach;?>
    </div>

    <!-- Flash -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType;?> alert-dismissible" style="margin-bottom:1rem;">
        <span><?php echo ViewHelper::e($message);?></span>
        <button class="alert-close">&times;</button>
    </div>
    <?php endif;?>

    <!-- Filter bar -->
    <div style="background:var(--white);padding:1rem;border-radius:var(--radius-lg);
                box-shadow:var(--shadow-sm);margin-bottom:1.5rem;
                display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;">
        <?php $statuses = [''=> 'All Status','active'=>'✅ Active','closed'=>'🔒 Closed','draft'=>'📝 Draft'];
        foreach ($statuses as $val => $lbl):
            $active = $statusFilter === $val; ?>
        <a href="my-jobs.php?status=<?php echo $val;?>&sort=<?php echo urlencode($sort);?>"
           class="btn btn-sm <?php echo $active ? 'btn-primary' : 'btn-outline';?>"
           style="font-size:.82rem;">
            <?php echo $lbl;?>
        </a>
        <?php endforeach;?>

        <div style="margin-left:auto;display:flex;align-items:center;gap:.5rem;">
            <label style="font-size:.85rem;color:var(--dark-gray);">Sort:</label>
            <select class="form-control" style="height:36px;font-size:.85rem;width:auto;"
                    onchange="window.location='my-jobs.php?status=<?php echo urlencode($statusFilter);?>&sort='+this.value">
                <option value="created_at DESC" <?php echo $sort==='created_at DESC'?'selected':'';?>>Newest</option>
                <option value="created_at ASC"  <?php echo $sort==='created_at ASC' ?'selected':'';?>>Oldest</option>
                <option value="application_count DESC" <?php echo $sort==='application_count DESC'?'selected':'';?>>Most Applied</option>
            </select>
        </div>
    </div>

    <!-- Job cards -->
    <?php if (empty($jobs)): ?>
    <div class="card" style="text-align:center;padding:3rem 1rem;">
        <div style="font-size:4rem;margin-bottom:1rem;">📭</div>
        <h3>No Jobs <?php echo $statusFilter ? 'with this status' : 'Posted Yet'; ?></h3>
        <p style="color:var(--dark-gray);">
            <?php echo $statusFilter ? 'Try a different filter.' : 'Create your first job posting to start receiving applications.'; ?>
        </p>
        <a href="post-job.php" class="btn btn-primary">Post a Job</a>
    </div>

    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:1rem;">
        <?php foreach ($jobs as $job):
            $dr = $job['days_remaining'] ?? null;
            $isExpiring = $dr !== null && $dr >= 0 && $dr <= 7;
            $isExpired  = $dr !== null && $dr < 0;
        ?>
        <div class="card" style="border-left:4px solid <?php
            echo match($job['status']) {
                'active' => 'var(--success)', 'closed' => 'var(--danger)',
                'draft'  => 'var(--gray)',    default  => 'var(--warning)'
            };
        ?>;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">

                <!-- Job info -->
                <div style="flex:1;min-width:220px;">
                    <h3 style="margin:0 0 .4rem;color:var(--primary);font-size:1.1rem;">
                        <?php echo ViewHelper::e($job['title']);?>
                    </h3>
                    <div style="font-size:.85rem;color:var(--dark-gray);margin-bottom:.6rem;
                                display:flex;flex-wrap:wrap;gap:.6rem;">
                        <span>📍 <?php echo ViewHelper::e($job['location']);?></span>
                        <span>💼 <?php echo ViewHelper::e(getJobTypes()[$job['job_type']] ?? $job['job_type']);?></span>
                        <span>🌐 <?php echo ViewHelper::e(getWorkTypes()[$job['work_type']] ?? $job['work_type'] ?? '');?></span>
                        <?php if ($job['salary_min'] || $job['salary_max']): ?>
                        <span>💰 <?php echo ViewHelper::formatSalary($job['salary_min'],$job['salary_max'],$job['salary_currency']??'ETB');?></span>
                        <?php endif;?>
                    </div>

                    <!-- Deadline indicator -->
                    <?php if ($job['application_deadline']): ?>
                    <div style="font-size:.82rem;margin-bottom:.5rem;
                                color:<?php echo $isExpired?'var(--danger)':($isExpiring?'var(--warning)':'var(--dark-gray)');?>">
                        ⏰ Deadline: <?php echo ViewHelper::formatDate($job['application_deadline'],'M j, Y');?>
                        <?php if ($isExpired): echo ' — <strong>Expired</strong>';
                        elseif ($isExpiring): echo " — <strong>{$dr} day(s) left</strong>";
                        endif;?>
                    </div>
                    <?php endif;?>

                    <!-- Meta chips -->
                    <div style="display:flex;flex-wrap:wrap;gap:.35rem;">
                        <?php echo ViewHelper::jobStatusBadge($job['status']);?>
                        <?php if ($job['is_featured']): ?><span class="badge badge-featured">⭐ Featured</span><?php endif;?>
                        <span class="badge badge-activity" style="text-transform:none;">
                            📨 <?php echo number_format($job['application_count']??0);?> applicants
                        </span>
                        <?php if ($job['pending_count'] ?? 0): ?>
                        <span class="badge badge-pending" style="text-transform:none;">
                            ⏳ <?php echo $job['pending_count'];?> pending
                        </span>
                        <?php endif;?>
                    </div>

                    <div style="font-size:.78rem;color:var(--dark-gray);margin-top:.5rem;">
                        Posted <?php echo ViewHelper::timeAgo($job['created_at']);?>
                        · <?php echo ViewHelper::formatDate($job['created_at']);?>
                    </div>
                </div>

                <!-- Actions -->
                <div style="display:flex;flex-direction:column;gap:.5rem;align-items:flex-end;flex-shrink:0;">
                    <a href="applicants.php?job_id=<?php echo $job['id'];?>"
                       class="btn btn-outline btn-sm">
                        📨 View Applicants
                    </a>
                    <form method="POST" style="display:inline;"
                           onclick="return confirm('<?php echo $job['status']==='active'?'Close':'Reopen';?> this job?')">
                        <input type="hidden" name="csrf_token"     value="<?php echo $csrf;?>">
                        <input type="hidden" name="toggle_job_id"  value="<?php echo $job['id'];?>">
                        <button type="submit"
                                class="btn btn-sm <?php echo $job['status']==='active'?'btn-warning':'btn-success';?>">
                            <?php echo $job['status']==='active'?'🔒 Close':'✅ Reopen';?>
                        </button>
                    </form>
                    <a href="?delete=<?php echo $job['id'];?>&csrf=<?php echo $csrf;?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Delete this job? All applications will be lost.')">
                        🗑️ Delete
                    </a>
                </div>

            </div>
        </div>
        <?php endforeach;?>
    </div>

    <!-- Pagination -->
    <?php echo ViewHelper::pagination($result, 'my-jobs.php?' . http_build_query(array_filter(['status'=>$statusFilter,'sort'=>$sort!=='created_at DESC'?$sort:'']))); ?>
    <?php endif;?>

</div>

<script>
document.querySelectorAll('.alert-close').forEach(b =>
    b.addEventListener('click', () => b.closest('.alert').remove())
);
</script>

<?php include '../includes/footer.php'; ?>
