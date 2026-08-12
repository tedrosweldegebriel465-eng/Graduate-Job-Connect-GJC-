<?php
/**
 * Graduate Job Connect — Notifications Page
 */

require_once __DIR__ . '/config/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Models\NotificationModel;
use App\Helpers\ViewHelper;

AuthMiddleware::require('any', 'login.php');

$pdo        = getDBConnection();
$notifModel = new NotificationModel($pdo);
$userId     = currentUserId();

// Mark all as read when visiting
$notifModel->markAllRead($userId);

$page    = max(1, (int) ($_GET['page'] ?? 1));
$result  = $notifModel->getForUser($userId, $page, 20);

$page_title = 'Notifications';
include 'includes/header.php';
?>

<div class="container" style="max-width:720px;padding:2rem 1rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
        <h1 style="margin:0;">🔔 Notifications</h1>
        <a href="javascript:history.back()" class="btn btn-outline btn-sm">← Back</a>
    </div>

    <?php if (empty($result['data'])): ?>
        <div class="card" style="text-align:center;padding:3rem 1rem;">
            <div style="font-size:3rem;margin-bottom:1rem;">🔔</div>
            <h3>No notifications yet</h3>
            <p style="color:var(--dark-gray);">We'll notify you about application updates and new opportunities.</p>
        </div>

    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:.75rem;">
            <?php foreach ($result['data'] as $n): ?>
            <div class="card" style="border-left:4px solid <?php echo $n['is_read'] ? 'var(--gray)' : 'var(--primary)'; ?>;
                                     opacity:<?php echo $n['is_read'] ? '.75' : '1'; ?>;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                    <div>
                        <div style="font-weight:600;margin-bottom:.25rem;">
                            <?php echo ViewHelper::e($n['title']); ?>
                        </div>
                        <p style="margin:0;color:var(--dark-gray);font-size:.9rem;">
                            <?php echo ViewHelper::e($n['message']); ?>
                        </p>
                        <?php if (!empty($n['link'])): ?>
                            <a href="<?php echo ViewHelper::e($n['link']); ?>"
                               style="font-size:.85rem;color:var(--primary);margin-top:.4rem;display:inline-block;">
                                View details →
                            </a>
                        <?php endif; ?>
                    </div>
                    <time style="font-size:.78rem;color:var(--dark-gray);white-space:nowrap;flex-shrink:0;"
                          datetime="<?php echo ViewHelper::e($n['created_at']); ?>">
                        <?php echo ViewHelper::timeAgo($n['created_at']); ?>
                    </time>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php echo ViewHelper::pagination($result, 'notifications.php'); ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
