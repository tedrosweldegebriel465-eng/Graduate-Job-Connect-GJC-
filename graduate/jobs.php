<?php
/**
 * Graduate Job Connect — Browse Jobs
 * Features: keyword search, location, category, job type, work type, sort, pagination, save toggle
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Controllers\JobController;
use App\Middleware\AuthMiddleware;
use App\Helpers\ViewHelper;

AuthMiddleware::require('graduate', '../login.php');

$pdo         = getDBConnection();
$controller  = new JobController($pdo);
$graduateId  = currentUserId();

// ─── Handle AJAX save toggle ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_save') {
    AuthMiddleware::verifyCsrf($_POST['csrf_token'] ?? '');
    $jobId  = (int) ($_POST['job_id'] ?? 0);
    $result = $controller->toggleSave($graduateId, $jobId);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}

// ─── Search filters ───────────────────────────────────────────────────────────
$filters = [
    'q'          => trim($_GET['q']          ?? ''),
    'location'   => trim($_GET['location']   ?? ''),
    'category'   => trim($_GET['category']   ?? ''),
    'job_type'   => trim($_GET['job_type']   ?? ''),
    'work_type'  => trim($_GET['work_type']  ?? ''),
    'experience' => trim($_GET['experience'] ?? ''),
    'sort'       => trim($_GET['sort']       ?? 'created_at DESC'),
];
$page    = max(1, (int) ($_GET['page'] ?? 1));

// ─── Get jobs ─────────────────────────────────────────────────────────────────
$jobModel  = $controller->getJobModel();
$savedModel= $controller->getSavedModel();
$result    = $jobModel->searchJobs(
    keyword:    $filters['q'],
    location:   $filters['location'],
    category:   $filters['category'],
    jobType:    $filters['job_type'],
    workType:   $filters['work_type'],
    experience: $filters['experience'],
    sort:       $filters['sort'],
    page:       $page,
    perPage:    defined('DEFAULT_PER_PAGE') ? DEFAULT_PER_PAGE : 12
);

$jobs = $result['data'];

// Get saved job IDs for this graduate (for heart icon state)
$savedIds = [];
if (!empty($jobs)) {
    $jobIds   = array_column($jobs, 'id');
    $marks    = implode(',', array_fill(0, count($jobIds), '?'));
    $stmt     = $pdo->prepare(
        "SELECT job_id FROM saved_jobs WHERE graduate_id = ? AND job_id IN ({$marks})"
    );
    $stmt->execute(array_merge([$graduateId], $jobIds));
    $savedIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
}

$csrf         = AuthMiddleware::csrfToken();
$filterActive = array_filter(array_diff_key($filters, ['sort' => '']));

$page_title   = 'Browse Jobs';
$css_path     = '../assets/css/';
$js_path      = '../assets/js/';
$home_path    = '../';
$logout_path  = '../';
$graduate_path= '';
include '../includes/header.php';
?>

<div class="container" style="padding: 2rem 1rem;">

    <!-- Page Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
        <div>
            <h1 style="margin:0;">💼 Browse Jobs</h1>
            <p style="color:var(--dark-gray);margin:.25rem 0 0;">
                <?php echo number_format($result['total']); ?> job<?php echo $result['total'] !== 1 ? 's' : ''; ?> found
                <?php if (!empty($filters['q'])): ?>
                    for "<strong><?php echo ViewHelper::e($filters['q']); ?></strong>"
                <?php endif; ?>
            </p>
        </div>
        <a href="my-applications.php" class="btn btn-outline">📋 My Applications</a>
    </div>

    <!-- Search + Filter Bar -->
    <form method="GET" id="searchForm" class="search-bar-form"
          style="background:var(--white);padding:1.25rem;border-radius:var(--radius-lg);
                 box-shadow:var(--shadow-sm);margin-bottom:2rem;">

        <!-- Keyword + Location -->
        <div style="display:grid;grid-template-columns:2fr 1fr auto;gap:.75rem;margin-bottom:.75rem;flex-wrap:wrap;">
            <div style="position:relative;">
                <span style="position:absolute;left:.9rem;top:50%;transform:translateY(-50%);font-size:1rem;">🔍</span>
                <input type="text" name="q" class="form-control"
                       value="<?php echo ViewHelper::e($filters['q']); ?>"
                       placeholder="Job title, skill, or keyword…"
                       style="padding-left:2.5rem;">
            </div>
            <div style="position:relative;">
                <span style="position:absolute;left:.9rem;top:50%;transform:translateY(-50%);">📍</span>
                <input type="text" name="location" class="form-control"
                       value="<?php echo ViewHelper::e($filters['location']); ?>"
                       placeholder="Location…"
                       style="padding-left:2.5rem;">
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
        </div>

        <!-- Filter chips row -->
        <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;">
            <select name="category" class="form-control" style="flex:1;min-width:140px;height:40px;"
                    onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach (\App\Helpers\ViewHelper::class ? getIndustries() : [] as $cat): ?>
                    <option value="<?php echo ViewHelper::e($cat); ?>"
                        <?php echo $filters['category'] === $cat ? 'selected' : ''; ?>>
                        <?php echo ViewHelper::e($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="job_type" class="form-control" style="flex:1;min-width:130px;height:40px;"
                    onchange="this.form.submit()">
                <option value="">All Types</option>
                <?php foreach (getJobTypes() as $val => $label): ?>
                    <option value="<?php echo $val; ?>"
                        <?php echo $filters['job_type'] === $val ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="work_type" class="form-control" style="flex:1;min-width:120px;height:40px;"
                    onchange="this.form.submit()">
                <option value="">On-site/Remote</option>
                <?php foreach (getWorkTypes() as $val => $label): ?>
                    <option value="<?php echo $val; ?>"
                        <?php echo $filters['work_type'] === $val ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="experience" class="form-control" style="flex:1;min-width:140px;height:40px;"
                    onchange="this.form.submit()">
                <option value="">All Levels</option>
                <?php foreach (getExperienceLevels() as $val => $label): ?>
                    <option value="<?php echo $val; ?>"
                        <?php echo $filters['experience'] === $val ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="sort" class="form-control" style="flex:1;min-width:150px;height:40px;"
                    onchange="this.form.submit()">
                <option value="created_at DESC" <?php echo $filters['sort'] === 'created_at DESC' ? 'selected' : ''; ?>>Newest First</option>
                <option value="created_at ASC"  <?php echo $filters['sort'] === 'created_at ASC'  ? 'selected' : ''; ?>>Oldest First</option>
                <option value="application_deadline ASC" <?php echo $filters['sort'] === 'application_deadline ASC' ? 'selected' : ''; ?>>Deadline Soon</option>
                <option value="application_count DESC"   <?php echo $filters['sort'] === 'application_count DESC'   ? 'selected' : ''; ?>>Most Applied</option>
            </select>

            <?php if ($filterActive): ?>
                <a href="jobs.php" class="btn btn-outline btn-sm">✕ Clear Filters</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Results -->
    <?php if (empty($jobs)): ?>
        <div class="card" style="text-align:center;padding:3rem 1rem;">
            <div style="font-size:4rem;margin-bottom:1rem;">🔍</div>
            <h3>No jobs found</h3>
            <p style="color:var(--dark-gray);">
                Try adjusting your search terms or clearing filters.
            </p>
            <a href="jobs.php" class="btn btn-primary">Browse All Jobs</a>
        </div>

    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1.5rem;margin-bottom:2rem;">
            <?php foreach ($jobs as $job): ?>
            <?php
                $isSaved   = in_array($job['id'], $savedIds, false);
                $days      = $job['days_remaining'] ?? null;
                $isExpiring= $days !== null && $days >= 0 && $days <= 7;
                $isExpired = $days !== null && $days < 0;
            ?>
            <article class="card" style="display:flex;flex-direction:column;gap:.75rem;border-left:4px solid var(--primary);">

                <!-- Header: logo + company + featured badge -->
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
                    <div style="display:flex;align-items:center;gap:.75rem;">
                        <?php if (!empty($job['logo_filename'])): ?>
                            <img src="../uploads/logos/<?php echo ViewHelper::e($job['logo_filename']); ?>"
                                 alt="<?php echo ViewHelper::e($job['company_name'] ?? ''); ?>"
                                 style="width:44px;height:44px;border-radius:8px;object-fit:cover;border:1px solid var(--gray);">
                        <?php else: ?>
                            <div style="width:44px;height:44px;border-radius:8px;background:var(--primary-gradient);
                                        display:flex;align-items:center;justify-content:center;
                                        color:#fff;font-size:1.3rem;">🏢</div>
                        <?php endif; ?>
                        <div>
                            <div style="font-size:.85rem;color:var(--dark-gray);">
                                <?php echo ViewHelper::e($job['company_name'] ?? $job['employer_name'] ?? ''); ?>
                            </div>
                            <div style="font-size:.8rem;color:var(--dark-gray);">
                                📍 <?php echo ViewHelper::e($job['location']); ?>
                            </div>
                        </div>
                    </div>
                    <!-- Save button -->
                    <button type="button"
                            class="save-btn"
                            data-job-id="<?php echo $job['id']; ?>"
                            data-csrf="<?php echo $csrf; ?>"
                            title="<?php echo $isSaved ? 'Unsave job' : 'Save job'; ?>"
                            style="background:none;border:none;cursor:pointer;font-size:1.4rem;
                                   line-height:1;padding:.25rem;flex-shrink:0;"
                            aria-label="<?php echo $isSaved ? 'Unsave' : 'Save'; ?> job">
                        <?php echo $isSaved ? '❤️' : '🤍'; ?>
                    </button>
                </div>

                <!-- Job title -->
                <h3 style="margin:0;font-size:1.1rem;color:var(--primary);">
                    <?php echo ViewHelper::e($job['title']); ?>
                </h3>

                <!-- Description snippet -->
                <p style="color:var(--dark-gray);font-size:.9rem;line-height:1.6;margin:0;flex:1;">
                    <?php echo ViewHelper::e(ViewHelper::truncate($job['description'], 110)); ?>
                </p>

                <!-- Meta chips -->
                <div style="display:flex;flex-wrap:wrap;gap:.4rem;">
                    <span class="badge badge-draft" style="text-transform:none;">
                        <?php echo ViewHelper::e(getJobTypes()[$job['job_type']] ?? $job['job_type']); ?>
                    </span>
                    <span class="badge badge-draft" style="text-transform:none;">
                        <?php echo ViewHelper::e(getWorkTypes()[$job['work_type']] ?? $job['work_type']); ?>
                    </span>
                    <?php if (!empty($job['salary_min']) || !empty($job['salary_max'])): ?>
                    <span class="badge badge-shortlisted" style="text-transform:none;">
                        💰 <?php echo ViewHelper::formatSalary($job['salary_min'], $job['salary_max'], $job['salary_currency'] ?? 'ETB'); ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($job['is_featured']): ?>
                    <span class="badge badge-featured">⭐ Featured</span>
                    <?php endif; ?>
                </div>

                <!-- Deadline + footer -->
                <div style="display:flex;justify-content:space-between;align-items:center;
                            padding-top:.75rem;border-top:1px solid var(--light-gray);">
                    <div style="font-size:.8rem;">
                        <?php if ($isExpired): ?>
                            <span style="color:var(--danger);">⛔ Deadline passed</span>
                        <?php elseif ($isExpiring): ?>
                            <span style="color:var(--warning);">⚠️ <?php echo $days; ?>d left</span>
                        <?php elseif ($days !== null): ?>
                            <span style="color:var(--dark-gray);">📅 <?php echo $days; ?> days left</span>
                        <?php else: ?>
                            <span style="color:var(--dark-gray);">📅 No deadline</span>
                        <?php endif; ?>
                        <span style="margin-left:.5rem;color:var(--dark-gray);">
                            · <?php echo number_format($job['application_count'] ?? 0); ?> applied
                        </span>
                    </div>
                    <a href="apply.php?job_id=<?php echo $job['id']; ?>"
                       class="btn btn-primary btn-sm"
                       <?php echo $isExpired ? 'disabled style="opacity:.5;pointer-events:none;"' : ''; ?>>
                        Apply →
                    </a>
                </div>

            </article>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php echo ViewHelper::pagination($result, 'jobs.php?' . http_build_query(array_filter($filters))); ?>

    <?php endif; ?>

</div><!-- /container -->

<script>
// Save / unsave toggle (AJAX, no page reload)
document.querySelectorAll('.save-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
        const jobId = this.dataset.jobId;
        const csrf  = this.dataset.csrf;
        const body  = new URLSearchParams({action: 'toggle_save', job_id: jobId, csrf_token: csrf});
        try {
            const res  = await fetch('jobs.php', {method: 'POST', body});
            const data = await res.json();
            if (data.success) {
                this.textContent = data.action === 'saved' ? '❤️' : '🤍';
                this.title       = data.action === 'saved' ? 'Unsave job' : 'Save job';
            }
        } catch (e) {
            console.error('Save toggle failed', e);
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
