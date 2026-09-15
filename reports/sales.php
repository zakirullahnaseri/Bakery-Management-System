
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";


/* =====================================================
   FILTERS
===================================================== */

$from = $_GET["from"] ?? date("Y-m-01");
$to   = $_GET["to"] ?? date("Y-m-d");

$customer_id = !empty($_GET["customer_id"])
    ? (int) $_GET["customer_id"]
    : 0;

$payment_method = $_GET["payment_method"] ?? "";

$payment_status = $_GET["payment_status"] ?? "";


/* =====================================================
   VALIDATE DATES
===================================================== */

if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $from)) {
    $from = date("Y-m-01");
}

if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $to)) {
    $to = date("Y-m-d");
}


/* =====================================================
   CUSTOMERS
===================================================== */

$customerStmt = $conn->query("
    SELECT
        customer_id,
        name
    FROM customers
    ORDER BY name ASC
");

$customers = $customerStmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   BUILD QUERY
===================================================== */

$sql = "
    SELECT
        s.sale_id,
        s.sale_date,
        s.total_amount,
        s.discount,
        s.paid_amount,
        s.due_amount,
        s.payment_method,
        s.payment_status,

        c.name AS customer_name,

        u.full_name AS user_name

    FROM sales s

    LEFT JOIN customers c
        ON s.customer_id = c.customer_id

    LEFT JOIN users u
        ON s.user_id = u.user_id

    WHERE DATE(s.sale_date)
        BETWEEN :from AND :to
";

$params = [
    ":from" => $from,
    ":to"   => $to
];


/* =====================================================
   CUSTOMER FILTER
===================================================== */

if ($customer_id > 0) {

    $sql .= "
        AND s.customer_id = :customer_id
    ";

    $params[":customer_id"] = $customer_id;
}


/* =====================================================
   PAYMENT METHOD FILTER
===================================================== */

$allowedMethods = [
    "cash",
    "card",
    "bank",
    "credit"
];

if (
    $payment_method !== "" &&
    in_array($payment_method, $allowedMethods, true)
) {

    $sql .= "
        AND s.payment_method = :payment_method
    ";

    $params[":payment_method"] = $payment_method;
}


/* =====================================================
   PAYMENT STATUS FILTER
===================================================== */

$allowedStatuses = [
    "paid",
    "partial",
    "unpaid"
];

if (
    $payment_status !== "" &&
    in_array($payment_status, $allowedStatuses, true)
) {

    $sql .= "
        AND s.payment_status = :payment_status
    ";

    $params[":payment_status"] = $payment_status;
}


/* =====================================================
   ORDER
===================================================== */

$sql .= "
    ORDER BY s.sale_id DESC
";


/* =====================================================
   GET SALES
===================================================== */

$stmt = $conn->prepare($sql);

$stmt->execute($params);

$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   SUMMARY
===================================================== */

$totalSales = count($sales);

$totalAmount = 0;

$totalDiscount = 0;

$totalPaid = 0;

$totalDue = 0;


foreach ($sales as $sale) {

    $totalAmount +=
        (float) $sale["total_amount"];

    $totalDiscount +=
        (float) $sale["discount"];

    $totalPaid +=
        (float) $sale["paid_amount"];

    $totalDue +=
        (float) $sale["due_amount"];
}


/* =====================================================
   MONEY FUNCTION
===================================================== */

function money($amount)
{
    return "$" . number_format(
        (float) $amount,
        2
    );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Sales Report - Bakery Management</title>


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
            background: white;
            border-radius: 14px;
            padding: 20px;
            height: 100%;
        }

        .summary-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 20px;
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
     MAIN
===================================================== -->

<div class="container-fluid p-4">


    <!-- HEADER -->

    <div class="d-flex justify-content-between align-items-center mb-4">


        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-cart-check"></i>

                Sales Report

            </h2>


            <p class="text-muted mb-0">

                Detailed sales report

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
         FILTER
    ================================================== -->

    <div class="card shadow-sm mb-4 no-print">


        <div class="card-header bg-white py-3">

            <h5 class="mb-0">

                <i class="bi bi-funnel"></i>

                Filter Sales

            </h5>

        </div>


        <div class="card-body">


            <form method="GET">


                <div class="row g-3">


                    <!-- FROM -->

                    <div class="col-md-3">

                        <label class="form-label fw-semibold">

                            From Date

                        </label>

                        <input
                            type="date"
                            name="from"
                            class="form-control"
                            value="<?= htmlspecialchars($from) ?>"
                            required>

                    </div>


                    <!-- TO -->

                    <div class="col-md-3">

                        <label class="form-label fw-semibold">

                            To Date

                        </label>

                        <input
                            type="date"
                            name="to"
                            class="form-control"
                            value="<?= htmlspecialchars($to) ?>"
                            required>

                    </div>


                    <!-- CUSTOMER -->

                    <div class="col-md-3">

                        <label class="form-label fw-semibold">

                            Customer

                        </label>

                        <select
                            name="customer_id"
                            class="form-select">

                            <option value="">

                                All Customers

                            </option>


                            <?php foreach (
                                $customers
                                as $customer
                            ): ?>

                                <option
                                    value="<?= (int)$customer["customer_id"] ?>"
                                    <?= $customer_id ==
                                        $customer["customer_id"]
                                        ? "selected"
                                        : "" ?>>

                                    <?= htmlspecialchars(
                                        $customer["name"]
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- PAYMENT METHOD -->

                    <div class="col-md-3">

                        <label class="form-label fw-semibold">

                            Payment Method

                        </label>

                        <select
                            name="payment_method"
                            class="form-select">

                            <option value="">

                                All Methods

                            </option>


                            <option
                                value="cash"
                                <?= $payment_method === "cash"
                                    ? "selected"
                                    : "" ?>>

                                Cash

                            </option>


                            <option
                                value="card"
                                <?= $payment_method === "card"
                                    ? "selected"
                                    : "" ?>>

                                Card

                            </option>


                            <option
                                value="bank"
                                <?= $payment_method === "bank"
                                    ? "selected"
                                    : "" ?>>

                                Bank

                            </option>


                            <option
                                value="credit"
                                <?= $payment_method === "credit"
                                    ? "selected"
                                    : "" ?>>

                                Credit

                            </option>

                        </select>

                    </div>


                    <!-- STATUS -->

                    <div class="col-md-3">

                        <label class="form-label fw-semibold">

                            Payment Status

                        </label>

                        <select
                            name="payment_status"
                            class="form-select">

                            <option value="">

                                All Status

                            </option>


                            <option
                                value="paid"
                                <?= $payment_status === "paid"
                                    ? "selected"
                                    : "" ?>>

                                Paid

                            </option>


                            <option
                                value="partial"
                                <?= $payment_status === "partial"
                                    ? "selected"
                                    : "" ?>>

                                Partial

                            </option>


                            <option
                                value="unpaid"
                                <?= $payment_status === "unpaid"
                                    ? "selected"
                                    : "" ?>>

                                Unpaid

                            </option>

                        </select>

                    </div>


                    <!-- BUTTONS -->

                    <div class="col-md-9 d-flex align-items-end">

                        <button
                            type="submit"
                            class="btn btn-primary me-2">

                            <i class="bi bi-search"></i>

                            Generate Report

                        </button>


                        <a
                            href="sales.php"
                            class="btn btn-secondary">

                            <i class="bi bi-arrow-clockwise"></i>

                            Reset

                        </a>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <!-- =================================================
         SUMMARY
    ================================================== -->

    <div class="row g-4 mb-4">


        <!-- TRANSACTIONS -->

        <div class="col-md-6 col-xl-3">

            <div class="summary-card shadow-sm">


                <div class="d-flex justify-content-between">


                    <div>

                        <small class="text-muted">

                            Transactions

                        </small>


                        <h3 class="fw-bold mt-2">

                            <?= $totalSales ?>

                        </h3>

                    </div>


                    <div class="summary-icon bg-primary-subtle text-primary">

                        <i class="bi bi-receipt"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- TOTAL -->

        <div class="col-md-6 col-xl-3">

            <div class="summary-card shadow-sm">


                <div class="d-flex justify-content-between">


                    <div>

                        <small class="text-muted">

                            Total Sales

                        </small>


                        <h3 class="fw-bold mt-2">

                            <?= money($totalAmount) ?>

                        </h3>

                    </div>


                    <div class="summary-icon bg-success-subtle text-success">

                        <i class="bi bi-currency-dollar"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- PAID -->

        <div class="col-md-6 col-xl-3">

            <div class="summary-card shadow-sm">


                <div class="d-flex justify-content-between">


                    <div>

                        <small class="text-muted">

                            Paid

                        </small>


                        <h3 class="fw-bold mt-2 text-success">

                            <?= money($totalPaid) ?>

                        </h3>

                    </div>


                    <div class="summary-icon bg-success-subtle text-success">

                        <i class="bi bi-check-circle"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- DUE -->

        <div class="col-md-6 col-xl-3">

            <div class="summary-card shadow-sm">


                <div class="d-flex justify-content-between">


                    <div>

                        <small class="text-muted">

                            Due

                        </small>


                        <h3 class="fw-bold mt-2 text-danger">

                            <?= money($totalDue) ?>

                        </h3>

                    </div>


                    <div class="summary-icon bg-danger-subtle text-danger">

                        <i class="bi bi-clock-history"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         REPORT TABLE
    ================================================== -->

    <div class="card shadow-sm">


        <div class="card-header bg-white py-3">


            <div class="d-flex justify-content-between align-items-center">


                <div>

                    <h5 class="mb-1">

                        <i class="bi bi-table"></i>

                        Sales Details

                    </h5>


                    <small class="text-muted">

                        <?= htmlspecialchars($from) ?>

                        to

                        <?= htmlspecialchars($to) ?>

                    </small>

                </div>


                <span class="badge bg-primary">

                    <?= $totalSales ?>

                    Sales

                </span>

            </div>

        </div>


        <div class="card-body">


            <?php if (empty($sales)): ?>


                <div class="text-center py-5">


                    <i class="bi bi-receipt fs-1 text-muted"></i>


                    <h5 class="mt-3">

                        No Sales Found

                    </h5>


                    <p class="text-muted">

                        No sales match the selected filters.

                    </p>

                </div>


            <?php else: ?>


                <div class="table-responsive">


                    <table class="table table-hover align-middle">


                        <thead class="table-light">

                            <tr>

                                <th>#</th>

                                <th>Date</th>

                                <th>Customer</th>

                                <th>Cashier</th>

                                <th>Total</th>

                                <th>Discount</th>

                                <th>Paid</th>

                                <th>Due</th>

                                <th>Payment</th>

                                <th>Status</th>

                                <th class="text-center no-print">

                                    Action

                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $sales
                            as $sale
                        ): ?>


                            <tr>


                                <!-- ID -->

                                <td>

                                    <strong>

                                        #<?= (int)$sale["sale_id"] ?>

                                    </strong>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $sale["sale_date"]
                                    ) ?>

                                </td>


                                <!-- CUSTOMER -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $sale["customer_name"]
                                        )
                                    ): ?>

                                        <?= htmlspecialchars(
                                            $sale["customer_name"]
                                        ) ?>

                                    <?php else: ?>

                                        <span class="text-muted">

                                            Walk-in Customer

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- USER -->

                                <td>

                                    <?= htmlspecialchars(
                                        $sale["user_name"]
                                        ?? "Unknown"
                                    ) ?>

                                </td>


                                <!-- TOTAL -->

                                <td>

                                    <strong>

                                        <?= money(
                                            $sale["total_amount"]
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- DISCOUNT -->

                                <td>

                                    <?= money(
                                        $sale["discount"]
                                    ) ?>

                                </td>


                                <!-- PAID -->

                                <td>

                                    <span class="text-success">

                                        <?= money(
                                            $sale["paid_amount"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- DUE -->

                                <td>

                                    <?php if (
                                        (float)$sale["due_amount"] > 0
                                    ): ?>

                                        <span class="text-danger">

                                            <?= money(
                                                $sale["due_amount"]
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="text-success">

                                            $0.00

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- PAYMENT -->

                                <td>

                                    <span class="badge bg-secondary">

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $sale[
                                                    "payment_method"
                                                ]
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php

                                    $statusClass = "secondary";

                                    if (
                                        $sale["payment_status"]
                                        === "paid"
                                    ) {

                                        $statusClass =
                                            "success";

                                    } elseif (
                                        $sale["payment_status"]
                                        === "partial"
                                    ) {

                                        $statusClass =
                                            "warning";

                                    } elseif (
                                        $sale["payment_status"]
                                        === "unpaid"
                                    ) {

                                        $statusClass =
                                            "danger";
                                    }

                                    ?>


                                    <span
                                        class="badge bg-<?= $statusClass ?>">

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $sale[
                                                    "payment_status"
                                                ]
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ACTION -->

                                <td
                                    class="text-center no-print">


                                    <div
                                        class="btn-group"
                                        role="group">


                                        <!-- VIEW -->

                                        <a
                                            href="../sales/view.php?id=<?= (int)$sale["sale_id"] ?>"
                                            class="btn btn-sm btn-primary"
                                            title="View Sale">

                                            <i class="bi bi-eye"></i>

                                        </a>


                                        <!-- PRINT -->

                                        <a
                                            href="../sales/view.php?id=<?= (int)$sale["sale_id"] ?>"
                                            target="_blank"
                                            class="btn btn-sm btn-secondary"
                                            title="Print Sale">

                                            <i class="bi bi-printer"></i>

                                        </a>

                                    </div>


                                </td>

                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                        <!-- TOTAL FOOTER -->

                        <tfoot class="table-light">


                            <tr>

                                <th colspan="4" class="text-end">

                                    TOTAL

                                </th>


                                <th>

                                    <?= money($totalAmount) ?>

                                </th>


                                <th>

                                    <?= money($totalDiscount) ?>

                                </th>


                                <th class="text-success">

                                    <?= money($totalPaid) ?>

                                </th>


                                <th class="text-danger">

                                    <?= money($totalDue) ?>

                                </th>


                                <th colspan="3">

                                </th>

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