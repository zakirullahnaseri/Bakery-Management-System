```php
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| DATE FILTER
|--------------------------------------------------------------------------
*/

$from = $_GET["from"] ?? date("Y-m-01");
$to   = $_GET["to"] ?? date("Y-m-d");

/*
|--------------------------------------------------------------------------
| VALIDATE DATE
|--------------------------------------------------------------------------
*/

$fromDate = DateTime::createFromFormat("Y-m-d", $from);
$toDate   = DateTime::createFromFormat("Y-m-d", $to);

if (!$fromDate || $fromDate->format("Y-m-d") !== $from) {
    $from = date("Y-m-01");
}

if (!$toDate || $toDate->format("Y-m-d") !== $to) {
    $to = date("Y-m-d");
}

/*
|--------------------------------------------------------------------------
| TOTAL SALES
|--------------------------------------------------------------------------
*/

$salesStmt = $conn->prepare("
    SELECT
        COALESCE(SUM(total_amount), 0) AS total_sales,
        COALESCE(SUM(discount), 0) AS total_discount,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(due_amount), 0) AS total_due
    FROM sales
    WHERE DATE(sale_date) BETWEEN :from_date AND :to_date
");

$salesStmt->execute([
    ":from_date" => $from,
    ":to_date"   => $to
]);

$salesData = $salesStmt->fetch(PDO::FETCH_ASSOC);

$totalSales    = (float)($salesData["total_sales"] ?? 0);
$totalDiscount = (float)($salesData["total_discount"] ?? 0);
$totalPaid     = (float)($salesData["total_paid"] ?? 0);
$totalDue      = (float)($salesData["total_due"] ?? 0);


/*
|--------------------------------------------------------------------------
| COST OF GOODS SOLD
|--------------------------------------------------------------------------
| sale_items.unit_price = selling price
| products.cost_price   = product cost
|
*/

$cogsStmt = $conn->prepare("
    SELECT
        COALESCE(
            SUM(si.quantity * p.cost_price),
            0
        ) AS total_cost
    FROM sale_items si

    INNER JOIN sales s
        ON si.sale_id = s.sale_id

    INNER JOIN products p
        ON si.product_id = p.product_id

    WHERE DATE(s.sale_date)
        BETWEEN :from_date AND :to_date
");

$cogsStmt->execute([
    ":from_date" => $from,
    ":to_date"   => $to
]);

$cogsData = $cogsStmt->fetch(PDO::FETCH_ASSOC);

$totalCost = (float)($cogsData["total_cost"] ?? 0);


/*
|--------------------------------------------------------------------------
| GROSS PROFIT
|--------------------------------------------------------------------------
*/

$grossProfit = $totalSales - $totalCost;


/*
|--------------------------------------------------------------------------
| EXPENSES
|--------------------------------------------------------------------------
*/

$expenseStmt = $conn->prepare("
    SELECT
        COALESCE(SUM(amount), 0) AS total_expenses
    FROM expenses
    WHERE DATE(expense_date)
        BETWEEN :from_date AND :to_date
");

$expenseStmt->execute([
    ":from_date" => $from,
    ":to_date"   => $to
]);

$expenseData = $expenseStmt->fetch(PDO::FETCH_ASSOC);

$totalExpenses = (float)($expenseData["total_expenses"] ?? 0);


/*
|--------------------------------------------------------------------------
| NET PROFIT
|--------------------------------------------------------------------------
*/

$netProfit = $grossProfit - $totalExpenses;


/*
|--------------------------------------------------------------------------
| PROFIT MARGIN
|--------------------------------------------------------------------------
*/

$profitMargin = 0;

if ($totalSales > 0) {
    $profitMargin = ($netProfit / $totalSales) * 100;
}


/*
|--------------------------------------------------------------------------
| SALES COUNT
|--------------------------------------------------------------------------
*/

$countStmt = $conn->prepare("
    SELECT COUNT(*) AS total_sales_count
    FROM sales
    WHERE DATE(sale_date)
        BETWEEN :from_date AND :to_date
");

$countStmt->execute([
    ":from_date" => $from,
    ":to_date"   => $to
]);

$salesCount = (int)$countStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| EXPENSE COUNT
|--------------------------------------------------------------------------
*/

$expenseCountStmt = $conn->prepare("
    SELECT COUNT(*) AS total_expense_count
    FROM expenses
    WHERE DATE(expense_date)
        BETWEEN :from_date AND :to_date
");

$expenseCountStmt->execute([
    ":from_date" => $from,
    ":to_date"   => $to
]);

$expenseCount = (int)$expenseCountStmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Profit Report - Bakery Management</title>

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
            background: #f5f6fa;
        }

        .navbar {
            background: white;
            border-bottom: 1px solid #ddd;
        }

        .card {
            border: none;
            border-radius: 14px;
        }

        .page-title {
            font-weight: 700;
        }

        .stat-card {
            border-radius: 14px;
            min-height: 145px;
        }

        .stat-icon {
            font-size: 32px;
        }

        .profit-box {
            border-radius: 14px;
            padding: 30px;
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

<body>

<!-- NAVBAR -->

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


<div class="container-fluid p-4">

    <!-- HEADER -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-graph-up-arrow"></i>

                Profit Report

            </h2>

            <p class="text-muted mb-0">

                Sales, costs, expenses and profit analysis

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
                onclick="window.print()"
                class="btn btn-dark">

                <i class="bi bi-printer"></i>

                Print

            </button>

        </div>

    </div>


    <!-- DATE FILTER -->

    <div class="card shadow-sm mb-4 no-print">

        <div class="card-body">

            <form method="GET">

                <div class="row g-3 align-items-end">

                    <div class="col-md-5">

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


                    <div class="col-md-5">

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


                    <div class="col-md-2">

                        <button
                            type="submit"
                            class="btn btn-primary w-100">

                            <i class="bi bi-search"></i>

                            Filter

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <!-- REPORT PERIOD -->

    <div class="alert alert-info">

        <i class="bi bi-calendar3"></i>

        <strong>Report Period:</strong>

        <?= htmlspecialchars($from) ?>

        &nbsp; to &nbsp;

        <?= htmlspecialchars($to) ?>

    </div>


    <!-- STAT CARDS -->

    <div class="row g-4 mb-4">


        <!-- SALES -->

        <div class="col-md-6 col-xl-3">

            <div class="card shadow-sm stat-card">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-1">
                                Total Sales
                            </p>

                            <h3 class="fw-bold mb-2">

                                $<?= number_format(
                                    $totalSales,
                                    2
                                ) ?>

                            </h3>

                            <small class="text-muted">

                                <?= $salesCount ?> sales

                            </small>

                        </div>

                        <div class="stat-icon text-primary">

                            <i class="bi bi-cart-check"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- COST -->

        <div class="col-md-6 col-xl-3">

            <div class="card shadow-sm stat-card">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-1">
                                Product Cost
                            </p>

                            <h3 class="fw-bold mb-2">

                                $<?= number_format(
                                    $totalCost,
                                    2
                                ) ?>

                            </h3>

                            <small class="text-muted">

                                Cost of goods sold

                            </small>

                        </div>

                        <div class="stat-icon text-warning">

                            <i class="bi bi-box-seam"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- EXPENSES -->

        <div class="col-md-6 col-xl-3">

            <div class="card shadow-sm stat-card">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-1">
                                Expenses
                            </p>

                            <h3 class="fw-bold mb-2">

                                $<?= number_format(
                                    $totalExpenses,
                                    2
                                ) ?>

                            </h3>

                            <small class="text-muted">

                                <?= $expenseCount ?> expenses

                            </small>

                        </div>

                        <div class="stat-icon text-danger">

                            <i class="bi bi-wallet2"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- NET PROFIT -->

        <div class="col-md-6 col-xl-3">

            <div class="card shadow-sm stat-card">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-1">
                                Net Profit
                            </p>

                            <h3 class="fw-bold mb-2">

                                $<?= number_format(
                                    $netProfit,
                                    2
                                ) ?>

                            </h3>

                            <small class="<?= $netProfit >= 0
                                ? 'text-success'
                                : 'text-danger' ?>">

                                <?= number_format(
                                    $profitMargin,
                                    2
                                ) ?>% margin

                            </small>

                        </div>

                        <div class="stat-icon <?= $netProfit >= 0
                            ? 'text-success'
                            : 'text-danger' ?>">

                            <i class="bi bi-graph-up-arrow"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- PROFIT SUMMARY -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">

                <i class="bi bi-calculator"></i>

                Profit Summary

            </h5>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table">

                    <tbody>

                        <tr>

                            <td>
                                Total Sales
                            </td>

                            <td class="text-end fw-bold">

                                $<?= number_format(
                                    $totalSales,
                                    2
                                ) ?>

                            </td>

                        </tr>


                        <tr>

                            <td>
                                Product Cost
                            </td>

                            <td class="text-end text-warning">

                                - $<?= number_format(
                                    $totalCost,
                                    2
                                ) ?>

                            </td>

                        </tr>


                        <tr class="table-light">

                            <td class="fw-bold">
                                Gross Profit
                            </td>

                            <td class="text-end fw-bold">

                                $<?= number_format(
                                    $grossProfit,
                                    2
                                ) ?>

                            </td>

                        </tr>


                        <tr>

                            <td>
                                Operating Expenses
                            </td>

                            <td class="text-end text-danger">

                                - $<?= number_format(
                                    $totalExpenses,
                                    2
                                ) ?>

                            </td>

                        </tr>


                        <tr class="table-success">

                            <td class="fw-bold fs-5">

                                Net Profit

                            </td>

                            <td class="text-end fw-bold fs-5">

                                $<?= number_format(
                                    $netProfit,
                                    2
                                ) ?>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- PAYMENT SUMMARY -->

    <div class="row g-4">


        <div class="col-lg-6">

            <div class="card shadow-sm">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-credit-card"></i>

                        Payment Summary

                    </h5>

                </div>

                <div class="card-body">

                    <div class="d-flex justify-content-between mb-3">

                        <span>
                            Total Paid
                        </span>

                        <strong class="text-success">

                            $<?= number_format(
                                $totalPaid,
                                2
                            ) ?>

                        </strong>

                    </div>


                    <div class="d-flex justify-content-between mb-3">

                        <span>
                            Total Due
                        </span>

                        <strong class="text-danger">

                            $<?= number_format(
                                $totalDue,
                                2
                            ) ?>

                        </strong>

                    </div>


                    <div class="d-flex justify-content-between">

                        <span>
                            Total Discount
                        </span>

                        <strong class="text-warning">

                            $<?= number_format(
                                $totalDiscount,
                                2
                            ) ?>

                        </strong>

                    </div>

                </div>

            </div>

        </div>


        <!-- PROFIT STATUS -->

        <div class="col-lg-6">

            <div class="card shadow-sm">

                <div class="card-body text-center profit-box">

                    <?php if ($netProfit >= 0): ?>

                        <i class="bi bi-emoji-smile fs-1 text-success"></i>

                        <h4 class="mt-3 text-success">

                            Business is Profitable

                        </h4>

                        <p class="text-muted mb-0">

                            Your net profit for this period is

                            <strong>

                                $<?= number_format(
                                    $netProfit,
                                    2
                                ) ?>

                            </strong>

                        </p>

                    <?php else: ?>

                        <i class="bi bi-emoji-frown fs-1 text-danger"></i>

                        <h4 class="mt-3 text-danger">

                            Business is Running at a Loss

                        </h4>

                        <p class="text-muted mb-0">

                            Your net loss for this period is

                            <strong>

                                $<?= number_format(
                                    abs($netProfit),
                                    2
                                ) ?>

                            </strong>

                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>



    <!-- javascript links  -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>



</body>

</html>

