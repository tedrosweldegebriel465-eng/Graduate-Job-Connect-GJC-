<?php
/**
 * Graduate Job Connect — includes/functions.php
 *
 * COMPATIBILITY SHIM — every function here is guarded with function_exists()
 * so this file is safe to include AFTER bootstrap.php has already run.
 *
 * bootstrap.php defines the auth helpers (isLoggedIn, isAdmin, etc.) and
 * getDBConnection(). This file adds legacy helper functions that old pages
 * still call directly. New code should use the App\ namespace classes instead.
 */

// ─── Bootstrap if not already loaded ────────────────────────────────────────
if (!function_exists('getDBConnection')) {
    require_once dirname(__DIR__) . '/config/bootstrap.php';
}

// ─── Session / Auth (guarded — bootstrap.php defines these) ──────────────────

if (!function_exists('startSession')) {
    function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0, 'path' => '/',
                'secure' => false, 'httponly' => true, 'samesite' => 'Lax'
            ]);
            session_start();
        }
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() { return !empty($_SESSION['user_id']); }
}
if (!function_exists('hasRole')) {
    function hasRole($role) { return ($_SESSION['user_role'] ?? '') === $role; }
}
if (!function_exists('isAdmin'))    { function isAdmin()    { return hasRole('admin'); } }
if (!function_exists('isEmployer')) { function isEmployer() { return hasRole('employer'); } }
if (!function_exists('isGraduate')) { function isGraduate() { return hasRole('graduate'); } }
if (!function_exists('currentUserId')) {
    function currentUserId() { return (int)($_SESSION['user_id'] ?? 0); }
}
if (!function_exists('currentRole')) {
    function currentRole() { return $_SESSION['user_role'] ?? ''; }
}

if (!function_exists('requireRole')) {
    function requireRole($role) {
        if (function_exists('startSession')) startSession();
        if (!isLoggedIn()) {
            header('Location: ' . str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) . 'login.php');
            exit();
        }
        if ($role !== 'any' && !hasRole($role) && !isAdmin()) {
            header('Location: ' . str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) . 'login.php');
            exit();
        }
    }
}

if (!function_exists('redirectToDashboard')) {
    function redirectToDashboard() {
        $role = $_SESSION['user_role'] ?? '';
        $base = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1);
        header("Location: {$base}{$role}/dashboard.php");
        exit();
    }
}

// ─── Security helpers ─────────────────────────────────────────────────────────

if (!function_exists('hashPassword')) {
    function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash) { return password_verify($password, $hash); }
}
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) { return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
if (!function_exists('isRateLimited')) {
    function isRateLimited($key, $limit = 5, $window = 300) {
        $timeKey = 'rl_time_' . $key; $countKey = 'rl_count_' . $key;
        $now = time();
        if (!isset($_SESSION[$timeKey]) || ($now - $_SESSION[$timeKey]) > $window) {
            $_SESSION[$timeKey] = $now; $_SESSION[$countKey] = 1; return false;
        }
        $_SESSION[$countKey]++;
        return $_SESSION[$countKey] > $limit;
    }
}

// ─── Validation ───────────────────────────────────────────────────────────────

if (!function_exists('isValidEmail')) {
    function isValidEmail($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
}
if (!function_exists('isValidPhone')) {
    function isValidPhone($phone) {
        return preg_match('/^(\+?251|0)[1-9]\d{8}$/', preg_replace('/[\s\-\(\)]/', '', $phone));
    }
}
if (!function_exists('validatePasswordStrength')) {
    function validatePasswordStrength($password) {
        if (strlen($password) < 8)         return ['valid'=>false,'message'=>'At least 8 characters.'];
        if (!preg_match('/[A-Z]/',$password)) return ['valid'=>false,'message'=>'Needs uppercase letter.'];
        if (!preg_match('/[a-z]/',$password)) return ['valid'=>false,'message'=>'Needs lowercase letter.'];
        if (!preg_match('/[0-9]/',$password)) return ['valid'=>false,'message'=>'Needs a number.'];
        if (!preg_match('/[^A-Za-z0-9]/',$password)) return ['valid'=>false,'message'=>'Needs special character.'];
        return ['valid'=>true,'message'=>'Strong password.'];
    }
}
if (!function_exists('isEthiopianUniversityEmail')) {
    function isEthiopianUniversityEmail($email) {
        $domains = ['aau.edu.et','bdu.edu.et','haramaya.edu.et','ju.edu.et','mu.edu.et',
                    'dbu.edu.et','dmu.edu.et','ddu.edu.et','gu.edu.et','hwu.edu.et',
                    'wgu.edu.et','wu.edu.et','adama.edu.et','smuc.edu.et','unity.edu.et'];
        $domain = strtolower(substr(strrchr($email,'@'),1));
        return in_array($domain, $domains);
    }
}
if (!function_exists('isValidUrl')) {
    function isValidUrl($url) { return filter_var($url, FILTER_VALIDATE_URL) !== false; }
}

// ─── DB helpers ───────────────────────────────────────────────────────────────

if (!function_exists('getUserById')) {
    function getUserById($pdo, $user_id) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
if (!function_exists('getUserProfile')) {
    function getUserProfile($pdo, $user_id, $role) {
        $table = $role === 'graduate' ? 'graduate_profiles' : 'employer_profiles';
        $stmt  = $pdo->prepare("SELECT * FROM {$table} WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
if (!function_exists('logActivity')) {
    function logActivity($pdo, $user_id, $action, $details = '') {
        try {
            $pdo->prepare("INSERT INTO activity_logs (user_id,action,details,ip_address) VALUES (?,?,?,?)")
                ->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (Exception $e) { error_log("logActivity: ".$e->getMessage()); }
    }
}
if (!function_exists('createNotification')) {
    function createNotification($pdo, $user_id, $type, $message, $title = '', $link = '') {
        try {
            if (empty($title)) $title = ucfirst(str_replace('_',' ',$type));
            $pdo->prepare("INSERT INTO notifications (user_id,type,title,message,link) VALUES (?,?,?,?,?)")
                ->execute([$user_id, $type, $title, $message, $link]);
        } catch (Exception $e) { error_log("createNotification: ".$e->getMessage()); }
    }
}

// ─── File upload ──────────────────────────────────────────────────────────────

if (!function_exists('uploadCV')) {
    function uploadCV($file, $user_id) {
        if ($file['size'] > 5*1024*1024) return false;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']); finfo_close($finfo);
        if ($mime !== 'application/pdf') return false;
        $dir = dirname(__DIR__) . '/uploads/cv/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = 'cv_'.$user_id.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.pdf';
        return move_uploaded_file($file['tmp_name'], $dir.$filename) ? $filename : false;
    }
}

// ─── Formatting ───────────────────────────────────────────────────────────────

if (!function_exists('formatDate')) {
    function formatDate($datetime, $format = 'M d, Y') {
        if (empty($datetime)) return 'N/A';
        try { return (new DateTime($datetime))->format($format); }
        catch (Exception $e) { return 'N/A'; }
    }
}
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        if (empty($datetime)) return 'N/A';
        try {
            $diff = (new DateTime())->diff(new DateTime($datetime));
            if ($diff->y) return $diff->y.' year'.($diff->y>1?'s':'').' ago';
            if ($diff->m) return $diff->m.' month'.($diff->m>1?'s':'').' ago';
            if ($diff->d) return $diff->d.' day'.($diff->d>1?'s':'').' ago';
            if ($diff->h) return $diff->h.' hour'.($diff->h>1?'s':'').' ago';
            if ($diff->i) return $diff->i.' minute'.($diff->i>1?'s':'').' ago';
            return 'just now';
        } catch (Exception $e) { return 'N/A'; }
    }
}
if (!function_exists('truncateText')) {
    function truncateText($text, $length = 100, $suffix = '...') {
        if (mb_strlen($text) <= $length) return $text;
        return mb_substr($text, 0, $length) . $suffix;
    }
}
if (!function_exists('daysUntil')) {
    function daysUntil($date) {
        if (!$date) return 0;
        try { return (int)(new DateTime())->diff(new DateTime($date))->format('%r%a'); }
        catch (Exception $e) { return 0; }
    }
}
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount, $currency = 'ETB') {
        if ($amount === null || $amount === '') return 'N/A';
        return $currency . ' ' . number_format((float)$amount, 2);
    }
}
if (!function_exists('formatSalaryRange')) {
    function formatSalaryRange($min, $max, $currency = 'ETB') {
        if (empty($min) && empty($max)) return 'Not specified';
        if (empty($min)) return 'Up to ' . formatCurrency($max, $currency);
        if (empty($max)) return 'From '  . formatCurrency($min, $currency);
        return formatCurrency($min, $currency) . ' - ' . formatCurrency($max, $currency);
    }
}

// ─── Lookup lists ─────────────────────────────────────────────────────────────

if (!function_exists('getJobTypes')) {
    function getJobTypes() {
        return ['full-time'=>'Full Time','part-time'=>'Part Time',
                'internship'=>'Internship','contract'=>'Contract','freelance'=>'Freelance'];
    }
}
if (!function_exists('getWorkTypes')) {
    function getWorkTypes() { return ['onsite'=>'On-site','remote'=>'Remote','hybrid'=>'Hybrid']; }
}
if (!function_exists('getExperienceLevels')) {
    function getExperienceLevels() {
        return ['entry'=>'Entry Level (0-2 years)','mid'=>'Mid Level (3-5 years)',
                'senior'=>'Senior Level (5+ years)','lead'=>'Lead/Managerial','internship'=>'Internship'];
    }
}
if (!function_exists('getCompanySizes')) {
    function getCompanySizes() {
        return ['1-10'=>'1-10 employees','11-50'=>'11-50 employees','51-200'=>'51-200 employees',
                '201-500'=>'201-500 employees','501-1000'=>'501-1000 employees','1000+'=>'1000+ employees'];
    }
}
if (!function_exists('getIndustries')) {
    function getIndustries() {
        return ['Agriculture','Banking & Finance','Construction','Education','Energy & Mining',
                'Engineering','Healthcare','Hospitality & Tourism','Information Technology',
                'Manufacturing','Media & Communications','NGO & Non-Profit','Real Estate',
                'Retail & Consumer Goods','Telecommunications','Transportation & Logistics','Other'];
    }
}

// ─── Badge helpers ────────────────────────────────────────────────────────────

if (!function_exists('getJobStatusBadgeClass')) {
    function getJobStatusBadgeClass($status) {
        $c = ['active'=>'badge-active','closed'=>'badge-closed','draft'=>'badge-draft','expired'=>'badge-expired'];
        return $c[$status] ?? 'badge-secondary';
    }
}
if (!function_exists('getApplicationStatusBadgeClass')) {
    function getApplicationStatusBadgeClass($status) {
        $c = ['pending'=>'badge-pending','shortlisted'=>'badge-shortlisted',
              'accepted'=>'badge-accepted','rejected'=>'badge-rejected','withdrawn'=>'badge-withdrawn'];
        return $c[$status] ?? 'badge-secondary';
    }
}
if (!function_exists('getRoleBadgeClass')) {
    function getRoleBadgeClass($role) {
        $c = ['admin'=>'badge-admin','employer'=>'badge-employer','graduate'=>'badge-graduate'];
        return $c[$role] ?? 'badge-secondary';
    }
}
if (!function_exists('getRoleIcon')) {
    function getRoleIcon($role) {
        $i = ['admin'=>'👑','employer'=>'🏢','graduate'=>'🎓'];
        return $i[$role] ?? '👤';
    }
}

// ─── Misc helpers ─────────────────────────────────────────────────────────────

if (!function_exists('getUserInitials')) {
    function getUserInitials($name) {
        $w = explode(' ', trim($name)); $i = '';
        foreach ($w as $word) if (!empty($word)) $i .= strtoupper($word[0]);
        return substr($i, 0, 2);
    }
}
if (!function_exists('getUserDisplayName')) {
    function getUserDisplayName($user) {
        if (empty($user)) return 'Guest';
        return $user['name'] ?? $user['email'] ?? 'User';
    }
}
if (!function_exists('hasPermission')) {
    function hasPermission($requiredRole) {
        if ($requiredRole === 'admin')    return isAdmin();
        if ($requiredRole === 'employer') return isEmployer() || isAdmin();
        if ($requiredRole === 'graduate') return isGraduate() || isAdmin();
        return isLoggedIn();
    }
}
if (!function_exists('createSlug')) {
    function createSlug($string) {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($string)), '-');
    }
}
if (!function_exists('getFileExtension')) {
    function getFileExtension($filename) { return strtolower(pathinfo($filename, PATHINFO_EXTENSION)); }
}
if (!function_exists('createDirectory')) {
    function createDirectory($path, $permissions = 0755) {
        if (!is_dir($path)) return mkdir($path, $permissions, true); return true;
    }
}
if (!function_exists('humanFileSize')) {
    function humanFileSize($bytes, $decimals = 2) {
        $size = ['B','KB','MB','GB','TB'];
        $f    = floor((strlen($bytes)-1)/3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024,$f)) . ' ' . $size[$f];
    }
}
if (!function_exists('randomString')) {
    function randomString($length = 10) {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $r = '';
        for ($i = 0; $i < $length; $i++) $r .= $chars[random_int(0, strlen($chars)-1)];
        return $r;
    }
}
if (!function_exists('getUploadPath')) {
    function getUploadPath($type = '') {
        $base = dirname(__DIR__) . '/uploads/';
        return $type ? $base . $type . '/' : $base;
    }
}
if (!function_exists('deleteUploadedFile')) {
    function deleteUploadedFile($filename, $type = '') {
        if (empty($filename)) return true;
        $path = getUploadPath($type) . $filename;
        if (file_exists($path)) return unlink($path);
        return true;
    }
}
if (!function_exists('getProfileCompletion')) {
    function getProfileCompletion($pdo, $user_id, $role) {
        $user = getUserById($pdo, $user_id);
        if (!$user) return 0;
        $fields = ['name'=>!empty($user['name']),'email'=>!empty($user['email']),'phone'=>!empty($user['phone'])];
        if ($role === 'graduate') {
            $p = getUserProfile($pdo,$user_id,'graduate');
            $fields['university']=$fields['graduation_year']=$fields['skills']=$fields['cv']=false;
            if ($p) { $fields['university']=!empty($p['university']); $fields['graduation_year']=!empty($p['graduation_year']); $fields['skills']=!empty($p['skills']); $fields['cv']=!empty($p['cv_filename']); }
        } elseif ($role === 'employer') {
            $p = getUserProfile($pdo,$user_id,'employer');
            $fields['company_name']=$fields['description']=$fields['location']=$fields['industry']=false;
            if ($p) { $fields['company_name']=!empty($p['company_name']); $fields['description']=!empty($p['company_description']); $fields['location']=!empty($p['company_location']); $fields['industry']=!empty($p['industry']); }
        }
        $total = count($fields); $completed = array_sum($fields);
        return $total > 0 ? round(($completed/$total)*100) : 0;
    }
}
if (!function_exists('generatePagination')) {
    function generatePagination($currentPage, $totalPages, $baseUrl = '?') {
        if ($totalPages <= 1) return '';
        $html = '<nav class="pagination-nav"><ul class="pagination">';
        if ($currentPage > 1) $html .= '<li><a href="'.$baseUrl.'page='.($currentPage-1).'">&laquo;</a></li>';
        $s = max(1,$currentPage-2); $e = min($totalPages,$currentPage+2);
        if ($s>1) { $html .= '<li><a href="'.$baseUrl.'page=1">1</a></li>'; if ($s>2) $html.='<li><span>…</span></li>'; }
        for ($i=$s;$i<=$e;$i++) { $a=($i==$currentPage)?' class="active"':''; $html.='<li'.$a.'><a href="'.$baseUrl.'page='.$i.'">'.$i.'</a></li>'; }
        if ($e<$totalPages) { if ($e<$totalPages-1) $html.='<li><span>…</span></li>'; $html.='<li><a href="'.$baseUrl.'page='.$totalPages.'">'.$totalPages.'</a></li>'; }
        if ($currentPage<$totalPages) $html.='<li><a href="'.$baseUrl.'page='.($currentPage+1).'">&raquo;</a></li>';
        return $html.'</ul></nav>';
    }
}
if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $message, $headers = []) {
        $h = array_merge(['From'=>'noreply@jobportal.com','Content-Type'=>'text/html; charset=UTF-8'], $headers);
        $hs = ''; foreach ($h as $k=>$v) $hs .= "$k: $v\r\n";
        return mail($to, $subject, $message, $hs);
    }
}
