<?php
// session started by requireLogin()
require_once 'config.php';
require_once 'sidebar.php';
requireLogin('coordinator');

$uid = (int)$_SESSION['user_id'];

if (isset($_GET['action']) && $_GET['action'] === 'mark_notifs_read') {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
    redirect(BASE_URL . 'coordinator_docsreq.php');
}

$msg = $err = '';

// Add requirement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_req'])) {
    $name  = sanitize($conn, $_POST['doc_name']);
    $desc  = sanitize($conn, $_POST['description']);
    $reqd  = isset($_POST['is_required']) ? 1 : 0;
    if (!$name) { $err = "Document name is required."; }
    else {
        $unlock = isset($_POST['unlock_after_completion']) ? 1 : 0;
        $conn->query("
        INSERT INTO document_requirements
        (doc_name, description, is_required, unlock_after_completion, created_by)
        VALUES
        ('$name','$desc',$reqd,$unlock,$uid)
        ");
    }
}

// Edit requirement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_req'])) {
    $rid  = (int)$_POST['req_id'];
    $name = sanitize($conn, $_POST['doc_name']);
    $desc = sanitize($conn, $_POST['description']);
    $reqd = isset($_POST['is_required']) ? 1 : 0;
   $unlock = isset($_POST['unlock_after_completion']) ? 1 : 0;
    $conn->query("
    INSERT INTO document_requirements
    (doc_name, description, is_required, unlock_after_completion, created_by)
    VALUES
    ('$name','$desc',$reqd,$unlock,$uid)
    ");
    $msg = "Requirement updated!";
}

// Delete requirement
if (isset($_GET['delete_req']) && is_numeric($_GET['delete_req'])) {
    $rid = (int)$_GET['delete_req'];
    $conn->query("DELETE FROM document_requirements WHERE id=$rid");
    $msg = "Requirement deleted.";
}

// List requirements with submission counts
$reqs = $conn->query("
    SELECT dr.*,
        (SELECT COUNT(*) FROM document_submissions ds WHERE ds.requirement_id=dr.id) AS total_submissions,
        (SELECT COUNT(*) FROM document_submissions ds WHERE ds.requirement_id=dr.id AND ds.status='approved') AS approved_count,
        (SELECT COUNT(*) FROM document_submissions ds WHERE ds.requirement_id=dr.id AND ds.status='pending') AS pending_count
    FROM document_requirements dr
    ORDER BY dr.id ASC
");
$req_list = [];
while ($r = $reqs->fetch_assoc()) $req_list[] = $r;

// All document submissions for overview tab
$all_subs = $conn->query("
    SELECT ds.*, u.full_name, s.student_number, s.course, dr.doc_name
    FROM document_submissions ds
    JOIN students s ON ds.student_id=s.id
    JOIN users u ON s.user_id=u.id
    JOIN document_requirements dr ON ds.requirement_id=dr.id
    ORDER BY ds.submitted_at DESC
    LIMIT 50
");
$subs_list = [];
while ($r = $all_subs->fetch_assoc()) $subs_list[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Document Requirements – OJT Monitoring</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrapper">
<?php renderSidebar('coordinator','doc_requirements',$conn); ?>
<div class="main-content">
<?php renderTopbar('Document Requirements',$conn); ?>
<?php renderNotifPanel($conn); ?>
<div class="page-content">

<?php if ($msg): ?><div class="alert alert-success" data-auto-hide><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"  data-auto-hide><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="tabs" data-group="doctabs">
    <div class="tab active" data-tabgroup="doctabs" data-tab="requirements" onclick="switchTab('doctabs','requirements')">Requirements</div>
    <div class="tab" data-tabgroup="doctabs" data-tab="submissions" onclick="switchTab('doctabs','submissions')">All Submissions</div>
</div>

<!-- Requirements Tab -->
<div class="tab-content active" data-tabgroup="doctabs" data-tab="requirements">
<div class="grid-2">
    <!-- Add Form -->
    <div class="card">
        <div class="card-header"><h3>Add New Requirement</h3></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group"><label>Document Name</label><input type="text" name="doc_name" placeholder="e.g. Medical Certificate" required></div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="2" placeholder="Brief description…"></textarea></div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_required" checked style="width:auto;"> Required document
                    </label>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="unlock_after_completion" style="width:auto;">
                        Unlock only after OJT Progress Completion
                    </label>
                </div>
               <button type="submit" name="add_req" class="btn btn-primary btn-block">
                   + Add Requirement
                </button>
            </form>
        </div>
    </div>

    <!-- Requirements List -->
    <div class="card">
        <div class="card-header"><h3>Current Requirements (<?= count($req_list) ?>)</h3></div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($req_list)): ?>
            <div class="empty-state"><?= svgIcon('file') ?><p>No requirements added yet.</p></div>
            <?php else: foreach ($req_list as $r): ?>
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);">
                <div class="flex-between" style="margin-bottom:6px;">
                    <div>
                        <div class="font-bold text-sm"><?= htmlspecialchars($r['doc_name']) ?></div>
                        <div class="text-sm text-muted"><?= htmlspecialchars($r['description']) ?></div>
                    </div>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <span class="badge <?= $r['is_required']?'badge-rejected':'badge-info' ?>"><?= $r['is_required']?'Required':'Optional' ?></span>
                        <button class="btn btn-sm btn-secondary" 
                                onclick="openEditReq(<?= htmlspecialchars(json_encode($r)) ?>)">
                            Edit
                        </button>
                        <a href="?delete_req=<?= $r['id'] ?>" 
                        class="btn btn-sm btn-danger"
                        onclick="return confirm('Delete this requirement? Existing submissions will also be removed.')">
                        Delete
                        </a>
                    </div>
                </div>
                <div style="display:flex;gap:12px;font-size:12px;color:var(--text-muted);">
                    <span>📥 <?= $r['total_submissions'] ?> submitted</span>
                    <span>✅ <?= $r['approved_count'] ?> approved</span>
                    <span>🕐 <?= $r['pending_count'] ?> pending</span>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
</div>

<!-- Submissions Tab -->
<div class="tab-content" data-tabgroup="doctabs" data-tab="submissions">
<div class="card">
    <div class="card-header">
        <h3>All Document Submissions</h3>
        <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="subSearch" placeholder="Search…" style="padding:8px 8px 8px 32px;border:1px solid var(--border);border-radius:var(--radius);">
        </div>
    </div>
    <div class="table-wrap">
        <table id="subTable">
            <thead><tr><th>Student</th><th>Document</th><th>File</th><th>Submitted</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php if (empty($subs_list)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:32px;">No submissions yet.</td></tr>
            <?php else: foreach ($subs_list as $s): ?>
            <tr>
                <td>
                    <div class="font-bold"><?= htmlspecialchars($s['full_name']) ?></div>
                    <div class="text-sm text-muted"><?= htmlspecialchars($s['student_number']) ?></div>
                </td>
                <td class="text-sm"><?= htmlspecialchars($s['doc_name']) ?></td>
                <td>
                    <?php if ($s['file_path']): ?>
                    <a href="<?= UPLOAD_URL.$s['file_path'] ?>" target="_blank" class="btn btn-sm btn-secondary"><?= svgIcon('eye') ?> View</a>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="text-sm text-muted"><?= date('M d, Y', strtotime($s['submitted_at'])) ?></td>
                <td><span class="badge badge-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
                <td class="text-sm text-muted"><?= htmlspecialchars($s['remarks'] ?: '—') ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

</div></div></div>

<!-- Edit Requirement Modal -->
<div class="modal-overlay" id="editReqModal">
<div class="modal">
    <div class="modal-header"><h3>Edit Requirement</h3><button class="modal-close" onclick="closeModal('editReqModal')">×</button></div>
    <form method="POST">
        <input type="hidden" name="req_id" id="edit_req_id">
        <div class="modal-body">
            <div class="form-group"><label>Document Name</label><input type="text" name="doc_name" id="edit_req_name" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" id="edit_req_desc" rows="2"></textarea></div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="is_required" id="edit_req_required" style="width:auto;"> Required document
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editReqModal')">Cancel</button>
            <button type="submit" name="edit_req" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>
</div>

<script src="main.js"></script>
<script>
function openEditReq(r) {
    document.getElementById('edit_req_id').value       = r.id;
    document.getElementById('edit_req_name').value     = r.doc_name;
    document.getElementById('edit_req_desc').value     = r.description || '';
    document.getElementById('edit_req_required').checked = r.is_required == 1;
    openModal('editReqModal');
}
searchTable('subSearch','subTable');
</script>
</body>
</html>
