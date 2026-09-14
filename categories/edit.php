
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";
$lang = $isPashto ? "ps" : "en";


/* =====================================================
   TRANSLATIONS
===================================================== */

$t = [

    "en" => [

        "system"            => "Bakery System",
        "categories"        => "Categories",
        "edit_category"     => "Edit Category",
        "update_information"=> "Update category information",
        "dashboard"         => "Dashboard",
        "logout"            => "Logout",
        "back"              => "Back",

        "category"          => "Category",
        "category_name"     => "Category Name",
        "description"       => "Description",
        "created_at"        => "Created At",

        "enter_name"        => "Enter category name",
        "enter_description" => "Enter category description...",

        "cancel"            => "Cancel",
        "update_category"   => "Update Category",

        "name_required"     => "Category name is required.",
        "duplicate"         => "Another category with this name already exists.",
        "not_found"         => "Category not found.",
        "updated"           => "Category updated successfully.",
        "error"             => "An error occurred while updating the category.",

        "unknown"           => "Unknown"

    ],


    "ps" => [

        "system"            => "د بیکري سیستم",
        "categories"        => "کټګورۍ",
        "edit_category"     => "کټګوري سمول",
        "update_information"=> "د کټګورۍ معلومات تازه کړئ",
        "dashboard"         => "ډشبورډ",
        "logout"            => "وتل",
        "back"              => "بېرته",

        "category"          => "کټګوري",
        "category_name"     => "د کټګورۍ نوم",
        "description"       => "تشریح",
        "created_at"        => "د جوړېدو نېټه",

        "enter_name"        => "د کټګورۍ نوم ولیکئ",
        "enter_description" => "د کټګورۍ تشریح ولیکئ...",

        "cancel"            => "لغوه کول",
        "update_category"   => "کټګوري تازه کول",

        "name_required"     => "د کټګورۍ نوم اړین دی.",
        "duplicate"         => "د دې نوم بله کټګوري له مخکې موجوده ده.",
        "not_found"         => "کټګوري پیدا نه شوه.",
        "updated"           => "کټګوري په بریالیتوب سره تازه شوه.",
        "error"             => "د کټګورۍ د تازه کولو پر مهال ستونزه رامنځته شوه.",

        "unknown"           => "نامعلوم"

    ]

];

$text = $t[$lang];


/* =====================================================
   GET CATEGORY ID
===================================================== */

$category_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


if ($category_id <= 0) {

    header("Location: index.php");
    exit;
}


/* =====================================================
   GET CATEGORY
===================================================== */

$stmt = $conn->prepare("
    SELECT
        category_id,
        category_name,
        description,
        created_at
    FROM categories
    WHERE category_id = :category_id
    LIMIT 1
");

$stmt->execute([
    ":category_id" => $category_id
]);

$category = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$category) {

    header(
        "Location: index.php?error=not_found"
    );

    exit;
}


/* =====================================================
   FORM VARIABLES
===================================================== */

$error = "";

$category_name = $category["category_name"];
$description = $category["description"] ?? "";


/* =====================================================
   UPDATE CATEGORY
   POST → UPDATE → REDIRECT → GET
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $category_name = trim(
        $_POST["category_name"] ?? ""
    );

    $description = trim(
        $_POST["description"] ?? ""
    );


    /* =================================================
       VALIDATION
    ================================================= */

    if ($category_name === "") {

        $error = $text["name_required"];

    } else {

        try {

            /* =========================================
               CHECK DUPLICATE CATEGORY
            ========================================= */

            $checkStmt = $conn->prepare("
                SELECT category_id
                FROM categories
                WHERE category_name = :category_name
                  AND category_id != :category_id
                LIMIT 1
            ");

            $checkStmt->execute([

                ":category_name" => $category_name,

                ":category_id" => $category_id

            ]);


            $existingCategory =
                $checkStmt->fetch(PDO::FETCH_ASSOC);


            if ($existingCategory) {

                $error = $text["duplicate"];

            } else {

                /* =====================================
                   UPDATE CATEGORY
                ===================================== */

                $updateStmt = $conn->prepare("
                    UPDATE categories
                    SET
                        category_name = :category_name,
                        description = :description
                    WHERE category_id = :category_id
                ");

                $updateStmt->execute([

                    ":category_name" => $category_name,

                    ":description" =>
                        $description !== ""
                            ? $description
                            : null,

                    ":category_id" => $category_id

                ]);


                /* =====================================
                   PRG PATTERN

                   POST
                   ↓
                   UPDATE
                   ↓
                   REDIRECT
                   ↓
                   GET

                   Refresh will NOT update again.
                ===================================== */

                header(
                    "Location: index.php?success=updated"
                );

                exit;
            }


        } catch (PDOException $e) {

            /*
                Database details are intentionally
                not shown to the user.
            */

            $error = $text["error"];
        }
    }
}

?>

<!DOCTYPE html>

<html
    lang="<?= $isPashto ? "ps" : "en" ?>"
    dir="<?= languageDirection() ?>"
>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>

        <?= htmlspecialchars(
            $text["edit_category"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>

        -

        <?= htmlspecialchars(
            $text["system"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>

    </title>


    <!-- Background -->

    <link
        rel="stylesheet"
        href="../assets/css/backgrounds.css"
    >


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

        .form-label {
            font-weight: 500;
        }

        .page-title {
            font-weight: 700;
        }

        [dir="rtl"] .me-1 {
            margin-right: 0 !important;
            margin-left: .25rem !important;
        }

        [dir="rtl"] .me-2 {
            margin-right: 0 !important;
            margin-left: .5rem !important;
        }

        [dir="rtl"] .me-3 {
            margin-right: 0 !important;
            margin-left: 1rem !important;
        }

    </style>

</head>


<body class="bg-expenses">


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar">

    <div class="container-fluid px-4">


        <!-- SYSTEM -->

        <a
            href="../admin/dashboard.php"
            class="navbar-brand fw-bold"
        >

            <i class="bi bi-shop"></i>

            <?= htmlspecialchars(
                $text["system"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </a>


        <!-- USER AREA -->

        <div class="d-flex align-items-center">


            <!-- USER -->

            <span class="me-3">

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars(
                    $_SESSION["full_name"] ?? "User",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </span>


            <!-- ROLE -->

            <span class="badge bg-primary me-3">

                <?= htmlspecialchars(
                    $_SESSION["role"] ?? $text["unknown"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </span>


            <!-- PASHTO -->

            <a
                href="?id=<?= (int) $category_id ?>&lang=ps"
                class="btn btn-sm
                <?= $isPashto
                    ? "btn-primary"
                    : "btn-outline-primary"
                ?> me-1"
            >

                پښتو

            </a>


            <!-- ENGLISH -->

            <a
                href="?id=<?= (int) $category_id ?>&lang=en"
                class="btn btn-sm
                <?= !$isPashto
                    ? "btn-primary"
                    : "btn-outline-primary"
                ?> me-3"
            >

                English

            </a>


            <!-- LOGOUT -->

            <a
                href="../public/logout.php"
                class="btn btn-outline-danger btn-sm"
            >

                <i class="bi bi-box-arrow-right"></i>

                <?= htmlspecialchars(
                    $text["logout"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

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
        class="d-flex
        justify-content-between
        align-items-center
        mb-4"
    >

        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-pencil-square"></i>

                <?= htmlspecialchars(
                    $text["edit_category"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </h2>


            <p class="text-muted mb-0">

                <?= htmlspecialchars(
                    $text["update_information"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </p>

        </div>


        <a
            href="view.php?id=<?= (int) $category_id ?>"
            class="btn btn-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            <?= htmlspecialchars(
                $text["back"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </a>

    </div>



    <!-- =================================================
         ERROR
    ================================================= -->

    <?php if ($error !== ""): ?>

        <div
            class="alert alert-danger
            alert-dismissible fade show"
        >

            <i
                class="bi bi-exclamation-triangle-fill"
            ></i>

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            ) ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>



    <!-- =================================================
         EDIT FORM
    ================================================= -->

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="card shadow-sm">


                <!-- CARD HEADER -->

                <div
                    class="card-header
                    bg-white
                    py-3"
                >

                    <h5 class="mb-0">

                        <i class="bi bi-tag"></i>

                        <?= htmlspecialchars(
                            $text["category"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                        #

                        <?= (int) $category_id ?>

                    </h5>

                </div>



                <!-- CARD BODY -->

                <div class="card-body">

                    <form
                        method="POST"
                        action="edit.php?id=<?= (int) $category_id ?>"
                        autocomplete="off"
                    >


                        <!-- =================================
                             CATEGORY NAME
                        ================================== -->

                        <div class="mb-4">

                            <label class="form-label">

                                <?= htmlspecialchars(
                                    $text["category_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                name="category_name"
                                class="form-control"
                                maxlength="100"
                                value="<?= htmlspecialchars(
                                    $category_name,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                placeholder="<?= htmlspecialchars(
                                    $text["enter_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                required
                            >

                        </div>



                        <!-- =================================
                             DESCRIPTION
                        ================================== -->

                        <div class="mb-4">

                            <label class="form-label">

                                <?= htmlspecialchars(
                                    $text["description"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </label>


                            <textarea
                                name="description"
                                class="form-control"
                                rows="5"
                                placeholder="<?= htmlspecialchars(
                                    $text["enter_description"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                            ><?= htmlspecialchars(
                                $description,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?></textarea>

                        </div>



                        <!-- =================================
                             CREATED AT
                        ================================== -->

                        <div class="mb-4">

                            <label class="form-label">

                                <?= htmlspecialchars(
                                    $text["created_at"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </label>


                            <input
                                type="text"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $category["created_at"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                readonly
                            >

                        </div>



                        <!-- =================================
                             BUTTONS
                        ================================== -->

                        <div
                            class="d-flex
                            justify-content-between"
                        >


                            <!-- CANCEL -->

                            <a
                                href="view.php?id=<?= (int) $category_id ?>"
                                class="btn btn-secondary"
                            >

                                <i class="bi bi-x-circle"></i>

                                <?= htmlspecialchars(
                                    $text["cancel"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </a>


                            <!-- UPDATE -->

                            <button
                                type="submit"
                                class="btn btn-success"
                            >

                                <i class="bi bi-save"></i>

                                <?= htmlspecialchars(
                                    $text["update_category"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>



<!-- =====================================================
     BOOTSTRAP JS
===================================================== -->

<script
    src="../assets/js/bootstrap.bundle.min.js"
></script>


</body>

</html>

