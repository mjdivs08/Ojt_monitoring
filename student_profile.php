<?php
require_once 'config.php';
require_once 'sidebar.php';

requireLogin('student');

$user_id = (int)$_SESSION['user_id'];

$msg = '';
$err = '';


/*
|--------------------------------------------------------------------------
| GET CURRENT STUDENT
|--------------------------------------------------------------------------
*/

$studentStmt = $conn->prepare("
    SELECT
        s.*,
        u.email,
        u.full_name,
        u.username
    FROM students s
    JOIN users u
        ON u.id = s.user_id
    WHERE s.user_id = ?
    LIMIT 1
");

$studentStmt->bind_param(
    "i",
    $user_id
);

$studentStmt->execute();

$studentResult = $studentStmt->get_result();

$student = $studentResult->fetch_assoc();


if (!$student) {

    echo "Student profile not found. Please contact your coordinator.";
    exit;
}


$sid = (int)$student['id'];


/*
|--------------------------------------------------------------------------
| UPDATE PROFILE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_profile'])
) {

    $email = trim(
        $_POST['email'] ?? ''
    );

    $company_name = trim(
        $_POST['company_name'] ?? ''
    );

    $new_password =
        $_POST['new_password'] ?? '';

    $confirm_password =
        $_POST['confirm_password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | VALIDATE EMAIL
    |--------------------------------------------------------------------------
    */

    if (
        $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $err =
            "Please enter a valid email address.";

    }


    /*
    |--------------------------------------------------------------------------
    | CHECK EMAIL DUPLICATE
    |--------------------------------------------------------------------------
    */

    if (!$err) {

        $emailCheck = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            AND id != ?
            LIMIT 1
        ");

        $emailCheck->bind_param(
            "si",
            $email,
            $user_id
        );

        $emailCheck->execute();

        if (
            $emailCheck
            ->get_result()
            ->num_rows > 0
        ) {

            $err =
                "That email address is already being used.";

        }
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE PASSWORD
    |--------------------------------------------------------------------------
    |
    | Password is optional.
    | Blank = keep current password.
    |
    */

    if (
        !$err
        && $new_password !== ''
    ) {

        if (
            strlen($new_password) < 6
        ) {

            $err =
                "New password must contain at least 6 characters.";

        } elseif (
            $new_password !==
            $confirm_password
        ) {

            $err =
                "New password and confirmation do not match.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | CHECK IF COMPANY CHANGED
    |--------------------------------------------------------------------------
    */

    $old_company_name =
        trim(
            $student['company_name']
            ?? ''
        );

    $company_changed =
        strcasecmp(
            $old_company_name,
            $company_name
        ) !== 0;


    /*
    |--------------------------------------------------------------------------
    | UPDATE PROFILE
    |--------------------------------------------------------------------------
    */

    if (!$err) {

        $conn->begin_transaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | UPDATE COMPANY
            |--------------------------------------------------------------------------
            |
            | If the student changes company,
            | company_id is cleared.
            |
            | This means the new company must
            | be verified again by the coordinator.
            |
            */

            if ($company_changed) {

                $updateStudent =
                    $conn->prepare("
                        UPDATE students
                        SET
                            company_name = ?,
                            company_id = NULL
                        WHERE id = ?
                        AND user_id = ?
                    ");

                $updateStudent->bind_param(
                    "sii",
                    $company_name,
                    $sid,
                    $user_id
                );

            } else {

                $updateStudent =
                    $conn->prepare("
                        UPDATE students
                        SET company_name = ?
                        WHERE id = ?
                        AND user_id = ?
                    ");

                $updateStudent->bind_param(
                    "sii",
                    $company_name,
                    $sid,
                    $user_id
                );

            }


            if (!$updateStudent->execute()) {

                throw new Exception(
                    "Unable to update company information."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE EMAIL
            |--------------------------------------------------------------------------
            */

            $updateUser =
                $conn->prepare("
                    UPDATE users
                    SET email = ?
                    WHERE id = ?
                ");

            $updateUser->bind_param(
                "si",
                $email,
                $user_id
            );


            if (!$updateUser->execute()) {

                throw new Exception(
                    "Unable to update email address."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE PASSWORD IF PROVIDED
            |--------------------------------------------------------------------------
            */

            if ($new_password !== '') {

                $hashedPassword =
                    password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );

                $passwordStmt =
                    $conn->prepare("
                        UPDATE users
                        SET password = ?
                        WHERE id = ?
                    ");

                $passwordStmt->bind_param(
                    "si",
                    $hashedPassword,
                    $user_id
                );


                if (!$passwordStmt->execute()) {

                    throw new Exception(
                        "Unable to update password."
                    );

                }

            }


            /*
            |--------------------------------------------------------------------------
            | SAVE EVERYTHING
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            redirect(
                BASE_URL
                . 'student_profile.php?updated=1'
            );


        } catch (Exception $e) {

            $conn->rollback();

            $err = $e->getMessage();

        }

    }

}


/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

if (isset($_GET['updated'])) {

    $msg =
        "Profile updated successfully.";

}


/*
|--------------------------------------------------------------------------
| RELOAD STUDENT AFTER POSSIBLE UPDATE
|--------------------------------------------------------------------------
*/

$studentStmt = $conn->prepare("
    SELECT
        s.*,
        u.email,
        u.full_name,
        u.username
    FROM students s
    JOIN users u
        ON u.id = s.user_id
    WHERE s.user_id = ?
    LIMIT 1
");

$studentStmt->bind_param(
    "i",
    $user_id
);

$studentStmt->execute();

$student =
    $studentStmt
    ->get_result()
    ->fetch_assoc();


/*
|--------------------------------------------------------------------------
| GET TOTAL RENDERED HOURS
|--------------------------------------------------------------------------
*/

$hoursStmt =
    $conn->prepare("
        SELECT
            COALESCE(
                SUM(rendered_hours),
                0
            ) AS total_rendered
        FROM weekly_logs
        WHERE student_id = ?
        AND status = 'approved'
    ");

$hoursStmt->bind_param(
    "i",
    $sid
);

$hoursStmt->execute();

$hoursResult =
    $hoursStmt
    ->get_result()
    ->fetch_assoc();

$total_rendered =
    (float)(
        $hoursResult['total_rendered']
        ?? 0
    );


/*
|--------------------------------------------------------------------------
| GET COORDINATOR
|--------------------------------------------------------------------------
*/

$coor = null;

if (
    !empty(
        $student['coordinator_id']
    )
) {

    $coordinator_id =
        (int)$student[
            'coordinator_id'
        ];

    $coorStmt =
        $conn->prepare("
            SELECT
                id,
                full_name,
                email
            FROM users
            WHERE id = ?
            AND role = 'coordinator'
            LIMIT 1
        ");

    $coorStmt->bind_param(
        "i",
        $coordinator_id
    );

    $coorStmt->execute();

    $coorResult =
        $coorStmt->get_result();

    if (
        $coorResult->num_rows > 0
    ) {

        $coor =
            $coorResult
            ->fetch_assoc();

    }

}


/*
|--------------------------------------------------------------------------
| STUDENT COMPANY / MOA INFORMATION
|--------------------------------------------------------------------------
*/

$moaStatus =
    'No Company';

$moaExpiration =
    null;

$moaEffective =
    null;


/*
|--------------------------------------------------------------------------
| CHECK VERIFIED COMPANY
|--------------------------------------------------------------------------
*/

if (
    !empty(
        $student['company_id']
    )
) {

    $company_id =
        (int)$student[
            'company_id'
        ];


    /*
    |--------------------------------------------------------------------------
    | GET LATEST MOA
    |--------------------------------------------------------------------------
    */

    $moaStmt =
        $conn->prepare("
            SELECT
                effective_date,
                expiration_date
            FROM company_moa
            WHERE company_id = ?
            ORDER BY
                expiration_date DESC,
                id DESC
            LIMIT 1
        ");

    $moaStmt->bind_param(
        "i",
        $company_id
    );

    $moaStmt->execute();

    $moa =
        $moaStmt
        ->get_result()
        ->fetch_assoc();


    /*
    |--------------------------------------------------------------------------
    | DETERMINE MOA STATUS
    |--------------------------------------------------------------------------
    */

    if ($moa) {

        $today =
            date('Y-m-d');

        $moaEffective =
            $moa[
                'effective_date'
            ];

        $moaExpiration =
            $moa[
                'expiration_date'
            ];


        if (
            $today <
            $moaEffective
        ) {

            $moaStatus =
                'Upcoming';

        } elseif (
            $today >
            $moaExpiration
        ) {

            $moaStatus =
                'Expired';

        } else {

            $daysRemaining =
                floor(
                    (
                        strtotime(
                            $moaExpiration
                        )
                        -
                        strtotime(
                            $today
                        )
                    )
                    / 86400
                );


            if (
                $daysRemaining <= 30
            ) {

                $moaStatus =
                    'Expiring Soon';

            } else {

                $moaStatus =
                    'Active';

            }

        }


    } else {

        $moaStatus =
            'No MOA';

    }


} elseif (
    !empty(
        $student['company_name']
    )
) {

    /*
    |--------------------------------------------------------------------------
    | COMPANY ENTERED BUT NOT VERIFIED
    |--------------------------------------------------------------------------
    */

    $moaStatus =
        'Awaiting Verification';

}


/*
|--------------------------------------------------------------------------
| MOA BADGE
|--------------------------------------------------------------------------
*/

$moaClass = '';

switch ($moaStatus) {

    case 'Active':

        $moaClass =
            'badge-approved';

        break;


    case 'Expiring Soon':

    case 'Upcoming':

    case 'Awaiting Verification':

        $moaClass =
            'badge-pending';

        break;


    case 'Expired':

    case 'No MOA':

        $moaClass =
            'badge-rejected';

        break;

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
        My Profile - OJT Monitoring
    </title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>

<body>

<div class="wrapper">


    <!-- ========================================================= -->
    <!-- SIDEBAR -->
    <!-- ========================================================= -->

    <?php
    renderSidebar(
        'student',
        'profile',
        $conn
    );
    ?>


    <!-- ========================================================= -->
    <!-- MAIN CONTENT -->
    <!-- ========================================================= -->

    <div class="main-content">


        <!-- TOPBAR -->

        <?php

        if (
            function_exists(
                'renderTopbar'
            )
        ) {

            renderTopbar(
                'My Profile',
                $conn
            );

        }

        ?>


        <!-- NOTIFICATIONS -->

        <?php

        if (
            function_exists(
                'renderNotifPanel'
            )
        ) {

            renderNotifPanel(
                $conn
            );

        }

        ?>


        <div class="page-content">


            <!-- ========================================================= -->
            <!-- SUCCESS / ERROR -->
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
                >

                    <?= htmlspecialchars(
                        $err
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- ========================================================= -->
            <!-- EDIT PROFILE -->
            <!-- ========================================================= -->

            <div class="card mb-20">

                <div class="card-header">

                    <h3>
                        Edit Profile
                    </h3>

                </div>


                <div class="card-body">

                    <form method="POST">


                        <!-- EMAIL -->

                        <div class="form-group">

                            <label>
                                Email Address
                            </label>

                            <input
                                type="email"
                                name="email"
                                value="<?= htmlspecialchars(
                                    $student['email']
                                    ?? ''
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- COMPANY -->

                        <div class="form-group">

                            <label>
                                Company / Organization
                            </label>

                            <input
                                type="text"
                                name="company_name"
                                value="<?= htmlspecialchars(
                                    $student['company_name']
                                    ?? ''
                                ) ?>"
                                placeholder="Enter the company you applied to"
                            >

                            <small class="text-muted">

                                If you change your company,
                                it will require coordinator
                                verification again.

                            </small>

                        </div>


                        <!-- PASSWORD -->

                        <div class="grid-2">

                            <div class="form-group">

                                <label>
                                    New Password
                                </label>

                                <input
                                    type="password"
                                    name="new_password"
                                    placeholder="Leave blank to keep current password"
                                    autocomplete="new-password"
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Confirm New Password
                                </label>

                                <input
                                    type="password"
                                    name="confirm_password"
                                    placeholder="Confirm new password"
                                    autocomplete="new-password"
                                >

                            </div>

                        </div>


                        <button
                            type="submit"
                            name="update_profile"
                            class="btn btn-primary"
                        >

                            Save Changes

                        </button>


                    </form>

                </div>

            </div>


            <!-- ========================================================= -->
            <!-- STUDENT INFORMATION -->
            <!-- ========================================================= -->

            <div class="card mb-20">

                <div class="card-header">

                    <h3>
                        Student Info
                    </h3>

                </div>


                <div class="card-body">

                    <div style="
                        display:flex;
                        flex-direction:column;
                        gap:12px;
                    ">


                        <!-- STUDENT NUMBER -->

                        <div class="flex-between">

                            <span class="text-sm text-muted">

                                Student No.

                            </span>

                            <span class="font-bold">

                                <?= htmlspecialchars(
                                    $student[
                                        'student_number'
                                    ]
                                    ?? '—'
                                ) ?>

                            </span>

                        </div>


                        <!-- COURSE -->

                        <div class="flex-between">

                            <span class="text-sm text-muted">
                                Course
                            </span>

                            <span class="font-bold">

                                <?= htmlspecialchars(
                                    $student['course']
                                    ?: '—'
                                ) ?>

                            </span>

                        </div>


                        <!-- SECTION -->

                        <div class="flex-between">

                            <span class="text-sm text-muted">
                                Section
                            </span>

                            <span class="font-bold">

                                <?= htmlspecialchars(
                                    $student['section']
                                    ?: '—'
                                ) ?>

                            </span>

                        </div>


                        <!-- ========================================================= -->
                        <!-- COMPANY -->
                        <!-- ========================================================= -->

                        <div class="flex-between">

                            <span class="text-sm text-muted">
                                Company
                            </span>

                            <span class="font-bold">

                                <?= htmlspecialchars(
                                    $student[
                                        'company_name'
                                    ]
                                    ?: '—'
                                ) ?>

                            </span>

                        </div>


                        <!-- ========================================================= -->
                        <!-- COMPANY VERIFICATION -->
                        <!-- ========================================================= -->

                        <div class="flex-between">

                            <span class="text-sm text-muted">

                                Company Verification

                            </span>


                            <?php
                            if (
                                !empty(
                                    $student[
                                        'company_id'
                                    ]
                                )
                            ):
                            ?>

                                <span class="badge badge-approved">

                                    Verified

                                </span>


                            <?php
                            elseif (
                                !empty(
                                    $student[
                                        'company_name'
                                    ]
                                )
                            ):
                            ?>

                                <span class="badge badge-pending">

                                    Pending Verification

                                </span>


                            <?php else: ?>

                                <span class="text-muted">

                                    —

                                </span>

                            <?php endif; ?>

                        </div>


                        <!-- ========================================================= -->
                        <!-- MOA STATUS -->
                        <!-- ========================================================= -->

                        <div class="flex-between">

                            <span class="text-sm text-muted">

                                MOA Status

                            </span>


                            <?php
                            if (
                                $moaStatus ===
                                'No Company'
                            ):
                            ?>

                                <span class="text-muted">

                                    —

                                </span>


                            <?php else: ?>

                                <span
                                    class="badge <?= htmlspecialchars(
                                        $moaClass
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $moaStatus
                                    ) ?>

                                </span>

                            <?php endif; ?>

                        </div>


                        <!-- MOA EFFECTIVE -->

                        <?php if ($moaEffective): ?>

                            <div class="flex-between">

                                <span class="text-sm text-muted">

                                    MOA Effective

                                </span>

                                <span class="font-bold">

                                    <?= date(
                                        'M d, Y',
                                        strtotime(
                                            $moaEffective
                                        )
                                    ) ?>

                                </span>

                            </div>

                        <?php endif; ?>


                        <!-- MOA EXPIRATION -->

                        <?php if ($moaExpiration): ?>

                            <div class="flex-between">

                                <span class="text-sm text-muted">

                                    MOA Valid Until

                                </span>

                                <span
                                    class="font-bold
                                    <?php

                                    if (
                                        $moaStatus ===
                                        'Expired'
                                    ) {

                                        echo 'text-danger';

                                    } elseif (
                                        $moaStatus ===
                                        'Expiring Soon'
                                    ) {

                                        echo 'text-warning';

                                    }

                                    ?>"
                                >

                                    <?= date(
                                        'M d, Y',
                                        strtotime(
                                            $moaExpiration
                                        )
                                    ) ?>

                                </span>

                            </div>

                        <?php endif; ?>


                        <!-- ========================================================= -->
                        <!-- STUDENT STATUS -->
                        <!-- ========================================================= -->

                        <div class="flex-between">

                            <span class="text-sm text-muted">

                                Status

                            </span>

                            <span
                                class="badge badge-<?= htmlspecialchars(
                                    $student[
                                        'status'
                                    ]
                                    ?? 'pending'
                                ) ?>"
                            >

                                <?= ucfirst(
                                    htmlspecialchars(
                                        $student[
                                            'status'
                                        ]
                                        ?? 'pending'
                                    )
                                ) ?>

                            </span>

                        </div>


                        <!-- REQUIRED HOURS -->

                        <div class="flex-between">

                            <span class="text-sm text-muted">

                                Required Hours

                            </span>

                            <span class="font-bold">

                                <?= number_format(
                                    (float)(
                                        $student[
                                            'required_hours'
                                        ]
                                        ?? 0
                                    ),
                                    0
                                ) ?>

                                hrs

                            </span>

                        </div>


                        <!-- WEEKLY TARGET -->

                        <div class="flex-between">

                            <span class="text-sm text-muted">

                                Weekly Target

                            </span>

                            <span class="font-bold">

                                <?= number_format(
                                    (float)(
                                        $student[
                                            'weekly_required_hours'
                                        ]
                                        ?? 0
                                    ),
                                    1
                                ) ?>

                                hrs

                            </span>

                        </div>


                        <!-- HOURS RENDERED -->

                        <div class="flex-between">

                            <span class="text-sm text-muted">

                                Hours Rendered

                            </span>

                            <span class="font-bold text-success">

                                <?= number_format(
                                    $total_rendered,
                                    1
                                ) ?>

                                hrs

                            </span>

                        </div>


                        <!-- DEPLOYMENT DATE -->

                        <?php
                        if (
                            !empty(
                                $student[
                                    'deployment_date'
                                ]
                            )
                        ):
                        ?>

                            <div class="flex-between">

                                <span class="text-sm text-muted">

                                    Deployment Date

                                </span>

                                <span class="font-bold">

                                    <?= date(
                                        'M d, Y',
                                        strtotime(
                                            $student[
                                                'deployment_date'
                                            ]
                                        )
                                    ) ?>

                                </span>

                            </div>

                        <?php endif; ?>


                        <!-- DOCUMENT DEADLINE -->

                        <?php
                        if (
                            !empty(
                                $student[
                                    'pre_deployment_deadline'
                                ]
                            )
                        ):
                        ?>

                            <div class="flex-between">

                                <span class="text-sm text-muted">

                                    Doc Deadline

                                </span>

                                <span class="font-bold text-warning">

                                    <?= date(
                                        'M d, Y',
                                        strtotime(
                                            $student[
                                                'pre_deployment_deadline'
                                            ]
                                        )
                                    ) ?>

                                </span>

                            </div>

                        <?php endif; ?>


                    </div>

                </div>

            </div>


            <!-- ========================================================= -->
            <!-- MY COORDINATOR -->
            <!-- ========================================================= -->

            <?php if ($coor): ?>

                <div class="card">

                    <div class="card-header">

                        <h3>
                            My Coordinator
                        </h3>

                    </div>


                    <div class="card-body">

                        <div style="
                            display:flex;
                            align-items:center;
                            gap:12px;
                        ">


                            <div style="
                                width:42px;
                                height:42px;
                                border-radius:50%;
                                background:var(--primary);
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                color:#fff;
                                font-weight:700;
                                flex-shrink:0;
                            ">

                                <?= strtoupper(
                                    substr(
                                        $coor[
                                            'full_name'
                                        ],
                                        0,
                                        1
                                    )
                                ) ?>

                            </div>


                            <div>

                                <div class="font-bold">

                                    <?= htmlspecialchars(
                                        $coor[
                                            'full_name'
                                        ]
                                    ) ?>

                                </div>


                                <div class="text-sm text-muted">

                                    <?= htmlspecialchars(
                                        $coor[
                                            'email'
                                        ]
                                        ?? ''
                                    ) ?>

                                </div>

                            </div>


                        </div>

                    </div>

                </div>


            <?php else: ?>


                <div class="card">

                    <div class="card-header">

                        <h3>
                            My Coordinator
                        </h3>

                    </div>

                    <div class="card-body">

                        <span class="text-muted">

                            No coordinator assigned.

                        </span>

                    </div>

                </div>


            <?php endif; ?>


        </div>
        <!-- END PAGE CONTENT -->


    </div>
    <!-- END MAIN CONTENT -->


</div>
<!-- END WRAPPER -->


<script src="main.js"></script>

</body>

</html>