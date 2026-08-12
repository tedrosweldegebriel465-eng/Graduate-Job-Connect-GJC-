<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * NotificationModel — notifications table
 * All methods are safe if the table doesn't exist yet.
 */
class NotificationModel extends BaseModel
{
    protected string $table = 'notifications';

    private function tableReady(): bool
    {
        try {
            $this->db->query("SELECT 1 FROM notifications LIMIT 0");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function countUnread(int $userId): int
    {
        if (!$this->tableReady()) return 0;
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0"
            );
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function getUnread(int $userId, int $limit = 10): array
    {
        if (!$this->tableReady()) return [];
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0
                 ORDER BY created_at DESC LIMIT ?"
            );
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $empty = ['data' => [], 'total' => 0, 'totalPages' => 1, 'page' => 1, 'perPage' => $perPage];
        if (!$this->tableReady()) return $empty;
        try {
            return $this->paginate(
                "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC",
                [$userId], $page, $perPage
            );
        } catch (\Throwable) {
            return $empty;
        }
    }

    public function markAllRead(int $userId): void
    {
        if (!$this->tableReady()) return;
        try {
            $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")
                ->execute([$userId]);
        } catch (\Throwable) {}
    }

    public function markRead(int $notificationId, int $userId): void
    {
        if (!$this->tableReady()) return;
        try {
            $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
                ->execute([$notificationId, $userId]);
        } catch (\Throwable) {}
    }

    public function push(int $userId, string $type, string $title, string $message, string $link = ''): int|false
    {
        if (!$this->tableReady()) return false;
        try {
            return $this->create([
                'user_id' => $userId,
                'type'    => $type,
                'title'   => $title,
                'message' => $message,
                'link'    => $link,
                'is_read' => 0,
            ]);
        } catch (\Throwable) {
            return false;
        }
    }
}
