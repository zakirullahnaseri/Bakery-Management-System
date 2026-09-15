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


/* ===============================
   SAVE INGREDIENT
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


    /* VALIDATION */

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
            ? "اوسنۍ ذخیره منفي کېدای نشي."
            : "Current stock cannot be negative.";

    } elseif ($minimum_stock < 0) {

        $error = $isPashto
            ? "لږ تر لږه ذخیره منفي کېدای نشي."
            : "Minimum stock cannot be negative.";

    } elseif ($purchase_price < 0) {

        $error = $isPashto
            ? "د پېرلو بیه منفي کېدای نشي."
            : "Purchase price cannot be negative.";

    } else {

        try {

            $stmt = $conn->prepare("
                INSERT INTO ingredients
                (
                    ingredient_name,
                    unit,
                    current_stock,
                    minimum_stock,
                    purchase_price,
                    supplier_id,
                    expiry_date
                )
                VALUES
                (
                    :ingredient_name,
                    :unit,
                    :current_stock,
                    :minimum_stock,
                    :purchase_price,
                    :supplier_id,
                    :expiry_date
                )
            ");

            $stmt->execute([

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
                    $expiry_date
            ]);


            $message = $isPashto
                ? "خام مواد په بریالیتوب سره اضافه شول."
                : "Ingredient added successfully.";

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
                ? "خام مواد اضافه نه شول. "
                    . $e->getMessage()
                : "Unable to add ingredient. "
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
        ? "خام مواد اضافه کول - د بیکري مدیریت"
        : "Add Ingredient - Bakery Management"
    ?>
</title>

<link
    rel="stylesheet"
    href="../assets/css/bootstrap.min.css">

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

    <?php if ($isPashto): ?>

    body {
        text-align: right;
    }

    .me-2 {
        margin-right: 0 !important;
        margin-left: .5rem !important;
    }

    .me-3 {
        margin-right: 0 !important;
        margin-left: 1rem !important;
    }

    <?php endif; ?>

</style>


</head>

<body>

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


        <!-- LANGUAGE -->

        <div class="me-3">

            <a
                href="?lang=ps"
                class="btn btn-sm <?= $isPashto
                    ? "btn-primary"
                    : "btn-outline-primary"
                ?>">

                پښتو

            </a>


            <a
                href="?lang=en"
                class="btn btn-sm <?= !$isPashto
                    ? "btn-primary"
                    : "btn-outline-primary"
                ?>">

                English

            </a>

        </div>


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

            <?= $isPashto
                ? "وتل"
                : "Logout"
            ?>

        </a>

    </div>

</div>


</nav>

<div class="container py-4">


<!-- HEADER -->

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="page-title mb-1">

            <i class="bi bi-plus-circle"></i>

            <?= $isPashto
                ? "خام مواد اضافه کول"
                : "Add Ingredient"
            ?>

        </h2>


        <p class="text-muted mb-0">

            <?= $isPashto
                ? "نوی خام مواد اضافه کړئ"
                : "Add a new raw material"
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


<!-- ERROR -->

<?php if ($error !== ""): ?>

    <div class="alert alert-danger">

        <i class="bi bi-exclamation-triangle-fill"></i>

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<!-- CARD -->

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


                <!-- INGREDIENT NAME -->

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
                            $_POST["ingredient_name"] ?? ""
                        ) ?>"
                        required>

                </div>


                <!-- UNIT -->

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
                        placeholder="<?= $isPashto
                            ? "کیلو، لیتر، دانه..."
                            : "kg, liter, piece..."
                        ?>"
                        value="<?= htmlspecialchars(
                            $_POST["unit"] ?? ""
                        ) ?>"
                        required>

                </div>


                <!-- CURRENT STOCK -->

                <div class="col-md-4">

                    <label class="form-label">

                        <?= $isPashto
                            ? "اوسنۍ ذخیره"
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
                            $_POST["current_stock"] ?? "0"
                        ) ?>">

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
                        step="0.001"
                        value="<?= htmlspecialchars(
                            $_POST["minimum_stock"] ?? "0"
                        ) ?>">

                </div>


                <!-- PURCHASE PRICE -->

                <div class="col-md-4">

                    <label class="form-label">

                        <?= $isPashto
                            ? "د پېرلو بیه"
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
                            $_POST["purchase_price"] ?? "0"
                        ) ?>">

                </div>


                <!-- SUPPLIER -->

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


                        <?php foreach ($suppliers as $supplier): ?>

                            <option
                                value="<?= (int) $supplier["supplier_id"] ?>"
                                <?= (
                                    ($_POST["supplier_id"] ?? "")
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


                <!-- EXPIRY DATE -->

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
                            $_POST["expiry_date"] ?? ""
                        ) ?>">

                </div>


            </div>


            <hr class="my-4">


            <!-- BUTTONS -->

            <div class="d-flex justify-content-end gap-2">

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
                    class="btn btn-success">

                    <i class="bi bi-check-circle"></i>

                    <?= $isPashto
                        ? "خام مواد خوندي کړئ"
                        : "Save Ingredient"
                    ?>

                </button>

            </div>

        </form>

    </div>

</div>

</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>

</body>

</html>
