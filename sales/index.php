<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/language.php";

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| DELETE SALE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_sale"])) {

    $saleId = (int)($_POST["sale_id"] ?? 0);

    if ($saleId <= 0) {

        $error = t("invalid_sale_id");
    } else {

        try {

            $conn->beginTransaction();

            /*
            | Get sale items
            */

            $stmt = $conn->prepare("
                SELECT product_id, quantity
                FROM sale_items
                WHERE sale_id = ?
            ");

            $stmt->execute([$saleId]);

            $items = $stmt->fetchAll();

            /*
            | Restore product stock
            */

            foreach ($items as $item) {

                $stmt = $conn->prepare("
                    UPDATE products
                    SET stock_quantity = stock_quantity + ?
                    WHERE product_id = ?
                ");

                $stmt->execute([
                    $item["quantity"],
                    $item["product_id"]
                ]);
            }

            /*
            | Delete sale
            */

            $stmt = $conn->prepare("
                DELETE FROM sales
                WHERE sale_id = ?
            ");

            $stmt->execute([$saleId]);

            if ($stmt->rowCount() === 0) {

                throw new Exception(
                    t("sale_not_found")
                );
            }

            /*
            | Commit
            */

            $conn->commit();

            /*
            | IMPORTANT:
            | Redirect after DELETE
            | This prevents duplicate POST when refreshing.
            */

            header("Location: index.php?deleted=1");
            exit;
        } catch (Exception $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| CREATE SALE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_sale"])) {

    $customerId = !empty($_POST["customer_id"])
        ? (int)$_POST["customer_id"]
        : null;

    $discount = max(
        0,
        (float)($_POST["discount"] ?? 0)
    );

    $paidAmount = max(
        0,
        (float)($_POST["paid_amount"] ?? 0)
    );

    $paymentMethod = $_POST["payment_method"] ?? "cash";

    $notes = trim($_POST["notes"] ?? "");

    $productIds = $_POST["product_id"] ?? [];
    $quantities = $_POST["quantity"] ?? [];

    /*
    | Validate payment method
    */

    $allowedPaymentMethods = [
        "cash",
        "card",
        "bank",
        "credit"
    ];

    if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {

        $paymentMethod = "cash";
    }

    try {

        if (!is_array($productIds) || !is_array($quantities)) {

            throw new Exception(
                t("please_add_product")
            );
        }

        $validProducts = [];

        foreach ($productIds as $index => $productId) {

            $productId = (int)$productId;

            $quantity = isset($quantities[$index])
                ? (float)$quantities[$index]
                : 0;

            if ($productId <= 0) {
                continue;
            }

            if ($quantity <= 0) {

                throw new Exception(
                    t("invalid_quantity")
                );
            }

            $validProducts[] = [
                "product_id" => $productId,
                "quantity" => $quantity
            ];
        }

        if (count($validProducts) === 0) {

            throw new Exception(
                t("please_add_product")
            );
        }

        /*
        | Check duplicate products
        */

        $selectedIds = [];

        foreach ($validProducts as $item) {

            if (
                in_array(
                    $item["product_id"],
                    $selectedIds,
                    true
                )
            ) {

                throw new Exception(
                    t("duplicate_product")
                );
            }

            $selectedIds[] = $item["product_id"];
        }

        /*
        | Start transaction
        */

        $conn->beginTransaction();

        $subtotal = 0;

        $saleItems = [];

        /*
        | Check every product
        */

        foreach ($validProducts as $item) {

            $stmt = $conn->prepare("
                SELECT
                    product_id,
                    product_name,
                    selling_price,
                    stock_quantity
                FROM products
                WHERE product_id = ?
                FOR UPDATE
            ");

            $stmt->execute([
                $item["product_id"]
            ]);

            $product = $stmt->fetch();

            if (!$product) {

                throw new Exception(
                    t("product_not_found") .
                        " ID: " .
                        $item["product_id"]
                );
            }

            $stockQuantity =
                (float)$product["stock_quantity"];

            $quantity =
                (float)$item["quantity"];

            if ($quantity > $stockQuantity) {

                throw new Exception(
                    t("not_enough_stock") .
                        " " .
                        $product["product_name"] .
                        " | " .
                        t("available_stock") .
                        " " .
                        $stockQuantity
                );
            }

            $unitPrice =
                (float)$product["selling_price"];

            $itemTotal =
                $quantity * $unitPrice;

            $subtotal += $itemTotal;

            $saleItems[] = [
                "product_id" =>
                $product["product_id"],

                "product_name" =>
                $product["product_name"],

                "quantity" =>
                $quantity,

                "unit_price" =>
                $unitPrice,

                "total_price" =>
                $itemTotal
            ];
        }

        /*
        | Discount
        */

        if ($discount > $subtotal) {

            $discount = $subtotal;
        }

        /*
        | Grand total
        */

        $grandTotal =
            $subtotal - $discount;

        /*
        | Paid amount
        */

        if ($paidAmount > $grandTotal) {

            $paidAmount = $grandTotal;
        }

        /*
        | Due amount
        */

        $dueAmount =
            $grandTotal - $paidAmount;

        /*
        | Payment status
        */

        if ($paidAmount <= 0) {

            $paymentStatus = "unpaid";
        } elseif ($paidAmount < $grandTotal) {

            $paymentStatus = "partial";
        } else {

            $paymentStatus = "paid";
        }

        /*
        |--------------------------------------------------------------------------
        | Insert Sale
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            INSERT INTO sales
            (
                customer_id,
                total_amount,
                discount,
                paid_amount,
                due_amount,
                payment_method,
                payment_status,
                notes
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $customerId,
            $grandTotal,
            $discount,
            $paidAmount,
            $dueAmount,
            $paymentMethod,
            $paymentStatus,
            $notes
        ]);

        $saleId =
            (int)$conn->lastInsertId();

        /*
        |--------------------------------------------------------------------------
        | Insert Sale Items + Reduce Stock
        |--------------------------------------------------------------------------
        */

        foreach ($saleItems as $item) {

            /*
            | Insert sale item
            */

            $stmt = $conn->prepare("
                INSERT INTO sale_items
                (
                    sale_id,
                    product_id,
                    quantity,
                    unit_price,
                    total_price
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $saleId,
                $item["product_id"],
                $item["quantity"],
                $item["unit_price"],
                $item["total_price"]
            ]);

            /*
            | Reduce stock
            */

            $stmt = $conn->prepare("
                UPDATE products
                SET stock_quantity =
                    stock_quantity - ?
                WHERE product_id = ?
                  AND stock_quantity >= ?
            ");

            $stmt->execute([
                $item["quantity"],
                $item["product_id"],
                $item["quantity"]
            ]);

            if ($stmt->rowCount() === 0) {

                throw new Exception(
                    t("stock_update_failed") .
                        " " .
                        $item["product_id"]
                );
            }
        }

        /*
        | Commit
        */

        $conn->commit();

        /*
        | Redirect after CREATE
        */

        header(
            "Location: index.php?success=1"
        );

        exit;
    } catch (Exception $e) {

        if ($conn->inTransaction()) {

            $conn->rollBack();
        }

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| SUCCESS / DELETE MESSAGE
|--------------------------------------------------------------------------
*/

if (
    isset($_GET["success"]) &&
    $_GET["success"] == "1"
) {

    $message =
        t("sale_completed_successfully");
}

if (
    isset($_GET["deleted"]) &&
    $_GET["deleted"] == "1"
) {

    $message =
        t("sale_deleted_successfully");
}


/*
|--------------------------------------------------------------------------
| GET CUSTOMERS
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT
        customer_id,
        name
    FROM customers
    ORDER BY name ASC
");

$customers =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| GET PRODUCTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT
        product_id,
        product_name,
        selling_price,
        stock_quantity
    FROM products
    ORDER BY product_name ASC
");

$products =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| GET SALES HISTORY
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT
        s.*,
        c.name AS customer_name
    FROM sales s
    LEFT JOIN customers c
        ON s.customer_id = c.customer_id
    ORDER BY s.sale_id DESC
");

$sales =
    $stmt->fetchAll();

?>

<!DOCTYPE html>

<html
    lang="<?= currentLanguage() ?>"
    dir="<?= languageDirection() ?>">

<head>

    <meta charset="UTF-8">
    <!-- image link -->
    <link rel="stylesheet" href="../assets/css/backgrounds.css">


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars(t("sales_pos")) ?>
        -
        <?= htmlspecialchars(t("bakery_management")) ?>
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                Arial,
                Tahoma,
                sans-serif;
            background: #f5f6fa;
            color: #222;
        }

        .container {
            width: 95%;
            max-width: 1400px;
            margin: 30px auto;
        }

        .topbar {
            background: #ffffff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            box-shadow:
                0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .title h1 {
            margin: 0 0 5px;
            font-size: 28px;
        }

        .title p {
            margin: 0;
            color: #777;
        }

        .language-buttons {
            display: flex;
            gap: 8px;
        }

        .language-buttons a {
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 7px;
            background: #eee;
            color: #333;
            font-size: 14px;
        }

        .language-buttons a.active {
            background: #333;
            color: white;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: #dff6e5;
            color: #176b2c;
        }

        .error {
            background: #fde2e2;
            color: #a11;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 25px;
            box-shadow:
                0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
            flex-wrap: wrap;
        }

        .card-header h2 {
            margin: 0;
            font-size: 22px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        select,
        input,
        textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #ccc;
            border-radius: 7px;
            font-size: 15px;
            background: white;
        }

        textarea {
            min-height: 80px;
            resize: vertical;
        }

        .items-table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: center;
            white-space: nowrap;
        }

        th {
            background: #f7f7f7;
        }

        .product-row select {
            min-width: 260px;
        }

        .quantity-input {
            width: 110px;
        }

        .price-display,
        .total-display {
            font-weight: bold;
        }

        .btn {
            border: none;
            border-radius: 7px;
            padding: 10px 16px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-success {
            background: #198754;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-warning {
            background: #f0ad4e;
            color: white;
        }

        .btn-small {
            padding: 7px 10px;
            font-size: 12px;
        }

        .summary {
            max-width: 500px;
            margin-left: auto;
            margin-top: 20px;
        }

        [dir="rtl"] .summary {
            margin-left: 0;
            margin-right: auto;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .summary-row.grand {
            font-size: 19px;
            font-weight: bold;
        }

        .form-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-paid {
            background: #dff6e5;
            color: #176b2c;
        }

        .status-partial {
            background: #fff3cd;
            color: #856404;
        }

        .status-unpaid {
            background: #fde2e2;
            color: #a11;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
        }

        .empty {
            text-align: center;
            padding: 35px;
            color: #777;
        }

        @media (max-width: 800px) {

            .form-row {
                grid-template-columns: 1fr;
            }

            .container {
                width: 96%;
            }

            .card {
                padding: 15px;
            }

            .topbar {
                align-items: flex-start;
            }

        }
    </style>

</head>

<body class="bg-sales">

    <div class="container">


        <!-- TOP BAR -->

        <div class="topbar">

            <div class="title">

                <h1>
                    <?= htmlspecialchars(t("sales_pos_page")) ?>
                </h1>

                <p>
                    <?= htmlspecialchars(t("create_manage_sales")) ?>
                </p>

            </div>

            <div class="language-buttons">

                <a
                    href="?lang=ps"
                    class="<?= currentLanguage() === "ps" ? "active" : "" ?>">
                    پښتو
                </a>

                <a
                    href="?lang=en"
                    class="<?= currentLanguage() === "en" ? "active" : "" ?>">
                    English
                </a>

                <a
                    href="../admin/dashboard.php"
                    class="btn btn-secondary">
                    <?= htmlspecialchars(t("dashboard")) ?>
                </a>

            </div>

        </div>


        <!-- ALERTS -->

        <?php if ($message): ?>

            <div class="alert success">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="alert error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- NEW SALE -->

        <div class="card">

            <div class="card-header">

                <h2>
                    <?= htmlspecialchars(t("new_sale")) ?>
                </h2>

            </div>


            <form
                method="POST"
                id="saleForm">


                <!-- CUSTOMER -->

                <div class="form-group">

                    <label>
                        <?= htmlspecialchars(t("customer")) ?>
                    </label>

                    <select name="customer_id">

                        <option value="">
                            <?= htmlspecialchars(t("walk_in_customer")) ?>
                        </option>

                        <?php foreach ($customers as $customer): ?>

                            <option
                                value="<?= (int)$customer["customer_id"] ?>">
                                <?= htmlspecialchars($customer["name"]) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- PRODUCTS -->

                <div class="card-header">

                    <h2>
                        <?= htmlspecialchars(t("products")) ?>
                    </h2>

                    <button
                        type="button"
                        class="btn btn-primary"
                        onclick="addProductRow()">
                        +
                        <?= htmlspecialchars(t("add_product")) ?>
                    </button>

                </div>


                <div class="items-table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    <?= htmlspecialchars(t("product")) ?>
                                </th>

                                <th>
                                    <?= htmlspecialchars(t("stock")) ?>
                                </th>

                                <th>
                                    <?= htmlspecialchars(t("quantity")) ?>
                                </th>

                                <th>
                                    <?= htmlspecialchars(t("unit_price")) ?>
                                </th>

                                <th>
                                    <?= htmlspecialchars(t("total")) ?>
                                </th>

                                <th>
                                    <?= htmlspecialchars(t("actions")) ?>
                                </th>

                            </tr>

                        </thead>

                        <tbody id="productRows"></tbody>

                    </table>

                </div>


                <!-- SUMMARY -->

                <div class="summary">

                    <div class="summary-row">

                        <span>
                            <?= htmlspecialchars(t("subtotal")) ?>
                        </span>

                        <strong id="subtotalDisplay">
                            0.00
                        </strong>

                    </div>


                    <div class="form-group">

                        <label>
                            <?= htmlspecialchars(t("discount")) ?>
                        </label>

                        <input
                            type="number"
                            name="discount"
                            id="discount"
                            value="0"
                            min="0"
                            step="0.01"
                            oninput="calculateTotal()">

                    </div>


                    <div class="summary-row grand">

                        <span>
                            <?= htmlspecialchars(t("grand_total")) ?>
                        </span>

                        <strong id="grandTotalDisplay">
                            0.00
                        </strong>

                    </div>


                    <div class="form-group">

                        <label>
                            <?= htmlspecialchars(t("paid_amount")) ?>
                        </label>

                        <input
                            type="number"
                            name="paid_amount"
                            id="paidAmount"
                            value="0"
                            min="0"
                            step="0.01"
                            oninput="calculateTotal()">

                    </div>


                    <div class="summary-row">

                        <span>
                            <?= htmlspecialchars(t("due_amount")) ?>
                        </span>

                        <strong id="dueAmountDisplay">
                            0.00
                        </strong>

                    </div>


                    <div class="form-group">

                        <label>
                            <?= htmlspecialchars(t("payment_method")) ?>
                        </label>

                        <select name="payment_method">

                            <option value="cash">
                                <?= htmlspecialchars(t("cash")) ?>
                            </option>

                            <option value="card">
                                <?= htmlspecialchars(t("card")) ?>
                            </option>

                            <option value="bank">
                                <?= htmlspecialchars(t("bank")) ?>
                            </option>

                            <option value="credit">
                                <?= htmlspecialchars(t("credit")) ?>
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>
                            <?= htmlspecialchars(t("notes")) ?>
                        </label>

                        <textarea
                            name="notes"
                            placeholder="<?= htmlspecialchars(t("optional_notes")) ?>"></textarea>

                    </div>

                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        name="create_sale"
                        class="btn btn-success">
                        <?= htmlspecialchars(t("complete_sale")) ?>
                    </button>

                </div>

            </form>

        </div>


        <!-- SALES HISTORY -->

        <div class="card">

            <div class="card-header">

                <div>

                    <h2>
                        <?= htmlspecialchars(t("sales_history")) ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars(t("all_completed_sales")) ?>
                    </p>

                </div>

            </div>


            <?php if (empty($sales)): ?>

                <div class="empty">

                    <h3>
                        <?= htmlspecialchars(t("no_sales_found")) ?>
                    </h3>

                    <p>
                        <?= htmlspecialchars(t("no_sales_recorded")) ?>
                    </p>

                </div>

            <?php else: ?>

                <div class="items-table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    <?= htmlspecialchars(t("sale_number")) ?>
                                </th>

                                <th>
                                    <?= htmlspecialchars(t("date")) ?>
                                </th>

                                <th>
                                    <?= htmlspecialchars(t("customer")) ?>
                                </th>

                                <th>
                                    <?= htmlspecialchars(t("total")) ?>
                                </th>

                                <th>
                                    <?= htmlspecialchars(t("paid")) ?>
                                </th>

                                <th>
                                    <?= htmlspecialchars(t("due")) ?>
                                </th>

                                <th>
                                    <?= htmlspecialchars(t("status")) ?>
                                </th>

                                <th>
                                    <?= htmlspecialchars(t("actions")) ?>
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($sales as $sale): ?>

                                <tr>

                                    <td>
                                        #<?= (int)$sale["sale_id"] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $sale["sale_date"]
                                        ) ?>
                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            $sale["customer_name"]
                                                ?: t("walk_in")
                                        ) ?>

                                    </td>

                                    <td>

                                        <?= number_format(
                                            (float)$sale["total_amount"],
                                            2
                                        ) ?>

                                    </td>

                                    <td>

                                        <?= number_format(
                                            (float)$sale["paid_amount"],
                                            2
                                        ) ?>

                                    </td>

                                    <td>

                                        <?= number_format(
                                            (float)$sale["due_amount"],
                                            2
                                        ) ?>

                                    </td>

                                    <td>

                                        <?php

                                        $statusClass =
                                            "status-unpaid";

                                        $statusText =
                                            t("payment_unpaid");

                                        if (
                                            $sale["payment_status"]
                                            === "paid"
                                        ) {

                                            $statusClass =
                                                "status-paid";

                                            $statusText =
                                                t("payment_paid");
                                        } elseif (
                                            $sale["payment_status"]
                                            === "partial"
                                        ) {

                                            $statusClass =
                                                "status-partial";

                                            $statusText =
                                                t("payment_partial");
                                        }

                                        ?>

                                        <span
                                            class="status <?= $statusClass ?>">
                                            <?= htmlspecialchars(
                                                $statusText
                                            ) ?>
                                        </span>

                                    </td>

                                    <td>

                                        <div class="action-buttons">


                                            <!-- VIEW -->

                                            <a
                                                href="view.php?sale_id=<?= (int)$sale["sale_id"] ?>"
                                                class="btn btn-primary btn-small">
                                                <?= htmlspecialchars(
                                                    t("view_sale")
                                                ) ?>
                                            </a>


                                            <!-- PRINT -->

                                            <a
                                                href="view.php?sale_id=<?= (int)$sale["sale_id"] ?>&print=1"
                                                target="_blank"
                                                class="btn btn-warning btn-small">
                                                <?= htmlspecialchars(
                                                    t("print_sale")
                                                ) ?>
                                            </a>


                                            <!-- EDIT -->

                                            <a
                                                href="edit.php?sale_id=<?= (int)$sale["sale_id"] ?>"
                                                class="btn btn-success btn-small">
                                                <?= htmlspecialchars(
                                                    t("edit_sale")
                                                ) ?>
                                            </a>


                                            <!-- DELETE -->

                                            <form
                                                method="POST"
                                                style="display:inline;"
                                                onsubmit="return confirmDeleteSale();">

                                                <input
                                                    type="hidden"
                                                    name="sale_id"
                                                    value="<?= (int)$sale["sale_id"] ?>">

                                                <button
                                                    type="submit"
                                                    name="delete_sale"
                                                    class="btn btn-danger btn-small">
                                                    <?= htmlspecialchars(
                                                        t("delete_sale")
                                                    ) ?>
                                                </button>

                                            </form>


                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <script>
        /*
|--------------------------------------------------------------------------
| Product Data
|--------------------------------------------------------------------------
*/

        const products = <?= json_encode(
                                $products,
                                JSON_UNESCAPED_UNICODE |
                                    JSON_UNESCAPED_SLASHES
                            ) ?>;


        /*
        |--------------------------------------------------------------------------
        | Translation Strings
        |--------------------------------------------------------------------------
        */

        const jsText = {

            selectProduct: <?= json_encode(
                                t("select_product"),
                                JSON_UNESCAPED_UNICODE
                            ) ?>,

            removeProduct: <?= json_encode(
                                t("remove_product"),
                                JSON_UNESCAPED_UNICODE
                            ) ?>,

            selectValidProduct: <?= json_encode(
                                    t("select_valid_product"),
                                    JSON_UNESCAPED_UNICODE
                                ) ?>,

            duplicateProduct: <?= json_encode(
                                    t("duplicate_product"),
                                    JSON_UNESCAPED_UNICODE
                                ) ?>,

            completeSale: <?= json_encode(
                                t("complete_this_sale"),
                                JSON_UNESCAPED_UNICODE
                            ) ?>

        };


        /*
        |--------------------------------------------------------------------------
        | Add Product Row
        |--------------------------------------------------------------------------
        */

        function addProductRow() {

            const tbody =
                document.getElementById("productRows");

            const row =
                document.createElement("tr");

            row.className = "product-row";

            let options = `
        <option value="">
            ${escapeHtml(jsText.selectProduct)}
        </option>
    `;

            products.forEach(function(product) {

                options += `
            <option
                value="${product.product_id}"
                data-price="${product.selling_price}"
                data-stock="${product.stock_quantity}"
            >
                ${escapeHtml(product.product_name)}
            </option>
        `;

            });

            row.innerHTML = `

        <td>

            <select
                name="product_id[]"
                class="product-select"
                onchange="productChanged(this)"
                required
            >

                ${options}

            </select>

        </td>

        <td class="stock-display">
            -
        </td>

        <td>

            <input
                type="number"
                name="quantity[]"
                class="quantity-input"
                value="1"
                min="0.01"
                step="0.01"
                oninput="quantityChanged(this)"
                required
            >

        </td>

        <td class="price-display">
            0.00
        </td>

        <td class="total-display">
            0.00
        </td>

        <td>

            <button
                type="button"
                class="btn btn-danger btn-small"
                onclick="removeProductRow(this)"
            >
                ${escapeHtml(jsText.removeProduct)}
            </button>

        </td>

    `;

            tbody.appendChild(row);

            calculateTotal();
        }


        /*
        |--------------------------------------------------------------------------
        | Product Changed
        |--------------------------------------------------------------------------
        */

        function productChanged(select) {

            const row =
                select.closest("tr");

            const option =
                select.options[select.selectedIndex];

            if (!option || !option.value) {

                row.querySelector(
                    ".stock-display"
                ).textContent = "-";

                row.querySelector(
                    ".price-display"
                ).textContent = "0.00";

                row.querySelector(
                    ".total-display"
                ).textContent = "0.00";

                calculateTotal();

                return;
            }

            const stock =
                parseFloat(
                    option.dataset.stock || 0
                );

            const price =
                parseFloat(
                    option.dataset.price || 0
                );

            row.querySelector(
                    ".stock-display"
                ).textContent =
                stock.toFixed(2);

            row.querySelector(
                    ".price-display"
                ).textContent =
                price.toFixed(2);

            const quantityInput =
                row.querySelector(
                    ".quantity-input"
                );

            quantityInput.max = stock;

            quantityChanged(
                quantityInput
            );

            checkDuplicateProducts(
                select
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Quantity Changed
        |--------------------------------------------------------------------------
        */

        function quantityChanged(input) {

            const row =
                input.closest("tr");

            const select =
                row.querySelector(
                    ".product-select"
                );

            const option =
                select.options[
                    select.selectedIndex
                ];

            const quantity =
                parseFloat(
                    input.value || 0
                );

            const price =
                parseFloat(
                    option?.dataset.price || 0
                );

            const total =
                quantity * price;

            row.querySelector(
                    ".total-display"
                ).textContent =
                total.toFixed(2);

            calculateTotal();
        }


        /*
        |--------------------------------------------------------------------------
        | Remove Product Row
        |--------------------------------------------------------------------------
        */

        function removeProductRow(button) {

            const row =
                button.closest("tr");

            row.remove();

            calculateTotal();
        }


        /*
        |--------------------------------------------------------------------------
        | Check Duplicate Products
        |--------------------------------------------------------------------------
        */

        function checkDuplicateProducts(
            currentSelect
        ) {

            const selectedValue =
                currentSelect.value;

            if (!selectedValue) {
                return;
            }

            const selects =
                document.querySelectorAll(
                    ".product-select"
                );

            let count = 0;

            selects.forEach(function(select) {

                if (
                    select.value ===
                    selectedValue
                ) {

                    count++;
                }

            });

            if (count > 1) {

                alert(
                    jsText.duplicateProduct
                );

                currentSelect.value = "";

                productChanged(
                    currentSelect
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Calculate Total
        |--------------------------------------------------------------------------
        */

        function calculateTotal() {

            let subtotal = 0;

            document
                .querySelectorAll(".total-display")
                .forEach(function(element) {

                    subtotal +=
                        parseFloat(
                            element.textContent || 0
                        );

                });

            let discount =
                parseFloat(
                    document.getElementById(
                        "discount"
                    ).value || 0
                );

            if (discount < 0) {
                discount = 0;
            }

            if (discount > subtotal) {
                discount = subtotal;
            }

            const grandTotal =
                subtotal - discount;

            let paid =
                parseFloat(
                    document.getElementById(
                        "paidAmount"
                    ).value || 0
                );

            if (paid < 0) {
                paid = 0;
            }

            if (paid > grandTotal) {
                paid = grandTotal;
            }

            const due =
                grandTotal - paid;

            document.getElementById(
                    "subtotalDisplay"
                ).textContent =
                subtotal.toFixed(2);

            document.getElementById(
                    "grandTotalDisplay"
                ).textContent =
                grandTotal.toFixed(2);

            document.getElementById(
                    "dueAmountDisplay"
                ).textContent =
                due.toFixed(2);
        }


        /*
        |--------------------------------------------------------------------------
        | Confirm Delete
        |--------------------------------------------------------------------------
        */

        function confirmDeleteSale() {

            return confirm(
                <?= json_encode(
                    t("confirm_delete_sale"),
                    JSON_UNESCAPED_UNICODE
                ) ?>
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Form Validation
        |--------------------------------------------------------------------------
        */

        document
            .getElementById("saleForm")
            .addEventListener(
                "submit",
                function(event) {

                    const selects =
                        document.querySelectorAll(
                            ".product-select"
                        );

                    let validCount = 0;

                    selects.forEach(
                        function(select) {

                            if (select.value) {
                                validCount++;
                            }

                        }
                    );

                    if (validCount === 0) {

                        event.preventDefault();

                        alert(
                            jsText.selectValidProduct
                        );

                        return;
                    }

                    if (
                        !confirm(
                            jsText.completeSale
                        )
                    ) {

                        event.preventDefault();

                        return;
                    }

                }
            );


        /*
        |--------------------------------------------------------------------------
        | HTML Escape
        |--------------------------------------------------------------------------
        */

        function escapeHtml(text) {

            const div =
                document.createElement("div");

            div.textContent = text;

            return div.innerHTML;
        }


        /*
        |--------------------------------------------------------------------------
        | Start With One Product Row
        |--------------------------------------------------------------------------
        */

        addProductRow();
    </script>

</body>

</html>