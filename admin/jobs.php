<?php
/**
 * Graduate Job Connect — Admin: Job Management
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Models\JobModel;
use App\Helpers\ViewHelper;

AuthMiddleware::require('admin', '../login.php');

$pdo      = getDBConnection();
$jobModel = new JobModel($pdo);
$adminId  = currentUserId();
$csrf     = AuthMiddleware::csrfToken();

$message = ''; $msgType = '';

// ─── Delete ───────────────────────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    AuthMiddleware::verifyCsrf($_GET['csrf'] ?? '');
    $job = $jobModel->findById((int) $_GET['delete']);
    if ($job) {
        $jobModel->delete((int) $_GET['delete']);
        $message = "✅ Job \"{$job['title']}\" deleted."; $msgType = 'success';
    } else {
        $message = '❌ Job not found.'; $msgType = 'error';
    }
}

// ─── Toggle status (POST — prevents CSRF via GET) ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_job_id'])) {
    AuthMiddleware::verifyCsrf($_POST['csrf_token'] ?? '');
    $job = $jobModel->findById((int) $_POST['toggle_job_id']);
    if ($job) {
        $new = $job['status'] === 'active' ? 'closed' : 'active';
        $jobModel->update((int) $_POST['toggle_job_id'], ['status' => $new]);
        $message = "✅ \"{$job['title']}\" is now " . ucfirst($new) . '.'; $msgType = 'success';
    }
}

// ─── Bulk action ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'], $_POST['job_ids'])) {
    AuthMiddleware::verifyCsrf($_POST['csrf_token'] ?? '');
    $ids    = array_map('intval', (array) $_POST['job_ids']);
    $action = $_POST['bulk_action'];
    $done   = 0;
    foreach ($ids as $id) {
        match($action) {
            'activate' => $jobModel->update($id, ['status' => 'active'])  && $done++,
            'close'    => $jobModel->update($id, ['status' => 'closed'])  && $done++,
            'delete'   => $jobModel->delete($id)                          && $done++,
            default    => null
        };
    }
    $message = "✅ {$done} job(s) {$action}d."; $msgType = 'success';
}

// ─── Filters ──────────────────────────────────────────────────────────────────
$search  = trim($_GET['search']   ?? '');
$status  = trim($_GET['status']   ?? '');
$jobType = trim($_GET['job_type'] ?? '');
$sort    = in_array($_GET['sort'] ?? '', ['created_at DESC','created_at ASC','j.title ASC','application_count DESC'])
             ? $_GET['sort'] : 'created_at DESC';
$page    = max(1, (int) ($_GET['page'] ?? 1));

$result  = $jobModel->listForAdmin($search, $status, $jobType, $sort, $page, 20);
$jobs    = $result['data'];
$stats   = $jobModel->getAdminStats();

$page_title  = 'Manage Jobs | Admin';
$css_path    = '../assets/css/';
$js_path     = '../assets/js/';
$home_path   = '../';
$logout_path = '../';
include '../includes/header.php';
?>

<div class="container" style="padding:2rem 1rem;">

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;
                gap:1rem;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:2px solid var(--light-gray);">
        <div>
            <h1 style="margin:0;">💼 Manage Jobs</h1>
            <p style="color:var(--dark-gray);margin:.25rem 0 0;">
                <?php echo number_format($result['total']); ?> job<?php echo $result['total'] != 1 ? 's' : ''; ?> found
            </p>
        </div>
        <a href="dashboard.php" class="btn btn-outline btn-sm">← Dashboard</a>
    </div>

    <!-- Stats row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:.75rem;margin-bottom:1.5rem;">
        <?php foreach ([
            ['total_jobs',   '📊','Total',      '#e8f0fe','var(--primary)'],
            ['active_jobs',  '✅','Active',     '#d4edda','var(--success)'],
            ['closed_jobs',  '🔒','Closed',     '#f8d7da','var(--danger)'],
            ['expired_jobs', '⏰','Expired',    '#fff3cd','#856404'],
            ['total_applications','📨','Applications','#cce5ff','var(--info)'],
        ] as [$key,$icon,$lbl,$bg,$color]): ?>
        <div style="background:<?php echo $bg;?>;color:<?php echo $color;?>;padding:.85rem;
                    border-radius:var(--radius-lg);text-align:center;">
            <div><?php echo $icon;?></div>
            <div style="font-size:1.3rem;font-weight:700;"><?php echo number_format($stats[$key]??0);?></div>
            <div style="font-size:.75rem;font-weight:600;"><?php echo $lbl;?></div>
        </div>
        <?php endforeach;?>
    </div>

    <!-- Flash -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $msgType;?> alert-dismissible" style="margin-bottom:1rem;">
        <span><?php echo ViewHelper::e($message);?></span>
        <button class="alert-close">&times;</button>
    </div>
    <?php endif;?>

    <!-- Filter bar -->
    <form method="GET" style="background:var(--white);padding:1rem 1.25rem;border-radius:var(--radius-lg);
                               box-shadow:var(--shadow-sm);margin-bottom:1.5rem;">
        <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
            <div style="flex:2;min-width:180px;">
                <div style="position:relative;">
                    <span style="position:absolute;left:.8rem;top:50%;transform:translateY(-50%);">🔍</span>
                    <input type="text" name="search" class="form-control"
                           value="<?php echo ViewHelper::e($search);?>"
                           placeholder="Title, location, description…"
                           style="padding-left:2.4rem;height:40px;">
                </div>
            </div>
            <select name="status" class="form-control" style="height:40px;flex:1;min-width:120px;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <?php foreach (['active'=>'✅ Active','closed'=>'🔒 Closed','draft'=>'📝 Draft','expired'=>'⏰ Expired'] as $v=>$l): ?>
                <option value="<?php echo $v;?>" <?php echo $status===$v?'selected':'';?>><?php echo $l;?></option>
                <?php endforeach;?>
            </select>
            <select name="job_type" class="form-control" style="height:40px;flex:1;min-width:130px;" onchange="this.form.submit()">
                <option value="">All Types</option>
                <?php foreach (getJobTypes() as $v=>$l): ?>
                <option value="<?php echo $v;?>" <?php echo $jobType===$v?'selected':'';?>><?php echo $l;?></option>
                <?php endforeach;?>
            </select>
            <select name="sort" class="form-control" style="height:40px;flex:1;min-width:150px;" onchange="this.form.submit()">
                <option value="created_at DESC"     <?php echo $sort==='created_at DESC'    ?'selected':'';?>>Newest</option>
                <option value="created_at ASC"      <?php echo $sort==='created_at ASC'     ?'selected':'';?>>Oldest</option>
                <option value="j.title ASC"         <?php echo $sort==='j.title ASC'        ?'selected':'';?>>Title A–Z</option>
                <option value="application_count DESC" <?php echo $sort==='application_count DESC'?'selected':'';?>>Most Applied</option>
            </select>
            <button type="submit" class="btn btn-primary" style="height:40px;">Apply</button>
            <?php if ($search||$status||$jobType): ?>
            <a href="jobs.php" class="btn btn-outline" style="height:40px;display:flex;align-items:center;">✕</a>
            <?php endif;?>
        </div>
    </form>

    <!-- Jobs table -->
    <?php if (empty($jobs)): ?>
    <div class="card" style="text-align:center;padding:3rem 1rem;">
        <div style="font-size:4rem;margin-bottom:1rem;">📭</div>
        <h3>No jobs found</h3>
        <a href="jobs.php" class="btn btn-outline btn-sm">Clear Filters</a>
    </div>
    <?php else: ?>

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
            <div style="display:flex;gap:.5rem;">
                <select name="bulk_action" class="form-control" style="height:36px;font-size:.85rem;width:auto;">
                    <option value="">Bulk Action</option>
                    <option value="activate">✅ Activate</option>
                    <option value="close">🔒 Close</option>
                    <option value="delete">🗑️ Delete</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"
                        onclick="return confirmBulk()" style="height:36px;">Apply</button>
            </div>
        </div>

        <!-- Table -->
        <div class="card" style="padding:0;overflow:hidden;">
            <div class="table-responsive">
                <table class="table table-hover" style="font-size:.87rem;">
                    <thead>
                        <tr>
                            <th style="width:40px;"></th>
                            <th>Job</th>
                            <th>Employer</th>
                            <th>Type</th>
                            <th>Apps</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $j):
                            $dr = $j['application_deadline']
                                ? ViewHelper::daysUntil($j['application_deadline']) : null;
                            $expired = $dr !== null && $dr < 0;
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="job_ids[]" value="<?php echo $j['id'];?>"
                                       class="job-cb" onchange="updateCount()"
                                       style="width:15px;height:15px;accent-color:var(--primary);">
                            </td>
                            <td>
                                <div style="font-weight:700;color:var(--primary);">
                                    <?php echo ViewHelper::e(ViewHelper::truncate($j['title'], 45));?>
                                </div>
                                <div style="font-size:.77rem;color:var(--dark-gray);">
                                    📍 <?php echo ViewHelper::e($j['location']);?>
                                </div>
                                <?php if ($j['is_featured']): ?>
                                <span class="badge badge-featured" style="font-size:.65rem;margin-top:.2rem;">⭐ Featured</span>
                                <?php endif;?>
                            </td>
                            <td>
                                <div style="font-weight:600;font-size:.85rem;"><?php echo ViewHelper::e($j['company_name'] ?? $j['employer_name'] ?? '');?></div>
                                <div style="font-size:.77rem;color:var(--dark-gray);">#<?php echo $j['id'];?></div>
                            </td>
                            <td>
                                <span class="badge badge-draft" style="text-transform:none;font-size:.72rem;">
                                    <?php echo ViewHelper::e(getJobTypes()[$j['job_type']] ?? $j['job_type']);?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-activity" style="text-transform:none;font-size:.72rem;">
                                    <?php echo number_format($j['application_count']??0);?>
                                </span>
                                <?php if ($j['pending_applications']??0): ?>
                                <div style="font-size:.72rem;color:var(--warning);">
                                    <?php echo $j['pending_applications'];?> pending
                                </div>
                                <?php endif;?>
                            </td>
                            <td style="font-size:.8rem;color:var(--dark-gray);">
                                <?php if ($j['application_deadline']): ?>
                                    <span style="color:<?php echo $expired?'var(--danger)':($dr<=7?'var(--warning)':'inherit');?>">
                                        <?php echo ViewHelper::formatDate($j['application_deadline'],'M j, Y');?>
                                        <?php if ($expired): echo '<br><strong>Expired</strong>';
                                        elseif($dr<=7): echo "<br>{$dr}d left";
                                        endif;?>
                                    </span>
                                <?php else: echo '—'; endif;?>
                            </td>
                            <td><?php echo ViewHelper::jobStatusBadge($j['status']);?></td>
                            <td>
                                <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
                                    <!-- Toggle via POST (CSRF safe) -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token"    value="<?php echo $csrf;?>">
                                        <input type="hidden" name="toggle_job_id" value="<?php echo $j['id'];?>">
                                        <button type="submit"
                                                class="btn btn-sm <?php echo $j['status']==='active'?'btn-warning':'btn-success';?>"
                                                style="padding:.2rem .4rem;font-size:.8rem;"
                                                title="<?php echo $j['status']==='active'?'Close':'Activate';?>">
                                            <?php echo $j['status']==='active'?'🔒':'✅';?>
                                        </button>
                                    </form>
                                    <a href="?delete=<?php echo $j['id'];?>&csrf=<?php echo $csrf;?>"
                                       class="btn btn-sm btn-danger"
                                       style="padding:.2rem .4rem;font-size:.8rem;"
                                       onclick="return confirm('Delete \"<?php echo addslashes($j['title']);?>\"?')"
                                       title="Delete">
                                        🗑️
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <?php echo ViewHelper::pagination($result, 'jobs.php?' . http_build_query(array_filter([
        'search'=>$search,'status'=>$status,'job_type'=>$jobType,'sort'=>$sort!=='created_at DESC'?$sort:''
    ]))); ?>
    <?php endif;?>

</div>

<script>
function toggleAll(m){ document.querySelectorAll('.job-cb').forEach(c=>c.checked=m.checked); updateCount(); }
function updateCount(){ document.getElementById('selectedCount').textContent = document.querySelectorAll('.job-cb:checked').length; }
function confirmBulk(){
    const n=document.querySelectorAll('.job-cb:checked').length, a=document.querySelector('[name="bulk_action"]').value;
    if(!a){alert('Select a bulk action.');return false;}
    if(!n){alert('Select at least one job.');return false;}
    if(a==='delete') return confirm(`Permanently delete ${n} job(s) and all their applications?`);
    return confirm(`${a.charAt(0).toUpperCase()+a.slice(1)} ${n} job(s)?`);
}
document.querySelectorAll('.alert-close').forEach(b=>b.addEventListener('click',()=>b.closest('.alert').remove()));
</script>

<?php include '../includes/footer.php'; ?>
