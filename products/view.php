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

$stmt = $conn->prepare("
    SELECT
        p.product_id,
        p.category_id,
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

    WHERE p.product_id = :product_id
");

$stmt->execute([
    ":product_id" => $product_id
]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$product) {
    die(
        $isPashto
            ? "محصول ونه موندل شو."
            : "Product not found."
    );
}


/* =====================================================
   CALCULATE PROFIT
===================================================== */

$cost_price =
    (float) $product["cost_price"];

$selling_price =
    (float) $product["selling_price"];

$profit =
    $selling_price - $cost_price;


/* =====================================================
   STOCK STATUS
===================================================== */

$stock_quantity =
    (float) $product["stock_quantity"];

$minimum_stock =
    (float) $product["minimum_stock"];


if ($stock_quantity <= 0) {

    $stock_status = $isPashto
        ? "ذخیره ختمه ده"
        : "Out of Stock";

    $stock_badge = "danger";

} elseif ($stock_quantity <= $minimum_stock) {

    $stock_status = $isPashto
        ? "ذخیره کمه ده"
        : "Low Stock";

    $stock_badge = "warning";

} else {

    $stock_status = $isPashto
        ? "ذخیره موجوده ده"
        : "In Stock";

    $stock_badge = "success";
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
        ? "د محصول معلومات #"
        : "View Product #"
    ?>
    <?= $product_id ?>
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

    .info-label {
        color: #6c757d;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .info-value {
        font-size: 18px;
        font-weight: 600;
    }

    .product-title {
        font-size: 30px;
        font-weight: 700;
    }

    .price-box {
        background-color: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
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
                $_SESSION["full_name"] ?? "User"
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

<!-- =================================================
     HEADER
================================================= -->

<div
    class="d-flex justify-content-between align-items-center mb-4"
>

    <div>

        <h2>

            <i class="bi bi-eye"></i>

            <?= $isPashto
                ? "د محصول معلومات"
                : "Product Details"
            ?>

        </h2>

        <p class="text-muted mb-0">

            <?= $isPashto
                ? "د محصول معلومات وګورئ"
                : "View product information"
            ?>

        </p>

    </div>


    <div>

        <a
            href="index.php"
            class="btn btn-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            <?= $isPashto
                ? "بېرته"
                : "Back"
            ?>

        </a>

    </div>

</div>



<div class="row g-4">


    <!-- =================================================
         LEFT SIDE
    ================================================= -->

    <div class="col-lg-8">


        <!-- PRODUCT INFORMATION -->

        <div class="card shadow-sm mb-4">

            <div class="card-body p-4">


                <div
                    class="d-flex justify-content-between align-items-start"
                >


                    <div>

                        <div class="text-muted mb-2">

                            <?= $isPashto
                                ? "محصول #"
                                : "Product #"
                            ?>

                            <?= $product_id ?>

                        </div>


                        <div class="product-title">

                            <?= htmlspecialchars(
                                $product["product_name"]
                            ) ?>

                        </div>


                        <div class="mt-2">


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


                            <span
                                class="badge bg-<?= $stock_badge ?>"
                            >

                                <?= $stock_status ?>

                            </span>

                        </div>

                    </div>


                    <div class="text-end">

                        <div class="info-label">

                            <?= $isPashto
                                ? "کټګوري"
                                : "Category"
                            ?>

                        </div>

                        <div class="fw-bold">

                            <?= htmlspecialchars(
                                $product["category_name"]
                                ?? (
                                    $isPashto
                                        ? "کټګوري نشته"
                                        : "No Category"
                                )
                            ) ?>

                        </div>

                    </div>

                </div>


                <hr class="my-4">


                <!-- DESCRIPTION -->

                <div class="mb-4">

                    <div class="info-label">

                        <?= $isPashto
                            ? "تشریح"
                            : "Description"
                        ?>

                    </div>


                    <div>

                        <?php if (
                            !empty($product["description"])
                        ): ?>

                            <?= nl2br(
                                htmlspecialchars(
                                    $product["description"]
                                )
                            ) ?>

                        <?php else: ?>

                            <span class="text-muted">

                                <?= $isPashto
                                    ? "تشریح نشته."
                                    : "No description available."
                                ?>

                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- DETAILS -->

                <div class="row g-4">


                    <!-- UNIT -->

                    <div class="col-md-4">

                        <div class="info-label">

                            <?= $isPashto
                                ? "واحد"
                                : "Unit"
                            ?>

                        </div>

                        <div class="info-value">

                            <?= htmlspecialchars(
                                $product["unit"]
                            ) ?>

                        </div>

                    </div>



                    <!-- STOCK -->

                    <div class="col-md-4">

                        <div class="info-label">

                            <?= $isPashto
                                ? "اوسنۍ ذخیره"
                                : "Current Stock"
                            ?>

                        </div>

                        <div class="info-value">

                            <?= number_format(
                                $stock_quantity,
                                3
                            ) ?>

                            <?= htmlspecialchars(
                                $product["unit"]
                            ) ?>

                        </div>

                    </div>



                    <!-- MINIMUM STOCK -->

                    <div class="col-md-4">

                        <div class="info-label">

                            <?= $isPashto
                                ? "لږ تر لږه ذخیره"
                                : "Minimum Stock"
                            ?>

                        </div>

                        <div class="info-value">

                            <?= number_format(
                                $minimum_stock,
                                3
                            ) ?>

                            <?= htmlspecialchars(
                                $product["unit"]
                            ) ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- PRICES -->

        <div class="card shadow-sm">

            <div class="card-header bg-white py-3">

                <h5 class="mb-0">

                    <i class="bi bi-cash-stack"></i>

                    <?= $isPashto
                        ? "د بیې معلومات"
                        : "Pricing Information"
                    ?>

                </h5>

            </div>


            <div class="card-body">

                <div class="row g-4">


                    <!-- COST -->

                    <div class="col-md-4">

                        <div class="price-box">

                            <div class="info-label">

                                <?= $isPashto
                                    ? "د اخیستلو بیه"
                                    : "Cost Price"
                                ?>

                            </div>

                            <div class="fs-3 fw-bold">

                                $

                                <?= number_format(
                                    $cost_price,
                                    2
                                ) ?>

                            </div>

                        </div>

                    </div>



                    <!-- SELLING -->

                    <div class="col-md-4">

                        <div class="price-box">

                            <div class="info-label">

                                <?= $isPashto
                                    ? "د خرڅلاو بیه"
                                    : "Selling Price"
                                ?>

                            </div>

                            <div
                                class="fs-3 fw-bold text-primary"
                            >

                                $

                                <?= number_format(
                                    $selling_price,
                                    2
                                ) ?>

                            </div>

                        </div>

                    </div>



                    <!-- PROFIT -->

                    <div class="col-md-4">

                        <div class="price-box">

                            <div class="info-label">

                                <?= $isPashto
                                    ? "ګټه"
                                    : "Profit"
                                ?>

                            </div>


                            <div
                                class="fs-3 fw-bold <?= $profit >= 0
                                    ? "text-success"
                                    : "text-danger"
                                ?>"
                            >

                                $

                                <?= number_format(
                                    $profit,
                                    2
                                ) ?>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>



    <!-- =================================================
         RIGHT SIDE
    ================================================= -->

    <div class="col-lg-4">


        <!-- QUICK ACTIONS -->

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white py-3">

                <h5 class="mb-0">

                    <i class="bi bi-lightning"></i>

                    <?= $isPashto
                        ? "چټک کارونه"
                        : "Quick Actions"
                    ?>

                </h5>

            </div>


            <div class="card-body">


                <!-- EDIT -->

                <a
                    href="edit.php?id=<?= $product_id ?>"
                    class="btn btn-warning w-100 mb-3"
                >

                    <i class="bi bi-pencil"></i>

                    <?= $isPashto
                        ? "محصول سمول"
                        : "Edit Product"
                    ?>

                </a>


                <!-- DELETE -->

                <a
                    href="delete.php?id=<?= $product_id ?>"
                    class="btn btn-danger w-100 mb-3"
                    onclick="return confirm(
                        '<?= $isPashto
                            ? "ایا ډاډه یاست چې دا محصول حذف کړئ؟"
                            : "Are you sure you want to delete this product?"
                        ?>'
                    );"
                >

                    <i class="bi bi-trash"></i>

                    <?= $isPashto
                        ? "محصول حذف کول"
                        : "Delete Product"
                    ?>

                </a>


                <!-- PRODUCT LIST -->

                <a
                    href="index.php"
                    class="btn btn-secondary w-100"
                >

                    <i class="bi bi-list"></i>

                    <?= $isPashto
                        ? "د محصولاتو لست"
                        : "Product List"
                    ?>

                </a>

            </div>

        </div>



        <!-- PRODUCT SUMMARY -->

        <div class="card shadow-sm">

            <div class="card-header bg-white py-3">

                <h5 class="mb-0">

                    <i class="bi bi-info-circle"></i>

                    <?= $isPashto
                        ? "لنډیز"
                        : "Summary"
                    ?>

                </h5>

            </div>


            <div class="card-body">


                <div
                    class="d-flex justify-content-between mb-3"
                >

                    <span class="text-muted">

                        <?= $isPashto
                            ? "د محصول شمېره"
                            : "Product ID"
                        ?>

                    </span>

                    <strong>

                        #<?= $product_id ?>

                    </strong>

                </div>


                <div
                    class="d-flex justify-content-between mb-3"
                >

                    <span class="text-muted">

                        <?= $isPashto
                            ? "کټګوري"
                            : "Category"
                        ?>

                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            $product["category_name"]
                            ?? (
                                $isPashto
                                    ? "کټګوري نشته"
                                    : "No Category"
                            )
                        ) ?>

                    </strong>

                </div>


                <div
                    class="d-flex justify-content-between mb-3"
                >

                    <span class="text-muted">

                        <?= $isPashto
                            ? "ذخیره"
                            : "Stock"
                        ?>

                    </span>

                    <strong>

                        <?= number_format(
                            $stock_quantity,
                            3
                        ) ?>

                        <?= htmlspecialchars(
                            $product["unit"]
                        ) ?>

                    </strong>

                </div>


                <div
                    class="d-flex justify-content-between mb-3"
                >

                    <span class="text-muted">

                        <?= $isPashto
                            ? "حالت"
                            : "Status"
                        ?>

                    </span>


                    <?php if (
                        $product["status"]
                        === "available"
                    ): ?>

                        <span class="badge bg-success">

                            <?= $isPashto
                                ? "موجود"
                                : "Available"
                            ?>

                        </span>

                    <?php else: ?>

                        <span class="badge bg-secondary">

                            <?= $isPashto
                                ? "نا موجود"
                                : "Unavailable"
                            ?>

                        </span>

                    <?php endif; ?>

                </div>


                <div
                    class="d-flex justify-content-between"
                >

                    <span class="text-muted">

                        <?= $isPashto
                            ? "جوړ شوی"
                            : "Created"
                        ?>

                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            $product["created_at"]
                        ) ?>

                    </strong>

                </div>

            </div>

        </div>

    </div>

</div>


</div>

<!-- Bootstrap JavaScript -->

<script
    src="../assets/js/bootstrap.bundle.min.js"
></script>

</body>

</html>
