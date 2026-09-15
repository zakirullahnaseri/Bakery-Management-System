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
        . "&lang="
        . urlencode(currentLanguage())
    );

    exit;
}


/* ===============================
   GET INGREDIENT
================================ */

$stmt = $conn->prepare("
    SELECT
        i.*,
        s.name AS supplier_name,
        s.phone AS supplier_phone,
        s.email AS supplier_email,
        s.address AS supplier_address
    FROM ingredients i
    LEFT JOIN suppliers s
        ON i.supplier_id = s.supplier_id
    WHERE i.ingredient_id = :id
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
        . "&lang="
        . urlencode(currentLanguage())
    );

    exit;
}


$currentStock =
    (float) $ingredient["current_stock"];

$minimumStock =
    (float) $ingredient["minimum_stock"];


if ($currentStock <= 0) {

    $status = $isPashto
        ? "ذخیره ختمه ده"
        : "Out of Stock";

    $statusClass = "danger";

} elseif ($currentStock <= $minimumStock) {

    $status = $isPashto
        ? "ذخیره کمه ده"
        : "Low Stock";

    $statusClass = "warning";

} else {

    $status = $isPashto
        ? "موجود"
        : "Available";

    $statusClass = "success";
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
        ? "د خام موادو معلومات - د بیکري مدیریت"
        : "View Ingredient - Bakery Management"
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

    .info-label {
        font-size: 13px;
        color: #6c757d;
        margin-bottom: 4px;
    }

    .info-value {
        font-size: 17px;
        font-weight: 600;
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
                href="?id=<?= $id ?>&lang=ps"
                class="btn btn-sm <?= $isPashto
                    ? "btn-primary"
                    : "btn-outline-primary"
                ?>">

                پښتو

            </a>


            <a
                href="?id=<?= $id ?>&lang=en"
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

            <i class="bi bi-eye"></i>

            <?= $isPashto
                ? "د خام موادو معلومات"
                : "Ingredient Details"
            ?>

        </h2>


        <p class="text-muted mb-0">

            <?= $isPashto
                ? "د خام موادو معلومات وګورئ"
                : "View ingredient information"
            ?>

        </p>

    </div>


    <div>


        <a
            href="index.php"
            class="btn btn-secondary me-2">

            <i class="bi bi-arrow-left"></i>

            <?= $isPashto
                ? "بېرته"
                : "Back"
            ?>

        </a>


        <a
            href="edit.php?id=<?= $id ?>"
            class="btn btn-warning">

            <i class="bi bi-pencil-square"></i>

            <?= $isPashto
                ? "سمول"
                : "Edit"
            ?>

        </a>

    </div>

</div>



<div class="row g-4">


    <!-- INGREDIENT INFORMATION -->

    <div class="col-lg-8">

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


                <div class="row g-4">


                    <!-- ID -->

                    <div class="col-md-6">

                        <div class="info-label">

                            <?= $isPashto
                                ? "د خام موادو ID"
                                : "Ingredient ID"
                            ?>

                        </div>


                        <div class="info-value">

                            #<?= (int) $ingredient["ingredient_id"] ?>

                        </div>

                    </div>



                    <!-- NAME -->

                    <div class="col-md-6">

                        <div class="info-label">

                            <?= $isPashto
                                ? "د خام موادو نوم"
                                : "Ingredient Name"
                            ?>

                        </div>


                        <div class="info-value">

                            <?= htmlspecialchars(
                                $ingredient["ingredient_name"]
                            ) ?>

                        </div>

                    </div>



                    <!-- UNIT -->

                    <div class="col-md-6">

                        <div class="info-label">

                            <?= $isPashto
                                ? "واحد"
                                : "Unit"
                            ?>

                        </div>


                        <div class="info-value">

                            <?= htmlspecialchars(
                                $ingredient["unit"]
                            ) ?>

                        </div>

                    </div>



                    <!-- STATUS -->

                    <div class="col-md-6">

                        <div class="info-label">

                            <?= $isPashto
                                ? "حالت"
                                : "Status"
                            ?>

                        </div>


                        <div>

                            <span
                                class="badge bg-<?= $statusClass ?> fs-6">

                                <?= $status ?>

                            </span>

                        </div>

                    </div>



                    <!-- CURRENT STOCK -->

                    <div class="col-md-4">

                        <div class="info-label">

                            <?= $isPashto
                                ? "اوسنۍ ذخیره"
                                : "Current Stock"
                            ?>

                        </div>


                        <div class="info-value">

                            <?= number_format(
                                $currentStock,
                                3
                            ) ?>

                            <?= htmlspecialchars(
                                $ingredient["unit"]
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
                                $minimumStock,
                                3
                            ) ?>

                            <?= htmlspecialchars(
                                $ingredient["unit"]
                            ) ?>

                        </div>

                    </div>



                    <!-- PURCHASE PRICE -->

                    <div class="col-md-4">

                        <div class="info-label">

                            <?= $isPashto
                                ? "د پېرلو بیه"
                                : "Purchase Price"
                            ?>

                        </div>


                        <div class="info-value">

                            $<?= number_format(
                                (float) $ingredient["purchase_price"],
                                2
                            ) ?>

                        </div>

                    </div>



                    <!-- EXPIRY -->

                    <div class="col-md-6">

                        <div class="info-label">

                            <?= $isPashto
                                ? "د ختمېدو نېټه"
                                : "Expiry Date"
                            ?>

                        </div>


                        <div class="info-value">

                            <?php if (
                                !empty($ingredient["expiry_date"])
                            ): ?>

                                <?= htmlspecialchars(
                                    $ingredient["expiry_date"]
                                ) ?>

                            <?php else: ?>

                                <span class="text-muted">

                                    <?= $isPashto
                                        ? "نه ده ټاکل شوې"
                                        : "Not specified"
                                    ?>

                                </span>

                            <?php endif; ?>

                        </div>

                    </div>



                    <!-- CREATED -->

                    <div class="col-md-6">

                        <div class="info-label">

                            <?= $isPashto
                                ? "د جوړېدو نېټه"
                                : "Created At"
                            ?>

                        </div>


                        <div class="info-value">

                            <?= htmlspecialchars(
                                $ingredient["created_at"]
                            ) ?>

                        </div>

                    </div>


                </div>

            </div>

        </div>

    </div>



    <!-- SUPPLIER -->

    <div class="col-lg-4">


        <div class="card shadow-sm">


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


                <?php if (
                    !empty($ingredient["supplier_name"])
                ): ?>


                    <h5>

                        <?= htmlspecialchars(
                            $ingredient["supplier_name"]
                        ) ?>

                    </h5>


                    <hr>



                    <?php if (
                        !empty($ingredient["supplier_phone"])
                    ): ?>

                        <p class="mb-2">

                            <i class="bi bi-telephone"></i>

                            <?= htmlspecialchars(
                                $ingredient["supplier_phone"]
                            ) ?>

                        </p>

                    <?php endif; ?>



                    <?php if (
                        !empty($ingredient["supplier_email"])
                    ): ?>

                        <p class="mb-2">

                            <i class="bi bi-envelope"></i>

                            <?= htmlspecialchars(
                                $ingredient["supplier_email"]
                            ) ?>

                        </p>

                    <?php endif; ?>



                    <?php if (
                        !empty($ingredient["supplier_address"])
                    ): ?>

                        <p class="mb-0">

                            <i class="bi bi-geo-alt"></i>

                            <?= htmlspecialchars(
                                $ingredient["supplier_address"]
                            ) ?>

                        </p>

                    <?php endif; ?>


                <?php else: ?>


                    <div class="text-center py-3">

                        <i class="bi bi-truck fs-1 text-muted"></i>


                        <p class="text-muted mt-2 mb-0">

                            <?= $isPashto
                                ? "هیڅ عرضه کوونکی نه دی ټاکل شوی."
                                : "No supplier assigned."
                            ?>

                        </p>

                    </div>


                <?php endif; ?>

            </div>

        </div>



        <!-- ACTIONS -->

        <div class="card shadow-sm mt-4">


            <div class="card-header bg-white py-3">

                <h5 class="mb-0">

                    <i class="bi bi-lightning"></i>

                    <?= $isPashto
                        ? "عملیات"
                        : "Actions"
                    ?>

                </h5>

            </div>


            <div class="card-body">


                <a
                    href="edit.php?id=<?= $id ?>"
                    class="btn btn-warning w-100 mb-2">

                    <i class="bi bi-pencil-square"></i>

                    <?= $isPashto
                        ? "خام مواد سمول"
                        : "Edit Ingredient"
                    ?>

                </a>



                <a
                    href="delete.php?id=<?= $id ?>"
                    class="btn btn-danger w-100"
                    onclick="return confirm('<?= $isPashto
                        ? "ایا ډاډه یاست چې دا خام مواد حذف کړئ؟"
                        : "Are you sure you want to delete this ingredient?"
                    ?>');">

                    <i class="bi bi-trash"></i>

                    <?= $isPashto
                        ? "خام مواد حذف کول"
                        : "Delete Ingredient"
                    ?>

                </a>

            </div>

        </div>


    </div>


</div>


</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>

</body>

</html>
