<?php
// session started by requireLogin()
require_once 'config.php';
require_once 'sidebar.php';
requireLogin('student');

// Handle mark notifications read
if (isset($_GET['action']) && $_GET['action'] === 'mark_notifs_read') {
    $uid = (int)$_SESSION['user_id'];
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
    redirect(BASE_URL . 'student_dashboard.php');
}

$student = getStudentByUserId($conn, $_SESSION['user_id']);
if (!$student) {
    echo "Student profile not found. Please contact your coordinator.";
    exit;
}
$sid = $student['id'];
$total_rendered = (float)getTotalRenderedHours($conn, $sid);
$required = (float)$student['required_hours'];
$weekly_required = (float)$student['weekly_required_hours'];
$progress = $required > 0 ? min(100, round(($total_rendered / $required) * 100)) : 0;
$remaining = max(0, $required - $total_rendered);

// Weekly logs — guard against query failure
$weeks_result = $conn->query("SELECT * FROM weekly_logs WHERE student_id=$sid ORDER BY week_number DESC LIMIT 5");
$weeks = [];
if ($weeks_result) {
    while ($row = $weeks_result->fetch_assoc()) $weeks[] = $row;
}

// Remaining cumulative deficit
$hour_balance = 0;

$all_weeks = $conn->query("
    SELECT rendered_hours
    FROM weekly_logs
    WHERE student_id = $sid
      AND status != 'rejected'
    ORDER BY week_number ASC
");

while ($row = $all_weeks->fetch_assoc()) {

        // Running hour balance
    $hour_balance += ($row['rendered_hours'] - $weekly_required);
}

// Documents
$doc_reqs = $conn->query("SELECT dr.*, ds.status as sub_status FROM document_requirements dr LEFT JOIN document_submissions ds ON dr.id = ds.requirement_id AND ds.student_id=$sid WHERE dr.is_required=1");
$docs_submitted = 0; $docs_total = 0;
$doc_list = [];
while ($row = $doc_reqs->fetch_assoc()) {
    $docs_total++;
    if ($row['sub_status']) $docs_submitted++;
    $doc_list[] = $row;
}

// Unread notifications
$notif_count = getUnreadNotifCount($conn, $_SESSION['user_id']);

// Deadline warning
$deadline_msg = '';
if ($student['pre_deployment_deadline']) {

    $deadline = new DateTime($student['pre_deployment_deadline']);
    $now = new DateTime();

    if ($now < $deadline) {

        $diff = $now->diff($deadline);

        $deadline_msg = "Submit all documents before <strong>" .
            $deadline->format('F d, Y') .
            "</strong> ({$diff->days} days left)";
    }
}

// Active warnings
$warnings = $conn->query("SELECT * FROM notifications WHERE user_id={$_SESSION['user_id']} AND type='warning' AND is_read=0 ORDER BY created_at DESC LIMIT 3");
$warn_list = [];
while ($row = $warnings->fetch_assoc()) $warn_list[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard - OJT Monitoring</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrapper">
<?php renderSidebar('student', 'dashboard', $conn); ?>
<div class="main-content">
<?php renderTopbar('Dashboard', $conn); ?>
<?php renderNotifPanel($conn); ?>
<div class="page-content">

<?php foreach ($warn_list as $w): ?>
<div class="alert alert-warning" style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
    <span style="width:20px;height:20px;display:inline-flex;">
        <?= svgIcon('warning') ?>
    </span>
    <div>
        <strong><?= htmlspecialchars($w['title']) ?></strong>
    </div>
</div>
<?php endforeach; ?>

<?php if ($deadline_msg): ?>
<div class="deadline-banner">
    <?= svgIcon('calendar') ?>
    <div class="text"><span><?= $deadline_msg ?></span></div>
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><?= svgIcon('clock') ?></div>
        <div class="stat-info">
            <div class="value"><?= number_format($total_rendered, 1) ?></div>
            <div class="label">Hours Rendered</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><?= svgIcon('chart') ?></div>
        <div class="stat-info">
            <div class="value"><?= $progress ?>%</div>
            <div class="label">OJT Progress</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><?= svgIcon('clock') ?></div>
        <div class="stat-info">
            <div class="value"><?= number_format($remaining, 1) ?></div>
            <div class="label">Hours Remaining</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $hour_balance < 0 ? 'red' : 'green' ?>">
            <?= svgIcon('warning') ?>
        </div>

        <div class="stat-info">

            <div class="value <?= $hour_balance < 0 ? 'text-danger' : 'text-success' ?>">
                <?= $hour_balance > 0 ? '+' : '' ?>
                <?= number_format($hour_balance, 1) ?> hrs
            </div>

            <div class="label">
                Cumulative Hour Deficit / Excess
            </div>

        </div>
    </div>
</div>

<div class="grid-2 mb-20">
    <div class="card">
        <div class="card-header"><h3>OJT Progress</h3></div>
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                <span class="text-muted text-sm">Rendered: <strong><?= number_format($total_rendered,1) ?> hrs</strong></span>
                <span class="text-muted text-sm">Required: <strong><?= number_format($required,1) ?> hrs</strong></span>
            </div>
            <div class="progress-wrap">
                <div class="progress-bar <?= $progress >= 100 ? 'green' : ($progress >= 50 ? '' : 'yellow') ?>" style="width:<?= $progress ?>%"></div>
            </div>
            <div style="margin-top:10px;font-size:13px;color:var(--text-muted);">
                <?php if ($progress >= 100): ?>
                    <span class="text-success font-bold">✓ Required hours completed!</span>
                <?php else: ?>
                    <span><?= number_format($remaining,1) ?> hours more to complete your OJT</span>
                <?php endif; ?>
            </div>
            <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);">
                <div class="text-muted text-sm" style="margin-bottom:6px;">Weekly Target: <strong><?= number_format($weekly_required,1) ?> hrs/week</strong></div>
                <div class="text-muted text-sm">Company: <strong><?= htmlspecialchars($student['company_name'] ?: 'Not set') ?></strong></div>
                <div class="text-muted text-sm" style="margin-top:4px;">Status: <span class="badge badge-<?= $student['status'] ?>"><?= ucfirst($student['status']) ?></span></div>
            </div>
        </div>
    </div>

    <div class="card">
    <div class="card-header">
        <h3>Document Checklist</h3>
        <a href="student_documents.php" class="btn btn-sm btn-primary">View All</a>
    </div>

    <div class="card-body" style="padding:12px;">
        <div style="margin-bottom:12px;">
            <div class="progress-wrap">
                <div class="progress-bar green"
                    style="width:<?= $docs_total > 0 ? round(($docs_submitted / $docs_total) * 100) : 0 ?>%">
                </div>
            </div>
            <div class="text-sm text-muted" style="margin-top:6px;">
                <?= $docs_submitted ?>/<?= $docs_total ?> submitted
            </div>
        </div>

        <?php foreach (array_slice($doc_list, 0, 5) as $doc): ?>

            <?php
            // Hide PhilHealth requirement for non-BSTM students
            if (
                stripos($doc['doc_name'], 'PhilHealth') !== false &&
                strtoupper(trim($student['course'])) !== 'BSTM'
            ) {
                continue;
            }
            ?>

            <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--border);">
                <span style="font-size:16px;">
                    <?= $doc['sub_status'] === 'approved'
                        ? '✅'
                        : ($doc['sub_status'] ? '🕐' : '⬜') ?>
                </span>

                <span class="text-sm">
                    <?= htmlspecialchars($doc['doc_name']) ?>
                </span>

                <?php if ($doc['sub_status']): ?>
                    <span class="badge badge-<?= $doc['sub_status'] ?>" style="margin-left:auto;">
                        <?= ucfirst($doc['sub_status']) ?>
                    </span>
                <?php endif; ?>
            </div>

        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Recent Weekly Logs</h3>
        <a href="weekly_log.php" class="btn btn-sm btn-primary">Submit Hours</a>
    </div>
    <?php if (empty($weeks)): ?>
    <div class="empty-state"><?= svgIcon('clock') ?><p>No weekly logs yet. Start by submitting your hours.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Week</th><th>Period</th><th>Rendered</th><th>Target</th><th>Difference</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($weeks as $w): ?>
            <?php $diff = $w['rendered_hours'] - $weekly_required; ?>
            <tr>
                <td>Week <?= $w['week_number'] ?></td>
                <td class="text-sm text-muted"><?= date('M d', strtotime($w['week_start'])) ?> - <?= date('M d, Y', strtotime($w['week_end'])) ?></td>
                <td><strong><?= number_format($w['rendered_hours'],1) ?> hrs</strong></td>
                <td class="text-muted"><?= number_format($weekly_required,1) ?> hrs</td>
                <td>
                    <span class="<?= $diff >= 0 ? 'hours-positive' : 'hours-negative' ?>">
                        <?= $diff >= 0 ? '+' : '' ?><?= number_format($diff,1) ?> hrs
                    </span>
                </td>
                <td><span class="badge badge-<?= $w['status'] ?>"><?= ucfirst($w['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</div>
</div>
</div>
<script src="main.js"></script>
</body>
</html>
