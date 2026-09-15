
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| REPORT DATE FILTER
|--------------------------------------------------------------------------
*/

$from = $_GET["from"] ?? date("Y-m-01");
$to   = $_GET["to"] ?? date("Y-m-d");

/*
|--------------------------------------------------------------------------
| DATE VALIDATION
|--------------------------------------------------------------------------
*/

if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $from)) {
    $from = date("Y-m-01");
}

if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $to)) {
    $to = date("Y-m-d");
}

/*
|--------------------------------------------------------------------------
| SALES SUMMARY
|--------------------------------------------------------------------------
*/

$salesStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_sales,
        COALESCE(SUM(total_amount), 0) AS total_sales_amount,
        COALESCE(SUM(discount), 0) AS total_discount,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(due_amount), 0) AS total_due
    FROM sales
    WHERE DATE(sale_date) BETWEEN :from AND :to
");

$salesStmt->execute([
    ":from" => $from,
    ":to"   => $to
]);

$salesSummary = $salesStmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PURCHASE SUMMARY
|--------------------------------------------------------------------------
*/

$purchaseStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_purchases,
        COALESCE(SUM(total_amount), 0) AS total_purchase_amount,
        COALESCE(SUM(paid_amount), 0) AS total_purchase_paid,
        COALESCE(SUM(due_amount), 0) AS total_purchase_due
    FROM purchases
    WHERE DATE(purchase_date) BETWEEN :from AND :to
");

$purchaseStmt->execute([
    ":from" => $from,
    ":to"   => $to
]);

$purchaseSummary = $purchaseStmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| EXPENSE SUMMARY
|--------------------------------------------------------------------------
*/

$expenseStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_expenses,
        COALESCE(SUM(amount), 0) AS total_expense_amount
    FROM expenses
    WHERE DATE(expense_date) BETWEEN :from AND :to
");

$expenseStmt->execute([
    ":from" => $from,
    ":to"   => $to
]);

$expenseSummary = $expenseStmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PRODUCT / STOCK SUMMARY
|--------------------------------------------------------------------------
*/

$productStmt = $conn->query("
    SELECT
        COUNT(*) AS total_products,
        COALESCE(SUM(stock_quantity), 0) AS total_stock
    FROM products
    WHERE status = 'available'
");

$productSummary = $productStmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| LOW STOCK PRODUCTS
|--------------------------------------------------------------------------
*/

$lowStockStmt = $conn->query("
    SELECT
        product_id,
        product_name,
        stock_quantity,
        minimum_stock,
        unit
    FROM products
    WHERE status = 'available'
      AND stock_quantity <= minimum_stock
    ORDER BY stock_quantity ASC
");

$lowStockProducts = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| SALES BY PAYMENT METHOD
|--------------------------------------------------------------------------
*/

$paymentMethodStmt = $conn->prepare("
    SELECT
        payment_method,
        COUNT(*) AS sale_count,
        COALESCE(SUM(total_amount), 0) AS amount
    FROM sales
    WHERE DATE(sale_date) BETWEEN :from AND :to
    GROUP BY payment_method
    ORDER BY amount DESC
");

$paymentMethodStmt->execute([
    ":from" => $from,
    ":to"   => $to
]);

$paymentMethods = $paymentMethodStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| SALES BY PAYMENT STATUS
|--------------------------------------------------------------------------
*/

$paymentStatusStmt = $conn->prepare("
    SELECT
        payment_status,
        COUNT(*) AS sale_count,
        COALESCE(SUM(total_amount), 0) AS amount
    FROM sales
    WHERE DATE(sale_date) BETWEEN :from AND :to
    GROUP BY payment_status
    ORDER BY amount DESC
");

$paymentStatusStmt->execute([
    ":from" => $from,
    ":to"   => $to
]);

$paymentStatuses = $paymentStatusStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| TOP SELLING PRODUCTS
|--------------------------------------------------------------------------
*/

$topProductsStmt = $conn->prepare("
    SELECT
        p.product_name,
        SUM(si.quantity) AS total_quantity,
        SUM(si.total_price) AS total_amount
    FROM sale_items si

    INNER JOIN sales s
        ON si.sale_id = s.sale_id

    INNER JOIN products p
        ON si.product_id = p.product_id

    WHERE DATE(s.sale_date) BETWEEN :from AND :to

    GROUP BY
        si.product_id,
        p.product_name

    ORDER BY total_quantity DESC

    LIMIT 10
");

$topProductsStmt->execute([
    ":from" => $from,
    ":to"   => $to
]);

$topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PROFIT ESTIMATE
|--------------------------------------------------------------------------
*/

$totalSales =
    (float) ($salesSummary["total_sales_amount"] ?? 0);

$totalPurchases =
    (float) ($purchaseSummary["total_purchase_amount"] ?? 0);

$totalExpenses =
    (float) ($expenseSummary["total_expense_amount"] ?? 0);

$estimatedProfit =
    $totalSales - $totalPurchases - $totalExpenses;

/*
|--------------------------------------------------------------------------
| MONEY FORMAT
|--------------------------------------------------------------------------
*/

function money($amount)
{
    return "$" . number_format((float)$amount, 2);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Reports - Bakery Management</title>
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

        .stat-card {
            border-radius: 14px;
            padding: 22px;
            background: white;
            height: 100%;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .table td {
            vertical-align: middle;
        }

        .report-card {
            min-height: 100%;
        }

    </style>

</head>

<body class="bg-reports">

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

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-bar-chart-line"></i>

                Reports

            </h2>

            <p class="text-muted mb-0">

                Bakery business reports and statistics

            </p>

        </div>

        <!-- REPORT BUTTONS -->

        <div class="d-flex gap-2">

            <!-- Customer Report -->

            <a
                href="customers.php"
                class="btn btn-outline-primary">

                <i class="bi bi-people"></i>

                Customer Report

            </a>

            <!-- Dashboard -->

            <a
                href="../admin/dashboard.php"
                class="btn btn-secondary">

                <i class="bi bi-arrow-left"></i>

                Dashboard

            </a>

        </div>

    </div>


    <!-- =================================================
         DATE FILTER
    ================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <form method="GET">

                <div class="row g-3 align-items-end">

                    <div class="col-md-4">

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

                    <div class="col-md-4">

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

                    <div class="col-md-4">

                        <button
                            type="submit"
                            class="btn btn-primary me-2">

                            <i class="bi bi-filter"></i>

                            Generate Report

                        </button>

                        <a
                            href="index.php"
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
         STATISTICS
    ================================================== -->

    <div class="row g-4 mb-4">

        <!-- SALES -->

        <div class="col-md-6 col-xl-3">

            <div class="stat-card shadow-sm">

                <div class="d-flex justify-content-between">

                    <div>

                        <small class="text-muted">
                            Total Sales
                        </small>

                        <h3 class="fw-bold mt-2">
                            <?= money($totalSales) ?>
                        </h3>

                        <small class="text-muted">

                            <?= (int)$salesSummary["total_sales"] ?>

                            transactions

                        </small>

                    </div>

                    <div class="stat-icon bg-primary-subtle text-primary">

                        <i class="bi bi-cart-check"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- PURCHASES -->

        <div class="col-md-6 col-xl-3">

            <div class="stat-card shadow-sm">

                <div class="d-flex justify-content-between">

                    <div>

                        <small class="text-muted">
                            Total Purchases
                        </small>

                        <h3 class="fw-bold mt-2">
                            <?= money($totalPurchases) ?>
                        </h3>

                        <small class="text-muted">

                            <?= (int)$purchaseSummary["total_purchases"] ?>

                            purchases

                        </small>

                    </div>

                    <div class="stat-icon bg-warning-subtle text-warning">

                        <i class="bi bi-bag-check"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- EXPENSES -->

        <div class="col-md-6 col-xl-3">

            <div class="stat-card shadow-sm">

                <div class="d-flex justify-content-between">

                    <div>

                        <small class="text-muted">
                            Total Expenses
                        </small>

                        <h3 class="fw-bold mt-2">
                            <?= money($totalExpenses) ?>
                        </h3>

                        <small class="text-muted">

                            <?= (int)$expenseSummary["total_expenses"] ?>

                            expenses

                        </small>

                    </div>

                    <div class="stat-icon bg-danger-subtle text-danger">

                        <i class="bi bi-cash-stack"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- PROFIT -->

        <div class="col-md-6 col-xl-3">

            <div class="stat-card shadow-sm">

                <div class="d-flex justify-content-between">

                    <div>

                        <small class="text-muted">
                            Estimated Profit
                        </small>

                        <h3 class="fw-bold mt-2
                            <?= $estimatedProfit >= 0
                                ? 'text-success'
                                : 'text-danger' ?>">

                            <?= money($estimatedProfit) ?>

                        </h3>

                        <small class="text-muted">

                            Sales - Purchases - Expenses

                        </small>

                    </div>

                    <div class="stat-icon bg-success-subtle text-success">

                        <i class="bi bi-graph-up-arrow"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         SALES DETAILS
    ================================================== -->

    <div class="row g-4 mb-4">

        <!-- PAYMENT METHODS -->

        <div class="col-lg-6">

            <div class="card shadow-sm report-card">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-credit-card"></i>

                        Sales by Payment Method

                    </h5>

                </div>

                <div class="card-body">

                    <?php if (empty($paymentMethods)): ?>

                        <div class="text-center text-muted py-4">
                            No payment data found.
                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table table-hover">

                                <thead>

                                    <tr>

                                        <th>Method</th>

                                        <th>Sales</th>

                                        <th class="text-end">
                                            Amount
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                <?php foreach ($paymentMethods as $payment): ?>

                                    <tr>

                                        <td>

                                            <span class="badge bg-secondary">

                                                <?= htmlspecialchars(
                                                    ucfirst(
                                                        $payment["payment_method"]
                                                    )
                                                ) ?>

                                            </span>

                                        </td>

                                        <td>
                                            <?= (int)$payment["sale_count"] ?>
                                        </td>

                                        <td class="text-end fw-bold">

                                            <?= money(
                                                $payment["amount"]
                                            ) ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- PAYMENT STATUS -->

        <div class="col-lg-6">

            <div class="card shadow-sm report-card">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-wallet2"></i>

                        Sales Payment Status

                    </h5>

                </div>

                <div class="card-body">

                    <?php if (empty($paymentStatuses)): ?>

                        <div class="text-center text-muted py-4">
                            No payment status data found.
                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table table-hover">

                                <thead>

                                    <tr>

                                        <th>Status</th>

                                        <th>Sales</th>

                                        <th class="text-end">
                                            Amount
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                <?php foreach ($paymentStatuses as $status): ?>

                                    <?php

                                    $statusClass = "secondary";

                                    if ($status["payment_status"] === "paid") {

                                        $statusClass = "success";

                                    } elseif (
                                        $status["payment_status"] === "partial"
                                    ) {

                                        $statusClass = "warning";

                                    } elseif (
                                        $status["payment_status"] === "unpaid"
                                    ) {

                                        $statusClass = "danger";

                                    }

                                    ?>

                                    <tr>

                                        <td>

                                            <span
                                                class="badge bg-<?= $statusClass ?>">

                                                <?= htmlspecialchars(
                                                    ucfirst(
                                                        $status["payment_status"]
                                                    )
                                                ) ?>

                                            </span>

                                        </td>

                                        <td>

                                            <?= (int)$status["sale_count"] ?>

                                        </td>

                                        <td class="text-end fw-bold">

                                            <?= money(
                                                $status["amount"]
                                            ) ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         TOP PRODUCTS + LOW STOCK
    ================================================== -->

    <div class="row g-4 mb-4">

        <!-- TOP PRODUCTS -->

        <div class="col-lg-7">

            <div class="card shadow-sm">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-trophy"></i>

                        Top Selling Products

                    </h5>

                </div>

                <div class="card-body">

                    <?php if (empty($topProducts)): ?>

                        <div class="text-center text-muted py-4">
                            No sales found for this period.
                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table table-hover align-middle">

                                <thead class="table-light">

                                    <tr>

                                        <th>#</th>

                                        <th>Product</th>

                                        <th>Quantity</th>

                                        <th class="text-end">
                                            Sales
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                <?php

                                $rank = 1;

                                foreach ($topProducts as $product):

                                ?>

                                    <tr>

                                        <td>
                                            <strong>
                                                <?= $rank++ ?>
                                            </strong>
                                        </td>

                                        <td>

                                            <?= htmlspecialchars(
                                                $product["product_name"]
                                            ) ?>

                                        </td>

                                        <td>

                                            <?= number_format(
                                                (float)$product["total_quantity"],
                                                2
                                            ) ?>

                                        </td>

                                        <td class="text-end fw-bold">

                                            <?= money(
                                                $product["total_amount"]
                                            ) ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- LOW STOCK -->

        <div class="col-lg-5">

            <div class="card shadow-sm">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-exclamation-triangle"></i>

                        Low Stock Products

                    </h5>

                </div>

                <div class="card-body">

                    <?php if (empty($lowStockProducts)): ?>

                        <div class="text-center text-success py-4">

                            <i class="bi bi-check-circle fs-2"></i>

                            <p class="mb-0 mt-2">

                                All products have sufficient stock.

                            </p>

                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table table-hover align-middle">

                                <thead class="table-light">

                                    <tr>

                                        <th>Product</th>

                                        <th>Stock</th>

                                        <th>Minimum</th>

                                    </tr>

                                </thead>

                                <tbody>

                                <?php foreach (
                                    $lowStockProducts
                                    as $product
                                ): ?>

                                    <tr>

                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $product["product_name"]
                                                ) ?>

                                            </strong>

                                        </td>

                                        <td>

                                            <span class="badge bg-danger">

                                                <?= number_format(
                                                    (float)$product["stock_quantity"],
                                                    2
                                                ) ?>

                                                <?= htmlspecialchars(
                                                    $product["unit"]
                                                ) ?>

                                            </span>

                                        </td>

                                        <td>

                                            <?= number_format(
                                                (float)$product["minimum_stock"],
                                                2
                                            ) ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         CASH / DUE / STOCK
    ================================================== -->

    <div class="row g-4">

        <!-- SALES PAID -->

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <span class="text-muted">
                            Sales Paid
                        </span>

                        <i class="bi bi-check-circle text-success"></i>

                    </div>

                    <h4 class="fw-bold mt-2 text-success">

                        <?= money(
                            $salesSummary["total_paid"]
                        ) ?>

                    </h4>

                </div>

            </div>

        </div>


        <!-- CUSTOMER DUE -->

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <span class="text-muted">
                            Customer Due
                        </span>

                        <i class="bi bi-clock-history text-danger"></i>

                    </div>

                    <h4 class="fw-bold mt-2 text-danger">

                        <?= money(
                            $salesSummary["total_due"]
                        ) ?>

                    </h4>

                </div>

            </div>

        </div>


        <!-- PRODUCT STOCK -->

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <span class="text-muted">
                            Current Product Stock
                        </span>

                        <i class="bi bi-box-seam text-primary"></i>

                    </div>

                    <h4 class="fw-bold mt-2">

                        <?= number_format(
                            (float)$productSummary["total_stock"],
                            2
                        ) ?>

                    </h4>

                    <small class="text-muted">

                        <?= (int)$productSummary["total_products"] ?>

                        available products

                    </small>

                </div>

            </div>

        </div>

    </div>

</div>



    <!-- javascript links  -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>




</body>

</html>
