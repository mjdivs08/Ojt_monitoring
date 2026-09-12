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
    redirect(BASE_URL . 'weekly_logs.php');
}
/*
|--------------------------------------------------------------------------
| APPROVE / REJECT WEEKLY LOG
|--------------------------------------------------------------------------
|
| IMPORTANT:
| - Reviews the exact weekly_logs.id selected by the coordinator.
| - Only pending logs can be reviewed.
| - The database update is verified before a notification is sent.
| - Notification is sent only after the status is successfully committed.
|
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['review_log'])
) {
    $log_id = (int)($_POST['log_id'] ?? 0);
    $action = trim((string)($_POST['log_action'] ?? ''));
    $remarks = sanitize(
        $conn,
        $_POST['remarks'] ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | VALIDATE REQUEST
    |--------------------------------------------------------------------------
    */
    if ($log_id <= 0) {
        $err = "Invalid weekly log.";
    } elseif (
        !in_array(
            $action,
            ['approve', 'reject'],
            true
        )
    ) {
        $err = "Invalid review action.";
    } elseif (
        $action === 'reject' &&
        trim($remarks) === ''
    ) {
        $err = "Remarks are required when rejecting a weekly log.";
    }

    $log = null;

    /*
    |--------------------------------------------------------------------------
    | VERIFY EXACT LOG + COORDINATOR OWNERSHIP
    |--------------------------------------------------------------------------
    */
    if (!$err) {
        $check = $conn->prepare("
            SELECT
                wl.id AS log_id,
                wl.student_id,
                wl.week_number,
                wl.rendered_hours,
                wl.status,
                s.user_id,
                s.weekly_required_hours
            FROM weekly_logs wl
            INNER JOIN students s
                ON s.id = wl.student_id
            WHERE wl.id = ?
              AND s.coordinator_id = ?
            LIMIT 1
        ");

        if (!$check) {
            $err = "Unable to verify weekly log: " . $conn->error;
        } else {
            $check->bind_param(
                "ii",
                $log_id,
                $coor_id
            );

            if (!$check->execute()) {
                $err = "Unable to verify weekly log: " . $check->error;
            } else {
                $checkResult = $check->get_result();

                if (
                    !$checkResult ||
                    $checkResult->num_rows === 0
                ) {
                    $err = "Weekly log not found or you are not authorized to review it.";
                } else {
                    $log = $checkResult->fetch_assoc();
                    $currentStatus = strtolower(
                        trim(
                            (string)$log['status']
                        )
                    );

                    if ($currentStatus !== 'pending') {
                        $err = "This weekly log has already been reviewed.";
                    }
                }
            }

            $check->close();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE EXACT WEEKLY LOG
    |--------------------------------------------------------------------------
    */
    if (!$err && $log) {
        $status =
            $action === 'approve'
            ? 'approved'
            : 'rejected';

        /*
        |--------------------------------------------------------------------------
        | TRANSACTION
        |--------------------------------------------------------------------------
        */
        $conn->begin_transaction();

        try {
            $update = $conn->prepare("
                UPDATE weekly_logs
                SET
                    status = ?,
                    coordinator_remarks = ?,
                    reviewed_at = NOW(),
                    reviewed_by = ?
                WHERE id = ?
                  AND student_id = ?
                  AND status = 'pending'
                LIMIT 1
            ");

            if (!$update) {
                throw new Exception(
                    "Unable to prepare weekly log update: "
                    . $conn->error
                );
            }

            $studentId = (int)$log['student_id'];

            $update->bind_param(
                "ssiii",
                $status,
                $remarks,
                $coor_id,
                $log_id,
                $studentId
            );

            if (!$update->execute()) {
                $updateError = $update->error;
                $update->close();

                throw new Exception(
                    "Unable to update weekly log: "
                    . $updateError
                );
            }

            $affectedRows = $update->affected_rows;
            $update->close();

            /*
            |--------------------------------------------------------------------------
            | EXACTLY ONE ROW MUST CHANGE
            |--------------------------------------------------------------------------
            */
            if ($affectedRows !== 1) {
                throw new Exception(
                    "The weekly log was not updated. It may already have been reviewed. Please refresh the page and try again."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | VERIFY SAVED STATUS BEFORE COMMIT
            |--------------------------------------------------------------------------
            */
            $verify = $conn->prepare("
                SELECT
                    status,
                    coordinator_remarks,
                    reviewed_at,
                    reviewed_by
                FROM weekly_logs
                WHERE id = ?
                  AND student_id = ?
                LIMIT 1
            ");

            if (!$verify) {
                throw new Exception(
                    "Unable to verify saved review: "
                    . $conn->error
                );
            }

            $verify->bind_param(
                "ii",
                $log_id,
                $studentId
            );

            if (!$verify->execute()) {
                $verifyError = $verify->error;
                $verify->close();

                throw new Exception(
                    "Unable to verify saved review: "
                    . $verifyError
                );
            }

            $verifyResult = $verify->get_result();
            $savedReview =
                $verifyResult &&
                $verifyResult->num_rows > 0
                ? $verifyResult->fetch_assoc()
                : null;

            $verify->close();

            if (
                !$savedReview ||
                strtolower(
                    trim(
                        (string)$savedReview['status']
                    )
                ) !== $status
            ) {
                throw new Exception(
                    "The review could not be confirmed in the database."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | COMMIT FIRST
            |--------------------------------------------------------------------------
            */
            $conn->commit();

            /*
            |--------------------------------------------------------------------------
            | NOTIFY STUDENT ONLY AFTER SUCCESSFUL DATABASE UPDATE
            |--------------------------------------------------------------------------
            */
            $notif_title =
                $status === 'approved'
                ? 'Weekly Log Approved'
                : 'Weekly Log Rejected';

            if ($status === 'approved') {
                $notif_msg =
                    "Your Week "
                    . (int)$log['week_number']
                    . " log ("
                    . number_format(
                        (float)$log['rendered_hours'],
                        1
                    )
                    . " hrs) has been approved.";

                $notif_type = 'success';
            } else {
                $notif_msg =
                    "Your Week "
                    . (int)$log['week_number']
                    . " log was rejected. Reason: "
                    . $remarks;

                $notif_type = 'error';
            }

            sendNotification(
                $conn,
                (int)$log['user_id'],
                $notif_title,
                $notif_msg,
                $notif_type
            );

            /*
            |--------------------------------------------------------------------------
            | DEFICIT NOTIFICATION FOR APPROVED LOGS
            |--------------------------------------------------------------------------
            */
            if (
                $status === 'approved' &&
                (float)$log['rendered_hours']
                <
                (float)$log['weekly_required_hours']
            ) {
                $deficit =
                    (float)$log['weekly_required_hours']
                    -
                    (float)$log['rendered_hours'];

                sendNotification(
                    $conn,
                    (int)$log['user_id'],
                    'Hour Deficit Noted',
                    "Week "
                    . (int)$log['week_number']
                    . ": You are "
                    . number_format(
                        $deficit,
                        1
                    )
                    . " hrs short of your weekly target.",
                    'warning'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | PRESERVE CURRENT FILTERS
            |--------------------------------------------------------------------------
            */
            $redirectParams = [
                'filter' =>
                    $_GET['filter'] ?? 'all',

                'week' =>
                    $_GET['week'] ?? 'all',

                'course' =>
                    $_GET['course'] ?? 'all',

                'section' =>
                    $_GET['section'] ?? 'all',

                'msg' =>
                    ucfirst($status)
            ];

            redirect(
                BASE_URL
                . 'weekly_logs.php?'
                . http_build_query(
                    $redirectParams
                )
            );

        } catch (Throwable $e) {
            $conn->rollback();

            $err = $e->getMessage();
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
| FILTER VALUES
|--------------------------------------------------------------------------
*/
$filter =
    $_GET['filter']
    ?? 'all';
$weekFilter =
    $_GET['week']
    ?? 'all';
$courseFilter =
    $_GET['course']
    ?? 'all';
$sectionFilter =
    $_GET['section']
    ?? 'all';
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
| GET WEEK OPTIONS
|--------------------------------------------------------------------------
*/
$weeks = [];
$weeksQuery =
    $conn->prepare("
        SELECT DISTINCT
            wl.week_number
        FROM weekly_logs wl
        JOIN students s
            ON wl.student_id = s.id
        WHERE
            s.coordinator_id = ?
        ORDER BY
            wl.week_number ASC
    ");
$weeksQuery->bind_param(
    "i",
    $coor_id
);
$weeksQuery->execute();
$weeksResult =
    $weeksQuery->get_result();
while (
    $row =
    $weeksResult->fetch_assoc()
) {
    $weeks[] =
        (int)$row['week_number'];
}
/*
|--------------------------------------------------------------------------
| GET COURSE OPTIONS
|--------------------------------------------------------------------------
*/
$courses = [];
$courseQuery =
    $conn->prepare("
        SELECT DISTINCT
            course
        FROM students
        WHERE
            coordinator_id = ?
        AND course IS NOT NULL
        AND course != ''
        ORDER BY
            course ASC
    ");
$courseQuery->bind_param(
    "i",
    $coor_id
);
$courseQuery->execute();
$courseResult =
    $courseQuery->get_result();
while (
    $row =
    $courseResult->fetch_assoc()
) {
    $courses[] =
        $row['course'];
}
/*
|--------------------------------------------------------------------------
| GET SECTION OPTIONS
|--------------------------------------------------------------------------
*/
$sections = [];
$sectionQuery =
    $conn->prepare("
        SELECT DISTINCT
            section
        FROM students
        WHERE
            coordinator_id = ?
        AND section IS NOT NULL
        AND section != ''
        ORDER BY
            section ASC
    ");
$sectionQuery->bind_param(
    "i",
    $coor_id
);
$sectionQuery->execute();
$sectionResult =
    $sectionQuery->get_result();
while (
    $row =
    $sectionResult->fetch_assoc()
) {
    $sections[] =
        $row['section'];
}
/*
|--------------------------------------------------------------------------
| BUILD WHERE CLAUSE
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
        "wl.status = ?";
    $types .= "s";
    $params[] =
        $filter;
}
/*
|--------------------------------------------------------------------------
| WEEK FILTER
|--------------------------------------------------------------------------
*/
if (
    $weekFilter !== 'all' &&
    ctype_digit(
        (string)$weekFilter
    )
) {
    $whereParts[] =
        "wl.week_number = ?";
    $types .= "i";
    $params[] =
        (int)$weekFilter;
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
$whereSql =
    implode(
        " AND ",
        $whereParts
    );
/*
|--------------------------------------------------------------------------
| GET FILTERED WEEKLY LOGS
|--------------------------------------------------------------------------
*/
$logsStmt =
    $conn->prepare("
        SELECT
            wl.*,
            wl.id AS log_id,
            u.full_name,
            s.student_number,
            s.course,
            s.section,
            s.weekly_required_hours
        FROM weekly_logs wl

        INNER JOIN (
            SELECT
                student_id,
                week_number,
                MAX(id) AS latest_id
            FROM weekly_logs
            GROUP BY
                student_id,
                week_number
        ) latest
            ON latest.latest_id = wl.id

        INNER JOIN students s
            ON wl.student_id = s.id

        INNER JOIN users u
            ON s.user_id = u.id

        WHERE
            $whereSql

        ORDER BY
            wl.week_number DESC,
            wl.submitted_at DESC,
            wl.id DESC
    ");
/*
|--------------------------------------------------------------------------
| DYNAMIC BIND
|--------------------------------------------------------------------------
*/
if (
    !empty($params)
) {
    $bindParams = [];
    $bindParams[] =
        &$types;
    foreach (
        $params
        as $key => $value
    ) {
        $bindParams[] =
            &$params[$key];
    }
    call_user_func_array(
        [
            $logsStmt,
            'bind_param'
        ],
        $bindParams
    );
}
$logsStmt->execute();
$logsResult =
    $logsStmt->get_result();
$logs = [];
while (
    $row =
    $logsResult->fetch_assoc()
) {
    $logs[] = $row;
}
/*
|--------------------------------------------------------------------------
| STATUS BUTTON URL
|--------------------------------------------------------------------------
*/
function weeklyFilterUrl(
    $status,
    $week,
    $course,
    $section
) {
    return
        '?'
        . http_build_query(
            [
                'filter' =>
                    $status,

                'week' =>
                    $week,

                'course' =>
                    $course,

                'section' =>
                    $section
            ]
        );
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
        Weekly Logs - OJT Monitoring
    </title>
    <link
        rel="stylesheet"
        href="style.css"
    >

    <style>
        .signature-score-btn {
            border: none;
            min-width: 92px;
            border-radius: 9px;
            padding: 6px 9px;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            line-height: 1.2;
        }
        .signature-score-btn strong { font-size: 13px; }
        .signature-score-btn span { font-size: 10px; margin-top: 2px; }
        .signature-high { background: #dcfce7; color: #166534; }
        .signature-medium { background: #fef3c7; color: #92400e; }
        .signature-low { background: #fee2e2; color: #991b1b; }
        .signature-compare-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 15px;
        }
        .signature-compare-card {
            background: #f8fafc;
            border: 1px solid #dbe4ee;
            border-radius: 10px;
            padding: 10px;
            text-align: center;
        }
        .signature-compare-card img {
            display: block;
            width: 100%;
            height: 140px;
            object-fit: contain;
            background: #fff;
            border-radius: 7px;
        }
        .signature-large-score {
            margin-top: 15px;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            background: #f8fafc;
        }
        .signature-large-score strong {
            display: block;
            font-size: 28px;
            color: #0f172a;
        }
        @media (max-width: 650px) {
            .signature-compare-grid { grid-template-columns: 1fr; }
        }
    </style>

</head>
<body>
<div class="wrapper">
    <?php
    renderSidebar(
        'coordinator',
        'weekly_logs',
        $conn
    );
    ?>
    <div class="main-content">
        <?php
        renderTopbar(
            'Weekly Logs Review',
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
            <!-- SUCCESS MESSAGE -->
            <!-- ========================================================= -->
            <?php if ($msg): ?>
                <div
                    class="alert alert-success"
                    data-auto-hide
                >
                    <?= $msg ?>
                </div>
            <?php endif; ?>
            <!-- ========================================================= -->
            <!-- ERROR MESSAGE -->
            <!-- ========================================================= -->
            <?php if ($err): ?>
                <div
                    class="alert alert-danger"
                >
                    <?= htmlspecialchars(
                        $err
                    ) ?>
                </div>
            <?php endif; ?>
            <!-- ========================================================= -->
            <!-- STATUS BUTTONS -->
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
                    href="<?= weeklyFilterUrl(
                        'all',
                        $weekFilter,
                        $courseFilter,
                        $sectionFilter
                    ) ?>"
                    class="btn <?= $filter === 'all'
                        ? 'btn-primary'
                        : 'btn-secondary' ?>"
                >
                    All Logs
                </a>
                <a
                    href="<?= weeklyFilterUrl(
                        'pending',
                        $weekFilter,
                        $courseFilter,
                        $sectionFilter
                    ) ?>"
                    class="btn <?= $filter === 'pending'
                        ? 'btn-primary'
                        : 'btn-secondary' ?>"
                >
                    Pending
                </a>
                <a
                    href="<?= weeklyFilterUrl(
                        'approved',
                        $weekFilter,
                        $courseFilter,
                        $sectionFilter
                    ) ?>"
                    class="btn <?= $filter === 'approved'
                        ? 'btn-primary'
                        : 'btn-secondary' ?>"
                >
                    Approved
                </a>
                <a
                    href="<?= weeklyFilterUrl(
                        'rejected',
                        $weekFilter,
                        $courseFilter,
                        $sectionFilter
                    ) ?>"
                    class="btn <?= $filter === 'rejected'
                        ? 'btn-primary'
                        : 'btn-secondary' ?>"
                >
                    Rejected
                </a>
            </div>
            <!-- ========================================================= -->
            <!-- AUTOMATIC FILTERS -->
            <!-- ========================================================= -->
            <div class="card mb-20">
                <div class="card-body">
                    <form
                        method="GET"
                        id="weeklyFilterForm"
                        style="
                            display:flex;
                            gap:12px;
                            align-items:flex-end;
                            flex-wrap:wrap;
                        "
                    >
                        <!-- KEEP CURRENT STATUS -->

                        <input
                            type="hidden"
                            name="filter"
                            value="<?= htmlspecialchars(
                                $filter
                            ) ?>"
                        >


                        <!-- WEEK FILTER -->

                        <div
                            class="form-group"
                            style="
                                margin-bottom:0;
                                min-width:150px;
                            "
                        >

                            <label>
                                Week
                            </label>


                            <select
                                name="week"
                                id="weekFilter"
                            >

                                <option value="all">

                                    All Weeks

                                </option>


                                <?php
                                foreach (
                                    $weeks
                                    as $week
                                ):
                                ?>

                                    <option
                                        value="<?= $week ?>"
                                        <?= (string)$weekFilter === (string)$week
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        Week <?= $week ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- COURSE FILTER -->

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


                        <!-- SECTION FILTER -->

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

                    </form>


                </div>

            </div>


            <!-- ========================================================= -->
            <!-- WEEKLY LOGS -->
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

                        Weekly Logs
                        (<?= count($logs) ?>)

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
                            id="weeklySearch"
                            placeholder="Search student..."
                            style="
                                padding:
                                8px
                                8px
                                8px
                                32px;

                                border:
                                1px solid
                                var(--border);

                                border-radius:
                                var(--radius);
                            "
                        >


                    </div>


                </div>


                <?php
                if (
                    empty($logs)
                ):
                ?>


                    <div class="empty-state">

                        <?= svgIcon(
                            'clock'
                        ) ?>

                        <p>

                            No weekly logs found.

                        </p>

                    </div>


                <?php else: ?>


                    <div class="table-wrap">


                        <table
                            id="weeklyLogsTable"
                        >


                            <thead>

                                <tr>

                                   <th>Student</th>
                                    <th>Course</th>
                                    <th>Section</th>
                                    <th>Week</th>
                                    <th>Period</th>
                                    <th>Rendered</th>
                                    <th>DTR</th>
                                    <th>Signature</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>


                            <tbody>


                            <?php
                            foreach (
                                $logs
                                as $log
                            ):
                            ?>


                                <tr>


                                    <!-- STUDENT -->

                                    <td>

                                        <div class="font-bold">

                                            <?= htmlspecialchars(
                                                $log[
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
                                                $log[
                                                    'student_number'
                                                ]
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- COURSE -->

                                    <td>

                                        <?= htmlspecialchars(
                                            $log['course']
                                            ?: '—'
                                        ) ?>

                                    </td>


                                    <!-- SECTION -->

                                    <td>

                                        <?= htmlspecialchars(
                                            $log['section']
                                            ?: '—'
                                        ) ?>

                                    </td>


                                    <!-- WEEK -->

                                    <td>

                                        Week

                                        <?= (int)$log[
                                            'week_number'
                                        ] ?>

                                    </td>


                                    <!-- PERIOD -->

                                    <td
                                        class="
                                            text-sm
                                            text-muted
                                        "
                                    >

                                        <?= date(
                                            'M d',
                                            strtotime(
                                                $log[
                                                    'week_start'
                                                ]
                                            )
                                        ) ?>

                                        -

                                        <?= date(
                                            'M d, Y',
                                            strtotime(
                                                $log[
                                                    'week_end'
                                                ]
                                            )
                                        ) ?>

                                    </td>


                                    <!-- RENDERED -->

                                    <td>

                                        <strong>

                                            <?= number_format(
                                                $log[
                                                    'rendered_hours'
                                                ],
                                                1
                                            ) ?>

                                            hrs

                                        </strong>

                                    </td>


                                    <!-- DTR -->

                                    <td>


                                        <?php
                                        if (
                                            !empty(
                                                $log[
                                                    'dtr_photo'
                                                ]
                                            )
                                        ):
                                        ?>


                                            <a
                                                href="<?= UPLOAD_URL .
                                                    $log[
                                                        'dtr_photo'
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

                                                DTR

                                            </a>


                                        <?php else: ?>


                                            <span
                                                class="
                                                    text-muted
                                                    text-sm
                                                "
                                            >

                                                No photo

                                            </span>


                                        <?php endif; ?>


                                    </td>


                                    <!-- SIGNATURE VERIFICATION -->

                                    <td>

                                        <?php
                                        $signatureScore =
                                            $log['signature_similarity'] !== null
                                            ? (float)$log['signature_similarity']
                                            : null;

                                        if ($signatureScore !== null) {
                                            if ($signatureScore >= 75) {
                                                $signatureClass = 'signature-high';
                                                $signatureLabel = 'High';
                                            } elseif ($signatureScore >= 50) {
                                                $signatureClass = 'signature-medium';
                                                $signatureLabel = 'Moderate';
                                            } else {
                                                $signatureClass = 'signature-low';
                                                $signatureLabel = 'Needs Review';
                                            }
                                        }
                                        ?>

                                        <?php if ($signatureScore === null): ?>

                                            <span class="text-sm text-muted">
                                                Not Checked
                                            </span>

                                        <?php else: ?>

                                            <button
                                                type="button"
                                                class="signature-score-btn <?= $signatureClass ?>"
                                                onclick='openSignatureReview(
                                                    <?= (int)$log["id"] ?>,
                                                    <?= json_encode($log["full_name"]) ?>,
                                                    <?= (int)$log["week_number"] ?>,
                                                    <?= json_encode(number_format($signatureScore, 2)) ?>,
                                                    <?= json_encode($log["signature_result"] ?? "") ?>
                                                )'
                                            >
                                                <strong><?= number_format($signatureScore, 2) ?>%</strong>
                                                <span><?= htmlspecialchars($signatureLabel) ?></span>
                                            </button>

                                        <?php endif; ?>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span
                                            class="badge badge-<?= htmlspecialchars(
                                                $log[
                                                    'status'
                                                ]
                                            ) ?>"
                                        >

                                            <?= ucfirst(
                                                htmlspecialchars(
                                                    $log[
                                                        'status'
                                                    ]
                                                )
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- ACTION -->

                                    <td>


                                        <?php
                                        if (
                                            $log[
                                                'status'
                                            ]
                                            ===
                                            'pending'
                                        ):
                                        ?>


                                            <button
                                                type="button"
                                                class="
                                                    btn
                                                    btn-sm
                                                    btn-success
                                                "
                                                onclick="openReviewModal(
                                                    <?= (int)$log['log_id'] ?>,
                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $log[
                                                                'full_name'
                                                            ]
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>,
                                                    <?= (int)$log[
                                                        'week_number'
                                                    ] ?>,
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
                                                onclick="openReviewModal(
                                                    <?= (int)$log['log_id'] ?>,
                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $log[
                                                                'full_name'
                                                            ]
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>,
                                                    <?= (int)$log[
                                                        'week_number'
                                                    ] ?>,
                                                    'reject'
                                                )"
                                            >

                                                ✗ Reject

                                            </button>


                                        <?php else: ?>


                                            <span
                                                class="
                                                    text-sm
                                                    text-muted
                                                "
                                            >

                                                Reviewed

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
<!-- REVIEW MODAL -->
<!-- ========================================================= -->

<div
    class="modal-overlay"
    id="reviewModal"
>

    <div class="modal">


        <div class="modal-header">

            <h3
                id="reviewModalTitle"
            >

                Review Weekly Log

            </h3>


            <button
                type="button"
                class="modal-close"
                onclick="
                    closeModal(
                        'reviewModal'
                    )
                "
            >

                ×

            </button>

        </div>


        <form method="POST">


            <input
                type="hidden"
                name="log_id"
                id="review_log_id"
            >


            <input
                type="hidden"
                name="log_action"
                id="review_log_action"
            >


            <div class="modal-body">


                <p
                    class="
                        text-muted
                        text-sm
                    "
                    id="reviewModalDesc"
                    style="
                        margin-bottom:
                        14px;
                    "
                ></p>


                <div class="form-group">


                    <label>

                        Remarks / Comments

                        <span
                            class="
                                text-sm
                                text-muted
                            "
                        >

                            (optional for approval,
                            required for rejection)

                        </span>

                    </label>


                    <textarea
                        name="remarks"
                        id="review_remarks"
                        rows="3"
                        placeholder="Enter your remarks..."
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
                    onclick="
                        closeModal(
                            'reviewModal'
                        )
                    "
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    name="review_log"
                    class="
                        btn
                        btn-primary
                    "
                    id="reviewSubmitBtn"
                >

                    Submit Review

                </button>


            </div>


        </form>


    </div>

</div>




<!-- ========================================================= -->
<!-- SIGNATURE REVIEW MODAL -->
<!-- ========================================================= -->

<div class="modal-overlay" id="signatureReviewModal">
    <div class="modal" style="max-width:680px;">
        <div class="modal-header">
            <h3>Signature Verification</h3>
            <button
                type="button"
                class="modal-close"
                onclick="closeModal('signatureReviewModal')"
            >×</button>
        </div>

        <div class="modal-body">
            <div id="signatureStudentText" class="text-sm text-muted"></div>

            <div class="signature-compare-grid">
                <div class="signature-compare-card">
                    <div class="font-bold" style="margin-bottom:8px;">
                        Company Reference Signature
                    </div>
                    <img
                        src=""
                        id="referenceSignaturePreview"
                        alt="Company reference signature"
                    >
                </div>

                <div class="signature-compare-card">
                    <div class="font-bold" style="margin-bottom:8px;">
                        Selected DTR Signature
                    </div>
                    <img
                        src=""
                        id="submittedSignaturePreview"
                        alt="Submitted DTR signature"
                    >
                </div>
            </div>

            <div class="signature-large-score">
                <strong id="signatureScoreText">—</strong>
                <div id="signatureResultText" class="font-bold">—</div>
            </div>

            <div class="text-sm text-muted" style="margin-top:12px;line-height:1.5;">
                The similarity score is an automated visual comparison and serves only
                as verification assistance. The coordinator still makes the final
                approval or rejection decision.
            </div>
        </div>

        <div class="modal-footer">
            <button
                type="button"
                class="btn btn-secondary"
                onclick="closeModal('signatureReviewModal')"
            >Close</button>
        </div>
    </div>
</div>


<script src="main.js"></script>


<script>

/*
|--------------------------------------------------------------------------
| AUTOMATIC FILTER
|--------------------------------------------------------------------------
*/

const weeklyFilterForm =
    document.getElementById(
        'weeklyFilterForm'
    );


const weekFilter =
    document.getElementById(
        'weekFilter'
    );


const courseFilter =
    document.getElementById(
        'courseFilter'
    );


const sectionFilter =
    document.getElementById(
        'sectionFilter'
    );


[
    weekFilter,
    courseFilter,
    sectionFilter
]
.forEach(function(filter) {

    if (filter) {

        filter.addEventListener(
            'change',
            function() {

                weeklyFilterForm.submit();

            }
        );

    }

});

</script>


<script>

/*
|--------------------------------------------------------------------------
| REVIEW MODAL
|--------------------------------------------------------------------------
*/

function openReviewModal(
    logId,
    studentName,
    weekNum,
    action
) {

    document.getElementById(
        'review_log_id'
    ).value =
        logId;


    document.getElementById(
        'review_log_action'
    ).value =
        action;


    document.getElementById(
        'reviewModalDesc'
    ).textContent =
        'Student: '
        + studentName
        + ' — Week '
        + weekNum;


    const btn =
        document.getElementById(
            'reviewSubmitBtn'
        );


    const remarks =
        document.getElementById(
            'review_remarks'
        );


    remarks.value = '';


    if (
        action === 'approve'
    ) {

        document.getElementById(
            'reviewModalTitle'
        ).textContent =
            'Approve Weekly Log';


        btn.className =
            'btn btn-success';


        btn.textContent =
            '✓ Approve';


        remarks.required =
            false;


    } else {

        document.getElementById(
            'reviewModalTitle'
        ).textContent =
            'Reject Weekly Log';


        btn.className =
            'btn btn-danger';


        btn.textContent =
            '✗ Reject';


        remarks.required =
            true;

    }


    openModal(
        'reviewModal'
    );

}

</script>


<script>

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

const weeklySearch =
    document.getElementById(
        'weeklySearch'
    );


if (weeklySearch) {

    weeklySearch.addEventListener(
        'keyup',
        function() {


            const value =
                this.value
                .toLowerCase()
                .trim();


            document
                .querySelectorAll(
                    '#weeklyLogsTable tbody tr'
                )
                .forEach(
                    function(row) {


                        const text =
                            row
                            .textContent
                            .toLowerCase();


                        row.style.display =
                            text.includes(
                                value
                            )
                            ? ''
                            : 'none';


                    }
                );


        }
    );

}

</script>




<script>
/*
|--------------------------------------------------------------------------
| SIGNATURE REVIEW
|--------------------------------------------------------------------------
*/
function openSignatureReview(
    logId,
    studentName,
    weekNumber,
    score,
    result
) {
    document.getElementById('signatureStudentText').textContent =
        studentName + ' — Week ' + weekNumber;

    document.getElementById('signatureScoreText').textContent =
        score + '%';

    document.getElementById('signatureResultText').textContent =
        result || 'Verification result unavailable';

    const previewBase = <?= json_encode(BASE_URL . 'signature_preview.php') ?>;

    document.getElementById('referenceSignaturePreview').src =
        previewBase
        + '?id=' + encodeURIComponent(logId)
        + '&type=reference&t=' + Date.now();

    document.getElementById('submittedSignaturePreview').src =
        previewBase
        + '?id=' + encodeURIComponent(logId)
        + '&type=submitted&t=' + Date.now();

    openModal('signatureReviewModal');
}
</script>

</body>

</html>