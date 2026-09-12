<?php
require_once 'config.php';
require_once 'sidebar.php';
require_once 'signature_verification.php';

requireLogin('student');

$student = getStudentByUserId($conn, $_SESSION['user_id']);
if (!$student) {
    exit('Student profile not found. Please contact your coordinator.');
}

$sid = (int)$student['id'];
$uid = (int)$_SESSION['user_id'];
$weekly_required = (float)$student['weekly_required_hours'];
$msg = '';
$err = '';

/* --------------------------------------------------------------------------
   Mark notifications read
---------------------------------------------------------------------------- */
if (isset($_GET['action']) && $_GET['action'] === 'mark_notifs_read') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->close();
    redirect(BASE_URL . 'weekly_log.php');
}

/* --------------------------------------------------------------------------
   Student's deployed company and stored company reference signature
---------------------------------------------------------------------------- */
$company = null;
$stmt = $conn->prepare("\n    SELECT\n        c.id,\n        c.company_name,\n        c.supervisor_name,\n        c.reference_signature\n    FROM students s\n    LEFT JOIN companies c ON c.id = s.company_id\n    WHERE s.id = ?\n    LIMIT 1\n");
$stmt->bind_param('i', $sid);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (!empty($row['id'])) {
        $company = $row;
    }
}
$stmt->close();

/* --------------------------------------------------------------------------
   Submit / re-submit weekly log
---------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_log'])) {
    $week_num  = (int)($_POST['week_number'] ?? 0);
    $week_start = sanitize($conn, $_POST['week_start'] ?? '');
    $week_end   = sanitize($conn, $_POST['week_end'] ?? '');
    $rendered   = (float)($_POST['rendered_hours'] ?? 0);

    $signatureX      = isset($_POST['signature_x']) ? (float)$_POST['signature_x'] : -1;
    $signatureY      = isset($_POST['signature_y']) ? (float)$_POST['signature_y'] : -1;
    $signatureWidth  = isset($_POST['signature_width']) ? (float)$_POST['signature_width'] : 0;
    $signatureHeight = isset($_POST['signature_height']) ? (float)$_POST['signature_height'] : 0;

    $existingLog = null;

    if ($week_num < 1 || $week_num > 52) {
        $err = 'Invalid week number.';
    } elseif (!$week_start || !$week_end) {
        $err = 'Please select the week start and end dates.';
    } elseif (strtotime($week_end) < strtotime($week_start)) {
        $err = 'Week end date cannot be earlier than week start date.';
    } elseif ($rendered <= 0 || $rendered > 120) {
        $err = 'Please enter valid rendered hours (0.5–120).';
    } elseif (!$company) {
        $err = 'You have not yet been assigned to an OJT company.';
    } elseif (empty($company['reference_signature'])) {
        $err = 'Your deployed company does not yet have a registered supervisor reference signature.';
    } elseif (!isset($_FILES['dtr_photo']) || $_FILES['dtr_photo']['error'] !== UPLOAD_ERR_OK) {
        $err = 'DTR image is required.';
    } elseif (
        $signatureX < 0 || $signatureY < 0 ||
        $signatureWidth <= 0 || $signatureHeight <= 0 ||
        $signatureX > 1 || $signatureY > 1 ||
        $signatureWidth > 1 || $signatureHeight > 1 ||
        ($signatureX + $signatureWidth) > 1.01 ||
        ($signatureY + $signatureHeight) > 1.01
    ) {
        $err = 'Please highlight the complete supervisor signature before submitting.';
    }

    /* Check if the week already exists. Rejected logs may be re-uploaded. */
    if (!$err) {
        $stmt = $conn->prepare("\n            SELECT id, status, dtr_photo\n            FROM weekly_logs\n            WHERE student_id = ? AND week_number = ?\n            LIMIT 1\n        ");
        $stmt->bind_param('ii', $sid, $week_num);
        $stmt->execute();
        $weekResult = $stmt->get_result();
        if ($weekResult && $weekResult->num_rows > 0) {
            $existingLog = $weekResult->fetch_assoc();
            $existingLog['status'] = strtolower(trim((string)$existingLog['status']));

            if ($existingLog['status'] === 'pending') {
                $err = "Week {$week_num} is still pending review. You cannot upload another DTR yet.";
            } elseif ($existingLog['status'] === 'approved') {
                $err = "Week {$week_num} has already been approved and is locked.";
            }
        }
        $stmt->close();
    }

    $dtr_photo = '';
    $fullDtrPath = '';

    /* Validate and save the complete DTR image. */
    if (!$err) {
        $file = $_FILES['dtr_photo'];

        if ($file['size'] > 5 * 1024 * 1024) {
            $err = 'DTR image must be under 5 MB.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            if (!isset($allowed[$mime])) {
                $err = 'DTR must be a JPG, PNG, or WEBP image.';
            } else {
                $uploadDir = UPLOAD_PATH . 'dtr' . DIRECTORY_SEPARATOR;
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    $err = 'Unable to create the DTR upload folder.';
                } else {
                    $filename = 'dtr_' . $sid . '_wk' . $week_num . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $allowed[$mime];
                    $fullDtrPath = $uploadDir . $filename;

                    if (!move_uploaded_file($file['tmp_name'], $fullDtrPath)) {
                        $err = 'Unable to save the DTR image.';
                    } else {
                        $dtr_photo = 'dtr/' . $filename;
                    }
                }
            }
        }
    }

    /* Compare highlighted DTR signature with the student's company reference. */
    $signatureSimilarity = null;
    $signatureResult = null;

    if (!$err) {
        $referencePath = UPLOAD_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $company['reference_signature']);

        $verification = verifySelectedSignature(
            $referencePath,
            $fullDtrPath,
            $signatureX,
            $signatureY,
            $signatureWidth,
            $signatureHeight
        );

        if (empty($verification['success'])) {
            if ($fullDtrPath && is_file($fullDtrPath)) {
                @unlink($fullDtrPath);
            }
            $err = 'Signature verification could not be completed. ' . ($verification['result'] ?? '');
        } else {
            $signatureSimilarity = (float)$verification['similarity'];
            $signatureResult = (string)$verification['result'];
        }
    }

    if (!$err) {
        /* Re-upload rejected week by updating the SAME row, avoiding duplicates. */
        if ($existingLog && $existingLog['status'] === 'rejected') {
            $stmt = $conn->prepare("\n                UPDATE weekly_logs\n                SET\n                    week_start = ?,\n                    week_end = ?,\n                    rendered_hours = ?,\n                    dtr_photo = ?,\n                    signature_x = ?,\n                    signature_y = ?,\n                    signature_width = ?,\n                    signature_height = ?,\n                    signature_similarity = ?,\n                    signature_result = ?,\n                    signature_checked_at = NOW(),\n                    status = 'pending',\n                    coordinator_remarks = NULL,\n                    submitted_at = NOW(),\n                    reviewed_at = NULL,\n                    reviewed_by = NULL\n                WHERE id = ? AND student_id = ? AND status = 'rejected'\n            ");
            $stmt->bind_param(
                'ssdsdddddsii',
                $week_start,
                $week_end,
                $rendered,
                $dtr_photo,
                $signatureX,
                $signatureY,
                $signatureWidth,
                $signatureHeight,
                $signatureSimilarity,
                $signatureResult,
                $existingLog['id'],
                $sid
            );

            if (!$stmt->execute()) {
                $err = 'Database error: ' . $stmt->error;
                if (is_file($fullDtrPath)) @unlink($fullDtrPath);
            } else {
                /* Remove the old rejected DTR only after update succeeds. */
                if (!empty($existingLog['dtr_photo'])) {
                    $oldPath = UPLOAD_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $existingLog['dtr_photo']);
                    if (is_file($oldPath) && $oldPath !== $fullDtrPath) {
                        @unlink($oldPath);
                    }
                }
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("\n                INSERT INTO weekly_logs (\n                    student_id, week_number, week_start, week_end, rendered_hours, dtr_photo,\n                    signature_x, signature_y, signature_width, signature_height,\n                    signature_similarity, signature_result, signature_checked_at\n                )\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())\n            ");
            $stmt->bind_param(
                'iissdsddddds',
                $sid,
                $week_num,
                $week_start,
                $week_end,
                $rendered,
                $dtr_photo,
                $signatureX,
                $signatureY,
                $signatureWidth,
                $signatureHeight,
                $signatureSimilarity,
                $signatureResult
            );

            if (!$stmt->execute()) {
                $err = 'Database error: ' . $stmt->error;
                if (is_file($fullDtrPath)) @unlink($fullDtrPath);
            }
            $stmt->close();
        }

        if (!$err) {
            $wasResubmission = ($existingLog && $existingLog['status'] === 'rejected');
            redirect(
                BASE_URL . 'weekly_log.php?submitted=' . $week_num
                . ($wasResubmission ? '&resubmitted=1' : '')
            );
        }
    }
}

if (isset($_GET['submitted'])) {
    $submittedWeek = (int)$_GET['submitted'];
    if (isset($_GET['resubmitted'])) {
        $msg = 'Week ' . $submittedWeek . ' DTR re-uploaded successfully and sent back for coordinator review.';
    } else {
        $msg = 'Week ' . $submittedWeek . ' DTR submitted successfully and sent for coordinator review.';
    }
}

/* --------------------------------------------------------------------------
   Load weekly logs
---------------------------------------------------------------------------- */
$stmt = $conn->prepare("SELECT * FROM weekly_logs WHERE student_id = ? ORDER BY week_number ASC");
$stmt->bind_param('i', $sid);
$stmt->execute();
$logsResult = $stmt->get_result();

$log_list = [];
$total_rendered = 0.0;
$hour_balance = 0.0;

while ($row = $logsResult->fetch_assoc()) {
    $diff = (float)$row['rendered_hours'] - $weekly_required;
    $row['diff'] = $diff;
    $log_list[] = $row;

    /* Only approved logs affect official progress/balance. */
    if ($row['status'] === 'approved') {
        $total_rendered += (float)$row['rendered_hours'];
        $hour_balance += $diff;
    }
}
$stmt->close();

$last_week = !empty($log_list) ? max(array_column($log_list, 'week_number')) : 0;
$next_week = min(52, $last_week + 1);
$week_start_suggest = date('Y-m-d', strtotime('monday this week'));
$week_end_suggest = date('Y-m-d', strtotime('friday this week'));

/* --------------------------------------------------------------------------
   Re-upload mode for a rejected week
---------------------------------------------------------------------------- */
$reuploadLog = null;
$reuploadWeek = isset($_GET['reupload']) ? (int)$_GET['reupload'] : 0;

if ($reuploadWeek >= 1 && $reuploadWeek <= 52) {
    $stmt = $conn->prepare("
        SELECT *
        FROM weekly_logs
        WHERE student_id = ?
          AND week_number = ?
          AND status = 'rejected'
        ORDER BY id DESC
        LIMIT 1
    " );
    $stmt->bind_param('ii', $sid, $reuploadWeek);
    $stmt->execute();
    $reuploadResult = $stmt->get_result();

    if ($reuploadResult && $reuploadResult->num_rows > 0) {
        $reuploadLog = $reuploadResult->fetch_assoc();
        $next_week = (int)$reuploadLog['week_number'];
        $week_start_suggest = $reuploadLog['week_start'];
        $week_end_suggest = $reuploadLog['week_end'];
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Weekly Hours - OJT Monitoring</title>
<link rel="stylesheet" href="<?= BASE_URL ?>style.css?v=<?= time() ?>">
<style>
.company-verification-info {
    padding: 12px 14px;
    margin-bottom: 15px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--bg);
}
.dtr-preview-section { display:none; margin-top:15px; }
.dtr-preview-section.show { display:block; }
.signature-instruction {
    background:#eff6ff;
    color:#1e40af;
    border-radius:9px;
    padding:11px 13px;
    margin-bottom:10px;
    font-size:13px;
    line-height:1.5;
}
.dtr-canvas-wrap {
    width:100%;
    overflow:auto;
    max-height:600px;
    padding:8px;
    border:2px solid #dbe4ee;
    border-radius:10px;
    background:#f8fafc;
}
#dtrSelectionCanvas {
    display:block;
    max-width:100%;
    height:auto;
    margin:0 auto;
    cursor:crosshair;
    touch-action:none;
    user-select:none;
    -webkit-user-select:none;
}
.signature-selection-status { margin-top:8px; font-size:13px; font-weight:600; }
.signature-selection-status.ready { color:#15803d; }
.signature-selection-status.not-ready { color:#b45309; }
#submitWeeklyLogBtn:disabled { opacity:.55; cursor:not-allowed; }
</style>
</head>
<body>
<div class="wrapper">
<?php renderSidebar('student', 'weekly_log', $conn); ?>
<div class="main-content">
<?php renderTopbar('Weekly Hours Log', $conn); ?>
<?php renderNotifPanel($conn); ?>
<div class="page-content">

<?php if ($msg): ?><div class="alert alert-success" data-auto-hide><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="grid-2 mb-20">
    <div class="stat-card">
        <div class="stat-icon blue"><?= svgIcon('clock') ?></div>
        <div class="stat-info">
            <div class="value"><?= number_format($total_rendered, 1) ?></div>
            <div class="label">Approved Rendered Hours</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $hour_balance < 0 ? 'red' : 'green' ?>"><?= svgIcon('warning') ?></div>
        <div class="stat-info">
            <div class="value <?= $hour_balance < 0 ? 'text-danger' : 'text-success' ?>">
                <?= $hour_balance > 0 ? '+' : '' ?><?= number_format($hour_balance, 1) ?> hrs
            </div>
            <div class="label">Cumulative Hour Deficit / Excess</div>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Submit Weekly Log</h3></div>
        <div class="card-body">
            <?php if ($company): ?>
                <div class="company-verification-info">
                    <div class="text-sm text-muted">Deployed Company</div>
                    <strong><?= htmlspecialchars($company['company_name']) ?></strong>
                    <?php if (!empty($company['supervisor_name'])): ?>
                        <div class="text-sm text-muted" style="margin-top:4px;">
                            OJT Supervisor: <strong><?= htmlspecialchars($company['supervisor_name']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!$company): ?>
                <div class="alert alert-warning">You cannot submit a Weekly DTR until a company is assigned to you.</div>
            <?php elseif (empty($company['reference_signature'])): ?>
                <div class="alert alert-warning">Your company does not yet have a registered supervisor reference signature.</div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="weeklyLogForm">
                <div class="form-group">
                    <label>Week Number</label>
                    <input
                        type="number"
                        name="week_number"
                        value="<?= (int)$next_week ?>"
                        min="1"
                        max="52"
                        <?= $reuploadLog ? 'readonly' : '' ?>
                        required
                    >
                    <?php if ($reuploadLog): ?>
                        <div class="alert alert-warning" style="margin-top:8px;margin-bottom:0;">
                            Re-uploading rejected <strong>Week <?= (int)$reuploadLog['week_number'] ?></strong>.
                            Upload a corrected DTR, highlight the supervisor signature again, then submit.
                        </div>
                    <?php else: ?>
                        <div class="text-sm text-muted" style="margin-top:4px;">
                            For a rejected DTR, use the <strong>Re-upload</strong> button beside that rejected week below.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Week Start</label>
                        <input type="date" name="week_start" value="<?= htmlspecialchars($week_start_suggest) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Week End</label>
                        <input type="date" name="week_end" value="<?= htmlspecialchars($week_end_suggest) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Hours Rendered This Week</label>
                    <input
                        type="number"
                        name="rendered_hours"
                        value="<?= $reuploadLog ? htmlspecialchars((string)$reuploadLog['rendered_hours']) : '' ?>"
                        placeholder="e.g. 40"
                        step="0.5"
                        min="0.5"
                        max="120"
                        required
                        oninput="updateHourPreview(this.value)"
                    >
                    <div id="hourPreview" style="margin-top:6px;font-size:13px;"></div>
                </div>

                <div class="form-group">
                    <label>Upload DTR Photo <span class="text-muted text-sm">(required)</span></label>
                    <div class="file-upload-area" id="dtr_upload_area">
                        <?= svgIcon('upload') ?>
                        <p>Click to upload DTR</p>
                        <p class="text-sm text-muted">JPG, PNG, or WEBP — max 5 MB</p>
                        <div class="file-name" id="dtr_file_name"></div>
                    </div>
                    <input type="file" id="dtr_photo" name="dtr_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" style="display:none" required>
                </div>

                <div class="dtr-preview-section" id="dtrPreviewSection">
                    <div class="signature-instruction">
                        <strong>Highlight the OJT supervisor's signature.</strong><br>
                        Drag a rectangle around the supervisor signature only. The complete DTR is still saved; this rectangle only tells the system what part to compare.
                    </div>
                    <div class="dtr-canvas-wrap">
                        <canvas id="dtrSelectionCanvas"></canvas>
                    </div>
                    <div id="signatureSelectionStatus" class="signature-selection-status not-ready">No signature selected.</div>
                    <button type="button" id="clearSignatureSelection" class="btn btn-sm btn-secondary" style="margin-top:8px;">Clear Selection</button>
                </div>

                <input type="hidden" name="signature_x" id="signature_x">
                <input type="hidden" name="signature_y" id="signature_y">
                <input type="hidden" name="signature_width" id="signature_width">
                <input type="hidden" name="signature_height" id="signature_height">

                <button type="submit" name="submit_log" id="submitWeeklyLogBtn" class="btn btn-primary btn-block" disabled>Submit Weekly Log</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Hours Summary</h3></div>
        <div class="card-body" style="padding:12px;">
            <div style="background:var(--bg);border-radius:var(--radius);padding:12px;margin-bottom:14px;">
                <div class="flex-between" style="margin-bottom:4px;">
                    <span class="text-sm text-muted">Weekly Target</span>
                    <span class="font-bold"><?= number_format($weekly_required, 1) ?> hrs</span>
                </div>
                <div class="flex-between" style="margin-bottom:4px;">
                    <span class="text-sm text-muted">Total Required</span>
                    <span class="font-bold"><?= number_format((float)$student['required_hours'], 1) ?> hrs</span>
                </div>
                <div class="flex-between">
                    <span class="text-sm text-muted">Approved Rendered</span>
                    <span class="font-bold text-success"><?= number_format($total_rendered, 1) ?> hrs</span>
                </div>
            </div>

            <div style="font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:8px;">WEEKLY BREAKDOWN</div>
            <?php if (empty($log_list)): ?>
                <p class="text-muted text-sm text-center" style="padding:16px;">No logs submitted yet.</p>
            <?php else: ?>
                <div style="max-height:300px;overflow-y:auto;">
                    <?php foreach (array_reverse($log_list) as $log): ?>
                        <div style="padding:8px 0;border-bottom:1px solid var(--border);">
                            <div class="flex-between">
                                <span class="text-sm font-bold">Week <?= (int)$log['week_number'] ?></span>
                                <span class="badge badge-<?= htmlspecialchars($log['status']) ?>"><?= ucfirst(htmlspecialchars($log['status'])) ?></span>
                            </div>
                            <div class="flex-between" style="margin-top:3px;">
                                <span class="text-sm text-muted"><?= date('M d', strtotime($log['week_start'])) ?> - <?= date('M d', strtotime($log['week_end'])) ?></span>
                                <span class="text-sm font-bold"><?= number_format((float)$log['rendered_hours'], 1) ?> hrs</span>
                            </div>
                            <?php if (!empty($log['coordinator_remarks'])): ?>
                                <div style="font-size:11px;color:var(--info);margin-top:3px;font-style:italic;">Remarks: <?= htmlspecialchars($log['coordinator_remarks']) ?></div>
                            <?php endif; ?>
                            <?php if ($log['status'] === 'rejected'): ?>
                                <div style="margin-top:7px;">
                                    <a
                                        href="<?= BASE_URL ?>weekly_log.php?reupload=<?= (int)$log['week_number'] ?>"
                                        class="btn btn-sm btn-primary"
                                    >
                                        Re-upload Week <?= (int)$log['week_number'] ?> DTR
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($log_list)): ?>
<div class="card mt-20">
    <div class="card-header"><h3>All Weekly Logs</h3></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Week</th>
                    <th>Period</th>
                    <th>Rendered</th>
                    <th>DTR</th>
                    <th>Signature Check</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($log_list as $log): ?>
                <tr>
                    <td><strong>Week <?= (int)$log['week_number'] ?></strong></td>
                    <td class="text-sm text-muted"><?= date('M d', strtotime($log['week_start'])) ?> - <?= date('M d, Y', strtotime($log['week_end'])) ?></td>
                    <td><strong><?= number_format((float)$log['rendered_hours'], 1) ?> hrs</strong></td>
                    <td>
                        <?php if (!empty($log['dtr_photo'])): ?>
                            <a
    href="<?= htmlspecialchars(
        UPLOAD_URL . $log['dtr_photo'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    target="_blank"
    rel="noopener"
    class="btn btn-sm btn-secondary"
>
    <?= svgIcon('eye') ?> View
</a>
                        <?php else: ?>
                            <span class="text-muted text-sm">None</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($log['signature_checked_at'])): ?>
                            <span class="badge badge-approved">Verification Completed</span>
                        <?php else: ?>
                            <span class="text-muted text-sm">Not checked</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= htmlspecialchars($log['status']) ?>"><?= ucfirst(htmlspecialchars($log['status'])) ?></span></td>
                    <td class="text-sm text-muted"><?= htmlspecialchars($log['coordinator_remarks'] ?: '—') ?></td>
                    <td>
                        <?php if ($log['status'] === 'rejected'): ?>
                            <a
                                href="<?= BASE_URL ?>weekly_log.php?reupload=<?= (int)$log['week_number'] ?>"
                                class="btn btn-sm btn-primary"
                            >
                                Re-upload DTR
                            </a>
                        <?php elseif ($log['status'] === 'pending'): ?>
                            <span class="text-muted text-sm">Waiting for review</span>
                        <?php else: ?>
                            <span class="text-muted text-sm">Locked</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div>
</div>
</div>

<script src="<?= BASE_URL ?>main.js?v=<?= time() ?>"></script>
<script>
function updateHourPreview(val) {
    const target = <?= json_encode($weekly_required) ?>;
    const rendered = parseFloat(val) || 0;
    const diff = rendered - target;
    const el = document.getElementById('hourPreview');
    if (!val) { el.innerHTML = ''; return; }
    el.innerHTML = diff >= 0
        ? '<span class="text-success">+' + diff.toFixed(1) + ' hrs above target</span>'
        : '<span class="text-danger">' + diff.toFixed(1) + ' hrs below target</span>';
}

const fileInput = document.getElementById('dtr_photo');
const uploadArea = document.getElementById('dtr_upload_area');
const fileNameDisplay = document.getElementById('dtr_file_name');
const previewSection = document.getElementById('dtrPreviewSection');
const canvas = document.getElementById('dtrSelectionCanvas');
const ctx = canvas.getContext('2d');
const statusElement = document.getElementById('signatureSelectionStatus');
const submitButton = document.getElementById('submitWeeklyLogBtn');
const clearButton = document.getElementById('clearSignatureSelection');
const signatureX = document.getElementById('signature_x');
const signatureY = document.getElementById('signature_y');
const signatureWidth = document.getElementById('signature_width');
const signatureHeight = document.getElementById('signature_height');

let image = new Image();
let selecting = false;
let startX = 0;
let startY = 0;
let selection = null;

uploadArea.addEventListener('click', function (event) {
    event.preventDefault();
    fileInput.click();
});

fileInput.addEventListener('change', function () {
    const file = this.files[0];
    resetSelection(false);

    if (!file) {
        previewSection.classList.remove('show');
        fileNameDisplay.textContent = '';
        return;
    }

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        alert('Please select a JPG, PNG, or WEBP image.');
        this.value = '';
        previewSection.classList.remove('show');
        return;
    }

    fileNameDisplay.textContent = file.name;
    const reader = new FileReader();

    reader.onload = function (event) {
        image = new Image();
        image.onload = function () {
            let width = image.naturalWidth;
            let height = image.naturalHeight;
            const maxWidth = 900;

            if (width > maxWidth) {
                const ratio = maxWidth / width;
                width = Math.round(width * ratio);
                height = Math.round(height * ratio);
            }

            canvas.width = width;
            canvas.height = height;
            previewSection.classList.add('show');
            drawCanvas();
        };
        image.src = event.target.result;
    };

    reader.readAsDataURL(file);
});

function drawCanvas() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    if (image && image.complete && image.naturalWidth > 0) {
        ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
    }

    if (selection && selection.width > 0 && selection.height > 0) {
        ctx.save();
        ctx.fillStyle = 'rgba(37, 99, 235, 0.20)';
        ctx.strokeStyle = '#2563eb';
        ctx.lineWidth = 3;
        ctx.fillRect(selection.x, selection.y, selection.width, selection.height);
        ctx.strokeRect(selection.x, selection.y, selection.width, selection.height);

        const label = 'Supervisor Signature';
        ctx.font = 'bold 13px Arial';
        const textWidth = ctx.measureText(label).width;
        const labelX = Math.max(0, selection.x);
        const labelTop = Math.max(0, selection.y - 22);
        ctx.fillStyle = '#2563eb';
        ctx.fillRect(labelX, labelTop, textWidth + 14, 20);
        ctx.fillStyle = '#fff';
        ctx.fillText(label, labelX + 7, labelTop + 15);
        ctx.restore();
    }
}

function canvasPosition(event) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    return {
        x: (event.clientX - rect.left) * scaleX,
        y: (event.clientY - rect.top) * scaleY
    };
}

canvas.addEventListener('pointerdown', function (event) {
    event.preventDefault();
    if (!image || !image.complete || !image.naturalWidth || canvas.width <= 0) return;

    try { canvas.setPointerCapture(event.pointerId); } catch (e) {}

    const p = canvasPosition(event);
    selecting = true;
    startX = Math.max(0, Math.min(canvas.width, p.x));
    startY = Math.max(0, Math.min(canvas.height, p.y));
    selection = { x: startX, y: startY, width: 0, height: 0 };

    clearCoordinateFields();
    submitButton.disabled = true;
    statusElement.className = 'signature-selection-status not-ready';
    statusElement.textContent = 'Selecting supervisor signature...';
    drawCanvas();
});

canvas.addEventListener('pointermove', function (event) {
    if (!selecting) return;
    event.preventDefault();

    const p = canvasPosition(event);
    const currentX = Math.max(0, Math.min(canvas.width, p.x));
    const currentY = Math.max(0, Math.min(canvas.height, p.y));

    selection = {
        x: Math.min(startX, currentX),
        y: Math.min(startY, currentY),
        width: Math.abs(currentX - startX),
        height: Math.abs(currentY - startY)
    };
    drawCanvas();
});

canvas.addEventListener('pointerup', function (event) {
    event.preventDefault();
    finishSelection();
    try { canvas.releasePointerCapture(event.pointerId); } catch (e) {}
});

canvas.addEventListener('pointercancel', function () {
    selecting = false;
});

function finishSelection() {
    if (!selecting) return;
    selecting = false;

    if (!selection || selection.width < 20 || selection.height < 12) {
        resetSelection(true);
        statusElement.textContent = 'Selection is too small. Drag around the complete supervisor signature.';
        return;
    }

    signatureX.value = (selection.x / canvas.width).toFixed(6);
    signatureY.value = (selection.y / canvas.height).toFixed(6);
    signatureWidth.value = (selection.width / canvas.width).toFixed(6);
    signatureHeight.value = (selection.height / canvas.height).toFixed(6);

    statusElement.className = 'signature-selection-status ready';
    statusElement.textContent = '✓ Supervisor signature selected';

    <?php if ($company && !empty($company['reference_signature'])): ?>
    submitButton.disabled = false;
    <?php else: ?>
    submitButton.disabled = true;
    <?php endif; ?>

    drawCanvas();
}

function clearCoordinateFields() {
    signatureX.value = '';
    signatureY.value = '';
    signatureWidth.value = '';
    signatureHeight.value = '';
}

function resetSelection(redraw = true) {
    selecting = false;
    selection = null;
    clearCoordinateFields();
    submitButton.disabled = true;
    statusElement.className = 'signature-selection-status not-ready';
    statusElement.textContent = 'No signature selected.';
    if (redraw && canvas.width > 0) drawCanvas();
}

clearButton.addEventListener('click', function () {
    resetSelection(true);
});

<?php if ($reuploadLog): ?>
updateHourPreview(<?= json_encode((float)$reuploadLog['rendered_hours']) ?>);
<?php endif; ?>

document.getElementById('weeklyLogForm').addEventListener('submit', function (event) {
    if (!signatureX.value || !signatureY.value || !signatureWidth.value || !signatureHeight.value) {
        event.preventDefault();
        alert('Please highlight the OJT supervisor signature before submitting.');
    }
});
</script>
</body>
</html>
