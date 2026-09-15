
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";

/* =====================================================
   DATE FILTER
===================================================== */

$from_date = $_GET["from_date"] ?? "";
$to_date   = $_GET["to_date"] ?? "";


/* =====================================================
   BUILD QUERY
===================================================== */

$sql = "
    SELECT
        e.expense_id,
        e.expense_title,
        e.description,
        e.amount,
        e.expense_date,
        u.full_name AS user_name
    FROM expenses e

    LEFT JOIN users u
        ON e.user_id = u.user_id

    WHERE 1=1
";

$params = [];


/* FROM DATE */

if ($from_date !== "") {

    $sql .= " AND DATE(e.expense_date) >= :from_date";

    $params[":from_date"] = $from_date;
}


/* TO DATE */

if ($to_date !== "") {

    $sql .= " AND DATE(e.expense_date) <= :to_date";

    $params[":to_date"] = $to_date;
}


$sql .= "
    ORDER BY e.expense_date DESC,
             e.expense_id DESC
";


$stmt = $conn->prepare($sql);

$stmt->execute($params);

$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   TOTAL EXPENSES
===================================================== */

$total_expenses = 0;

foreach ($expenses as $expense) {

    $total_expenses += (float) $expense["amount"];
}


/* =====================================================
   NUMBER OF EXPENSES
===================================================== */

$expense_count = count($expenses);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Expenses Report - Bakery Management</title>

  
<!-- bootstrap -->
<link
    rel="stylesheet"
    href="../assets/css/bootstrap.min.css">

    <!-- Bootstrap Icons -->

 <link
    rel="stylesheet"
    href="../assets/css/bootstrap-icons.css">



    <style>

        body {
            background-color: #f5f6fa;
        }

        .navbar {
            background-color: white;
            border-bottom: 1px solid #ddd;
        }

        .card {
            border: none;
            border-radius: 14px;
        }

        .page-title {
            font-weight: 700;
        }

        .summary-card {
            border-radius: 14px;
        }

        .table td {
            vertical-align: middle;
        }

        .print-header {
            display: none;
        }

        @media print {

            body {
                background: white !important;
            }

            .navbar,
            .no-print,
            .filter-section,
            .action-column {
                display: none !important;
            }

            .container-fluid {
                padding: 0 !important;
            }

            .card {
                box-shadow: none !important;
            }

            .print-header {
                display: block;
                text-align: center;
                margin-bottom: 20px;
            }

            .table {
                font-size: 12px;
            }

        }

    </style>

</head>

<body>


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar">

    <div class="container-fluid px-4">

        <a
            href="../admin/dashboard.php"
            class="navbar-brand fw-bold">

            <i class="bi bi-shop"></i>

            Bakery System

        </a>


        <div class="d-flex align-items-center">

            <span class="me-3">

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars(
                    $_SESSION["full_name"] ?? "User"
                ) ?>

            </span>


            <span class="badge bg-primary me-3">

                <?= htmlspecialchars(
                    $_SESSION["role"] ?? "User"
                ) ?>

            </span>


            <a
                href="../public/logout.php"
                class="btn btn-outline-danger btn-sm">

                <i class="bi bi-box-arrow-right"></i>

                Logout

            </a>

        </div>

    </div>

</nav>



<!-- =====================================================
     MAIN
===================================================== -->

<div class="container-fluid p-4">


    <!-- HEADER -->

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">

        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-cash-stack"></i>

                Expenses Report

            </h2>

            <p class="text-muted mb-0">

                View and analyze bakery expenses

            </p>

        </div>


        <div>

            <a
                href="index.php"
                class="btn btn-secondary me-2">

                <i class="bi bi-arrow-left"></i>

                Reports

            </a>


            <button
                type="button"
                onclick="window.print()"
                class="btn btn-dark">

                <i class="bi bi-printer"></i>

                Print

            </button>

        </div>

    </div>



    <!-- PRINT HEADER -->

    <div class="print-header">

        <h2>Bakery Management System</h2>

        <h4>Expenses Report</h4>

        <?php if ($from_date !== "" || $to_date !== ""): ?>

            <p>

                <?php if ($from_date !== ""): ?>

                    From:
                    <?= htmlspecialchars($from_date) ?>

                <?php endif; ?>


                <?php if ($to_date !== ""): ?>

                    &nbsp;&nbsp;

                    To:
                    <?= htmlspecialchars($to_date) ?>

                <?php endif; ?>

            </p>

        <?php else: ?>

            <p>All Expenses</p>

        <?php endif; ?>

    </div>



    <!-- =================================================
         FILTER
    ================================================== -->

    <div class="card shadow-sm mb-4 filter-section">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">

                <i class="bi bi-funnel"></i>

                Filter Expenses

            </h5>

        </div>


        <div class="card-body">

            <form
                method="GET"
                class="row g-3 align-items-end">


                <!-- FROM -->

                <div class="col-md-4">

                    <label class="form-label">

                        From Date

                    </label>

                    <input
                        type="date"
                        name="from_date"
                        class="form-control"
                        value="<?= htmlspecialchars($from_date) ?>">

                </div>


                <!-- TO -->

                <div class="col-md-4">

                    <label class="form-label">

                        To Date

                    </label>

                    <input
                        type="date"
                        name="to_date"
                        class="form-control"
                        value="<?= htmlspecialchars($to_date) ?>">

                </div>


                <!-- BUTTONS -->

                <div class="col-md-4">

                    <button
                        type="submit"
                        class="btn btn-primary me-2">

                        <i class="bi bi-search"></i>

                        Apply Filter

                    </button>


                    <a
                        href="expenses.php"
                        class="btn btn-secondary">

                        <i class="bi bi-x-circle"></i>

                        Clear

                    </a>

                </div>

            </form>

        </div>

    </div>



    <!-- =================================================
         SUMMARY
    ================================================== -->

    <div class="row g-4 mb-4">


        <!-- TOTAL -->

        <div class="col-md-6">

            <div class="card shadow-sm summary-card">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <small class="text-muted">

                                Total Expenses

                            </small>

                            <h3 class="mb-0 text-danger">

                                $<?= number_format(
                                    $total_expenses,
                                    2
                                ) ?>

                            </h3>

                        </div>


                        <div class="fs-1 text-danger">

                            <i class="bi bi-cash-stack"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- COUNT -->

        <div class="col-md-6">

            <div class="card shadow-sm summary-card">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <small class="text-muted">

                                Number of Expenses

                            </small>

                            <h3 class="mb-0 text-primary">

                                <?= $expense_count ?>

                            </h3>

                        </div>


                        <div class="fs-1 text-primary">

                            <i class="bi bi-receipt"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>



    <!-- =================================================
         EXPENSE TABLE
    ================================================== -->

    <div class="card shadow-sm">


        <div class="card-header bg-white py-3">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <h5 class="mb-0">

                        <i class="bi bi-list-ul"></i>

                        Expense List

                    </h5>

                    <small class="text-muted">

                        Expense records

                    </small>

                </div>


                <span class="badge bg-danger">

                    <?= $expense_count ?>

                    Expenses

                </span>

            </div>

        </div>



        <div class="card-body">


            <?php if (empty($expenses)): ?>


                <div class="text-center py-5">

                    <i class="bi bi-cash-stack fs-1 text-muted"></i>

                    <h5 class="mt-3">

                        No Expenses Found

                    </h5>

                    <p class="text-muted">

                        No expenses match the selected date range.

                    </p>

                </div>


            <?php else: ?>


                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>#</th>

                                <th>Expense</th>

                                <th>Description</th>

                                <th>Amount</th>

                                <th>Date</th>

                                <th>User</th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($expenses as $expense): ?>


                            <tr>


                                <!-- ID -->

                                <td>

                                    <strong>

                                        #<?= (int)
                                            $expense["expense_id"] ?>

                                    </strong>

                                </td>


                                <!-- TITLE -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $expense["expense_title"]
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- DESCRIPTION -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $expense["description"]
                                        )
                                    ): ?>

                                        <?= htmlspecialchars(
                                            $expense["description"]
                                        ) ?>

                                    <?php else: ?>

                                        <span class="text-muted">

                                            N/A

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- AMOUNT -->

                                <td>

                                    <strong class="text-danger">

                                        $<?= number_format(
                                            (float)
                                            $expense["amount"],
                                            2
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $expense["expense_date"]
                                    ) ?>

                                </td>


                                <!-- USER -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $expense["user_name"]
                                        )
                                    ): ?>

                                        <?= htmlspecialchars(
                                            $expense["user_name"]
                                        ) ?>

                                    <?php else: ?>

                                        <span class="text-muted">

                                            Unknown

                                        </span>

                                    <?php endif; ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                        <!-- TOTAL -->

                        <tfoot class="table-light">

                            <tr>

                                <th colspan="3" class="text-end">

                                    Total:

                                </th>

                                <th class="text-danger">

                                    $<?= number_format(
                                        $total_expenses,
                                        2
                                    ) ?>

                                </th>

                                <th colspan="2"></th>

                            </tr>

                        </tfoot>


                    </table>

                </div>


            <?php endif; ?>


        </div>

    </div>


</div>



    <!-- javascript links  -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>




</body>

</html>

