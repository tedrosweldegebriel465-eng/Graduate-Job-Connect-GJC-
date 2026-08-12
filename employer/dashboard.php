<?php
/**
 * Graduate Job Connect — Employer Dashboard  (Phase 5)
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Controllers\ProfileController;
use App\Models\JobModel;
use App\Models\ApplicationModel;
use App\Models\EmployerProfileModel;
use App\Helpers\ViewHelper;

AuthMiddleware::require('employer', '../login.php');

$pdo        = getDBConnection();
$employerId = currentUserId();

$jobModel   = new JobModel($pdo);
$appModel   = new ApplicationModel($pdo);
$profCtrl   = new ProfileController($pdo);
$profModel  = new EmployerProfileModel($pdo);

$jobStats   = $jobModel->getEmployerStats($employerId);
$appStats   = $appModel->getEmployerApplicationStats($employerId);
$profile    = $profModel->findByUserId($employerId);
$completion = $profCtrl->getEmployerCompletion($employerId);

// Recent applications (latest 6)
$recentApps = $appModel->getEmployerApplications($employerId, page: 1, perPage: 6)['data'];

// Recent jobs (latest 5)
$recentJobs = $jobModel->getEmployerJobs($employerId, page: 1, perPage: 5)['data'];

// Weekly applications (last 14 days) for bar chart
$stmt = $pdo->prepare("
    SELECT DATE(a.applied_at) AS day, COUNT(*) AS cnt
    FROM applications a JOIN jobs j ON a.job_id = j.id
    WHERE j.employer_id = ? AND a.applied_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY DATE(a.applied_at)
    ORDER BY day ASC
");
$stmt->execute([$employerId]);
$rawWeekly = $stmt->fetchAll();

// Build 14-day labels + data arrays
$weeklyLabels = [];
$weeklyData   = [];
$weeklyMap    = array_column($rawWeekly, 'cnt', 'day');
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $weeklyLabels[] = date('M j', strtotime($d));
    $weeklyData[]   = (int) ($weeklyMap[$d] ?? 0);
}

// Status doughnut
$statusLabels = ['Pending', 'Shortlisted', 'Accepted', 'Rejected'];
$statusData   = [
    (int)($appStats['pending']     ?? 0),
    (int)($appStats['shortlisted'] ?? 0),
    (int)($appStats['accepted']    ?? 0),
    (int)($appStats['rejected']    ?? 0),
];
$statusColors = ['#f39c12','#3498db','#27ae60','#e74c3c'];

$page_title    = 'Employer Dashboard';
$css_path      = '../assets/css/';
$js_path       = '../assets/js/';
$home_path     = '../';
$logout_path   = '../';
$employer_path = '';
include '../includes/header.php';
?>

<div class="container" style="padding:2rem 1rem;">

    <!-- Page header -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;
                gap:1rem;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:2px solid var(--light-gray);">
        <div>
            <h1 style="margin:0;">🏢 Employer Dashboard</h1>
            <p style="color:var(--dark-gray);margin:.25rem 0 0;">
                Welcome back, <strong><?php echo ViewHelper::e($_SESSION['user_name']); ?></strong>
                <?php if ($profile && $profile['company_name']): ?>
                — <?php echo ViewHelper::e($profile['company_name']); ?>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <a href="post-job.php"  class="btn btn-primary">➕ Post Job</a>
            <a href="applicants.php" class="btn btn-outline">📨 Applicants</a>
        </div>
    </div>

    <!-- Profile completion -->
    <?php if ($completion < 80): ?>
    <div class="alert alert-warning alert-dismissible" style="margin-bottom:1.5rem;">
        <span class="alert-icon">⚠️</span>
        <div style="flex:1;">
            <strong>Company profile is <?php echo $completion; ?>% complete.</strong>
            A complete profile attracts more quality applicants.
            <a href="profile.php" style="font-weight:700;color:inherit;text-decoration:underline;">
                Complete profile →
            </a>
            <div style="margin-top:.5rem;"><?php echo ViewHelper::completionBar($completion); ?></div>
        </div>
        <button class="alert-close">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Stats cards -->
    <div class="stats-grid" style="margin-bottom:2rem;">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e8f0fe;color:var(--primary);">💼</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($jobStats['total_jobs'] ?? 0); ?></div>
                <div class="stat-label">Total Jobs</div>
                <div class="stat-sub"><?php echo number_format($jobStats['active_jobs'] ?? 0); ?> active</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda;color:var(--success);">📨</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($appStats['total'] ?? 0); ?></div>
                <div class="stat-label">Applications</div>
                <div class="stat-sub"><?php echo number_format($appStats['pending'] ?? 0); ?> pending review</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd;color:var(--warning);">⭐</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($appStats['shortlisted'] ?? 0); ?></div>
                <div class="stat-label">Shortlisted</div>
                <div class="stat-sub"><?php echo number_format($appStats['accepted'] ?? 0); ?> accepted</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff;color:var(--info);">👁️</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($jobStats['total_views'] ?? 0); ?></div>
                <div class="stat-label">Total Views</div>
                <div class="stat-sub">Across all postings</div>
            </div>
        </div>
    </div>

    <!-- Charts row -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;margin-bottom:2rem;">

        <!-- Bar chart: 14-day applications -->
        <div class="card">
            <div class="card-header">
                <h3 style="margin:0;font-size:1rem;">📈 Applications — Last 14 Days</h3>
            </div>
            <div style="position:relative;height:220px;margin-top:.5rem;">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>

        <!-- Doughnut: status breakdown -->
        <div class="card" style="display:flex;flex-direction:column;align-items:center;gap:1rem;">
            <div class="card-header" style="width:100%;">
                <h3 style="margin:0;font-size:1rem;">🥧 Status Breakdown</h3>
            </div>
            <?php if (array_sum($statusData) > 0): ?>
            <canvas id="statusChart" width="160" height="160"></canvas>
            <div style="display:flex;flex-direction:column;gap:.3rem;font-size:.8rem;width:100%;">
                <?php foreach ($statusLabels as $i => $sl): ?>
                <div style="display:flex;justify-content:space-between;">
                    <span style="display:flex;align-items:center;gap:.4rem;">
                        <span style="width:10px;height:10px;border-radius:50%;background:<?php echo $statusColors[$i]; ?>;display:inline-block;"></span>
                        <?php echo $sl; ?>
                    </span>
                    <strong><?php echo $statusData[$i]; ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align:center;color:var(--dark-gray);padding:1rem 0;">
                No applications yet.
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Recent applications + recent jobs -->
    <div style="display:grid;grid-template-columns:3fr 2fr;gap:1.5rem;margin-bottom:2rem;">

        <!-- Recent applications -->
        <div class="card">
            <div class="card-header">
                <h3 style="margin:0;font-size:1rem;">📨 Recent Applications</h3>
                <a href="applicants.php" class="btn btn-outline btn-sm">View All</a>
            </div>
            <?php if (empty($recentApps)): ?>
            <p style="color:var(--dark-gray);padding:.5rem 0;">No applications yet.</p>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:.6rem;margin-top:.75rem;">
                <?php foreach ($recentApps as $app): ?>
                <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem;
                            border-radius:var(--radius);border:1px solid var(--light-gray);">
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--primary-gradient);
                                color:#fff;display:flex;align-items:center;justify-content:center;
                                font-weight:700;font-size:.85rem;flex-shrink:0;">
                        <?php echo ViewHelper::initials($app['applicant_name']); ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?php echo ViewHelper::e($app['applicant_name']); ?>
                        </div>
                        <div style="font-size:.78rem;color:var(--dark-gray);">
                            for <?php echo ViewHelper::e($app['job_title']); ?>
                            · <?php echo ViewHelper::timeAgo($app['applied_at']); ?>
                        </div>
                    </div>
                    <div style="flex-shrink:0;">
                        <?php echo ViewHelper::appStatusBadge($app['status']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent jobs -->
        <div class="card">
            <div class="card-header">
                <h3 style="margin:0;font-size:1rem;">💼 Your Jobs</h3>
                <a href="my-jobs.php" class="btn btn-outline btn-sm">Manage</a>
            </div>
            <?php if (empty($recentJobs)): ?>
            <div style="text-align:center;padding:1.5rem 0;color:var(--dark-gray);">
                <p>No jobs posted yet.</p>
                <a href="post-job.php" class="btn btn-primary btn-sm">Post a Job</a>
            </div>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:.5rem;margin-top:.75rem;">
                <?php foreach ($recentJobs as $job): ?>
                <div style="padding:.6rem;border-radius:var(--radius);border:1px solid var(--light-gray);">
                    <div style="font-weight:600;font-size:.9rem;color:var(--primary);margin-bottom:.2rem;">
                        <?php echo ViewHelper::e($job['title']); ?>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:.78rem;color:var(--dark-gray);">
                            <?php echo number_format($job['application_count'] ?? 0); ?> applicants
                            <?php if (($job['days_remaining'] ?? null) !== null): ?>
                            · <?php
                                $dr = (int)$job['days_remaining'];
                                if ($dr < 0) echo '<span style="color:var(--danger);">Expired</span>';
                                elseif ($dr <= 7) echo "<span style='color:var(--warning);'>{$dr}d left</span>";
                                else echo "{$dr}d left";
                            ?>
                            <?php endif; ?>
                        </span>
                        <?php echo ViewHelper::jobStatusBadge($job['status']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Quick actions -->
    <div style="padding-top:2rem;border-top:2px solid var(--light-gray);">
        <h3>⚡ Quick Actions</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-top:1rem;">
            <?php foreach ([
                ['post-job.php',   '➕', 'Post Job',    'New listing'],
                ['my-jobs.php',    '📋', 'My Jobs',     'Manage postings'],
                ['applicants.php', '📨', 'Applicants',  'Review candidates'],
                ['profile.php',    '🏢', 'Company',     'Edit profile'],
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
// Weekly bar chart
(function(){
    const ctx = document.getElementById('weeklyChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($weeklyLabels); ?>,
            datasets: [{
                label: 'Applications',
                data:  <?php echo json_encode($weeklyData); ?>,
                backgroundColor: 'rgba(26,60,110,0.7)',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                x: { ticks: { maxRotation: 45 } }
            }
        }
    });
})();

// Status doughnut
(function(){
    const ctx = document.getElementById('statusChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($statusLabels); ?>,
            datasets: [{
                data:            <?php echo json_encode($statusData); ?>,
                backgroundColor: <?php echo json_encode($statusColors); ?>,
                borderWidth: 2, borderColor: '#fff'
            }]
        },
        options: { responsive:false, plugins:{ legend:{ display:false } }, cutout:'60%' }
    });
})();

document.querySelectorAll('.alert-close').forEach(b =>
    b.addEventListener('click', () => b.closest('.alert').remove())
);
</script>

<?php include '../includes/footer.php'; ?>
