
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

$supplier_id = !empty($_GET["supplier_id"])
    ? (int) $_GET["supplier_id"]
    : 0;

$payment_status = $_GET["payment_status"] ?? "";

$from_date = $_GET["from_date"] ?? "";

$to_date = $_GET["to_date"] ?? "";


/* =====================================================
   GET SUPPLIERS
===================================================== */

$supplierStmt = $conn->query("
    SELECT
        supplier_id,
        name
    FROM suppliers
    ORDER BY name ASC
");

$suppliers = $supplierStmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   BUILD PURCHASE QUERY
===================================================== */

$sql = "
    SELECT
        p.purchase_id,
        p.purchase_date,
        p.total_amount,
        p.paid_amount,
        p.due_amount,
        p.payment_status,
        p.notes,
        s.name AS supplier_name,
        u.full_name AS user_name
    FROM purchases p

    LEFT JOIN suppliers s
        ON p.supplier_id = s.supplier_id

    LEFT JOIN users u
        ON p.user_id = u.user_id

    WHERE 1=1
";

$params = [];


/* SUPPLIER FILTER */

if ($supplier_id > 0) {

    $sql .= "
        AND p.supplier_id = :supplier_id
    ";

    $params[":supplier_id"] = $supplier_id;
}


/* PAYMENT STATUS FILTER */

if (
    in_array(
        $payment_status,
        ["paid", "partial", "unpaid"],
        true
    )
) {

    $sql .= "
        AND p.payment_status = :payment_status
    ";

    $params[":payment_status"] = $payment_status;
}


/* FROM DATE */

if ($from_date !== "") {

    $sql .= "
        AND DATE(p.purchase_date) >= :from_date
    ";

    $params[":from_date"] = $from_date;
}


/* TO DATE */

if ($to_date !== "") {

    $sql .= "
        AND DATE(p.purchase_date) <= :to_date
    ";

    $params[":to_date"] = $to_date;
}


$sql .= "
    ORDER BY p.purchase_id DESC
";


$purchaseStmt = $conn->prepare($sql);

$purchaseStmt->execute($params);

$purchases = $purchaseStmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   CALCULATE TOTALS
===================================================== */

$totalPurchases = count($purchases);

$totalAmount = 0;

$totalPaid = 0;

$totalDue = 0;

$paidCount = 0;

$partialCount = 0;

$unpaidCount = 0;


foreach ($purchases as $purchase) {

    $totalAmount += (float) $purchase["total_amount"];

    $totalPaid += (float) $purchase["paid_amount"];

    $totalDue += (float) $purchase["due_amount"];


    if ($purchase["payment_status"] === "paid") {

        $paidCount++;

    } elseif ($purchase["payment_status"] === "partial") {

        $partialCount++;

    } elseif ($purchase["payment_status"] === "unpaid") {

        $unpaidCount++;
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Purchase Report - Bakery Management</title>

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
            border: none;
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

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-bag-check"></i>

                Purchase Report

            </h2>

            <p class="text-muted mb-0">

                View and analyze bakery purchases

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

                Filter Purchases

            </h5>

        </div>


        <div class="card-body">

            <form method="GET">

                <div class="row g-3">


                    <!-- SUPPLIER -->

                    <div class="col-md-3">

                        <label class="form-label">

                            Supplier

                        </label>

                        <select
                            name="supplier_id"
                            class="form-select">

                            <option value="">

                                All Suppliers

                            </option>


                            <?php foreach ($suppliers as $supplier): ?>

                                <option
                                    value="<?= (int) $supplier["supplier_id"] ?>"
                                    <?= $supplier_id === (int) $supplier["supplier_id"]
                                        ? "selected"
                                        : "" ?>>

                                    <?= htmlspecialchars(
                                        $supplier["name"]
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- PAYMENT STATUS -->

                    <div class="col-md-3">

                        <label class="form-label">

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


                    <!-- FROM DATE -->

                    <div class="col-md-2">

                        <label class="form-label">

                            From Date

                        </label>

                        <input
                            type="date"
                            name="from_date"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $from_date
                            ) ?>">

                    </div>


                    <!-- TO DATE -->

                    <div class="col-md-2">

                        <label class="form-label">

                            To Date

                        </label>

                        <input
                            type="date"
                            name="to_date"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $to_date
                            ) ?>">

                    </div>


                    <!-- BUTTONS -->

                    <div class="col-md-2 d-flex align-items-end">

                        <button
                            type="submit"
                            class="btn btn-primary me-2">

                            <i class="bi bi-search"></i>

                            Filter

                        </button>


                        <a
                            href="purchases.php"
                            class="btn btn-secondary">

                            <i class="bi bi-x-circle"></i>

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


        <!-- TOTAL PURCHASES -->

        <div class="col-md-3">

            <div class="card summary-card shadow-sm">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-1">

                                Total Purchases

                            </p>

                            <h3 class="fw-bold mb-0">

                                <?= $totalPurchases ?>

                            </h3>

                        </div>


                        <div class="summary-icon text-primary">

                            <i class="bi bi-bag-check"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- TOTAL AMOUNT -->

        <div class="col-md-3">

            <div class="card summary-card shadow-sm">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-1">

                                Total Amount

                            </p>

                            <h3 class="fw-bold mb-0">

                                $<?= number_format(
                                    $totalAmount,
                                    2
                                ) ?>

                            </h3>

                        </div>


                        <div class="summary-icon text-info">

                            <i class="bi bi-currency-dollar"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- PAID -->

        <div class="col-md-3">

            <div class="card summary-card shadow-sm">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-1">

                                Total Paid

                            </p>

                            <h3 class="fw-bold text-success mb-0">

                                $<?= number_format(
                                    $totalPaid,
                                    2
                                ) ?>

                            </h3>

                        </div>


                        <div class="summary-icon text-success">

                            <i class="bi bi-check-circle"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- DUE -->

        <div class="col-md-3">

            <div class="card summary-card shadow-sm">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-1">

                                Total Due

                            </p>

                            <h3 class="fw-bold text-danger mb-0">

                                $<?= number_format(
                                    $totalDue,
                                    2
                                ) ?>

                            </h3>

                        </div>


                        <div class="summary-icon text-danger">

                            <i class="bi bi-exclamation-circle"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         STATUS SUMMARY
    ================================================== -->

    <div class="row g-3 mb-4">

        <div class="col-md-4">

            <div class="alert alert-success mb-0">

                <i class="bi bi-check-circle-fill"></i>

                Paid Purchases:

                <strong>

                    <?= $paidCount ?>

                </strong>

            </div>

        </div>


        <div class="col-md-4">

            <div class="alert alert-warning mb-0">

                <i class="bi bi-clock-fill"></i>

                Partial Purchases:

                <strong>

                    <?= $partialCount ?>

                </strong>

            </div>

        </div>


        <div class="col-md-4">

            <div class="alert alert-danger mb-0">

                <i class="bi bi-x-circle-fill"></i>

                Unpaid Purchases:

                <strong>

                    <?= $unpaidCount ?>

                </strong>

            </div>

        </div>

    </div>


    <!-- =================================================
         PURCHASE TABLE
    ================================================== -->

    <div class="card shadow-sm">

        <div class="card-header bg-white py-3">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <h5 class="mb-0">

                        <i class="bi bi-table"></i>

                        Purchase List

                    </h5>

                    <small class="text-muted">

                        Purchase records matching your filters

                    </small>

                </div>


                <span class="badge bg-primary">

                    <?= $totalPurchases ?>

                    Purchases

                </span>

            </div>

        </div>


        <div class="card-body">


            <?php if (empty($purchases)): ?>

                <div class="text-center py-5">

                    <i class="bi bi-bag-x fs-1 text-muted"></i>

                    <h5 class="mt-3">

                        No Purchases Found

                    </h5>

                    <p class="text-muted">

                        No purchase records match your selected filters.

                    </p>

                </div>

            <?php else: ?>


                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>#</th>

                                <th>Date</th>

                                <th>Supplier</th>

                                <th>Created By</th>

                                <th>Total</th>

                                <th>Paid</th>

                                <th>Due</th>

                                <th>Status</th>

                                <th class="no-print text-center">

                                    Action

                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($purchases as $purchase): ?>

                            <tr>


                                <!-- ID -->

                                <td>

                                    <strong>

                                        #<?= (int) $purchase["purchase_id"] ?>

                                    </strong>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $purchase["purchase_date"]
                                    ) ?>

                                </td>


                                <!-- SUPPLIER -->

                                <td>

                                    <?php if (
                                        !empty($purchase["supplier_name"])
                                    ): ?>

                                        <?= htmlspecialchars(
                                            $purchase["supplier_name"]
                                        ) ?>

                                    <?php else: ?>

                                        <span class="text-muted">

                                            No Supplier

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- USER -->

                                <td>

                                    <?= htmlspecialchars(
                                        $purchase["user_name"]
                                            ?? "Unknown"
                                    ) ?>

                                </td>


                                <!-- TOTAL -->

                                <td>

                                    <strong>

                                        $<?= number_format(
                                            (float) $purchase["total_amount"],
                                            2
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- PAID -->

                                <td>

                                    <span class="text-success">

                                        $<?= number_format(
                                            (float) $purchase["paid_amount"],
                                            2
                                        ) ?>

                                    </span>

                                </td>


                                <!-- DUE -->

                                <td>

                                    <?php if (
                                        (float) $purchase["due_amount"] > 0
                                    ): ?>

                                        <span class="text-danger">

                                            $<?= number_format(
                                                (float) $purchase["due_amount"],
                                                2
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="text-success">

                                            $0.00

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php

                                    $statusClass = "secondary";

                                    if (
                                        $purchase["payment_status"] === "paid"
                                    ) {

                                        $statusClass = "success";

                                    } elseif (
                                        $purchase["payment_status"] === "partial"
                                    ) {

                                        $statusClass = "warning";

                                    } elseif (
                                        $purchase["payment_status"] === "unpaid"
                                    ) {

                                        $statusClass = "danger";
                                    }

                                    ?>


                                    <span
                                        class="badge bg-<?= $statusClass ?>">

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $purchase["payment_status"]
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ACTION -->

                                <td class="no-print text-center">

                                    <a
                                        href="../purchases/view.php?id=<?= (int) $purchase["purchase_id"] ?>"
                                        class="btn btn-sm btn-primary"
                                        title="View Purchase">

                                        <i class="bi bi-eye"></i>

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                        </tbody>


                        <!-- TOTAL FOOTER -->

                        <tfoot class="table-light">

                            <tr>

                                <th colspan="4" class="text-end">

                                    Grand Total:

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

