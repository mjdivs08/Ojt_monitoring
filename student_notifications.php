<?php
// session started by requireLogin()
require_once 'config.php';
require_once 'sidebar.php';
requireLogin('student');

$uid = (int)$_SESSION['user_id'];

if (isset($_GET['action']) && $_GET['action'] === 'mark_notifs_read') {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
    redirect(BASE_URL . 'student_notifications.php');
}

// Mark all as read when page opens
$conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");

$notifs = $conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC");
$notif_list = [];
while ($row = $notifs->fetch_assoc()) $notif_list[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notifications - OJT Monitoring</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrapper">
<?php renderSidebar('student', 'notifications', $conn); ?>
<div class="main-content">
<?php renderTopbar('Notifications', $conn); ?>
<?php renderNotifPanel($conn); ?>
<div class="page-content">
<div class="card">
    <div class="card-header"><h3>All Notifications</h3></div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($notif_list)): ?>
        <div class="empty-state"><?= svgIcon('bell') ?><p>No notifications yet.</p></div>
        <?php else: ?>
        <?php foreach ($notif_list as $n): ?>
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:flex-start;">
            <div class="notif-dot <?= $n['type'] ?>" style="width:10px;height:10px;border-radius:50%;margin-top:5px;flex-shrink:0;
                background:<?= $n['type']==='warning'?'var(--warning)':($n['type']==='error'?'var(--danger)':($n['type']==='success'?'var(--success)':'var(--info)')) ?>"></div>
            <div style="flex:1;">
                <div class="font-bold" style="font-size:14px;"><?= htmlspecialchars($n['title']) ?></div>
                <div class="text-muted text-sm" style="margin-top:3px;"><?= htmlspecialchars($n['message']) ?></div>
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;"><?= date('F d, Y h:i A', strtotime($n['created_at'])) ?></div>
            </div>
            <span class="badge badge-<?= $n['type'] === 'warning' ? 'warning' : ($n['type'] === 'error' ? 'rejected' : ($n['type'] === 'success' ? 'approved' : 'info')) ?>">
                <?= ucfirst($n['type']) ?>
            </span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
</div>
<script src="main.js"></script>
</body>
</html>
