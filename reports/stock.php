
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";


/* =====================================================
   GET PRODUCTS STOCK
===================================================== */

$productStmt = $conn->query("
    SELECT
        p.product_id,
        p.product_name,
        p.selling_price,
        p.cost_price,
        p.stock_quantity,
        p.unit,
        p.minimum_stock,
        p.status,
        c.category_name
    FROM products p

    LEFT JOIN categories c
        ON p.category_id = c.category_id

    ORDER BY p.product_name ASC
");

$products = $productStmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   GET INGREDIENTS STOCK
===================================================== */

$ingredientStmt = $conn->query("
    SELECT
        i.ingredient_id,
        i.ingredient_name,
        i.unit,
        i.current_stock,
        i.minimum_stock,
        i.purchase_price,
        i.expiry_date,
        s.name AS supplier_name
    FROM ingredients i

    LEFT JOIN suppliers s
        ON i.supplier_id = s.supplier_id

    ORDER BY i.ingredient_name ASC
");

$ingredients = $ingredientStmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   PRODUCT SUMMARY
===================================================== */

$totalProducts = count($products);

$totalProductStock = 0;
$productStockValue = 0;
$lowProducts = 0;
$outProducts = 0;

foreach ($products as $product) {

    $stock = (float) $product["stock_quantity"];
    $cost = (float) $product["cost_price"];
    $minimum = (float) $product["minimum_stock"];

    $totalProductStock += $stock;

    $productStockValue += ($stock * $cost);

    if ($stock <= 0) {

        $outProducts++;

    } elseif ($stock <= $minimum) {

        $lowProducts++;
    }
}


/* =====================================================
   INGREDIENT SUMMARY
===================================================== */

$totalIngredients = count($ingredients);

$totalIngredientStock = 0;
$ingredientStockValue = 0;
$lowIngredients = 0;
$outIngredients = 0;

foreach ($ingredients as $ingredient) {

    $stock = (float) $ingredient["current_stock"];
    $price = (float) $ingredient["purchase_price"];
    $minimum = (float) $ingredient["minimum_stock"];

    $totalIngredientStock += $stock;

    $ingredientStockValue += ($stock * $price);

    if ($stock <= 0) {

        $outIngredients++;

    } elseif ($stock <= $minimum) {

        $lowIngredients++;
    }
}


/* =====================================================
   TOTAL STOCK VALUE
===================================================== */

$totalStockValue =
    $productStockValue + $ingredientStockValue;

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Stock Report - Bakery Management</title>



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

        .table td {
            vertical-align: middle;
        }

        .summary-card {
            border-radius: 14px;
        }

        .section-title {
            font-weight: 700;
        }

        .print-header {
            display: none;
        }

        @media print {

            body {
                background: white !important;
            }

            .navbar,
            .no-print {
                display: none !important;
            }

            .container-fluid {
                padding: 0 !important;
            }

            .card {
                box-shadow: none !important;
            }

            .print-header {
                display: block;
                text-align: center;
                margin-bottom: 20px;
            }

            .table {
                font-size: 11px;
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

    <div
        class="d-flex justify-content-between align-items-center mb-4 no-print">

        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-box-seam"></i>

                Stock Report

            </h2>

            <p class="text-muted mb-0">

                Products and ingredients stock overview

            </p>

        </div>


        <div>

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



    <!-- PRINT HEADER -->

    <div class="print-header">

        <h2>Bakery Management System</h2>

        <h4>Stock Report</h4>

        <p>
            Products & Ingredients Stock
        </p>

    </div>



    <!-- =================================================
         SUMMARY CARDS
    ================================================== -->

    <div class="row g-4 mb-4">


        <!-- PRODUCTS -->

        <div class="col-md-4">

            <div class="card shadow-sm summary-card">

                <div class="card-body">

                    <div
                        class="d-flex justify-content-between align-items-center">

                        <div>

                            <small class="text-muted">

                                Total Products

                            </small>

                            <h3 class="mb-0 text-primary">

                                <?= $totalProducts ?>

                            </h3>

                        </div>


                        <div class="fs-1 text-primary">

                            <i class="bi bi-box-seam"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- INGREDIENTS -->

        <div class="col-md-4">

            <div class="card shadow-sm summary-card">

                <div class="card-body">

                    <div
                        class="d-flex justify-content-between align-items-center">

                        <div>

                            <small class="text-muted">

                                Total Ingredients

                            </small>

                            <h3 class="mb-0 text-success">

                                <?= $totalIngredients ?>

                            </h3>

                        </div>


                        <div class="fs-1 text-success">

                            <i class="bi bi-basket"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- VALUE -->

        <div class="col-md-4">

            <div class="card shadow-sm summary-card">

                <div class="card-body">

                    <div
                        class="d-flex justify-content-between align-items-center">

                        <div>

                            <small class="text-muted">

                                Estimated Stock Value

                            </small>

                            <h3 class="mb-0 text-dark">

                                $<?= number_format(
                                    $totalStockValue,
                                    2
                                ) ?>

                            </h3>

                        </div>


                        <div class="fs-1 text-dark">

                            <i class="bi bi-currency-dollar"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>



    <!-- =================================================
         STOCK ALERTS
    ================================================== -->

    <div class="row g-4 mb-4">


        <!-- LOW PRODUCT -->

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <i class="bi bi-exclamation-triangle fs-2 text-warning"></i>

                    <h5 class="mt-2">

                        Low Products

                    </h5>

                    <h3 class="text-warning">

                        <?= $lowProducts ?>

                    </h3>

                </div>

            </div>

        </div>



        <!-- OUT PRODUCT -->

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <i class="bi bi-x-circle fs-2 text-danger"></i>

                    <h5 class="mt-2">

                        Out Products

                    </h5>

                    <h3 class="text-danger">

                        <?= $outProducts ?>

                    </h3>

                </div>

            </div>

        </div>



        <!-- LOW INGREDIENT -->

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <i class="bi bi-exclamation-circle fs-2 text-warning"></i>

                    <h5 class="mt-2">

                        Low Ingredients

                    </h5>

                    <h3 class="text-warning">

                        <?= $lowIngredients ?>

                    </h3>

                </div>

            </div>

        </div>



        <!-- OUT INGREDIENT -->

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <i class="bi bi-x-octagon fs-2 text-danger"></i>

                    <h5 class="mt-2">

                        Out Ingredients

                    </h5>

                    <h3 class="text-danger">

                        <?= $outIngredients ?>

                    </h3>

                </div>

            </div>

        </div>

    </div>



    <!-- =================================================
         PRODUCTS STOCK
    ================================================== -->

    <div class="card shadow-sm mb-4">


        <div class="card-header bg-white py-3">

            <h5 class="mb-0 section-title">

                <i class="bi bi-box-seam"></i>

                Products Stock

            </h5>

        </div>


        <div class="card-body">


            <?php if (empty($products)): ?>

                <div class="text-center py-5">

                    <i class="bi bi-box-seam fs-1 text-muted"></i>

                    <h5 class="mt-3">

                        No Products Found

                    </h5>

                </div>

            <?php else: ?>


                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>#</th>

                                <th>Product</th>

                                <th>Category</th>

                                <th>Stock</th>

                                <th>Minimum</th>

                                <th>Unit</th>

                                <th>Cost Price</th>

                                <th>Stock Value</th>

                                <th>Status</th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($products as $product): ?>


                            <?php

                            $stock =
                                (float)
                                $product["stock_quantity"];

                            $minimum =
                                (float)
                                $product["minimum_stock"];

                            $stockValue =
                                $stock *
                                (float)
                                $product["cost_price"];


                            if ($stock <= 0) {

                                $stockClass =
                                    "danger";

                                $stockText =
                                    "Out of Stock";

                            } elseif (
                                $stock <= $minimum
                            ) {

                                $stockClass =
                                    "warning";

                                $stockText =
                                    "Low Stock";

                            } else {

                                $stockClass =
                                    "success";

                                $stockText =
                                    "In Stock";
                            }

                            ?>


                            <tr>


                                <td>

                                    #<?= (int)
                                        $product["product_id"] ?>

                                </td>


                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $product["product_name"]
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= !empty(
                                        $product["category_name"]
                                    )
                                        ? htmlspecialchars(
                                            $product["category_name"]
                                        )
                                        : '<span class="text-muted">N/A</span>'
                                    ?>

                                </td>


                                <td>

                                    <strong
                                        class="text-<?= $stockClass ?>">

                                        <?= number_format(
                                            $stock,
                                            2
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= number_format(
                                        $minimum,
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $product["unit"]
                                    ) ?>

                                </td>


                                <td>

                                    $<?= number_format(
                                        (float)
                                        $product["cost_price"],
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    $<?= number_format(
                                        $stockValue,
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <span
                                        class="badge bg-<?= $stockClass ?>">

                                        <?= $stockText ?>

                                    </span>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php endif; ?>


        </div>

    </div>



    <!-- =================================================
         INGREDIENTS STOCK
    ================================================== -->

    <div class="card shadow-sm mb-4">


        <div class="card-header bg-white py-3">

            <h5 class="mb-0 section-title">

                <i class="bi bi-basket"></i>

                Ingredients / Raw Materials Stock

            </h5>

        </div>


        <div class="card-body">


            <?php if (empty($ingredients)): ?>

                <div class="text-center py-5">

                    <i class="bi bi-basket fs-1 text-muted"></i>

                    <h5 class="mt-3">

                        No Ingredients Found

                    </h5>

                </div>

            <?php else: ?>


                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>#</th>

                                <th>Ingredient</th>

                                <th>Supplier</th>

                                <th>Current Stock</th>

                                <th>Minimum</th>

                                <th>Unit</th>

                                <th>Purchase Price</th>

                                <th>Stock Value</th>

                                <th>Expiry Date</th>

                                <th>Status</th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($ingredients as $ingredient): ?>


                            <?php

                            $stock =
                                (float)
                                $ingredient["current_stock"];

                            $minimum =
                                (float)
                                $ingredient["minimum_stock"];

                            $stockValue =
                                $stock *
                                (float)
                                $ingredient["purchase_price"];


                            if ($stock <= 0) {

                                $stockClass =
                                    "danger";

                                $stockText =
                                    "Out of Stock";

                            } elseif (
                                $stock <= $minimum
                            ) {

                                $stockClass =
                                    "warning";

                                $stockText =
                                    "Low Stock";

                            } else {

                                $stockClass =
                                    "success";

                                $stockText =
                                    "In Stock";
                            }


                            /* EXPIRY */

                            $expiryClass =
                                "";

                            $expiryText =
                                $ingredient["expiry_date"]
                                ?: "N/A";


                            if (
                                !empty(
                                    $ingredient["expiry_date"]
                                )
                            ) {

                                $today =
                                    new DateTime();

                                $expiry =
                                    new DateTime(
                                        $ingredient["expiry_date"]
                                    );


                                if ($expiry < $today) {

                                    $expiryClass =
                                        "text-danger fw-bold";

                                    $expiryText =
                                        $ingredient["expiry_date"]
                                        . " (Expired)";

                                } elseif (
                                    $today->diff(
                                        $expiry
                                    )->days <= 30
                                ) {

                                    $expiryClass =
                                        "text-warning fw-bold";

                                    $expiryText =
                                        $ingredient["expiry_date"]
                                        . " (Soon)";
                                }
                            }

                            ?>


                            <tr>


                                <td>

                                    #<?= (int)
                                        $ingredient["ingredient_id"] ?>

                                </td>


                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $ingredient["ingredient_name"]
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= !empty(
                                        $ingredient["supplier_name"]
                                    )
                                        ? htmlspecialchars(
                                            $ingredient["supplier_name"]
                                        )
                                        : '<span class="text-muted">N/A</span>'
                                    ?>

                                </td>


                                <td>

                                    <strong
                                        class="text-<?= $stockClass ?>">

                                        <?= number_format(
                                            $stock,
                                            3
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= number_format(
                                        $minimum,
                                        3
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $ingredient["unit"]
                                    ) ?>

                                </td>


                                <td>

                                    $<?= number_format(
                                        (float)
                                        $ingredient["purchase_price"],
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    $<?= number_format(
                                        $stockValue,
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <span
                                        class="<?= $expiryClass ?>">

                                        <?= htmlspecialchars(
                                            $expiryText
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <span
                                        class="badge bg-<?= $stockClass ?>">

                                        <?= $stockText ?>

                                    </span>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php endif; ?>


        </div>

    </div>



    <!-- =================================================
         FOOTER SUMMARY
    ================================================== -->

    <div class="card shadow-sm">

        <div class="card-body">

            <div
                class="row text-center">


                <div class="col-md-4">

                    <small class="text-muted">

                        Product Stock Value

                    </small>

                    <h5>

                        $<?= number_format(
                            $productStockValue,
                            2
                        ) ?>

                    </h5>

                </div>


                <div class="col-md-4">

                    <small class="text-muted">

                        Ingredient Stock Value

                    </small>

                    <h5>

                        $<?= number_format(
                            $ingredientStockValue,
                            2
                        ) ?>

                    </h5>

                </div>


                <div class="col-md-4">

                    <small class="text-muted">

                        Total Estimated Stock Value

                    </small>

                    <h4 class="text-primary">

                        $<?= number_format(
                            $totalStockValue,
                            2
                        ) ?>

                    </h4>

                </div>


            </div>

        </div>

    </div>


</div>

    <!-- javascript links  -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>


</body>

</html>
