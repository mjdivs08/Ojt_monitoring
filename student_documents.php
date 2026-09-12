<?php
// session started by requireLogin()
require_once 'config.php';
require_once 'sidebar.php';
requireLogin('student');

$student = getStudentByUserId($conn, $_SESSION['user_id']);
if (!$student) { echo "No student profile found."; exit; }
$sid = $student['id'];
$total_rendered = (float)getTotalRenderedHours($conn, $sid);
$required_hours = (float)$student['required_hours'];

$msg = $err = '';

if (isset($_GET['action']) && $_GET['action'] === 'mark_notifs_read') {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id={$_SESSION['user_id']}");
    redirect(BASE_URL . 'student_documents.php');
}

// Submit document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_doc'])) {
    $req_id = (int)$_POST['requirement_id'];
    $r = $conn->query("
    SELECT unlock_after_completion
    FROM document_requirements
    WHERE id = $req_id
    ")->fetch_assoc();

    if (
        $r &&
        $r['unlock_after_completion'] &&
        $total_rendered < $required_hours
    ) {
        $err = "This document cannot be uploaded until you complete your required OJT hours.";
    }
    if (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== 0) {
        $err = "Please select a file to upload.";
    } else {
        $ext = strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','pdf','doc','docx'];
        if (!in_array($ext, $allowed)) {
            $err = "File type not allowed. Use JPG, PNG, PDF, DOC, or DOCX.";
        } elseif ($_FILES['doc_file']['size'] > 5 * 1024 * 1024) {
            $err = "File size must be under 5MB.";
        } else {
            $fname = 'doc_' . $sid . '_req' . $req_id . '_' . time() . '.' . $ext;
            $upload_dir = UPLOAD_PATH . 'documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            move_uploaded_file($_FILES['doc_file']['tmp_name'], $upload_dir . $fname);
            $fpath = $conn->real_escape_string('documents/' . $fname);
            $fname_esc = $conn->real_escape_string($_FILES['doc_file']['name']);

            // Get latest submission
            $current = $conn->query("
                SELECT id, status
                FROM document_submissions
                WHERE student_id = $sid
                AND requirement_id = $req_id
                ORDER BY id DESC
                LIMIT 1
            ")->fetch_assoc();

            if ($current) {

                if ($current['status'] == 'pending') {

                    $err = "Your document is still waiting for the coordinator's review.";

                } elseif ($current['status'] == 'approved') {

                    $err = "This document has already been approved.";

                } else {

                    // Rejected → replace the latest submission
                    $conn->query("
                        UPDATE document_submissions
                        SET
                            file_name='$fname_esc',
                            file_path='$fpath',
                            status='pending',
                            remarks='',
                            submitted_at=NOW()
                        WHERE id={$current['id']}
                    ");

                    $msg = "Document re-uploaded successfully.";

                }

            } else {

                // First upload
                $conn->query("
                    INSERT INTO document_submissions
                    (student_id, requirement_id, file_name, file_path)
                    VALUES
                    ($sid, $req_id, '$fname_esc', '$fpath')
                ");

                $msg = "Document uploaded successfully.";

            }
        }
    }
}

// Check deadline
$deadline_banner = '';
if ($student['pre_deployment_deadline']) {
    $deadline = new DateTime($student['pre_deployment_deadline']);
    $now = new DateTime();
    if ($deadline > $now) {
        $diff = $now->diff($deadline);
        $deadline_banner = "Submit all documents before <strong>" . $deadline->format('F d, Y') . "</strong> ({$diff->days} days left)";
    } else {
        $deadline_banner = "⚠ Deadline passed on " . $deadline->format('F d, Y');
    }
}

$reqs = $conn->query("
SELECT
    dr.*,
    ds.id AS sub_id,
    ds.file_name,
    ds.file_path,
    ds.status AS sub_status,
    ds.remarks,
    ds.submitted_at
FROM document_requirements dr

LEFT JOIN document_submissions ds
ON ds.id = (
    SELECT id
    FROM document_submissions
    WHERE student_id = $sid
      AND requirement_id = dr.id
    ORDER BY id DESC
    LIMIT 1
)
WHERE NOT (
    dr.doc_name LIKE '%PhilHealth%'
    AND '{$student['course']}' != 'BSTM'
)
ORDER BY dr.id ASC
");
$req_list = [];
while ($row = $reqs->fetch_assoc()) $req_list[] = $row;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Documents - OJT Monitoring</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrapper">
<?php renderSidebar('student', 'documents', $conn); ?>
<div class="main-content">
<?php renderTopbar('My Documents', $conn); ?>
<?php renderNotifPanel($conn); ?>
<div class="page-content">

<?php if ($msg): ?><div class="alert alert-success" data-auto-hide><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger" data-auto-hide><?= htmlspecialchars($err) ?></div><?php endif; ?>

<?php if ($deadline_banner): ?>
<div class="deadline-banner">
    <?= svgIcon('calendar') ?>
    <div class="text"><span><?= $deadline_banner ?></span></div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Pre-Deployment Document Checklist</h3>
        <span class="text-sm text-muted">
            <?= count(array_filter($req_list, fn($r) => $r['sub_status'])) ?>/<?= count($req_list) ?> Submitted
        </span>
    </div>
    <div class="card-body" style="padding:0;">
        <?php foreach ($req_list as $req): ?>
            <?php
            $locked =
                $req['unlock_after_completion'] == 1 &&
                $total_rendered < $required_hours;
            ?>
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);">
            <div class="flex-between" style="margin-bottom:8px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-size:20px;">
                        <?php
                        if ($req['sub_status'] === 'approved') echo '✅';
                        elseif ($req['sub_status'] === 'rejected') echo '❌';
                        elseif ($req['sub_status'] === 'pending') echo '🕐';
                        else echo '⬜';
                        ?>
                    </span>
                    <div>
                        <div class="font-bold"><?= htmlspecialchars($req['doc_name']) ?></div>
                        <div class="text-sm text-muted"><?= htmlspecialchars($req['description']) ?></div>
                    </div>
                </div>
                <?php if ($locked): ?>
                <div class="text-sm text-muted" style="margin-top:6px;color:#d97706;">
                    🔒 This document will be available after completing
                    <?= number_format($required_hours) ?> required OJT hours.
                </div>
                <?php endif; ?>
                <div style="display:flex;align-items:center;gap:8px;">
                    <?php if ($locked): ?>
                        <button class="btn btn-sm btn-secondary" disabled>
                            🔒 Complete OJT First
                        </button>

                    <?php elseif ($req['sub_status'] == 'pending'): ?>

                        <button class="btn btn-sm btn-secondary" disabled>
                            Pending Review
                        </button>

                    <?php elseif ($req['sub_status'] == 'approved'): ?>

                        <span class="badge badge-approved">
                            Approved
                        </span>

                    <?php elseif ($req['sub_status'] == 'rejected'): ?>

                        <button class="btn btn-sm btn-primary"
                            onclick="openUploadModal(
                                <?= $req['id'] ?>,
                                '<?= htmlspecialchars(addslashes($req['doc_name'])) ?>'
                            )">
                            Re-upload
                        </button>

                    <?php else: ?>

                        <button class="btn btn-sm btn-primary"
                            onclick="openUploadModal(
                                <?= $req['id'] ?>,
                                '<?= htmlspecialchars(addslashes($req['doc_name'])) ?>'
                            )">
                            Upload
                        </button>
                    <?php endif; ?>
                    <?php if ($req['file_path']): ?>
                    <a href="<?= UPLOAD_URL . $req['file_path'] ?>" target="_blank" class="btn btn-sm btn-secondary"><?= svgIcon('eye') ?> View </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($req['sub_status'] === 'rejected' && $req['remarks']): ?>
            <div class="alert alert-danger" style="margin-top:8px;padding:8px 12px;font-size:12px;">
                <strong>Rejection reason:</strong> <?= htmlspecialchars($req['remarks']) ?>
            </div>
            <?php endif; ?>
            <?php if ($req['sub_status'] === 'approved'): ?>
            <div class="text-sm text-muted" style="margin-top:4px;">Submitted: <?= date('M d, Y', strtotime($req['submitted_at'])) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($req_list)): ?>
        <div class="empty-state"><?= svgIcon('file') ?><p>No document requirements set yet.</p></div>
        <?php endif; ?>
    </div>
</div>

</div>
</div>
</div>

<!-- Upload Modal -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Upload Document</h3>
            <button class="modal-close" onclick="closeModal('uploadModal')">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="requirement_id" id="modal_req_id">
                <div style="margin-bottom:14px;">
                    <strong id="modal_doc_name" style="font-size:15px;"></strong>
                </div>
                <div class="form-group">
                    <label>Select File</label>
                    <div class="file-upload-area" id="doc_upload_area">
                        <?= svgIcon('upload') ?>
                        <p>Click to upload or drag & drop</p>
                        <p class="text-sm text-muted">JPG, PNG, PDF, DOC, DOCX — Max 5MB</p>
                        <div class="file-name" id="doc_file_name"></div>
                    </div>
                    <input type="file" id="doc_file" name="doc_file" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx" style="display:none" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Cancel</button>
                <button type="submit" name="submit_doc" class="btn btn-primary">Upload Document</button>
            </div>
        </form>
    </div>
</div>

<script src="main.js"></script>
<script>
function openUploadModal(reqId, docName) {
    document.getElementById('modal_req_id').value = reqId;
    document.getElementById('modal_doc_name').textContent = docName;
    document.getElementById('doc_file_name').textContent = '';
    const preview = document.getElementById('doc_upload_area_preview');
    if (preview) preview.remove();
    openModal('uploadModal');
    setTimeout(() => initFileUpload('doc_file', 'doc_upload_area', 'doc_file_name'), 50);
}
</script>
</body>
</html>
