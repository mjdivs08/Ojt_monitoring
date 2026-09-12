<?php
// Session started by requireLogin()

require_once 'config.php';
require_once 'sidebar.php';

requireLogin('coordinator');

$coor_id = (int)$_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Mark notifications as read
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'mark_notifs_read'
) {

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ?
    ");

    if ($stmt) {
        $stmt->bind_param('i', $coor_id);
        $stmt->execute();
        $stmt->close();
    }

    redirect(
        BASE_URL .
        'coordinator_notifications.php'
    );
}


/*
|--------------------------------------------------------------------------
| Mark all coordinator notifications read when page opens
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE user_id = ?
");

if ($stmt) {
    $stmt->bind_param('i', $coor_id);
    $stmt->execute();
    $stmt->close();
}


$msg = '';
$err = '';


/*
|--------------------------------------------------------------------------
| Send notification to one student
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['send_notif'])
) {

    $target_uid = isset(
        $_POST['target_user_id']
    )
        ? (int)$_POST['target_user_id']
        : 0;


    $title = trim(
        $_POST['notif_title'] ?? ''
    );


    $body = trim(
        $_POST['notif_body'] ?? ''
    );


    $type = $_POST['notif_type']
        ?? 'info';


    if (
        !in_array(
            $type,
            [
                'info',
                'warning',
                'success',
                'error'
            ],
            true
        )
    ) {
        $type = 'info';
    }


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($target_uid <= 0) {

        $err =
            'Please search and select a student.';

    } elseif ($title === '') {

        $err =
            'Notification title is required.';

    } elseif ($body === '') {

        $err =
            'Notification message is required.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Verify that the selected student belongs to this coordinator
        |--------------------------------------------------------------------------
        */

        $chk = $conn->prepare("
            SELECT s.id
            FROM students s

            INNER JOIN users u
                ON s.user_id = u.id

            WHERE u.id = ?
              AND s.coordinator_id = ?

            LIMIT 1
        ");


        if (!$chk) {

            $err =
                'Unable to verify the selected student.';

        } else {

            $chk->bind_param(
                'ii',
                $target_uid,
                $coor_id
            );


            $chk->execute();

            $result =
                $chk->get_result();


            if (
                $result &&
                $result->num_rows > 0
            ) {

                sendNotification(
                    $conn,
                    $target_uid,
                    $title,
                    $body,
                    $type
                );


                $msg =
                    'Notification sent successfully!';

            } else {

                $err =
                    'Invalid student selected.';
            }


            $chk->close();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Send bulk notifications
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['send_bulk'])
) {

    $target =
        $_POST['bulk_target']
        ?? 'all_students';


    $title =
        trim(
            $_POST['bulk_title'] ?? ''
        );


    $body =
        trim(
            $_POST['bulk_body'] ?? ''
        );


    $type =
        $_POST['bulk_type']
        ?? 'info';


    if (
        !in_array(
            $type,
            [
                'info',
                'warning',
                'success',
                'error'
            ],
            true
        )
    ) {
        $type = 'info';
    }


    if (
        !in_array(
            $target,
            [
                'all_students',
                'pending',
                'deployed'
            ],
            true
        )
    ) {
        $target =
            'all_students';
    }


    if ($title === '') {

        $err =
            'Bulk notification title is required.';

    } elseif ($body === '') {

        $err =
            'Bulk notification message is required.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT:
        | Bulk notification is limited to students assigned
        | to the currently logged-in coordinator.
        |--------------------------------------------------------------------------
        */

        $sql = "
            SELECT u.id

            FROM students s

            INNER JOIN users u
                ON s.user_id = u.id

            WHERE s.coordinator_id = ?
              AND u.role = 'student'
        ";


        if ($target === 'pending') {

            $sql .= "
                AND s.status = 'pending'
            ";

        } elseif (
            $target === 'deployed'
        ) {

            $sql .= "
                AND s.status = 'deployed'
            ";
        }


        $usersStmt =
            $conn->prepare($sql);


        if (!$usersStmt) {

            $err =
                'Unable to load students for bulk notification.';

        } else {

            $usersStmt->bind_param(
                'i',
                $coor_id
            );


            $usersStmt->execute();


            $usersResult =
                $usersStmt->get_result();


            $cnt = 0;


            if ($usersResult) {

                while (
                    $row =
                        $usersResult->fetch_assoc()
                ) {

                    sendNotification(
                        $conn,
                        (int)$row['id'],
                        $title,
                        $body,
                        $type
                    );


                    $cnt++;
                }
            }


            $usersStmt->close();


            $msg =
                'Notification sent to ' .
                $cnt .
                ' student(s)!';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load students assigned to this coordinator
|--------------------------------------------------------------------------
*/

$students = [];


$stmt = $conn->prepare("
    SELECT
        u.id,
        u.full_name,
        s.student_number

    FROM students s

    INNER JOIN users u
        ON s.user_id = u.id

    WHERE s.coordinator_id = ?

    ORDER BY
        u.full_name ASC
");


if ($stmt) {

    $stmt->bind_param(
        'i',
        $coor_id
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    if ($result) {

        while (
            $row =
                $result->fetch_assoc()
        ) {

            $students[] =
                $row;
        }
    }


    $stmt->close();
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
        Notifications – OJT Monitoring
    </title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>style.css?v=<?= time() ?>"
    >

</head>


<body>


<div class="wrapper">


    <?php
    renderSidebar(
        'coordinator',
        'notifications',
        $conn
    );
    ?>


    <div class="main-content">


        <?php
        renderTopbar(
            'Notifications',
            $conn
        );
        ?>


        <?php
        renderNotifPanel(
            $conn
        );
        ?>


        <div class="page-content">


            <!-- =========================
                 SUCCESS MESSAGE
                 ========================= -->

            <?php if ($msg !== ''): ?>

                <div
                    class="alert alert-success"
                    data-auto-hide
                >
                    <?= htmlspecialchars($msg) ?>
                </div>

            <?php endif; ?>


            <!-- =========================
                 ERROR MESSAGE
                 ========================= -->

            <?php if ($err !== ''): ?>

                <div
                    class="alert alert-danger"
                    data-auto-hide
                >
                    <?= htmlspecialchars($err) ?>
                </div>

            <?php endif; ?>



            <div class="grid-2">


                <!-- =====================================================
                     SEND NOTIFICATION TO ONE STUDENT
                     ===================================================== -->

                <div class="card">


                    <div class="card-header">

                        <h3>
                            Send Notification to Student
                        </h3>

                    </div>


                    <div class="card-body">


                        <form method="POST">


                            <!-- =========================
                                 SEARCH STUDENT
                                 ========================= -->

                            <div
                                class="form-group"
                                id="studentSearchGroup"
                            >


                                <label for="studentSearch">
                                    Search Student
                                </label>


                                <input
                                    type="text"
                                    id="studentSearch"
                                    placeholder="Type student name or student number..."
                                    autocomplete="off"
                                >


                                <!--
                                    Actual selected student's user ID.
                                    This is what gets submitted.
                                -->

                                <input
                                    type="hidden"
                                    name="target_user_id"
                                    id="selectedStudentId"
                                >


                                <!-- =========================
                                     SEARCH RESULTS
                                     ========================= -->

                                <div
                                    id="studentResults"
                                    class="student-results"
                                    style="display:none;"
                                >


                                    <?php foreach ($students as $student): ?>


                                        <div
                                            class="student-option"

                                            data-id="<?= (int)$student['id'] ?>"

                                            data-name="<?= htmlspecialchars(
                                                $student['full_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"

                                            data-number="<?= htmlspecialchars(
                                                $student['student_number'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"

                                            style="display:none;"
                                        >

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $student['full_name']
                                                ) ?>
                                            </strong>

                                            <span
                                                class="text-sm text-muted"
                                            >
                                                (
                                                <?= htmlspecialchars(
                                                    $student['student_number']
                                                ) ?>
                                                )
                                            </span>

                                        </div>


                                    <?php endforeach; ?>


                                    <!-- Shown only when nothing matches -->

                                    <div
                                        id="noStudentResults"
                                        class="text-sm text-muted"
                                        style="
                                            display:none;
                                            padding:12px;
                                            text-align:center;
                                        "
                                    >
                                        No students found.
                                    </div>


                                </div>


                                <!-- Selected student indicator -->

                                <div
                                    id="selectedStudentDisplay"
                                    class="text-sm text-success"
                                    style="
                                        display:none;
                                        margin-top:7px;
                                    "
                                >
                                </div>


                            </div>



                            <!-- =========================
                                 NOTIFICATION TYPE
                                 ========================= -->

                            <div class="form-group">

                                <label for="notif_type">
                                    Type
                                </label>


                                <select
                                    name="notif_type"
                                    id="notif_type"
                                >

                                    <option value="info">
                                        ℹ Info
                                    </option>

                                    <option value="warning">
                                        ⚠ Reminder
                                    </option>

                                    <option value="success">
                                        ✅ Complete
                                    </option>

                                    <option value="error">
                                        ❌ Error
                                    </option>

                                </select>

                            </div>



                            <!-- =========================
                                 TITLE
                                 ========================= -->

                            <div class="form-group">

                                <label for="notif_title">
                                    Title
                                </label>


                                <input
                                    type="text"
                                    id="notif_title"
                                    name="notif_title"
                                    placeholder="Notification title"
                                    maxlength="150"
                                    required
                                >

                            </div>



                            <!-- =========================
                                 MESSAGE
                                 ========================= -->

                            <div class="form-group">

                                <label for="notif_body">
                                    Message
                                </label>


                                <textarea
                                    id="notif_body"
                                    name="notif_body"
                                    rows="4"
                                    placeholder="Enter your message..."
                                    required
                                ></textarea>

                            </div>



                            <!-- =========================
                                 SEND BUTTON
                                 ========================= -->

                            <button
                                type="submit"
                                name="send_notif"
                                class="btn btn-primary btn-block"
                            >
                                Send Notification
                            </button>


                        </form>


                    </div>

                </div>



                <!-- =====================================================
                     RIGHT COLUMN
                     ===================================================== -->

                <div>


                    <!-- =================================================
                         BULK NOTIFICATIONS
                         ================================================= -->

                    <div class="card mb-20">


                        <div class="card-header">

                            <h3>
                                Bulk Notifications
                            </h3>

                        </div>


                        <div class="card-body">


                            <form method="POST">


                                <!-- SEND TO -->

                                <div class="form-group">

                                    <label for="bulk_target">
                                        Send To
                                    </label>


                                    <select
                                        name="bulk_target"
                                        id="bulk_target"
                                    >

                                        <option
                                            value="all_students"
                                        >
                                            All My Students
                                        </option>

                                        <option
                                            value="pending"
                                        >
                                            Pending Students Only
                                        </option>

                                        <option
                                            value="deployed"
                                        >
                                            Deployed Students Only
                                        </option>

                                    </select>

                                </div>



                                <!-- TYPE -->

                                <div class="form-group">

                                    <label for="bulk_type">
                                        Type
                                    </label>


                                    <select
                                        name="bulk_type"
                                        id="bulk_type"
                                    >

                                        <option value="info">
                                            ℹ Info
                                        </option>

                                        <option value="warning">
                                            ⚠ Reminder
                                        </option>

                                        <option value="success">
                                            ✅ Complete
                                        </option>

                                        <option value="error">
                                            ❌ Error
                                        </option>

                                    </select>

                                </div>



                                <!-- TITLE -->

                                <div class="form-group">

                                    <label for="bulk_title">
                                        Title
                                    </label>


                                    <input
                                        type="text"
                                        id="bulk_title"
                                        name="bulk_title"
                                        placeholder="Notification title"
                                        maxlength="150"
                                        required
                                    >

                                </div>



                                <!-- MESSAGE -->

                                <div class="form-group">

                                    <label for="bulk_body">
                                        Message
                                    </label>


                                    <textarea
                                        id="bulk_body"
                                        name="bulk_body"
                                        rows="4"
                                        placeholder="Notification message..."
                                        required
                                    ></textarea>

                                </div>



                                <!-- SEND -->

                                <button
                                    type="submit"
                                    name="send_bulk"
                                    class="btn btn-primary btn-block"
                                >
                                    Send Bulk Notification
                                </button>


                            </form>


                        </div>

                    </div>


                </div>


            </div>


        </div>

    </div>

</div>



<!-- =========================================================
     MAIN JAVASCRIPT
     ========================================================= -->

<script
    src="<?= BASE_URL ?>main.js?v=<?= time() ?>"
></script>



<!-- =========================================================
     STUDENT SEARCH JAVASCRIPT
     ========================================================= -->

<script>

const searchInput =
    document.getElementById(
        'studentSearch'
    );

const resultsBox =
    document.getElementById(
        'studentResults'
    );

const hiddenInput =
    document.getElementById(
        'selectedStudentId'
    );

const noResults =
    document.getElementById(
        'noStudentResults'
    );

const selectedDisplay =
    document.getElementById(
        'selectedStudentDisplay'
    );

const searchGroup =
    document.getElementById(
        'studentSearchGroup'
    );

const studentOptions =
    document.querySelectorAll(
        '.student-option'
    );


/*
|--------------------------------------------------------------------------
| Hide all results when page first loads
|--------------------------------------------------------------------------
*/

function hideAllStudentResults() {

    resultsBox.style.display =
        'none';


    noResults.style.display =
        'none';


    studentOptions.forEach(
        option => {

            option.style.display =
                'none';
        }
    );
}


hideAllStudentResults();


/*
|--------------------------------------------------------------------------
| Search student
|--------------------------------------------------------------------------
*/

searchInput.addEventListener(
    'input',
    function () {

        const value =
            this.value
                .toLowerCase()
                .trim();


        /*
         * If the user manually edits the search box,
         * clear the previously selected ID.
         */

        hiddenInput.value = '';

        selectedDisplay.style.display =
            'none';


        /*
         * Nothing typed = show nothing.
         */

        if (value === '') {

            hideAllStudentResults();

            return;
        }


        let found = false;


        studentOptions.forEach(
            option => {

                const studentName =
                    (
                        option.dataset.name ||
                        ''
                    )
                    .toLowerCase();


                const studentNumber =
                    (
                        option.dataset.number ||
                        ''
                    )
                    .toLowerCase();


                const matches =
                    studentName.includes(
                        value
                    ) ||
                    studentNumber.includes(
                        value
                    );


                if (matches) {

                    option.style.display =
                        'block';

                    found = true;

                } else {

                    option.style.display =
                        'none';
                }
            }
        );


        /*
         * Show the result container only
         * after the user has searched.
         */

        resultsBox.style.display =
            'block';


        noResults.style.display =
            found
                ? 'none'
                : 'block';
    }
);


/*
|--------------------------------------------------------------------------
| Select student
|--------------------------------------------------------------------------
*/

studentOptions.forEach(
    option => {

        option.addEventListener(
            'click',
            function () {

                const studentId =
                    this.dataset.id || '';

                const studentName =
                    this.dataset.name || '';

                const studentNumber =
                    this.dataset.number || '';


                /*
                 * Clean value.
                 *
                 * This avoids the huge spaces caused
                 * by using this.textContent.
                 */

                const cleanValue =
                    studentName +
                    ' (' +
                    studentNumber +
                    ')';


                searchInput.value =
                    cleanValue;


                hiddenInput.value =
                    studentId;


                selectedDisplay.textContent =
                    'Selected: ' +
                    cleanValue;


                selectedDisplay.style.display =
                    'block';


                hideAllStudentResults();
            }
        );
    }
);


/*
|--------------------------------------------------------------------------
| When search box is focused
|--------------------------------------------------------------------------
|
| Do NOT automatically display all students.
|--------------------------------------------------------------------------
*/

searchInput.addEventListener(
    'focus',
    function () {

        const value =
            this.value
                .trim();


        /*
         * Empty search = keep results hidden.
         */

        if (value === '') {

            hideAllStudentResults();

            return;
        }


        /*
         * If a student is already selected,
         * don't reopen the results.
         */

        if (
            hiddenInput.value !== ''
        ) {

            hideAllStudentResults();

            return;
        }


        /*
         * If there is typed text but no selected
         * student, run the search again.
         */

        this.dispatchEvent(
            new Event(
                'input',
                {
                    bubbles: true
                }
            )
        );
    }
);


/*
|--------------------------------------------------------------------------
| Close results when clicking outside
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'click',
    function (event) {

        if (
            searchGroup &&
            !searchGroup.contains(
                event.target
            )
        ) {

            hideAllStudentResults();
        }
    }
);


/*
|--------------------------------------------------------------------------
| Prevent form submission without an actual selected student
|--------------------------------------------------------------------------
*/

const singleNotificationForm =
    document.querySelector(
        'button[name="send_notif"]'
    )?.closest('form');


if (singleNotificationForm) {

    singleNotificationForm.addEventListener(
        'submit',
        function (event) {

            if (
                hiddenInput.value === ''
            ) {

                event.preventDefault();

                alert(
                    'Please search and select a student first.'
                );

                searchInput.focus();
            }
        }
    );
}

</script>


</body>

</html>