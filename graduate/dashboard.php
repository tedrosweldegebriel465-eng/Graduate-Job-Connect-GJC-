<?php
/**
 * Graduate Job Connect — Graduate Dashboard
 * Phase 5: Professional dashboard with stats, chart data, profile completion
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Controllers\ProfileController;
use App\Models\ApplicationModel;
use App\Models\JobModel;
use App\Models\SavedJobModel;
use App\Models\NotificationModel;
use App\Models\GraduateProfileModel;
use App\Helpers\ViewHelper;

AuthMiddleware::require('graduate', '../login.php');

$pdo         = getDBConnection();
$userId      = currentUserId();

// ─── Data ─────────────────────────────────────────────────────────────────────
$appModel    = new ApplicationModel($pdo);
$jobModel    = new JobModel($pdo);
$savedModel  = new SavedJobModel($pdo);
$notifModel  = new NotificationModel($pdo);
$profCtrl    = new ProfileController($pdo);
$profModel   = new GraduateProfileModel($pdo);

$appStats    = $appModel->getGraduateStats($userId);
$profile     = $profModel->findByUserId($userId);
$completion  = $profCtrl->getGraduateCompletion($userId);
$savedCount  = $savedModel->countSaved($userId);
$unreadNotif = $notifModel->countUnread($userId);

// Recent applications (last 5)
$recentApps  = $appModel->getGraduateApplications($userId, 1, 5)['data'];

// Latest 6 active jobs
$latestJobs  = $jobModel->searchJobs(page: 1, perPage: 6)['data'];

// Chart data: application status breakdown
$chartLabels = ['Pending', 'Shortlisted', 'Accepted', 'Rejected', 'Withdrawn'];
$chartData   = [
    (int)($appStats['pending']     ?? 0),
    (int)($appStats['shortlisted'] ?? 0),
    (int)($appStats['accepted']    ?? 0),
    (int)($appStats['rejected']    ?? 0),
    (int)($appStats['withdrawn']   ?? 0),
];
$chartColors = ['#f39c12','#3498db','#27ae60','#e74c3c','#95a5a6'];

$page_title   = 'Dashboard';
$css_path     = '../assets/css/';
$js_path      = '../assets/js/';
$home_path    = '../';
$logout_path  = '../';
$graduate_path= '';
include '../includes/header.php';
?>

<div class="container" style="padding:2rem 1rem;">

    <!-- ── Page header ───────────────────────────────────────────────────── -->
    <div style="display:flex;justify-content:space-between;align-items:center;
                flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;
                padding-bottom:1.5rem;border-bottom:2px solid var(--light-gray);">
        <div>
            <h1 style="margin:0;">🎓 Graduate Dashboard</h1>
            <p style="color:var(--dark-gray);margin:.25rem 0 0;">
                Welcome back, <strong><?php echo ViewHelper::e($_SESSION['user_name']); ?></strong>!
            </p>
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <a href="jobs.php"            class="btn btn-primary">💼 Browse Jobs</a>
            <a href="my-applications.php" class="btn btn-outline">📋 My Applications</a>
        </div>
    </div>

    <!-- ── Profile completion banner ─────────────────────────────────────── -->
    <?php if ($completion < 80): ?>
    <div class="alert alert-warning alert-dismissible" style="margin-bottom:1.5rem;">
        <span class="alert-icon">⚠️</span>
        <div style="flex:1;">
            <strong>Your profile is <?php echo $completion; ?>% complete.</strong>
            Complete it to get noticed by employers.
            <a href="profile.php" style="font-weight:700;color:inherit;text-decoration:underline;">
                Complete profile →
            </a>
            <div style="margin-top:.5rem;">
                <?php echo ViewHelper::completionBar($completion); ?>
            </div>
        </div>
        <button class="alert-close" aria-label="Close">&times;</button>
    </div>
    <?php endif; ?>

    <!-- ── Stats cards ───────────────────────────────────────────────────── -->
    <div class="stats-grid" style="margin-bottom:2rem;">

        <div class="stat-card">
            <div class="stat-icon" style="background:#e8f0fe;color:var(--primary);">📨</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($appStats['total'] ?? 0); ?></div>
                <div class="stat-label">Applications</div>
                <div class="stat-sub"><?php echo number_format($appStats['pending'] ?? 0); ?> pending</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda;color:var(--success);">✅</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($appStats['accepted'] ?? 0); ?></div>
                <div class="stat-label">Accepted</div>
                <div class="stat-sub"><?php echo number_format($appStats['shortlisted'] ?? 0); ?> shortlisted</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd;color:var(--warning);">❤️</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($savedCount); ?></div>
                <div class="stat-label">Saved Jobs</div>
                <div class="stat-sub"><a href="saved-jobs.php" style="color:var(--primary);">View saved →</a></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff;color:var(--info);">🔔</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($unreadNotif); ?></div>
                <div class="stat-label">Notifications</div>
                <div class="stat-sub"><a href="../notifications.php" style="color:var(--primary);">View all →</a></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:#f3e5f5;color:#6a1b9a;">📊</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $completion; ?>%</div>
                <div class="stat-label">Profile Complete</div>
                <div style="margin-top:.4rem;"><?php echo ViewHelper::completionBar($completion); ?></div>
            </div>
        </div>

    </div>

    <!-- ── Main grid: chart + recent applications ────────────────────────── -->
    <div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;margin-bottom:2rem;">

        <!-- Doughnut chart -->
        <?php if (array_sum($chartData) > 0): ?>
        <div class="card" style="display:flex;flex-direction:column;align-items:center;gap:1rem;">
            <div class="card-header" style="width:100%;">
                <h3 style="margin:0;font-size:1rem;">📊 Application Breakdown</h3>
            </div>
            <div style="position:relative;width:200px;height:200px;">
                <canvas id="appChart" width="200" height="200"></canvas>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:.4rem;justify-content:center;font-size:.8rem;">
                <?php foreach ($chartLabels as $i => $label): ?>
                    <?php if ($chartData[$i] > 0): ?>
                    <span style="display:flex;align-items:center;gap:.3rem;">
                        <span style="width:10px;height:10px;border-radius:50%;
                                     background:<?php echo $chartColors[$i]; ?>;display:inline-block;"></span>
                        <?php echo $label; ?> (<?php echo $chartData[$i]; ?>)
                    </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card" style="display:flex;flex-direction:column;align-items:center;
                                 justify-content:center;gap:.75rem;text-align:center;padding:2rem;">
            <div style="font-size:3rem;">📭</div>
            <p style="color:var(--dark-gray);margin:0;">No applications yet.</p>
            <a href="jobs.php" class="btn btn-primary btn-sm">Browse Jobs</a>
        </div>
        <?php endif; ?>

        <!-- Recent applications -->
        <div class="card">
            <div class="card-header">
                <h3 style="margin:0;font-size:1rem;">📨 Recent Applications</h3>
                <a href="my-applications.php" class="btn btn-outline btn-sm">View All</a>
            </div>
            <?php if (empty($recentApps)): ?>
                <p style="color:var(--dark-gray);padding:1rem 0;">
                    No applications yet. <a href="jobs.php">Browse jobs</a> to get started.
                </p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" style="font-size:.9rem;">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Company</th>
                            <th>Applied</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentApps as $app): ?>
                        <tr>
                            <td><strong><?php echo ViewHelper::e($app['job_title']); ?></strong></td>
                            <td style="color:var(--dark-gray);">
                                <?php echo ViewHelper::e($app['company_name'] ?? $app['employer_name'] ?? '—'); ?>
                            </td>
                            <td style="color:var(--dark-gray);font-size:.8rem;">
                                <?php echo ViewHelper::timeAgo($app['applied_at']); ?>
                            </td>
                            <td><?php echo ViewHelper::appStatusBadge($app['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ── Latest available jobs ─────────────────────────────────────────── -->
    <div class="card">
        <div class="card-header">
            <h3 style="margin:0;font-size:1rem;">💼 Latest Available Jobs</h3>
            <a href="jobs.php" class="btn btn-outline btn-sm">Browse All</a>
        </div>
        <?php if (empty($latestJobs)): ?>
            <p style="color:var(--dark-gray);padding:.5rem 0;">No jobs available right now.</p>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;margin-top:1rem;">
            <?php foreach ($latestJobs as $job): ?>
            <div style="border:1px solid var(--light-gray);border-radius:var(--radius);
                        padding:1rem;transition:var(--transition);"
                 onmouseover="this.style.borderColor='var(--primary)';this.style.boxShadow='var(--shadow-sm)'"
                 onmouseout="this.style.borderColor='var(--light-gray)';this.style.boxShadow='none'">
                <div style="font-weight:700;color:var(--primary);margin-bottom:.25rem;">
                    <?php echo ViewHelper::e($job['title']); ?>
                </div>
                <div style="font-size:.85rem;color:var(--dark-gray);margin-bottom:.5rem;">
                    🏢 <?php echo ViewHelper::e($job['company_name'] ?? $job['employer_name'] ?? ''); ?>
                    · 📍 <?php echo ViewHelper::e($job['location']); ?>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.75rem;">
                    <span class="badge badge-draft" style="text-transform:none;font-size:.75rem;">
                        <?php echo ViewHelper::e(getJobTypes()[$job['job_type']] ?? $job['job_type']); ?>
                    </span>
                    <a href="apply.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary btn-sm">Apply</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Quick actions ─────────────────────────────────────────────────── -->
    <div style="margin-top:2rem;padding-top:2rem;border-top:2px solid var(--light-gray);">
        <h3>⚡ Quick Actions</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-top:1rem;">
            <?php
            $actions = [
                ['profile.php',         '👤', 'My Profile',       'Update skills & CV'],
                ['jobs.php',            '🔍', 'Browse Jobs',      'Find opportunities'],
                ['my-applications.php', '📋', 'Applications',     'Track your status'],
                ['saved-jobs.php',      '❤️', 'Saved Jobs',       'Bookmarked listings'],
                ['../notifications.php','🔔', 'Notifications',    $unreadNotif > 0 ? "{$unreadNotif} unread" : 'All caught up'],
            ];
            foreach ($actions as [$href, $icon, $label, $desc]):
            ?>
            <a href="<?php echo $href; ?>"
               style="display:flex;flex-direction:column;align-items:center;text-align:center;
                      padding:1.25rem 1rem;border:2px solid var(--light-gray);
                      border-radius:var(--radius-lg);text-decoration:none;color:var(--text-dark);
                      transition:var(--transition);"
               onmouseover="this.style.borderColor='var(--primary)';this.style.transform='translateY(-3px)';this.style.boxShadow='var(--shadow-md)'"
               onmouseout="this.style.borderColor='var(--light-gray)';this.style.transform='';this.style.boxShadow=''">
                <span style="font-size:1.75rem;margin-bottom:.4rem;"><?php echo $icon; ?></span>
                <span style="font-weight:700;font-size:.95rem;"><?php echo $label; ?></span>
                <span style="font-size:.78rem;color:var(--dark-gray);margin-top:.2rem;"><?php echo $desc; ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const ctx = document.getElementById('appChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                data:            <?php echo json_encode($chartData); ?>,
                backgroundColor: <?php echo json_encode($chartColors); ?>,
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.parsed}`
                    }
                }
            },
            cutout: '65%'
        }
    });
})();

// Dismiss alerts
document.querySelectorAll('.alert-close').forEach(b => {
    b.addEventListener('click', function () {
        const el = this.closest('.alert');
        el.style.transition = 'opacity .4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    });
});
</script>

<?php include '../includes/footer.php'; ?>
