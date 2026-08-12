<?php

declare(strict_types=1);

namespace App\Services;

/**
 * FileUploadService
 *
 * Centralised, secure file upload handling.
 * Validates MIME type (via finfo), size, and extension before moving.
 */
class FileUploadService
{
    private int    $maxSize;
    private string $uploadBase;

    public function __construct()
    {
        $this->maxSize    = defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5 * 1024 * 1024;
        $this->uploadBase = dirname(__DIR__, 2) . '/uploads/';
    }

    // ─── CV Upload ────────────────────────────────────────────────────────────

    /**
     * @return array{ success: bool, filename?: string, error?: string }
     */
    public function uploadCV(array $file, int $userId): array
    {
        return $this->upload($file, 'cv', $userId, ['application/pdf'], 'pdf');
    }

    // ─── Logo Upload ──────────────────────────────────────────────────────────

    public function uploadLogo(array $file, int $userId): array
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        return $this->upload($file, 'logos', $userId, $allowed, null);
    }

    // ─── Avatar Upload ────────────────────────────────────────────────────────

    public function uploadAvatar(array $file, int $userId): array
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        return $this->upload($file, 'avatars', $userId, $allowed, null);
    }

    // ─── Core ─────────────────────────────────────────────────────────────────

    private function upload(
        array  $file,
        string $subDir,
        int    $userId,
        array  $allowedMimes,
        ?string $forceExt
    ): array {
        // PHP upload error check
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => $this->phpUploadError($file['error'])];
        }

        // Size
        if ($file['size'] > $this->maxSize) {
            $mb = round($this->maxSize / 1048576, 1);
            return ['success' => false, 'error' => "File too large. Maximum size is {$mb} MB."];
        }

        // MIME via finfo (not trusting $_FILES['type'])
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimes, true)) {
            $allowed = implode(', ', $allowedMimes);
            return ['success' => false, 'error' => "Invalid file type. Allowed: {$allowed}."];
        }

        // Destination directory
        $dir = $this->uploadBase . $subDir . '/';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return ['success' => false, 'error' => 'Upload directory could not be created.'];
        }

        // Safe filename
        $ext      = $forceExt ?? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $ext      = preg_replace('/[^a-z0-9]/', '', $ext);
        $filename = "{$subDir}_{$userId}_" . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = $dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['success' => false, 'error' => 'Failed to save uploaded file.'];
        }

        return ['success' => true, 'filename' => $filename];
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function delete(string $filename, string $subDir): bool
    {
        if (empty($filename)) return true;
        $path = $this->uploadBase . $subDir . '/' . $filename;
        if (file_exists($path)) {
            return unlink($path);
        }
        return true;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function phpUploadError(int $code): string
    {
        return match($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum allowed size.',
            UPLOAD_ERR_PARTIAL  => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE  => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            default             => 'Unknown upload error.',
        };
    }
}
