<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {

    header(
        "Location: index.php?error="
        . urlencode(
            $isPashto
                ? "د خام موادو ID ناسم دی."
                : "Invalid ingredient ID."
        )
    );

    exit;
}


/* ===============================
   GET INGREDIENT
================================ */

$stmt = $conn->prepare("
    SELECT *
    FROM ingredients
    WHERE ingredient_id = :id
");

$stmt->execute([
    ":id" => $id
]);

$ingredient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ingredient) {

    header(
        "Location: index.php?error="
        . urlencode(
            $isPashto
                ? "خام مواد ونه موندل شول."
                : "Ingredient not found."
        )
    );

    exit;
}


/* ===============================
   GET SUPPLIERS
================================ */

$supplierStmt = $conn->query("
    SELECT
        supplier_id,
        name
    FROM suppliers
    ORDER BY name ASC
");

$suppliers = $supplierStmt->fetchAll(PDO::FETCH_ASSOC);

$error = "";


/* ===============================
   UPDATE
================================ */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $ingredient_name =
        trim($_POST["ingredient_name"] ?? "");

    $unit =
        trim($_POST["unit"] ?? "");

    $current_stock =
        (float) ($_POST["current_stock"] ?? 0);

    $minimum_stock =
        (float) ($_POST["minimum_stock"] ?? 0);

    $purchase_price =
        (float) ($_POST["purchase_price"] ?? 0);

    $supplier_id =
        !empty($_POST["supplier_id"])
            ? (int) $_POST["supplier_id"]
            : null;

    $expiry_date =
        !empty($_POST["expiry_date"])
            ? $_POST["expiry_date"]
            : null;


    if ($ingredient_name === "") {

        $error = $isPashto
            ? "د خام موادو نوم اړین دی."
            : "Ingredient name is required.";

    } elseif ($unit === "") {

        $error = $isPashto
            ? "واحد اړین دی."
            : "Unit is required.";

    } elseif ($current_stock < 0) {

        $error = $isPashto
            ? "اوسنی ذخیره منفي کېدای نشي."
            : "Current stock cannot be negative.";

    } elseif ($minimum_stock < 0) {

        $error = $isPashto
            ? "لږ تر لږه ذخیره منفي کېدای نشي."
            : "Minimum stock cannot be negative.";

    } elseif ($purchase_price < 0) {

        $error = $isPashto
            ? "د پېرود بیه منفي کېدای نشي."
            : "Purchase price cannot be negative.";

    } else {

        try {

            $update = $conn->prepare("
                UPDATE ingredients
                SET
                    ingredient_name = :ingredient_name,
                    unit = :unit,
                    current_stock = :current_stock,
                    minimum_stock = :minimum_stock,
                    purchase_price = :purchase_price,
                    supplier_id = :supplier_id,
                    expiry_date = :expiry_date
                WHERE ingredient_id = :id
            ");

            $update->execute([

                ":ingredient_name" =>
                    $ingredient_name,

                ":unit" =>
                    $unit,

                ":current_stock" =>
                    $current_stock,

                ":minimum_stock" =>
                    $minimum_stock,

                ":purchase_price" =>
                    $purchase_price,

                ":supplier_id" =>
                    $supplier_id,

                ":expiry_date" =>
                    $expiry_date,

                ":id" =>
                    $id
            ]);


            $message = $isPashto
                ? "خام مواد په بریالیتوب سره تازه شول."
                : "Ingredient updated successfully.";

            $lang = currentLanguage();

            header(
                "Location: index.php?success="
                . urlencode($message)
                . "&lang="
                . urlencode($lang)
            );

            exit;

        } catch (PDOException $e) {

            $error = $isPashto
                ? "خام مواد تازه نه شول. "
                . $e->getMessage()
                : "Unable to update ingredient. "
                . $e->getMessage();
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
        content="width=device-width, initial-scale=1.0">

    <title>
        <?= $isPashto
            ? "خام مواد سمول - د بیکري مدیریت"
            : "Edit Ingredient - Bakery Management"
        ?>
    </title>


    <!-- Bootstrap -->
    <link
        rel="stylesheet"
        href="../assets/css/bootstrap.min.css">


    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="../assets/css/bootstrap-icons.css">


    <style>

        body {
            background: #f5f6fa;
        }

        .navbar {
            background: white;
            border-bottom: 1px solid #ddd;
        }

        .card {
            border: none;
            border-radius: 14px;
        }

        .page-title {
            font-weight: 700;
        }

        [dir="rtl"] .me-3 {
            margin-right: 0 !important;
            margin-left: 1rem !important;
        }

        [dir="rtl"] .ms-3 {
            margin-left: 0 !important;
            margin-right: 1rem !important;
        }

    </style>

</head>

<body>


<!-- ===============================
     NAVBAR
================================ -->

<nav class="navbar">

    <div class="container-fluid px-4">

        <a
            href="../admin/dashboard.php"
            class="navbar-brand fw-bold">

            <i class="bi bi-shop"></i>

            <?= $isPashto
                ? "د بیکري سیستم"
                : "Bakery System"
            ?>

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


            <!-- Language -->

            <a
                href="?id=<?= $id ?>&lang=ps"
                class="btn btn-sm
                <?= $isPashto
                    ? "btn-primary"
                    : "btn-outline-primary"
                ?> me-1">

                پښتو

            </a>


            <a
                href="?id=<?= $id ?>&lang=en"
                class="btn btn-sm
                <?= !$isPashto
                    ? "btn-primary"
                    : "btn-outline-primary"
                ?> me-3">

                English

            </a>


            <a
                href="../public/logout.php"
                class="btn btn-outline-danger btn-sm">

                <i class="bi bi-box-arrow-right"></i>

                <?= $isPashto
                    ? "وتل"
                    : "Logout"
                ?>

            </a>

        </div>

    </div>

</nav>



<!-- ===============================
     MAIN
================================ -->

<div class="container py-4">


    <!-- Page Header -->

    <div
        class="d-flex justify-content-between
        align-items-center mb-4">

        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-pencil-square"></i>

                <?= $isPashto
                    ? "خام مواد سمول"
                    : "Edit Ingredient"
                ?>

            </h2>


            <p class="text-muted mb-0">

                <?= $isPashto
                    ? "د خام موادو معلومات تازه کړئ"
                    : "Update ingredient information"
                ?>

            </p>

        </div>


        <a
            href="index.php"
            class="btn btn-secondary">

            <i class="bi bi-arrow-left"></i>

            <?= $isPashto
                ? "بېرته"
                : "Back"
            ?>

        </a>

    </div>



    <!-- Error -->

    <?php if ($error !== ""): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle-fill"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>



    <!-- Card -->

    <div class="card shadow-sm">


        <div class="card-header bg-white py-3">

            <h5 class="mb-0">

                <i class="bi bi-box-seam"></i>

                <?= $isPashto
                    ? "د خام موادو معلومات"
                    : "Ingredient Information"
                ?>

            </h5>

        </div>



        <div class="card-body">

            <form method="POST">

                <div class="row g-3">


                    <!-- Ingredient Name -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= $isPashto
                                ? "د خام موادو نوم"
                                : "Ingredient Name"
                            ?>

                            <span class="text-danger">*</span>

                        </label>


                        <input
                            type="text"
                            name="ingredient_name"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $_POST["ingredient_name"]
                                ?? $ingredient["ingredient_name"]
                            ) ?>"
                            required>

                    </div>



                    <!-- Unit -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= $isPashto
                                ? "واحد"
                                : "Unit"
                            ?>

                            <span class="text-danger">*</span>

                        </label>


                        <input
                            type="text"
                            name="unit"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $_POST["unit"]
                                ?? $ingredient["unit"]
                            ) ?>"
                            required>

                    </div>



                    <!-- Current Stock -->

                    <div class="col-md-4">

                        <label class="form-label">

                            <?= $isPashto
                                ? "اوسنی ذخیره"
                                : "Current Stock"
                            ?>

                        </label>


                        <input
                            type="number"
                            name="current_stock"
                            class="form-control"
                            min="0"
                            step="0.001"
                            value="<?= htmlspecialchars(
                                $_POST["current_stock"]
                                ?? $ingredient["current_stock"]
                            ) ?>">

                    </div>



                    <!-- Minimum Stock -->

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
                            step="0.001"
                            value="<?= htmlspecialchars(
                                $_POST["minimum_stock"]
                                ?? $ingredient["minimum_stock"]
                            ) ?>">

                    </div>



                    <!-- Purchase Price -->

                    <div class="col-md-4">

                        <label class="form-label">

                            <?= $isPashto
                                ? "د پېرود بیه"
                                : "Purchase Price"
                            ?>

                        </label>


                        <input
                            type="number"
                            name="purchase_price"
                            class="form-control"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars(
                                $_POST["purchase_price"]
                                ?? $ingredient["purchase_price"]
                            ) ?>">

                    </div>



                    <!-- Supplier -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= $isPashto
                                ? "عرضه کوونکی"
                                : "Supplier"
                            ?>

                        </label>


                        <select
                            name="supplier_id"
                            class="form-select">

                            <option value="">

                                <?= $isPashto
                                    ? "هیڅ عرضه کوونکی نشته"
                                    : "No Supplier"
                                ?>

                            </option>


                            <?php

                            $selectedSupplier =
                                $_POST["supplier_id"]
                                ?? $ingredient["supplier_id"];

                            ?>


                            <?php foreach ($suppliers as $supplier): ?>

                                <option
                                    value="<?= (int) $supplier["supplier_id"] ?>"
                                    <?= (
                                        $selectedSupplier
                                        == $supplier["supplier_id"]
                                    )
                                        ? "selected"
                                        : ""
                                    ?>>

                                    <?= htmlspecialchars(
                                        $supplier["name"]
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>



                    <!-- Expiry Date -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= $isPashto
                                ? "د ختمېدو نېټه"
                                : "Expiry Date"
                            ?>

                        </label>


                        <input
                            type="date"
                            name="expiry_date"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $_POST["expiry_date"]
                                ?? $ingredient["expiry_date"]
                                ?? ""
                            ) ?>">

                    </div>


                </div>



                <hr class="my-4">



                <!-- Buttons -->

                <div
                    class="d-flex justify-content-end gap-2">

                    <a
                        href="index.php"
                        class="btn btn-secondary">

                        <?= $isPashto
                            ? "لغوه"
                            : "Cancel"
                        ?>

                    </a>


                    <button
                        type="submit"
                        class="btn btn-warning">

                        <i class="bi bi-save"></i>

                        <?= $isPashto
                            ? "خام مواد تازه کول"
                            : "Update Ingredient"
                        ?>

                    </button>

                </div>


            </form>

        </div>

    </div>

</div>



<!-- JavaScript -->
<script
    src="../assets/js/bootstrap.bundle.min.js">
</script>


</body>

</html>