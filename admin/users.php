<?php
/**
 * Graduate Job Connect — Admin: User Management
 * Uses UserModel for all DB operations
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Models\UserModel;
use App\Helpers\ViewHelper;

AuthMiddleware::require('admin', '../login.php');

$pdo       = getDBConnection();
$userModel = new UserModel($pdo);
$adminId   = currentUserId();

$message = ''; $msgType = '';

// ─── CSRF helper ──────────────────────────────────────────────────────────────
$csrf = AuthMiddleware::csrfToken();

$authCtrl = new \App\Controllers\AuthController($pdo);

// ─── Approve User ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user_id'])) {
    $res = $authCtrl->handleApproveUser((int)$_POST['approve_user_id'], $adminId);
    $message = $res['error'] ?? $res['success'];
    $msgType = isset($res['error']) ? 'error' : 'success';
}

// ─── Reject User ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_user_id'])) {
    $res = $authCtrl->handleRejectUser((int)$_POST['reject_user_id'], $adminId, trim($_POST['notes'] ?? ''));
    $message = $res['error'] ?? $res['success'];
    $msgType = isset($res['error']) ? 'error' : 'success';
}

// ─── Suspend User ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['suspend_user_id'])) {
    $res = $authCtrl->handleSuspendUser((int)$_POST['suspend_user_id'], $adminId, trim($_POST['notes'] ?? ''));
    $message = $res['error'] ?? $res['success'];
    $msgType = isset($res['error']) ? 'error' : 'success';
}

// ─── Delete single user (POST — CSRF token stays out of URL/server logs) ──────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    AuthMiddleware::verifyCsrf($_POST['csrf_token'] ?? '');
    $uid  = (int) $_POST['delete_user_id'];
    $user = $userModel->findById($uid);
    if (!$user || $user['role'] === 'admin') {
        $message = 'Cannot delete admin users.'; $msgType = 'error';
    } else {
        $userModel->delete($uid);
        $message = "User \"{$user['name']}\" deleted."; $msgType = 'success';
    }
}

// ─── Toggle status (POST — prevents CSRF via GET) ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    AuthMiddleware::verifyCsrf($_POST['csrf_token'] ?? '');
    $uid = (int) $_POST['toggle_user_id'];
    $ok  = $userModel->toggleStatus($uid);
    $message = $ok ? 'User status updated.' : 'Could not update status.';
    $msgType = $ok ? 'success' : 'error';
}

// ─── Bulk action ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'], $_POST['user_ids'])) {
    AuthMiddleware::verifyCsrf($_POST['csrf_token'] ?? '');
    $ids    = array_map('intval', (array) $_POST['user_ids']);
    $action = $_POST['bulk_action'];

    if (empty($ids)) {
        $message = 'Select at least one user.'; $msgType = 'warning';
    } else {
        $done = 0;
        foreach ($ids as $uid) {
            $u = $userModel->findById($uid);
            if (!$u || $u['role'] === 'admin') continue;
            match($action) {
                'activate'   => $userModel->updateAccountStatus($uid, UserModel::STATUS_ACTIVE, $adminId) && $done++,
                'suspend'    => $userModel->updateAccountStatus($uid, UserModel::STATUS_SUSPENDED, $adminId) && $done++,
                'deactivate' => $userModel->updateAccountStatus($uid, UserModel::STATUS_SUSPENDED, $adminId) && $done++,
                'delete'     => $userModel->delete($uid) && $done++,
                default      => null
            };
        }
        $message = "{$done} user(s) updated."; $msgType = 'success';
    }
}

// ─── Filters ──────────────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$role   = trim($_GET['role']   ?? '');
$status = trim($_GET['status'] ?? '');
$sort   = in_array($_GET['sort'] ?? '', ['created_at DESC','created_at ASC','name ASC','activity_count DESC'])
            ? $_GET['sort'] : 'created_at DESC';
$page   = max(1, (int) ($_GET['page'] ?? 1));

$result = $userModel->listUsers($search, $role, $status, $sort, $page, 20);
$users  = $result['data'];
$stats  = $userModel->getStats();

$page_title  = 'Manage Users | Admin';
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
            <h1 style="margin:0;">👥 Manage Users</h1>
            <p style="color:var(--dark-gray);margin:.25rem 0 0;">
                <?php echo number_format($result['total']); ?> user<?php echo $result['total'] != 1 ? 's' : ''; ?> found
            </p>
        </div>
        <a href="dashboard.php" class="btn btn-outline btn-sm">← Dashboard</a>
    </div>

    <!-- Stats row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:.75rem;margin-bottom:1.5rem;">
        <?php foreach ([
            ['total',        '👤','Total Users', '#e8f0fe','var(--primary)'],
            ['graduates',    '🎓','Graduates',   '#d4edda','var(--success)'],
            ['employers',    '🏢','Employers',   '#fef3e2','#d4a843'],
            ['active',       '✅','Active',      '#d1ecf1','#0c5460'],
            ['new_this_week','⚡','This Week',   '#fff3cd','#856404'],
        ] as [$key,$icon,$lbl,$bg,$color]):
            $v = number_format($stats[$key] ?? 0); ?>
        <div style="background:<?php echo $bg;?>;color:<?php echo $color;?>;padding:.85rem;
                    border-radius:var(--radius-lg);text-align:center;">
            <div><?php echo $icon;?></div>
            <div style="font-size:1.3rem;font-weight:700;"><?php echo $v;?></div>
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
                           placeholder="Name, email, or phone…"
                           style="padding-left:2.4rem;height:40px;">
                </div>
            </div>
            <select name="role" class="form-control" style="height:40px;flex:1;min-width:120px;" onchange="this.form.submit()">
                <option value="">All Roles</option>
                <option value="graduate" <?php echo $role==='graduate'?'selected':'';?>>🎓 Graduate</option>
                <option value="employer" <?php echo $role==='employer'?'selected':'';?>>🏢 Employer</option>
            </select>
            <select name="status" class="form-control" style="height:40px;flex:1;min-width:120px;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active"   <?php echo $status==='active'  ?'selected':'';?>>✅ Active</option>
                <option value="inactive" <?php echo $status==='inactive'?'selected':'';?>>⛔ Inactive</option>
            </select>
            <select name="sort" class="form-control" style="height:40px;flex:1;min-width:140px;" onchange="this.form.submit()">
                <option value="created_at DESC"      <?php echo $sort==='created_at DESC'     ?'selected':'';?>>Newest First</option>
                <option value="created_at ASC"       <?php echo $sort==='created_at ASC'      ?'selected':'';?>>Oldest First</option>
                <option value="name ASC"             <?php echo $sort==='name ASC'            ?'selected':'';?>>Name A–Z</option>
                <option value="activity_count DESC"  <?php echo $sort==='activity_count DESC' ?'selected':'';?>>Most Active</option>
            </select>
            <button type="submit" class="btn btn-primary" style="height:40px;">Apply</button>
            <?php if ($search || $role || $status): ?>
            <a href="users.php" class="btn btn-outline" style="height:40px;display:flex;align-items:center;">✕</a>
            <?php endif;?>
        </div>
    </form>

    <!-- Bulk + table -->
    <?php if (empty($users)): ?>
    <div class="card" style="text-align:center;padding:3rem 1rem;">
        <div style="font-size:4rem;margin-bottom:1rem;">👤</div>
        <h3>No users found</h3>
        <p style="color:var(--dark-gray);">Try adjusting filters.</p>
        <a href="users.php" class="btn btn-outline btn-sm">Clear Filters</a>
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
            <div style="display:flex;gap:.5rem;align-items:center;">
                <select name="bulk_action" class="form-control" style="height:36px;font-size:.85rem;width:auto;">
                    <option value="">Bulk Action</option>
                    <option value="activate">✅ Activate</option>
                    <option value="deactivate">⛔ Deactivate</option>
                    <option value="delete">🗑️ Delete</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirmBulk()" style="height:36px;">Apply</button>
            </div>
        </div>

        <!-- Table -->
        <div class="card" style="padding:0;overflow:hidden;">
            <div class="table-responsive">
                <table class="table table-hover" style="font-size:.88rem;">
                    <thead>
                        <tr>
                            <th style="width:40px;"></th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Activity</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="user_ids[]" value="<?php echo $u['id'];?>"
                                       class="user-cb" onchange="updateCount()"
                                       style="width:15px;height:15px;accent-color:var(--primary);">
                            </td>
                            <td>
                                <div style="font-weight:700;"><?php echo ViewHelper::e($u['name']);?></div>
                                <div style="font-size:.78rem;color:var(--dark-gray);"><?php echo ViewHelper::e($u['email']);?></div>
                                <?php if ($u['phone']): ?>
                                <div style="font-size:.75rem;color:var(--dark-gray);"><?php echo ViewHelper::e($u['phone']);?></div>
                                <?php endif;?>
                                <?php if ($u['profile_info']): ?>
                                <div style="font-size:.78rem;color:var(--primary);margin-top:.2rem;">
                                    <?php echo ViewHelper::e($u['profile_info']);?>
                                </div>
                                <?php endif;?>
                            </td>
                            <td><?php echo ViewHelper::roleBadge($u['role']);?></td>
                            <td>
                                <span class="badge badge-activity" style="text-transform:none;font-size:.75rem;">
                                    <?php echo number_format($u['activity_count'] ?? 0);?>
                                    <?php echo $u['role'] === 'graduate' ? 'app.' : 'job(s)';?>
                                </span>
                                <?php if ($u['last_login']): ?>
                                <div style="font-size:.75rem;color:var(--dark-gray);margin-top:.2rem;">
                                    Last seen <?php echo ViewHelper::timeAgo($u['last_login']);?>
                                </div>
                                <?php endif;?>
                            </td>
                            <td>
                                <span class="badge <?php echo $u['is_active'] ? 'badge-active' : 'badge-closed';?>"
                                      style="text-transform:none;">
                                    <?php echo $u['is_active'] ? '✅ Active' : '⛔ Inactive';?>
                                </span>
                            </td>
                            <td style="color:var(--dark-gray);font-size:.8rem;">
                                <?php echo ViewHelper::formatDate($u['created_at']);?>
                                <div><?php echo ViewHelper::timeAgo($u['created_at']);?></div>
                            </td>
                            <td>
                                <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
                                    <!-- Toggle via POST (CSRF safe) -->
                                    <form method="POST" action="users.php" style="display:inline;">
                                        <input type="hidden" name="csrf_token"     value="<?php echo $csrf;?>">
                                        <input type="hidden" name="toggle_user_id" value="<?php echo $u['id'];?>">
                                        <button type="submit"
                                                class="btn btn-sm <?php echo $u['is_active']?'btn-warning':'btn-success';?>"
                                                title="<?php echo $u['is_active']?'Deactivate':'Activate';?>"
                                                style="padding:.2rem .5rem;font-size:.8rem;">
                                            <?php echo $u['is_active']?'⛔':'✅';?>
                                        </button>
                                    </form>
                                    <!-- DELETE via POST to keep CSRF token out of URL/logs -->
                                    <form method="POST" action="users.php" style="display:inline;"
                                          onsubmit="return confirm('Delete <?php echo addslashes(htmlspecialchars($u['name'])); ?>? This cannot be undone.')">
                                        <input type="hidden" name="csrf_token"    value="<?php echo $csrf;?>">
                                        <input type="hidden" name="delete_user_id" value="<?php echo $u['id'];?>">
                                        <button type="submit" class="btn btn-sm btn-danger"
                                                style="padding:.2rem .5rem;font-size:.8rem;"
                                                title="Delete">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <!-- Pagination -->
    <?php echo ViewHelper::pagination($result, 'users.php?' . http_build_query(array_filter([
        'search'=>$search,'role'=>$role,'status'=>$status,'sort'=>$sort!=='created_at DESC'?$sort:''
    ]))); ?>
    <?php endif;?>

</div>

<script>
function toggleAll(m){ document.querySelectorAll('.user-cb').forEach(c=>c.checked=m.checked); updateCount(); }
function updateCount(){ document.getElementById('selectedCount').textContent = document.querySelectorAll('.user-cb:checked').length; }
function confirmBulk(){
    const n=document.querySelectorAll('.user-cb:checked').length, a=document.querySelector('[name="bulk_action"]').value;
    if(!a){alert('Select a bulk action.');return false;}
    if(!n){alert('Select at least one user.');return false;}
    return confirm(`${a.charAt(0).toUpperCase()+a.slice(1)} ${n} user(s)?`);
}
document.querySelectorAll('.alert-close').forEach(b=>b.addEventListener('click',()=>b.closest('.alert').remove()));
</script>

<?php include '../includes/footer.php'; ?>
