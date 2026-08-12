<?php
/**
 * Graduate Job Connect - Post New Job
 * University Project - Job posting form
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('employer');

$pdo         = getDBConnection();
$employer_id = $_SESSION['user_id'];
$message     = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die('Security verification failed. Please go back and try again.');
    }
    // Rotate token after use
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $title            = sanitizeInput($_POST['title']       ?? '');
    $description      = sanitizeInput($_POST['description'] ?? '');
    $location         = sanitizeInput($_POST['location']    ?? '');
    $requirements     = sanitizeInput($_POST['requirements'] ?? '');
    $preferred_skills = sanitizeInput($_POST['preferred_skills'] ?? '');
    $job_type         = sanitizeInput($_POST['job_type']    ?? '');
    $work_type        = sanitizeInput($_POST['work_type']   ?? 'onsite');
    $experience_level = sanitizeInput($_POST['experience_level'] ?? 'entry');
    $category         = sanitizeInput($_POST['category']    ?? '');
    $deadline         = sanitizeInput($_POST['application_deadline'] ?? '');
    $salary_min       = !empty($_POST['salary_min']) ? (float)$_POST['salary_min'] : null;
    $salary_max       = !empty($_POST['salary_max']) ? (float)$_POST['salary_max'] : null;

    $valid_job_types  = ['full-time', 'part-time', 'internship', 'contract', 'freelance'];
    $valid_work_types = ['onsite', 'remote', 'hybrid'];

    if (empty($title) || empty($description) || empty($location)) {
        $error_message = 'Please fill in all required fields (Title, Description, Location).';
    } elseif (!in_array($job_type, $valid_job_types)) {
        $error_message = 'Please select a valid job type.';
    } elseif (!in_array($work_type, $valid_work_types)) {
        $error_message = 'Please select a valid work type.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO jobs 
                    (employer_id, title, description, location, requirements, preferred_skills,
                     job_type, work_type, experience_level, category,
                     salary_min, salary_max, application_deadline, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $employer_id, $title, $description, $location, $requirements, $preferred_skills,
                $job_type, $work_type, $experience_level, $category,
                $salary_min, $salary_max,
                $deadline ?: null
            ]);

            $message = 'Job posted successfully!';
            $_POST   = [];

        } catch (PDOException $e) {
            error_log("Post Job Error: " . $e->getMessage());
            $error_message = 'Error posting job. Please try again.';
        }
    }
}

$page_title    = "Post New Job";
$css_path      = "../assets/css/";
$js_path       = "../assets/js/";
$home_path     = "../";
$logout_path   = "../";
$employer_path = "";
include '../includes/header.php';
?>

<div class="container" style="max-width: 800px;">
    <div class="flex justify-between align-center mb-20">
        <h1>Post New Job</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($message); ?>
            <div class="mt-20">
                <a href="my-jobs.php" class="btn">View My Jobs</a>
                <a href="post-job.php" class="btn btn-success">Post Another Job</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if (!$message): ?>
    <?php
    // Generate CSRF token if not set
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['csrf_token'];
    ?>
    <form method="POST" id="jobForm" class="card">
        <div class="card-header">
            <h3>Job Details</h3>
        </div>
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="form-group">
                <label for="title">Job Title *</label>
                <input type="text" id="title" name="title" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                       placeholder="e.g., Software Developer, Marketing Assistant" required>
            </div>

            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label for="job_type">Job Type *</label>
                    <select id="job_type" name="job_type" class="form-control" required>
                        <option value="">Select job type</option>
                        <?php foreach (getJobTypes() as $val => $label): ?>
                            <option value="<?php echo $val; ?>"
                                <?php echo (($_POST['job_type'] ?? '') === $val) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="work_type">Work Type</label>
                    <select id="work_type" name="work_type" class="form-control">
                        <?php foreach (getWorkTypes() as $val => $label): ?>
                            <option value="<?php echo $val; ?>"
                                <?php echo (($_POST['work_type'] ?? 'onsite') === $val) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label for="experience_level">Experience Level</label>
                    <select id="experience_level" name="experience_level" class="form-control">
                        <?php foreach (getExperienceLevels() as $val => $label): ?>
                            <option value="<?php echo $val; ?>"
                                <?php echo (($_POST['experience_level'] ?? 'entry') === $val) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" class="form-control">
                        <option value="">Select category</option>
                        <?php foreach (getIndustries() as $ind): ?>
                            <option value="<?php echo htmlspecialchars($ind); ?>"
                                <?php echo (($_POST['category'] ?? '') === $ind) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ind); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="location">Job Location *</label>
                <input type="text" id="location" name="location" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                       placeholder="e.g., Addis Ababa, Ethiopia or Remote" required>
            </div>

            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label for="salary_min">Salary Min (ETB)</label>
                    <input type="number" id="salary_min" name="salary_min" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['salary_min'] ?? ''); ?>"
                           placeholder="e.g., 30000" min="0" step="500">
                </div>
                <div class="form-group">
                    <label for="salary_max">Salary Max (ETB)</label>
                    <input type="number" id="salary_max" name="salary_max" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['salary_max'] ?? ''); ?>"
                           placeholder="e.g., 60000" min="0" step="500">
                </div>
            </div>

            <div class="form-group">
                <label for="application_deadline">Application Deadline</label>
                <input type="date" id="application_deadline" name="application_deadline" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['application_deadline'] ?? ''); ?>"
                       min="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="form-group">
                <label for="description">Job Description *</label>
                <textarea id="description" name="description" class="form-control"
                          rows="6" placeholder="Describe the job responsibilities, duties, and what you're looking for..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="requirements">Requirements &amp; Qualifications</label>
                <textarea id="requirements" name="requirements" class="form-control"
                          rows="4" placeholder="List the required skills, education, experience, etc."><?php echo htmlspecialchars($_POST['requirements'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="preferred_skills">Preferred Skills</label>
                <input type="text" id="preferred_skills" name="preferred_skills" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['preferred_skills'] ?? ''); ?>"
                       placeholder="e.g., Python, Laravel, React (comma-separated)">
            </div>

            <div class="form-group text-center">
                <button type="submit" class="btn btn-success">Post Job</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
