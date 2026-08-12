<?php
/**
 * Graduate Job Connect — Landing Page
 */

require_once __DIR__ . '/config/bootstrap.php';

use App\Models\JobModel;
use App\Models\UserModel;
use App\Models\ApplicationModel;
use App\Helpers\ViewHelper;

$pdo = getDBConnection();

// ─── Graceful DB queries — never crash the homepage ──────────────────────────
$featuredJobs = [];
$userStats    = [];
$jobStats     = [];
$appStats     = [];
$dbError      = null;

try {
    $jobModel     = new JobModel($pdo);
    $featuredJobs = $jobModel->getFeaturedJobs(6);
    $jobStats     = $jobModel->getAdminStats();
} catch (\Throwable $e) {
    $dbError = $e->getMessage();
    error_log('[index.php] JobModel error: ' . $e->getMessage());
}

try {
    $userModel = new UserModel($pdo);
    $userStats = $userModel->getStats();
} catch (\Throwable $e) {
    error_log('[index.php] UserModel error: ' . $e->getMessage());
}

try {
    $appModel  = new ApplicationModel($pdo);
    $appStats  = $appModel->getAdminStats();
} catch (\Throwable $e) {
    error_log('[index.php] ApplicationModel error: ' . $e->getMessage());
}

$page_title = 'Home';
include 'includes/header.php';
?>

<?php if ($dbError): ?>
<!-- ── Setup banner (shown when DB tables are missing) ──────────────────── -->
<div style="background:#fff3cd;border-bottom:3px solid #f39c12;padding:1.25rem 2rem;">
    <div class="container" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
            <strong style="color:#856404;">⚠️ Database setup required</strong>
            <span style="color:#856404;font-size:.9rem;margin-left:.5rem;">
                Some tables are missing. Run the migration to complete setup.
            </span>
        </div>
        <a href="run_migration.php"
           style="background:#e67e22;color:#fff;padding:.45rem 1.1rem;border-radius:6px;
                  text-decoration:none;font-weight:600;font-size:.88rem;white-space:nowrap;">
            🔄 Run Migration
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ── Hero ─────────────────────────────────────────────────────────────── -->
<section class="hero-section" aria-label="Hero">
    <div class="hero-container">
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">Graduate Job Connect</h1>
                <p class="hero-eyebrow">GJC | Connecting Graduates with Opportunities</p>
                <p class="hero-subtitle">Your gateway to meaningful careers</p>
                <p class="hero-description">
                    Bridging talented Ethiopian university graduates with employers who are looking
                    for fresh, motivated talent. Start your journey today.
                </p>
                <?php if (!isLoggedIn()): ?>
                <div class="hero-buttons">
                    <a href="register.php?role=graduate" class="btn btn-secondary btn-lg">🎓 Find Jobs</a>
                    <a href="register.php?role=employer" class="btn btn-outline btn-lg"
                       style="color:#fff;border-color:#fff;">🏢 Post Jobs</a>
                </div>
                <?php else: ?>
                <div class="hero-buttons">
                    <a href="<?php echo currentRole();?>/dashboard.php" class="btn btn-secondary btn-lg">
                        Go to Dashboard →
                    </a>
                </div>
                <?php endif;?>
            </div>
            <div class="hero-image">
                <img src="images/hero.svg" alt="Graduate Job Connect platform preview"
                     class="hero-img" loading="eager" width="520" height="380">
            </div>
        </div>
    </div>
</section>

<!-- ── Live platform stats ───────────────────────────────────────────────── -->
<section style="background:var(--white);padding:2.5rem 0;border-bottom:1px solid var(--light-gray);"
         aria-label="Platform statistics">
    <div class="container">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:2rem;text-align:center;">
            <?php foreach ([
                [number_format($userStats['graduates'] ?? 0) . '+', 'Graduates Registered', '🎓'],
                [number_format($userStats['employers'] ?? 0) . '+', 'Companies Hiring',      '🏢'],
                [number_format($jobStats['active_jobs']  ?? 0),     'Active Jobs',           '💼'],
                [number_format($appStats['accepted']     ?? 0) . '+','Successful Hires',      '✅'],
            ] as [$num, $lbl, $icon]): ?>
            <div>
                <div style="font-size:2.5rem;font-weight:800;color:var(--primary);"><?php echo $num;?></div>
                <div style="font-size:.9rem;color:var(--dark-gray);margin-top:.2rem;">
                    <?php echo $icon;?> <?php echo $lbl;?>
                </div>
            </div>
            <?php endforeach;?>
        </div>
    </div>
</section>

<div class="container" style="padding:3rem 1rem;">

    <?php if (isLoggedIn()): ?>
    <!-- ── Logged-in welcome ─────────────────────────────────────────────── -->
    <div class="alert alert-info" style="margin-bottom:2rem;">
        <span class="alert-icon">👋</span>
        <div>
            Welcome back, <strong><?php echo ViewHelper::e($_SESSION['user_name']);?></strong>!
            <a href="<?php echo currentRole();?>/dashboard.php" class="btn btn-primary btn-sm"
               style="margin-left:1rem;">Go to Dashboard</a>
        </div>
    </div>
    <?php endif;?>

    <?php if (!empty($featuredJobs)): ?>
    <!-- ── Featured Jobs ─────────────────────────────────────────────────── -->
    <section aria-labelledby="featured-heading" style="margin-bottom:3rem;">
        <div class="section-header" style="text-align:left;margin-bottom:1.5rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
                <div>
                    <h2 id="featured-heading" style="margin:0;">⭐ Featured Opportunities</h2>
                    <p style="color:var(--dark-gray);margin:.25rem 0 0;">Hand-picked roles from top employers</p>
                </div>
                <a href="graduate/jobs.php" class="btn btn-outline">View All Jobs →</a>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.5rem;">
            <?php foreach ($featuredJobs as $job):
                $days = $job['application_deadline']
                    ? ViewHelper::daysUntil($job['application_deadline']) : null;
            ?>
            <article class="card" style="border-left:4px solid var(--secondary);display:flex;flex-direction:column;gap:.75rem;">

                <!-- Company logo + name -->
                <div style="display:flex;align-items:center;gap:.75rem;">
                    <?php if (!empty($job['logo_filename'])): ?>
                    <img src="uploads/logos/<?php echo ViewHelper::e($job['logo_filename']);?>"
                         alt="<?php echo ViewHelper::e($job['company_name'] ?? '');?>"
                         style="width:40px;height:40px;border-radius:8px;object-fit:cover;border:1px solid var(--gray);">
                    <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:8px;background:var(--primary-gradient);
                                display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;">🏢</div>
                    <?php endif;?>
                    <div>
                        <div style="font-weight:600;font-size:.85rem;color:var(--text-dark);">
                            <?php echo ViewHelper::e($job['company_name'] ?? '');?>
                        </div>
                        <div style="font-size:.78rem;color:var(--dark-gray);">
                            📍 <?php echo ViewHelper::e($job['location']);?>
                        </div>
                    </div>
                    <span class="badge badge-featured" style="margin-left:auto;">⭐ Featured</span>
                </div>

                <!-- Title + snippet -->
                <h3 style="margin:0;font-size:1rem;color:var(--primary);">
                    <?php echo ViewHelper::e($job['title']);?>
                </h3>
                <p style="font-size:.85rem;color:var(--dark-gray);line-height:1.6;margin:0;flex:1;">
                    <?php echo ViewHelper::e(ViewHelper::truncate($job['description'], 100));?>
                </p>

                <!-- Chips -->
                <div style="display:flex;flex-wrap:wrap;gap:.35rem;">
                    <span class="badge badge-draft" style="text-transform:none;font-size:.72rem;">
                        <?php echo ViewHelper::e(getJobTypes()[$job['job_type']] ?? $job['job_type']);?>
                    </span>
                    <?php if (!empty($job['salary_min']) || !empty($job['salary_max'])): ?>
                    <span class="badge badge-shortlisted" style="text-transform:none;font-size:.72rem;">
                        💰 <?php echo ViewHelper::formatSalary($job['salary_min'],$job['salary_max'],$job['salary_currency']??'ETB');?>
                    </span>
                    <?php endif;?>
                    <?php if ($days !== null && $days >= 0 && $days <= 7): ?>
                    <span class="badge badge-pending" style="font-size:.72rem;">⚠️ <?php echo $days;?>d left</span>
                    <?php endif;?>
                </div>

                <!-- CTA -->
                <div style="display:flex;justify-content:space-between;align-items:center;
                            padding-top:.75rem;border-top:1px solid var(--light-gray);margin-top:auto;">
                    <span style="font-size:.78rem;color:var(--dark-gray);">
                        📅 <?php echo ViewHelper::timeAgo($job['created_at'] ?? '');?>
                    </span>
                    <?php if (isLoggedIn() && isGraduate()): ?>
                    <a href="graduate/apply.php?job_id=<?php echo $job['id'];?>" class="btn btn-primary btn-sm">
                        Apply →
                    </a>
                    <?php elseif (!isLoggedIn()): ?>
                    <a href="register.php?role=graduate" class="btn btn-primary btn-sm">
                        Register to Apply
                    </a>
                    <?php endif;?>
                </div>

            </article>
            <?php endforeach;?>
        </div>
    </section>
    <?php endif;?>

    <!-- ── How it works ──────────────────────────────────────────────────── -->
    <?php if (!isLoggedIn()): ?>
    <section aria-labelledby="user-types-heading" style="margin-bottom:3rem;">
        <div class="section-header">
            <h2 id="user-types-heading">Choose Your Path</h2>
            <p>Join thousands of graduates and employers already on the platform</p>
        </div>

        <div class="user-types-grid">

            <div class="user-type-card graduate-card">
                <div class="card-image">
                    <img src="images/graduate.svg" alt="Graduate using platform" class="card-img" loading="lazy" width="480" height="320">
                </div>
                <div class="card-content">
                    <h3>🎓 For Graduates</h3>
                    <p>Find your perfect opportunity and launch your career with top employers across Ethiopia.</p>
                    <ul class="feature-list">
                        <li>Browse hundreds of active job opportunities</li>
                        <li>Upload and manage your CV</li>
                        <li>Track every application in real-time</li>
                        <li>Save interesting jobs for later</li>
                        <li>Get notified on status changes</li>
                    </ul>
                    <a href="register.php?role=graduate" class="btn btn-success btn-full">
                        Create Graduate Account
                    </a>
                </div>
            </div>

            <div class="user-type-card employer-card">
                <div class="card-image">
                    <img src="images/employer.svg" alt="Employer managing applicants" class="card-img" loading="lazy" width="480" height="320">
                </div>
                <div class="card-content">
                    <h3>🏢 For Employers</h3>
                    <p>Post openings and connect with motivated, qualified graduates ready to contribute from day one.</p>
                    <ul class="feature-list">
                        <li>Post unlimited job listings</li>
                        <li>Full Applicant Tracking System (ATS)</li>
                        <li>Review CVs and cover letters</li>
                        <li>Shortlist, accept or reject applicants</li>
                        <li>Analytics on job performance</li>
                    </ul>
                    <a href="register.php?role=employer" class="btn btn-primary btn-full">
                        Create Employer Account
                    </a>
                </div>
            </div>

        </div>

        <div style="text-align:center;margin-top:1.5rem;">
            <p style="color:var(--dark-gray);">
                Already have an account?
                <a href="login.php" class="btn btn-outline" style="margin-left:.5rem;">Login Here</a>
            </p>
        </div>
    </section>

    <!-- How it works steps -->
    <section style="background:var(--light-gray);border-radius:var(--radius-xl);padding:3rem 2rem;margin-bottom:3rem;"
             aria-labelledby="how-heading">
        <div class="section-header">
            <h2 id="how-heading">How It Works</h2>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;text-align:center;">
            <?php foreach ([
                ['1','Register','Create your free account as a graduate or employer in under 2 minutes.'],
                ['2','Build Profile','Add your skills, CV, and experience so employers can find you.'],
                ['3','Apply / Post','Graduates apply with one click. Employers post jobs in minutes.'],
                ['4','Get Hired','Track your applications and land your next opportunity.'],
            ] as [$step,$title,$desc]): ?>
            <div style="background:var(--white);padding:1.5rem;border-radius:var(--radius-lg);
                        box-shadow:var(--shadow-sm);">
                <div style="width:40px;height:40px;border-radius:50%;background:var(--primary-gradient);
                            color:#fff;font-weight:800;font-size:1.1rem;display:flex;align-items:center;
                            justify-content:center;margin:0 auto 1rem;">
                    <?php echo $step;?>
                </div>
                <h4 style="color:var(--primary);margin-bottom:.5rem;"><?php echo $title;?></h4>
                <p style="color:var(--dark-gray);font-size:.9rem;margin:0;"><?php echo $desc;?></p>
            </div>
            <?php endforeach;?>
        </div>
    </section>
    <?php endif;?>

</div><!-- /container -->

<?php include 'includes/footer.php'; ?>
