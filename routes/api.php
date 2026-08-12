<?php
/**
 * Graduate Job Connect — REST API
 *
 * Lightweight JSON API layer.
 * All endpoints return: { "success": bool, "data": mixed, "error": string|null }
 *
 * Usage: route all requests through Apache rewrite or call directly.
 *   GET  /routes/api.php?resource=jobs
 *   POST /routes/api.php?resource=auth&action=login
 *
 * Phase 6: This file will be replaced by a proper router.
 * For now it documents all planned endpoints and implements the stable ones.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Models\JobModel;
use App\Models\ApplicationModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\ApplicationService;
use App\Services\JobService;
use App\Middleware\AuthMiddleware;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ─── CORS (restrict in production) ───────────────────────────────────────────
if (APP_ENV === 'development') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit(); }

// ─── Helpers ─────────────────────────────────────────────────────────────────

function apiSuccess(mixed $data, string $message = 'OK', int $code = 200): never
{
    http_response_code($code);
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data],
                     JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function apiError(string $error, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $error, 'data' => null],
                     JSON_UNESCAPED_UNICODE);
    exit();
}

function requireApiAuth(): int
{
    if (!isLoggedIn()) apiError('Unauthenticated.', 401);
    return currentUserId();
}

// ─── Router ──────────────────────────────────────────────────────────────────

$pdo      = getDBConnection();
$resource = strtolower(trim($_GET['resource'] ?? ''));
$action   = strtolower(trim($_GET['action']   ?? ''));
$id       = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : null;
$method   = $_SERVER['REQUEST_METHOD'];

// Parse JSON body for PUT/POST
$body = [];
if (in_array($method, ['POST', 'PUT'], true)) {
    $raw  = file_get_contents('php://input');
    $body = $raw ? (json_decode($raw, true) ?? $_POST) : $_POST;
}

// ─── Routes ──────────────────────────────────────────────────────────────────

match ($resource) {

    // ── POST /api?resource=auth&action=login ──────────────────────────────
    'auth' => (function () use ($pdo, $action, $method, $body) {
        $auth = new AuthService($pdo);
        $auth->startSession();

        if ($action === 'login' && $method === 'POST') {
            $result = $auth->login($body['email'] ?? '', $body['password'] ?? '');
            if (!$result['success']) apiError($result['error'], 401);
            apiSuccess([
                'user_id'   => currentUserId(),
                'user_name' => $_SESSION['user_name'],
                'role'      => currentRole(),
            ], 'Login successful.');
        }

        if ($action === 'logout') {
            $auth->logout();
            apiSuccess(null, 'Logged out.');
        }

        if ($action === 'me' && $method === 'GET') {
            requireApiAuth();
            apiSuccess([
                'user_id'   => currentUserId(),
                'user_name' => $_SESSION['user_name'],
                'role'      => currentRole(),
            ]);
        }

        apiError("Unknown auth action: {$action}.");
    })(),

    // ── GET  /api?resource=jobs           → list/search
    // ── GET  /api?resource=jobs&id=N      → single job
    // ── POST /api?resource=jobs           → create (employer)
    'jobs' => (function () use ($pdo, $method, $id, $body) {
        $model = new JobModel($pdo);

        if ($method === 'GET' && $id) {
            $job = $model->getJobDetail($id);
            if (!$job) apiError('Job not found.', 404);
            $model->incrementViews($id);
            apiSuccess($job);
        }

        if ($method === 'GET') {
            $result = $model->searchJobs(
                keyword:    trim($_GET['q']          ?? ''),
                location:   trim($_GET['location']   ?? ''),
                category:   trim($_GET['category']   ?? ''),
                jobType:    trim($_GET['job_type']    ?? ''),
                workType:   trim($_GET['work_type']   ?? ''),
                experience: trim($_GET['experience']  ?? ''),
                sort:       trim($_GET['sort']        ?? 'created_at DESC'),
                page:       max(1, (int)($_GET['page'] ?? 1)),
                perPage:    min(50, max(1, (int)($_GET['per_page'] ?? 12)))
            );
            apiSuccess($result);
        }

        if ($method === 'POST') {
            $userId = requireApiAuth();
            if (!isEmployer() && !isAdmin()) apiError('Only employers can post jobs.', 403);
            $service = new JobService($pdo);
            $result  = $service->postJob($userId, $body);
            if (!$result['success']) apiError($result['error']);
            apiSuccess(['job_id' => $result['job_id']], 'Job created.', 201);
        }

        if ($method === 'DELETE' && $id) {
            $userId = requireApiAuth();
            $job    = $model->findById($id);
            if (!$job) apiError('Job not found.', 404);
            if (!isAdmin() && (int)$job['employer_id'] !== $userId) apiError('Forbidden.', 403);
            $model->delete($id);
            apiSuccess(null, 'Job deleted.');
        }

        apiError("Method {$method} not supported on /jobs.");
    })(),

    // ── GET  /api?resource=applications           → own applications
    // ── POST /api?resource=applications           → apply (graduate)
    // ── PUT  /api?resource=applications&id=N      → update status (employer)
    // ── DELETE /api?resource=applications&id=N    → withdraw (graduate)
    'applications' => (function () use ($pdo, $method, $id, $body) {
        $userId  = requireApiAuth();
        $service = new ApplicationService($pdo);
        $model   = new ApplicationModel($pdo);

        if ($method === 'GET') {
            if (isGraduate()) {
                $result = $model->getGraduateApplications($userId, (int)($_GET['page']??1), 20);
                apiSuccess($result);
            }
            if (isEmployer()) {
                $result = $model->getEmployerApplications($userId, page:(int)($_GET['page']??1), perPage:20);
                apiSuccess($result);
            }
            if (isAdmin()) {
                $stmt = $pdo->query("SELECT * FROM applications ORDER BY applied_at DESC LIMIT 50");
                apiSuccess($stmt->fetchAll());
            }
        }

        if ($method === 'POST') {
            if (!isGraduate()) apiError('Only graduates can apply.', 403);
            $result = $service->apply($userId, (int)($body['job_id']??0), $body['cover_letter']??'');
            if (!$result['success']) apiError($result['error']);
            apiSuccess(null, 'Application submitted.', 201);
        }

        if ($method === 'PUT' && $id) {
            if (!isEmployer() && !isAdmin()) apiError('Only employers can update applications.', 403);
            $result = $service->updateStatus($id, $body['status']??'', $userId);
            if (!$result['success']) apiError($result['error']);
            apiSuccess(null, 'Status updated.');
        }

        if ($method === 'DELETE' && $id) {
            if (!isGraduate()) apiError('Only graduates can withdraw applications.', 403);
            $result = $service->withdraw($id, $userId);
            if (!$result['success']) apiError($result['error']);
            apiSuccess(null, 'Application withdrawn.');
        }

        apiError("Method {$method} not supported on /applications.");
    })(),

    // ── catch-all ─────────────────────────────────────────────────────────
    default => apiError("Unknown resource: '{$resource}'. Available: auth, jobs, applications.", 404),
};
