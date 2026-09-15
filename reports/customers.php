<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";

/* =====================================================
   SEARCH
===================================================== */

$search = trim($_GET["search"] ?? "");


/* =====================================================
   GET CUSTOMER REPORT
===================================================== */

$sql = "
    SELECT
        c.customer_id,
        c.name,
        c.phone,
        c.email,

        COUNT(s.sale_id) AS total_sales,

        COALESCE(SUM(s.total_amount), 0) AS total_amount,

        COALESCE(SUM(s.paid_amount), 0) AS total_paid,

        COALESCE(SUM(s.due_amount), 0) AS total_due

    FROM customers c

    LEFT JOIN sales s
        ON c.customer_id = s.customer_id
";


$params = [];


if ($search !== "") {

    $sql .= "
        WHERE
            c.name LIKE :search
            OR c.phone LIKE :search
            OR c.email LIKE :search
    ";

    $params[":search"] = "%{$search}%";
}


$sql .= "
    GROUP BY
        c.customer_id,
        c.name,
        c.phone,
        c.email

    ORDER BY total_amount DESC
";


$stmt = $conn->prepare($sql);
$stmt->execute($params);

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   SUMMARY
===================================================== */

$totalCustomers = count($customers);

$totalSales = 0;
$totalAmount = 0;
$totalPaid = 0;
$totalDue = 0;


foreach ($customers as $customer) {

    $totalSales += (int)$customer["total_sales"];

    $totalAmount += (float)$customer["total_amount"];

    $totalPaid += (float)$customer["total_paid"];

    $totalDue += (float)$customer["total_due"];
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Customer Report - Bakery Management</title>
    <!-- css link  -->
    <link rel="stylesheet" href="../assets/css/backgrounds.css">
   
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

        .summary-icon {
            font-size: 30px;
        }

        .table td {
            vertical-align: middle;
        }

        @media print {

            .no-print {
                display: none !important;
            }

            body {
                background: white;
            }

            .card {
                box-shadow: none !important;
            }

        }

    </style>

</head>


<body class="bg-customers">


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar no-print">

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
     CONTENT
===================================================== -->

<div class="container-fluid p-4">


    <!-- HEADER -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-people"></i>

                Customer Report

            </h2>

            <p class="text-muted mb-0">

                Customer sales and payment summary

            </p>

        </div>


        <div class="no-print">

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



    <!-- =================================================
         SUMMARY CARDS
    ================================================== -->

    <div class="row g-4 mb-4">


        <!-- CUSTOMERS -->

        <div class="col-md-6 col-xl-3">

            <div class="card shadow-sm summary-card">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <small class="text-muted">
                                Customers
                            </small>

                            <h3 class="fw-bold mt-2 mb-0">

                                <?= number_format(
                                    $totalCustomers
                                ) ?>

                            </h3>

                        </div>

                        <i class="bi bi-people text-primary summary-icon"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- SALES -->

        <div class="col-md-6 col-xl-3">

            <div class="card shadow-sm summary-card">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <small class="text-muted">
                                Total Sales
                            </small>

                            <h3 class="fw-bold mt-2 mb-0">

                                <?= number_format(
                                    $totalSales
                                ) ?>

                            </h3>

                        </div>

                        <i class="bi bi-receipt text-success summary-icon"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- AMOUNT -->

        <div class="col-md-6 col-xl-3">

            <div class="card shadow-sm summary-card">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <small class="text-muted">
                                Total Sales Amount
                            </small>

                            <h3 class="fw-bold mt-2 mb-0">

                                $<?= number_format(
                                    $totalAmount,
                                    2
                                ) ?>

                            </h3>

                        </div>

                        <i class="bi bi-currency-dollar text-info summary-icon"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- DUE -->

        <div class="col-md-6 col-xl-3">

            <div class="card shadow-sm summary-card">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <small class="text-muted">
                                Total Due
                            </small>

                            <h3 class="fw-bold mt-2 mb-0 text-danger">

                                $<?= number_format(
                                    $totalDue,
                                    2
                                ) ?>

                            </h3>

                        </div>

                        <i class="bi bi-exclamation-circle text-danger summary-icon"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>



    <!-- =================================================
         CUSTOMER REPORT
    ================================================== -->

    <div class="card shadow-sm">


        <div class="card-header bg-white py-3">


            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <h5 class="mb-0">

                        <i class="bi bi-bar-chart"></i>

                        Customer Sales Report

                    </h5>

                    <small class="text-muted">

                        Sales, payments and outstanding balances

                    </small>

                </div>

            </div>

        </div>



        <div class="card-body">


            <!-- SEARCH -->

            <form
                method="GET"
                class="row g-2 mb-4 no-print">

                <div class="col-md-10">

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search customer by name, phone or email..."
                        value="<?= htmlspecialchars($search) ?>">

                </div>


                <div class="col-md-2 d-grid">

                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="bi bi-search"></i>

                        Search

                    </button>

                </div>

            </form>



            <?php if ($search !== ""): ?>

                <div class="mb-3 no-print">

                    <span class="text-muted">

                        Search result for:

                    </span>

                    <strong>

                        <?= htmlspecialchars($search) ?>

                    </strong>


                    <a
                        href="customers.php"
                        class="btn btn-sm btn-outline-secondary ms-2">

                        <i class="bi bi-x"></i>

                        Clear

                    </a>

                </div>

            <?php endif; ?>



            <?php if (empty($customers)): ?>

                <div class="text-center py-5">

                    <i class="bi bi-people fs-1 text-muted"></i>

                    <h5 class="mt-3">

                        No Customers Found

                    </h5>

                    <p class="text-muted">

                        No customer sales data is available.

                    </p>

                </div>

            <?php else: ?>


                <div class="table-responsive">

                    <table class="table table-hover align-middle">


                        <thead class="table-light">

                            <tr>

                                <th>#</th>

                                <th>Customer</th>

                                <th>Phone</th>

                                <th>Sales</th>

                                <th>Total Amount</th>

                                <th>Paid</th>

                                <th>Due</th>

                                <th>Status</th>

                                <th class="text-center no-print">
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($customers as $customer): ?>


                            <?php

                            $due = (float)$customer["total_due"];

                            ?>


                            <tr>


                                <!-- ID -->

                                <td>

                                    <strong>

                                        #<?= (int)$customer["customer_id"] ?>

                                    </strong>

                                </td>



                                <!-- CUSTOMER -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $customer["name"]
                                        ) ?>

                                    </strong>


                                    <?php if (!empty($customer["email"])): ?>

                                        <br>

                                        <small class="text-muted">

                                            <?= htmlspecialchars(
                                                $customer["email"]
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                </td>



                                <!-- PHONE -->

                                <td>

                                    <?php if (!empty($customer["phone"])): ?>

                                        <?= htmlspecialchars(
                                            $customer["phone"]
                                        ) ?>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            N/A
                                        </span>

                                    <?php endif; ?>

                                </td>



                                <!-- SALES -->

                                <td>

                                    <span class="badge bg-primary">

                                        <?= number_format(
                                            (int)$customer["total_sales"]
                                        ) ?>

                                    </span>

                                </td>



                                <!-- TOTAL -->

                                <td>

                                    <strong>

                                        $<?= number_format(
                                            (float)$customer["total_amount"],
                                            2
                                        ) ?>

                                    </strong>

                                </td>



                                <!-- PAID -->

                                <td>

                                    <span class="text-success">

                                        $<?= number_format(
                                            (float)$customer["total_paid"],
                                            2
                                        ) ?>

                                    </span>

                                </td>



                                <!-- DUE -->

                                <td>

                                    <?php if ($due > 0): ?>

                                        <strong class="text-danger">

                                            $<?= number_format(
                                                $due,
                                                2
                                            ) ?>

                                        </strong>

                                    <?php else: ?>

                                        <span class="text-success">

                                            $0.00

                                        </span>

                                    <?php endif; ?>

                                </td>



                                <!-- STATUS -->

                                <td>

                                    <?php if ($due <= 0): ?>

                                        <span class="badge bg-success">

                                            Paid

                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-danger">

                                            Due

                                        </span>

                                    <?php endif; ?>

                                </td>



                                <!-- ACTION -->

                                <td class="text-center no-print">

                                    <a
                                        href="../customers/view.php?id=<?= (int)$customer["customer_id"] ?>"
                                        class="btn btn-sm btn-primary"
                                        title="View Customer">

                                        <i class="bi bi-eye"></i>

                                    </a>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                        <!-- FOOTER TOTAL -->

                        <tfoot class="table-light">

                            <tr>

                                <th colspan="3" class="text-end">

                                    Grand Total:

                                </th>

                                <th>

                                    <?= number_format(
                                        $totalSales
                                    ) ?>

                                </th>

                                <th>

                                    $<?= number_format(
                                        $totalAmount,
                                        2
                                    ) ?>

                                </th>

                                <th class="text-success">

                                    $<?= number_format(
                                        $totalPaid,
                                        2
                                    ) ?>

                                </th>

                                <th class="text-danger">

                                    $<?= number_format(
                                        $totalDue,
                                        2
                                    ) ?>

                                </th>

                                <th></th>

                                <th class="no-print"></th>

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