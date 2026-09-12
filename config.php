<?php
date_default_timezone_set('Asia/Manila');
// ─── Database ───────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ojt_monitoring');

// ─── Base URL auto-detect ────────────────────────────────────────
// Works whether the folder is called ojt_system or something else
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Walk up from the current file to find the project root (the folder containing index.php)
// __DIR__ is e.g. /var/www/html/ojt_system/includes  → root = dirname(__DIR__)
$docRoot  = rtrim(str_replace('\\','/',$_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$projDir  = rtrim(str_replace('\\','/',dirname(__DIR__)), '/');
$basePath = str_replace($docRoot, '', $projDir);
if ($basePath === '') $basePath = '/ojt_system/';   // safe fallback
define('BASE_URL',    $protocol . '://' . $host . $basePath);
define('UPLOAD_PATH', $docRoot . '/ojt_systems/uploads/documents/');
define('UPLOAD_URL', $protocol . '://' . $host . '/ojt_systems/uploads/documents/');

// ─── DB Connection ───────────────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('
    <div style="font-family:sans-serif;padding:40px;color:#dc2626;
                background:#fee2e2;border-radius:8px;margin:40px auto;max-width:600px;">
        <h2>Database Connection Failed</h2>
        <p>' . htmlspecialchars($conn->connect_error) . '</p>
        <p style="font-size:13px;color:#7f1d1d;">
            Import <strong>database.sql</strong> in phpMyAdmin and
            update the credentials in <strong>includes/config.php</strong>.
        </p>
    </div>');
}
$conn->set_charset('utf8mb4');

// ─── Helpers ─────────────────────────────────────────────────────

function redirect($url) {
    header('Location: ' . $url);
    exit();
}

/**
 * Escape a value for safe insertion into a SQL string literal.
 * Do NOT also run htmlspecialchars here — that belongs at display time.
 */
function db_escape($conn, $val) {
    return $conn->real_escape_string(trim((string)$val));
}

/**
 * Legacy alias kept so existing calls still work.
 * NOTE: only used for DB — XSS escaping is done at output with htmlspecialchars().
 */
function sanitize($conn, $data) {
    return db_escape($conn, $data);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin($role = null) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isLoggedIn()) redirect(BASE_URL . '/login.php');
    if ($role && $_SESSION['role'] !== $role) redirect(BASE_URL . '/login.php');
}

function getStudentByUserId($conn, $user_id) {
    $uid = (int)$user_id;
    $r   = $conn->query("
        SELECT s.*, u.full_name, u.email, u.username
        FROM   students s
        JOIN   users u ON s.user_id = u.id
        WHERE  s.user_id = $uid
        LIMIT 1
    ");
    return ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
}

function getTotalRenderedHours($conn, $student_id) {
    $sid = (int)$student_id;
    $r   = $conn->query("
        SELECT COALESCE(SUM(rendered_hours), 0) AS total
        FROM   weekly_logs
        WHERE  student_id = $sid AND status != 'rejected'
    ");
    return ($r) ? (float)$r->fetch_assoc()['total'] : 0.0;
}

function getUnreadNotifCount($conn, $user_id) {
    $uid = (int)$user_id;
    $r   = $conn->query("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id=$uid AND is_read=0");
    return ($r) ? (int)$r->fetch_assoc()['cnt'] : 0;
}

function sendNotification($conn, $user_id, $title, $message, $type = 'info') {
    $uid = (int)$user_id;
    $t   = $conn->real_escape_string($title);
    $m   = $conn->real_escape_string($message);
    $ty  = in_array($type, ['info','warning','success','error']) ? $type : 'info';
    $conn->query("INSERT INTO notifications (user_id,title,message,type) VALUES ($uid,'$t','$m','$ty')");
}

function getSetting($conn, $key) {
    $k = $conn->real_escape_string($key);
    $r = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$k' LIMIT 1");
    return ($r && ($row = $r->fetch_assoc())) ? $row['setting_value'] : null;
}

function timeAgo($datetime)
{
    if (!$datetime) { return ''; }
    try { $timezone = new DateTimeZone('Asia/Manila'); $now = new DateTime('now', $timezone); $date = new DateTime( $datetime, $timezone );
        $seconds = $now->getTimestamp() - $date->getTimestamp();
        if ($seconds < 0) {
            return 'Just now';
        }
        if ($seconds < 60) {
            return 'Just now';
        }
        $minutes = floor(
            $seconds / 60
        );
        if ($minutes < 60) {
            return $minutes .
                ($minutes == 1
                    ? ' minute ago'
                    : ' minutes ago');
        }
        $hours = floor(
            $seconds / 3600
        );
        if ($hours < 24) {
            return $hours .
                ($hours == 1
                    ? ' hour ago'
                    : ' hours ago');
        }
        $days = floor(
            $seconds / 86400
        );
        if ($days < 7) {
            return $days .
                ($days == 1
                    ? ' day ago'
                    : ' days ago');
        }
        return $date->format(
            'M d, Y h:i A'
        );
    } catch (Exception $e) {
        return '';
    }
}

