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
   SUCCESS / ERROR
===================================================== */

$success = "";
$error = "";


/* =====================================================
   UPDATE SALE
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        $customer_id = !empty($_POST["customer_id"])
            ? (int) $_POST["customer_id"]
            : null;

        $discount = isset($_POST["discount"])
            ? (float) $_POST["discount"]
            : 0;

        $paid_amount = isset($_POST["paid_amount"])
            ? (float) $_POST["paid_amount"]
            : 0;

        $payment_method = $_POST["payment_method"] ?? "cash";

        $notes = trim($_POST["notes"] ?? "");

        $product_ids = $_POST["product_id"] ?? [];
        $quantities = $_POST["quantity"] ?? [];
        $unit_prices = $_POST["unit_price"] ?? [];


        /* -------------------------------------------------
           VALIDATION
        ------------------------------------------------- */

        $allowed_payment_methods = [
            "cash",
            "card",
            "bank",
            "credit"
        ];

        if (!in_array(
            $payment_method,
            $allowed_payment_methods,
            true
        )) {
            throw new Exception(
                t("invalid_payment_method")
            );
        }


        if (empty($product_ids)) {
            throw new Exception(
                t("please_add_product")
            );
        }


        /* -------------------------------------------------
           GET OLD SALE ITEMS
        ------------------------------------------------- */

        $oldStmt = $conn->prepare("
            SELECT
                product_id,
                quantity
            FROM sale_items
            WHERE sale_id = ?
        ");

        $oldStmt->execute([
            $sale_id
        ]);

        $oldItems = $oldStmt->fetchAll(PDO::FETCH_ASSOC);


        /* -------------------------------------------------
           START TRANSACTION
        ------------------------------------------------- */

        $conn->beginTransaction();


        /* -------------------------------------------------
           RESTORE OLD STOCK
        ------------------------------------------------- */

        foreach ($oldItems as $oldItem) {

            $restoreStmt = $conn->prepare("
                UPDATE products
                SET stock_quantity =
                    stock_quantity + ?
                WHERE product_id = ?
            ");

            $restoreStmt->execute([
                (float) $oldItem["quantity"],
                (int) $oldItem["product_id"]
            ]);
        }


        /* -------------------------------------------------
           DELETE OLD SALE ITEMS
        ------------------------------------------------- */

        $deleteItemsStmt = $conn->prepare("
            DELETE FROM sale_items
            WHERE sale_id = ?
        ");

        $deleteItemsStmt->execute([
            $sale_id
        ]);


        /* -------------------------------------------------
           BUILD NEW ITEMS
        ------------------------------------------------- */

        $subtotal = 0;

        $newItems = [];

        $usedProducts = [];


        foreach ($product_ids as $i => $product_id) {

            $product_id = (int) $product_id;

            $quantity = isset($quantities[$i])
                ? (float) $quantities[$i]
                : 0;

            $unit_price = isset($unit_prices[$i])
                ? (float) $unit_prices[$i]
                : 0;


            if ($product_id <= 0) {
                throw new Exception(
                    t("select_valid_product")
                );
            }


            if ($quantity <= 0) {
                throw new Exception(
                    t("invalid_quantity")
                );
            }


            /* Duplicate Product Check */

            if (in_array(
                $product_id,
                $usedProducts,
                true
            )) {
                throw new Exception(
                    t("duplicate_product")
                );
            }

            $usedProducts[] = $product_id;


            /* -------------------------------------------------
               GET PRODUCT
            ------------------------------------------------- */

            $productStmt = $conn->prepare("
                SELECT
                    product_id,
                    product_name,
                    selling_price,
                    stock_quantity
                FROM products
                WHERE product_id = ?
                FOR UPDATE
            ");

            $productStmt->execute([
                $product_id
            ]);

            $product = $productStmt->fetch(PDO::FETCH_ASSOC);


            if (!$product) {
                throw new Exception(
                    t("product_not_found")
                );
            }


            /*
             * Use current selling price if posted
             * price is invalid.
             */

            if ($unit_price <= 0) {
                $unit_price =
                    (float) $product["selling_price"];
            }


            /* -------------------------------------------------
               CHECK STOCK
            ------------------------------------------------- */

            $availableStock =
                (float) $product["stock_quantity"];


            if ($quantity > $availableStock) {

                throw new Exception(
                    t("not_enough_stock")
                    . " - "
                    . $product["product_name"]
                    . " ("
                    . t("available_stock")
                    . ": "
                    . number_format(
                        $availableStock,
                        2
                    )
                    . ")"
                );
            }


            /* -------------------------------------------------
               ITEM TOTAL
            ------------------------------------------------- */

            $itemTotal =
                $quantity * $unit_price;

            $subtotal += $itemTotal;


            $newItems[] = [
                "product_id" => $product_id,
                "quantity" => $quantity,
                "unit_price" => $unit_price,
                "discount" => 0,
                "total_price" => $itemTotal
            ];
        }


        /* -------------------------------------------------
           DISCOUNT
        ------------------------------------------------- */

        if ($discount < 0) {
            $discount = 0;
        }

        if ($discount > $subtotal) {
            $discount = $subtotal;
        }


        /* -------------------------------------------------
           GRAND TOTAL
        ------------------------------------------------- */

        $grandTotal =
            $subtotal - $discount;


        /* -------------------------------------------------
           PAID AMOUNT
        ------------------------------------------------- */

        if ($paid_amount < 0) {
            $paid_amount = 0;
        }

        if ($paid_amount > $grandTotal) {
            $paid_amount = $grandTotal;
        }


        /* -------------------------------------------------
           DUE
        ------------------------------------------------- */

        $due_amount =
            $grandTotal - $paid_amount;


        /* -------------------------------------------------
           PAYMENT STATUS
        ------------------------------------------------- */

        if ($due_amount <= 0) {

            $payment_status = "paid";

        } elseif ($paid_amount > 0) {

            $payment_status = "partial";

        } else {

            $payment_status = "unpaid";
        }


        /* -------------------------------------------------
           UPDATE SALES
        ------------------------------------------------- */

        $updateSaleStmt = $conn->prepare("
            UPDATE sales
            SET
                customer_id = ?,
                total_amount = ?,
                discount = ?,
                paid_amount = ?,
                due_amount = ?,
                payment_method = ?,
                payment_status = ?,
                notes = ?
            WHERE sale_id = ?
        ");

        $updateSaleStmt->execute([
            $customer_id,
            $grandTotal,
            $discount,
            $paid_amount,
            $due_amount,
            $payment_method,
            $payment_status,
            $notes,
            $sale_id
        ]);


        /* -------------------------------------------------
           INSERT NEW ITEMS + REDUCE STOCK
        ------------------------------------------------- */

        $insertItemStmt = $conn->prepare("
            INSERT INTO sale_items (
                sale_id,
                product_id,
                quantity,
                unit_price,
                discount,
                total_price
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");


        $stockStmt = $conn->prepare("
            UPDATE products
            SET stock_quantity =
                stock_quantity - ?
            WHERE product_id = ?
              AND stock_quantity >= ?
        ");


        foreach ($newItems as $item) {

            $insertItemStmt->execute([
                $sale_id,
                $item["product_id"],
                $item["quantity"],
                $item["unit_price"],
                $item["discount"],
                $item["total_price"]
            ]);


            $stockStmt->execute([
                $item["quantity"],
                $item["product_id"],
                $item["quantity"]
            ]);


            if ($stockStmt->rowCount() !== 1) {

                throw new Exception(
                    t("stock_update_failed")
                );
            }
        }


        /* -------------------------------------------------
           COMMIT
        ------------------------------------------------- */

        $conn->commit();


        header(
            "Location: view.php?sale_id="
            . $sale_id
            . "&updated=1"
        );

        exit;


    } catch (Exception $e) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        $error = $e->getMessage();
    }
}


/* =====================================================
   GET SALE
===================================================== */

$saleStmt = $conn->prepare("
    SELECT
        sale_id,
        customer_id,
        total_amount,
        discount,
        paid_amount,
        due_amount,
        payment_method,
        payment_status,
        notes,
        sale_date
    FROM sales
    WHERE sale_id = ?
");

$saleStmt->execute([
    $sale_id
]);

$sale = $saleStmt->fetch(PDO::FETCH_ASSOC);


if (!$sale) {
    header("Location: index.php");
    exit;
}


/* =====================================================
   GET CUSTOMERS
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
   GET PRODUCTS
===================================================== */

$productStmt = $conn->query("
    SELECT
        product_id,
        product_name,
        selling_price,
        stock_quantity,
        unit
    FROM products
    ORDER BY product_name ASC
");

$products = $productStmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   GET SALE ITEMS
===================================================== */

$itemStmt = $conn->prepare("
    SELECT
        si.product_id,
        si.quantity,
        si.unit_price,
        si.discount,
        si.total_price,
        p.product_name,
        p.stock_quantity,
        p.unit
    FROM sale_items si

    INNER JOIN products p
        ON si.product_id = p.product_id

    WHERE si.sale_id = ?

    ORDER BY si.sale_item_id ASC
");

$itemStmt->execute([
    $sale_id
]);

$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

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
        <?= t("edit_sale") ?>
        #<?= $sale_id ?>
    </title>


    <link
        rel="stylesheet"
        href="../assets/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/bootstrap-icons.css"
    >


    <style>

        body {
            background: #f5f6fa;
        }

        .card {
            border: none;
            border-radius: 14px;
        }

        [dir="rtl"] {
            text-align: right;
        }

        [dir="rtl"] .text-end {
            text-align: left !important;
        }

        .remove-row {
            cursor: pointer;
        }

    </style>

</head>


<body>


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar navbar-light bg-white border-bottom">

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
                class="btn btn-secondary btn-sm"
            >

                <i class="bi bi-arrow-left"></i>

                <?= t("back_to_sales") ?>

            </a>

        </div>

    </div>

</nav>


<div class="container py-4">


    <!-- =================================================
         TITLE
    ================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <h3 class="mb-1">

                <i class="bi bi-pencil-square"></i>

                <?= t("edit_sale") ?>

                #<?= $sale_id ?>

            </h3>

            <div class="text-muted">

                <?= t("sale_date") ?>:

                <?= htmlspecialchars(
                    $sale["sale_date"]
                ) ?>

            </div>

        </div>

    </div>


    <!-- =================================================
         ERROR
    ================================================== -->

    <?php if (!empty($error)): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         FORM
    ================================================== -->

    <form method="POST">


        <!-- =================================================
             CUSTOMER
        ================================================== -->

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">

                <h5 class="mb-0">

                    <i class="bi bi-person"></i>

                    <?= t("customer") ?>

                </h5>

            </div>


            <div class="card-body">

                <select
                    name="customer_id"
                    class="form-select"
                >

                    <option value="">

                        <?= t("walk_in_customer") ?>

                    </option>


                    <?php foreach ($customers as $customer): ?>

                        <option
                            value="<?= $customer["customer_id"] ?>"
                            <?= (
                                (int)
                                $sale["customer_id"]
                                ===
                                (int)
                                $customer["customer_id"]
                            )
                                ? "selected"
                                : ""
                            ?>
                        >

                            <?= htmlspecialchars(
                                $customer["name"]
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>


        <!-- =================================================
             PRODUCTS
        ================================================== -->

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">

                <div
                    class="d-flex justify-content-between align-items-center"
                >

                    <h5 class="mb-0">

                        <i class="bi bi-box-seam"></i>

                        <?= t("sale_items") ?>

                    </h5>


                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        id="addProductBtn"
                    >

                        <i class="bi bi-plus"></i>

                        <?= t("add_product") ?>

                    </button>

                </div>

            </div>


            <div class="card-body">


                <div
                    id="productRows"
                >


                    <?php foreach ($items as $item): ?>

                        <div class="product-row border rounded p-3 mb-3">

                            <div class="row g-3 align-items-end">


                                <!-- Product -->

                                <div class="col-md-4">

                                    <label class="form-label">

                                        <?= t("product") ?>

                                    </label>


                                    <select
                                        name="product_id[]"
                                        class="form-select product-select"
                                        required
                                    >

                                        <option value="">

                                            <?= t("select_product") ?>

                                        </option>


                                        <?php foreach ($products as $product): ?>

                                            <option
                                                value="<?= $product["product_id"] ?>"
                                                data-price="<?= htmlspecialchars($product["selling_price"]) ?>"
                                                data-stock="<?= htmlspecialchars($product["stock_quantity"]) ?>"
                                                data-unit="<?= htmlspecialchars($product["unit"]) ?>"
                                                <?= (
                                                    (int)
                                                    $item["product_id"]
                                                    ===
                                                    (int)
                                                    $product["product_id"]
                                                )
                                                    ? "selected"
                                                    : ""
                                                ?>
                                            >

                                                <?= htmlspecialchars(
                                                    $product["product_name"]
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <!-- Quantity -->

                                <div class="col-md-2">

                                    <label class="form-label">

                                        <?= t("quantity") ?>

                                    </label>


                                    <input
                                        type="number"
                                        name="quantity[]"
                                        class="form-control quantity-input"
                                        value="<?= htmlspecialchars($item["quantity"]) ?>"
                                        min="0.01"
                                        step="0.01"
                                        required
                                    >

                                </div>


                                <!-- Unit Price -->

                                <div class="col-md-2">

                                    <label class="form-label">

                                        <?= t("unit_price") ?>

                                    </label>


                                    <input
                                        type="number"
                                        name="unit_price[]"
                                        class="form-control price-input"
                                        value="<?= htmlspecialchars($item["unit_price"]) ?>"
                                        min="0"
                                        step="0.01"
                                        required
                                    >

                                </div>


                                <!-- Total -->

                                <div class="col-md-2">

                                    <label class="form-label">

                                        <?= t("total") ?>

                                    </label>


                                    <input
                                        type="text"
                                        class="form-control row-total"
                                        value="<?= number_format((float)$item["total_price"], 2, ".", "") ?>"
                                        readonly
                                    >

                                </div>


                                <!-- Remove -->

                                <div class="col-md-2">

                                    <button
                                        type="button"
                                        class="btn btn-danger w-100 remove-row"
                                    >

                                        <i class="bi bi-trash"></i>

                                        <?= t("remove_product") ?>

                                    </button>

                                </div>


                            </div>

                        </div>

                    <?php endforeach; ?>


                </div>


                <!-- Template -->

                <template id="productTemplate">

                    <div class="product-row border rounded p-3 mb-3">

                        <div class="row g-3 align-items-end">


                            <div class="col-md-4">

                                <label class="form-label">

                                    <?= t("product") ?>

                                </label>


                                <select
                                    name="product_id[]"
                                    class="form-select product-select"
                                    required
                                >

                                    <option value="">

                                        <?= t("select_product") ?>

                                    </option>


                                    <?php foreach ($products as $product): ?>

                                        <option
                                            value="<?= $product["product_id"] ?>"
                                            data-price="<?= htmlspecialchars($product["selling_price"]) ?>"
                                            data-stock="<?= htmlspecialchars($product["stock_quantity"]) ?>"
                                            data-unit="<?= htmlspecialchars($product["unit"]) ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $product["product_name"]
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="col-md-2">

                                <label class="form-label">

                                    <?= t("quantity") ?>

                                </label>


                                <input
                                    type="number"
                                    name="quantity[]"
                                    class="form-control quantity-input"
                                    value="1"
                                    min="0.01"
                                    step="0.01"
                                    required
                                >

                            </div>


                            <div class="col-md-2">

                                <label class="form-label">

                                    <?= t("unit_price") ?>

                                </label>


                                <input
                                    type="number"
                                    name="unit_price[]"
                                    class="form-control price-input"
                                    value="0"
                                    min="0"
                                    step="0.01"
                                    required
                                >

                            </div>


                            <div class="col-md-2">

                                <label class="form-label">

                                    <?= t("total") ?>

                                </label>


                                <input
                                    type="text"
                                    class="form-control row-total"
                                    value="0.00"
                                    readonly
                                >

                            </div>


                            <div class="col-md-2">

                                <button
                                    type="button"
                                    class="btn btn-danger w-100 remove-row"
                                >

                                    <i class="bi bi-trash"></i>

                                    <?= t("remove_product") ?>

                                </button>

                            </div>


                        </div>

                    </div>

                </template>


            </div>

        </div>


        <!-- =================================================
             PAYMENT
        ================================================== -->

        <div class="row">


            <div class="col-md-7">

                <div class="card shadow-sm mb-4">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-credit-card"></i>

                            <?= t("payment") ?>

                        </h5>

                    </div>


                    <div class="card-body">


                        <div class="row g-3">


                            <!-- Discount -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    <?= t("discount") ?>

                                </label>


                                <input
                                    type="number"
                                    name="discount"
                                    id="discount"
                                    class="form-control"
                                    value="<?= htmlspecialchars($sale["discount"]) ?>"
                                    min="0"
                                    step="0.01"
                                >

                            </div>


                            <!-- Paid -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    <?= t("paid_amount") ?>

                                </label>


                                <input
                                    type="number"
                                    name="paid_amount"
                                    id="paid_amount"
                                    class="form-control"
                                    value="<?= htmlspecialchars($sale["paid_amount"]) ?>"
                                    min="0"
                                    step="0.01"
                                >

                            </div>


                            <!-- Payment Method -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    <?= t("payment_method") ?>

                                </label>


                                <select
                                    name="payment_method"
                                    class="form-select"
                                >

                                    <option
                                        value="cash"
                                        <?= $sale["payment_method"] === "cash"
                                            ? "selected"
                                            : ""
                                        ?>
                                    >

                                        <?= t("cash") ?>

                                    </option>


                                    <option
                                        value="card"
                                        <?= $sale["payment_method"] === "card"
                                            ? "selected"
                                            : ""
                                        ?>
                                    >

                                        <?= t("card") ?>

                                    </option>


                                    <option
                                        value="bank"
                                        <?= $sale["payment_method"] === "bank"
                                            ? "selected"
                                            : ""
                                        ?>
                                    >

                                        <?= t("bank") ?>

                                    </option>


                                    <option
                                        value="credit"
                                        <?= $sale["payment_method"] === "credit"
                                            ? "selected"
                                            : ""
                                        ?>
                                    >

                                        <?= t("credit") ?>

                                    </option>

                                </select>

                            </div>


                            <!-- Notes -->

                            <div class="col-12">

                                <label class="form-label">

                                    <?= t("notes") ?>

                                </label>


                                <textarea
                                    name="notes"
                                    class="form-control"
                                    rows="3"
                                ><?= htmlspecialchars($sale["notes"] ?? "") ?></textarea>

                            </div>


                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 TOTAL
            ================================================== -->

            <div class="col-md-5">

                <div class="card shadow-sm mb-4">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-calculator"></i>

                            <?= t("payment") ?>

                        </h5>

                    </div>


                    <div class="card-body">


                        <div
                            class="d-flex justify-content-between mb-3"
                        >

                            <span>

                                <?= t("subtotal") ?>

                            </span>


                            <strong>

                                $<span id="subtotal">0.00</span>

                            </strong>

                        </div>


                        <div
                            class="d-flex justify-content-between mb-3"
                        >

                            <span>

                                <?= t("discount") ?>

                            </span>


                            <strong>

                                $<span id="discountDisplay">0.00</span>

                            </strong>

                        </div>


                        <hr>


                        <div
                            class="d-flex justify-content-between mb-3"
                        >

                            <span class="fw-bold">

                                <?= t("grand_total") ?>

                            </span>


                            <strong class="text-primary fs-4">

                                $<span id="grandTotal">0.00</span>

                            </strong>

                        </div>


                        <div
                            class="d-flex justify-content-between"
                        >

                            <span>

                                <?= t("due_amount") ?>

                            </span>


                            <strong class="text-danger">

                                $<span id="dueAmount">0.00</span>

                            </strong>

                        </div>


                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             SAVE
        ================================================== -->

        <div class="text-center mb-5">

            <button
                type="submit"
                class="btn btn-success btn-lg px-5"
            >

                <i class="bi bi-check-circle"></i>

                <?= t("edit_sale") ?>

            </button>


            <a
                href="view.php?sale_id=<?= $sale_id ?>"
                class="btn btn-secondary btn-lg px-4"
            >

                <i class="bi bi-x-circle"></i>

                <?= t("back_to_sales") ?>

            </a>

        </div>


    </form>

</div>


<script>

const productRows =
    document.getElementById("productRows");

const productTemplate =
    document.getElementById("productTemplate");

const addProductBtn =
    document.getElementById("addProductBtn");

const discountInput =
    document.getElementById("discount");

const paidInput =
    document.getElementById("paid_amount");


/* =====================================================
   ADD PRODUCT
===================================================== */

addProductBtn.addEventListener("click", function () {

    const clone =
        productTemplate.content.cloneNode(true);

    productRows.appendChild(clone);

    updateTotals();

});


/* =====================================================
   PRODUCT CHANGE
===================================================== */

document.addEventListener("change", function (e) {

    if (
        e.target.classList.contains(
            "product-select"
        )
    ) {

        const row =
            e.target.closest(".product-row");

        const option =
            e.target.options[
                e.target.selectedIndex
            ];

        if (!option) {
            return;
        }

        const price =
            option.dataset.price || 0;

        const priceInput =
            row.querySelector(
                ".price-input"
            );

        priceInput.value = price;

        updateRowTotal(row);

        updateTotals();
    }

});


/* =====================================================
   INPUT CHANGE
===================================================== */

document.addEventListener("input", function (e) {

    if (
        e.target.classList.contains(
            "quantity-input"
        ) ||
        e.target.classList.contains(
            "price-input"
        )
    ) {

        const row =
            e.target.closest(".product-row");

        updateRowTotal(row);

        updateTotals();
    }


    if (
        e.target === discountInput ||
        e.target === paidInput
    ) {

        updateTotals();
    }

});


/* =====================================================
   REMOVE ROW
===================================================== */

document.addEventListener("click", function (e) {

    const button =
        e.target.closest(".remove-row");

    if (!button) {
        return;
    }

    const rows =
        document.querySelectorAll(
            ".product-row"
        );

    if (rows.length <= 1) {

        alert(
            "<?= htmlspecialchars(
                t("please_add_product")
            ) ?>"
        );

        return;
    }

    button
        .closest(".product-row")
        .remove();

    updateTotals();

});


/* =====================================================
   ROW TOTAL
===================================================== */

function updateRowTotal(row) {

    if (!row) {
        return;
    }

    const quantity =
        parseFloat(
            row.querySelector(
                ".quantity-input"
            ).value
        ) || 0;

    const price =
        parseFloat(
            row.querySelector(
                ".price-input"
            ).value
        ) || 0;

    const total =
        quantity * price;

    row.querySelector(
        ".row-total"
    ).value = total.toFixed(2);
}


/* =====================================================
   TOTALS
===================================================== */

function updateTotals() {

    let subtotal = 0;

    document
        .querySelectorAll(".product-row")
        .forEach(function (row) {

            updateRowTotal(row);

            const total =
                parseFloat(
                    row.querySelector(
                        ".row-total"
                    ).value
                ) || 0;

            subtotal += total;

        });


    let discount =
        parseFloat(
            discountInput.value
        ) || 0;


    if (discount < 0) {
        discount = 0;
    }


    if (discount > subtotal) {
        discount = subtotal;
        discountInput.value =
            discount.toFixed(2);
    }


    const grandTotal =
        subtotal - discount;


    let paid =
        parseFloat(
            paidInput.value
        ) || 0;


    if (paid < 0) {
        paid = 0;
    }


    if (paid > grandTotal) {
        paid = grandTotal;
        paidInput.value =
            paid.toFixed(2);
    }


    const due =
        grandTotal - paid;


    document.getElementById(
        "subtotal"
    ).textContent =
        subtotal.toFixed(2);


    document.getElementById(
        "discountDisplay"
    ).textContent =
        discount.toFixed(2);


    document.getElementById(
        "grandTotal"
    ).textContent =
        grandTotal.toFixed(2);


    document.getElementById(
        "dueAmount"
    ).textContent =
        due.toFixed(2);
}


/* =====================================================
   INITIAL TOTAL
===================================================== */

updateTotals();

</script>


</body>

</html>