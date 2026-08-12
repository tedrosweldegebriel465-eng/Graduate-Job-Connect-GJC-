<?php
/**
 * Graduate Job Connect — Employer: View Applicants (ATS)
 * Full applicant tracking with status updates, bulk actions, filters, CSRF
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Controllers\JobController;
use App\Models\ApplicationModel;
use App\Models\JobModel;
use App\Helpers\ViewHelper;

AuthMiddleware::require('employer', '../login.php');

$pdo        = getDBConnection();
$employerId = currentUserId();
$controller = new JobController($pdo);
$appModel   = new ApplicationModel($pdo);
$jobModel   = new JobModel($pdo);

$message     = '';
$messageType = '';

// ─── Handle single status update ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['application_id'])) {
    $result      = $controller->updateApplicationStatus(
        (int) $_POST['application_id'],
        $_POST['update_status'],
        $employerId
    );
    $message     = $result['success']
        ? '✅ Application status updated successfully.'
        : ('❌ ' . ($result['error'] ?? 'Update failed.'));
    $messageType = $result['success'] ? 'success' : 'error';
}

// ─── Handle bulk action ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'], $_POST['application_ids'])) {
    AuthMiddleware::verifyCsrf($_POST['csrf_token'] ?? '');
    $ids    = array_map('intval', (array) $_POST['application_ids']);
    $action = $_POST['bulk_action'];
    $validStatuses = ApplicationModel::VALID_STATUSES;

    if (!in_array($action, $validStatuses, true)) {
        $message = '❌ Invalid bulk action.'; $messageType = 'error';
    } elseif (empty($ids)) {
        $message = 'Please select at least one application.'; $messageType = 'warning';
    } else {
        $updated = 0;
        foreach ($ids as $id) {
            $r = $appModel->updateStatus($id, $action, $employerId);
            if ($r) $updated++;
        }
        $message     = "✅ {$updated} application(s) updated to " . ucfirst($action) . '.';
        $messageType = 'success';
    }
}

// ─── Filters ──────────────────────────────────────────────────────────────────
$jobFilter    = isset($_GET['job_id']) && is_numeric($_GET['job_id']) ? (int) $_GET['job_id'] : null;
$statusFilter = trim($_GET['status'] ?? '');
$searchFilter = trim($_GET['search'] ?? '');
$sort         = trim($_GET['sort']   ?? 'applied_at DESC');
$page         = max(1, (int) ($_GET['page'] ?? 1));

// ─── Data ─────────────────────────────────────────────────────────────────────
$employerJobs = $jobModel->getEmployerJobs($employerId, page: 1, perPage: 100)['data'];
$appStats     = $appModel->getEmployerApplicationStats($employerId);

$result = $appModel->getEmployerApplications(
    employerId: $employerId,
    jobId:      $jobFilter,
    status:     $statusFilter,
    search:     $searchFilter,
    sort:       $sort,
    page:       $page,
    perPage:    15
);
$applications = $result['data'];

$csrf       = AuthMiddleware::csrfToken();
$page_title = 'Job Applications';
$css_path   = '../assets/css/';
$js_path    = '../assets/js/';
$home_path  = '../';
$logout_path= '../';
$employer_path = '';
include '../includes/header.php';
?>

<div class="container" style="padding:2rem 1rem;">

    <!-- Page header -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;
                gap:1rem;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:2px solid var(--light-gray);">
        <div>
            <h1 style="margin:0;">📨 Job Applications</h1>
            <p style="color:var(--dark-gray);margin:.25rem 0 0;">
                Manage all applicants for your job postings
            </p>
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <a href="post-job.php"   class="btn btn-primary">➕ Post New Job</a>
            <a href="dashboard.php"  class="btn btn-outline">← Dashboard</a>
        </div>
    </div>

    <!-- Stats row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <?php foreach ([
            ['total','📊','Total',     '#e8f0fe','var(--primary)'],
            ['pending','⏳','Pending',  '#fff3cd','#856404'],
            ['shortlisted','⭐','Shortlisted','#cce5ff','#004085'],
            ['accepted','✅','Accepted','#d4edda','#155724'],
            ['rejected','❌','Rejected','#f8d7da','#721c24'],
        ] as [$key,$icon,$lbl,$bg,$color]):
        $cnt = (int)($appStats[$key] ?? 0); ?>
        <div style="background:<?php echo $bg;?>;color:<?php echo $color;?>;padding:1rem;
                    border-radius:var(--radius-lg);text-align:center;">
            <div style="font-size:1.4rem;"><?php echo $icon;?></div>
            <div style="font-size:1.5rem;font-weight:700;"><?php echo $cnt;?></div>
            <div style="font-size:.78rem;font-weight:600;"><?php echo $lbl;?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Flash message -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType;?> alert-dismissible" style="margin-bottom:1rem;">
        <span><?php echo ViewHelper::e($message);?></span>
        <button class="alert-close">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Filter bar -->
    <form method="GET" style="background:var(--white);padding:1rem 1.25rem;border-radius:var(--radius-lg);
                               box-shadow:var(--shadow-sm);margin-bottom:1.5rem;">
        <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">

            <div style="flex:2;min-width:180px;">
                <label style="font-size:.8rem;color:var(--dark-gray);display:block;margin-bottom:.25rem;">Search</label>
                <div style="position:relative;">
                    <span style="position:absolute;left:.8rem;top:50%;transform:translateY(-50%);">🔍</span>
                    <input type="text" name="search" class="form-control"
                           value="<?php echo ViewHelper::e($searchFilter);?>"
                           placeholder="Name or email…"
                           style="padding-left:2.4rem;height:40px;">
                </div>
            </div>

            <div style="flex:1;min-width:150px;">
                <label style="font-size:.8rem;color:var(--dark-gray);display:block;margin-bottom:.25rem;">Job</label>
                <select name="job_id" class="form-control" style="height:40px;" onchange="this.form.submit()">
                    <option value="">All Jobs</option>
                    <?php foreach ($employerJobs as $j): ?>
                    <option value="<?php echo $j['id'];?>"
                        <?php echo $jobFilter === $j['id'] ? 'selected' : '';?>>
                        <?php echo ViewHelper::e(ViewHelper::truncate($j['title'], 30));?>
                        (<?php echo $j['application_count'] ?? 0;?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex:1;min-width:130px;">
                <label style="font-size:.8rem;color:var(--dark-gray);display:block;margin-bottom:.25rem;">Status</label>
                <select name="status" class="form-control" style="height:40px;" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <?php foreach (['pending'=>'⏳ Pending','shortlisted'=>'⭐ Shortlisted',
                                    'accepted'=>'✅ Accepted','rejected'=>'❌ Rejected','withdrawn'=>'🚫 Withdrawn'] as $v=>$l): ?>
                    <option value="<?php echo $v;?>" <?php echo $statusFilter===$v?'selected':'';?>>
                        <?php echo $l;?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex:1;min-width:140px;">
                <label style="font-size:.8rem;color:var(--dark-gray);display:block;margin-bottom:.25rem;">Sort</label>
                <select name="sort" class="form-control" style="height:40px;" onchange="this.form.submit()">
                    <option value="applied_at DESC" <?php echo $sort==='applied_at DESC'?'selected':'';?>>Newest First</option>
                    <option value="applied_at ASC"  <?php echo $sort==='applied_at ASC' ?'selected':'';?>>Oldest First</option>
                    <option value="u.name ASC"      <?php echo $sort==='u.name ASC'     ?'selected':'';?>>Name A–Z</option>
                    <option value="a.status ASC"    <?php echo $sort==='a.status ASC'   ?'selected':'';?>>By Status</option>
                </select>
            </div>

            <div style="display:flex;gap:.5rem;align-items:flex-end;">
                <button type="submit" class="btn btn-primary" style="height:40px;">Apply</button>
                <?php if ($jobFilter || $statusFilter || $searchFilter): ?>
                <a href="applicants.php" class="btn btn-outline" style="height:40px;display:flex;align-items:center;">✕</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <!-- Applications list -->
    <?php if (empty($applications)): ?>
    <div class="card" style="text-align:center;padding:3rem 1rem;">
        <div style="font-size:4rem;margin-bottom:1rem;">📭</div>
        <h3>No Applications Found</h3>
        <p style="color:var(--dark-gray);">
            <?php echo ($jobFilter||$statusFilter||$searchFilter) ? 'Try adjusting your filters.' : 'Post a job to start receiving applications.'; ?>
        </p>
        <?php if ($jobFilter||$statusFilter||$searchFilter): ?>
        <a href="applicants.php" class="btn btn-outline btn-sm">Clear Filters</a>
        <?php else: ?>
        <a href="post-job.php" class="btn btn-primary">Post a Job</a>
        <?php endif; ?>
    </div>

    <?php else: ?>

    <!-- Bulk action form wraps the whole list -->
    <form method="POST" id="bulkForm">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf;?>">

        <!-- Bulk toolbar -->
        <div style="display:flex;justify-content:space-between;align-items:center;
                    padding:.75rem 1rem;background:var(--light-gray);border-radius:var(--radius);
                    margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
            <div style="display:flex;align-items:center;gap:.75rem;font-size:.9rem;">
                <input type="checkbox" id="selectAll" onchange="toggleAll(this)"
                       style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);">
                <label for="selectAll" style="cursor:pointer;color:var(--dark-gray);">
                    Select all · <span id="selectedCount">0</span> selected
                </label>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center;">
                <select name="bulk_action" class="form-control" style="height:36px;font-size:.85rem;width:auto;">
                    <option value="">Bulk Action</option>
                    <option value="shortlisted">⭐ Shortlist</option>
                    <option value="accepted">✅ Accept</option>
                    <option value="rejected">❌ Reject</option>
                    <option value="pending">⏳ Reset to Pending</option>
                </select>
                <button type="submit" name="bulk_action_submit"
                        class="btn btn-primary btn-sm"
                        onclick="return confirmBulk()"
                        style="height:36px;">
                    Apply
                </button>
            </div>
            <div style="font-size:.85rem;color:var(--dark-gray);">
                Showing <?php echo count($applications);?> of <?php echo number_format($result['total']);?> total
            </div>
        </div>

        <!-- Application cards -->
        <div style="display:flex;flex-direction:column;gap:1rem;">
            <?php foreach ($applications as $app):
                $statusColors = ['pending'=>'var(--warning)','shortlisted'=>'var(--info)',
                                 'accepted'=>'var(--success)','rejected'=>'var(--danger)','withdrawn'=>'var(--gray)'];
                $borderColor  = $statusColors[$app['status']] ?? 'var(--gray)';
            ?>
            <div class="card" style="border-left:4px solid <?php echo $borderColor;?>;">

                <div style="display:flex;align-items:flex-start;gap:1rem;flex-wrap:wrap;">

                    <!-- Checkbox + avatar -->
                    <div style="display:flex;align-items:center;gap:.75rem;flex-shrink:0;">
                        <input type="checkbox" name="application_ids[]"
                               value="<?php echo $app['id'];?>"
                               class="app-checkbox"
                               onchange="updateCount()"
                               style="width:16px;height:16px;accent-color:var(--primary);">
                        <div style="width:44px;height:44px;border-radius:50%;
                                    background:var(--primary-gradient);color:#fff;
                                    display:flex;align-items:center;justify-content:center;
                                    font-weight:700;font-size:.95rem;flex-shrink:0;">
                            <?php echo ViewHelper::initials($app['applicant_name']);?>
                        </div>
                    </div>

                    <!-- Applicant info -->
                    <div style="flex:1;min-width:200px;">
                        <div style="font-weight:700;font-size:1rem;color:var(--text-dark);">
                            <?php echo ViewHelper::e($app['applicant_name']);?>
                        </div>
                        <div style="font-size:.85rem;color:var(--dark-gray);margin:.2rem 0;">
                            📧 <?php echo ViewHelper::e($app['applicant_email']);?>
                            <?php if ($app['applicant_phone']): ?>
                            · 📱 <?php echo ViewHelper::e($app['applicant_phone']);?>
                            <?php endif;?>
                        </div>
                        <div style="font-size:.85rem;color:var(--primary);font-weight:600;">
                            for: <?php echo ViewHelper::e($app['job_title']);?>
                            · <?php echo ViewHelper::e($app['location'] ?? '');?>
                        </div>

                        <!-- Education & skills -->
                        <?php if ($app['university']): ?>
                        <div style="font-size:.82rem;color:var(--dark-gray);margin-top:.4rem;">
                            🎓 <?php echo ViewHelper::e($app['university']);?>
                            <?php if ($app['department']): echo ' · ' . ViewHelper::e($app['department']);endif;?>
                            <?php if ($app['graduation_year']): echo ' (' . $app['graduation_year'] . ')';endif;?>
                        </div>
                        <?php endif;?>

                        <?php if ($app['skills']): ?>
                        <div style="margin-top:.5rem;">
                            <?php echo ViewHelper::skillTags(ViewHelper::truncate($app['skills'], 80));?>
                        </div>
                        <?php endif;?>

                        <div style="font-size:.78rem;color:var(--dark-gray);margin-top:.4rem;">
                            Applied <?php echo ViewHelper::timeAgo($app['applied_at']);?>
                            · <?php echo ViewHelper::formatDate($app['applied_at']);?>
                        </div>
                    </div>

                    <!-- Status + actions -->
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.6rem;flex-shrink:0;">
                        <?php echo ViewHelper::appStatusBadge($app['status']);?>

                        <!-- CV & links -->
                        <div style="display:flex;gap:.4rem;flex-wrap:wrap;justify-content:flex-end;">
                            <?php if ($app['cv_filename']): ?>
                            <a href="../uploads/cv/<?php echo ViewHelper::e($app['cv_filename']);?>"
                               target="_blank" class="btn btn-outline btn-sm">📄 CV</a>
                            <?php endif;?>
                            <?php if ($app['linkedin_url']): ?>
                            <a href="<?php echo ViewHelper::e($app['linkedin_url']);?>"
                               target="_blank" class="btn btn-outline btn-sm">🔗</a>
                            <?php endif;?>
                            <?php if ($app['github_url']): ?>
                            <a href="<?php echo ViewHelper::e($app['github_url']);?>"
                               target="_blank" class="btn btn-outline btn-sm">💻</a>
                            <?php endif;?>
                        </div>

                        <!-- Status update form -->
                        <?php if ($app['status'] !== 'withdrawn'): ?>
                        <form method="POST" style="display:flex;gap:.35rem;flex-wrap:wrap;justify-content:flex-end;">
                            <input type="hidden" name="csrf_token"    value="<?php echo $csrf;?>">
                            <input type="hidden" name="application_id" value="<?php echo $app['id'];?>">
                            <?php
                            $actions = [
                                'shortlisted'=>['⭐','btn-outline','Shortlist'],
                                'accepted'   =>['✅','btn-success', 'Accept'],
                                'rejected'   =>['❌','btn-danger',  'Reject'],
                            ];
                            foreach ($actions as $val=>[$icon,$cls,$lbl]):
                                $disabled = $app['status']===$val ? 'disabled style="opacity:.4"' : '';
                            ?>
                            <button type="submit" name="update_status" value="<?php echo $val;?>"
                                    class="btn btn-sm <?php echo $cls;?>"
                                    <?php echo $disabled;?>>
                                <?php echo $icon;?> <?php echo $lbl;?>
                            </button>
                            <?php endforeach;?>
                        </form>
                        <?php endif;?>
                    </div>

                </div>

                <!-- Cover letter (collapsible) -->
                <?php if (!empty($app['cover_letter'])): ?>
                <details style="margin-top:.75rem;border-top:1px solid var(--light-gray);padding-top:.75rem;">
                    <summary style="cursor:pointer;font-size:.88rem;color:var(--primary);font-weight:600;
                                    list-style:none;display:flex;align-items:center;gap:.4rem;">
                        ✍️ Cover Letter
                        <span style="font-size:.75rem;color:var(--dark-gray);font-weight:400;">(click to expand)</span>
                    </summary>
                    <div style="margin-top:.6rem;padding:.75rem;background:var(--light-gray);
                                border-radius:var(--radius);font-size:.88rem;line-height:1.6;
                                color:var(--text-dark);">
                        <?php echo nl2br(ViewHelper::e($app['cover_letter']));?>
                    </div>
                </details>
                <?php endif;?>

            </div>
            <?php endforeach;?>
        </div>
    </form>

    <!-- Pagination -->
    <?php
    $paginationUrl = 'applicants.php?' . http_build_query(array_filter([
        'job_id' => $jobFilter, 'status' => $statusFilter,
        'search' => $searchFilter, 'sort' => $sort !== 'applied_at DESC' ? $sort : ''
    ]));
    echo ViewHelper::pagination($result, $paginationUrl);
    ?>

    <?php endif; // empty check ?>

</div><!-- /container -->

<script>
function toggleAll(master) {
    document.querySelectorAll('.app-checkbox').forEach(cb => cb.checked = master.checked);
    updateCount();
}
function updateCount() {
    const n = document.querySelectorAll('.app-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = n;
    document.getElementById('selectAll').indeterminate =
        n > 0 && n < document.querySelectorAll('.app-checkbox').length;
}
function confirmBulk() {
    const n      = document.querySelectorAll('.app-checkbox:checked').length;
    const action = document.querySelector('[name="bulk_action"]').value;
    if (!action) { alert('Please select a bulk action.'); return false; }
    if (n === 0)  { alert('Please select at least one application.'); return false; }
    return confirm(`Apply "${action}" to ${n} application(s)?`);
}
document.querySelectorAll('.alert-close').forEach(b =>
    b.addEventListener('click', () => b.closest('.alert').remove())
);
</script>

<?php include '../includes/footer.php'; ?>
