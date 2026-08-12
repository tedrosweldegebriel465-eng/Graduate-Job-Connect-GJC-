<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;

/**
 * BaseController
 *
 * Shared utilities for every controller:
 *   - PDO reference
 *   - Flash message helpers
 *   - Redirect helper
 *   - JSON response helper (for API endpoints)
 */
abstract class BaseController
{
    protected PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    // ─── Flash messages ───────────────────────────────────────────────────────

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Consume and return all queued flash messages, clearing the queue.
     */
    public static function getFlash(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }

    // ─── Redirect ─────────────────────────────────────────────────────────────

    protected function redirect(string $url): never
    {
        header("Location: {$url}");
        exit();
    }

    // ─── JSON ─────────────────────────────────────────────────────────────────

    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    protected function jsonError(string $message, int $status = 400): never
    {
        $this->json(['success' => false, 'error' => $message], $status);
    }

    protected function jsonSuccess(mixed $data = null, string $message = 'OK'): never
    {
        $this->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    // ─── Input sanitization ───────────────────────────────────────────────────

    protected function input(string $key, mixed $default = ''): string
    {
        $val = $_POST[$key] ?? $_GET[$key] ?? $default;
        return htmlspecialchars(trim((string) $val), ENT_QUOTES, 'UTF-8');
    }

    protected function intInput(string $key, int $default = 0): int
    {
        return (int) ($_POST[$key] ?? $_GET[$key] ?? $default);
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }
}
