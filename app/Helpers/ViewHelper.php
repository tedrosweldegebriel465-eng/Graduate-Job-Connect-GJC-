<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * ViewHelper
 *
 * Pure static helpers used inside view/template files.
 * No business logic — only formatting and rendering utilities.
 */
class ViewHelper
{
    // ─── Output escaping ──────────────────────────────────────────────────────

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // ─── Dates ────────────────────────────────────────────────────────────────

    public static function formatDate(?string $datetime, string $format = 'M d, Y'): string
    {
        if (empty($datetime)) return 'N/A';
        try {
            return (new \DateTime($datetime))->format($format);
        } catch (\Exception) {
            return 'N/A';
        }
    }

    public static function timeAgo(?string $datetime): string
    {
        if (empty($datetime)) return 'N/A';
        try {
            $diff = (new \DateTime())->diff(new \DateTime($datetime));
            if ($diff->y) return $diff->y . ' year'   . ($diff->y > 1 ? 's' : '') . ' ago';
            if ($diff->m) return $diff->m . ' month'  . ($diff->m > 1 ? 's' : '') . ' ago';
            if ($diff->d) return $diff->d . ' day'    . ($diff->d > 1 ? 's' : '') . ' ago';
            if ($diff->h) return $diff->h . ' hour'   . ($diff->h > 1 ? 's' : '') . ' ago';
            if ($diff->i) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
            return 'just now';
        } catch (\Exception) {
            return 'N/A';
        }
    }

    public static function daysUntil(?string $date): int
    {
        if (empty($date)) return 0;
        try {
            $diff = (new \DateTime())->diff(new \DateTime($date));
            return (int) $diff->format('%r%a');
        } catch (\Exception) {
            return 0;
        }
    }

    // ─── Salary ───────────────────────────────────────────────────────────────

    public static function formatSalary(
        mixed  $min,
        mixed  $max,
        string $currency = 'ETB'
    ): string {
        $min = $min !== null && $min !== '' ? (float) $min : null;
        $max = $max !== null && $max !== '' ? (float) $max : null;

        if ($min === null && $max === null) return 'Not specified';

        $fmt = fn($n) => $currency . ' ' . number_format($n, 0);

        if ($min === null) return 'Up to ' . $fmt($max);
        if ($max === null) return 'From '  . $fmt($min);
        return $fmt($min) . ' – ' . $fmt($max);
    }

    // ─── Text ─────────────────────────────────────────────────────────────────

    public static function truncate(string $text, int $length = 120, string $suffix = '…'): string
    {
        $clean = strip_tags($text);
        if (mb_strlen($clean) <= $length) return $clean;
        return mb_substr($clean, 0, $length) . $suffix;
    }

    public static function initials(string $name): string
    {
        $words    = array_filter(explode(' ', trim($name)));
        $initials = array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)), $words);
        return mb_substr(implode('', $initials), 0, 2);
    }

    // ─── Badges ───────────────────────────────────────────────────────────────

    public static function jobStatusBadge(string $status): string
    {
        $map = [
            'active'  => 'badge-active',
            'closed'  => 'badge-closed',
            'draft'   => 'badge-draft',
            'expired' => 'badge-expired',
        ];
        $class = $map[$status] ?? 'badge-secondary';
        return '<span class="badge ' . $class . '">' . ucfirst(self::e($status)) . '</span>';
    }

    public static function appStatusBadge(string $status): string
    {
        $map = [
            'pending'     => 'badge-pending',
            'shortlisted' => 'badge-shortlisted',
            'accepted'    => 'badge-accepted',
            'rejected'    => 'badge-rejected',
            'withdrawn'   => 'badge-withdrawn',
        ];
        $icons = [
            'pending'     => '⏳',
            'shortlisted' => '⭐',
            'accepted'    => '✅',
            'rejected'    => '❌',
            'withdrawn'   => '🚫',
        ];
        $class = $map[$status]  ?? 'badge-secondary';
        $icon  = $icons[$status] ?? '';
        return '<span class="badge ' . $class . '">' . $icon . ' ' . ucfirst(self::e($status)) . '</span>';
    }

    public static function roleBadge(string $role): string
    {
        $icons = ['admin' => '👑', 'employer' => '🏢', 'graduate' => '🎓'];
        $icon  = $icons[$role] ?? '👤';
        return '<span class="badge badge-' . self::e($role) . '">' . $icon . ' ' . ucfirst(self::e($role)) . '</span>';
    }

    // ─── Pagination ───────────────────────────────────────────────────────────

    /**
     * Render Bootstrap-compatible pagination links.
     *
     * @param array  $pagination   Result of BaseModel::paginate()
     * @param string $baseUrl      URL to append ?page=N to (must already contain other params)
     */
    public static function pagination(array $pagination, string $baseUrl): string
    {
        ['page' => $page, 'totalPages' => $total] = $pagination;
        if ($total <= 1) return '';

        $sep = str_contains($baseUrl, '?') ? '&' : '?';
        $html = '<nav class="pagination-nav" aria-label="Page navigation"><ul class="pagination">';

        // Prev
        if ($page > 1) {
            $html .= '<li><a href="' . $baseUrl . $sep . 'page=' . ($page - 1) . '" aria-label="Previous">&laquo;</a></li>';
        }

        $start = max(1, $page - 2);
        $end   = min($total, $page + 2);

        if ($start > 1) {
            $html .= '<li><a href="' . $baseUrl . $sep . 'page=1">1</a></li>';
            if ($start > 2) $html .= '<li class="dots"><span>…</span></li>';
        }

        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $page ? ' class="active"' : '';
            $html  .= '<li' . $active . '><a href="' . $baseUrl . $sep . 'page=' . $i . '">' . $i . '</a></li>';
        }

        if ($end < $total) {
            if ($end < $total - 1) $html .= '<li class="dots"><span>…</span></li>';
            $html .= '<li><a href="' . $baseUrl . $sep . 'page=' . $total . '">' . $total . '</a></li>';
        }

        // Next
        if ($page < $total) {
            $html .= '<li><a href="' . $baseUrl . $sep . 'page=' . ($page + 1) . '" aria-label="Next">&raquo;</a></li>';
        }

        $html .= '</ul></nav>';
        return $html;
    }

    // ─── Skills tags ─────────────────────────────────────────────────────────

    public static function skillTags(?string $skillsStr): string
    {
        if (empty($skillsStr)) return '';
        $skills = array_filter(array_map('trim', explode(',', $skillsStr)));
        $tags   = array_map(fn($s) => '<span class="skill-tag">' . self::e($s) . '</span>', $skills);
        return '<div class="skills-tags">' . implode('', $tags) . '</div>';
    }

    // ─── Profile completion bar ───────────────────────────────────────────────

    public static function completionBar(int $pct): string
    {
        $color = $pct >= 80 ? 'var(--success)' : ($pct >= 50 ? 'var(--warning)' : 'var(--danger)');
        return <<<HTML
        <div class="progress-track" title="{$pct}% complete" style="background:var(--light-gray);border-radius:4px;height:8px;overflow:hidden;">
            <div style="width:{$pct}%;height:100%;background:{$color};border-radius:4px;transition:width 0.5s ease;"></div>
        </div>
        HTML;
    }
}
