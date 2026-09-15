
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

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
| PURCHASE ID
|--------------------------------------------------------------------------
*/
$purchase_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($purchase_id <= 0) {
    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| GET PURCHASE
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        purchase_id,
        supplier_id,
        total_amount,
        paid_amount,
        due_amount,
        payment_status,
        notes
    FROM purchases
    WHERE purchase_id = :purchase_id
    LIMIT 1
");

$stmt->execute([
    ":purchase_id" => $purchase_id
]);

$purchase = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$purchase) {
    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| GET ORIGINAL ITEMS
|--------------------------------------------------------------------------
*/
$itemStmt = $conn->prepare("
    SELECT
        purchase_item_id,
        ingredient_id,
        quantity,
        unit_price,
        total_price
    FROM purchase_items
    WHERE purchase_id = :purchase_id
    ORDER BY purchase_item_id ASC
");

$itemStmt->execute([
    ":purchase_id" => $purchase_id
]);

$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

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

$suppliers = $supplierStmt->fetchAll(PDO::FETCH_ASSOC);

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

$ingredients = $ingredientStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| UPDATE PURCHASE
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
            ? "د امنیت نښه ناسمه ده. مهرباني وکړئ پاڼه بیا تازه کړئ."
            : "Invalid security token. Please refresh the page and try again.";

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
                ? "لږ تر لږه یو جنس اضافه کړئ."
                : "Please add at least one ingredient.";

        } else {

            try {

                $conn->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | BUILD OLD STOCK MAP
                |--------------------------------------------------------------------------
                */
                $oldStockMap = [];

                foreach ($items as $oldItem) {

                    $ingredientId =
                        (int) $oldItem["ingredient_id"];

                    $oldQty =
                        (float) $oldItem["quantity"];

                    if (!isset($oldStockMap[$ingredientId])) {
                        $oldStockMap[$ingredientId] = 0;
                    }

                    $oldStockMap[$ingredientId] += $oldQty;
                }

                /*
                |--------------------------------------------------------------------------
                | BUILD NEW ITEMS
                |--------------------------------------------------------------------------
                */
                $newStockMap = [];

                $purchaseItems = [];

                $total_amount = 0;

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
                                ? "مقدار باید له صفر څخه زیات وي."
                                : "Quantity must be greater than zero."
                        );
                    }

                    if ($unit_price < 0) {

                        throw new Exception(
                            $isPashto
                                ? "د واحد بیه منفي کېدای نشي."
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
                            ingredient_name,
                            current_stock
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
                                ? "جنس پیدا نه شو."
                                : "Ingredient not found."
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | NEW STOCK MAP
                    |--------------------------------------------------------------------------
                    */
                    if (!isset($newStockMap[$ingredient_id])) {
                        $newStockMap[$ingredient_id] = 0;
                    }

                    $newStockMap[$ingredient_id] += $quantity;

                    /*
                    |--------------------------------------------------------------------------
                    | ITEM TOTAL
                    |--------------------------------------------------------------------------
                    */
                    $itemTotal = round(
                        $quantity * $unit_price,
                        2
                    );

                    $total_amount = round(
                        $total_amount + $itemTotal,
                        2
                    );

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
                            ? "مهرباني وکړئ یو صحیح جنس انتخاب کړئ."
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

                $paid_amount = round($paid_amount, 2);

                if ($paid_amount > $total_amount) {
                    $paid_amount = $total_amount;
                }

                $due_amount = round(
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
                | UPDATE PURCHASE
                |--------------------------------------------------------------------------
                */
                $updateStmt = $conn->prepare("
                    UPDATE purchases
                    SET
                        supplier_id = :supplier_id,
                        total_amount = :total_amount,
                        paid_amount = :paid_amount,
                        due_amount = :due_amount,
                        payment_status = :payment_status,
                        notes = :notes
                    WHERE purchase_id = :purchase_id
                ");

                $updateStmt->execute([
                    ":supplier_id" => $supplier_id,
                    ":total_amount" => $total_amount,
                    ":paid_amount" => $paid_amount,
                    ":due_amount" => $due_amount,
                    ":payment_status" => $payment_status,
                    ":notes" => $notes,
                    ":purchase_id" => $purchase_id
                ]);

                /*
                |--------------------------------------------------------------------------
                | DELETE OLD ITEMS
                |--------------------------------------------------------------------------
                */
                $deleteItemsStmt = $conn->prepare("
                    DELETE FROM purchase_items
                    WHERE purchase_id = :purchase_id
                ");

                $deleteItemsStmt->execute([
                    ":purchase_id" => $purchase_id
                ]);

                /*
                |--------------------------------------------------------------------------
                | CALCULATE STOCK DELTA
                |--------------------------------------------------------------------------
                */
                $allIngredientIds = array_unique(
                    array_merge(
                        array_keys($oldStockMap),
                        array_keys($newStockMap)
                    )
                );

                $stockUpdateStmt = $conn->prepare("
                    UPDATE ingredients
                    SET current_stock =
                        current_stock + :delta
                    WHERE ingredient_id =
                        :ingredient_id
                ");

                foreach ($allIngredientIds as $ingredientId) {

                    $oldQty = (float) (
                        $oldStockMap[$ingredientId] ?? 0
                    );

                    $newQty = (float) (
                        $newStockMap[$ingredientId] ?? 0
                    );

                    $delta = round(
                        $newQty - $oldQty,
                        3
                    );

                    if ($delta == 0) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | LOCK / CHECK CURRENT STOCK
                    |--------------------------------------------------------------------------
                    */
                    $stockCheckStmt = $conn->prepare("
                        SELECT
                            ingredient_id,
                            ingredient_name,
                            current_stock
                        FROM ingredients
                        WHERE ingredient_id = :ingredient_id
                        FOR UPDATE
                    ");

                    $stockCheckStmt->execute([
                        ":ingredient_id" => $ingredientId
                    ]);

                    $stockIngredient =
                        $stockCheckStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$stockIngredient) {

                        throw new Exception(
                            $isPashto
                                ? "د سټاک د تازه کولو پر مهال جنس پیدا نه شو."
                                : "Ingredient not found while updating stock."
                        );
                    }

                    $currentStock =
                        (float) $stockIngredient["current_stock"];

                    /*
                    |--------------------------------------------------------------------------
                    | CHECK ENOUGH STOCK
                    |--------------------------------------------------------------------------
                    */
                    if (
                        $delta < 0 &&
                        $currentStock < abs($delta)
                    ) {

                        throw new Exception(
                            (
                                $isPashto
                                    ? "دا پېرود نه شي تازه کېدای. د '"
                                    : "Cannot update this purchase. Current stock for '"
                            )
                            . $stockIngredient["ingredient_name"]
                            . (
                                $isPashto
                                    ? "' اوسنی سټاک "
                                    : "' is "
                            )
                            . $currentStock
                            . (
                                $isPashto
                                    ? " دی، خو "
                                    : ", but "
                            )
                            . abs($delta)
                            . (
                                $isPashto
                                    ? " باید له سټاک څخه کم شي."
                                    : " must be removed."
                            )
                        );
                    }

                    $stockUpdateStmt->execute([
                        ":delta" => $delta,
                        ":ingredient_id" => $ingredientId
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | INSERT NEW ITEMS
                |--------------------------------------------------------------------------
                */
                $insertItemStmt = $conn->prepare("
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

                foreach ($purchaseItems as $item) {

                    $insertItemStmt->execute([
                        ":purchase_id" => $purchase_id,
                        ":ingredient_id" => $item["ingredient_id"],
                        ":quantity" => $item["quantity"],
                        ":unit_price" => $item["unit_price"],
                        ":total_price" => $item["total_price"]
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | COMMIT
                |--------------------------------------------------------------------------
                */
                $conn->commit();

                header(
                    "Location: view.php?id="
                    . $purchase_id
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
    }
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
        <?= $isPashto ? "د پېرود سمون" : "Edit Purchase" ?>
        #<?= $purchase_id ?>
        - Bakery Management
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

        <?php if ($isPashto): ?>

        body {
            font-family: Tahoma, Arial, sans-serif;
        }

        <?php endif; ?>

    </style>

</head>

<body>

<nav class="navbar">

    <div class="container-fluid px-4">

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


        <div class="d-flex align-items-center gap-2">

            <!-- Language -->

            <a
                href="?id=<?= $purchase_id ?>&lang=ps"
                class="btn btn-sm <?= $isPashto ? "btn-primary" : "btn-outline-primary" ?>"
            >
                پښتو
            </a>

            <a
                href="?id=<?= $purchase_id ?>&lang=en"
                class="btn btn-sm <?= !$isPashto ? "btn-primary" : "btn-outline-primary" ?>"
            >
                English
            </a>


            <span class="ms-2 me-2">

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars(
                    $_SESSION["full_name"] ?? "User",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </span>


            <a
                href="../public/logout.php"
                class="btn btn-outline-danger btn-sm"
            >

                <i class="bi bi-box-arrow-right"></i>

                <?= $isPashto ? "وتل" : "Logout" ?>

            </a>

        </div>

    </div>

</nav>


<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2>

                <i class="bi bi-pencil-square"></i>

                <?= $isPashto
                    ? "د پېرود سمون"
                    : "Edit Purchase"
                ?>

                #<?= $purchase_id ?>

            </h2>

            <p class="text-muted mb-0">

                <?= $isPashto
                    ? "د پېرود معلومات تازه کړئ"
                    : "Update purchase information"
                ?>

            </p>

        </div>


        <a
            href="view.php?id=<?= $purchase_id ?>"
            class="btn btn-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            <?= $isPashto ? "بېرته" : "Back" ?>

        </a>

    </div>


    <?php if (isset($error)): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle-fill"></i>

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    <?php endif; ?>


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
                                    <?= (
                                        (int) (
                                            $purchase["supplier_id"] ?? 0
                                        )
                                        ===
                                        (int) $supplier["supplier_id"]
                                    )
                                        ? "selected"
                                        : ""
                                    ?>
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

                        <div class="d-flex justify-content-between align-items-center">

                            <h5 class="mb-0">

                                <i class="bi bi-basket"></i>

                                <?= $isPashto
                                    ? "اجناس"
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
                                    ? "جنس اضافه کړئ"
                                    : "Add Ingredient"
                                ?>

                            </button>

                        </div>

                    </div>


                    <div class="card-body">

                        <div id="ingredientContainer">

                            <?php if (empty($items)): ?>

                                <div class="ingredient-row">

                                    <div class="row g-3 align-items-end">


                                        <div class="col-md-5">

                                            <label class="form-label">

                                                <?= $isPashto
                                                    ? "جنس"
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
                                                        ? "جنس انتخاب کړئ"
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
                                                        <?= htmlspecialchars(
                                                            $ingredient["unit"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>

                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>


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


                                        <div class="col-md-2">

                                            <button
                                                type="button"
                                                class="btn btn-outline-danger w-100 remove-ingredient"
                                            >

                                                <i class="bi bi-trash"></i>

                                                <?= $isPashto
                                                    ? "لرې کول"
                                                    : "Remove"
                                                ?>

                                            </button>

                                        </div>

                                    </div>


                                    <div class="text-end mt-3">

                                        <strong>

                                            <?= $isPashto
                                                ? "د جنس ټول:"
                                                : "Item Total:"
                                            ?>

                                            $

                                            <span class="row-total">
                                                0.00
                                            </span>

                                        </strong>

                                    </div>

                                </div>


                            <?php else: ?>


                                <?php foreach ($items as $item): ?>

                                    <div class="ingredient-row">

                                        <div class="row g-3 align-items-end">


                                            <div class="col-md-5">

                                                <label class="form-label">

                                                    <?= $isPashto
                                                        ? "جنس"
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
                                                            ? "جنس انتخاب کړئ"
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
                                                            <?= (
                                                                (int) $item["ingredient_id"]
                                                                ===
                                                                (int) $ingredient["ingredient_id"]
                                                            )
                                                                ? "selected"
                                                                : ""
                                                            ?>
                                                        >

                                                            <?= htmlspecialchars(
                                                                $ingredient["ingredient_name"],
                                                                ENT_QUOTES,
                                                                "UTF-8"
                                                            ) ?>

                                                            -
                                                            <?= htmlspecialchars(
                                                                $ingredient["unit"],
                                                                ENT_QUOTES,
                                                                "UTF-8"
                                                            ) ?>

                                                        </option>

                                                    <?php endforeach; ?>

                                                </select>

                                            </div>


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
                                                    value="<?= htmlspecialchars(
                                                        $item["quantity"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>"
                                                    required
                                                >

                                            </div>


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
                                                    value="<?= htmlspecialchars(
                                                        $item["unit_price"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>"
                                                    required
                                                >

                                            </div>


                                            <div class="col-md-2">

                                                <button
                                                    type="button"
                                                    class="btn btn-outline-danger w-100 remove-ingredient"
                                                >

                                                    <i class="bi bi-trash"></i>

                                                    <?= $isPashto
                                                        ? "لرې کول"
                                                        : "Remove"
                                                    ?>

                                                </button>

                                            </div>

                                        </div>


                                        <div class="text-end mt-3">

                                            <strong>

                                                <?= $isPashto
                                                    ? "د جنس ټول:"
                                                    : "Item Total:"
                                                ?>

                                                $

                                                <span class="row-total">
                                                    0.00
                                                </span>

                                            </strong>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>


            <!-- SUMMARY -->

            <div class="col-lg-4">

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

                            <div class="d-flex justify-content-between">

                                <span>

                                    <?= $isPashto
                                        ? "ټول مبلغ"
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
                                    ? "تادیه شوی مبلغ"
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
                                value="<?= htmlspecialchars(
                                    $purchase["paid_amount"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                            >

                        </div>


                        <!-- DUE -->

                        <div class="summary-box mb-3">

                            <div class="d-flex justify-content-between">

                                <span>

                                    <?= $isPashto
                                        ? "پاتې مبلغ"
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
                                    ? "یادښتونه"
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
                            ><?= htmlspecialchars(
                                $purchase["notes"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?></textarea>

                        </div>


                        <!-- UPDATE -->

                        <button
                            type="submit"
                            class="btn btn-success btn-lg w-100"
                        >

                            <i class="bi bi-save"></i>

                            <?= $isPashto
                                ? "پېرود تازه کړئ"
                                : "Update Purchase"
                            ?>

                        </button>

                    </div>

                </div>

            </div>

        </div>

    </form>

</div>


<script>

const ingredientContainer =
    document.getElementById("ingredientContainer");

const addIngredientBtn =
    document.getElementById("addIngredientBtn");

const paidAmountInput =
    document.getElementById("paidAmount");


addIngredientBtn.addEventListener(
    "click",
    function () {

        const firstRow =
            document.querySelector(".ingredient-row");

        if (!firstRow) {
            return;
        }

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

        ingredientContainer.appendChild(
            newRow
        );

        updateTotals();
    }
);


document.addEventListener(
    "click",
    function (event) {

        const button =
            event.target.closest(".remove-ingredient");

        if (!button) {
            return;
        }

        const rows =
            document.querySelectorAll(
                ".ingredient-row"
            );

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
        }

        updateTotals();
    }
);


document.addEventListener(
    "change",
    function (event) {

        if (
            event.target.classList.contains(
                "ingredient-select"
            )
        ) {

            const row =
                event.target.closest(
                    ".ingredient-row"
                );

            const option =
                event.target.options[
                    event.target.selectedIndex
                ];

            const price =
                option
                    ? option.getAttribute(
                        "data-price"
                    )
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


function updateRowTotal(row) {

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
    ).textContent =
        total.toFixed(2);
}


function updateTotals() {

    let totalAmount = 0;

    document
        .querySelectorAll(
            ".ingredient-row"
        )
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
            parseFloat(
                paidAmountInput.value
            ) || 0
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


updateTotals();

</script>


<script src="../assets/js/bootstrap.bundle.min.js"></script>

</body>
</html>
