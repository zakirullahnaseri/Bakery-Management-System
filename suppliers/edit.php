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
| GET SUPPLIER ID
|--------------------------------------------------------------------------
*/

$supplier_id = isset($_GET["supplier_id"])
    ? (int) $_GET["supplier_id"]
    : 0;

if ($supplier_id <= 0) {
    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| GET SUPPLIER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        supplier_id,
        name,
        phone,
        address,
        email,
        created_at
    FROM suppliers
    WHERE supplier_id = :supplier_id
");

$stmt->execute([
    ":supplier_id" => $supplier_id
]);

$supplier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supplier) {

    $message = currentLanguage() === "ps"
        ? "عرضه کوونکی ونه موندل شو."
        : "Supplier not found.";

    die($message);
}


/*
|--------------------------------------------------------------------------
| UPDATE SUPPLIER
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $email = trim($_POST["email"] ?? "");


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($name === "") {

        $error = currentLanguage() === "ps"
            ? "د عرضه کوونکي نوم ضروري دی."
            : "Supplier name is required.";

    } elseif (
        $email !== ""
        && !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $error = currentLanguage() === "ps"
            ? "مهرباني وکړئ صحیح ایمیل ولیکئ."
            : "Please enter a valid email address.";

    } else {

        try {

            $updateStmt = $conn->prepare("
                UPDATE suppliers
                SET
                    name = :name,
                    phone = :phone,
                    address = :address,
                    email = :email
                WHERE supplier_id = :supplier_id
            ");

            $updateStmt->execute([

                ":name" => $name,

                ":phone" =>
                    $phone !== ""
                        ? $phone
                        : null,

                ":address" =>
                    $address !== ""
                        ? $address
                        : null,

                ":email" =>
                    $email !== ""
                        ? $email
                        : null,

                ":supplier_id" => $supplier_id
            ]);


            /*
            |--------------------------------------------------------------------------
            | REDIRECT
            |--------------------------------------------------------------------------
            */

            header(
                "Location: index.php?updated=1"
            );

            exit;


        } catch (PDOException $e) {

            $error = currentLanguage() === "ps"
                ? "د عرضه کوونکي د معلوماتو په بدلولو کې ستونزه رامنځته شوه."
                : "Error updating supplier.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | KEEP USER INPUT
    |--------------------------------------------------------------------------
    */

    $supplier["name"] = $name;
    $supplier["phone"] = $phone;
    $supplier["address"] = $address;
    $supplier["email"] = $email;
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

        <?= currentLanguage() === "ps"
            ? "د عرضه کوونکي سمون"
            : "Edit Supplier"
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

            <?= currentLanguage() === "ps"
                ? "د بیکري سیستم"
                : "Bakery System"
            ?>

        </a>


        <div class="d-flex align-items-center">

            <a
                href="?supplier_id=<?= $supplier_id ?>&lang=ps"
                class="btn btn-outline-primary btn-sm me-1"
            >
                پښتو
            </a>

            <a
                href="?supplier_id=<?= $supplier_id ?>&lang=en"
                class="btn btn-outline-secondary btn-sm me-3"
            >
                English
            </a>


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

                <?= currentLanguage() === "ps"
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

<div class="container p-4">


    <!-- HEADER -->

    <div
        class="d-flex justify-content-between align-items-center mb-4"
    >

        <div>

            <h2>

                <i class="bi bi-pencil-square"></i>

                <?= currentLanguage() === "ps"
                    ? "د عرضه کوونکي سمون"
                    : "Edit Supplier"
                ?>

            </h2>

            <p class="text-muted mb-0">

                <?= currentLanguage() === "ps"
                    ? "د عرضه کوونکي معلومات بدل کړئ."
                    : "Update supplier information."
                ?>

            </p>

        </div>


        <a
            href="index.php"
            class="btn btn-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            <?= currentLanguage() === "ps"
                ? "بېرته"
                : "Back"
            ?>

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

    <div class="card shadow-sm">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">

                <i class="bi bi-truck"></i>

                <?= currentLanguage() === "ps"
                    ? "عرضه کوونکی #"
                    : "Supplier #"
                ?>

                <?= $supplier_id ?>

            </h5>

        </div>


        <div class="card-body">

            <form method="POST">


                <div class="row g-4">


                    <!-- NAME -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= currentLanguage() === "ps"
                                ? "د عرضه کوونکي نوم"
                                : "Supplier Name"
                            ?>

                            <span class="text-danger">*</span>

                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            maxlength="100"
                            value="<?= htmlspecialchars(
                                $supplier["name"]
                            ) ?>"
                            required
                        >

                    </div>



                    <!-- PHONE -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= currentLanguage() === "ps"
                                ? "تلیفون"
                                : "Phone"
                            ?>

                        </label>

                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            maxlength="30"
                            value="<?= htmlspecialchars(
                                $supplier["phone"] ?? ""
                            ) ?>"
                        >

                    </div>



                    <!-- EMAIL -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= currentLanguage() === "ps"
                                ? "ایمیل"
                                : "Email"
                            ?>

                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            maxlength="100"
                            value="<?= htmlspecialchars(
                                $supplier["email"] ?? ""
                            ) ?>"
                        >

                    </div>



                    <!-- ADDRESS -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= currentLanguage() === "ps"
                                ? "پته"
                                : "Address"
                            ?>

                        </label>

                        <textarea
                            name="address"
                            class="form-control"
                            rows="3"
                            maxlength="255"
                        ><?= htmlspecialchars(
                            $supplier["address"] ?? ""
                        ) ?></textarea>

                    </div>

                </div>


                <hr class="my-4">


                <!-- BUTTONS -->

                <div class="d-flex gap-2">


                    <button
                        type="submit"
                        class="btn btn-success"
                    >

                        <i class="bi bi-save"></i>

                        <?= currentLanguage() === "ps"
                            ? "معلومات تازه کړئ"
                            : "Update Supplier"
                        ?>

                    </button>


                    <a
                        href="index.php"
                        class="btn btn-secondary"
                    >

                        <i class="bi bi-x-circle"></i>

                        <?= currentLanguage() === "ps"
                            ? "لغوه"
                            : "Cancel"
                        ?>

                    </a>

                </div>

            </form>

        </div>

    </div>

</div>


<script src="../assets/js/bootstrap.bundle.min.js"></script>

</body>

</html>