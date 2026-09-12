<?php
// session started by requireLogin()
require_once 'config.php';
require_once 'sidebar.php';
requireLogin('admin');

$uid = (int)$_SESSION['user_id'];

if (isset($_GET['action']) && $_GET['action'] === 'mark_notifs_read') {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
    redirect(BASE_URL . 'admin_dashboard.php');
}

// Stats
$total_students    = $conn->query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'];
$total_coordinators= $conn->query("SELECT COUNT(*) c FROM users WHERE role='coordinator'")->fetch_assoc()['c'];
$pending_logs      = $conn->query("SELECT COUNT(*) c FROM weekly_logs WHERE status='pending'")->fetch_assoc()['c'];
$pending_docs      = $conn->query("SELECT COUNT(*) c FROM document_submissions WHERE status='pending'")->fetch_assoc()['c'];
$deployed          = $conn->query("SELECT COUNT(*) c FROM students WHERE status='deployed'")->fetch_assoc()['c'];
$completed         = $conn->query("SELECT COUNT(*) c FROM students WHERE status='completed'")->fetch_assoc()['c'];

// Recent registrations
$recent_students = $conn->query("
    SELECT s.*, u.full_name, u.created_at, u.email, c.full_name AS coor_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN users c ON s.coordinator_id = c.id
    ORDER BY u.created_at DESC LIMIT 6
");
$recent_list = [];
while ($r = $recent_students->fetch_assoc()) $recent_list[] = $r;

// Recent weekly logs
$recent_logs = $conn->query("
    SELECT wl.*, u.full_name, s.student_number
    FROM weekly_logs wl
    JOIN students s ON wl.student_id = s.id
    JOIN users u ON s.user_id = u.id
    ORDER BY wl.submitted_at DESC LIMIT 6
");
$logs_list = [];
while ($r = $recent_logs->fetch_assoc()) $logs_list[] = $r;

// Hour totals
$avg_hrs = $conn->query("
    SELECT COALESCE(AVG(rendered),0) avg_r FROM (
        SELECT SUM(rendered_hours) rendered FROM weekly_logs WHERE status!='rejected' GROUP BY student_id
    ) t
")->fetch_assoc()['avg_r'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard – OJT Monitoring</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrapper">
<?php renderSidebar('admin','dashboard',$conn); ?>
<div class="main-content">
<?php renderTopbar('Admin Dashboard',$conn); ?>
<?php renderNotifPanel($conn); ?>
<div class="page-content">

<div class="stats-grid admin-stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><?= svgIcon('users') ?></div>
        <div class="stat-info"><div class="value"><?= $total_students ?></div><div class="label">Total Students</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><?= svgIcon('person') ?></div>
        <div class="stat-info"><div class="value"><?= $total_coordinators ?></div><div class="label">Coordinators</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><?= svgIcon('clock') ?></div>
        <div class="stat-info"><div class="value"><?= $pending_logs ?></div><div class="label">Pending Logs</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><?= svgIcon('file') ?></div>
        <div class="stat-info"><div class="value"><?= $pending_docs ?></div><div class="label">Pending Docs</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><?= svgIcon('chart') ?></div>
        <div class="stat-info"><div class="value"><?= $deployed ?></div><div class="label">Deployed</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><?= svgIcon('check') ?></div>
        <div class="stat-info"><div class="value"><?= $completed ?></div><div class="label">Completed</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><?= svgIcon('clock') ?></div>
        <div class="stat-info"><div class="value"><?= number_format($avg_hrs,1) ?></div><div class="label">Avg Hours/Student</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><?= svgIcon('file') ?></div>
        <div class="stat-info"><div class="value"><?= $total_students - $deployed - $completed ?></div><div class="label">Pending Deployment</div></div>
    </div>
</div>

<div class="grid-2">
    <div class="card mb-20">
        <div class="card-header">
            <h3>Recent Student Registrations</h3>
            <a href="admin_users.php?role=student" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="card-body dashboard-scroll-card">
        <?php if (empty($recent_list)): ?>
            <div class="empty-state"><?= svgIcon('users') ?><p>No students yet.</p></div>
        <?php else: foreach ($recent_list as $s): ?>
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <div class="font-bold text-sm"><?= htmlspecialchars($s['full_name']) ?></div>
                    <div class="text-sm text-muted"><?= htmlspecialchars($s['student_number']) ?> — <?= htmlspecialchars($s['course'] ?? '') ?></div>
                    <div style="font-size:11px;color:#94a3b8;">Coordinator: <?= htmlspecialchars($s['coor_name'] ?? 'None') ?></div>
                </div>
                <span class="badge badge-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span>
            </div>
        <?php endforeach; endif; ?>
        </div>
    </div>
</div>

</div></div></div>
<script src="main.js"></script>
</body>
</html>
