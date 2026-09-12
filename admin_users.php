<?php
// session started by requireLogin()
require_once 'config.php';
require_once 'sidebar.php';
requireLogin('admin');

$admin_uid = (int)$_SESSION['user_id'];

/* ── mark notifications read ── */
if (isset($_GET['action']) && $_GET['action'] === 'mark_notifs_read') {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$admin_uid");
    redirect(BASE_URL . 'admin_users.php');
}

$msg = $err = '';
$new_credentials = null;   // holds {username, plain_password} after creation

/* ════════════════════════════════════════════
   ADD USER  (admin creates accounts)
   ════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username  = trim($_POST['username'] ?? '');
    $plain_pw  = trim($_POST['password'] ?? '');
    $role      = in_array($_POST['role'],['admin','coordinator','student']) ? $_POST['role'] : 'student';
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');

    // Validations
    if (!$username || !$plain_pw || !$full_name) {
        $err = "Username, password, and full name are required.";
    } elseif (strlen($plain_pw) < 6) {
        $err = "Password must be at least 6 characters.";
    } elseif (!preg_match('/^[a-zA-Z0-9_.@-]+$/', $username)) {
    $err = "Username may only contain letters, numbers, underscores (_), and hyphens (-), and period (.) and at signs (@).";
    } else {
        $u_esc  = $conn->real_escape_string($username);
        $n_esc  = $conn->real_escape_string($full_name);
        $e_esc  = $conn->real_escape_string($email);
        $pw_hash = password_hash($plain_pw, PASSWORD_DEFAULT);

        // Duplicate username check
        $dup = $conn->query("SELECT id FROM users WHERE username='$u_esc'");
        if ($dup && $dup->num_rows > 0) {
            $err = "Username \"$username\" already exists. Choose another.";
        } else {
            $conn->begin_transaction();
            try {
                $conn->query("INSERT INTO users (username,password,role,full_name,email)
                              VALUES ('$u_esc','$pw_hash','$role','$n_esc','$e_esc')");
                $new_uid = $conn->insert_id;

                if ($role === 'student') {
                    $snum    = trim($_POST['student_number'] ?? '');
                    $course  = trim($_POST['course'] ?? '');
                    $section = trim($_POST['section'] ?? '');
                    $coor_id = (int)($_POST['coordinator_id'] ?? 0);

                    if (!$snum) $snum = 'STU-' . str_pad($new_uid, 5, '0', STR_PAD_LEFT);

                    // Duplicate student_number check
                    $sn_esc = $conn->real_escape_string($snum);
                    $sdup   = $conn->query("SELECT id FROM students WHERE student_number='$sn_esc'");
                    if ($sdup && $sdup->num_rows > 0) {
                        $conn->rollback();
                        $err = "Student number \"$snum\" already exists.";
                    } else {
                        $co_esc = $conn->real_escape_string($course);
                        $se_esc = $conn->real_escape_string($section);

                        $coor_val =
                            $coor_id
                            ? $coor_id
                            : 'NULL';


                        /*
                        |--------------------------------------------------------------------------
                        | GET DEFAULT OJT HOURS FROM SETTINGS
                        |--------------------------------------------------------------------------
                        */

                        $default_required_hours = (float)(
                            getSetting(
                                $conn,
                                'total_required_hours'
                            ) ?? 600
                        );

                        $default_weekly_hours = (float)(
                            getSetting(
                                $conn,
                                'weekly_required_hours'
                            ) ?? 40
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | CREATE STUDENT PROFILE
                        |--------------------------------------------------------------------------
                        */

                        $conn->query("
                            INSERT INTO students
                            (
                                user_id,
                                student_number,
                                course,
                                section,
                                coordinator_id,
                                required_hours,
                                weekly_required_hours
                            )
                            VALUES
                            (
                                $new_uid,
                                '$sn_esc',
                                '$co_esc',
                                '$se_esc',
                                $coor_val,
                                $default_required_hours,
                                $default_weekly_hours
                            )
                        ");
                        $conn->commit();
                        $new_credentials = ['username'=>$username,'password'=>$plain_pw,'role'=>$role,'name'=>$full_name];
                        $msg = "Account for <strong>$full_name</strong> created successfully!";
                    }
                } else {
                    $conn->commit();
                    $new_credentials = ['username'=>$username,'password'=>$plain_pw,'role'=>$role,'name'=>$full_name];
                    $msg = "Account for <strong>$full_name</strong> created successfully!";
                }
            } catch (Exception $e) {
                $conn->rollback();
                $err = "Error creating user: " . $e->getMessage();
            }
        }
    }
}

/* ════════════════════════════════════════════
   EDIT USER
   ════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $edit_id   = (int)$_POST['edit_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $n_esc = $conn->real_escape_string($full_name);
    $e_esc = $conn->real_escape_string($email);
    $conn->query("UPDATE users SET full_name='$n_esc', email='$e_esc' WHERE id=$edit_id");

    // Update student-specific fields
    if (!empty($_POST['course'])) {
        $co_esc = $conn->real_escape_string(trim($_POST['course']));
        $se_esc = $conn->real_escape_string(trim($_POST['section'] ?? ''));
        $coor   = (int)($_POST['coordinator_id'] ?? 0);
        $cval   = $coor ? $coor : 'NULL';
        $conn->query("UPDATE students SET course='$co_esc', section='$se_esc', coordinator_id=$cval WHERE user_id=$edit_id");
    }

    if (!empty($_POST['new_password'])) {
        $npw = trim($_POST['new_password']);
        if (strlen($npw) < 6) {
            $err = "New password must be at least 6 characters.";
        } else {
            $ph = password_hash($npw, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password='$ph' WHERE id=$edit_id");
            if (!$err) $msg = "User updated and password reset!";
        }
    }
    if (!$err && !$msg) $msg = "User updated successfully!";
}

/* ════════════════════════════════════════════
   RESET PASSWORD (admin sets a new known password)
   ════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_pw'])) {
    $target_id  = (int)$_POST['reset_uid'];
    $plain_new  = trim($_POST['reset_password'] ?? '');
    $target_name= $conn->real_escape_string(trim($_POST['reset_name'] ?? ''));

    if (strlen($plain_new) < 6) {
        $err = "Password must be at least 6 characters.";
    } else {
        $ph = password_hash($plain_new, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$ph' WHERE id=$target_id");
        $msg = "Password reset for <strong>$target_name</strong>.";
        $new_credentials = ['username'=>$_POST['reset_username'],'password'=>$plain_new,'role'=>'','name'=>$target_name];
    }
}

/* ════════════════════════════════════════════
   DELETE USER  (POST to avoid CSRF via GET)
   ════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $del_id = (int)$_POST['delete_id'];
    if ($del_id === $admin_uid) {
        $err = "You cannot delete your own account.";
    } else {
        $conn->query("DELETE FROM users WHERE id=$del_id");
        $msg = "User deleted.";
    }
}

/* ════════════════════════════════════════════
   FETCH LIST
   ════════════════════════════════════════════ */
$role_filter = $_GET['role'] ?? 'all';
$w = $role_filter !== 'all' ? "WHERE u.role='" . $conn->real_escape_string($role_filter) . "'" : "";

$users = $conn->query("
    SELECT u.*,
        s.student_number, s.course, s.section, s.status AS stu_status, s.id AS student_row_id,
        c.full_name AS coor_name, c.id AS coor_id
    FROM users u
    LEFT JOIN students s ON s.user_id = u.id
    LEFT JOIN users c    ON s.coordinator_id = c.id
    $w
    ORDER BY u.role ASC, u.full_name ASC
");
$user_list = [];
if ($users) while ($r = $users->fetch_assoc()) $user_list[] = $r;

$coordinators = $conn->query("SELECT id, full_name FROM users WHERE role='coordinator' ORDER BY full_name");
$coor_list = [];
if ($coordinators) while ($r = $coordinators->fetch_assoc()) $coor_list[] = $r;

// Counts per role
$cnt_admin = $conn->query("SELECT COUNT(*) c FROM users WHERE role='admin'")->fetch_assoc()['c'];
$cnt_coor  = $conn->query("SELECT COUNT(*) c FROM users WHERE role='coordinator'")->fetch_assoc()['c'];
$cnt_stu   = $conn->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management – OJT Monitoring</title>
<link rel="stylesheet" href="style.css">
<style>
.role-badge-admin       { background:#fee2e2; color:#dc2626; }
.role-badge-coordinator { background:#dbeafe; color:#2563eb; }
.role-badge-student     { background:#dcfce7; color:#16a34a; }
.creds-box {
    background: linear-gradient(135deg,#ecfdf5,#d1fae5);
    border: 1px solid #6ee7b7;
    border-radius: var(--radius);
    padding: 16px 20px;
    margin-bottom: 20px;
}
.creds-box h4 { color:#065f46; margin-bottom:10px; font-size:14px; display:flex;align-items:center;gap:8px; }
.creds-grid  { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.cred-item   { background:#fff; border-radius:6px; padding:10px 14px; }
.cred-label  { font-size:11px; font-weight:700; text-transform:uppercase; color:#6b7280; margin-bottom:3px; }
.cred-value  { font-size:15px; font-weight:700; color:#065f46; font-family:monospace; letter-spacing:.04em; }
.cred-copy   { float:right; font-size:11px; color:var(--primary); cursor:pointer; margin-top:2px; }
.modal-lg    { max-width:600px; }
</style>
</head>
<body>
<div class="wrapper">
<?php renderSidebar('admin','users',$conn); ?>
<div class="main-content">
<?php renderTopbar('User Management',$conn); ?>
<?php renderNotifPanel($conn); ?>
<div class="page-content">

<?php if ($err): ?>
<div class="alert alert-danger" data-auto-hide><?= $err ?></div>
<?php endif; ?>

<?php if ($msg && !$new_credentials): ?>
<div class="alert alert-success" data-auto-hide><?= $msg ?></div>
<?php endif; ?>

<?php /* ── Credential reveal box after create / reset ── */ ?>
<?php if ($new_credentials): ?>
<div class="creds-box">
    <h4>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;color:#059669"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        ✅ <?= $msg ?> — Share these credentials with the user:
    </h4>
    <div class="creds-grid">
        <div class="cred-item">
            <div class="cred-label">Username</div>
            <div class="cred-value" id="credUser"><?= htmlspecialchars($new_credentials['username']) ?></div>
            <span class="cred-copy" onclick="copyText('credUser')">📋 Copy</span>
        </div>
        <div class="cred-item">
            <div class="cred-label">Password</div>
            <div class="cred-value" id="credPw"><?= htmlspecialchars($new_credentials['password']) ?></div>
            <span class="cred-copy" onclick="copyText('credPw')">📋 Copy</span>
        </div>
    </div>
    <p style="font-size:11px;color:#065f46;margin-top:10px;">
        ⚠ Save or share these credentials now — the password cannot be retrieved later.
    </p>
</div>
<?php endif; ?>

<!-- Stats + Add button -->
<div class="flex-between mb-20" style="flex-wrap:wrap;gap:12px;">
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="?role=all"         class="btn <?= $role_filter==='all'?'btn-primary':'btn-secondary' ?>">All (<?= $cnt_admin+$cnt_coor+$cnt_stu ?>)</a>
        <a href="?role=admin"       class="btn <?= $role_filter==='admin'?'btn-primary':'btn-secondary' ?>">Admins (<?= $cnt_admin ?>)</a>
        <a href="?role=coordinator" class="btn <?= $role_filter==='coordinator'?'btn-primary':'btn-secondary' ?>">Coordinators (<?= $cnt_coor ?>)</a>
        <a href="?role=student"     class="btn <?= $role_filter==='student'?'btn-primary':'btn-secondary' ?>">Students (<?= $cnt_stu ?>)</a>
    </div>
    <button class="btn btn-primary" onclick="openModal('addUserModal')">
        <?= svgIcon('plus') ?> Create Account
    </button>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h3>Accounts</h3>
        <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" id="userSearch" placeholder="Search name, username, student no…"
                style="padding:8px 8px 8px 32px;border:1px solid var(--border);border-radius:var(--radius);min-width:220px;">
        </div>
    </div>
    <div class="table-wrap">
        <table id="userTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Student No.</th>
                    <th>Coordinator</th>
                    <th>Email</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($user_list)): ?>
            <tr><td colspan="9" class="text-center text-muted" style="padding:32px;">No users found.</td></tr>
            <?php else: ?>
            <?php foreach ($user_list as $i => $u): ?>
            <tr>
                <td class="text-muted text-sm"><?= $i+1 ?></td>
                <td>
                    <div class="font-bold"><?= htmlspecialchars($u['full_name']) ?></div>
                    <?php if ($u['stu_status']): ?>
                    <span class="badge badge-<?= $u['stu_status'] ?>" style="font-size:10px;"><?= ucfirst($u['stu_status']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <code style="background:var(--bg);padding:2px 7px;border-radius:4px;font-size:12px;">
                        <?= htmlspecialchars($u['username']) ?>
                    </code>
                </td>
                <td>
                    <span class="badge role-badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span>
                </td>
                <td class="text-sm"><?= htmlspecialchars($u['student_number'] ?? '—') ?></td>
                <td class="text-sm"><?= htmlspecialchars($u['coor_name'] ?? '—') ?></td>
                <td class="text-sm text-muted"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                <td class="text-sm text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">

                        <button
                            class="btn btn-sm"
                            style="background:#3b82f6;color:#fff;border:none;padding:6px 12px;border-radius:5px;font-weight:600;cursor:pointer;"
                            onclick="openEditUser(<?= htmlspecialchars(json_encode($u)) ?>)"
                            title="Edit User">
                            Edit
                        </button>

                        <button
                            class="btn btn-sm"
                            style="background:#f59e0b;color:#fff;border:none;padding:6px 12px;border-radius:5px;font-weight:600;cursor:pointer;"
                            onclick="openResetPw(
                                <?= $u['id'] ?>,
                                '<?= htmlspecialchars(addslashes($u['username'])) ?>',
                                '<?= htmlspecialchars(addslashes($u['full_name'])) ?>'
                            )"
                            title="Reset Password">
                            Reset Password
                        </button>

                        <?php if ($u['id'] !== $admin_uid): ?>
                        <form method="POST"
                            style="display:inline;"
                            onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($u['full_name'])) ?>? This cannot be undone.')">

                            <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">

                            <button
                                type="submit"
                                name="delete_user"
                                class="btn btn-sm"
                                style="background:#ef4444;color:#fff;border:none;padding:6px 12px;border-radius:5px;font-weight:600;cursor:pointer;"
                                title="Delete User">
                                Delete
                            </button>

                        </form>
                        <?php endif; ?>

                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div><!-- page-content -->
</div><!-- main-content -->
</div><!-- wrapper -->

<!-- ══════════════════════════════════════
     ADD USER MODAL
══════════════════════════════════════ -->
<div class="modal-overlay" id="addUserModal">
<div class="modal modal-lg">
    <div class="modal-header">
        <h3><?= svgIcon('plus') ?> Create New Account</h3>
        <button class="modal-close" onclick="closeModal('addUserModal')">×</button>
    </div>
    <form method="POST" id="addUserForm">
        <div class="modal-body">

            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px;color:#1e40af;">
                ℹ️ Only administrators can create accounts. Share the credentials with the user after creation.
            </div>

            <!-- Role selector -->
            <div class="form-group">
                <label>Account Role <span style="color:var(--danger)">*</span></label>
                <div style="display:flex;gap:10px;">
                    <label style="flex:1;cursor:pointer;">
                        <input type="radio" name="role" value="student" checked onchange="toggleRoleFields('student')"
                               style="width:auto;margin-right:4px;">
                        🟢 Student
                    </label>
                    <label style="flex:1;cursor:pointer;">
                        <input type="radio" name="role" value="coordinator" onchange="toggleRoleFields('coordinator')"
                               style="width:auto;margin-right:4px;">
                        🔵 Coordinator
                    </label>
                    <label style="flex:1;cursor:pointer;">
                        <input type="radio" name="role" value="admin" onchange="toggleRoleFields('admin')"
                               style="width:auto;margin-right:4px;">
                        🔴 Admin
                    </label>
                </div>
            </div>

            <!-- Common fields -->
            <div class="grid-2">
                <div class="form-group">
                    <label>Full Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="full_name" placeholder="e.g. Juan Dela Cruz" required>
                </div>
                <div class="form-group">
                    <label>Username <span style="color:var(--danger)">*</span></label>
                    <input type="text"
                            name="username"
                            pattern="[a-zA-Z0-9_-]+"
                            title="Letters, numbers, underscores (_) and hyphens (-) only, and period (.), and at signs (@)"
                            required>
                </div>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="juan@email.com">
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Password <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="password" id="newPwField" placeholder="Min 6 characters" required minlength="6">
                    <div style="margin-top:5px;">
                        <button type="button" onclick="generatePw()" class="btn btn-sm btn-secondary" style="font-size:11px;">
                            🎲 Generate
                        </button>
                    </div>
                </div>
                <div class="form-group" style="padding-top:22px;">
                    <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:10px;font-size:11px;color:#92400e;">
                        💡 The password is shown in plain text so you can share it with the user. They can change it after logging in.
                    </div>
                </div>
            </div>

            <!-- Student-only fields -->
            <div id="studentFields">
                <div style="border-top:1px solid var(--border);margin:14px 0 14px;padding-top:14px;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
                    Student Details
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Student Number</label>
                        <input type="text" name="student_number" placeholder="e.g. 2021-00001">
                    </div>
                    <div class="form-group">
                        <label>Course</label>
                        <input type="text" name="course" placeholder="e.g. BSIT">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Section</label>
                        <input type="text" name="section" placeholder="e.g. 4A">
                    </div>
                    <div class="form-group">
                        <label>Assign Coordinator</label>
                        <select name="coordinator_id">
                            <option value="">— None —</option>
                            <?php foreach ($coor_list as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
            <button type="submit" name="add_user" class="btn btn-primary">
                <?= svgIcon('plus') ?> Create Account
            </button>
        </div>
    </form>
</div>
</div>

<!-- ══════════════════════════════════════
     EDIT USER MODAL
══════════════════════════════════════ -->
<div class="modal-overlay" id="editUserModal">
<div class="modal modal-lg">
    <div class="modal-header">
        <h3><?= svgIcon('edit') ?> Edit Account</h3>
        <button class="modal-close" onclick="closeModal('editUserModal')">×</button>
    </div>
    <form method="POST">
        <input type="hidden" name="edit_id" id="edit_id">
        <div class="modal-body">
            <div class="grid-2">
                <div class="form-group">
                    <label>Full Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="full_name" id="edit_full_name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email">
                </div>
            </div>
            <!-- Student fields in edit -->
            <div id="editStudentFields" style="display:none;">
                <div style="border-top:1px solid var(--border);margin:10px 0;padding-top:14px;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">
                    Student Details
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Course</label>
                        <input type="text" name="course" id="edit_course" placeholder="e.g. BSIT">
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <input type="text" name="section" id="edit_section" placeholder="e.g. 4A">
                    </div>
                </div>
                <div class="form-group">
                    <label>Assign Coordinator</label>
                    <select name="coordinator_id" id="edit_coor">
                        <option value="">— None —</option>
                        <?php foreach ($coor_list as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Reset Password <span class="text-sm text-muted">(leave blank to keep current)</span></label>
                <input type="text" name="new_password" id="edit_pw" placeholder="Enter new password (min 6 chars)" minlength="6">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
            <button type="submit" name="edit_user" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>
</div>

<!-- ══════════════════════════════════════
     RESET PASSWORD MODAL
══════════════════════════════════════ -->
<div class="modal-overlay" id="resetPwModal">
<div class="modal">
    <div class="modal-header">
        <h3>🔑 Reset Password</h3>
        <button class="modal-close" onclick="closeModal('resetPwModal')">×</button>
    </div>
    <form method="POST">
        <input type="hidden" name="reset_uid"      id="reset_uid">
        <input type="hidden" name="reset_username" id="reset_uname">
        <input type="hidden" name="reset_name"     id="reset_name">
        <div class="modal-body">
            <p class="text-muted text-sm" id="resetDesc" style="margin-bottom:14px;"></p>
            <div class="form-group">
                <label>New Password <span style="color:var(--danger)">*</span></label>
                <input type="text" name="reset_password" id="resetPwInput"
                       placeholder="Enter new password (min 6 chars)" required minlength="6">
            </div>
            <button type="button" onclick="generateResetPw()" class="btn btn-sm btn-secondary">
                🎲 Generate Random Password
            </button>
            <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:10px;font-size:11px;color:#92400e;margin-top:12px;">
                💡 After resetting, the new password will be shown so you can share it with the user.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('resetPwModal')">Cancel</button>
            <button type="submit" name="reset_pw" class="btn btn-warning">Reset Password</button>
        </div>
    </form>
</div>
</div>

<script src="main.js"></script>
<script>
/* ── Role field toggling ── */
function toggleRoleFields(role) {
    document.getElementById('studentFields').style.display = role === 'student' ? 'block' : 'none';
}

/* ── Open edit modal ── */
function openEditUser(u) {
    document.getElementById('edit_id').value        = u.id;
    document.getElementById('edit_full_name').value = u.full_name || '';
    document.getElementById('edit_email').value     = u.email    || '';
    document.getElementById('edit_pw').value        = '';

    const sf = document.getElementById('editStudentFields');
    if (u.role === 'student') {
        sf.style.display = 'block';
        document.getElementById('edit_course').value  = u.course   || '';
        document.getElementById('edit_section').value = u.section  || '';
        const sel = document.getElementById('edit_coor');
        sel.value = u.coor_id || '';
    } else {
        sf.style.display = 'none';
    }
    openModal('editUserModal');
}

/* ── Open reset-password modal ── */
function openResetPw(uid, uname, name) {
    document.getElementById('reset_uid').value   = uid;
    document.getElementById('reset_uname').value = uname;
    document.getElementById('reset_name').value  = name;
    document.getElementById('resetDesc').textContent = 'Resetting password for: ' + name + ' (' + uname + ')';
    document.getElementById('resetPwInput').value = '';
    openModal('resetPwModal');
}

/* ── Random password generators ── */
function makePassword(len) {
    const chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#';
    let p = '';
    for (let i = 0; i < (len || 10); i++) p += chars[Math.floor(Math.random() * chars.length)];
    return p;
}
function generatePw()      { document.getElementById('newPwField').value    = makePassword(10); }
function generateResetPw() { document.getElementById('resetPwInput').value  = makePassword(10); }

/* ── Copy to clipboard ── */
function copyText(elId) {
    const text = document.getElementById(elId).textContent.trim();
    navigator.clipboard.writeText(text).then(() => showAlert('Copied to clipboard!', 'success'));
}

/* ── Table search ── */
searchTable('userSearch', 'userTable');
</script>
</body>
</html>
