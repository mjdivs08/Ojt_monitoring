<?php

require_once 'config.php';
require_once 'sidebar.php';

requireLogin('coordinator');


$coor_id =
    (int)$_SESSION[
        'user_id'
    ];


$company_id =
    (int)(
        $_GET['id']
        ?? $_POST['company_id']
        ?? 0
    );


$msg = '';
$err = '';


if (
    $company_id <= 0
) {

    exit(
        'Invalid company.'
    );
}


/*
|--------------------------------------------------------------------------
| GET COMPANY
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare("
        SELECT *
        FROM companies

        WHERE
            id = ?
        AND
            created_by = ?

        LIMIT 1
    ");


$stmt->bind_param(
    'ii',
    $company_id,
    $coor_id
);


$stmt->execute();


$result =
    $stmt->get_result();


if (
    !$result ||
    $result->num_rows === 0
) {

    $stmt->close();

    exit(
        'Company not found or access denied.'
    );
}


$company =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| SAVE SUPERVISOR / SIGNATURE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER[
        'REQUEST_METHOD'
    ] === 'POST'
    &&
    isset(
        $_POST[
            'save_signature'
        ]
    )
) {

    $supervisor_name =
        trim(
            $_POST[
                'supervisor_name'
            ]
            ?? ''
        );


    if (
        $supervisor_name === ''
    ) {

        $err =
            'Supervisor name is required.';
    }


    /*
    |--------------------------------------------------------------------------
    | Existing reference signature can stay if no new image uploaded
    |--------------------------------------------------------------------------
    */

    $newSignaturePath =
        $company[
            'reference_signature'
        ]
        ?? null;


    $oldSignaturePath =
        $newSignaturePath;


    if (
        !$err
        &&
        isset(
            $_FILES[
                'reference_signature'
            ]
        )
        &&
        $_FILES[
            'reference_signature'
        ]['error']
            !==
        UPLOAD_ERR_NO_FILE
    ) {

        $file =
            $_FILES[
                'reference_signature'
            ];


        if (
            $file['error']
            !==
            UPLOAD_ERR_OK
        ) {

            $err =
                'Unable to upload reference signature.';

        } elseif (
            $file['size']
            >
            5 * 1024 * 1024
        ) {

            $err =
                'Reference signature must be under 5 MB.';

        } else {

            $finfo =
                finfo_open(
                    FILEINFO_MIME_TYPE
                );


            $mime =
                finfo_file(
                    $finfo,
                    $file[
                        'tmp_name'
                    ]
                );


            finfo_close(
                $finfo
            );


            $allowed = [
                'image/jpeg'
                    => 'jpg',

                'image/png'
                    => 'png'
            ];


            if (
                !isset(
                    $allowed[
                        $mime
                    ]
                )
            ) {

                $err =
                    'Reference signature must be JPG or PNG.';

            } else {

                $directory =
                    UPLOAD_PATH
                    . 'signatures'
                    . DIRECTORY_SEPARATOR;


                if (
                    !is_dir(
                        $directory
                    )
                ) {

                    mkdir(
                        $directory,
                        0755,
                        true
                    );
                }


                $filename =
                    'company_'
                    . $company_id
                    . '_signature_'
                    . time()
                    . '_'
                    . bin2hex(
                        random_bytes(4)
                    )
                    . '.'
                    . $allowed[
                        $mime
                    ];


                $destination =
                    $directory
                    . $filename;


                if (
                    !move_uploaded_file(
                        $file[
                            'tmp_name'
                        ],
                        $destination
                    )
                ) {

                    $err =
                        'Failed to save reference signature.';

                } else {

                    $newSignaturePath =
                        'signatures/'
                        . $filename;
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | A reference image is required
    |--------------------------------------------------------------------------
    */

    if (
        !$err
        &&
        empty(
            $newSignaturePath
        )
    ) {

        $err =
            'Please upload the company supervisor reference signature.';
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE COMPANY
    |--------------------------------------------------------------------------
    */

    if (!$err) {

        $stmt =
            $conn->prepare("
                UPDATE companies

                SET
                    supervisor_name = ?,
                    reference_signature = ?

                WHERE
                    id = ?
                AND
                    created_by = ?
            ");


        $stmt->bind_param(
            'ssii',
            $supervisor_name,
            $newSignaturePath,
            $company_id,
            $coor_id
        );


        if (
            $stmt->execute()
        ) {

            /*
            |--------------------------------------------------------------------------
            | Remove old image only after database update succeeds
            |--------------------------------------------------------------------------
            */

            if (
                $oldSignaturePath
                &&
                $oldSignaturePath
                    !==
                $newSignaturePath
            ) {

                $oldFullPath =
                    UPLOAD_PATH
                    . str_replace(
                        [
                            '/',
                            '\\'
                        ],
                        DIRECTORY_SEPARATOR,
                        $oldSignaturePath
                    );


                if (
                    is_file(
                        $oldFullPath
                    )
                ) {

                    @unlink(
                        $oldFullPath
                    );
                }
            }


            $msg =
                'Company supervisor signature updated successfully.';


            /*
            |--------------------------------------------------------------------------
            | Refresh company
            |--------------------------------------------------------------------------
            */

            $company[
                'supervisor_name'
            ] =
                $supervisor_name;


            $company[
                'reference_signature'
            ] =
                $newSignaturePath;

        } else {

            $err =
                'Unable to update company signature.';
        }


        $stmt->close();
    }
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
    Company Signature - OJT Monitoring
</title>

<link
    rel="stylesheet"
    href="<?= BASE_URL ?>style.css?v=<?= time() ?>"
>

<style>

.reference-signature-preview {
    width: 280px;
    max-width: 100%;
    height: 120px;
    object-fit: contain;
    border: 1px solid #dbe4ee;
    border-radius: 10px;
    padding: 8px;
    background: white;
}

.signature-security-note {
    padding: 12px 14px;
    background: #fef3c7;
    color: #92400e;
    border-radius: 9px;
    margin-bottom: 16px;
    font-size: 13px;
    line-height: 1.5;
}

</style>

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
    'Company Supervisor Signature',
    $conn
);

renderNotifPanel(
    $conn
);

?>


<div class="page-content">


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

<div class="alert alert-danger">

<?= htmlspecialchars(
    $err
) ?>

</div>

<?php endif; ?>


<div
    class="card"
    style="max-width:750px;"
>


<div class="card-header">

<h3>

<?= htmlspecialchars(
    $company[
        'company_name'
    ]
) ?>

</h3>

</div>


<div class="card-body">


<div class="signature-security-note">

The stored reference signature is
protected verification data. Students
should not be given access to this image.
The system automatically uses it when
a student deployed to this company
submits a Weekly DTR.

</div>


<form
    method="POST"
    enctype="multipart/form-data"
>


<input
    type="hidden"
    name="company_id"
    value="<?= $company_id ?>"
>


<div class="form-group">

<label>
    OJT Supervisor Name
</label>

<input
    type="text"
    name="supervisor_name"
    value="<?= htmlspecialchars(
        $company[
            'supervisor_name'
        ]
        ?? ''
    ) ?>"
    required
>

</div>


<?php if (
    !empty(
        $company[
            'reference_signature'
        ]
    )
): ?>

<div class="form-group">

<label>
    Current Reference Signature
</label>

<br>

<img
    src="<?= UPLOAD_URL . htmlspecialchars(
        $company[
            'reference_signature'
        ]
    ) ?>"
    alt="Reference signature"
    class="reference-signature-preview"
>

</div>

<?php endif; ?>


<div class="form-group">

<label>

<?= !empty(
    $company[
        'reference_signature'
    ]
)
    ? 'Replace Reference Signature'
    : 'Upload Reference Signature'
?>

</label>

<input
    type="file"
    name="reference_signature"
    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
    <?= empty(
        $company[
            'reference_signature'
        ]
    )
        ? 'required'
        : ''
    ?>
>

<div
    class="text-sm text-muted"
    style="margin-top:5px;"
>

Use a clear image containing only
the authorized supervisor signature,
preferably on a plain white background.
</div>
</div>
<button
    type="submit"
    name="save_signature"
    class="btn btn-primary"
>
Save Supervisor Signature
</button>
<a
    href="<?= BASE_URL ?>coordinator_companies.php"
    class="btn btn-secondary">
Back
</a>
</form>
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