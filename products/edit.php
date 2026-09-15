<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";


/* =====================================================
   GET PRODUCT ID
===================================================== */

$product_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($product_id <= 0) {
    header("Location: index.php");
    exit;
}


/* =====================================================
   GET PRODUCT
===================================================== */

$productStmt = $conn->prepare("
    SELECT
        product_id,
        category_id,
        product_name,
        description,
        selling_price,
        cost_price,
        stock_quantity,
        unit,
        minimum_stock,
        status
    FROM products
    WHERE product_id = :product_id
");

$productStmt->execute([
    ":product_id" => $product_id
]);

$product = $productStmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die($isPashto ? "محصول ونه موندل شو." : "Product not found.");
}


/* =====================================================
   GET CATEGORIES
===================================================== */

$categoryStmt = $conn->query("
    SELECT
        category_id,
        category_name
    FROM categories
    ORDER BY category_name ASC
");

$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   UPDATE PRODUCT
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $category_id = !empty($_POST["category_id"])
        ? (int) $_POST["category_id"]
        : null;

    $product_name = trim($_POST["product_name"] ?? "");

    $description = trim($_POST["description"] ?? "");

    $selling_price = (float) ($_POST["selling_price"] ?? 0);

    $cost_price = (float) ($_POST["cost_price"] ?? 0);

    $stock_quantity = (float) ($_POST["stock_quantity"] ?? 0);

    $unit = trim($_POST["unit"] ?? "piece");

    $minimum_stock = (float) ($_POST["minimum_stock"] ?? 0);

    $status = $_POST["status"] ?? "available";


    /* =================================================
       VALIDATION
    ================================================= */

    if ($product_name === "") {

        $error = $isPashto
            ? "د محصول نوم اړین دی."
            : "Product name is required.";

    } elseif ($selling_price < 0) {

        $error = $isPashto
            ? "د خرڅلاو بیه منفي کېدای نشي."
            : "Selling price cannot be negative.";

    } elseif ($cost_price < 0) {

        $error = $isPashto
            ? "د اخیستلو بیه منفي کېدای نشي."
            : "Cost price cannot be negative.";

    } elseif ($stock_quantity < 0) {

        $error = $isPashto
            ? "د ذخیرې مقدار منفي کېدای نشي."
            : "Stock quantity cannot be negative.";

    } elseif ($minimum_stock < 0) {

        $error = $isPashto
            ? "د لږ تر لږه ذخیرې مقدار منفي کېدای نشي."
            : "Minimum stock cannot be negative.";

    } elseif (!in_array(
        $status,
        ["available", "unavailable"],
        true
    )) {

        $error = $isPashto
            ? "د محصول حالت ناسم دی."
            : "Invalid product status.";

    } else {

        try {

            $updateStmt = $conn->prepare("
                UPDATE products
                SET
                    category_id = :category_id,
                    product_name = :product_name,
                    description = :description,
                    selling_price = :selling_price,
                    cost_price = :cost_price,
                    stock_quantity = :stock_quantity,
                    unit = :unit,
                    minimum_stock = :minimum_stock,
                    status = :status
                WHERE product_id = :product_id
            ");

            $updateStmt->execute([

                ":category_id" => $category_id,

                ":product_name" => $product_name,

                ":description" => $description,

                ":selling_price" => $selling_price,

                ":cost_price" => $cost_price,

                ":stock_quantity" => $stock_quantity,

                ":unit" => $unit,

                ":minimum_stock" => $minimum_stock,

                ":status" => $status,

                ":product_id" => $product_id
            ]);


            $lang = currentLanguage();

            header(
                "Location: view.php?id="
                . $product_id
                . "&updated=1&lang="
                . urlencode($lang)
            );

            exit;

        } catch (PDOException $e) {

            $error = $isPashto
                ? "د محصول د تازه کولو پر مهال ستونزه رامنځته شوه."
                : "Error updating product: " . $e->getMessage();
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
    <?= $isPashto
        ? "د محصول سمون - بیکري مدیریت"
        : "Edit Product - Bakery Management"
    ?>
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

    .form-control,
    .form-select {
        border-radius: 8px;
    }

    <?php if ($isPashto): ?>

    body {
        text-align: right;
    }

    .navbar-brand {
        margin-right: 0;
        margin-left: 1rem;
    }

    .me-3 {
        margin-right: 0 !important;
        margin-left: 1rem !important;
    }

    .btn i {
        margin-left: 4px;
        margin-right: 0;
    }

    <?php endif; ?>

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
        class="navbar-brand fw-bold"
    >

        <i class="bi bi-shop"></i>

        <?= $isPashto
            ? "د بیکري سیستم"
            : "Bakery System"
        ?>

    </a>


    <div class="d-flex align-items-center">


        <!-- LANGUAGE -->

        <div class="me-3">

            <a
                href="?id=<?= $product_id ?>&lang=ps"
                class="btn btn-sm <?= $isPashto
                    ? "btn-primary"
                    : "btn-outline-primary"
                ?>"
            >
                پښتو
            </a>

            <a
                href="?id=<?= $product_id ?>&lang=en"
                class="btn btn-sm <?= !$isPashto
                    ? "btn-primary"
                    : "btn-outline-primary"
                ?>"
            >
                English
            </a>

        </div>


        <!-- USER -->

        <span class="me-3">

            <i class="bi bi-person-circle"></i>

            <?= htmlspecialchars(
                $_SESSION["full_name"]
            ) ?>

        </span>


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

<!-- =====================================================
     MAIN
===================================================== -->

<div class="container-fluid p-4">


<!-- HEADER -->

<div
    class="d-flex justify-content-between align-items-center mb-4"
>

    <div>

        <h2>

            <i class="bi bi-pencil-square"></i>

            <?= $isPashto
                ? "د محصول سمون"
                : "Edit Product"
            ?>

            #<?= $product_id ?>

        </h2>


        <p class="text-muted mb-0">

            <?= $isPashto
                ? "د محصول معلومات تازه کړئ"
                : "Update product information"
            ?>

        </p>

    </div>


    <a
        href="view.php?id=<?= $product_id ?>"
        class="btn btn-secondary"
    >

        <i class="bi bi-arrow-left"></i>

        <?= $isPashto
            ? "بېرته"
            : "Back"
        ?>

    </a>

</div>


<!-- ERROR -->

<?php if (isset($error)): ?>

    <div class="alert alert-danger">

        <i class="bi bi-exclamation-triangle"></i>

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<!-- FORM -->

<form method="POST">

    <div class="row g-4">


        <!-- LEFT -->

        <div class="col-lg-8">


            <!-- BASIC INFORMATION -->

            <div class="card shadow-sm mb-4">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-box-seam"></i>

                        <?= $isPashto
                            ? "د محصول معلومات"
                            : "Product Information"
                        ?>

                    </h5>

                </div>


                <div class="card-body">


                    <!-- PRODUCT NAME -->

                    <div class="mb-3">

                        <label class="form-label">

                            <?= $isPashto
                                ? "د محصول نوم"
                                : "Product Name"
                            ?>

                        </label>


                        <input
                            type="text"
                            name="product_name"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $product["product_name"]
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- CATEGORY -->

                    <div class="mb-3">

                        <label class="form-label">

                            <?= $isPashto
                                ? "کټګوري"
                                : "Category"
                            ?>

                        </label>


                        <select
                            name="category_id"
                            class="form-select"
                        >

                            <option value="">

                                <?= $isPashto
                                    ? "کټګوري وټاکئ"
                                    : "Select Category"
                                ?>

                            </option>


                            <?php foreach (
                                $categories
                                as $category
                            ): ?>

                                <option
                                    value="<?= (int) $category["category_id"] ?>"
                                    <?= (
                                        (int) $product["category_id"]
                                        ===
                                        (int) $category["category_id"]
                                    )
                                        ? "selected"
                                        : ""
                                    ?>
                                >

                                    <?= htmlspecialchars(
                                        $category["category_name"]
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- DESCRIPTION -->

                    <div class="mb-3">

                        <label class="form-label">

                            <?= $isPashto
                                ? "تشریح"
                                : "Description"
                            ?>

                        </label>


                        <textarea
                            name="description"
                            class="form-control"
                            rows="5"
                        ><?= htmlspecialchars(
                            $product["description"] ?? ""
                        ) ?></textarea>

                    </div>

                </div>

            </div>


            <!-- PRICES -->

            <div class="card shadow-sm mb-4">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-cash-stack"></i>

                        <?= $isPashto
                            ? "بیې"
                            : "Pricing"
                        ?>

                    </h5>

                </div>


                <div class="card-body">

                    <div class="row g-3">


                        <!-- SELLING PRICE -->

                        <div class="col-md-6">

                            <label class="form-label">

                                <?= $isPashto
                                    ? "د خرڅلاو بیه"
                                    : "Selling Price"
                                ?>

                            </label>


                            <input
                                type="number"
                                name="selling_price"
                                class="form-control"
                                min="0"
                                step="0.01"
                                value="<?= htmlspecialchars(
                                    $product["selling_price"]
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- COST PRICE -->

                        <div class="col-md-6">

                            <label class="form-label">

                                <?= $isPashto
                                    ? "د اخیستلو بیه"
                                    : "Cost Price"
                                ?>

                            </label>


                            <input
                                type="number"
                                name="cost_price"
                                class="form-control"
                                min="0"
                                step="0.01"
                                value="<?= htmlspecialchars(
                                    $product["cost_price"]
                                ) ?>"
                                required
                            >

                        </div>

                    </div>

                </div>

            </div>


            <!-- STOCK -->

            <div class="card shadow-sm">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-boxes"></i>

                        <?= $isPashto
                            ? "د ذخیرې معلومات"
                            : "Stock Information"
                        ?>

                    </h5>

                </div>


                <div class="card-body">

                    <div class="row g-3">


                        <!-- STOCK -->

                        <div class="col-md-4">

                            <label class="form-label">

                                <?= $isPashto
                                    ? "د ذخیرې مقدار"
                                    : "Stock Quantity"
                                ?>

                            </label>


                            <input
                                type="number"
                                name="stock_quantity"
                                class="form-control"
                                min="0"
                                step="0.01"
                                value="<?= htmlspecialchars(
                                    $product["stock_quantity"]
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- UNIT -->

                        <div class="col-md-4">

                            <label class="form-label">

                                <?= $isPashto
                                    ? "واحد"
                                    : "Unit"
                                ?>

                            </label>


                            <input
                                type="text"
                                name="unit"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $product["unit"]
                                ) ?>"
                                placeholder="<?= $isPashto
                                    ? "ټوټه، کیلو، بکس..."
                                    : "piece, kg, box..."
                                ?>"
                                required
                            >

                        </div>


                        <!-- MINIMUM STOCK -->

                        <div class="col-md-4">

                            <label class="form-label">

                                <?= $isPashto
                                    ? "لږ تر لږه ذخیره"
                                    : "Minimum Stock"
                                ?>

                            </label>


                            <input
                                type="number"
                                name="minimum_stock"
                                class="form-control"
                                min="0"
                                step="0.01"
                                value="<?= htmlspecialchars(
                                    $product["minimum_stock"]
                                ) ?>"
                                required
                            >

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- RIGHT -->

        <div class="col-lg-4">


            <div class="card shadow-sm">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-gear"></i>

                        <?= $isPashto
                            ? "د محصول تنظیمات"
                            : "Product Settings"
                        ?>

                    </h5>

                </div>


                <div class="card-body">


                    <!-- STATUS -->

                    <div class="mb-4">

                        <label class="form-label">

                            <?= $isPashto
                                ? "حالت"
                                : "Status"
                            ?>

                        </label>


                        <select
                            name="status"
                            class="form-select"
                            required
                        >

                            <option
                                value="available"
                                <?= $product["status"]
                                    === "available"
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= $isPashto
                                    ? "موجود"
                                    : "Available"
                                ?>

                            </option>


                            <option
                                value="unavailable"
                                <?= $product["status"]
                                    === "unavailable"
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= $isPashto
                                    ? "نا موجود"
                                    : "Unavailable"
                                ?>

                            </option>

                        </select>

                    </div>


                    <!-- PROFIT PREVIEW -->

                    <div class="alert alert-light border">

                        <div
                            class="d-flex justify-content-between"
                        >

                            <span>

                                <?= $isPashto
                                    ? "د هر واحد ګټه"
                                    : "Profit Per Unit"
                                ?>

                            </span>


                            <strong id="profitPreview">

                                $0.00

                            </strong>

                        </div>

                    </div>


                    <!-- UPDATE -->

                    <button
                        type="submit"
                        class="btn btn-success btn-lg w-100 mb-2"
                    >

                        <i class="bi bi-save"></i>

                        <?= $isPashto
                            ? "محصول تازه کړئ"
                            : "Update Product"
                        ?>

                    </button>


                    <!-- CANCEL -->

                    <a
                        href="view.php?id=<?= $product_id ?>"
                        class="btn btn-outline-secondary w-100"
                    >

                        <?= $isPashto
                            ? "لغوه"
                            : "Cancel"
                        ?>

                    </a>

                </div>

            </div>

        </div>

    </div>

</form>


</div>

<script>

const sellingPrice =
    document.querySelector(
        'input[name="selling_price"]'
    );

const costPrice =
    document.querySelector(
        'input[name="cost_price"]'
    );

const profitPreview =
    document.getElementById(
        "profitPreview"
    );


function updateProfit() {

    const selling =
        parseFloat(
            sellingPrice.value
        ) || 0;

    const cost =
        parseFloat(
            costPrice.value
        ) || 0;

    const profit =
        selling - cost;

    profitPreview.textContent =
        "$" + profit.toFixed(2);
}


sellingPrice.addEventListener(
    "input",
    updateProfit
);

costPrice.addEventListener(
    "input",
    updateProfit
);

updateProfit();

</script>

<!-- Bootstrap JavaScript -->

<script
    src="../assets/js/bootstrap.bundle.min.js"
></script>

</body>

</html>
