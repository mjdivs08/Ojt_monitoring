<?php
// session started by requireLogin()
require_once 'config.php';
require_once 'sidebar.php';
requireLogin('coordinator');

$coor_id = (int)$_SESSION['user_id'];

if (isset($_GET['action']) && $_GET['action'] === 'mark_notifs_read') {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$coor_id");
    redirect(BASE_URL . 'coordinator_students.php');
}

$msg = $err = '';
// RESET OJT HOURS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_hours'])) {

    $sid = (int)$_POST['student_id'];
    $reason = sanitize($conn, $_POST['reset_reason'] ?? '');

    // Check ownership
    $check = $conn->query("
        SELECT user_id 
        FROM students 
        WHERE id=$sid 
        AND coordinator_id=$coor_id
    ");

    if($check && $check->num_rows > 0){

        $student = $check->fetch_assoc();

        // Remove rendered hours
        $conn->query("
            DELETE FROM weekly_logs
            WHERE student_id=$sid
        ");

        // Notify student
        sendNotification(
            $conn,
            $student['user_id'],
            "OJT Hours Reset",
            "Your rendered OJT hours were reset to 0. Reason: ".$reason,
            "warning"
        );

        redirect(BASE_URL."coordinator_students.php?view=$sid&reset=1");

    } else {
        $err = "Unauthorized action.";
    }
}

// Update student deployment settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $sid = (int)($_POST['student_id'] ?? 0);
    $company_id = !empty($_POST['company_id'])
        ? (int)$_POST['company_id']
        : null;
    $status_val = in_array(
        $_POST['status'] ?? '',
        ['pending', 'deployed', 'completed'],
        true
    ) ? $_POST['status'] : 'pending';
    $weekly_hrs = max(
        1,
        (float)($_POST['weekly_required_hours'] ?? 1)
    );
    $total_hrs = max(
        1,
        (float)($_POST['required_hours'] ?? 1)
    );
    // Validate dates
    $deploy_date = null;
    $deadline = null;
    if (
        !empty($_POST['deployment_date']) &&
        preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $_POST['deployment_date']
        )
    ) {
        $deploy_date = $_POST['deployment_date'];
    }
    if (
        !empty($_POST['pre_deployment_deadline']) &&
        preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $_POST['pre_deployment_deadline']
        )
    ) {
        $deadline = $_POST['pre_deployment_deadline'];
    }
    // Verify student belongs to coordinator
    $chk = $conn->prepare("
        SELECT id, user_id, company_name, status
        FROM students
        WHERE id = ?
        AND coordinator_id = ?
    ");
    $chk->bind_param(
        "ii",
        $sid,
        $coor_id
    );
    $chk->execute();
    $studentResult = $chk->get_result();
    if ($studentResult->num_rows === 0) {
        $err = "Unauthorized: this student is not assigned to you.";
    } else {
        $stu_row = $studentResult->fetch_assoc();
        /*
        |--------------------------------------------------------------------------
        | Verify selected company
        |--------------------------------------------------------------------------
        */
        $verified_company_name = null;
        if ($company_id) {
            $companyStmt = $conn->prepare("
                SELECT id, company_name
                FROM companies
                WHERE id = ?
                AND is_active = 1
                LIMIT 1
            ");
            $companyStmt->bind_param(
                "i",
                $company_id
            );
            $companyStmt->execute();
            $companyResult =
                $companyStmt->get_result();
            if ($companyResult->num_rows === 0) {

                $err = "Selected company could not be found.";
            } else {
                $companyData =
                    $companyResult->fetch_assoc();

                $verified_company_name =
                    $companyData['company_name'];
            }
        }
        /*
        |--------------------------------------------------------------------------
        | MOA CHECK BEFORE DEPLOYMENT
        |--------------------------------------------------------------------------
        */
        if (!$err && $status_val === 'deployed') {
            if (!$company_id) {
                $err =
                    "Please verify the student's company before deployment.";
            } else {
                $moaStmt = $conn->prepare("
                    SELECT id
                    FROM company_moa
                    WHERE company_id = ?
                    AND effective_date <= CURDATE()
                    AND expiration_date >= CURDATE()
                    ORDER BY expiration_date DESC
                    LIMIT 1
                ");
                $moaStmt->bind_param(
                    "i",
                    $company_id
                );
                $moaStmt->execute();
                $moaResult =
                    $moaStmt->get_result();
                if ($moaResult->num_rows === 0) {

                    $err =
                        "Deployment cannot continue because "
                        . "the selected company does not have "
                        . "an active MOA.";

                }
            }
        }
        /*
        |--------------------------------------------------------------------------
        | SAVE
        |--------------------------------------------------------------------------
        */

        if (!$err) {
            /*
             * If coordinator selected an official company,
             * use its official company name.
             *
             * Otherwise retain whatever company the student
             * originally entered.
             */
            $company_name =
                $verified_company_name
                ?? $stu_row['company_name'];
            $update = $conn->prepare("
                UPDATE students
                SET
                    company_name = ?,
                    company_id = ?,
                    deployment_date = ?,
                    pre_deployment_deadline = ?,
                    status = ?,
                    weekly_required_hours = ?,
                    required_hours = ?
                WHERE id = ?
                AND coordinator_id = ?
            ");
            $update->bind_param(
                "sisssddii",
                $company_name,
                $company_id,
                $deploy_date,
                $deadline,
                $status_val,
                $weekly_hrs,
                $total_hrs,
                $sid,
                $coor_id
            );
            if ($update->execute()) {
                if ($deploy_date) {
                    sendNotification(
                        $conn,
                        $stu_row['user_id'],
                        'Deployment Date Set',
                        "Your deployment date has been set to "
                        . date(
                            'F d, Y',
                            strtotime($deploy_date)
                        )
                        . (
                            $deadline
                            ? ". Deadline for documents: "
                                . date(
                                    'F d, Y',
                                    strtotime($deadline)
                                )
                                . "."
                            : "."
                        ),
                        'info'
                    );
                }
                redirect(
                    BASE_URL
                    . 'coordinator_students.php?view='
                    . $sid
                    . '&updated=1'
                );
            } else {
                $err = "Unable to update student information.";
            }
        }
    }
}
if (isset($_GET['updated'])) $msg = "Student information updated successfully!";
if(isset($_GET['reset']))
$msg = "Student OJT hours have been reset successfully.";

$weekly_required = (float)getSetting($conn, 'weekly_required_hours');
// Official partner companies
$company_options = [];

$company_q = $conn->query("
    SELECT id, company_name
    FROM companies
    WHERE is_active = 1
    ORDER BY company_name ASC
");

if ($company_q) {
    while ($company_row = $company_q->fetch_assoc()) {
        $company_options[] = $company_row;
    }
}

$students_q = $conn->query("
    SELECT s.*, u.full_name, u.email, u.username,
        COALESCE((SELECT SUM(wl.rendered_hours) FROM weekly_logs wl WHERE wl.student_id=s.id AND wl.status!='rejected'),0) as total_rendered,
        (SELECT COUNT(*) FROM weekly_logs wl WHERE wl.student_id=s.id) as total_weeks,
        (SELECT COUNT(*) FROM document_submissions ds WHERE ds.student_id=s.id AND ds.status='approved') as docs_approved,
        (SELECT COUNT(*) FROM document_submissions ds WHERE ds.student_id=s.id) as docs_submitted
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.coordinator_id=$coor_id
    ORDER BY u.full_name ASC
");
$student_list = [];
while ($row = $students_q->fetch_assoc()) $student_list[] = $row;

// Get doc requirements count
$doc_req_count = $conn->query("SELECT COUNT(*) as c FROM document_requirements WHERE is_required=1")->fetch_assoc()['c'];

// View single student detail
$view_student = null;
$view_weeks = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $vsid = (int)$_GET['view'];
    $vs_q = $conn->query("SELECT s.*, u.full_name, u.email, u.username FROM students s JOIN users u ON s.user_id=u.id WHERE s.id=$vsid AND s.coordinator_id=$coor_id");
    if ($vs_q && $vs_q->num_rows > 0) {
        $view_student = $vs_q->fetch_assoc();
        $vw_q = $conn->query("SELECT * FROM weekly_logs WHERE student_id=$vsid ORDER BY week_number ASC");
        while ($row = $vw_q->fetch_assoc()) $view_weeks[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Students - OJT Monitoring</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrapper">
<?php renderSidebar('coordinator', 'students', $conn); ?>
<div class="main-content">
<?php renderTopbar('My Students', $conn); ?>
<?php renderNotifPanel($conn); ?>
<div class="page-content">

<?php if ($msg): ?><div class="alert alert-success" data-auto-hide><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger" data-auto-hide><?= $err ?></div><?php endif; ?>

<?php if ($view_student): ?>
<!-- Student Detail View -->
<div style="margin-bottom:16px;">
    <a href="coordinator_students.php" class="btn btn-secondary btn-sm">← Back to Students</a>
</div>
<div class="grid-2 mb-20">
    <div class="card">
        <div class="card-header"><h3><?= htmlspecialchars($view_student['full_name']) ?></h3></div>
        <div class="card-body">
            <?php
            $vt = getTotalRenderedHours($conn, $view_student['id']);
            $vr = $view_student['required_hours'];
            $vpct = $vr > 0 ? min(100, round(($vt/$vr)*100)) : 0;
            $deficit = 0;
            foreach ($view_weeks as $vw) {
                $d = $vw['rendered_hours'] - $view_student['weekly_required_hours'];
                if ($d < 0 && $vw['status'] !== 'rejected') $deficit += $d;
            }
            ?>
            
            <div style="display:flex;flex-direction:column;gap:10px;">
                <div class="flex-between"><span class="text-muted text-sm">Student No.</span><strong><?= $view_student['student_number'] ?></strong></div>
                <div class="flex-between"><span class="text-muted text-sm">Course</span><strong><?= $view_student['course'] ?></strong></div>
                <div class="flex-between"><span class="text-muted text-sm">Section</span><strong><?= $view_student['section'] ?></strong></div>
                <div class="flex-between"><span class="text-muted text-sm">Company</span><strong><?= $view_student['company_name'] ?: '—' ?></strong></div>
                <div class="flex-between"><span class="text-muted text-sm">Status</span><span class="badge badge-<?= $view_student['status'] ?>"><?= ucfirst($view_student['status']) ?></span></div>
                <div class="flex-between"><span class="text-muted text-sm">Total Rendered</span><strong class="text-success"><?= number_format($vt,1) ?> hrs</strong></div>
                <div class="flex-between"><span class="text-muted text-sm">Required</span><strong><?= number_format($vr,1) ?> hrs</strong></div>
                <div class="flex-between"><span class="text-muted text-sm">Cumulative Deficit</span><strong class="<?= $deficit < 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($deficit,1) ?> hrs</strong></div>
                <div style="padding-top:8px;">
                    <div class="progress-wrap"><div class="progress-bar" style="width:<?= $vpct ?>%"></div></div>
                    <div class="text-sm text-muted" style="margin-top:4px;"><?= $vpct ?>% completed</div>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3>Update Student Settings</h3></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="student_id" value="<?= $view_student['id'] ?>">
                <div class="form-group">
    <label>Company Applied / Organization</label>
    <?php if (!empty($view_student['company_name'])): ?>
        <div style="
            background:#f8fafc;
            border:1px solid #e2e8f0;
            padding:10px 12px;
            border-radius:8px;
            margin-bottom:10px;
        ">
            <small class="text-muted">Student entered:</small><br>
            <strong>
                <?= htmlspecialchars($view_student['company_name']) ?>
            </strong>
        </div>
    <?php endif; ?>
    <label>Verified Company</label>
    <select name="company_id">
        <option value="">-- Not Yet Verified --</option>
        <?php foreach ($company_options as $company): ?>
            <option value="<?= (int)$company['id'] ?>"
                <?= (int)($view_student['company_id'] ?? 0) === (int)$company['id'] ? 'selected' : '' ?> >
                <?= htmlspecialchars($company['company_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
                    <div class="form-group"><label>Weekly Target Hrs</label><input type="number" name="weekly_required_hours" step="0.5" value="<?= $view_student['weekly_required_hours'] ?>"></div>
                    <div class="form-group"><label>Total Required Hrs</label><input type="number" name="required_hours" step="1" value="<?= $view_student['required_hours'] ?>"></div>
                </div>
                <div class="form-group"><label>Deployment Date</label><input type="date" name="deployment_date" value="<?= $view_student['deployment_date'] ?>"></div>
                <div class="form-group"><label>Doc Submission Deadline</label><input type="date" name="pre_deployment_deadline" value="<?= $view_student['pre_deployment_deadline'] ?>"></div>
                <div class="form-group"><label>Status</label>
                    <select name="status">
                        <option value="pending" <?= $view_student['status']==='pending'?'selected':'' ?>>Pending</option>
                        <option value="deployed" <?= $view_student['status']==='deployed'?'selected':'' ?>>Deployed</option>
                        <option value="completed" <?= $view_student['status']==='completed'?'selected':'' ?>>Completed</option>
                    </select>
                </div>
                <button type="submit" name="update_student" class="btn btn-primary btn-block">Save Changes</button>
            </form>

           <form method="POST" 
                onsubmit="return confirm('Reset this student rendered hours back to 0?');">
                <input type="hidden" 
                name="student_id" 
                value="<?= $view_student['id'] ?>">
                <div class="form-group">
                <label>Reset Reason</label>
                <textarea name="reset_reason" 
                required
                placeholder="Example: Violation of company rules"></textarea>
                </div>
                <button type="submit" 
                name="reset_hours"
                class="btn btn-danger btn-block">
                Reset Rendered Hours
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Weekly Performance -->
<div class="card">
    <div class="card-header"><h3>Weekly Performance Log</h3></div>
    <?php if (empty($view_weeks)): ?>
    <div class="empty-state"><?= svgIcon('clock') ?><p>No weekly logs submitted yet.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Week</th><th>Period</th><th>Rendered</th><th>Target</th><th>+/- Hrs</th><th>DTR</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($view_weeks as $vw): ?>
            <?php $d = $vw['rendered_hours'] - $view_student['weekly_required_hours']; ?>
            <tr>
                <td>Week <?= $vw['week_number'] ?></td>
                <td class="text-sm text-muted"><?= date('M d', strtotime($vw['week_start'])) ?> - <?= date('M d, Y', strtotime($vw['week_end'])) ?></td>
                <td><strong><?= number_format($vw['rendered_hours'],1) ?> hrs</strong></td>
                <td class="text-muted"><?= number_format($view_student['weekly_required_hours'],1) ?> hrs</td>
                <td><span class="<?= $d>=0?'hours-positive':'hours-negative' ?>"><?= $d>=0?'+':'' ?><?= number_format($d,1) ?> hrs</span></td>
                <td><?php if ($vw['dtr_photo']): ?><a href="<?= UPLOAD_URL.$vw['dtr_photo'] ?>" target="_blank" class="btn btn-sm btn-secondary"><?= svgIcon('eye') ?> View</a><?php else: ?><span class="text-muted text-sm">None</span><?php endif; ?></td>
                <td><span class="badge badge-<?= $vw['status'] ?>"><?= ucfirst($vw['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- Students List -->
<div class="card">
    <div class="card-header">
        <h3>All Students (<?= count($student_list) ?>)</h3>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">

            <!-- Search -->
            <div class="search-wrap">
                <input type="text" id="searchInput" placeholder="Search students...">
            </div>

            <!-- Course Filter -->
            <select id="courseFilter">
                <option value="">All Courses</option>
                <?php
                $courses = array_unique(array_column($student_list, 'course'));
                sort($courses);
                foreach($courses as $course):
                ?>
                    <option value="<?= htmlspecialchars($course) ?>">
                        <?= htmlspecialchars($course) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Section Filter -->
            <select id="sectionFilter">
                <option value="">All Sections</option>
                <?php
                $sections = array_unique(array_column($student_list, 'section'));
                sort($sections);
                foreach($sections as $section):
                ?>
                    <option value="<?= htmlspecialchars($section) ?>">
                        <?= htmlspecialchars($section) ?>
                    </option>
                <?php endforeach; ?>
            </select>

        </div>
    </div>
    <div class="table-wrap">
        <table id="studentsTable">
            <thead><tr><th>Student</th><th>Course</th><th>Section</th><th>Company</th><th>Progress</th><th>Deficit</th><th>Doc Status</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
        <?php if (empty($student_list)): ?>
            <tr>
                <td colspan="9" class="text-center text-muted" style="padding:32px;">
                    No students assigned.
                </td>
            </tr>
        <?php else: ?>
        <?php foreach ($student_list as $s): ?>

        <?php
        $pct = $s['required_hours'] > 0
            ? min(100, round(($s['total_rendered'] / $s['required_hours']) * 100))
            : 0;

        $def_q = $conn->query("
                SELECT rendered_hours
                FROM weekly_logs
                WHERE student_id={$s['id']}
                AND status!='rejected'
                ORDER BY week_number ASC
            ");

            $runningDeficit = 0;

            while ($dr = $def_q->fetch_assoc()) {

                $difference = $dr['rendered_hours'] - $s['weekly_required_hours'];

                $runningDeficit += $difference;

                // Once recovered, deficit becomes zero
                if ($runningDeficit > 0) {
                    $runningDeficit = 0;
                }
            }

            $def = $runningDeficit;
        ?>

        <tr
            data-course="<?= htmlspecialchars($s['course']) ?>"
            data-section="<?= htmlspecialchars($s['section']) ?>"
        >

            <!-- 1. Student -->
            <td>
                <div class="font-bold"><?= htmlspecialchars($s['full_name']) ?></div>
                <div class="text-sm text-muted"><?= htmlspecialchars($s['student_number']) ?></div>
            </td>

            <!-- 2. Course -->
            <td class="text-sm">
                <strong><?= htmlspecialchars($s['course']) ?></strong>
            </td>

            <!-- 3. Section -->
            <td class="text-sm">
                <small class="text-muted"><?= htmlspecialchars($s['section']) ?></small>
            </td>

            <!-- 4. Company -->
            <td class="text-sm">
                <?= htmlspecialchars($s['company_name'] ?: '—') ?>
            </td>

            <!-- 5. Progress -->
            <td style="min-width:120px;">
                <div class="progress-wrap" style="margin-bottom:3px;">
                    <div class="progress-bar <?= $pct>=100?'green':($pct>=50?'':'yellow') ?>"
                        style="width:<?= $pct ?>%">
                    </div>
                </div>
                <div class="text-sm text-muted">
                    <?= number_format($s['total_rendered'],1) ?> /
                    <?= number_format($s['required_hours'],0) ?> hrs
                    (<?= $pct ?>%)
                </div>
            </td>

            <!-- 6. Deficit -->
            <td>
                <span class="<?= $def < 0 ? 'hours-negative' : 'hours-positive' ?>">
                    <?= $def < 0 ? '' : '+' ?><?= number_format($def,1) ?> hrs
                </span>
            </td>

            <!-- 7. Doc Status -->
            <td class="text-sm">
                <?= $s['docs_approved'] ?>/<?= $doc_req_count ?> approved
            </td>

            <!-- 8. Status -->
            <td>
                <span class="badge badge-<?= $s['status'] ?>">
                    <?= ucfirst($s['status']) ?>
                </span>
            </td>

            <!-- 9. Actions -->
            <td>
                <a href="?view=<?= $s['id'] ?>" class="btn btn-sm btn-primary">
                    <?= svgIcon('eye') ?> View
                </a>
            </td>

        </tr>

        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
</div>
</div>
</div>
<script src="main.js"></script>
<script>
searchTable('searchInput', 'studentsTable');

const courseFilter = document.getElementById('courseFilter');
const sectionFilter = document.getElementById('sectionFilter');

function filterStudents() {
    const selectedCourse = courseFilter.value.toLowerCase();
    const selectedSection = sectionFilter.value.toLowerCase();

    const rows = document.querySelectorAll('#studentsTable tbody tr');

    rows.forEach(row => {
        const course = (row.dataset.course || '').toLowerCase();
        const section = (row.dataset.section || '').toLowerCase();

        const courseMatch =
            !selectedCourse || course === selectedCourse;

        const sectionMatch =
            !selectedSection || section === selectedSection;

        row.style.display =
            courseMatch && sectionMatch ? '' : 'none';
    });
}
function openResetModal(id){

    document.getElementById('reset_student_id').value=id;

    openModal('resetModal');

}

courseFilter.addEventListener('change', filterStudents);
sectionFilter.addEventListener('change', filterStudents);
</script>

</body>
</html>
