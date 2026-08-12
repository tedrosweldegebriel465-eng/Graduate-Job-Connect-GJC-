<?php
/**
 * Graduate Job Connect — Admin Dashboard  (Phase 5)
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Models\UserModel;
use App\Models\JobModel;
use App\Models\ApplicationModel;
use App\Helpers\ViewHelper;

AuthMiddleware::require('admin', '../login.php');

$pdo      = getDBConnection();
$userModel= new UserModel($pdo);
$jobModel = new JobModel($pdo);
$appModel = new ApplicationModel($pdo);

$userStats = $userModel->getStats();
$jobStats  = $jobModel->getAdminStats();
$appStats  = $appModel->getAdminStats();

$authCtrl  = new \App\Controllers\AuthController($pdo);
$message   = '';
$msgType   = '';

// Handle quick approve/reject POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_user_id'])) {
        $res = $authCtrl->handleApproveUser((int)$_POST['approve_user_id'], currentUserId());
        $message = $res['error'] ?? $res['success'];
        $msgType = isset($res['error']) ? 'error' : 'success';
    } elseif (isset($_POST['reject_user_id'])) {
        $res = $authCtrl->handleRejectUser((int)$_POST['reject_user_id'], currentUserId());
        $message = $res['error'] ?? $res['success'];
        $msgType = isset($res['error']) ? 'error' : 'success';
    }
}

// Pending registrations
$pending_users = $userModel->getPendingRegistrations(page: 1, perPage: 5)['data'];

// Recent registrations
$recent_users = $userModel->listUsers(page: 1, perPage: 5)['data'];

// Recent jobs
$recent_jobs  = $jobModel->listForAdmin(page: 1, perPage: 5)['data'];

// Recent applications
$stmt = $pdo->query("
    SELECT a.id, a.status, a.applied_at,
           u.name AS applicant_name, j.title AS job_title
    FROM applications a
    JOIN users u ON a.graduate_id = u.id
    JOIN jobs  j ON a.job_id      = j.id
    ORDER BY a.applied_at DESC LIMIT 6
");
$recent_apps = $stmt->fetchAll();

// 30-day registration trend for chart
$stmt = $pdo->query("
    SELECT DATE(created_at) AS day, COUNT(*) AS cnt
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(created_at) ORDER BY day ASC
");
$rawReg   = $stmt->fetchAll();
$regMap   = array_column($rawReg, 'cnt', 'day');
$regLabels= []; $regData = [];
for ($i = 29; $i >= 0; $i--) {
    $d           = date('Y-m-d', strtotime("-{$i} days"));
    $regLabels[] = date('M j', strtotime($d));
    $regData[]   = (int)($regMap[$d] ?? 0);
}

$page_title  = 'Admin Dashboard';
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
            <h1 style="margin:0;">📊 Admin Dashboard</h1>
            <p style="color:var(--dark-gray);margin:.25rem 0 0;">
                Platform overview · <?php echo ViewHelper::formatDate(date('Y-m-d H:i:s'), 'D, M j Y'); ?>
            </p>
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <a href="users.php" class="btn btn-primary">👥 Users</a>
            <a href="jobs.php"  class="btn btn-outline">💼 Jobs</a>
        </div>
    </div>

    <!-- Stats grid -->
    <div class="stats-grid" style="margin-bottom:2rem;">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e8f0fe;color:var(--primary);">👤</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($userStats['total'] ?? 0); ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-sub">
                    <?php echo number_format($userStats['new_this_week'] ?? 0); ?> this week
                </div>
            </div>
            <div class="stat-change positive">+<?php echo $userStats['new_this_week'] ?? 0; ?> week</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#e6f4ea;color:var(--success);">🎓</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($userStats['graduates'] ?? 0); ?></div>
                <div class="stat-label">Graduates</div>
                <div class="stat-sub"><?php echo number_format($userStats['active'] ?? 0); ?> active</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3e2;color:#d4a843;">🏢</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($userStats['employers'] ?? 0); ?></div>
                <div class="stat-label">Employers</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fce8e6;color:var(--danger);">💼</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($jobStats['total_jobs'] ?? 0); ?></div>
                <div class="stat-label">Jobs</div>
                <div class="stat-sub"><?php echo number_format($jobStats['active_jobs'] ?? 0); ?> active</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#e3f2fd;color:#1565c0;">📨</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($appStats['total'] ?? 0); ?></div>
                <div class="stat-label">Applications</div>
                <div class="stat-sub"><?php echo number_format($appStats['pending'] ?? 0); ?> pending</div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $msgType; ?> alert-dismissible" style="margin-bottom:1.5rem;">
        <span><?php echo ViewHelper::e($message); ?></span>
        <button class="alert-close">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Pending Registrations Section -->
    <?php if (!empty($pending_users)): ?>
    <div class="card" style="margin-bottom:2rem;border-left:4px solid var(--warning);">
        <div class="card-header">
            <h3 style="margin:0;font-size:1rem;">Pending Registrations</h3>
            <a href="users.php?status=pending_verification" class="btn btn-outline btn-sm">View All Pending</a>
        </div>
        <div class="table-responsive" style="margin-top:.75rem;">
            <table class="table table-hover" style="font-size:.88rem;">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Type</th>
                        <th>Profile / Institution</th>
                        <th>Registered</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_users as $pu): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;"><?php echo ViewHelper::e($pu['name']); ?></div>
                            <div style="font-size:.78rem;color:var(--dark-gray);"><?php echo ViewHelper::e($pu['email']); ?></div>
                        </td>
                        <td><?php echo ViewHelper::roleBadge($pu['role']); ?></td>
                        <td style="color:var(--primary);font-size:.82rem;">
                            <?php echo ViewHelper::e($pu['profile_info'] ?? '—'); ?>
                        </td>
                        <td style="color:var(--dark-gray);font-size:.8rem;">
                            <?php echo ViewHelper::timeAgo($pu['created_at']); ?>
                        </td>
                        <td>
                            <span class="badge badge-pending">Pending</span>
                        </td>
                        <td>
                            <div style="display:flex;gap:.35rem;">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo AuthMiddleware::csrfToken(); ?>">
                                    <input type="hidden" name="approve_user_id" value="<?php echo $pu['id']; ?>">
                                    <button type="submit" class="btn btn-primary btn-sm" style="padding:.25rem .5rem;font-size:.75rem;">
                                        Approve
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo AuthMiddleware::csrfToken(); ?>">
                                    <input type="hidden" name="reject_user_id" value="<?php echo $pu['id']; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="padding:.25rem .5rem;font-size:.75rem;color:var(--danger);border-color:var(--danger);" onclick="return confirm('Reject registration for <?php echo ViewHelper::e($pu['name']); ?>?')">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Registration trend chart -->
    <div class="card" style="margin-bottom:2rem;">
        <div class="card-header">
            <h3 style="margin:0;font-size:1rem;">📈 User Registrations — Last 30 Days</h3>
        </div>
        <div style="position:relative;height:200px;margin-top:.75rem;">
            <canvas id="regChart"></canvas>
        </div>
    </div>

    <!-- Recent activity grid -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem;">

        <!-- Recent users -->
        <div class="card">
            <div class="card-header">
                <h3 style="margin:0;font-size:1rem;">👤 Recent Registrations</h3>
                <a href="users.php" class="btn btn-outline btn-sm">View All</a>
            </div>
            <div class="table-responsive" style="margin-top:.75rem;">
                <table class="table table-hover" style="font-size:.88rem;">
                    <thead><tr><th>Name</th><th>Role</th><th>Joined</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_users as $u): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?php echo ViewHelper::e($u['name']); ?></div>
                                <div style="font-size:.78rem;color:var(--dark-gray);"><?php echo ViewHelper::e($u['email']); ?></div>
                            </td>
                            <td><?php echo ViewHelper::roleBadge($u['role']); ?></td>
                            <td style="color:var(--dark-gray);font-size:.8rem;"><?php echo ViewHelper::timeAgo($u['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent jobs -->
        <div class="card">
            <div class="card-header">
                <h3 style="margin:0;font-size:1rem;">💼 Recent Jobs</h3>
                <a href="jobs.php" class="btn btn-outline btn-sm">View All</a>
            </div>
            <div class="table-responsive" style="margin-top:.75rem;">
                <table class="table table-hover" style="font-size:.88rem;">
                    <thead><tr><th>Title</th><th>Employer</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_jobs as $j): ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo ViewHelper::e(ViewHelper::truncate($j['title'], 30)); ?></td>
                            <td style="color:var(--dark-gray);font-size:.8rem;"><?php echo ViewHelper::e($j['company_name'] ?? $j['employer_name'] ?? ''); ?></td>
                            <td><?php echo ViewHelper::jobStatusBadge($j['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Recent applications -->
    <div class="card" style="margin-bottom:2rem;">
        <div class="card-header">
            <h3 style="margin:0;font-size:1rem;">📨 Recent Applications</h3>
            <span class="badge badge-pending">
                <?php echo number_format($appStats['pending'] ?? 0); ?> pending
            </span>
        </div>
        <div class="table-responsive" style="margin-top:.75rem;">
            <table class="table table-hover" style="font-size:.88rem;">
                <thead><tr><th>Applicant</th><th>Job</th><th>Status</th><th>Applied</th></tr></thead>
                <tbody>
                    <?php foreach ($recent_apps as $a): ?>
                    <tr>
                        <td style="font-weight:600;"><?php echo ViewHelper::e($a['applicant_name']); ?></td>
                        <td style="color:var(--dark-gray);"><?php echo ViewHelper::e(ViewHelper::truncate($a['job_title'], 35)); ?></td>
                        <td><?php echo ViewHelper::appStatusBadge($a['status']); ?></td>
                        <td style="color:var(--dark-gray);font-size:.8rem;"><?php echo ViewHelper::timeAgo($a['applied_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick actions -->
    <div style="padding-top:2rem;border-top:2px solid var(--light-gray);">
        <h3>⚡ Quick Actions</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-top:1rem;">
            <?php foreach ([
                ['users.php',             '👥', 'Manage Users', 'View all users'],
                ['jobs.php',              '💼', 'Manage Jobs',  'View all jobs'],
                ['users.php?role=graduate','🎓', 'Graduates',   'Filter graduates'],
                ['users.php?role=employer','🏢', 'Employers',   'Filter employers'],
            ] as [$href, $icon, $lbl, $desc]): ?>
            <a href="<?php echo $href; ?>"
               style="text-align:center;padding:1.25rem 1rem;border:2px solid var(--light-gray);
                      border-radius:var(--radius-lg);text-decoration:none;color:var(--text-dark);
                      transition:var(--transition);"
               onmouseover="this.style.borderColor='var(--primary)';this.style.transform='translateY(-3px)';this.style.boxShadow='var(--shadow-md)'"
               onmouseout="this.style.borderColor='var(--light-gray)';this.style.transform='';this.style.boxShadow=''">
                <div style="font-size:1.75rem;margin-bottom:.4rem;"><?php echo $icon; ?></div>
                <div style="font-weight:700;"><?php echo $lbl; ?></div>
                <div style="font-size:.78rem;color:var(--dark-gray);"><?php echo $desc; ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
    const ctx = document.getElementById('regChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($regLabels); ?>,
            datasets: [{
                label: 'Registrations',
                data:  <?php echo json_encode($regData); ?>,
                borderColor: '#1a3c6e',
                backgroundColor: 'rgba(26,60,110,0.08)',
                borderWidth: 2,
                pointRadius: 3,
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } }
            }
        }
    });
})();
</script>

<?php include '../includes/footer.php'; ?>
