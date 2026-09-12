<?php
// session started by requireLogin()
require_once 'config.php';
require_once 'sidebar.php';
requireLogin('coordinator');

$coor_id = (int)$_SESSION['user_id'];

if (isset($_GET['action']) && $_GET['action'] === 'mark_notifs_read') {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$coor_id");
    redirect(BASE_URL . 'coordinator_dashboard.php');
}

// Stats
$total_students = $conn->query("SELECT COUNT(*) as c FROM students WHERE coordinator_id=$coor_id")->fetch_assoc()['c'];
$deployed = $conn->query("SELECT COUNT(*) as c FROM students WHERE coordinator_id=$coor_id AND status='deployed'")->fetch_assoc()['c'];
$completed = $conn->query("SELECT COUNT(*) as c FROM students WHERE coordinator_id=$coor_id AND status='completed'")->fetch_assoc()['c'];
$pending_logs = $conn->query("SELECT COUNT(*) as c FROM weekly_logs wl JOIN students s ON wl.student_id=s.id WHERE s.coordinator_id=$coor_id AND wl.status='pending'")->fetch_assoc()['c'];
$pending_docs = $conn->query("SELECT COUNT(*) as c FROM document_submissions ds JOIN students s ON ds.student_id=s.id WHERE s.coordinator_id=$coor_id AND ds.status='pending'")->fetch_assoc()['c'];

// Students with their performance
$students_q = $conn->query("
    SELECT s.*, u.full_name, u.email,
        COALESCE((SELECT SUM(wl.rendered_hours) FROM weekly_logs wl WHERE wl.student_id=s.id AND wl.status!='rejected'),0) as total_rendered,
        COALESCE((SELECT COUNT(*) FROM weekly_logs wl WHERE wl.student_id=s.id AND wl.status='pending'),0) as pending_logs_count
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.coordinator_id=$coor_id
    ORDER BY u.full_name ASC
");
$student_list = [];
while ($row = $students_q->fetch_assoc()) $student_list[] = $row;

// Recent weekly log submissions
$recent_logs = $conn->query("
    SELECT wl.*, u.full_name, s.student_number
    FROM weekly_logs wl
    JOIN students s ON wl.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE s.coordinator_id=$coor_id
    ORDER BY wl.submitted_at DESC LIMIT 8
");
$log_list = [];
while ($row = $recent_logs->fetch_assoc()) $log_list[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Coordinator Dashboard - OJT Monitoring</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrapper">
<?php renderSidebar('coordinator', 'dashboard', $conn); ?>
<div class="main-content">
<?php renderTopbar('Coordinator Dashboard', $conn); ?>
<?php renderNotifPanel($conn); ?>
<div class="page-content">

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><?= svgIcon('users') ?></div>
        <div class="stat-info"><div class="value"><?= $total_students ?></div><div class="label">Total Students</div></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green"><?= svgIcon('check') ?></div>
        <div class="stat-info"><div class="value"><?= $deployed ?></div><div class="label">Deployed</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><?= svgIcon('clock') ?></div>
        <div class="stat-info"><div class="value"><?= $pending_logs ?></div><div class="label">Pending Weekly Logs</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><?= svgIcon('file') ?></div>
        <div class="stat-info"><div class="value"><?= $pending_docs ?></div><div class="label">Pending Documents</div></div>
    </div>
</div>

<div class="grid-2 mb-20">
    <!-- Students Performance -->
    <div class="card">
        <div class="card-header">
            <h3>Students Overview</h3>
            <a href="coordinator_students.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="card-body dashboard-scroll-card" style="padding:0;">
            <?php if (empty($student_list)): ?>
            <div class="empty-state"><?= svgIcon('users') ?><p>No students assigned.</p></div>
            <?php else: ?>
            <?php foreach (array_slice($student_list, 0, 6) as $s): ?>
            <?php $pct = min(100, $s['required_hours'] > 0 ? round(($s['total_rendered']/$s['required_hours'])*100) : 0); ?>
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);">
                <div class="flex-between" style="margin-bottom:6px;">
                    <div>
                        <div class="font-bold" style="font-size:13px;"><?= htmlspecialchars($s['full_name']) ?></div>
                        <div class="text-sm text-muted"><?= htmlspecialchars($s['student_number']) ?> — <?= htmlspecialchars($s['course']) ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div class="text-sm font-bold"><?= number_format($s['total_rendered'],1) ?> hrs</div>
                        <span class="badge badge-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span>
                    </div>
                </div>
                <div class="progress-wrap">
                    <div class="progress-bar <?= $pct>=100?'green':($pct>=50?'':'yellow') ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="text-sm text-muted" style="margin-top:3px;"><?= $pct ?>% of <?= number_format($s['required_hours'],0) ?> hrs</div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Logs -->
    <div class="card">
        <div class="card-header">
            <h3>Recent Weekly Submissions</h3>
            <a href="weekly_logs.php" class="btn btn-sm btn-primary">Review All</a>
        </div>
        <div class="card-body dashboard-scroll-card" style="padding:0;">
            <?php if (empty($log_list)): ?>
            <div class="empty-state"><?= svgIcon('clock') ?><p>No log submissions yet.</p></div>
            <?php else: ?>
            <?php foreach ($log_list as $log): ?>
            <div style="padding:10px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div class="font-bold text-sm"><?= htmlspecialchars($log['full_name']) ?></div>
                    <div class="text-sm text-muted">Week <?= $log['week_number'] ?> — <?= number_format($log['rendered_hours'],1) ?> hrs</div>
                    <div style="font-size:11px;color:#94a3b8;"><?= timeAgo($log['submitted_at']) ?></div>
                </div>
                <span class="badge badge-<?= $log['status'] ?>"><?= ucfirst($log['status']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</div>
</div>
</div>
<script src="main.js"></script>
</body>
</html>
