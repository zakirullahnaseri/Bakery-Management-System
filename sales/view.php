<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";


/* =====================================================
   GET SALE ID
===================================================== */

$sale_id = isset($_GET["sale_id"])
    ? (int) $_GET["sale_id"]
    : 0;

if ($sale_id <= 0) {
    header("Location: index.php");
    exit;
}


/* =====================================================
   GET SALE
===================================================== */

$saleStmt = $conn->prepare("
    SELECT
        s.sale_id,
        s.sale_date,
        s.total_amount,
        s.discount,
        s.paid_amount,
        s.due_amount,
        s.payment_method,
        s.payment_status,
        s.notes,
        c.name AS customer_name,
        u.full_name AS user_name
    FROM sales s

    LEFT JOIN customers c
        ON s.customer_id = c.customer_id

    LEFT JOIN users u
        ON s.user_id = u.user_id

    WHERE s.sale_id = :sale_id
");

$saleStmt->execute([
    ":sale_id" => $sale_id
]);

$sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    header("Location: index.php");
    exit;
}


/* =====================================================
   GET SALE ITEMS
===================================================== */

$itemStmt = $conn->prepare("
    SELECT
        si.sale_item_id,
        si.quantity,
        si.unit_price,
        si.discount,
        si.total_price,
        p.product_name,
        p.unit
    FROM sale_items si

    INNER JOIN products p
        ON si.product_id = p.product_id

    WHERE si.sale_id = :sale_id

    ORDER BY si.sale_item_id ASC
");

$itemStmt->execute([
    ":sale_id" => $sale_id
]);

$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   ITEMS SUBTOTAL
===================================================== */

$itemsSubtotal = 0;

foreach ($items as $item) {
    $itemsSubtotal += (float) $item["total_price"];
}


/* =====================================================
   STATUS CLASS
===================================================== */

$statusClass = "secondary";

if ($sale["payment_status"] === "paid") {
    $statusClass = "success";
} elseif ($sale["payment_status"] === "partial") {
    $statusClass = "warning";
} elseif ($sale["payment_status"] === "unpaid") {
    $statusClass = "danger";
}


/* =====================================================
   TRANSLATED PAYMENT VALUES
===================================================== */

$paymentMethodText = $sale["payment_method"];

if ($sale["payment_method"] === "cash") {
    $paymentMethodText = t("cash");
} elseif ($sale["payment_method"] === "card") {
    $paymentMethodText = t("card");
} elseif ($sale["payment_method"] === "bank") {
    $paymentMethodText = t("bank");
} elseif ($sale["payment_method"] === "credit") {
    $paymentMethodText = t("credit");
}


$paymentStatusText = $sale["payment_status"];

if ($sale["payment_status"] === "paid") {
    $paymentStatusText = t("payment_paid");
} elseif ($sale["payment_status"] === "partial") {
    $paymentStatusText = t("payment_partial");
} elseif ($sale["payment_status"] === "unpaid") {
    $paymentStatusText = t("payment_unpaid");
}

?>

<!DOCTYPE html>

<html
    lang="<?= currentLanguage() ?>"
    dir="<?= languageDirection() ?>"
>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= t("sale") ?> #<?= $sale_id ?> -
        <?= t("bakery_management") ?>
    </title>


    <!-- Bootstrap -->

    <link
        rel="stylesheet"
        href="../assets/css/bootstrap.min.css"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="../assets/css/bootstrap-icons.css"
    >


    <style>

        body {
            background-color: #f5f6fa;
        }

        .card {
            border: none;
            border-radius: 14px;
        }

        .invoice-header {
            border-bottom: 2px solid #eee;
        }

        .sale-total {
            font-size: 22px;
            font-weight: bold;
        }

        .invoice-title {
            font-weight: 700;
        }

        [dir="rtl"] {
            text-align: right;
        }

        [dir="rtl"] .text-end {
            text-align: left !important;
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

            .container {
                max-width: 100% !important;
                width: 100% !important;
            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar navbar-light bg-white border-bottom no-print">

    <div class="container-fluid px-4">

        <a
            href="index.php"
            class="navbar-brand fw-bold"
        >

            <i class="bi bi-shop"></i>

            <?= t("bakery_management") ?>

        </a>


        <div>

            <a
                href="index.php"
                class="btn btn-secondary btn-sm me-2"
            >

                <i class="bi bi-arrow-left"></i>

                <?= t("back_to_sales") ?>

            </a>


            <button
                type="button"
                onclick="window.print()"
                class="btn btn-primary btn-sm"
            >

                <i class="bi bi-printer"></i>

                <?= t("print_sale") ?>

            </button>

        </div>

    </div>

</nav>


<!-- =====================================================
     MAIN
===================================================== -->

<div class="container py-4">


    <!-- =================================================
         INVOICE HEADER
    ================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <div
                class="d-flex justify-content-between align-items-start invoice-header pb-3 mb-4"
            >

                <div>

                    <h2 class="invoice-title">

                        <i class="bi bi-receipt"></i>

                        <?= t("sale_details") ?>

                    </h2>


                    <p class="text-muted mb-0">

                        <?= t("sale_number") ?>:

                        #<?= $sale_id ?>

                    </p>

                </div>


                <div class="text-end">

                    <h5 class="mb-1">

                        <?= t("bakery_management_system") ?>

                    </h5>

                    <small class="text-muted">

                        <?= t("sales") ?>

                    </small>

                </div>

            </div>


            <div class="row g-4">


                <!-- Customer -->

                <div class="col-md-4">

                    <div class="text-muted small">

                        <?= t("customer") ?>

                    </div>

                    <strong>

                        <?= htmlspecialchars(
                            $sale["customer_name"]
                            ?? t("walk_in_customer")
                        ) ?>

                    </strong>

                </div>


                <!-- Sale Date -->

                <div class="col-md-4">

                    <div class="text-muted small">

                        <?= t("sale_date") ?>

                    </div>

                    <strong>

                        <?= htmlspecialchars(
                            $sale["sale_date"]
                        ) ?>

                    </strong>

                </div>


                <!-- Created By -->

                <div class="col-md-4">

                    <div class="text-muted small">

                        <?= t("user") ?>

                    </div>

                    <strong>

                        <?= htmlspecialchars(
                            $sale["user_name"]
                            ?? "Unknown"
                        ) ?>

                    </strong>

                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         SALE ITEMS
    ================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">

                <i class="bi bi-box-seam"></i>

                <?= t("sale_items") ?>

            </h5>

        </div>


        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>
                                <?= t("product") ?>
                            </th>

                            <th>
                                <?= t("quantity") ?>
                            </th>

                            <th>
                                <?= t("unit_price") ?>
                            </th>

                            <th>
                                <?= t("discount") ?>
                            </th>

                            <th>
                                <?= t("total") ?>
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php if (empty($items)): ?>

                            <tr>

                                <td
                                    colspan="6"
                                    class="text-center text-muted py-4"
                                >

                                    <i
                                        class="bi bi-box-seam fs-2"
                                    ></i>

                                    <div class="mt-2">

                                        <?= t("no_sales_found") ?>

                                    </div>

                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach (
                                $items as $index => $item
                            ): ?>

                                <tr>


                                    <td>

                                        <?= $index + 1 ?>

                                    </td>


                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $item["product_name"]
                                            ) ?>

                                        </strong>


                                        <small
                                            class="text-muted d-block"
                                        >

                                            <?= t("unit") ?>:

                                            <?= htmlspecialchars(
                                                $item["unit"]
                                            ) ?>

                                        </small>

                                    </td>


                                    <td>

                                        <?= number_format(
                                            (float)
                                            $item["quantity"],
                                            2
                                        ) ?>

                                    </td>


                                    <td>

                                        $<?= number_format(
                                            (float)
                                            $item["unit_price"],
                                            2
                                        ) ?>

                                    </td>


                                    <td>

                                        $<?= number_format(
                                            (float)
                                            $item["discount"],
                                            2
                                        ) ?>

                                    </td>


                                    <td>

                                        <strong>

                                            $<?= number_format(
                                                (float)
                                                $item["total_price"],
                                                2
                                            ) ?>

                                        </strong>

                                    </td>


                                </tr>

                            <?php endforeach; ?>


                        <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- =================================================
         PAYMENT SUMMARY
    ================================================== -->

    <div class="row justify-content-end">

        <div class="col-md-5">

            <div class="card shadow-sm">


                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-calculator"></i>

                        <?= t("payment") ?>

                    </h5>

                </div>


                <div class="card-body">


                    <!-- Subtotal -->

                    <div
                        class="d-flex justify-content-between mb-3"
                    >

                        <span>

                            <?= t("subtotal") ?>

                        </span>


                        <strong>

                            $<?= number_format(
                                $itemsSubtotal,
                                2
                            ) ?>

                        </strong>

                    </div>


                    <!-- Discount -->

                    <div
                        class="d-flex justify-content-between mb-3"
                    >

                        <span>

                            <?= t("discount") ?>

                        </span>


                        <strong>

                            $<?= number_format(
                                (float)
                                $sale["discount"],
                                2
                            ) ?>

                        </strong>

                    </div>


                    <hr>


                    <!-- Grand Total -->

                    <div
                        class="d-flex justify-content-between mb-3"
                    >

                        <span class="fw-bold">

                            <?= t("grand_total") ?>

                        </span>


                        <strong
                            class="text-primary sale-total"
                        >

                            $<?= number_format(
                                (float)
                                $sale["total_amount"],
                                2
                            ) ?>

                        </strong>

                    </div>


                    <!-- Paid -->

                    <div
                        class="d-flex justify-content-between mb-3"
                    >

                        <span>

                            <?= t("paid_amount") ?>

                        </span>


                        <strong class="text-success">

                            $<?= number_format(
                                (float)
                                $sale["paid_amount"],
                                2
                            ) ?>

                        </strong>

                    </div>


                    <!-- Due -->

                    <div
                        class="d-flex justify-content-between mb-3"
                    >

                        <span>

                            <?= t("due_amount") ?>

                        </span>


                        <strong class="text-danger">

                            $<?= number_format(
                                (float)
                                $sale["due_amount"],
                                2
                            ) ?>

                        </strong>

                    </div>


                    <!-- Payment Method -->

                    <div
                        class="d-flex justify-content-between mb-3"
                    >

                        <span>

                            <?= t("payment_method") ?>

                        </span>


                        <span class="badge bg-secondary">

                            <?= htmlspecialchars(
                                $paymentMethodText
                            ) ?>

                        </span>

                    </div>


                    <!-- Payment Status -->

                    <div
                        class="d-flex justify-content-between"
                    >

                        <span>

                            <?= t("payment_status") ?>

                        </span>


                        <span
                            class="badge bg-<?= $statusClass ?>"
                        >

                            <?= htmlspecialchars(
                                $paymentStatusText
                            ) ?>

                        </span>

                    </div>


                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         NOTES
    ================================================== -->

    <?php if (!empty($sale["notes"])): ?>

        <div class="card shadow-sm mt-4">

            <div class="card-header bg-white">

                <strong>

                    <i class="bi bi-sticky"></i>

                    <?= t("notes") ?>

                </strong>

            </div>


            <div class="card-body">

                <?= nl2br(
                    htmlspecialchars(
                        $sale["notes"]
                    )
                ) ?>

            </div>

        </div>

    <?php endif; ?>


    <!-- =================================================
         FOOTER
    ================================================== -->

    <div class="text-center text-muted mt-4">

        <small>

            <?= t("bakery_management_system") ?>

        </small>

    </div>


</div>


</body>

</html>