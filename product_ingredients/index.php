
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";


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
   ADD RECIPE INGREDIENT
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $product_id = (int) ($_POST["product_id"] ?? 0);

    $ingredient_id = (int) ($_POST["ingredient_id"] ?? 0);

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

            /* CHECK PRODUCT */

            $checkProduct = $conn->prepare("
                SELECT product_id
                FROM products
                WHERE product_id = :product_id
            ");

            $checkProduct->execute([
                ":product_id" => $product_id
            ]);


            if (!$checkProduct->fetch()) {

                throw new Exception(
                    "Selected product was not found."
                );
            }


            /* CHECK INGREDIENT */

            $checkIngredient = $conn->prepare("
                SELECT ingredient_id
                FROM ingredients
                WHERE ingredient_id = :ingredient_id
            ");

            $checkIngredient->execute([
                ":ingredient_id" => $ingredient_id
            ]);


            if (!$checkIngredient->fetch()) {

                throw new Exception(
                    "Selected ingredient was not found."
                );
            }


            /* CHECK DUPLICATE */

            $checkDuplicate = $conn->prepare("
                SELECT product_ingredient_id
                FROM product_ingredients
                WHERE product_id = :product_id
                AND ingredient_id = :ingredient_id
            ");

            $checkDuplicate->execute([
                ":product_id" => $product_id,
                ":ingredient_id" => $ingredient_id
            ]);


            if ($checkDuplicate->fetch()) {

                throw new Exception(
                    "This ingredient is already added to this product."
                );
            }


            /* INSERT */

            $stmt = $conn->prepare("
                INSERT INTO product_ingredients
                (
                    product_id,
                    ingredient_id,
                    quantity_required
                )
                VALUES
                (
                    :product_id,
                    :ingredient_id,
                    :quantity_required
                )
            ");

            $stmt->execute([

                ":product_id" =>
                    $product_id,

                ":ingredient_id" =>
                    $ingredient_id,

                ":quantity_required" =>
                    $quantity_required
            ]);


            header(
                "Location: index.php?success=1"
            );

            exit;


        } catch (Exception $e) {

            $error = $e->getMessage();
        }
    }
}


/* =====================================================
   GET RECIPE LIST
===================================================== */

$recipeStmt = $conn->query("
    SELECT
        pi.product_ingredient_id,
        pi.product_id,
        pi.ingredient_id,
        pi.quantity_required,

        p.product_name,

        i.ingredient_name,
        i.unit,
        i.current_stock

    FROM product_ingredients pi

    INNER JOIN products p
        ON pi.product_id = p.product_id

    INNER JOIN ingredients i
        ON pi.ingredient_id = i.ingredient_id

    ORDER BY
        p.product_name ASC,
        i.ingredient_name ASC
");

$recipes = $recipeStmt->fetchAll(PDO::FETCH_ASSOC);

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
        Product Recipes - Bakery Management
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

        .table th {
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .recipe-card {
            min-height: 500px;
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

<div class="container-fluid p-4">


    <!-- HEADER -->

    <div
        class="d-flex justify-content-between align-items-center mb-4"
    >

        <div>

            <h2>

                <i class="bi bi-journal-text"></i>

                Product Recipes

            </h2>

            <p class="text-muted mb-0">

                Manage ingredients required for each product

            </p>

        </div>


        <a
            href="../admin/dashboard.php"
            class="btn btn-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            Dashboard

        </a>

    </div>



    <!-- =====================================================
         ADD SUCCESS MESSAGE
    ===================================================== -->

    <?php if (isset($_GET["success"])): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-check-circle"></i>

            Recipe ingredient added successfully.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         UPDATE SUCCESS MESSAGE
    ===================================================== -->

    <?php if (isset($_GET["updated"])): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-check-circle"></i>

            Recipe ingredient updated successfully.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         DELETE SUCCESS MESSAGE
    ===================================================== -->

    <?php if (isset($_GET["deleted"])): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-check-circle"></i>

            Recipe ingredient deleted successfully.

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

    <?php if (isset($error)): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-exclamation-triangle"></i>

            <?= htmlspecialchars($error) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>



    <div class="row g-4">


        <!-- =================================================
             LEFT - ADD RECIPE INGREDIENT
        ================================================= -->

        <div class="col-lg-4">

            <div class="card shadow-sm">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-plus-circle"></i>

                        Add Recipe Ingredient

                    </h5>

                </div>


                <div class="card-body">

                    <form method="POST">


                        <!-- PRODUCT -->

                        <div class="mb-3">

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
                                    >

                                        <?= htmlspecialchars(
                                            $product["product_name"]
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>



                        <!-- INGREDIENT -->

                        <div class="mb-3">

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

                        <div class="mb-3">

                            <label class="form-label">

                                Quantity Required

                            </label>


                            <input
                                type="number"
                                name="quantity_required"
                                class="form-control"
                                min="0.001"
                                step="0.001"
                                value="0.001"
                                required
                            >


                            <small class="text-muted">

                                Decimal quantity allowed.
                                Example: 0.500

                            </small>

                        </div>



                        <!-- SUBMIT -->

                        <button
                            type="submit"
                            class="btn btn-success w-100"
                        >

                            <i class="bi bi-plus-circle"></i>

                            Add Ingredient

                        </button>

                    </form>

                </div>

            </div>

        </div>



        <!-- =================================================
             RIGHT - RECIPE LIST
        ================================================= -->

        <div class="col-lg-8">

            <div class="card shadow-sm recipe-card">

                <div class="card-header bg-white py-3">

                    <div
                        class="d-flex justify-content-between align-items-center"
                    >

                        <h5 class="mb-0">

                            <i class="bi bi-list-ul"></i>

                            Recipe List

                        </h5>


                        <span class="badge bg-primary">

                            <?= count($recipes) ?>

                            Ingredients

                        </span>

                    </div>

                </div>


                <div class="card-body">

                    <div class="table-responsive">

                        <table
                            class="table table-hover align-middle"
                        >

                            <thead class="table-light">

                                <tr>

                                    <th>#</th>

                                    <th>Product</th>

                                    <th>Ingredient</th>

                                    <th>Quantity</th>

                                    <th>Unit</th>

                                    <th>Stock</th>

                                    <th>Actions</th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php if (
                                    empty($recipes)
                                ): ?>

                                    <tr>

                                        <td
                                            colspan="7"
                                            class="text-center text-muted py-5"
                                        >

                                            <i
                                                class="bi bi-journal-x fs-1 d-block mb-3"
                                            ></i>

                                            No recipe ingredients found.

                                        </td>

                                    </tr>

                                <?php else: ?>


                                    <?php foreach (
                                        $recipes as $recipe
                                    ): ?>

                                        <tr>


                                            <!-- ID -->

                                            <td>

                                                #

                                                <?= (int)
                                                    $recipe[
                                                        "product_ingredient_id"
                                                    ] ?>

                                            </td>



                                            <!-- PRODUCT -->

                                            <td>

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $recipe[
                                                            "product_name"
                                                        ]
                                                    ) ?>

                                                </strong>

                                            </td>



                                            <!-- INGREDIENT -->

                                            <td>

                                                <?= htmlspecialchars(
                                                    $recipe[
                                                        "ingredient_name"
                                                    ]
                                                ) ?>

                                            </td>



                                            <!-- QUANTITY -->

                                            <td>

                                                <strong>

                                                    <?= number_format(
                                                        (float)
                                                        $recipe[
                                                            "quantity_required"
                                                        ],
                                                        3
                                                    ) ?>

                                                </strong>

                                            </td>



                                            <!-- UNIT -->

                                            <td>

                                                <?= htmlspecialchars(
                                                    $recipe["unit"]
                                                ) ?>

                                            </td>



                                            <!-- STOCK -->

                                            <td>

                                                <?= number_format(
                                                    (float)
                                                    $recipe[
                                                        "current_stock"
                                                    ],
                                                    3
                                                ) ?>

                                                <?= htmlspecialchars(
                                                    $recipe["unit"]
                                                ) ?>

                                            </td>



                                            <!-- ACTIONS -->

                                            <td>

                                                <div
                                                    class="btn-group"
                                                >


                                                    <!-- EDIT -->

                                                    <a
                                                        href="edit.php?id=<?= (int) $recipe["product_ingredient_id"] ?>"
                                                        class="btn btn-sm btn-warning"
                                                        title="Edit"
                                                    >

                                                        <i
                                                            class="bi bi-pencil"
                                                        ></i>

                                                    </a>



                                                    <!-- DELETE -->

                                                    <a
                                                        href="delete.php?id=<?= (int) $recipe["product_ingredient_id"] ?>"
                                                        class="btn btn-sm btn-danger"
                                                        title="Delete"
                                                        onclick="return confirm('Are you sure you want to delete this recipe ingredient?');"
                                                    >

                                                        <i
                                                            class="bi bi-trash"
                                                        ></i>

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


  <!-- javascript links  -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>



</body>

</html>
