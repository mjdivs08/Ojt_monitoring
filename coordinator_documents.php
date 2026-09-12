<?php
require_once 'config.php';
require_once 'sidebar.php';

requireLogin('coordinator');

$coor_id = (int)$_SESSION['user_id'];

$msg = '';
$err = '';


/*
|--------------------------------------------------------------------------
| MARK NOTIFICATIONS AS READ
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'mark_notifs_read'
) {

    $conn->query("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = $coor_id
    ");

    redirect(
        BASE_URL .
        'coordinator_documents.php'
    );
}


/*
|--------------------------------------------------------------------------
| CURRENT FILTER VALUES
|--------------------------------------------------------------------------
*/

$filter =
    $_GET['filter'] ?? 'all';

$courseFilter =
    $_GET['course'] ?? 'all';

$sectionFilter =
    $_GET['section'] ?? 'all';

$documentFilter =
    $_GET['document'] ?? 'all';


/*
|--------------------------------------------------------------------------
| VALIDATE STATUS FILTER
|--------------------------------------------------------------------------
*/

$allowedFilters = [
    'all',
    'pending',
    'approved',
    'rejected'
];

if (
    !in_array(
        $filter,
        $allowedFilters,
        true
    )
) {

    $filter = 'all';
}


/*
|--------------------------------------------------------------------------
| APPROVE / REJECT DOCUMENT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['review_doc'])
) {

    $sub_id =
        (int)($_POST['sub_id'] ?? 0);

    $action =
        $_POST['doc_action'] ?? '';

    $remarks =
        sanitize(
            $conn,
            $_POST['remarks'] ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDATE ACTION
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $action,
            ['approve', 'reject'],
            true
        )
    ) {

        $err =
            "Invalid review action.";
    }


    /*
    |--------------------------------------------------------------------------
    | REQUIRE REMARKS FOR REJECTION
    |--------------------------------------------------------------------------
    */

    if (
        !$err &&
        $action === 'reject' &&
        trim($remarks) === ''
    ) {

        $err =
            "Remarks are required when rejecting a document.";
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY DOCUMENT OWNERSHIP
    |--------------------------------------------------------------------------
    */

    if (!$err) {

        $check =
            $conn->prepare("
                SELECT
                    ds.*,
                    s.user_id,
                    dr.doc_name

                FROM document_submissions ds

                JOIN students s
                    ON ds.student_id = s.id

                JOIN document_requirements dr
                    ON ds.requirement_id = dr.id

                WHERE ds.id = ?
                AND s.coordinator_id = ?

                LIMIT 1
            ");

        $check->bind_param(
            "ii",
            $sub_id,
            $coor_id
        );

        $check->execute();

        $checkResult =
            $check->get_result();


        if (
            $checkResult->num_rows === 0
        ) {

            $err =
                "Unauthorized action.";

        } else {

            $doc =
                $checkResult->fetch_assoc();


            /*
            |--------------------------------------------------------------------------
            | ONLY PENDING DOCUMENTS CAN BE REVIEWED
            |--------------------------------------------------------------------------
            */

            if (
                $doc['status'] !== 'pending'
            ) {

                $err =
                    "This document has already been reviewed.";
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE DOCUMENT
    |--------------------------------------------------------------------------
    */

    if (!$err) {

        $status =
            $action === 'approve'
            ? 'approved'
            : 'rejected';


        $update =
            $conn->prepare("
                UPDATE document_submissions

                SET
                    status = ?,
                    remarks = ?,
                    reviewed_at = NOW(),
                    reviewed_by = ?

                WHERE id = ?
                AND status = 'pending'
            ");

        $update->bind_param(
            "ssii",
            $status,
            $remarks,
            $coor_id,
            $sub_id
        );


        if ($update->execute()) {


            /*
            |--------------------------------------------------------------------------
            | NOTIFY STUDENT
            |--------------------------------------------------------------------------
            */

            if (
                $status === 'approved'
            ) {

                $title =
                    'Document Approved';

                $body =
                    'Your document "'
                    . $doc['doc_name']
                    . '" has been approved.';

                $type =
                    'success';

            } else {

                $title =
                    'Document Rejected';

                $body =
                    'Your document "'
                    . $doc['doc_name']
                    . '" was rejected. Reason: '
                    . $remarks;

                $type =
                    'error';
            }


            sendNotification(
                $conn,
                $doc['user_id'],
                $title,
                $body,
                $type
            );


            /*
            |--------------------------------------------------------------------------
            | PRESERVE FILTERS AFTER REVIEW
            |--------------------------------------------------------------------------
            */

            $redirectParams = [

                'filter' =>
                    $filter,

                'course' =>
                    $courseFilter,

                'section' =>
                    $sectionFilter,

                'document' =>
                    $documentFilter,

                'msg' =>
                    'Document ' .
                    ucfirst($status)
            ];


            redirect(
                BASE_URL
                . 'coordinator_documents.php?'
                . http_build_query(
                    $redirectParams
                )
            );


        } else {

            $err =
                "Unable to update the document.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['msg'])
) {

    $msg =
        htmlspecialchars(
            $_GET['msg']
        )
        . ' successfully!';
}


/*
|--------------------------------------------------------------------------
| COURSE OPTIONS
|--------------------------------------------------------------------------
*/

$courses = [];

$courseStmt =
    $conn->prepare("
        SELECT DISTINCT
            course

        FROM students

        WHERE coordinator_id = ?
        AND course IS NOT NULL
        AND course != ''

        ORDER BY course ASC
    ");

$courseStmt->bind_param(
    "i",
    $coor_id
);

$courseStmt->execute();

$courseResult =
    $courseStmt->get_result();

while (
    $row =
    $courseResult->fetch_assoc()
) {

    $courses[] =
        $row['course'];
}


/*
|--------------------------------------------------------------------------
| SECTION OPTIONS
|--------------------------------------------------------------------------
*/

$sections = [];

$sectionStmt =
    $conn->prepare("
        SELECT DISTINCT
            section

        FROM students

        WHERE coordinator_id = ?
        AND section IS NOT NULL
        AND section != ''

        ORDER BY section ASC
    ");

$sectionStmt->bind_param(
    "i",
    $coor_id
);

$sectionStmt->execute();

$sectionResult =
    $sectionStmt->get_result();

while (
    $row =
    $sectionResult->fetch_assoc()
) {

    $sections[] =
        $row['section'];
}


/*
|--------------------------------------------------------------------------
| DOCUMENT TYPE OPTIONS
|--------------------------------------------------------------------------
*/

$documentTypes = [];

$documentStmt =
    $conn->prepare("
        SELECT DISTINCT
            dr.id,
            dr.doc_name

        FROM document_requirements dr

        JOIN document_submissions ds
            ON ds.requirement_id = dr.id

        JOIN students s
            ON ds.student_id = s.id

        WHERE s.coordinator_id = ?

        ORDER BY dr.doc_name ASC
    ");

$documentStmt->bind_param(
    "i",
    $coor_id
);

$documentStmt->execute();

$documentResult =
    $documentStmt->get_result();

while (
    $row =
    $documentResult->fetch_assoc()
) {

    $documentTypes[] =
        $row;
}


/*
|--------------------------------------------------------------------------
| BUILD FILTERED DOCUMENT QUERY
|--------------------------------------------------------------------------
*/

$whereParts = [];

$whereParts[] =
    "s.coordinator_id = ?";

$params = [];

$types = "i";

$params[] =
    $coor_id;


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if (
    $filter !== 'all'
) {

    $whereParts[] =
        "ds.status = ?";

    $types .= "s";

    $params[] =
        $filter;
}


/*
|--------------------------------------------------------------------------
| COURSE FILTER
|--------------------------------------------------------------------------
*/

if (
    $courseFilter !== 'all'
) {

    $whereParts[] =
        "s.course = ?";

    $types .= "s";

    $params[] =
        $courseFilter;
}


/*
|--------------------------------------------------------------------------
| SECTION FILTER
|--------------------------------------------------------------------------
*/

if (
    $sectionFilter !== 'all'
) {

    $whereParts[] =
        "s.section = ?";

    $types .= "s";

    $params[] =
        $sectionFilter;
}


/*
|--------------------------------------------------------------------------
| DOCUMENT TYPE FILTER
|--------------------------------------------------------------------------
*/

if (
    $documentFilter !== 'all' &&
    ctype_digit(
        (string)$documentFilter
    )
) {

    $whereParts[] =
        "dr.id = ?";

    $types .= "i";

    $params[] =
        (int)$documentFilter;
}


$whereSql =
    implode(
        " AND ",
        $whereParts
    );


/*
|--------------------------------------------------------------------------
| GET FILTERED DOCUMENT SUBMISSIONS
|--------------------------------------------------------------------------
*/

$docsStmt =
    $conn->prepare("
        SELECT
            ds.*,

            u.full_name,

            s.student_number,
            s.course,
            s.section,

            dr.doc_name,
            dr.description

        FROM document_submissions ds

        JOIN students s
            ON ds.student_id = s.id

        JOIN users u
            ON s.user_id = u.id

        JOIN document_requirements dr
            ON ds.requirement_id = dr.id

        WHERE
            $whereSql

        ORDER BY
            ds.submitted_at DESC
    ");


/*
|--------------------------------------------------------------------------
| DYNAMIC BIND PARAMETERS
|--------------------------------------------------------------------------
*/

$bindParams = [];

$bindParams[] =
    &$types;

foreach (
    $params as $key => $value
) {

    $bindParams[] =
        &$params[$key];
}


call_user_func_array(
    [
        $docsStmt,
        'bind_param'
    ],
    $bindParams
);


$docsStmt->execute();

$docsResult =
    $docsStmt->get_result();

$doc_list = [];

while (
    $row =
    $docsResult->fetch_assoc()
) {

    $doc_list[] =
        $row;
}


/*
|--------------------------------------------------------------------------
| SUMMARY COUNTS
|--------------------------------------------------------------------------
*/

$counts = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];


$countStmt =
    $conn->prepare("
        SELECT
            ds.status,
            COUNT(*) AS total

        FROM document_submissions ds

        JOIN students s
            ON ds.student_id = s.id

        WHERE
            s.coordinator_id = ?

        GROUP BY
            ds.status
    ");

$countStmt->bind_param(
    "i",
    $coor_id
);

$countStmt->execute();

$countResult =
    $countStmt->get_result();

while (
    $row =
    $countResult->fetch_assoc()
) {

    if (
        isset(
            $counts[
                $row['status']
            ]
        )
    ) {

        $counts[
            $row['status']
        ] =
            (int)$row['total'];
    }
}


/*
|--------------------------------------------------------------------------
| STATUS FILTER URL
|--------------------------------------------------------------------------
*/

function documentFilterUrl(
    $status,
    $course,
    $section,
    $document
) {

    return
        '?'
        . http_build_query([
            'filter' =>
                $status,

            'course' =>
                $course,

            'section' =>
                $section,

            'document' =>
                $document
        ]);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Documents Review - OJT Monitoring
    </title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>


<body>

<div class="wrapper">


    <?php

    renderSidebar(
        'coordinator',
        'documents',
        $conn
    );

    ?>


    <div class="main-content">


        <?php

        renderTopbar(
            'Documents Review',
            $conn
        );

        ?>


        <?php

        renderNotifPanel(
            $conn
        );

        ?>


        <div class="page-content">


            <!-- ========================================================= -->
            <!-- MESSAGES -->
            <!-- ========================================================= -->

            <?php if ($msg): ?>

                <div
                    class="alert alert-success"
                    data-auto-hide
                >

                    <?= htmlspecialchars(
                        $msg
                    ) ?>

                </div>

            <?php endif; ?>


            <?php if ($err): ?>

                <div
                    class="alert alert-danger"
                    data-auto-hide
                >

                    <?= htmlspecialchars(
                        $err
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- ========================================================= -->
            <!-- SUMMARY CARDS -->
            <!-- ========================================================= -->

            <div class="stats-grid mb-20">


                <div class="stat-card">

                    <div class="stat-icon yellow">

                        <?= svgIcon(
                            'clock'
                        ) ?>

                    </div>

                    <div class="stat-info">

                        <div class="value">

                            <?= $counts[
                                'pending'
                            ] ?>

                        </div>

                        <div class="label">
                            Pending Review
                        </div>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-icon green">

                        <?= svgIcon(
                            'check'
                        ) ?>

                    </div>

                    <div class="stat-info">

                        <div class="value">

                            <?= $counts[
                                'approved'
                            ] ?>

                        </div>

                        <div class="label">
                            Approved
                        </div>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-icon red">

                        <?= svgIcon(
                            'warning'
                        ) ?>

                    </div>

                    <div class="stat-info">

                        <div class="value">

                            <?= $counts[
                                'rejected'
                            ] ?>

                        </div>

                        <div class="label">
                            Rejected
                        </div>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-icon blue">

                        <?= svgIcon(
                            'file'
                        ) ?>

                    </div>

                    <div class="stat-info">

                        <div class="value">

                            <?= array_sum(
                                $counts
                            ) ?>

                        </div>

                        <div class="label">
                            Total Submissions
                        </div>

                    </div>

                </div>


            </div>


            <!-- ========================================================= -->
            <!-- STATUS FILTER BUTTONS -->
            <!-- ========================================================= -->

            <div
                style="
                    display:flex;
                    gap:8px;
                    margin-bottom:15px;
                    flex-wrap:wrap;
                "
            >


                <a
                    href="<?= documentFilterUrl(
                        'all',
                        $courseFilter,
                        $sectionFilter,
                        $documentFilter
                    ) ?>"
                    class="btn <?= $filter === 'all'
                        ? 'btn-primary'
                        : 'btn-secondary' ?>"
                >

                    All
                    (<?= array_sum(
                        $counts
                    ) ?>)

                </a>


                <a
                    href="<?= documentFilterUrl(
                        'pending',
                        $courseFilter,
                        $sectionFilter,
                        $documentFilter
                    ) ?>"
                    class="btn <?= $filter === 'pending'
                        ? 'btn-primary'
                        : 'btn-secondary' ?>"
                >

                    Pending
                    (<?= $counts[
                        'pending'
                    ] ?>)

                </a>


                <a
                    href="<?= documentFilterUrl(
                        'approved',
                        $courseFilter,
                        $sectionFilter,
                        $documentFilter
                    ) ?>"
                    class="btn <?= $filter === 'approved'
                        ? 'btn-primary'
                        : 'btn-secondary' ?>"
                >

                    Approved
                    (<?= $counts[
                        'approved'
                    ] ?>)

                </a>


                <a
                    href="<?= documentFilterUrl(
                        'rejected',
                        $courseFilter,
                        $sectionFilter,
                        $documentFilter
                    ) ?>"
                    class="btn <?= $filter === 'rejected'
                        ? 'btn-primary'
                        : 'btn-secondary' ?>"
                >

                    Rejected
                    (<?= $counts[
                        'rejected'
                    ] ?>)

                </a>


            </div>


            <!-- ========================================================= -->
            <!-- AUTOMATIC FILTERS -->
            <!-- ========================================================= -->

            <div class="card mb-20">

                <div class="card-body">


                    <form
                        method="GET"
                        id="documentFilterForm"
                        style="
                            display:flex;
                            gap:12px;
                            align-items:flex-end;
                            flex-wrap:wrap;
                        "
                    >


                        <input
                            type="hidden"
                            name="filter"
                            value="<?= htmlspecialchars(
                                $filter
                            ) ?>"
                        >


                        <!-- COURSE -->

                        <div
                            class="form-group"
                            style="
                                margin-bottom:0;
                                min-width:180px;
                            "
                        >

                            <label>
                                Course
                            </label>


                            <select
                                name="course"
                                id="courseFilter"
                            >

                                <option value="all">
                                    All Courses
                                </option>


                                <?php
                                foreach (
                                    $courses
                                    as $course
                                ):
                                ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $course
                                        ) ?>"
                                        <?= $courseFilter === $course
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= htmlspecialchars(
                                            $course
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- SECTION -->

                        <div
                            class="form-group"
                            style="
                                margin-bottom:0;
                                min-width:180px;
                            "
                        >

                            <label>
                                Section
                            </label>


                            <select
                                name="section"
                                id="sectionFilter"
                            >

                                <option value="all">
                                    All Sections
                                </option>


                                <?php
                                foreach (
                                    $sections
                                    as $section
                                ):
                                ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $section
                                        ) ?>"
                                        <?= $sectionFilter === $section
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= htmlspecialchars(
                                            $section
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- DOCUMENT TYPE -->

                        <div
                            class="form-group"
                            style="
                                margin-bottom:0;
                                min-width:220px;
                            "
                        >

                            <label>
                                Document Type
                            </label>


                            <select
                                name="document"
                                id="documentTypeFilter"
                            >

                                <option value="all">
                                    All Document Types
                                </option>


                                <?php
                                foreach (
                                    $documentTypes
                                    as $document
                                ):
                                ?>

                                    <option
                                        value="<?= (int)$document[
                                            'id'
                                        ] ?>"
                                        <?= (string)$documentFilter ===
                                            (string)$document['id']
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= htmlspecialchars(
                                            $document[
                                                'doc_name'
                                            ]
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                    </form>


                </div>

            </div>


            <!-- ========================================================= -->
            <!-- DOCUMENT SUBMISSIONS -->
            <!-- ========================================================= -->

            <div class="card">


                <div
                    class="card-header"
                    style="
                        display:flex;
                        justify-content:space-between;
                        align-items:center;
                        gap:10px;
                        flex-wrap:wrap;
                    "
                >


                    <h3>

                        Document Submissions
                        (<?= count(
                            $doc_list
                        ) ?>)

                    </h3>


                    <!-- SEARCH -->

                    <div class="search-wrap">


                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >

                            <circle
                                cx="11"
                                cy="11"
                                r="8"
                            />

                            <line
                                x1="21"
                                y1="21"
                                x2="16.65"
                                y2="16.65"
                            />

                        </svg>


                        <input
                            type="text"
                            id="docSearch"
                            placeholder="Search student or document..."
                            style="
                                padding:8px 8px 8px 32px;
                                border:1px solid var(--border);
                                border-radius:var(--radius);
                            "
                        >


                    </div>


                </div>


                <?php if (empty($doc_list)): ?>


                    <div class="empty-state">

                        <?= svgIcon(
                            'file'
                        ) ?>

                        <p>
                            No documents found for the selected filters.
                        </p>

                    </div>
                <?php else: ?>
                    <div class="table-wrap document-submissions-scroll">
                        <table id="docTable">
                            <thead>
                                <tr>
                                    <th> Student </th>
                                    <th> Course </th>
                                    <th> Section </th>
                                    <th> Document </th>
                                    <th> File </th>
                                    <th> Submitted </th>
                                    <th> Status </th>
                                    <th> Remarks </th>
                                    <th> Actions </th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            foreach (
                                $doc_list
                                as $d
                            ):
                            ?>
                                <tr>
                                    <!-- STUDENT -->
                                    <td>
                                        <div class="font-bold">

                                            <?= htmlspecialchars(
                                                $d[
                                                    'full_name'
                                                ]
                                            ) ?>

                                        </div>
                                        <div
                                            class="
                                                text-sm
                                                text-muted
                                            "
                                        >

                                            <?= htmlspecialchars(
                                                $d[
                                                    'student_number'
                                                ]
                                            ) ?>

                                        </div>

                                    </td>
                                    <!-- COURSE -->

                                    <td>

                                        <?= htmlspecialchars(
                                            $d['course']
                                            ?: '—'
                                        ) ?>

                                    </td>
                                    <!-- SECTION -->
                                    <td>

                                        <?= htmlspecialchars(
                                            $d['section']
                                            ?: '—'
                                        ) ?>

                                    </td>
                                    <!-- DOCUMENT -->
                                    <td>
                                        <div
                                            class="
                                                font-bold
                                                text-sm
                                            "
                                        >
                                            <?= htmlspecialchars(
                                                $d[
                                                    'doc_name'
                                                ]
                                            ) ?>

                                        </div>


                                        <div
                                            class="
                                                text-sm
                                                text-muted
                                            "
                                        >

                                            <?= htmlspecialchars(
                                                $d[
                                                    'description'
                                                ]
                                                ?? ''
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- FILE -->

                                    <td>


                                        <?php
                                        if (
                                            !empty(
                                                $d[
                                                    'file_path'
                                                ]
                                            )
                                        ):
                                        ?>


                                            <?php

                                            $ext =
                                                strtolower(
                                                    pathinfo(
                                                        $d[
                                                            'file_path'
                                                        ],
                                                        PATHINFO_EXTENSION
                                                    )
                                                );

                                            ?>


                                            <a
                                                href="<?= UPLOAD_URL .
                                                    $d[
                                                        'file_path'
                                                    ] ?>"
                                                target="_blank"
                                                class="
                                                    btn
                                                    btn-sm
                                                    btn-secondary
                                                "
                                            >

                                                <?= svgIcon(
                                                    'eye'
                                                ) ?>

                                                <?= strtoupper(
                                                    $ext
                                                ) ?>

                                            </a>


                                        <?php else: ?>


                                            <span
                                                class="
                                                    text-muted
                                                    text-sm
                                                "
                                            >

                                                No file

                                            </span>


                                        <?php endif; ?>


                                    </td>


                                    <!-- SUBMITTED -->

                                    <td
                                        class="
                                            text-sm
                                            text-muted
                                        "
                                    >

                                        <?= date(
                                            'M d, Y',
                                            strtotime(
                                                $d[
                                                    'submitted_at'
                                                ]
                                            )
                                        ) ?>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span
                                            class="badge badge-<?= htmlspecialchars(
                                                $d[
                                                    'status'
                                                ]
                                            ) ?>"
                                        >

                                            <?= ucfirst(
                                                htmlspecialchars(
                                                    $d[
                                                        'status'
                                                    ]
                                                )
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- REMARKS -->

                                    <td
                                        class="
                                            text-sm
                                            text-muted
                                        "
                                        style="
                                            max-width:180px;
                                        "
                                    >

                                        <?= htmlspecialchars(
                                            $d['remarks']
                                            ?: '—'
                                        ) ?>

                                    </td>


                                    <!-- ACTION -->

                                    <td>


                                        <?php
                                        if (
                                            $d['status']
                                            === 'pending'
                                        ):
                                        ?>


                                            <button
                                                type="button"
                                                class="
                                                    btn
                                                    btn-sm
                                                    btn-success
                                                "
                                                onclick="openDocReview(
                                                    <?= (int)$d['id'] ?>,
                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $d[
                                                                'full_name'
                                                            ]
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>,
                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $d[
                                                                'doc_name'
                                                            ]
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>,
                                                    'approve'
                                                )"
                                            >

                                                ✓ Approve

                                            </button>


                                            <button
                                                type="button"
                                                class="
                                                    btn
                                                    btn-sm
                                                    btn-danger
                                                "
                                                style="
                                                    margin-top:4px;
                                                "
                                                onclick="openDocReview(
                                                    <?= (int)$d['id'] ?>,
                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $d[
                                                                'full_name'
                                                            ]
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>,
                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $d[
                                                                'doc_name'
                                                            ]
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>,
                                                    'reject'
                                                )"
                                            >

                                                ✗ Reject

                                            </button>


                                        <?php else: ?>


                                            <span
                                                class="badge <?= $d['status'] === 'approved'
                                                    ? 'bg-success'
                                                    : 'bg-danger' ?>"
                                            >

                                                <?= ucfirst(
                                                    htmlspecialchars(
                                                        $d[
                                                            'status'
                                                        ]
                                                    )
                                                ) ?>

                                            </span>


                                        <?php endif; ?>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                <?php endif; ?>


            </div>


        </div>
        <!-- END PAGE CONTENT -->


    </div>
    <!-- END MAIN CONTENT -->


</div>
<!-- END WRAPPER -->


<!-- ========================================================= -->
<!-- DOCUMENT REVIEW MODAL -->
<!-- ========================================================= -->

<div
    class="modal-overlay"
    id="docReviewModal"
>

    <div class="modal">


        <div class="modal-header">

            <h3 id="docModalTitle">
                Review Document
            </h3>


            <button
                type="button"
                class="modal-close"
                onclick="closeModal('docReviewModal')"
            >

                ×

            </button>

        </div>


        <form method="POST">


            <input
                type="hidden"
                name="sub_id"
                id="doc_sub_id"
            >


            <input
                type="hidden"
                name="doc_action"
                id="doc_action_val"
            >


            <div class="modal-body">


                <p
                    class="
                        text-muted
                        text-sm
                    "
                    id="docModalDesc"
                    style="
                        margin-bottom:14px;
                    "
                ></p>


                <div class="form-group">

                    <label>

                        Remarks

                        <span
                            class="
                                text-sm
                                text-muted
                            "
                        >

                            (required for rejection)

                        </span>

                    </label>


                    <textarea
                        name="remarks"
                        id="doc_remarks"
                        rows="3"
                        placeholder="Enter remarks..."
                    ></textarea>

                </div>


            </div>


            <div class="modal-footer">


                <button
                    type="button"
                    class="
                        btn
                        btn-secondary
                    "
                    onclick="closeModal('docReviewModal')"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    name="review_doc"
                    class="
                        btn
                        btn-primary
                    "
                    id="docSubmitBtn"
                >

                    Submit

                </button>


            </div>


        </form>


    </div>

</div>


<script src="main.js"></script>


<script>

/*
|--------------------------------------------------------------------------
| AUTOMATIC COURSE / SECTION / DOCUMENT FILTER
|--------------------------------------------------------------------------
*/

const documentFilterForm =
    document.getElementById(
        'documentFilterForm'
    );


const courseFilter =
    document.getElementById(
        'courseFilter'
    );


const sectionFilter =
    document.getElementById(
        'sectionFilter'
    );


const documentTypeFilter =
    document.getElementById(
        'documentTypeFilter'
    );


[
    courseFilter,
    sectionFilter,
    documentTypeFilter
]
.forEach(function(filter) {

    if (filter) {

        filter.addEventListener(
            'change',
            function() {

                documentFilterForm.submit();

            }
        );
    }
});


/*
|--------------------------------------------------------------------------
| LIVE SEARCH
|--------------------------------------------------------------------------
*/

const docSearch =
    document.getElementById(
        'docSearch'
    );


if (docSearch) {

    docSearch.addEventListener(
        'keyup',
        function() {


            const keyword =
                this.value
                .toLowerCase()
                .trim();


            document
                .querySelectorAll(
                    '#docTable tbody tr'
                )
                .forEach(
                    function(row) {


                        const rowText =
                            row
                            .textContent
                            .toLowerCase();


                        row.style.display =
                            rowText.includes(
                                keyword
                            )
                            ? ''
                            : 'none';


                    }
                );


        }
    );

}


/*
|--------------------------------------------------------------------------
| DOCUMENT REVIEW MODAL
|--------------------------------------------------------------------------
*/

function openDocReview(
    subId,
    student,
    docName,
    action
) {

    document.getElementById(
        'doc_sub_id'
    ).value =
        subId;


    document.getElementById(
        'doc_action_val'
    ).value =
        action;


    document.getElementById(
        'docModalDesc'
    ).textContent =
        student
        + ' — '
        + docName;


    const remarks =
        document.getElementById(
            'doc_remarks'
        );


    remarks.value = '';


    const btn =
        document.getElementById(
            'docSubmitBtn'
        );


    const title =
        document.getElementById(
            'docModalTitle'
        );


    if (
        action === 'approve'
    ) {

        title.textContent =
            'Approve Document';


        btn.className =
            'btn btn-success';


        btn.textContent =
            '✓ Approve';


        remarks.required =
            false;


    } else {

        title.textContent =
            'Reject Document';


        btn.className =
            'btn btn-danger';


        btn.textContent =
            '✗ Reject';


        remarks.required =
            true;
    }


    openModal(
        'docReviewModal'
    );
}

</script>


</body>

</html>