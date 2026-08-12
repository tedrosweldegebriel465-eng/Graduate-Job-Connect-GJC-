<?php
/**
 * Graduate Job Connect — Run Migration 002
 *
 * Access: http://localhost/Graduate%20Job%20Connect/run_migration.php
 * This upgrades an existing job_portal database to v2 schema.
 *
 * IMPORTANT: Delete or rename this file after running it once.
 */

// Local-only access
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Access denied.');
}

require_once __DIR__ . '/config/bootstrap.php';

$migrationFile = __DIR__ . '/database/migrations/002_upgrade_v2.sql';
$confirmed     = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';

$results  = [];
$errors   = [];
$success  = false;

if ($confirmed) {
    try {
        $pdo = getDBConnection();
        $sql = file_get_contents($migrationFile);

        // Split on semicolons (crude but effective for this migration)
        // Handle DELIMITER changes for triggers
        $sql = preg_replace('/DELIMITER\s*\/\//i', '', $sql);
        $sql = preg_replace('/DELIMITER\s*;/i',    '', $sql);
        $sql = str_replace('//', ';', $sql);

        // Split into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => strlen($s) > 5 && !preg_match('/^--/', $s)
        );

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        foreach ($statements as $stmt) {
            if (empty(trim($stmt))) continue;
            try {
                $pdo->exec($stmt);
                // Only show meaningful results
                if (preg_match('/^(CREATE|ALTER|INSERT|UPDATE|DROP|RENAME)/i', trim($stmt))) {
                    $preview = substr(preg_replace('/\s+/', ' ', $stmt), 0, 80);
                    $results[] = ['ok' => true, 'sql' => $preview . '…'];
                }
            } catch (\PDOException $e) {
                // Ignore "already exists" and "duplicate" errors — these are safe
                $msg = $e->getMessage();
                if (preg_match('/(already exists|Duplicate|1050|1060|1061|1062)/i', $msg)) {
                    continue; // skip silently
                }
                $errors[] = substr(preg_replace('/\s+/', ' ', $stmt), 0, 80) . ' → ' . $msg;
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $success = empty($errors);

    } catch (\Throwable $e) {
        $errors[] = 'Fatal: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Run Migration — Graduate Job Connect</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; color: #1f2937;
               margin: 0; padding: 2rem; }
        h1   { color: #1a3c6e; }
        .card { background: #fff; border-radius: 10px; padding: 2rem;
                box-shadow: 0 2px 12px rgba(0,0,0,.08); max-width: 800px; }
        .warn { background: #fff3cd; border-left: 4px solid #f39c12; border-radius: 6px;
                padding: 1rem 1.25rem; color: #856404; margin-bottom: 1.5rem; }
        .ok   { background: #d4edda; border-left: 4px solid #28a745; border-radius: 6px;
                padding: 1rem 1.25rem; color: #155724; margin-bottom: 1rem; }
        .err  { background: #f8d7da; border-left: 4px solid #dc3545; border-radius: 6px;
                padding: 1rem 1.25rem; color: #721c24; margin-bottom: 1rem; }
        .log  { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px;
                padding: 1rem; max-height: 400px; overflow-y: auto; font-size: .82rem;
                font-family: monospace; line-height: 1.6; }
        .log-ok   { color: #155724; }
        .log-fail { color: #721c24; font-weight: 600; }
        btn, .btn { display: inline-block; padding: .6rem 1.5rem; background: #1a3c6e;
                    color: #fff; border: none; border-radius: 6px; cursor: pointer;
                    font-size: .95rem; text-decoration: none; margin-right: .5rem; }
        .btn-danger { background: #dc3545; }
        .btn:hover  { background: #2a5298; }
        .btn-danger:hover { background: #c82333; }
        code { background: #f1f3f5; padding: .1rem .4rem; border-radius: 3px; font-size: .85rem; }
    </style>
</head>
<body>
<h1>🔄 Database Migration — v2 Upgrade</h1>

<div class="card">

<?php if ($confirmed): ?>

    <?php if ($success): ?>
    <div class="ok">
        <strong>✅ Migration completed successfully!</strong><br>
        All tables created/updated. Your database is now on v2 schema.
    </div>
    <?php else: ?>
    <div class="err">
        <strong>⚠️ Migration finished with <?php echo count($errors); ?> error(s).</strong><br>
        Errors that say "already exists" are safe and can be ignored.
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <h4 style="color:#721c24;">Errors:</h4>
    <div class="log">
        <?php foreach ($errors as $e): ?>
        <div class="log-fail">❌ <?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($results)): ?>
    <h4 style="margin-top:1rem;">Statements executed (<?php echo count($results); ?>):</h4>
    <div class="log">
        <?php foreach ($results as $r): ?>
        <div class="<?php echo $r['ok'] ? 'log-ok' : 'log-fail'; ?>">
            <?php echo $r['ok'] ? '✅' : '❌'; ?>
            <?php echo htmlspecialchars($r['sql']); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="margin-top:1.5rem;padding:1rem;background:#fff3cd;border-radius:6px;font-size:.88rem;color:#856404;">
        <strong>⚠️ Delete this file now:</strong>
        Remove <code>run_migration.php</code> from your project root for security.
    </div>

    <div style="margin-top:1.25rem;">
        <a href="test_database.php" class="btn">🔧 Run Health Check</a>
        <a href="index.php" class="btn">🏠 Go to Homepage</a>
    </div>

<?php else: ?>

    <div class="warn">
        <strong>⚠️ Read before running:</strong>
        <ul style="margin:.5rem 0 0 1rem;padding:0;">
            <li>This will modify your <code>job_portal</code> database schema.</li>
            <li>It adds missing tables and columns. It does <strong>not</strong> delete existing data.</li>
            <li>Run this <strong>once</strong>, then delete this file.</li>
            <li>Make sure XAMPP MySQL is running.</li>
        </ul>
    </div>

    <h3>Migration: <code>002_upgrade_v2.sql</code></h3>
    <p>This migration will:</p>
    <ul style="line-height:1.8;">
        <li>✅ Create <code>employer_profiles</code> table</li>
        <li>✅ Create <code>graduate_profiles</code> table (with correct <code>user_id</code> column)</li>
        <li>✅ Create <code>saved_jobs</code>, <code>notifications</code>, <code>activity_logs</code>, <code>password_resets</code></li>
        <li>✅ Add missing columns to <code>jobs</code> (salary_min/max, work_type, is_featured, etc.)</li>
        <li>✅ Add missing columns to <code>applications</code> (cover_letter, applied_at, etc.)</li>
        <li>✅ Rename <code>deadline</code> → <code>application_deadline</code> if needed</li>
        <li>✅ Migrate data from old <code>companies</code> table if it exists</li>
        <li>✅ Seed demo accounts with fresh deadlines</li>
        <li>✅ Add triggers for application count tracking</li>
    </ul>

    <form method="POST" style="margin-top:1.5rem;">
        <input type="hidden" name="confirm" value="yes">
        <button type="submit" class="btn btn-danger">🚀 Run Migration Now</button>
        <a href="index.php" class="btn" style="background:#6c757d;">Cancel</a>
    </form>

<?php endif; ?>

</div>
</body>
</html>
