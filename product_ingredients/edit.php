
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";


/* =====================================================
   GET RECIPE ID
===================================================== */

$recipe_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


if ($recipe_id <= 0) {
    header("Location: index.php");
    exit;
}


/* =====================================================
   GET PRODUCTS
===================================================== */

$productStmt = $conn->query("
    SELECT
        product_id,
        product_name
    FROM products
    ORDER BY product_name ASC
");

$products = $productStmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   GET INGREDIENTS
===================================================== */

$ingredientStmt = $conn->query("
    SELECT
        ingredient_id,
        ingredient_name,
        unit,
        current_stock
    FROM ingredients
    ORDER BY ingredient_name ASC
");

$ingredients = $ingredientStmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   GET RECIPE
===================================================== */

$stmt = $conn->prepare("
    SELECT
        product_ingredient_id,
        product_id,
        ingredient_id,
        quantity_required
    FROM product_ingredients
    WHERE product_ingredient_id = :recipe_id
");

$stmt->execute([
    ":recipe_id" => $recipe_id
]);

$recipe = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$recipe) {
    die("Recipe ingredient not found.");
}


/* =====================================================
   UPDATE RECIPE
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $product_id =
        (int) ($_POST["product_id"] ?? 0);

    $ingredient_id =
        (int) ($_POST["ingredient_id"] ?? 0);

    $quantity_required =
        (float) ($_POST["quantity_required"] ?? 0);


    if ($product_id <= 0) {

        $error = "Please select a product.";

    } elseif ($ingredient_id <= 0) {

        $error = "Please select an ingredient.";

    } elseif ($quantity_required <= 0) {

        $error = "Quantity required must be greater than 0.";

    } else {

        try {

            /* =========================================
               CHECK DUPLICATE
            ========================================= */

            $duplicateStmt = $conn->prepare("
                SELECT product_ingredient_id
                FROM product_ingredients
                WHERE product_id = :product_id
                AND ingredient_id = :ingredient_id
                AND product_ingredient_id != :recipe_id
            ");

            $duplicateStmt->execute([

                ":product_id" =>
                    $product_id,

                ":ingredient_id" =>
                    $ingredient_id,

                ":recipe_id" =>
                    $recipe_id
            ]);


            if ($duplicateStmt->fetch()) {

                throw new Exception(
                    "This ingredient is already assigned to this product."
                );
            }


            /* =========================================
               UPDATE
            ========================================= */

            $updateStmt = $conn->prepare("
                UPDATE product_ingredients
                SET
                    product_id = :product_id,
                    ingredient_id = :ingredient_id,
                    quantity_required = :quantity_required
                WHERE product_ingredient_id = :recipe_id
            ");


            $updateStmt->execute([

                ":product_id" =>
                    $product_id,

                ":ingredient_id" =>
                    $ingredient_id,

                ":quantity_required" =>
                    $quantity_required,

                ":recipe_id" =>
                    $recipe_id
            ]);


            header(
                "Location: index.php?updated=1"
            );

            exit;


        } catch (Exception $e) {

            $error = $e->getMessage();
        }
    }


    /* ================================================
       KEEP FORM VALUES AFTER ERROR
    ================================================ */

    $recipe["product_id"] =
        $product_id;

    $recipe["ingredient_id"] =
        $ingredient_id;

    $recipe["quantity_required"] =
        $quantity_required;
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Edit Recipe Ingredient - Bakery Management
    </title>


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

        .form-label {
            font-weight: 600;
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
            class="navbar-brand fw-bold"
        >

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


            <a
                href="../public/logout.php"
                class="btn btn-outline-danger btn-sm"
            >

                <i class="bi bi-box-arrow-right"></i>

                Logout

            </a>

        </div>

    </div>

</nav>



<!-- =====================================================
     MAIN
===================================================== -->

<div class="container p-4">


    <!-- HEADER -->

    <div
        class="d-flex justify-content-between align-items-center mb-4"
    >

        <div>

            <h2>

                <i class="bi bi-pencil-square"></i>

                Edit Recipe Ingredient

            </h2>

            <p class="text-muted mb-0">

                Update product recipe information

            </p>

        </div>


        <a
            href="index.php"
            class="btn btn-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            Back to Recipes

        </a>

    </div>



    <!-- =====================================================
         ERROR
    ===================================================== -->

    <?php if (isset($error)): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         EDIT FORM
    ===================================================== -->

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="card shadow-sm">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-journal-text"></i>

                        Recipe Information

                    </h5>

                </div>


                <div class="card-body">

                    <form method="POST">


                        <!-- PRODUCT -->

                        <div class="mb-4">

                            <label class="form-label">

                                Product

                            </label>


                            <select
                                name="product_id"
                                class="form-select"
                                required
                            >

                                <option value="">

                                    Select Product

                                </option>


                                <?php foreach (
                                    $products as $product
                                ): ?>

                                    <option
                                        value="<?= (int) $product["product_id"] ?>"
                                        <?= (
                                            (int) $recipe["product_id"]
                                            ===
                                            (int) $product["product_id"]
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



                        <!-- INGREDIENT -->

                        <div class="mb-4">

                            <label class="form-label">

                                Ingredient

                            </label>


                            <select
                                name="ingredient_id"
                                class="form-select"
                                required
                            >

                                <option value="">

                                    Select Ingredient

                                </option>


                                <?php foreach (
                                    $ingredients as $ingredient
                                ): ?>

                                    <option
                                        value="<?= (int) $ingredient["ingredient_id"] ?>"
                                        <?= (
                                            (int) $recipe["ingredient_id"]
                                            ===
                                            (int) $ingredient["ingredient_id"]
                                        )
                                            ? "selected"
                                            : ""
                                        ?>
                                    >

                                        <?= htmlspecialchars(
                                            $ingredient["ingredient_name"]
                                        ) ?>

                                        -

                                        <?= htmlspecialchars(
                                            $ingredient["unit"]
                                        ) ?>

                                        | Stock:

                                        <?= number_format(
                                            (float) $ingredient["current_stock"],
                                            3
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>



                        <!-- QUANTITY -->

                        <div class="mb-4">

                            <label class="form-label">

                                Quantity Required

                            </label>


                            <input
                                type="number"
                                name="quantity_required"
                                class="form-control"
                                min="0.001"
                                step="0.001"
                                value="<?= htmlspecialchars(
                                    $recipe["quantity_required"]
                                ) ?>"
                                required
                            >


                            <small class="text-muted">

                                Example: 0.500 kg

                            </small>

                        </div>



                        <!-- BUTTONS -->

                        <div class="d-flex gap-2">

                            <a
                                href="index.php"
                                class="btn btn-secondary flex-fill"
                            >

                                <i class="bi bi-x-circle"></i>

                                Cancel

                            </a>


                            <button
                                type="submit"
                                class="btn btn-success flex-fill"
                            >

                                <i class="bi bi-check-circle"></i>

                                Update Recipe

                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>


  <!-- javascript links  -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>



</body>

</html>