<?php
require_once 'config.php';
require_once 'sidebar.php';

requireLogin('coordinator');

$uid = (int)$_SESSION['user_id'];


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
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->close();
    }

    redirect(BASE_URL . 'coordinator_settings.php');
}


$msg = '';
$err = '';


/*
|--------------------------------------------------------------------------
| Helper for saving one setting
|--------------------------------------------------------------------------
*/

function saveSystemSetting(
    mysqli $conn,
    string $key,
    string $value
): bool {

    $stmt = $conn->prepare("
        INSERT INTO settings (
            setting_key,
            setting_value
        )
        VALUES (?, ?)

        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value)
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'ss',
        $key,
        $value
    );

    $success = $stmt->execute();

    $stmt->close();

    return $success;
}


/*
|--------------------------------------------------------------------------
| Save general/default OJT settings
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_settings'])
) {

    $academicYear = trim(
        $_POST['academic_year'] ?? ''
    );


    $totalHours = isset(
        $_POST['total_required_hours']
    )
        ? (float)$_POST['total_required_hours']
        : 0;


    $weeklyHours = isset(
        $_POST['weekly_required_hours']
    )
        ? (float)$_POST['weekly_required_hours']
        : 0;


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($academicYear === '') {

        $err = 'Academic year is required.';

    } elseif ($totalHours <= 0) {

        $err =
            'Total required hours must be greater than zero.';

    } elseif ($weeklyHours <= 0) {

        $err =
            'Weekly required hours must be greater than zero.';

    } elseif ($weeklyHours > $totalHours) {

        $err =
            'Weekly required hours cannot exceed total required hours.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Settings to save
        |--------------------------------------------------------------------------
        */

        $settings = [

            'academic_year' =>
                $academicYear,

            'total_required_hours' =>
                (string)$totalHours,

            'weekly_required_hours' =>
                (string)$weeklyHours,

            'settings_updated_by' =>
                (string)$uid,

            'settings_updated_at' =>
                date('Y-m-d H:i:s')
        ];


        $conn->begin_transaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Save each setting
            |--------------------------------------------------------------------------
            */

            foreach (
                $settings as $key => $value
            ) {

                if (
                    !saveSystemSetting(
                        $conn,
                        $key,
                        $value
                    )
                ) {
                    throw new Exception(
                        'Unable to save setting: ' .
                        $key
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Apply hour settings to existing students
            |--------------------------------------------------------------------------
            |
            | If unchecked:
            | Only newly created students will use these defaults.
            |
            | If checked:
            | All current students will also be updated.
            |
            */

            if (
                isset(
                    $_POST['apply_existing_students']
                )
            ) {

                $updateStudents =
                    $conn->prepare("
                        UPDATE students
                        SET required_hours = ?,
                            weekly_required_hours = ?
                    ");


                if (!$updateStudents) {

                    throw new Exception(
                        'Unable to prepare student update.'
                    );
                }


                $updateStudents->bind_param(
                    'dd',
                    $totalHours,
                    $weeklyHours
                );


                if (
                    !$updateStudents->execute()
                ) {

                    $errorMessage =
                        $updateStudents->error;

                    $updateStudents->close();

                    throw new Exception(
                        'Unable to update existing students: ' .
                        $errorMessage
                    );
                }


                $affectedStudents =
                    $updateStudents->affected_rows;


                $updateStudents->close();


                $msg =
                    'Settings saved successfully. ' .
                    'The hour requirements were applied to ' .
                    $affectedStudents .
                    ' existing student(s).';

            } else {

                $msg =
                    'Settings saved successfully. ' .
                    'The hour requirements will automatically ' .
                    'apply to newly created students.';
            }


            $conn->commit();

        } catch (Throwable $exception) {

            $conn->rollback();

            $err =
                $exception->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Change coordinator password
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['change_pw'])
) {

    $currentPassword =
        $_POST['current_pw'] ?? '';

    $newPassword =
        $_POST['new_pw'] ?? '';

    $confirmPassword =
        $_POST['confirm_pw'] ?? '';


    $stmt = $conn->prepare("
        SELECT password
        FROM users
        WHERE id = ?
        LIMIT 1
    ");


    if (!$stmt) {

        $err =
            'Unable to verify your account.';

    } else {

        $stmt->bind_param(
            'i',
            $uid
        );

        $stmt->execute();


        $result =
            $stmt->get_result();


        $user =
            $result
                ? $result->fetch_assoc()
                : null;


        $stmt->close();


        if (!$user) {

            $err =
                'User account not found.';

        } elseif (
            !password_verify(
                $currentPassword,
                $user['password']
            )
        ) {

            $err =
                'Current password is incorrect.';

        } elseif (
            $newPassword !==
            $confirmPassword
        ) {

            $err =
                'New passwords do not match.';

        } elseif (
            strlen($newPassword) < 8
        ) {

            $err =
                'Password must be at least 8 characters.';

        } elseif (
            password_verify(
                $newPassword,
                $user['password']
            )
        ) {

            $err =
                'Your new password must be different ' .
                'from your current password.';

        } else {

            $hashedPassword =
                password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );


            $update =
                $conn->prepare("
                    UPDATE users
                    SET password = ?
                    WHERE id = ?
                ");


            if (!$update) {

                $err =
                    'Unable to prepare the password update.';

            } else {

                $update->bind_param(
                    'si',
                    $hashedPassword,
                    $uid
                );


                if (
                    $update->execute()
                ) {

                    $msg =
                        'Password changed successfully.';

                } else {

                    $err =
                        'Unable to change your password.';
                }


                $update->close();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load current settings
|--------------------------------------------------------------------------
*/

$academicYear =
    getSetting(
        $conn,
        'academic_year'
    )
    ?? '2026-2027';


$totalHours =
    (float)(
        getSetting(
            $conn,
            'total_required_hours'
        )
        ?? 600
    );


$weeklyHours =
    (float)(
        getSetting(
            $conn,
            'weekly_required_hours'
        )
        ?? 40
    );


$updatedAt =
    getSetting(
        $conn,
        'settings_updated_at'
    );


$updatedById =
    (int)(
        getSetting(
            $conn,
            'settings_updated_by'
        )
        ?? 0
    );


$updatedByName =
    'Not available';


if ($updatedById > 0) {

    $stmt = $conn->prepare("
        SELECT full_name
        FROM users
        WHERE id = ?
        LIMIT 1
    ");


    if ($stmt) {

        $stmt->bind_param(
            'i',
            $updatedById
        );

        $stmt->execute();


        $result =
            $stmt->get_result();


        if (
            $result &&
            $result->num_rows > 0
        ) {

            $updatedByName =
                $result
                    ->fetch_assoc()
                    ['full_name'];
        }


        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| System Information
|--------------------------------------------------------------------------
*/

$dbSize = null;

$dbName = DB_NAME;


$stmt = $conn->prepare("
    SELECT ROUND(
        SUM(
            data_length +
            index_length
        ) / 1024 / 1024,
        2
    ) AS size

    FROM information_schema.tables

    WHERE table_schema = ?
");


if ($stmt) {

    $stmt->bind_param(
        's',
        $dbName
    );

    $stmt->execute();


    $result =
        $stmt->get_result();


    if (
        $result &&
        ($row = $result->fetch_assoc())
    ) {

        $dbSize =
            $row['size'];
    }


    $stmt->close();
}


$phpUploadLimit =
    ini_get('upload_max_filesize');


$timezone =
    date_default_timezone_get();

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
        Settings – OJT Monitoring
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
        'settings',
        $conn
    );
    ?>


    <div class="main-content">


        <?php
        renderTopbar(
            'System Settings',
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
                     LEFT COLUMN
                     ===================================================== -->

                <div>


                    <!-- =========================
                         GENERAL SETTINGS
                         ========================= -->

                    <div class="card mb-20">


                        <div class="card-header">

                            <h3>
                                General Settings
                            </h3>

                        </div>


                        <div class="card-body">


                            <form method="POST">


                                <!-- Academic Year -->

                                <div class="form-group">

                                    <label for="academic_year">
                                        Academic Year
                                    </label>


                                    <input
                                        type="text"
                                        id="academic_year"
                                        name="academic_year"
                                        value="<?= htmlspecialchars(
                                            $academicYear
                                        ) ?>"
                                        placeholder="Example: 2027-2028"
                                        maxlength="20"
                                        required
                                    >

                                </div>



                                <!-- =========================
                                     DEFAULT OJT HOURS
                                     ========================= -->

                                <div
                                    style="
                                        margin:22px 0 15px;
                                        padding-top:18px;
                                        border-top:1px solid var(--border);
                                    "
                                >

                                    <h4
                                        style="
                                            margin:0 0 5px;
                                            font-size:14px;
                                        "
                                    >
                                        Default OJT Hours
                                    </h4>


                                    <p
                                        class="text-sm text-muted"
                                        style="margin:0;"
                                    >
                                        These values will automatically
                                        apply to all newly created students.
                                    </p>

                                </div>



                                <div class="grid-2">


                                    <!-- Total Required Hours -->

                                    <div class="form-group">

                                        <label
                                            for="total_required_hours"
                                        >
                                            Default Total Required Hours
                                        </label>


                                        <input
                                            type="number"
                                            id="total_required_hours"
                                            name="total_required_hours"
                                            value="<?= htmlspecialchars(
                                                (string)$totalHours
                                            ) ?>"
                                            min="1"
                                            step="1"
                                            required
                                        >

                                    </div>



                                    <!-- Weekly Required Hours -->

                                    <div class="form-group">

                                        <label
                                            for="weekly_required_hours"
                                        >
                                            Default Weekly Required Hours
                                        </label>


                                        <input
                                            type="number"
                                            id="weekly_required_hours"
                                            name="weekly_required_hours"
                                            value="<?= htmlspecialchars(
                                                (string)$weeklyHours
                                            ) ?>"
                                            min="1"
                                            step="0.5"
                                            required
                                        >

                                    </div>


                                </div>



                                <!-- =========================
                                     APPLY TO EXISTING STUDENTS
                                     ========================= -->

                                <label
                                    style="
                                        display:flex;
                                        align-items:flex-start;
                                        gap:10px;
                                        padding:14px;
                                        margin-bottom:18px;
                                        border:1px solid #fcd34d;
                                        border-radius:8px;
                                        background:#fffbeb;
                                        cursor:pointer;
                                    "
                                >

                                    <input
                                        type="checkbox"
                                        id="apply_existing_students"
                                        name="apply_existing_students"
                                        value="1"
                                        style="
                                            width:auto;
                                            margin-top:3px;
                                            flex-shrink:0;
                                        "
                                    >


                                    <span>

                                        <strong
                                            style="
                                                display:block;
                                                color:#92400e;
                                                font-size:13px;
                                            "
                                        >
                                            Also apply to all existing students
                                        </strong>


                                        <small
                                            style="
                                                display:block;
                                                margin-top:4px;
                                                color:#a16207;
                                                line-height:1.4;
                                            "
                                        >
                                            Leave this unchecked to apply the
                                            new defaults only to students
                                            created after saving.
                                        </small>

                                    </span>

                                </label>



                                <!-- SAVE BUTTON -->

                                <button
                                    type="submit"
                                    name="save_settings"
                                    class="btn btn-primary btn-block"
                                    onclick="
                                        const box =
                                            document.getElementById(
                                                'apply_existing_students'
                                            );

                                        return !box.checked || confirm(
                                            'This will overwrite the hour ' +
                                            'requirements of every existing ' +
                                            'student. Continue?'
                                        );
                                    "
                                >
                                    Save General Settings
                                </button>


                            </form>


                        </div>

                    </div>



                    <!-- =====================================================
                         CHANGE PASSWORD
                         ===================================================== -->

                    <div class="card">


                        <div class="card-header">

                            <h3>
                                Change My Password
                            </h3>

                        </div>


                        <div class="card-body">


                            <form method="POST">


                                <!-- Current Password -->

                                <div class="form-group">

                                    <label for="current_pw">
                                        Current Password
                                    </label>


                                    <input
                                        type="password"
                                        id="current_pw"
                                        name="current_pw"
                                        autocomplete="current-password"
                                        required
                                    >

                                </div>



                                <!-- New Password -->

                                <div class="form-group">

                                    <label for="new_pw">
                                        New Password
                                    </label>


                                    <input
                                        type="password"
                                        id="new_pw"
                                        name="new_pw"
                                        minlength="8"
                                        autocomplete="new-password"
                                        required
                                    >

                                </div>



                                <!-- Confirm Password -->

                                <div class="form-group">

                                    <label for="confirm_pw">
                                        Confirm New Password
                                    </label>


                                    <input
                                        type="password"
                                        id="confirm_pw"
                                        name="confirm_pw"
                                        minlength="8"
                                        autocomplete="new-password"
                                        required
                                    >

                                </div>



                                <!-- CHANGE PASSWORD BUTTON -->

                                <button
                                    type="submit"
                                    name="change_pw"
                                    class="btn btn-primary btn-block"
                                >
                                    Change Password
                                </button>


                            </form>


                        </div>

                    </div>


                </div>



                <!-- =====================================================
                     RIGHT COLUMN
                     ===================================================== -->

                <div>


                    <!-- =========================
                         SYSTEM INFORMATION
                         ========================= -->

                    <div class="card">


                        <div class="card-header">

                            <h3>
                                System Information
                            </h3>

                        </div>


                        <div class="card-body">


                            <div
                                style="
                                    display:flex;
                                    flex-direction:column;
                                    gap:14px;
                                "
                            >


                                <!-- Academic Year -->

                                <div class="flex-between">

                                    <span class="text-muted text-sm">
                                        Current Academic Year
                                    </span>


                                    <strong>
                                        <?= htmlspecialchars(
                                            $academicYear
                                        ) ?>
                                    </strong>

                                </div>



                                <!-- Last Settings Update -->

                                <div class="flex-between">

                                    <span class="text-muted text-sm">
                                        Last Settings Update
                                    </span>


                                    <strong>

                                        <?= $updatedAt

                                            ? htmlspecialchars(
                                                date(
                                                    'M d, Y h:i A',
                                                    strtotime(
                                                        $updatedAt
                                                    )
                                                )
                                            )

                                            : 'Not available'
                                        ?>

                                    </strong>

                                </div>



                                <!-- Updated By -->

                                <div class="flex-between">

                                    <span class="text-muted text-sm">
                                        Updated By
                                    </span>


                                    <strong>
                                        <?= htmlspecialchars(
                                            $updatedByName
                                        ) ?>
                                    </strong>

                                </div>



                                <div
                                    style="
                                        border-top:1px solid var(--border);
                                        margin:3px 0;
                                    "
                                ></div>



                                <!-- PHP Version -->

                                <div class="flex-between">

                                    <span class="text-muted text-sm">
                                        PHP Version
                                    </span>


                                    <strong>
                                        <?= htmlspecialchars(
                                            phpversion()
                                        ) ?>
                                    </strong>

                                </div>



                                <!-- MySQL Version -->

                                <div class="flex-between">

                                    <span class="text-muted text-sm">
                                        MySQL Version
                                    </span>


                                    <strong>
                                        <?= htmlspecialchars(
                                            $conn->server_info
                                        ) ?>
                                    </strong>

                                </div>



                                <!-- Database Name -->

                                <div class="flex-between">

                                    <span class="text-muted text-sm">
                                        Database Name
                                    </span>


                                    <strong>
                                        <?= htmlspecialchars(
                                            DB_NAME
                                        ) ?>
                                    </strong>

                                </div>



                                <!-- Database Size -->

                                <div class="flex-between">

                                    <span class="text-muted text-sm">
                                        Database Size
                                    </span>


                                    <strong>

                                        <?= $dbSize !== null

                                            ? htmlspecialchars(
                                                (string)$dbSize
                                            ) . ' MB'

                                            : 'N/A'
                                        ?>

                                    </strong>

                                </div>



                                <!-- Upload Limit -->

                                <div class="flex-between">

                                    <span class="text-muted text-sm">
                                        PHP Upload Limit
                                    </span>


                                    <strong>
                                        <?= htmlspecialchars(
                                            $phpUploadLimit
                                        ) ?>
                                    </strong>

                                </div>



                                <!-- Timezone -->

                                <div class="flex-between">

                                    <span class="text-muted text-sm">
                                        Timezone
                                    </span>


                                    <strong>
                                        <?= htmlspecialchars(
                                            $timezone
                                        ) ?>
                                    </strong>

                                </div>



                                <!-- Server Time -->

                                <div class="flex-between">

                                    <span class="text-muted text-sm">
                                        Server Time
                                    </span>


                                    <strong>
                                        <?= date(
                                            'M d, Y h:i A'
                                        ) ?>
                                    </strong>

                                </div>


                            </div>


                        </div>

                    </div>


                </div>


            </div>


        </div>

    </div>

</div>


<script
    src="<?= BASE_URL ?>main.js?v=<?= time() ?>"
></script>


</body>

</html>