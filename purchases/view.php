<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

/*
|--------------------------------------------------------------------------
| LANGUAGE
|--------------------------------------------------------------------------
*/
$isPashto = currentLanguage() === "ps";

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];

/*
|--------------------------------------------------------------------------
| PURCHASE ID
|--------------------------------------------------------------------------
*/
$purchase_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($purchase_id <= 0) {
    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| GET PURCHASE
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        p.purchase_id,
        p.supplier_id,
        p.user_id,
        p.purchase_date,
        p.total_amount,
        p.paid_amount,
        p.due_amount,
        p.payment_status,
        p.notes,
        s.name AS supplier_name,
        u.full_name
    FROM purchases p
    LEFT JOIN suppliers s
        ON p.supplier_id = s.supplier_id
    LEFT JOIN users u
        ON p.user_id = u.user_id
    WHERE p.purchase_id = :purchase_id
    LIMIT 1
");

$stmt->execute([
    ":purchase_id" => $purchase_id
]);

$purchase = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$purchase) {
    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| GET ITEMS
|--------------------------------------------------------------------------
*/
$itemStmt = $conn->prepare("
    SELECT
        pi.purchase_item_id,
        pi.ingredient_id,
        pi.quantity,
        pi.unit_price,
        pi.total_price,
        i.ingredient_name,
        i.unit
    FROM purchase_items pi
    INNER JOIN ingredients i
        ON pi.ingredient_id = i.ingredient_id
    WHERE pi.purchase_id = :purchase_id
    ORDER BY pi.purchase_item_id ASC
");

$itemStmt->execute([
    ":purchase_id" => $purchase_id
]);

$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PAYMENT STATUS
|--------------------------------------------------------------------------
*/
$status = $purchase["payment_status"];

if ($status === "paid") {

    $badgeClass = "bg-success";

    $statusText = $isPashto
        ? "تادیه شوی"
        : "Paid";

} elseif ($status === "partial") {

    $badgeClass = "bg-warning text-dark";

    $statusText = $isPashto
        ? "نیمه تادیه"
        : "Partial";

} else {

    $badgeClass = "bg-danger";

    $statusText = $isPashto
        ? "نا تادیه"
        : "Unpaid";
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
        <?= $isPashto ? "د پېرود معلومات" : "View Purchase" ?>
        #<?= $purchase_id ?>
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

        .info-box {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
        }

        <?php if ($isPashto): ?>

        body {
            font-family: Tahoma, Arial, sans-serif;
        }

        .table th,
        .table td {
            text-align: right;
        }

        <?php endif; ?>

    </style>

</head>

<body>

<nav class="navbar">

    <div class="container-fluid px-4">

        <a
            href="../admin/dashboard.php"
            class="navbar-brand fw-bold"
        >
            <i class="bi bi-shop"></i>
            <?= $isPashto ? "د بیکري سیستم" : "Bakery System" ?>
        </a>

        <div class="d-flex align-items-center gap-2">

            <!-- Language -->

            <a
                href="?id=<?= $purchase_id ?>&lang=ps"
                class="btn btn-sm <?= $isPashto ? "btn-primary" : "btn-outline-primary" ?>"
            >
                پښتو
            </a>

            <a
                href="?id=<?= $purchase_id ?>&lang=en"
                class="btn btn-sm <?= !$isPashto ? "btn-primary" : "btn-outline-primary" ?>"
            >
                English
            </a>

            <span class="ms-2 me-2">

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars(
                    $_SESSION["full_name"] ?? "User",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </span>

            <a
                href="../public/logout.php"
                class="btn btn-outline-danger btn-sm"
            >
                <i class="bi bi-box-arrow-right"></i>

                <?= $isPashto ? "وتل" : "Logout" ?>

            </a>

        </div>

    </div>

</nav>


<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold">

                <i class="bi bi-receipt"></i>

                <?= $isPashto
                    ? "پېرود"
                    : "Purchase"
                ?>

                #<?= $purchase_id ?>

            </h2>

            <p class="text-muted mb-0">

                <?= $isPashto
                    ? "د پېرود جزیات وګورئ"
                    : "View purchase details"
                ?>

            </p>

        </div>


        <div class="d-flex gap-2">

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                <i class="bi bi-arrow-left"></i>

                <?= $isPashto ? "بېرته" : "Back" ?>

            </a>


            <a
                href="edit.php?id=<?= $purchase_id ?>"
                class="btn btn-warning"
            >
                <i class="bi bi-pencil-square"></i>

                <?= $isPashto ? "سمون" : "Edit" ?>

            </a>


            <form
                method="POST"
                action="delete.php"
                class="d-inline"
                onsubmit="return confirm(
                    '<?= $isPashto
                        ? "ایا ډاډه یاست چې دا پېرود حذف کړئ؟ د پېرودل شوې اندازې مقدار به له سټاک څخه هم کم شي."
                        : "Are you sure you want to delete this purchase? The purchased quantity will be removed from stock."
                    ?>'
                );"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrf_token,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="purchase_id"
                    value="<?= $purchase_id ?>"
                >

                <button
                    type="submit"
                    class="btn btn-danger"
                >
                    <i class="bi bi-trash"></i>

                    <?= $isPashto ? "حذف" : "Delete" ?>

                </button>

            </form>

        </div>

    </div>


    <?php if (isset($_GET["updated"])): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-check-circle"></i>

            <?= $isPashto
                ? "پېرود په بریالیتوب سره تازه شو."
                : "Purchase updated successfully."
            ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- Purchase Information -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">

                <i class="bi bi-info-circle"></i>

                <?= $isPashto
                    ? "د پېرود معلومات"
                    : "Purchase Information"
                ?>

            </h5>

        </div>


        <div class="card-body">

            <div class="row g-4">

                <!-- Purchase ID -->

                <div class="col-md-3">

                    <div class="info-box">

                        <small class="text-muted">

                            <?= $isPashto
                                ? "د پېرود شمېره"
                                : "Purchase ID"
                            ?>

                        </small>

                        <h5 class="mb-0">
                            #<?= $purchase_id ?>
                        </h5>

                    </div>

                </div>


                <!-- Purchase Date -->

                <div class="col-md-3">

                    <div class="info-box">

                        <small class="text-muted">

                            <?= $isPashto
                                ? "د پېرود نېټه"
                                : "Purchase Date"
                            ?>

                        </small>

                        <h6 class="mb-0">

                            <?= htmlspecialchars(
                                $purchase["purchase_date"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </h6>

                    </div>

                </div>


                <!-- Supplier -->

                <div class="col-md-3">

                    <div class="info-box">

                        <small class="text-muted">

                            <?= $isPashto
                                ? "عرضه کوونکی"
                                : "Supplier"
                            ?>

                        </small>

                        <h6 class="mb-0">

                            <?php if (!empty($purchase["supplier_name"])): ?>

                                <?= htmlspecialchars(
                                    $purchase["supplier_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            <?php else: ?>

                                <span class="text-muted">

                                    <?= $isPashto
                                        ? "عرضه کوونکی نشته"
                                        : "No Supplier"
                                    ?>

                                </span>

                            <?php endif; ?>

                        </h6>

                    </div>

                </div>


                <!-- Created By -->

                <div class="col-md-3">

                    <div class="info-box">

                        <small class="text-muted">

                            <?= $isPashto
                                ? "جوړوونکی"
                                : "Created By"
                            ?>

                        </small>

                        <h6 class="mb-0">

                            <?= htmlspecialchars(
                                $purchase["full_name"] ?? "Unknown",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </h6>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Purchase Items -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">

                <i class="bi bi-basket"></i>

                <?= $isPashto
                    ? "د پېرود توکي"
                    : "Purchase Items"
                ?>

            </h5>

        </div>


        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-light">

                        <tr>

                            <th>#</th>

                            <th>
                                <?= $isPashto
                                    ? "اجناس"
                                    : "Ingredient"
                                ?>
                            </th>

                            <th>
                                <?= $isPashto
                                    ? "مقدار"
                                    : "Quantity"
                                ?>
                            </th>

                            <th>
                                <?= $isPashto
                                    ? "واحد"
                                    : "Unit"
                                ?>
                            </th>

                            <th>
                                <?= $isPashto
                                    ? "د واحد بیه"
                                    : "Unit Price"
                                ?>
                            </th>

                            <th>
                                <?= $isPashto
                                    ? "ټول"
                                    : "Total"
                                ?>
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (empty($items)): ?>

                            <tr>

                                <td
                                    colspan="6"
                                    class="text-center text-muted py-4"
                                >

                                    <?= $isPashto
                                        ? "د پېرود توکي ونه موندل شول."
                                        : "No purchase items found."
                                    ?>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($items as $index => $item): ?>

                                <tr>

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>


                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $item["ingredient_name"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <?= number_format(
                                            (float) $item["quantity"],
                                            3
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $item["unit"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </td>


                                    <td>

                                        $<?= number_format(
                                            (float) $item["unit_price"],
                                            2
                                        ) ?>

                                    </td>


                                    <td>

                                        <strong>

                                            $<?= number_format(
                                                (float) $item["total_price"],
                                                2
                                            ) ?>

                                        </strong>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- Notes + Payment -->

    <div class="row g-4">


        <!-- Notes -->

        <div class="col-lg-7">

            <div class="card shadow-sm h-100">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-sticky"></i>

                        <?= $isPashto
                            ? "یادښتونه"
                            : "Notes"
                        ?>

                    </h5>

                </div>


                <div class="card-body">

                    <?php if (!empty(trim($purchase["notes"] ?? ""))): ?>

                        <p class="mb-0">

                            <?= nl2br(
                                htmlspecialchars(
                                    $purchase["notes"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                )
                            ) ?>

                        </p>

                    <?php else: ?>

                        <p class="text-muted mb-0">

                            <?= $isPashto
                                ? "هیڅ یادښت نه دی اضافه شوی."
                                : "No notes added."
                            ?>

                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- Payment Summary -->

        <div class="col-lg-5">

            <div class="card shadow-sm">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-calculator"></i>

                        <?= $isPashto
                            ? "د تادیې لنډیز"
                            : "Payment Summary"
                        ?>

                    </h5>

                </div>


                <div class="card-body">


                    <!-- Total -->

                    <div class="d-flex justify-content-between mb-3">

                        <span>

                            <?= $isPashto
                                ? "ټول مبلغ"
                                : "Total Amount"
                            ?>

                        </span>

                        <strong class="text-primary">

                            $<?= number_format(
                                (float) $purchase["total_amount"],
                                2
                            ) ?>

                        </strong>

                    </div>


                    <!-- Paid -->

                    <div class="d-flex justify-content-between mb-3">

                        <span>

                            <?= $isPashto
                                ? "تادیه شوی مبلغ"
                                : "Paid Amount"
                            ?>

                        </span>

                        <strong class="text-success">

                            $<?= number_format(
                                (float) $purchase["paid_amount"],
                                2
                            ) ?>

                        </strong>

                    </div>


                    <hr>


                    <!-- Due -->

                    <div class="d-flex justify-content-between mb-3">

                        <span class="fw-bold">

                            <?= $isPashto
                                ? "پاتې مبلغ"
                                : "Due Amount"
                            ?>

                        </span>

                        <strong class="text-danger fs-5">

                            $<?= number_format(
                                (float) $purchase["due_amount"],
                                2
                            ) ?>

                        </strong>

                    </div>


                    <!-- Status -->

                    <div class="d-flex justify-content-between align-items-center">

                        <span>

                            <?= $isPashto
                                ? "د تادیې حالت"
                                : "Payment Status"
                            ?>

                        </span>


                        <span class="badge <?= $badgeClass ?>">

                            <?= $statusText ?>

                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- Bootstrap JS -->

<script src="../assets/js/bootstrap.bundle.min.js"></script>

</body>

</html>