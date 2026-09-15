<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

/*
|--------------------------------------------------------------------------
| LANGUAGE
|--------------------------------------------------------------------------
*/

$isPashto = currentLanguage() === "ps";

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];

/*
|--------------------------------------------------------------------------
| SAVE PURCHASE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {

        $error = $isPashto
            ? "د امنیت Token ناسم دی. مهرباني وکړئ پاڼه Refresh کړئ."
            : "Invalid security token. Please refresh the page.";

    } else {

        $supplier_id = !empty($_POST["supplier_id"])
            ? (int) $_POST["supplier_id"]
            : null;

        $paid_amount = (float) ($_POST["paid_amount"] ?? 0);

        $notes = trim($_POST["notes"] ?? "");

        $ingredient_ids = $_POST["ingredient_id"] ?? [];
        $quantities = $_POST["quantity"] ?? [];
        $unit_prices = $_POST["unit_price"] ?? [];

        if (!is_array($ingredient_ids)) {
            $ingredient_ids = [];
        }

        if (!is_array($quantities)) {
            $quantities = [];
        }

        if (!is_array($unit_prices)) {
            $unit_prices = [];
        }

        if (empty($ingredient_ids)) {

            $error = $isPashto
                ? "لږ تر لږه یو Ingredient اضافه کړئ."
                : "Please add at least one ingredient.";

        } else {

            try {

                $conn->beginTransaction();

                $total_amount = 0;
                $purchaseItems = [];

                /*
                |--------------------------------------------------------------------------
                | VALIDATE ITEMS
                |--------------------------------------------------------------------------
                */

                foreach ($ingredient_ids as $index => $ingredient_id) {

                    $ingredient_id = (int) $ingredient_id;

                    $quantity = (float) (
                        $quantities[$index] ?? 0
                    );

                    $unit_price = (float) (
                        $unit_prices[$index] ?? 0
                    );

                    if ($ingredient_id <= 0) {
                        continue;
                    }

                    if ($quantity <= 0) {

                        throw new Exception(
                            $isPashto
                                ? "Quantity باید له صفر څخه زیاته وي."
                                : "Quantity must be greater than zero."
                        );
                    }

                    if ($unit_price < 0) {

                        throw new Exception(
                            $isPashto
                                ? "Unit Price منفي کېدای نشي."
                                : "Unit price cannot be negative."
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CHECK INGREDIENT
                    |--------------------------------------------------------------------------
                    */

                    $checkStmt = $conn->prepare("
                        SELECT
                            ingredient_id,
                            ingredient_name
                        FROM ingredients
                        WHERE ingredient_id = :ingredient_id
                        FOR UPDATE
                    ");

                    $checkStmt->execute([
                        ":ingredient_id" => $ingredient_id
                    ]);

                    $ingredient =
                        $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$ingredient) {

                        throw new Exception(
                            $isPashto
                                ? "ټاکل شوی Ingredient پیدا نه شو."
                                : "Selected ingredient was not found."
                        );
                    }

                    $itemTotal =
                        round($quantity * $unit_price, 2);

                    $total_amount =
                        round($total_amount + $itemTotal, 2);

                    $purchaseItems[] = [
                        "ingredient_id" => $ingredient_id,
                        "quantity" => $quantity,
                        "unit_price" => $unit_price,
                        "total_price" => $itemTotal
                    ];
                }

                if (empty($purchaseItems)) {

                    throw new Exception(
                        $isPashto
                            ? "مهرباني وکړئ یو معتبر Ingredient انتخاب کړئ."
                            : "Please select a valid ingredient."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | PAYMENT
                |--------------------------------------------------------------------------
                */

                if ($paid_amount < 0) {
                    $paid_amount = 0;
                }

                $paid_amount =
                    round($paid_amount, 2);

                if ($paid_amount > $total_amount) {
                    $paid_amount = $total_amount;
                }

                $due_amount =
                    round(
                        $total_amount - $paid_amount,
                        2
                    );

                if ($paid_amount >= $total_amount) {

                    $payment_status = "paid";

                } elseif ($paid_amount > 0) {

                    $payment_status = "partial";

                } else {

                    $payment_status = "unpaid";
                }

                /*
                |--------------------------------------------------------------------------
                | INSERT PURCHASE
                |--------------------------------------------------------------------------
                */

                $purchaseStmt = $conn->prepare("
                    INSERT INTO purchases
                    (
                        supplier_id,
                        user_id,
                        total_amount,
                        paid_amount,
                        due_amount,
                        payment_status,
                        notes
                    )
                    VALUES
                    (
                        :supplier_id,
                        :user_id,
                        :total_amount,
                        :paid_amount,
                        :due_amount,
                        :payment_status,
                        :notes
                    )
                ");

                $purchaseStmt->execute([
                    ":supplier_id" => $supplier_id,
                    ":user_id" => (int) $_SESSION["user_id"],
                    ":total_amount" => $total_amount,
                    ":paid_amount" => $paid_amount,
                    ":due_amount" => $due_amount,
                    ":payment_status" => $payment_status,
                    ":notes" => $notes
                ]);

                $purchase_id =
                    (int) $conn->lastInsertId();

                /*
                |--------------------------------------------------------------------------
                | INSERT ITEMS + UPDATE STOCK
                |--------------------------------------------------------------------------
                */

                $itemStmt = $conn->prepare("
                    INSERT INTO purchase_items
                    (
                        purchase_id,
                        ingredient_id,
                        quantity,
                        unit_price,
                        total_price
                    )
                    VALUES
                    (
                        :purchase_id,
                        :ingredient_id,
                        :quantity,
                        :unit_price,
                        :total_price
                    )
                ");

                $stockStmt = $conn->prepare("
                    UPDATE ingredients
                    SET current_stock =
                        current_stock + :quantity
                    WHERE ingredient_id = :ingredient_id
                ");

                foreach ($purchaseItems as $item) {

                    $itemStmt->execute([
                        ":purchase_id" => $purchase_id,
                        ":ingredient_id" => $item["ingredient_id"],
                        ":quantity" => $item["quantity"],
                        ":unit_price" => $item["unit_price"],
                        ":total_price" => $item["total_price"]
                    ]);

                    $stockStmt->execute([
                        ":quantity" => $item["quantity"],
                        ":ingredient_id" => $item["ingredient_id"]
                    ]);
                }

                $conn->commit();

                header(
                    "Location: index.php?success=1&purchase_id="
                    . $purchase_id
                );

                exit;

            } catch (Exception $e) {

                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| GET SUPPLIERS
|--------------------------------------------------------------------------
*/

$supplierStmt = $conn->query("
    SELECT supplier_id, name
    FROM suppliers
    ORDER BY name ASC
");

$suppliers =
    $supplierStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| GET INGREDIENTS
|--------------------------------------------------------------------------
*/

$ingredientStmt = $conn->query("
    SELECT
        ingredient_id,
        ingredient_name,
        unit,
        current_stock,
        purchase_price
    FROM ingredients
    ORDER BY ingredient_name ASC
");

$ingredients =
    $ingredientStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| GET PURCHASE HISTORY
|--------------------------------------------------------------------------
*/

$purchaseListStmt = $conn->query("
    SELECT
        p.purchase_id,
        p.purchase_date,
        p.total_amount,
        p.paid_amount,
        p.due_amount,
        p.payment_status,
        s.name AS supplier_name
    FROM purchases p
    LEFT JOIN suppliers s
        ON p.supplier_id = s.supplier_id
    ORDER BY p.purchase_id DESC
");

$purchases =
    $purchaseListStmt->fetchAll(PDO::FETCH_ASSOC);

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
        <?= $isPashto
            ? "پېرودونه - د بیکري مدیریت"
            : "Purchases - Bakery Management"
        ?>
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/backgrounds.css"
    >

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

        .ingredient-row {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .summary-box {
            border-radius: 10px;
            padding: 15px;
            background-color: #f8f9fa;
        }

        .form-control,
        .form-select {
            min-height: 42px;
        }

        <?php if ($isPashto): ?>

        body {
            font-family: Tahoma, Arial, sans-serif;
        }

        .navbar-brand,
        .navbar,
        .card,
        .table,
        .form-label,
        .form-control,
        .form-select,
        .btn {
            direction: rtl;
        }

        .table {
            text-align: right;
        }

        <?php endif; ?>

    </style>

</head>


<body class="bg-purchases">


<!-- NAVBAR -->

<nav class="navbar">

    <div class="container-fluid px-4">

        <div class="d-flex align-items-center">

            <a
                href="../admin/dashboard.php"
                class="navbar-brand fw-bold"
            >

                <i class="bi bi-shop"></i>

                <?= $isPashto
                    ? "د بیکري سیستم"
                    : "Bakery System"
                ?>

            </a>

        </div>


        <div class="d-flex align-items-center gap-2">

            <span class="me-2">

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars(
                    $_SESSION["full_name"] ?? (
                        $isPashto
                            ? "کارن"
                            : "User"
                    ),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </span>


            <!-- LANGUAGE -->

            <a
                href="?lang=ps"
                class="btn btn-sm <?= $isPashto
                    ? "btn-primary"
                    : "btn-outline-primary"
                ?>"
            >
                پښتو
            </a>


            <a
                href="?lang=en"
                class="btn btn-sm <?= !$isPashto
                    ? "btn-primary"
                    : "btn-outline-primary"
                ?>"
            >
                English
            </a>


            <!-- LOGOUT -->

            <a
                href="../public/logout.php"
                class="btn btn-outline-danger btn-sm"
            >

                <i class="bi bi-box-arrow-right"></i>

                <?= $isPashto
                    ? "وتل"
                    : "Logout"
                ?>

            </a>

        </div>

    </div>

</nav>


<div class="container-fluid p-4">


    <!-- PAGE HEADER -->

    <div
        class="d-flex justify-content-between align-items-center mb-4"
    >

        <div>

            <h2>

                <i class="bi bi-bag-check"></i>

                <?= $isPashto
                    ? "پېرودونه"
                    : "Purchases"
                ?>

            </h2>


            <p class="text-muted mb-0">

                <?= $isPashto
                    ? "د موادو پېرود او ذخیره مدیریت کړئ"
                    : "Purchase ingredients and manage stock"
                ?>

            </p>

        </div>


        <a
            href="../admin/dashboard.php"
            class="btn btn-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            <?= $isPashto
                ? "ډشبورډ"
                : "Dashboard"
            ?>

        </a>

    </div>


    <!-- SUCCESS -->

    <?php if (isset($_GET["success"])): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-check-circle-fill"></i>

            <?= $isPashto
                ? "پېرود په بریالیتوب سره بشپړ شو."
                : "Purchase completed successfully."
            ?>


            <?php if (isset($_GET["purchase_id"])): ?>

                <?= $isPashto
                    ? "د پېرود شمېره:"
                    : "Purchase #"
                ?>

                <?= (int) $_GET["purchase_id"] ?>

            <?php endif; ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- DELETE SUCCESS -->

    <?php if (isset($_GET["deleted"])): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-trash"></i>

            <?= $isPashto
                ? "پېرود په بریالیتوب سره حذف شو."
                : "Purchase deleted successfully."
            ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- UPDATED SUCCESS -->

    <?php if (isset($_GET["updated"])): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-check-circle-fill"></i>

            <?= $isPashto
                ? "پېرود په بریالیتوب سره Update شو."
                : "Purchase updated successfully."
            ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- ERROR -->

    <?php if (isset($error)): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-exclamation-triangle-fill"></i>

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- REDIRECT ERROR -->

    <?php if (isset($_GET["error"])): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-exclamation-triangle-fill"></i>

            <?= htmlspecialchars(
                $_GET["error"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- PURCHASE FORM -->

    <form
        method="POST"
        id="purchaseForm"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $csrf_token,
                ENT_QUOTES,
                "UTF-8"
            ) ?>"
        >


        <div class="row g-4">


            <!-- LEFT -->

            <div class="col-lg-8">


                <!-- SUPPLIER -->

                <div class="card shadow-sm mb-4">

                    <div class="card-header bg-white py-3">

                        <h5 class="mb-0">

                            <i class="bi bi-truck"></i>

                            <?= $isPashto
                                ? "عرضه کوونکی"
                                : "Supplier"
                            ?>

                        </h5>

                    </div>


                    <div class="card-body">

                        <select
                            name="supplier_id"
                            class="form-select"
                        >

                            <option value="">

                                <?= $isPashto
                                    ? "عرضه کوونکی انتخاب کړئ"
                                    : "Select Supplier"
                                ?>

                            </option>


                            <?php foreach ($suppliers as $supplier): ?>

                                <option
                                    value="<?= (int) $supplier["supplier_id"] ?>"
                                >

                                    <?= htmlspecialchars(
                                        $supplier["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>


                <!-- INGREDIENTS -->

                <div class="card shadow-sm">

                    <div class="card-header bg-white py-3">

                        <div
                            class="d-flex justify-content-between align-items-center"
                        >

                            <h5 class="mb-0">

                                <i class="bi bi-basket"></i>

                                <?= $isPashto
                                    ? "مواد"
                                    : "Ingredients"
                                ?>

                            </h5>


                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                id="addIngredientBtn"
                            >

                                <i class="bi bi-plus-circle"></i>

                                <?= $isPashto
                                    ? "مواد اضافه کړئ"
                                    : "Add Ingredient"
                                ?>

                            </button>

                        </div>

                    </div>


                    <div class="card-body">

                        <div id="ingredientContainer">


                            <!-- FIRST ROW -->

                            <div class="ingredient-row">

                                <div class="row g-3 align-items-end">


                                    <!-- INGREDIENT -->

                                    <div class="col-md-5">

                                        <label class="form-label">

                                            <?= $isPashto
                                                ? "مواد"
                                                : "Ingredient"
                                            ?>

                                        </label>


                                        <select
                                            name="ingredient_id[]"
                                            class="form-select ingredient-select"
                                            required
                                        >

                                            <option value="">

                                                <?= $isPashto
                                                    ? "مواد انتخاب کړئ"
                                                    : "Select Ingredient"
                                                ?>

                                            </option>


                                            <?php foreach ($ingredients as $ingredient): ?>

                                                <option
                                                    value="<?= (int) $ingredient["ingredient_id"] ?>"
                                                    data-price="<?= htmlspecialchars(
                                                        $ingredient["purchase_price"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>"
                                                >

                                                    <?= htmlspecialchars(
                                                        $ingredient["ingredient_name"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>

                                                    -

                                                    <?= $isPashto
                                                        ? "ذخیره:"
                                                        : "Stock:"
                                                    ?>

                                                    <?= htmlspecialchars(
                                                        $ingredient["current_stock"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>

                                                    <?= htmlspecialchars(
                                                        $ingredient["unit"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>

                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>


                                    <!-- QUANTITY -->

                                    <div class="col-md-2">

                                        <label class="form-label">

                                            <?= $isPashto
                                                ? "مقدار"
                                                : "Quantity"
                                            ?>

                                        </label>


                                        <input
                                            type="number"
                                            name="quantity[]"
                                            class="form-control quantity-input"
                                            min="0.001"
                                            step="0.001"
                                            value="1.000"
                                            required
                                        >

                                    </div>


                                    <!-- PRICE -->

                                    <div class="col-md-3">

                                        <label class="form-label">

                                            <?= $isPashto
                                                ? "د واحد بیه"
                                                : "Unit Price"
                                            ?>

                                        </label>


                                        <input
                                            type="number"
                                            name="unit_price[]"
                                            class="form-control price-input"
                                            min="0"
                                            step="0.01"
                                            value="0.00"
                                            required
                                        >

                                    </div>


                                    <!-- REMOVE -->

                                    <div class="col-md-2">

                                        <button
                                            type="button"
                                            class="btn btn-outline-danger w-100 remove-ingredient"
                                        >

                                            <i class="bi bi-trash"></i>

                                            <?= $isPashto
                                                ? "حذف"
                                                : "Remove"
                                            ?>

                                        </button>

                                    </div>

                                </div>


                                <!-- ITEM TOTAL -->

                                <div class="text-end mt-3">

                                    <strong>

                                        <?= $isPashto
                                            ? "د توکي ټول:"
                                            : "Item Total:"
                                        ?>

                                        $

                                        <span class="row-total">
                                            0.00
                                        </span>

                                    </strong>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- RIGHT -->

            <div class="col-lg-4">


                <!-- SUMMARY -->

                <div class="card shadow-sm">

                    <div class="card-header bg-white py-3">

                        <h5 class="mb-0">

                            <i class="bi bi-calculator"></i>

                            <?= $isPashto
                                ? "د پېرود لنډیز"
                                : "Purchase Summary"
                            ?>

                        </h5>

                    </div>


                    <div class="card-body">


                        <!-- TOTAL -->

                        <div class="summary-box mb-3">

                            <div
                                class="d-flex justify-content-between"
                            >

                                <span>

                                    <?= $isPashto
                                        ? "ټوله اندازه"
                                        : "Total Amount"
                                    ?>

                                </span>


                                <strong
                                    id="totalAmount"
                                    class="text-primary fs-4"
                                >
                                    $0.00
                                </strong>

                            </div>

                        </div>


                        <!-- PAID -->

                        <div class="mb-3">

                            <label class="form-label">

                                <?= $isPashto
                                    ? "ورکړل شوې اندازه"
                                    : "Paid Amount"
                                ?>

                            </label>


                            <input
                                type="number"
                                name="paid_amount"
                                id="paidAmount"
                                class="form-control"
                                min="0"
                                step="0.01"
                                value="0.00"
                            >

                        </div>


                        <!-- DUE -->

                        <div class="summary-box mb-3">

                            <div
                                class="d-flex justify-content-between"
                            >

                                <span>

                                    <?= $isPashto
                                        ? "پور پاتې"
                                        : "Due Amount"
                                    ?>

                                </span>


                                <strong
                                    id="dueAmount"
                                    class="text-danger"
                                >
                                    $0.00
                                </strong>

                            </div>

                        </div>


                        <!-- NOTES -->

                        <div class="mb-3">

                            <label class="form-label">

                                <?= $isPashto
                                    ? "یادښت"
                                    : "Notes"
                                ?>

                            </label>


                            <textarea
                                name="notes"
                                class="form-control"
                                rows="4"
                                placeholder="<?= $isPashto
                                    ? "اختیاري یادښت..."
                                    : "Optional notes..."
                                ?>"
                            ></textarea>

                        </div>


                        <!-- COMPLETE -->

                        <button
                            type="submit"
                            class="btn btn-success btn-lg w-100"
                        >

                            <i class="bi bi-check-circle"></i>

                            <?= $isPashto
                                ? "پېرود بشپړ کړئ"
                                : "Complete Purchase"
                            ?>

                        </button>

                    </div>

                </div>

            </div>

        </div>

    </form>


    <!-- PURCHASE HISTORY -->

    <div class="card shadow-sm mt-5">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">

                <i class="bi bi-clock-history"></i>

                <?= $isPashto
                    ? "د پېرود تاریخچه"
                    : "Purchase History"
                ?>

            </h5>

        </div>


        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">


                    <thead class="table-light">

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                <?= $isPashto
                                    ? "عرضه کوونکی"
                                    : "Supplier"
                                ?>
                            </th>

                            <th>
                                <?= $isPashto
                                    ? "نېټه"
                                    : "Date"
                                ?>
                            </th>

                            <th>
                                <?= $isPashto
                                    ? "ټول"
                                    : "Total"
                                ?>
                            </th>

                            <th>
                                <?= $isPashto
                                    ? "ورکړل"
                                    : "Paid"
                                ?>
                            </th>

                            <th>
                                <?= $isPashto
                                    ? "پاتې"
                                    : "Due"
                                ?>
                            </th>

                            <th>
                                <?= $isPashto
                                    ? "حالت"
                                    : "Status"
                                ?>
                            </th>

                            <th>
                                <?= $isPashto
                                    ? "عملیات"
                                    : "Actions"
                                ?>
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php if (empty($purchases)): ?>

                            <tr>

                                <td
                                    colspan="8"
                                    class="text-center text-muted py-4"
                                >

                                    <i class="bi bi-inbox fs-3"></i>

                                    <br>

                                    <?= $isPashto
                                        ? "هیڅ پېرود ونه موندل شو."
                                        : "No purchases found."
                                    ?>

                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach ($purchases as $purchase): ?>

                                <tr>


                                    <!-- ID -->

                                    <td>

                                        <strong>

                                            #<?= (int)
                                                $purchase["purchase_id"]
                                            ?>

                                        </strong>

                                    </td>


                                    <!-- SUPPLIER -->

                                    <td>

                                        <?= htmlspecialchars(
                                            $purchase["supplier_name"]
                                                ?? (
                                                    $isPashto
                                                        ? "عرضه کوونکی نشته"
                                                        : "No Supplier"
                                                ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </td>


                                    <!-- DATE -->

                                    <td>

                                        <?= htmlspecialchars(
                                            $purchase["purchase_date"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </td>


                                    <!-- TOTAL -->

                                    <td>

                                        <strong>

                                            $<?= number_format(
                                                (float)
                                                $purchase["total_amount"],
                                                2
                                            ) ?>

                                        </strong>

                                    </td>


                                    <!-- PAID -->

                                    <td class="text-success">

                                        $<?= number_format(
                                            (float)
                                            $purchase["paid_amount"],
                                            2
                                        ) ?>

                                    </td>


                                    <!-- DUE -->

                                    <td class="text-danger">

                                        $<?= number_format(
                                            (float)
                                            $purchase["due_amount"],
                                            2
                                        ) ?>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <?php

                                        $status =
                                            $purchase["payment_status"];

                                        $badge = "secondary";

                                        if ($status === "paid") {
                                            $badge = "success";
                                        } elseif ($status === "partial") {
                                            $badge = "warning text-dark";
                                        } elseif ($status === "unpaid") {
                                            $badge = "danger";
                                        }

                                        ?>


                                        <span
                                            class="badge bg-<?= $badge ?>"
                                        >

                                            <?php if ($status === "paid"): ?>

                                                <?= $isPashto
                                                    ? "بشپړ ورکړل شوی"
                                                    : "Paid"
                                                ?>

                                            <?php elseif ($status === "partial"): ?>

                                                <?= $isPashto
                                                    ? "نیمه ورکړل شوې"
                                                    : "Partial"
                                                ?>

                                            <?php elseif ($status === "unpaid"): ?>

                                                <?= $isPashto
                                                    ? "نه دی ورکړل شوی"
                                                    : "Unpaid"
                                                ?>

                                            <?php else: ?>

                                                <?= htmlspecialchars(
                                                    ucfirst($status),
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>

                                            <?php endif; ?>

                                        </span>

                                    </td>


                                    <!-- ACTIONS -->

                                    <td>


                                        <!-- VIEW -->

                                        <a
                                            href="view.php?id=<?= (int)
                                                $purchase["purchase_id"]
                                            ?>"
                                            class="btn btn-sm btn-info text-white"
                                            title="<?= $isPashto
                                                ? "کتل"
                                                : "View"
                                            ?>"
                                        >

                                            <i class="bi bi-eye"></i>

                                        </a>


                                        <!-- EDIT -->

                                        <a
                                            href="edit.php?id=<?= (int)
                                                $purchase["purchase_id"]
                                            ?>"
                                            class="btn btn-sm btn-warning"
                                            title="<?= $isPashto
                                                ? "سمول"
                                                : "Edit"
                                            ?>"
                                        >

                                            <i class="bi bi-pencil"></i>

                                        </a>


                                        <!-- DELETE -->

                                        <form
                                            method="POST"
                                            action="delete.php"
                                            class="d-inline"
                                            onsubmit="return confirmDeletePurchase();"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(
                                                    $csrf_token,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>"
                                            >


                                            <input
                                                type="hidden"
                                                name="purchase_id"
                                                value="<?= (int)
                                                    $purchase["purchase_id"]
                                                ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-danger"
                                                title="<?= $isPashto
                                                    ? "حذف"
                                                    : "Delete"
                                                ?>"
                                            >

                                                <i class="bi bi-trash"></i>

                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<!-- JAVASCRIPT -->

<script>

const ingredientContainer =
    document.getElementById("ingredientContainer");

const addIngredientBtn =
    document.getElementById("addIngredientBtn");

const paidAmountInput =
    document.getElementById("paidAmount");


/*
|--------------------------------------------------------------------------
| ADD INGREDIENT
|--------------------------------------------------------------------------
*/

addIngredientBtn.addEventListener(
    "click",
    function () {

        const firstRow =
            document.querySelector(".ingredient-row");

        const newRow =
            firstRow.cloneNode(true);


        newRow.querySelector(
            ".ingredient-select"
        ).value = "";


        newRow.querySelector(
            ".quantity-input"
        ).value = "1.000";


        newRow.querySelector(
            ".price-input"
        ).value = "0.00";


        newRow.querySelector(
            ".row-total"
        ).textContent = "0.00";


        ingredientContainer.appendChild(newRow);

        updateTotals();
    }
);


/*
|--------------------------------------------------------------------------
| REMOVE INGREDIENT
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "click",
    function (event) {

        const button =
            event.target.closest(".remove-ingredient");

        if (!button) {
            return;
        }


        const rows =
            document.querySelectorAll(".ingredient-row");

        const row =
            button.closest(".ingredient-row");


        if (rows.length > 1) {

            row.remove();

        } else {

            row.querySelector(
                ".ingredient-select"
            ).value = "";


            row.querySelector(
                ".quantity-input"
            ).value = "1.000";


            row.querySelector(
                ".price-input"
            ).value = "0.00";


            row.querySelector(
                ".row-total"
            ).textContent = "0.00";
        }


        updateTotals();
    }
);


/*
|--------------------------------------------------------------------------
| INGREDIENT SELECT
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "change",
    function (event) {

        if (
            event.target.classList.contains(
                "ingredient-select"
            )
        ) {

            const row =
                event.target.closest(".ingredient-row");


            const option =
                event.target.options[
                    event.target.selectedIndex
                ];


            const price =
                option
                    ? option.getAttribute("data-price")
                    : null;


            if (price !== null) {

                row.querySelector(
                    ".price-input"
                ).value =
                    price || "0.00";
            }


            updateTotals();
        }
    }
);


/*
|--------------------------------------------------------------------------
| ROW TOTAL
|--------------------------------------------------------------------------
*/

function updateRowTotal(row) {

    const quantity =
        parseFloat(
            row.querySelector(".quantity-input").value
        ) || 0;


    const price =
        parseFloat(
            row.querySelector(".price-input").value
        ) || 0;


    const total =
        quantity * price;


    row.querySelector(
        ".row-total"
    ).textContent =
        total.toFixed(2);
}


/*
|--------------------------------------------------------------------------
| TOTALS
|--------------------------------------------------------------------------
*/

function updateTotals() {

    let totalAmount = 0;


    document
        .querySelectorAll(".ingredient-row")
        .forEach(function (row) {

            updateRowTotal(row);


            const rowTotal =
                parseFloat(
                    row.querySelector(
                        ".row-total"
                    ).textContent
                ) || 0;


            totalAmount += rowTotal;
        });


    const paid =
        Math.max(
            0,
            parseFloat(paidAmountInput.value) || 0
        );


    const due =
        Math.max(
            0,
            totalAmount - paid
        );


    document.getElementById(
        "totalAmount"
    ).textContent =
        "$" + totalAmount.toFixed(2);


    document.getElementById(
        "dueAmount"
    ).textContent =
        "$" + due.toFixed(2);
}


/*
|--------------------------------------------------------------------------
| INPUT EVENTS
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "input",
    function (event) {

        if (
            event.target.classList.contains(
                "quantity-input"
            ) ||

            event.target.classList.contains(
                "price-input"
            ) ||

            event.target.id === "paidAmount"
        ) {

            updateTotals();
        }
    }
);


/*
|--------------------------------------------------------------------------
| DELETE CONFIRMATION
|--------------------------------------------------------------------------
*/

function confirmDeletePurchase() {

    <?php if ($isPashto): ?>

        return confirm(
            "ایا ډاډه یاست چې دا پېرود حذف کړئ؟\n\nد دې پېرود مقدار به له Stock څخه هم کم شي."
        );

    <?php else: ?>

        return confirm(
            "Are you sure you want to delete this purchase?\n\nThe purchased quantity will be removed from stock."
        );

    <?php endif; ?>
}


/*
|--------------------------------------------------------------------------
| INITIAL TOTAL
|--------------------------------------------------------------------------
*/

updateTotals();

</script>


<script src="../assets/js/bootstrap.bundle.min.js"></script>

</body>

</html>