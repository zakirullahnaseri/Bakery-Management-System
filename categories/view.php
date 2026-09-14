
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

        "system"             => "Bakery System",
        "categories"         => "Categories",
        "view_category"      => "View Category",
        "category_details"   => "Category details",

        "dashboard"          => "Dashboard",
        "logout"             => "Logout",
        "back"               => "Back",

        "category"           => "Category",
        "category_info"      => "Category Information",
        "category_id"        => "Category ID",
        "category_name"      => "Category Name",
        "description"        => "Description",
        "created_at"         => "Created At",

        "actions"            => "Actions",
        "edit"               => "Edit",
        "edit_category"      => "Edit Category",
        "delete"             => "Delete Category",
        "back_categories"    => "Back to Categories",

        "no_description"     => "No description provided.",
        "confirm_delete"     => "Are you sure you want to delete this category?",

        "not_found"          => "Category not found.",
        "unknown"            => "Unknown"

    ],


    "ps" => [

        "system"             => "د بیکري سیستم",
        "categories"         => "کټګورۍ",
        "view_category"      => "کټګوري کتل",
        "category_details"   => "د کټګورۍ معلومات",

        "dashboard"          => "ډشبورډ",
        "logout"             => "وتل",
        "back"               => "بېرته",

        "category"           => "کټګوري",
        "category_info"      => "د کټګورۍ معلومات",
        "category_id"        => "د کټګورۍ شمېره",
        "category_name"      => "د کټګورۍ نوم",
        "description"        => "تشریح",
        "created_at"         => "د جوړېدو نېټه",

        "actions"            => "عملیات",
        "edit"               => "سمول",
        "edit_category"      => "کټګوري سمول",
        "delete"             => "کټګوري حذف کول",
        "back_categories"    => "کټګوریو ته بېرته",

        "no_description"     => "هیڅ تشریح نه ده ورکړل شوې.",
        "confirm_delete"     => "ایا ډاډه یاست چې دا کټګوري حذف کړئ؟",

        "not_found"          => "کټګوري پیدا نه شوه.",
        "unknown"            => "نامعلوم"

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


/* =====================================================
   CATEGORY NOT FOUND
===================================================== */

if (!$category) {

    header(
        "Location: index.php?error=not_found"
    );

    exit;
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
            $text["view_category"],
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

        .info-label {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 18px;
            font-weight: 500;
        }

        .description-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            min-height: 100px;
            white-space: normal;
            word-break: break-word;
        }

        .action-form {
            margin: 0;
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

            <h2 class="mb-1">

                <i class="bi bi-tag"></i>

                <?= htmlspecialchars(
                    $text["view_category"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </h2>


            <p class="text-muted mb-0">

                <?= htmlspecialchars(
                    $text["category_details"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </p>

        </div>


        <div>


            <!-- BACK -->

            <a
                href="index.php"
                class="btn btn-secondary me-1"
            >

                <i class="bi bi-arrow-left"></i>

                <?= htmlspecialchars(
                    $text["back"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </a>


            <!-- EDIT -->

            <a
                href="edit.php?id=<?= (int) $category["category_id"] ?>"
                class="btn btn-warning"
            >

                <i class="bi bi-pencil"></i>

                <?= htmlspecialchars(
                    $text["edit"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </a>

        </div>

    </div>



    <!-- =================================================
         CATEGORY INFORMATION
    ================================================= -->

    <div class="row g-4">


        <!-- =================================================
             LEFT
        ================================================= -->

        <div class="col-lg-8">

            <div class="card shadow-sm">


                <!-- CARD HEADER -->

                <div
                    class="card-header
                    bg-white
                    py-3"
                >

                    <h5 class="mb-0">

                        <i class="bi bi-info-circle"></i>

                        <?= htmlspecialchars(
                            $text["category_info"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </h5>

                </div>


                <!-- CARD BODY -->

                <div class="card-body">


                    <div class="row g-4">


                        <!-- CATEGORY ID -->

                        <div class="col-md-6">

                            <div class="info-label">

                                <?= htmlspecialchars(
                                    $text["category_id"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>


                            <div class="info-value">

                                #

                                <?= (int)
                                    $category["category_id"] ?>

                            </div>

                        </div>



                        <!-- CATEGORY NAME -->

                        <div class="col-md-6">

                            <div class="info-label">

                                <?= htmlspecialchars(
                                    $text["category_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>


                            <div class="info-value">

                                <?= htmlspecialchars(
                                    $category["category_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                        </div>



                        <!-- CREATED -->

                        <div class="col-md-6">

                            <div class="info-label">

                                <?= htmlspecialchars(
                                    $text["created_at"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>


                            <div class="info-value">

                                <?= htmlspecialchars(
                                    $category["created_at"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </div>

                        </div>


                    </div>


                    <hr class="my-4">


                    <!-- DESCRIPTION -->

                    <div>

                        <div class="info-label">

                            <?= htmlspecialchars(
                                $text["description"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </div>


                        <div class="description-box">

                            <?php if (
                                !empty(
                                    $category["description"]
                                )
                            ): ?>

                                <?= nl2br(
                                    htmlspecialchars(
                                        $category["description"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                ) ?>

                            <?php else: ?>

                                <span class="text-muted">

                                    <?= htmlspecialchars(
                                        $text["no_description"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            <?php endif; ?>

                        </div>

                    </div>


                </div>

            </div>

        </div>



        <!-- =================================================
             RIGHT
        ================================================= -->

        <div class="col-lg-4">

            <div class="card shadow-sm">


                <!-- CARD HEADER -->

                <div
                    class="card-header
                    bg-white
                    py-3"
                >

                    <h5 class="mb-0">

                        <i class="bi bi-gear"></i>

                        <?= htmlspecialchars(
                            $text["actions"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </h5>

                </div>


                <!-- CARD BODY -->

                <div class="card-body">


                    <!-- EDIT -->

                    <a
                        href="edit.php?id=<?= (int) $category["category_id"] ?>"
                        class="btn btn-warning w-100 mb-3"
                    >

                        <i class="bi bi-pencil"></i>

                        <?= htmlspecialchars(
                            $text["edit_category"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </a>



                    <!-- DELETE -->

                    <form
                        action="delete.php"
                        method="POST"
                        class="action-form mb-3"
                        onsubmit="return confirm(
                            '<?= htmlspecialchars(
                                $text["confirm_delete"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>'
                        );"
                    >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int)
                                $category["category_id"] ?>"
                        >


                        <button
                            type="submit"
                            class="btn btn-danger w-100"
                        >

                            <i class="bi bi-trash"></i>

                            <?= htmlspecialchars(
                                $text["delete"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </button>

                    </form>



                    <!-- BACK -->

                    <a
                        href="index.php"
                        class="btn btn-secondary w-100"
                    >

                        <i class="bi bi-arrow-left"></i>

                        <?= htmlspecialchars(
                            $text["back_categories"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </a>


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

