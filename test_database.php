<?php
/**
 * Graduate Job Connect — Database Health Check
 * Access: http://localhost/Graduate%20Job%20Connect/test_database.php
 * Restrict or remove before deploying to production.
 */

require_once __DIR__ . '/config/bootstrap.php';

// Local-only access guard
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Access denied. Health check is available on localhost only.');
}

$pdo     = getDBConnection();
$passed  = 0;
$failed  = 0;
$results = [];

/**
 * Run a labeled check and record pass/fail.
 */
function runCheck(string $label, callable $fn, array &$results, int &$passed, int &$failed): void
{
    try {
        $detail    = $fn();
        $results[] = ['ok' => true,  'label' => $label, 'detail' => $detail ?? 'OK'];
        $passed++;
    } catch (\Throwable $e) {
        $results[] = ['ok' => false, 'label' => $label, 'detail' => $e->getMessage()];
        $failed++;
    }
}

// ─── Checks ───────────────────────────────────────────────────────────────────

runCheck('DB Connection', function () use ($pdo) {
    $pdo->query('SELECT 1');
    return 'MySQL ' . $pdo->query('SELECT VERSION()')->fetchColumn();
}, $results, $passed, $failed);

foreach (['users','graduate_profiles','employer_profiles','jobs','applications',
          'saved_jobs','notifications','activity_logs','password_resets'] as $tbl) {
    runCheck("Table: {$tbl}", function () use ($pdo, $tbl) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$tbl}`");
        $stmt->execute();
        return number_format((int) $stmt->fetchColumn()) . ' rows';
    }, $results, $passed, $failed);
}

runCheck('Admin user exists', function () use ($pdo) {
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE role='admin' LIMIT 1");
    $stmt->execute();
    $r = $stmt->fetch();
    if (!$r) throw new \RuntimeException('No admin found — run the seeder.');
    return "{$r['name']} <{$r['email']}>";
}, $results, $passed, $failed);

runCheck('Active jobs', function () use ($pdo) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM jobs WHERE status='active'")->fetchColumn();
    return number_format($n) . ' active job(s)';
}, $results, $passed, $failed);

runCheck('Password hashing (bcrypt)', function () {
    $hash = password_hash('test', PASSWORD_BCRYPT, ['cost' => 4]);
    if (!password_verify('test', $hash)) throw new \RuntimeException('Verify failed.');
    return 'bcrypt cost=' . (defined('BCRYPT_COST') ? BCRYPT_COST : 12);
}, $results, $passed, $failed);

runCheck('Session active', function () {
    if (session_status() !== PHP_SESSION_ACTIVE) throw new \RuntimeException('Session not started.');
    return 'name=' . session_name();
}, $results, $passed, $failed);

runCheck('Upload directories', function () {
    $base    = __DIR__ . '/uploads/';
    $missing = array_filter(['cv','logos','avatars'], fn($d) => !is_dir($base . $d));
    if ($missing) throw new \RuntimeException('Missing: ' . implode(', ', $missing));
    return 'cv, logos, avatars present';
}, $results, $passed, $failed);

runCheck('Environment constants', function () {
    $need    = ['DB_HOST','DB_NAME','DB_USER','SESSION_NAME','BCRYPT_COST','UPLOAD_MAX_SIZE','APP_VERSION'];
    $missing = array_filter($need, fn($c) => !defined($c));
    if ($missing) throw new \RuntimeException('Missing: ' . implode(', ', $missing));
    return count($need) . ' constants defined';
}, $results, $passed, $failed);

runCheck('Autoloader: UserModel', function () {
    $m = new \App\Models\UserModel(getDBConnection());
    return get_class($m) . ' loaded';
}, $results, $passed, $failed);

runCheck('Autoloader: AuthService', function () {
    $s = new \App\Services\AuthService(getDBConnection());
    return get_class($s) . ' loaded';
}, $results, $passed, $failed);

runCheck('Autoloader: ViewHelper', function () {
    $v = \App\Helpers\ViewHelper::e('<b>test</b>');
    if ($v !== '&lt;b&gt;test&lt;/b&gt;') throw new \RuntimeException('XSS escape broken.');
    return 'XSS escape working';
}, $results, $passed, $failed);

// ─── Render ───────────────────────────────────────────────────────────────────
$total  = $passed + $failed;
$allOk  = $failed === 0;
$colour = $allOk ? '#155724' : '#721c24';
$bg     = $allOk ? '#d4edda' : '#f8d7da';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Check — Graduate Job Connect</title>
    <style>
        * { box-sizing: border-box; }
        body  { font-family: 'Segoe UI', sans-serif; background: #f5f7fa;
                color: #1f2937; margin: 0; padding: 2rem; }
        h1    { color: #1a3c6e; margin-bottom: .5rem; }
        .sub  { color: #6b7280; font-size: .9rem; margin-bottom: 1.5rem; }
        .banner { padding: 1rem 1.5rem; border-radius: 8px;
                  background: <?php echo $bg; ?>; color: <?php echo $colour; ?>;
                  font-weight: 700; font-size: 1.05rem; margin-bottom: 1.5rem;
                  border-left: 5px solid <?php echo $colour; ?>; }
        table  { width: 100%; border-collapse: collapse; background: #fff;
                 border-radius: 10px; overflow: hidden;
                 box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        th     { background: #1a3c6e; color: #fff; padding: .7rem 1rem;
                 text-align: left; font-size: .83rem; text-transform: uppercase;
                 letter-spacing: .4px; }
        td     { padding: .6rem 1rem; border-bottom: 1px solid #f0f0f0; font-size: .9rem; }
        tr:last-child td { border-bottom: none; }
        tr:hover td      { background: #f9fafb; }
        .ok   { color: #155724; font-weight: 700; }
        .fail { color: #721c24; font-weight: 700; }
        .detail { color: #4b5563; }
        a.btn { display: inline-block; margin-top: 1.5rem; padding: .55rem 1.25rem;
                background: #1a3c6e; color: #fff; border-radius: 6px;
                text-decoration: none; font-size: .9rem; }
        a.btn:hover { background: #2a5298; }
        .warn { margin-top: 1.5rem; padding: .75rem 1rem; background: #fff3cd;
                color: #856404; border-radius: 6px; font-size: .85rem;
                border-left: 4px solid #f39c12; }
    </style>
</head>
<body>

<h1>🔧 Database Health Check</h1>
<p class="sub">Graduate Job Connect v<?php echo defined('APP_VERSION') ? APP_VERSION : '2.0.0'; ?> &mdash;
    <?php echo date('D, M j Y H:i:s'); ?></p>

<div class="banner">
    <?php echo $allOk
        ? "✅ All {$total} checks passed — system is healthy."
        : "⚠️ {$failed} of {$total} checks failed."; ?>
</div>

<table>
    <thead>
        <tr>
            <th style="width:36px;"></th>
            <th>Check</th>
            <th>Result</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($results as $r): ?>
        <tr>
            <td style="font-size:1rem;text-align:center;"><?php echo $r['ok'] ? '✅' : '❌'; ?></td>
            <td class="<?php echo $r['ok'] ? 'ok' : 'fail'; ?>">
                <?php echo htmlspecialchars($r['label']); ?>
            </td>
            <td class="detail"><?php echo htmlspecialchars($r['detail']); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="warn">
    ⚠️ <strong>Production reminder:</strong> Restrict or remove this file before deploying.
    Add IP restriction or delete <code>test_database.php</code>.
</div>

<a href="index.php" class="btn">← Back to Homepage</a>

</body>
</html>
