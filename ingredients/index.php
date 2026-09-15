<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";


/* ===============================
   SEARCH
================================ */

$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $stmt = $conn->prepare("
        SELECT
            i.*,
            s.name AS supplier_name
        FROM ingredients i
        LEFT JOIN suppliers s
            ON i.supplier_id = s.supplier_id
        WHERE i.ingredient_name LIKE :search
           OR i.unit LIKE :search
           OR s.name LIKE :search
        ORDER BY i.ingredient_id DESC
    ");

    $stmt->execute([
        ":search" => "%$search%"
    ]);

} else {

    $stmt = $conn->query("
        SELECT
            i.*,
            s.name AS supplier_name
        FROM ingredients i
        LEFT JOIN suppliers s
            ON i.supplier_id = s.supplier_id
        ORDER BY i.ingredient_id DESC
    ");
}

$ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <?= $isPashto ? "خام مواد - د بیکري مدیریت" : "Ingredients - Bakery Management" ?>
</title>

<link rel="stylesheet" href="../assets/css/backgrounds.css">

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

    .table td {
        vertical-align: middle;
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

    .ms-2 {
        margin-left: 0 !important;
        margin-right: .5rem !important;
    }

    <?php endif; ?>

</style>


</head>

<body class="bg-ingredients">

<nav class="navbar">


<div class="container-fluid px-4">

    <a
        href="../admin/dashboard.php"
        class="navbar-brand fw-bold">

        <i class="bi bi-shop"></i>

        <?= $isPashto ? "د بیکري سیستم" : "Bakery System" ?>

    </a>


    <div class="d-flex align-items-center">

        <!-- LANGUAGE -->

        <div class="me-3">

            <a
                href="?lang=ps<?= $search !== "" ? "&search=" . urlencode($search) : "" ?>"
                class="btn btn-sm <?= $isPashto ? "btn-primary" : "btn-outline-primary" ?>">

                پښتو

            </a>

            <a
                href="?lang=en<?= $search !== "" ? "&search=" . urlencode($search) : "" ?>"
                class="btn btn-sm <?= !$isPashto ? "btn-primary" : "btn-outline-primary" ?>">

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

            <?= $isPashto ? "وتل" : "Logout" ?>

        </a>

    </div>

</div>

</nav>

<div class="container-fluid p-4">


<!-- HEADER -->

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="page-title mb-1">

            <i class="bi bi-box-seam"></i>

            <?= $isPashto ? "خام مواد" : "Ingredients" ?>

        </h2>

        <p class="text-muted mb-0">

            <?= $isPashto
                ? "د بیکري خام مواد مدیریت کړئ"
                : "Manage bakery raw materials"
            ?>

        </p>

    </div>


    <div>

        <a
            href="../admin/dashboard.php"
            class="btn btn-secondary me-2">

            <i class="bi bi-arrow-left"></i>

            <?= $isPashto ? "ډشبورډ" : "Dashboard" ?>

        </a>


        <a
            href="add.php"
            class="btn btn-primary">

            <i class="bi bi-plus-circle"></i>

            <?= $isPashto ? "خام مواد اضافه کړئ" : "Add Ingredient" ?>

        </a>

    </div>

</div>


<!-- SUCCESS -->

<?php if (isset($_GET["success"])): ?>

    <div class="alert alert-success alert-dismissible fade show">

        <i class="bi bi-check-circle-fill"></i>

        <?= htmlspecialchars($_GET["success"]) ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert">
        </button>

    </div>

<?php endif; ?>


<!-- ERROR -->

<?php if (isset($_GET["error"])): ?>

    <div class="alert alert-danger alert-dismissible fade show">

        <i class="bi bi-exclamation-triangle-fill"></i>

        <?= htmlspecialchars($_GET["error"]) ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert">
        </button>

    </div>

<?php endif; ?>


<!-- CARD -->

<div class="card shadow-sm">

    <div class="card-header bg-white py-3">

        <div class="d-flex justify-content-between align-items-center">

            <h5 class="mb-0">

                <i class="bi bi-list-ul"></i>

                <?= $isPashto ? "د خامو موادو لست" : "Ingredient List" ?>

            </h5>


            <span class="badge bg-primary">

                <?= count($ingredients) ?>

                <?= $isPashto ? "خام مواد" : "Ingredients" ?>

            </span>

        </div>

    </div>


    <div class="card-body">


        <!-- SEARCH -->

        <form method="GET" class="mb-4">

            <input
                type="hidden"
                name="lang"
                value="<?= htmlspecialchars(currentLanguage()) ?>">

            <div class="input-group">

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="<?= $isPashto
                        ? "خام مواد، واحد یا عرضه کوونکی ولټوئ..."
                        : "Search ingredient, unit or supplier..."
                    ?>"
                    value="<?= htmlspecialchars($search) ?>">


                <button
                    type="submit"
                    class="btn btn-primary">

                    <i class="bi bi-search"></i>

                    <?= $isPashto ? "لټون" : "Search" ?>

                </button>


                <?php if ($search !== ""): ?>

                    <a
                        href="index.php?lang=<?= urlencode(currentLanguage()) ?>"
                        class="btn btn-secondary">

                        <i class="bi bi-x-circle"></i>

                        <?= $isPashto ? "پاکول" : "Clear" ?>

                    </a>

                <?php endif; ?>

            </div>

        </form>


        <?php if (empty($ingredients)): ?>

            <div class="text-center py-5">

                <i class="bi bi-box-seam fs-1 text-muted"></i>


                <h5 class="mt-3">

                    <?= $isPashto
                        ? "هیڅ خام مواد ونه موندل شول"
                        : "No Ingredients Found"
                    ?>

                </h5>


                <p class="text-muted">

                    <?= $isPashto
                        ? "خپل لومړی خام مواد اضافه کړئ."
                        : "Add your first ingredient."
                    ?>

                </p>


                <a
                    href="add.php"
                    class="btn btn-primary">

                    <i class="bi bi-plus-circle"></i>

                    <?= $isPashto
                        ? "خام مواد اضافه کړئ"
                        : "Add Ingredient"
                    ?>

                </a>

            </div>

        <?php else: ?>


            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>
                                <?= $isPashto ? "خام مواد" : "Ingredient" ?>
                            </th>

                            <th>
                                <?= $isPashto ? "واحد" : "Unit" ?>
                            </th>

                            <th>
                                <?= $isPashto ? "اوسنی ذخیره" : "Current Stock" ?>
                            </th>

                            <th>
                                <?= $isPashto ? "لږ تر لږه ذخیره" : "Minimum Stock" ?>
                            </th>

                            <th>
                                <?= $isPashto ? "د پېرلو بیه" : "Purchase Price" ?>
                            </th>

                            <th>
                                <?= $isPashto ? "عرضه کوونکی" : "Supplier" ?>
                            </th>

                            <th>
                                <?= $isPashto ? "د ختمېدو نېټه" : "Expiry Date" ?>
                            </th>

                            <th>
                                <?= $isPashto ? "حالت" : "Status" ?>
                            </th>

                            <th class="text-center">
                                <?= $isPashto ? "عملیات" : "Actions" ?>
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($ingredients as $ingredient): ?>

                        <?php

                        $currentStock =
                            (float) $ingredient["current_stock"];

                        $minimumStock =
                            (float) $ingredient["minimum_stock"];

                        $isLowStock =
                            $currentStock <= $minimumStock;

                        ?>


                        <tr>

                            <td>

                                <strong>
                                    #<?= (int) $ingredient["ingredient_id"] ?>
                                </strong>

                            </td>


                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $ingredient["ingredient_name"]
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <span class="badge bg-secondary">

                                    <?= htmlspecialchars(
                                        $ingredient["unit"]
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?php if ($isLowStock): ?>

                                    <span class="badge bg-danger">

                                        <?= number_format(
                                            $currentStock,
                                            3
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-success">

                                        <?= number_format(
                                            $currentStock,
                                            3
                                        ) ?>

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= number_format(
                                    $minimumStock,
                                    3
                                ) ?>

                            </td>


                            <td>

                                $<?= number_format(
                                    (float) $ingredient["purchase_price"],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <?php if (
                                    !empty($ingredient["supplier_name"])
                                ): ?>

                                    <?= htmlspecialchars(
                                        $ingredient["supplier_name"]
                                    ) ?>

                                <?php else: ?>

                                    <span class="text-muted">

                                        <?= $isPashto
                                            ? "نشته"
                                            : "N/A"
                                        ?>

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if (
                                    !empty($ingredient["expiry_date"])
                                ): ?>

                                    <?= htmlspecialchars(
                                        $ingredient["expiry_date"]
                                    ) ?>

                                <?php else: ?>

                                    <span class="text-muted">

                                        <?= $isPashto
                                            ? "نشته"
                                            : "N/A"
                                        ?>

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php

                                if ($currentStock <= 0) {

                                    $status = $isPashto
                                        ? "ذخیره ختمه ده"
                                        : "Out of Stock";

                                    $statusClass = "danger";

                                } elseif ($isLowStock) {

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


                                <span
                                    class="badge bg-<?= $statusClass ?>">

                                    <?= $status ?>

                                </span>

                            </td>


                            <td class="text-center">

                                <div
                                    class="btn-group"
                                    role="group">


                                    <!-- VIEW -->

                                    <a
                                        href="view.php?id=<?= (int) $ingredient["ingredient_id"] ?>"
                                        class="btn btn-sm btn-primary"
                                        title="<?= $isPashto ? "کتل" : "View" ?>">

                                        <i class="bi bi-eye"></i>

                                    </a>


                                    <!-- EDIT -->

                                    <a
                                        href="edit.php?id=<?= (int) $ingredient["ingredient_id"] ?>"
                                        class="btn btn-sm btn-warning"
                                        title="<?= $isPashto ? "سمول" : "Edit" ?>">

                                        <i class="bi bi-pencil-square"></i>

                                    </a>


                                    <!-- DELETE -->

                                    <a
                                        href="delete.php?id=<?= (int) $ingredient["ingredient_id"] ?>"
                                        class="btn btn-sm btn-danger"
                                        title="<?= $isPashto ? "حذف" : "Delete" ?>"
                                        onclick="return confirm('<?= $isPashto
                                            ? "ایا ډاډه یاست چې دا خام مواد حذف کړئ؟"
                                            : "Are you sure you want to delete this ingredient?"
                                        ?>');">

                                        <i class="bi bi-trash"></i>

                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>


</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>

</body>

</html>
