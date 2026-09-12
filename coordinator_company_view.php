<?php
require_once 'config.php';
require_once 'sidebar.php';
requireLogin('coordinator');
$coor_id = (int)$_SESSION['user_id'];
$company_id = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

$msg = '';
$err = '';

if ($company_id <= 0) {
    die("Invalid company.");
}
/*
|--------------------------------------------------------------------------
| GET COMPANY
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT *
    FROM companies
    WHERE id = ?
    AND is_active = 1
    LIMIT 1
");
$stmt->bind_param(
    "i",
    $company_id
);
$stmt->execute();
$companyResult = $stmt->get_result();
if ($companyResult->num_rows === 0) {
    die("Company not found.");
}
$company = $companyResult->fetch_assoc();

/*
|--------------------------------------------------------------------------
| MOA STATUS FUNCTION
|--------------------------------------------------------------------------
*/
function companyMoaStatus($effective, $expiration)
{
    if (!$effective || !$expiration) {
        return 'No MOA';
    }
    $today = date('Y-m-d');

    if ($today < $effective) {
        return 'Upcoming';
    }
    if ($today > $expiration) {
        return 'Expired';
    }
    $daysRemaining = floor(
        (
            strtotime($expiration)
            - strtotime($today)
        ) / 86400
    );
    if ($daysRemaining <= 30) {
        return 'Expiring Soon';
    }
    return 'Active';
}
/*
|--------------------------------------------------------------------------
| UPLOAD / RENEW MOA
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['upload_moa'])
) {
    $effective_date =
        trim($_POST['effective_date'] ?? '');
    $expiration_date =
        trim($_POST['expiration_date'] ?? '');
    $remarks =
        trim($_POST['remarks'] ?? '');
    /*
    |--------------------------------------------------------------------------
    | VALIDATE DATES
    |--------------------------------------------------------------------------
    */
    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $effective_date
        )
    ) {
        $err = "Please enter a valid effective date.";
    } elseif (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $expiration_date
        )
    ) {
        $err = "Please enter a valid expiration date.";
    } elseif ($expiration_date < $effective_date) {

        $err =
            "Expiration date cannot be earlier "
            . "than the effective date.";
    }
    /*
    |--------------------------------------------------------------------------
    | VALIDATE FILE
    |--------------------------------------------------------------------------
    */
    if (!$err) {
        if (
            !isset($_FILES['moa_file'])
            || $_FILES['moa_file']['error']
                !== UPLOAD_ERR_OK
        ) {

            $err = "Please select an MOA file.";
        } else {
            $file = $_FILES['moa_file'];
            $extension = strtolower(
                pathinfo(
                    $file['name'],
                    PATHINFO_EXTENSION
                )
            );
            $allowed_extensions = [
                'pdf',
                'jpg',
                'jpeg',
                'png',
                'doc',
                'docx'
            ];
            if (
                !in_array(
                    $extension,
                    $allowed_extensions,
                    true
                )
            ) {
                $err =
                    "Invalid file type. "
                    . "Allowed: PDF, JPG, PNG, DOC, DOCX.";
            } elseif (
                $file['size']
                > (5 * 1024 * 1024)
            ) {
                $err =
                    "MOA file must not exceed 5MB.";
            }
        }
    }
    /*
    |--------------------------------------------------------------------------
    | SAVE FILE
    |--------------------------------------------------------------------------
    */
    if (!$err) {

        $uploadDirectory =
            __DIR__ . '/uploads/moa/';
        if (!is_dir($uploadDirectory)) {
            mkdir(
                $uploadDirectory,
                0777,
                true
            );
        }
        $newFileName =
            'moa_company_'
            . $company_id
            . '_'
            . time()
            . '.'
            . $extension;
        $destination =
            $uploadDirectory
            . $newFileName;
        if (
            move_uploaded_file(
                $file['tmp_name'],
                $destination
            )
        ) {
            $relativePath =
                'uploads/moa/'
                . $newFileName;
            /*
            |--------------------------------------------------------------------------
            | SAVE MOA RECORD
            |--------------------------------------------------------------------------
            */
            $insert = $conn->prepare("
                INSERT INTO company_moa
                (
                    company_id,
                    moa_file,
                    effective_date,
                    expiration_date,
                    remarks,
                    created_by
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert->bind_param(
                "issssi",
                $company_id,
                $relativePath,
                $effective_date,
                $expiration_date,
                $remarks,
                $coor_id
            );
            if ($insert->execute()) {
                redirect(
                    BASE_URL
                    . 'coordinator_company_view.php?id='
                    . $company_id
                    . '&uploaded=1'
                );
            } else {
                // Remove file if DB insert fails
                if (file_exists($destination)) {
                    unlink($destination);
                }
                $err =
                    "Unable to save the MOA record.";
            }
        } else {
            $err =
                "Unable to upload the MOA file.";
        }
    }
}
/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/
if (isset($_GET['uploaded'])) {

    $msg =
        "MOA uploaded successfully.";
}
/*
|--------------------------------------------------------------------------
| GET LATEST MOA
|--------------------------------------------------------------------------
*/
$latestStmt = $conn->prepare("
    SELECT *
    FROM company_moa
    WHERE company_id = ?
    ORDER BY expiration_date DESC, id DESC
    LIMIT 1
");
$latestStmt->bind_param(
    "i",
    $company_id
);
$latestStmt->execute();
$latestResult =
    $latestStmt->get_result();
$latestMoa =
    $latestResult->fetch_assoc();
/*
|--------------------------------------------------------------------------
| GET MOA HISTORY
|--------------------------------------------------------------------------
*/
$historyStmt = $conn->prepare("
    SELECT
        cm.*,
        u.full_name AS uploaded_by
    FROM company_moa cm

    LEFT JOIN users u
        ON u.id = cm.created_by

    WHERE cm.company_id = ?

    ORDER BY
        cm.expiration_date DESC,
        cm.id DESC
");
$historyStmt->bind_param(
    "i",
    $company_id
);
$historyStmt->execute();
$historyResult =
    $historyStmt->get_result();
/*
|--------------------------------------------------------------------------
| GET ASSIGNED STUDENTS
|--------------------------------------------------------------------------
*/
$studentsStmt = $conn->prepare("
    SELECT
        s.id,
        s.student_number,
        s.course,
        s.section,
        s.status,
        u.full_name
    FROM students s
    JOIN users u
        ON u.id = s.user_id
    WHERE s.company_id = ?
    ORDER BY u.full_name ASC
");
$studentsStmt->bind_param(
    "i",
    $company_id
);
$studentsStmt->execute();
$studentResult =
    $studentsStmt->get_result();
/*
|--------------------------------------------------------------------------
| CURRENT MOA STATUS
|--------------------------------------------------------------------------
*/
$currentStatus = 'No MOA';
if ($latestMoa) {
    $currentStatus =
        companyMoaStatus(
            $latestMoa['effective_date'],
            $latestMoa['expiration_date']
        );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>
        <?= htmlspecialchars($company['company_name']) ?>
        - Company MOA
    </title>
    <link
        rel="stylesheet" href="style.css" >
</head>
<body>
<div class="wrapper">
<?php
renderSidebar(
    'coordinator',
    'companies',
    $conn
);
?>
<div class="main-content">
<?php
renderTopbar(
    'Company & MOA Details',
    $conn
);
?>
<?php renderNotifPanel($conn); ?>
<div class="page-content">
    <div style="margin-bottom:16px;">
        <a href="coordinator_companies.php" class="btn btn-secondary btn-sm" > ← Back to Companies </a>
    </div>
    <?php if ($msg): ?>
        <div class="alert alert-success" data-auto-hide > <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="alert alert-danger" data-auto-hide > <?= htmlspecialchars($err) ?>
        </div>
    <?php endif; ?>
    <!-- COMPANY AND CURRENT MOA -->
    <div class="grid-2 mb-20">
        <!-- COMPANY INFORMATION -->
        <div class="card">
            <div class="card-header">
                <h3>
                    Company Information
                </h3>
            </div>
            <div class="card-body">
                <div
                    style="
                        display:flex;
                        flex-direction:column;
                        gap:12px;
                    "
                >
                    <div class="flex-between">
                        <span class="text-muted text-sm">
                            Company
                        </span>
                        <strong>
                            <?= htmlspecialchars(
                                $company['company_name']
                            ) ?>
                        </strong>
                    </div>
                    <div class="flex-between">
                        <span class="text-muted text-sm">
                            Address
                        </span>
                        <strong>
                            <?= htmlspecialchars(
                                $company['address']
                                ?: '—'
                            ) ?>
                        </strong>
                    </div>
                    <div class="flex-between">
                        <span class="text-muted text-sm">
                            Contact Person
                        </span>
                        <strong>
                            <?= htmlspecialchars(
                                $company['contact_person']
                                ?: '—'
                            ) ?>
                        </strong>
                    </div>
                    <div class="flex-between">
                        <span class="text-muted text-sm">
                            Contact Number
                        </span>
                        <strong>
                            <?= htmlspecialchars(
                                $company['contact_number']
                                ?: '—'
                            ) ?>
                        </strong>
                    </div>
                    <div class="flex-between">
                        <span class="text-muted text-sm">
                            Email
                        </span>
                        <strong>
                            <?= htmlspecialchars(
                                $company['email']
                                ?: '—'
                            ) ?>
                        </strong>
                    </div>
                </div>
            </div>
        </div>
        <!-- CURRENT MOA -->
        <div class="card">
            <div class="card-header">
                <h3>
                    Current MOA
                </h3>
            </div>
            <div class="card-body">
                <?php if (!$latestMoa): ?>
                    <div class="empty-state">
                        <p>
                            No MOA has been uploaded
                            for this company yet.
                        </p>
                    </div>
                <?php else: ?>
                    <?php
                    $statusStyle = "
                        background:#e5e7eb;
                        color:#374151;
                    ";
                    if ($currentStatus === 'Active') {
                        $statusStyle = "
                            background:#dcfce7;
                            color:#166534;
                        ";
                    } elseif (
                        $currentStatus ===
                        'Expiring Soon'
                    ) {
                        $statusStyle = "
                            background:#fef3c7;
                            color:#92400e;
                        ";
                    } elseif (
                        $currentStatus === 'Expired'
                    ) {
                        $statusStyle = "
                            background:#fee2e2;
                            color:#991b1b;
                        ";
                    } elseif (
                        $currentStatus === 'Upcoming'
                    ) {
                        $statusStyle = "
                            background:#dbeafe;
                            color:#1e40af;
                        ";
                    }
                    ?>
                    <div
                        style="
                            display:flex;
                            flex-direction:column;
                            gap:12px;
                        "
                    >
                        <div class="flex-between">
                            <span class="text-muted text-sm">
                                Status
                            </span>
                            <span
                                class="badge"
                                style="<?= $statusStyle ?>"
                            >
                                <?= htmlspecialchars(
                                    $currentStatus
                                ) ?>
                            </span>
                        </div>
                        <div class="flex-between">
                            <span class="text-muted text-sm">
                                Effective Date
                            </span>
                            <strong>
                                <?= date(
                                    'M d, Y',
                                    strtotime(
                                        $latestMoa[
                                            'effective_date'
                                        ]
                                    )
                                ) ?>
                            </strong>
                        </div>
                        <div class="flex-between">
                            <span class="text-muted text-sm">
                                Expiration Date
                            </span>
                            <strong>
                                <?= date(
                                    'M d, Y',
                                    strtotime(
                                        $latestMoa[
                                            'expiration_date'
                                        ]
                                    )
                                ) ?>
                            </strong>
                        </div>
                        <div>
                            <a href="<?= htmlspecialchars( $latestMoa['moa_file'] ) ?>" target="_blank" class="btn btn-primary btn-sm" >
                                View MOA
                            </a>
                </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- UPLOAD / RENEW MOA -->
    <div class="card mb-20">
        <div class="card-header">
            <h3>
                <?= $latestMoa
                    ? 'Renew / Upload New MOA'
                    : 'Upload MOA'
                ?>
            </h3>
        </div>
        <div class="card-body">
            <form
                method="POST"
                enctype="multipart/form-data"
            >
                <div class="form-group">
                    <label>
                        MOA File
                    </label>
                    <input
                        type="file"
                        name="moa_file"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        required
                    >
                    <small class="text-muted">
                        Maximum file size: 5MB.
                    </small>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>
                            Effective Date
                        </label>
                        <input
                            type="date"
                            name="effective_date"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label>
                            Expiration Date
                        </label>
                        <input
                            type="date"
                            name="expiration_date"
                            required
                        >
                    </div>
                </div>
                <div class="form-group">
                    <label>
                        Remarks
                    </label>
                    <textarea
                        name="remarks"
                        rows="3"
                        placeholder="Optional notes about the MOA..."
                    ></textarea>
                </div>
                <button
                    type="submit"
                    name="upload_moa"
                    class="btn btn-primary"
                >
                    <?= $latestMoa
                        ? 'Upload Renewal'
                        : 'Upload MOA'
                    ?>
                </button>
            </form>
        </div>
    </div>
    <!-- MOA HISTORY -->
    <div class="card mb-20">
        <div class="card-header">
            <h3>
                MOA History
            </h3>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Effective</th>
                        <th>Expiration</th>
                        <th>Status</th>
                        <th>Uploaded By</th>
                        <th>Remarks</th>
                        <th>File</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if (
                    $historyResult->num_rows === 0
                ):
                ?>
                    <tr>
                        <td
                            colspan="6"
                            class="text-center text-muted"
                            style="padding:32px;"
                        >
                            No MOA history.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    while (
                        $moa =
                        $historyResult->fetch_assoc()
                    ):
                    ?>
                        <?php
                        $historyStatus =
                            companyMoaStatus(
                                $moa['effective_date'],
                                $moa['expiration_date']
                            );
                        ?>
                        <tr>
                            <td>
                                <?= date(
                                    'M d, Y',
                                    strtotime(
                                        $moa[
                                            'effective_date'
                                        ]
                                    )
                                ) ?>
                            </td>
                            <td>
                                <?= date(
                                    'M d, Y',
                                    strtotime(
                                        $moa[
                                            'expiration_date'
                                        ]
                                    )
                                ) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    $historyStatus
                                ) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    $moa['uploaded_by']
                                    ?: '—'
                                ) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    $moa['remarks']
                                    ?: '—'
                                ) ?>
                            </td>
                            <td>
                                <a
                                    href="<?= htmlspecialchars(
                                        $moa['moa_file']
                                    ) ?>"
                                    target="_blank"
                                    class="btn btn-secondary btn-sm"
                                >
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- ASSIGNED STUDENTS -->
    <div class="card">
        <div class="card-header">
            <h3>
                Assigned Students
            </h3>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Student No.</th>
                        <th>Course</th>
                        <th>Section</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if (
                    $studentResult->num_rows === 0
                ):
                ?>
                    <tr>
                        <td
                            colspan="6"
                            class="text-center text-muted"
                            style="padding:32px;"
                        >
                            No students are currently
                            assigned to this company.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    while (
                        $student =
                        $studentResult->fetch_assoc()
                    ):
                    ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $student['full_name']
                                    ) ?>
                                </strong>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    $student[
                                        'student_number'
                                    ]
                                ) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    $student['course']
                                ) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars(
                                    $student['section']
                                ) ?>
                            </td>
                            <td>
                                <span
                                    class="badge badge-<?= htmlspecialchars(
                                        $student['status']
                                    ) ?>"
                                >
                                    <?= ucfirst(
                                        $student['status']
                                    ) ?>
                                </span>
                            </td>
                            <td>
                                <a
                                    href="coordinator_students.php?view=<?= (int)$student['id'] ?>"
                                    class="btn btn-primary btn-sm"
                                >
                                    View Student
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
</div>
<script src="main.js"></script>
</body>
</html>