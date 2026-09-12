<?php

require_once 'config.php';
require_once 'signature_verification.php';

requireLogin();


$userId =
    (int)$_SESSION[
        'user_id'
    ];


$role =
    $_SESSION['role']
    ?? '';


$logId =
    (int)(
        $_GET['id']
        ?? 0
    );


$type =
    $_GET['type']
    ?? 'submitted';


if (
    $logId <= 0
    ||
    !in_array(
        $type,
        [
            'submitted',
            'reference'
        ],
        true
    )
) {

    http_response_code(
        400
    );

    exit(
        'Invalid request.'
    );
}


/*
|--------------------------------------------------------------------------
| GET WEEKLY LOG + COMPANY
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare("
        SELECT
            wl.*,

            s.user_id
                AS student_user_id,

            s.coordinator_id,

            c.reference_signature

        FROM weekly_logs wl

        INNER JOIN students s
            ON s.id =
                wl.student_id

        LEFT JOIN companies c
            ON c.id =
                s.company_id

        WHERE
            wl.id = ?

        LIMIT 1
    ");


$stmt->bind_param(
    'i',
    $logId
);


$stmt->execute();


$result =
    $stmt->get_result();


if (
    !$result
    ||
    $result->num_rows === 0
) {

    http_response_code(
        404
    );

    exit(
        'Weekly log not found.'
    );
}


$log =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| AUTHORIZATION
|--------------------------------------------------------------------------
*/

$isAdmin =
    $role === 'admin';


$isAssignedCoordinator =
    (
        $role ===
        'coordinator'
        &&
        (int)$log[
            'coordinator_id'
        ]
        ===
        $userId
    );


$isOwnerStudent =
    (
        $role ===
        'student'
        &&
        (int)$log[
            'student_user_id'
        ]
        ===
        $userId
    );


/*
|--------------------------------------------------------------------------
| REFERENCE SIGNATURE
|--------------------------------------------------------------------------
|
| STUDENTS ARE NEVER ALLOWED TO VIEW THIS.
|
*/

if (
    $type ===
    'reference'
) {

    if (
        !$isAdmin
        &&
        !$isAssignedCoordinator
    ) {

        http_response_code(
            403
        );

        exit(
            'Unauthorized.'
        );
    }


    if (
        empty(
            $log[
                'reference_signature'
            ]
        )
    ) {

        http_response_code(
            404
        );

        exit(
            'Reference signature unavailable.'
        );
    }


    $path =
        UPLOAD_PATH
        . str_replace(
            [
                '/',
                '\\'
            ],
            DIRECTORY_SEPARATOR,
            $log[
                'reference_signature'
            ]
        );


    $image =
        sigLoadImage(
            $path
        );


    if (!$image) {

        http_response_code(
            404
        );

        exit(
            'Reference signature file unavailable.'
        );
    }


    header(
        'Content-Type: image/png'
    );


    header(
        'Cache-Control: private, no-store, no-cache, must-revalidate'
    );


    imagepng(
        $image
    );


    imagedestroy(
        $image
    );


    exit;
}


/*
|--------------------------------------------------------------------------
| SUBMITTED SIGNATURE
|--------------------------------------------------------------------------
*/

if (
    !$isAdmin
    &&
    !$isAssignedCoordinator
    &&
    !$isOwnerStudent
) {

    http_response_code(
        403
    );

    exit(
        'Unauthorized.'
    );
}


if (
    empty(
        $log[
            'dtr_photo'
        ]
    )
    ||
    $log[
        'signature_x'
    ] === null
) {

    http_response_code(
        404
    );

    exit(
        'Selected signature unavailable.'
    );
}


$dtrPath =
    UPLOAD_PATH
    . str_replace(
        [
            '/',
            '\\'
        ],
        DIRECTORY_SEPARATOR,
        $log[
            'dtr_photo'
        ]
    );


$image =
    sigCropSelectedArea(
        $dtrPath,

        (float)$log[
            'signature_x'
        ],

        (float)$log[
            'signature_y'
        ],

        (float)$log[
            'signature_width'
        ],

        (float)$log[
            'signature_height'
        ]
    );


if (!$image) {

    http_response_code(
        404
    );

    exit(
        'Selected signature unavailable.'
    );
}


header(
    'Content-Type: image/png'
);


header(
    'Cache-Control: private, no-store, no-cache, must-revalidate'
);


imagepng(
    $image
);


imagedestroy(
    $image
);