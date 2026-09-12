<?php
require_once 'config.php';
require_once 'sidebar.php';
requireLogin('coordinator');

$coor_id = (int)$_SESSION['user_id'];
$msg = '';
$err = '';

/*
|--------------------------------------------------------------------------
| ADD COMPANY
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_company'])) {

    $company_name   = trim($_POST['company_name'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email          = trim($_POST['email'] ?? '');

    if ($company_name === '') {

        $err = "Company name is required.";

    } else {

        // Prevent duplicate company names
        $check = $conn->prepare("
            SELECT id
            FROM companies
            WHERE LOWER(company_name) = LOWER(?)
            LIMIT 1
        ");

        $check->bind_param("s", $company_name);
        $check->execute();

        $checkResult = $check->get_result();

        if ($checkResult->num_rows > 0) {

            $err = "This company already exists.";

        } else {

            $stmt = $conn->prepare("
                INSERT INTO companies
                (
                    company_name,
                    address,
                    contact_person,
                    contact_number,
                    email,
                    created_by
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "sssssi",
                $company_name,
                $address,
                $contact_person,
                $contact_number,
                $email,
                $coor_id
            );

            if ($stmt->execute()) {

                redirect(
                    BASE_URL .
                    'coordinator_companies.php?added=1'
                );

            } else {

                $err = "Unable to add company.";

            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

if (isset($_GET['added'])) {
    $msg = "Company added successfully.";
}


/*
|--------------------------------------------------------------------------
| GET COMPANIES
|--------------------------------------------------------------------------
*/

$companies = $conn->query("
    SELECT
        c.*,

        (
            SELECT COUNT(*)
            FROM students s
            WHERE s.company_id = c.id
        ) AS student_count,

        (
            SELECT cm.effective_date
            FROM company_moa cm
            WHERE cm.company_id = c.id
            ORDER BY cm.expiration_date DESC, cm.id DESC
            LIMIT 1
        ) AS effective_date,

        (
            SELECT cm.expiration_date
            FROM company_moa cm
            WHERE cm.company_id = c.id
            ORDER BY cm.expiration_date DESC, cm.id DESC
            LIMIT 1
        ) AS expiration_date

    FROM companies c
    WHERE c.is_active = 1
    ORDER BY c.company_name ASC
");


/*
|--------------------------------------------------------------------------
| MOA STATUS FUNCTION
|--------------------------------------------------------------------------
*/

function moaStatus($effective, $expiration)
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

    $daysRemaining =
        floor(
            (strtotime($expiration) - strtotime($today))
            / 86400
        );

    if ($daysRemaining <= 30) {
        return 'Expiring Soon';
    }

    return 'Active';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>
        Companies & MOA - OJT Monitoring
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
    'companies',
    $conn
);
?>

<div class="main-content">

<?php
renderTopbar(
    'Companies & MOA',
    $conn
);
?>

<?php renderNotifPanel($conn); ?>

<div class="page-content">

    <?php if ($msg): ?>

        <div
            class="alert alert-success"
            data-auto-hide
        >
            <?= htmlspecialchars($msg) ?>
        </div>

    <?php endif; ?>


    <?php if ($err): ?>

        <div
            class="alert alert-danger"
            data-auto-hide
        >
            <?= htmlspecialchars($err) ?>
        </div>

    <?php endif; ?>


    <!-- ADD COMPANY -->

    <div class="card">

        <div class="card-header">

            <h3>Add Partner Company</h3>

        </div>

        <div class="card-body">

            <form method="POST">

                <div class="grid-2">

                    <div class="form-group">

                        <label>
                            Company Name
                        </label>

                        <input
                            type="text"
                            name="company_name"
                            placeholder="Example: ABC Hotel"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Address
                        </label>

                        <input
                            type="text"
                            name="address"
                            placeholder="Company address"
                        >

                    </div>

                </div>


                <div class="grid-2">

                    <div class="form-group">

                        <label>
                            Contact Person
                        </label>

                        <input
                            type="text"
                            name="contact_person"
                            placeholder="Contact person"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Contact Number
                        </label>

                        <input
                            type="text"
                            name="contact_number"
                            placeholder="Contact number"
                        >

                    </div>

                </div>


                <div class="form-group">

                    <label>
                        Email
                    </label>

                    <input
                        type="email"
                        name="email"
                        placeholder="Company email"
                    >

                </div>


                <button
                    type="submit"
                    name="add_company"
                    class="btn btn-primary"
                >
                    Add Company
                </button>

            </form>

        </div>

    </div>


    <!-- COMPANY LIST -->

    <div
        class="card"
        style="margin-top:20px;"
    >

        <div class="card-header">

            <h3>
                Partner Companies
            </h3>

            <div class="search-wrap">

                <input
                    type="text"
                    id="companySearch"
                    placeholder="Search companies..."
                >

            </div>

        </div>


        <div class="table-wrap">

            <table id="companyTable">

                <thead>

                    <tr>

                        <th>Company</th>

                        <th>Contact Person</th>

                        <th>Students</th>

                        <th>MOA Status</th>

                        <th>Expiration</th>

                        <th>Action</th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!$companies || $companies->num_rows === 0): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="text-center text-muted"
                            style="padding:32px;"
                        >
                            No partner companies added yet.
                        </td>

                    </tr>

                <?php else: ?>


                    <?php while ($company = $companies->fetch_assoc()): ?>

                        <?php

                        $status = moaStatus(
                            $company['effective_date'],
                            $company['expiration_date']
                        );


                        $badgeClass = 'badge-pending';

                        if ($status === 'Active') {

                            $badgeClass = 'badge-approved';

                        } elseif ($status === 'Expiring Soon') {

                            $badgeClass = 'badge-pending';

                        } elseif ($status === 'Expired') {

                            $badgeClass = 'badge-rejected';

                        }

                        ?>


                        <tr>

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $company['company_name']
                                    ) ?>

                                </strong>

                                <?php if (!empty($company['address'])): ?>

                                    <div
                                        class="text-sm text-muted"
                                    >

                                        <?= htmlspecialchars(
                                            $company['address']
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $company['contact_person']
                                    ?: '—'
                                ) ?>

                            </td>


                            <td>

                                <?= (int)$company['student_count'] ?>

                            </td>


                            <td>

                                <?php if ($status === 'No MOA'): ?>

                                    <span
                                        class="badge"
                                        style="
                                            background:#e5e7eb;
                                            color:#374151;
                                        "
                                    >
                                        No MOA
                                    </span>

                                <?php elseif ($status === 'Upcoming'): ?>

                                    <span
                                        class="badge"
                                        style="
                                            background:#dbeafe;
                                            color:#1e40af;
                                        "
                                    >
                                        Upcoming
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="badge <?= $badgeClass ?>"
                                    >
                                        <?= htmlspecialchars($status) ?>
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php
                                if (
                                    !empty(
                                        $company['expiration_date']
                                    )
                                ):
                                ?>

                                    <?= date(
                                        'M d, Y',
                                        strtotime(
                                            $company['expiration_date']
                                        )
                                    ) ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>


                            <td>

                                <a
                                    href="coordinator_company_view.php?id=<?= (int)$company['id'] ?>"
                                    class="btn btn-sm btn-primary"
                                >
                                    Manage
                                </a>
                                <a
                                    href="<?= BASE_URL ?>company_signature.php?id=<?= (int)$company['id'] ?>"
                                    class="btn btn-sm btn-secondary"
                                >
                                    Supervisor Signature
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

<script>

const search =
    document.getElementById('companySearch');

const rows =
    document.querySelectorAll(
        '#companyTable tbody tr'
    );

if (search) {

    search.addEventListener(
        'keyup',
        function () {

            const keyword =
                this.value.toLowerCase();

            rows.forEach(row => {

                const text =
                    row.innerText.toLowerCase();

                row.style.display =
                    text.includes(keyword)
                        ? ''
                        : 'none';

            });

        }
    );

}

</script>

</body>

</html>