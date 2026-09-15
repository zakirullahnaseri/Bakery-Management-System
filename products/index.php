
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";

$error = "";


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
   ADD PRODUCT
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


    if ($product_name === "") {

        $error = $isPashto
            ? "د محصول نوم ضروري دی."
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
            ? "د سټاک مقدار منفي کېدای نشي."
            : "Stock quantity cannot be negative.";

    } elseif ($minimum_stock < 0) {

        $error = $isPashto
            ? "لږ تر لږه سټاک منفي کېدای نشي."
            : "Minimum stock cannot be negative.";

    } elseif (!in_array($status, ["available", "unavailable"], true)) {

        $error = $isPashto
            ? "د محصول حالت ناسم دی."
            : "Invalid product status.";

    } else {

        try {

            $stmt = $conn->prepare("
                INSERT INTO products
                (
                    category_id,
                    product_name,
                    description,
                    selling_price,
                    cost_price,
                    stock_quantity,
                    unit,
                    minimum_stock,
                    status
                )
                VALUES
                (
                    :category_id,
                    :product_name,
                    :description,
                    :selling_price,
                    :cost_price,
                    :stock_quantity,
                    :unit,
                    :minimum_stock,
                    :status
                )
            ");

            $stmt->execute([

                ":category_id" => $category_id,

                ":product_name" => $product_name,

                ":description" => $description,

                ":selling_price" => $selling_price,

                ":cost_price" => $cost_price,

                ":stock_quantity" => $stock_quantity,

                ":unit" => $unit,

                ":minimum_stock" => $minimum_stock,

                ":status" => $status
            ]);


            /*
            |--------------------------------------------------------------------------
            | Redirect after successful POST
            | Prevents duplicate product on refresh
            |--------------------------------------------------------------------------
            */

            $lang = currentLanguage();

            header(
                "Location: index.php?success=1&lang=" . urlencode($lang)
            );

            exit;


        } catch (PDOException $e) {

            $error = $isPashto
                ? "د محصول په اضافه کولو کې ستونزه رامنځته شوه."
                : "Error adding product.";
        }
    }
}


/* =====================================================
   GET PRODUCTS
===================================================== */

$productStmt = $conn->query("
    SELECT
        p.product_id,
        p.product_name,
        p.description,
        p.selling_price,
        p.cost_price,
        p.stock_quantity,
        p.unit,
        p.minimum_stock,
        p.status,
        p.created_at,

        c.category_name

    FROM products p

    LEFT JOIN categories c
        ON p.category_id = c.category_id

    ORDER BY p.product_id DESC
");

$products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

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
            ? "محصولات - د بیکري مدیریت"
            : "Products - Bakery Management"
        ?>

    </title>


    <link
        rel="stylesheet"
        href="../assets/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/bootstrap-icons.css"
    >
    <link rel="stylesheet" href="../assets/css/backgrounds.css">



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

        .form-label {
            font-weight: 600;
        }

        .table th {
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .product-list-card {
            min-height: 500px;
        }

        .profit-positive {
            color: #198754;
            font-weight: 600;
        }

        .profit-negative {
            color: #dc3545;
            font-weight: 600;
        }

        <?php if ($isPashto): ?>

        body {
            direction: rtl;
            text-align: right;
        }

        .form-control,
        .form-select {
            text-align: right;
        }

        .input-group > .input-group-text {
            direction: ltr;
        }

        <?php endif; ?>

    </style>

</head>


<body class="bg-products">


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



        <div class="d-flex align-items-center gap-2">


            <a
                href="?lang=ps"
                class="btn btn-outline-primary btn-sm"
            >
                پښتو
            </a>


            <a
                href="?lang=en"
                class="btn btn-outline-secondary btn-sm"
            >
                English
            </a>



            <span class="me-2">

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars(
                    $_SESSION["full_name"] ??
                    $_SESSION["name"] ??
                    $_SESSION["username"] ??
                    "User"
                ) ?>

            </span>



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

    <div class="d-flex justify-content-between align-items-center mb-4">


        <div>

            <h2>

                <i class="bi bi-box-seam"></i>

                <?= $isPashto
                    ? "محصولات"
                    : "Products"
                ?>

            </h2>


            <p class="text-muted mb-0">

                <?= $isPashto
                    ? "محصولات اضافه او د بیکري محصولات مدیریت کړئ"
                    : "Add products and manage your bakery products"
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



    <!-- =====================================================
         SUCCESS MESSAGE
    ===================================================== -->

    <?php if (isset($_GET["success"]) && $_GET["success"] === "1"): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
        >

            <i class="bi bi-check-circle"></i>

            <?= $isPashto
                ? "محصول په بریالیتوب سره اضافه شو."
                : "Product added successfully."
            ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         ERROR MESSAGE
    ===================================================== -->

    <?php if ($error !== ""): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>



    <div class="row g-4">


        <!-- =================================================
             LEFT SIDE - ADD PRODUCT
        ================================================= -->

        <div class="col-lg-4">


            <div class="card shadow-sm">


                <div class="card-header bg-white py-3">


                    <h5 class="mb-0">

                        <i class="bi bi-plus-circle"></i>

                        <?= $isPashto
                            ? "نوی محصول اضافه کړئ"
                            : "Add Product"
                        ?>

                    </h5>


                </div>



                <div class="card-body">


                    <form method="POST">


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
                                placeholder="<?= $isPashto
                                    ? "لکه: چاکلېټ کیک"
                                    : "e.g. Chocolate Cake"
                                ?>"
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



                                <?php foreach ($categories as $category): ?>

                                    <option
                                        value="<?= (int) $category["category_id"] ?>"
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
                                rows="3"
                                placeholder="<?= $isPashto
                                    ? "اختیاري تشریح..."
                                    : "Optional description..."
                                ?>"
                            ></textarea>


                        </div>



                        <!-- SELLING PRICE -->

                        <div class="mb-3">


                            <label class="form-label">

                                <?= $isPashto
                                    ? "د خرڅلاو بیه"
                                    : "Selling Price"
                                ?>

                            </label>


                            <div class="input-group">


                                <span class="input-group-text">
                                    $
                                </span>


                                <input
                                    type="number"
                                    name="selling_price"
                                    id="sellingPrice"
                                    class="form-control"
                                    min="0"
                                    step="0.01"
                                    value="0"
                                    required
                                >


                            </div>


                        </div>



                        <!-- COST PRICE -->

                        <div class="mb-3">


                            <label class="form-label">

                                <?= $isPashto
                                    ? "د اخیستلو بیه"
                                    : "Cost Price"
                                ?>

                            </label>


                            <div class="input-group">


                                <span class="input-group-text">
                                    $
                                </span>


                                <input
                                    type="number"
                                    name="cost_price"
                                    id="costPrice"
                                    class="form-control"
                                    min="0"
                                    step="0.01"
                                    value="0"
                                    required
                                >


                            </div>


                        </div>



                        <!-- PROFIT PREVIEW -->

                        <div class="alert alert-light border">


                            <div class="d-flex justify-content-between">


                                <span>

                                    <?= $isPashto
                                        ? "اټکلي ګټه"
                                        : "Estimated Profit"
                                    ?>

                                </span>


                                <strong id="profitPreview">

                                    $0.00

                                </strong>


                            </div>


                        </div>



                        <!-- STOCK -->

                        <div class="mb-3">


                            <label class="form-label">

                                <?= $isPashto
                                    ? "د سټاک مقدار"
                                    : "Stock Quantity"
                                ?>

                            </label>


                            <input
                                type="number"
                                name="stock_quantity"
                                class="form-control"
                                min="0"
                                step="0.001"
                                value="0"
                                required
                            >


                        </div>



                        <!-- UNIT -->

                        <div class="mb-3">


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
                                value="piece"
                                placeholder="<?= $isPashto
                                    ? "ټوټه، کیلو، بکس..."
                                    : "piece, kg, box..."
                                ?>"
                                required
                            >


                        </div>



                        <!-- MINIMUM STOCK -->

                        <div class="mb-3">


                            <label class="form-label">

                                <?= $isPashto
                                    ? "لږ تر لږه سټاک"
                                    : "Minimum Stock"
                                ?>

                            </label>


                            <input
                                type="number"
                                name="minimum_stock"
                                class="form-control"
                                min="0"
                                step="0.001"
                                value="0"
                            >


                        </div>



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
                            >


                                <option value="available">

                                    <?= $isPashto
                                        ? "موجود"
                                        : "Available"
                                    ?>

                                </option>


                                <option value="unavailable">

                                    <?= $isPashto
                                        ? "نا موجود"
                                        : "Unavailable"
                                    ?>

                                </option>


                            </select>


                        </div>



                        <!-- SUBMIT -->

                        <button
                            type="submit"
                            class="btn btn-success w-100"
                        >

                            <i class="bi bi-check-circle"></i>

                            <?= $isPashto
                                ? "محصول اضافه کړئ"
                                : "Add Product"
                            ?>

                        </button>


                    </form>


                </div>


            </div>


        </div>



        <!-- =================================================
             RIGHT SIDE - PRODUCT LIST
        ================================================= -->

        <div class="col-lg-8">


            <div class="card shadow-sm product-list-card">


                <div class="card-header bg-white py-3">


                    <div
                        class="d-flex justify-content-between align-items-center"
                    >


                        <h5 class="mb-0">


                            <i class="bi bi-list-ul"></i>


                            <?= $isPashto
                                ? "د محصولاتو لېست"
                                : "Product List"
                            ?>


                        </h5>



                        <span class="badge bg-primary">


                            <?= count($products) ?>


                            <?= $isPashto
                                ? "محصولات"
                                : "Products"
                            ?>


                        </span>


                    </div>


                </div>



                <div class="card-body">


                    <div class="table-responsive">


                        <table class="table table-hover align-middle">


                            <thead class="table-light">


                                <tr>


                                    <th>#</th>


                                    <th>
                                        <?= $isPashto
                                            ? "محصول"
                                            : "Product"
                                        ?>
                                    </th>


                                    <th>
                                        <?= $isPashto
                                            ? "کټګوري"
                                            : "Category"
                                        ?>
                                    </th>


                                    <th>
                                        <?= $isPashto
                                            ? "لګښت"
                                            : "Cost"
                                        ?>
                                    </th>


                                    <th>
                                        <?= $isPashto
                                            ? "خرڅلاو"
                                            : "Selling"
                                        ?>
                                    </th>


                                    <th>
                                        <?= $isPashto
                                            ? "ګټه"
                                            : "Profit"
                                        ?>
                                    </th>


                                    <th>
                                        <?= $isPashto
                                            ? "سټاک"
                                            : "Stock"
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
                                            ? "عمل"
                                            : "Actions"
                                        ?>
                                    </th>


                                </tr>


                            </thead>



                            <tbody>


                            <?php if (empty($products)): ?>


                                <tr>


                                    <td
                                        colspan="9"
                                        class="text-center text-muted py-5"
                                    >


                                        <i
                                            class="bi bi-box-seam fs-1 d-block mb-3"
                                        ></i>


                                        <?= $isPashto
                                            ? "هیڅ محصول پیدا نه شو."
                                            : "No products found."
                                        ?>


                                    </td>


                                </tr>


                            <?php else: ?>


                                <?php foreach ($products as $product): ?>


                                    <?php

                                    $profit =
                                        (float) $product["selling_price"]
                                        -
                                        (float) $product["cost_price"];

                                    ?>


                                    <tr>


                                        <!-- ID -->

                                        <td>

                                            #

                                            <?= (int) $product["product_id"] ?>

                                        </td>



                                        <!-- PRODUCT -->

                                        <td>


                                            <strong>

                                                <?= htmlspecialchars(
                                                    $product["product_name"]
                                                ) ?>

                                            </strong>



                                            <?php if (
                                                !empty($product["description"])
                                            ): ?>


                                                <small
                                                    class="text-muted d-block"
                                                >

                                                    <?= htmlspecialchars(
                                                        $product["description"]
                                                    ) ?>

                                                </small>


                                            <?php endif; ?>


                                        </td>



                                        <!-- CATEGORY -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $product["category_name"]
                                                ??
                                                (
                                                    $isPashto
                                                        ? "کټګوري نشته"
                                                        : "No Category"
                                                )
                                            ) ?>

                                        </td>



                                        <!-- COST -->

                                        <td>

                                            $

                                            <?= number_format(
                                                (float) $product["cost_price"],
                                                2
                                            ) ?>

                                        </td>



                                        <!-- SELLING -->

                                        <td>


                                            <strong>

                                                $

                                                <?= number_format(
                                                    (float) $product["selling_price"],
                                                    2
                                                ) ?>

                                            </strong>


                                        </td>



                                        <!-- PROFIT -->

                                        <td>


                                            <span
                                                class="<?= $profit >= 0
                                                    ? "profit-positive"
                                                    : "profit-negative"
                                                ?>"
                                            >

                                                $

                                                <?= number_format(
                                                    $profit,
                                                    2
                                                ) ?>


                                            </span>


                                        </td>



                                        <!-- STOCK -->

                                        <td>


                                            <?= number_format(
                                                (float) $product["stock_quantity"],
                                                3
                                            ) ?>


                                            <?= htmlspecialchars(
                                                $product["unit"]
                                            ) ?>


                                        </td>



                                        <!-- STATUS -->

                                        <td>


                                            <?php if (
                                                $product["status"]
                                                === "available"
                                            ): ?>


                                                <span
                                                    class="badge bg-success"
                                                >

                                                    <?= $isPashto
                                                        ? "موجود"
                                                        : "Available"
                                                    ?>

                                                </span>


                                            <?php else: ?>


                                                <span
                                                    class="badge bg-secondary"
                                                >

                                                    <?= $isPashto
                                                        ? "نا موجود"
                                                        : "Unavailable"
                                                    ?>

                                                </span>


                                            <?php endif; ?>


                                        </td>



                                        <!-- ACTIONS -->

                                        <td>


                                            <div class="btn-group">


                                                <a
                                                    href="view.php?id=<?= (int) $product["product_id"] ?>"
                                                    class="btn btn-sm btn-info text-white"
                                                    title="<?= $isPashto
                                                        ? "کتل"
                                                        : "View"
                                                    ?>"
                                                >

                                                    <i class="bi bi-eye"></i>

                                                </a>



                                                <a
                                                    href="edit.php?id=<?= (int) $product["product_id"] ?>"
                                                    class="btn btn-sm btn-warning"
                                                    title="<?= $isPashto
                                                        ? "سمون"
                                                        : "Edit"
                                                    ?>"
                                                >

                                                    <i class="bi bi-pencil"></i>

                                                </a>



                                                <a
                                                    href="delete.php?id=<?= (int) $product["product_id"] ?>"
                                                    class="btn btn-sm btn-danger"
                                                    title="<?= $isPashto
                                                        ? "حذف"
                                                        : "Delete"
                                                    ?>"
                                                    onclick="return confirm('<?= $isPashto
                                                        ? "ایا ډاډه یاست چې دا محصول حذف کړئ؟"
                                                        : "Are you sure you want to delete this product?"
                                                    ?>');"
                                                >

                                                    <i class="bi bi-trash"></i>

                                                </a>


                                            </div>


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


    </div>


</div>



<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>

 const sellingPrice =
    document.getElementById("sellingPrice");

const costPrice =
    document.getElementById("costPrice");

const profitPreview =
    document.getElementById("profitPreview");


function updateProfit() {

    const selling =
        parseFloat(sellingPrice.value) || 0;

    const cost =
        parseFloat(costPrice.value) || 0;

    const profit =
        selling - cost;


    profitPreview.textContent =
        "$" + profit.toFixed(2);


    if (profit >= 0) {

        profitPreview.className =
            "text-success fw-bold";

    } else {

        profitPreview.className =
            "text-danger fw-bold";

    }

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



<script src="../assets/js/bootstrap.bundle.min.js"></script>


</body>

</html>

