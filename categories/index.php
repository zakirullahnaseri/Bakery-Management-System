
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
        "manage_categories" => "Manage product categories",
        "dashboard"         => "Dashboard",
        "logout"            => "Logout",

        "add_category"      => "Add Category",
        "category_name"     => "Category Name",
        "description"       => "Description",
        "created"           => "Created",
        "actions"           => "Actions",

        "enter_name"        => "Enter category name",
        "enter_description" => "Enter category description...",
        "save_category"     => "Save Category",

        "category_list"     => "Category List",
        "category"         => "Categories",

        "search"           => "Search",
        "search_category"  => "Search category...",
        "clear"            => "Clear",

        "no_categories"    => "No categories found.",
        "add_first"        => "Add your first category.",

        "view"             => "View",
        "edit"             => "Edit",
        "delete"           => "Delete",

        "confirm_delete"   => "Are you sure you want to delete this category?",

        "name_required"    => "Category name is required.",
        "duplicate"        => "This category already exists.",
        "added"            => "Category added successfully.",
        "updated"          => "Category updated successfully.",
        "deleted"          => "Category deleted successfully.",
        "not_found"        => "Category not found.",

        "used"             => "This category cannot be deleted because it is being used by another record.",

        "delete_failed"    => "Failed to delete category.",
        "invalid_request"  => "Invalid request.",
        "error"            => "An error occurred.",

        "unknown"          => "Unknown"

    ],


    "ps" => [

        "system"            => "د بیکري سیستم",
        "categories"        => "کټګورۍ",
        "manage_categories" => "د محصولاتو کټګورۍ مدیریت کړئ",
        "dashboard"         => "ډشبورډ",
        "logout"            => "وتل",

        "add_category"      => "کټګوري اضافه کول",
        "category_name"     => "د کټګورۍ نوم",
        "description"       => "تشریح",
        "created"           => "د جوړېدو نېټه",
        "actions"           => "عملیات",

        "enter_name"        => "د کټګورۍ نوم ولیکئ",
        "enter_description" => "د کټګورۍ تشریح ولیکئ...",
        "save_category"     => "کټګوري خوندي کول",

        "category_list"     => "د کټګوریو لېست",
        "category"         => "کټګورۍ",

        "search"           => "لټون",
        "search_category"  => "کټګوري ولټوئ...",
        "clear"            => "پاکول",

        "no_categories"    => "هیڅ کټګوري ونه موندل شوه.",
        "add_first"        => "خپله لومړۍ کټګوري اضافه کړئ.",

        "view"             => "کتل",
        "edit"             => "سمول",
        "delete"           => "حذف",

        "confirm_delete"   => "ایا ډاډه یاست چې دا کټګوري حذف کړئ؟",

        "name_required"    => "د کټګورۍ نوم اړین دی.",
        "duplicate"        => "دا کټګوري له مخکې موجوده ده.",
        "added"            => "کټګوري په بریالیتوب سره اضافه شوه.",
        "updated"          => "کټګوري په بریالیتوب سره بدله شوه.",
        "deleted"          => "کټګوري په بریالیتوب سره حذف شوه.",
        "not_found"        => "کټګوري پیدا نه شوه.",

        "used"             => "دا کټګوري ځکه نه شي حذف کېدای چې په بل ریکارډ کې کارول شوې ده.",

        "delete_failed"    => "د کټګورۍ په حذفولو کې ستونزه رامنځته شوه.",
        "invalid_request"  => "ناسمه غوښتنه ده.",
        "error"            => "یوه ستونزه رامنځته شوه.",

        "unknown"          => "نامعلوم"

    ]

];

$text = $t[$lang];


/* =====================================================
   FORM VARIABLES
===================================================== */

$error = "";

$category_name = "";
$description = "";


/* =====================================================
   ADD CATEGORY
   POST → INSERT → REDIRECT → GET
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $category_name = trim(
        $_POST["category_name"] ?? ""
    );

    $description = trim(
        $_POST["description"] ?? ""
    );


    /* ===============================
       VALIDATION
    ================================ */

    if ($category_name === "") {

        $error = $text["name_required"];

    } else {

        try {

            /* ===============================
               CHECK DUPLICATE
            ================================ */

            $checkStmt = $conn->prepare("
                SELECT category_id
                FROM categories
                WHERE category_name = :category_name
                LIMIT 1
            ");

            $checkStmt->execute([
                ":category_name" => $category_name
            ]);

            $existingCategory = $checkStmt->fetch(
                PDO::FETCH_ASSOC
            );


            if ($existingCategory) {

                $error = $text["duplicate"];

            } else {

                /* ===============================
                   INSERT
                ================================ */

                $insertStmt = $conn->prepare("
                    INSERT INTO categories
                    (
                        category_name,
                        description
                    )
                    VALUES
                    (
                        :category_name,
                        :description
                    )
                ");

                $insertStmt->execute([

                    ":category_name" => $category_name,

                    ":description" =>
                        $description !== ""
                            ? $description
                            : null

                ]);


                $categoryId =
                    $conn->lastInsertId();


                /*
                    PRG PATTERN

                    POST
                    ↓
                    INSERT
                    ↓
                    REDIRECT
                    ↓
                    GET

                    Refresh will NOT insert again.
                */

                header(
                    "Location: index.php?success=added&category_id="
                    . (int) $categoryId
                );

                exit;
            }


        } catch (PDOException $e) {

            /*
                Do not show database details
                to the user.
            */

            $error = $text["error"];
        }
    }
}


/* =====================================================
   SEARCH
===================================================== */

$search = trim(
    $_GET["search"] ?? ""
);


if ($search !== "") {

    $stmt = $conn->prepare("
        SELECT
            category_id,
            category_name,
            description,
            created_at
        FROM categories
        WHERE category_name LIKE :search
           OR description LIKE :search
        ORDER BY category_id DESC
    ");

    $stmt->execute([
        ":search" => "%$search%"
    ]);

} else {

    $stmt = $conn->query("
        SELECT
            category_id,
            category_name,
            description,
            created_at
        FROM categories
        ORDER BY category_id DESC
    ");
}


$categories = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

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
            $text["categories"]
        ) ?>

        -

        <?= htmlspecialchars(
            $text["system"]
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

        .page-title {
            font-weight: 700;
        }

        .table td {
            vertical-align: middle;
        }

        .table th {
            white-space: nowrap;
        }

        .action-form {
            display: inline-block;
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
                $text["system"]
            ) ?>

        </a>



        <!-- USER AREA -->

        <div class="d-flex align-items-center">


            <!-- USER -->

            <span class="me-3">

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars(
                    $_SESSION["full_name"] ?? "User"
                ) ?>

            </span>


            <!-- ROLE -->

            <span class="badge bg-primary me-3">

                <?= htmlspecialchars(
                    $_SESSION["role"] ?? $text["unknown"]
                ) ?>

            </span>


            <!-- PASHTO -->

            <a
                href="?lang=ps<?= $search !== ""
                    ? "&search=" . urlencode($search)
                    : ""
                ?>"
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
                href="?lang=en<?= $search !== ""
                    ? "&search=" . urlencode($search)
                    : ""
                ?>"
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
                    $text["logout"]
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

                <i class="bi bi-tags"></i>

                <?= htmlspecialchars(
                    $text["categories"]
                ) ?>

            </h2>


            <p class="text-muted mb-0">

                <?= htmlspecialchars(
                    $text["manage_categories"]
                ) ?>

            </p>

        </div>


        <a
            href="../admin/dashboard.php"
            class="btn btn-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            <?= htmlspecialchars(
                $text["dashboard"]
            ) ?>

        </a>

    </div>



    <!-- =================================================
         SUCCESS MESSAGE
    ================================================= -->

    <?php if (
        isset($_GET["success"])
    ): ?>

        <div
            class="alert alert-success
            alert-dismissible fade show"
        >

            <i class="bi bi-check-circle-fill"></i>

            <?php if (
                $_GET["success"] === "added"
            ): ?>

                <?= htmlspecialchars(
                    $text["added"]
                ) ?>

                <?php if (
                    isset($_GET["category_id"])
                ): ?>

                    <strong>

                        #

                        <?= (int)
                            $_GET["category_id"]
                        ?>

                    </strong>

                <?php endif; ?>


            <?php elseif (
                $_GET["success"] === "updated"
            ): ?>

                <?= htmlspecialchars(
                    $text["updated"]
                ) ?>


            <?php elseif (
                $_GET["success"] === "deleted"
            ): ?>

                <?= htmlspecialchars(
                    $text["deleted"]
                ) ?>

            <?php endif; ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>



    <!-- =================================================
         ERROR FROM DELETE
    ================================================= -->

    <?php if (
        isset($_GET["error"])
    ): ?>

        <div
            class="alert alert-danger
            alert-dismissible fade show"
        >

            <i
                class="bi bi-exclamation-triangle-fill"
            ></i>


            <?php if (
                $_GET["error"] === "used"
            ): ?>

                <?= htmlspecialchars(
                    $text["used"]
                ) ?>


            <?php elseif (
                $_GET["error"] === "not_found"
            ): ?>

                <?= htmlspecialchars(
                    $text["not_found"]
                ) ?>


            <?php elseif (
                $_GET["error"] === "invalid_request"
            ): ?>

                <?= htmlspecialchars(
                    $text["invalid_request"]
                ) ?>


            <?php elseif (
                $_GET["error"] === "failed"
            ): ?>

                <?= htmlspecialchars(
                    $text["delete_failed"]
                ) ?>


            <?php else: ?>

                <?= htmlspecialchars(
                    $text["error"]
                ) ?>

            <?php endif; ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>



    <!-- =================================================
         FORM ERROR
    ================================================= -->

    <?php if (
        $error !== ""
    ): ?>

        <div
            class="alert alert-danger
            alert-dismissible fade show"
        >

            <i
                class="bi bi-exclamation-triangle-fill"
            ></i>

            <?= htmlspecialchars(
                $error
            ) ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>



    <!-- =================================================
         CONTENT
    ================================================= -->

    <div class="row g-4">


        <!-- =================================================
             ADD CATEGORY
        ================================================= -->

        <div class="col-lg-4">

            <div class="card shadow-sm">


                <!-- HEADER -->

                <div
                    class="card-header
                    bg-white py-3"
                >

                    <h5 class="mb-0">

                        <i
                            class="bi bi-plus-circle"
                        ></i>

                        <?= htmlspecialchars(
                            $text["add_category"]
                        ) ?>

                    </h5>

                </div>


                <!-- BODY -->

                <div class="card-body">

                    <form
                        method="POST"
                        action="index.php"
                        autocomplete="off"
                    >


                        <!-- CATEGORY NAME -->

                        <div class="mb-3">

                            <label class="form-label">

                                <?= htmlspecialchars(
                                    $text["category_name"]
                                ) ?>

                                <span
                                    class="text-danger"
                                >
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                name="category_name"
                                class="form-control"
                                maxlength="100"
                                placeholder="<?= htmlspecialchars(
                                    $text["enter_name"]
                                ) ?>"
                                value="<?= htmlspecialchars(
                                    $category_name
                                ) ?>"
                                required
                            >

                        </div>



                        <!-- DESCRIPTION -->

                        <div class="mb-3">

                            <label class="form-label">

                                <?= htmlspecialchars(
                                    $text["description"]
                                ) ?>

                            </label>


                            <textarea
                                name="description"
                                class="form-control"
                                rows="4"
                                placeholder="<?= htmlspecialchars(
                                    $text["enter_description"]
                                ) ?>"
                            ><?= htmlspecialchars(
                                $description
                            ) ?></textarea>

                        </div>



                        <!-- SAVE -->

                        <button
                            type="submit"
                            class="btn btn-success w-100"
                        >

                            <i
                                class="bi bi-save"
                            ></i>

                            <?= htmlspecialchars(
                                $text["save_category"]
                            ) ?>

                        </button>

                    </form>

                </div>

            </div>

        </div>



        <!-- =================================================
             CATEGORY LIST
        ================================================= -->

        <div class="col-lg-8">

            <div class="card shadow-sm">


                <!-- HEADER -->

                <div
                    class="card-header
                    bg-white py-3"
                >

                    <div
                        class="d-flex
                        justify-content-between
                        align-items-center"
                    >

                        <h5 class="mb-0">

                            <i
                                class="bi bi-list-ul"
                            ></i>

                            <?= htmlspecialchars(
                                $text["category_list"]
                            ) ?>

                        </h5>


                        <span
                            class="badge bg-primary"
                        >

                            <?= count(
                                $categories
                            ) ?>

                            <?= htmlspecialchars(
                                $text["category"]
                            ) ?>

                        </span>

                    </div>

                </div>



                <!-- BODY -->

                <div class="card-body">


                    <!-- =================================================
                         SEARCH
                    ================================================= -->

                    <form
                        method="GET"
                        action="index.php"
                        class="mb-4"
                    >

                        <div class="input-group">


                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="<?= htmlspecialchars(
                                    $text["search_category"]
                                ) ?>"
                                value="<?= htmlspecialchars(
                                    $search
                                ) ?>"
                            >


                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                <i
                                    class="bi bi-search"
                                ></i>

                                <?= htmlspecialchars(
                                    $text["search"]
                                ) ?>

                            </button>


                            <?php if (
                                $search !== ""
                            ): ?>

                                <a
                                    href="index.php"
                                    class="btn btn-secondary"
                                >

                                    <i
                                        class="bi bi-x-circle"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $text["clear"]
                                    ) ?>

                                </a>

                            <?php endif; ?>


                        </div>

                    </form>



                    <!-- =================================================
                         TABLE
                    ================================================= -->

                    <div class="table-responsive">

                        <table
                            class="table
                            table-hover
                            align-middle"
                        >


                            <thead
                                class="table-light"
                            >

                                <tr>

                                    <th>#</th>

                                    <th>

                                        <?= htmlspecialchars(
                                            $text["category_name"]
                                        ) ?>

                                    </th>

                                    <th>

                                        <?= htmlspecialchars(
                                            $text["description"]
                                        ) ?>

                                    </th>

                                    <th>

                                        <?= htmlspecialchars(
                                            $text["created"]
                                        ) ?>

                                    </th>

                                    <th
                                        class="text-center"
                                    >

                                        <?= htmlspecialchars(
                                            $text["actions"]
                                        ) ?>

                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php if (
                                empty($categories)
                            ): ?>

                                <tr>

                                    <td
                                        colspan="5"
                                        class="text-center
                                        text-muted
                                        py-5"
                                    >

                                        <i
                                            class="
                                            bi
                                            bi-tags
                                            fs-1"
                                        ></i>


                                        <h5
                                            class="mt-3"
                                        >

                                            <?= htmlspecialchars(
                                                $text["no_categories"]
                                            ) ?>

                                        </h5>


                                        <p
                                            class="mb-0"
                                        >

                                            <?= htmlspecialchars(
                                                $text["add_first"]
                                            ) ?>

                                        </p>

                                    </td>

                                </tr>


                            <?php else: ?>


                                <?php foreach (
                                    $categories
                                    as $category
                                ): ?>


                                    <tr>


                                        <!-- ID -->

                                        <td>

                                            <strong>

                                                #

                                                <?= (int)
                                                    $category[
                                                        "category_id"
                                                    ] ?>

                                            </strong>

                                        </td>



                                        <!-- NAME -->

                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $category[
                                                        "category_name"
                                                    ]
                                                ) ?>

                                            </strong>

                                        </td>



                                        <!-- DESCRIPTION -->

                                        <td>

                                            <?php if (
                                                !empty(
                                                    $category[
                                                        "description"
                                                    ]
                                                )
                                            ): ?>

                                                <?= htmlspecialchars(
                                                    mb_strimwidth(
                                                        $category[
                                                            "description"
                                                        ],
                                                        0,
                                                        60,
                                                        "..."
                                                    )
                                                ) ?>

                                            <?php else: ?>

                                                <span
                                                    class="text-muted"
                                                >

                                                    N/A

                                                </span>

                                            <?php endif; ?>

                                        </td>



                                        <!-- CREATED -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $category[
                                                    "created_at"
                                                ]
                                            ) ?>

                                        </td>



                                        <!-- ACTIONS -->

                                        <td
                                            class="text-center"
                                        >

                                            <div
                                                class="btn-group"
                                                role="group"
                                            >


                                                <!-- VIEW -->

                                                <a
                                                    href="view.php?id=<?= (int)
                                                        $category[
                                                            "category_id"
                                                        ] ?>"
                                                    class="
                                                    btn
                                                    btn-sm
                                                    btn-primary"
                                                    title="<?= htmlspecialchars(
                                                        $text["view"]
                                                    ) ?>"
                                                >

                                                    <i
                                                        class="bi bi-eye"
                                                    ></i>

                                                </a>



                                                <!-- EDIT -->

                                                <a
                                                    href="edit.php?id=<?= (int)
                                                        $category[
                                                            "category_id"
                                                        ] ?>"
                                                    class="
                                                    btn
                                                    btn-sm
                                                    btn-warning"
                                                    title="<?= htmlspecialchars(
                                                        $text["edit"]
                                                    ) ?>"
                                                >

                                                    <i
                                                        class="
                                                        bi
                                                        bi-pencil-square"
                                                    ></i>

                                                </a>



                                                <!-- DELETE -->

                                                <form
                                                    action="delete.php"
                                                    method="POST"
                                                    class="action-form"
                                                    onsubmit="return confirm(
                                                        '<?= htmlspecialchars(
                                                            $text[
                                                                "confirm_delete"
                                                            ],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>'
                                                    );"
                                                >


                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)
                                                            $category[
                                                                "category_id"
                                                            ] ?>"
                                                    >


                                                    <button
                                                        type="submit"
                                                        class="
                                                        btn
                                                        btn-sm
                                                        btn-danger"
                                                        title="<?= htmlspecialchars(
                                                            $text["delete"]
                                                        ) ?>"
                                                    >

                                                        <i
                                                            class="
                                                            bi
                                                            bi-trash"
                                                        ></i>

                                                    </button>

                                                </form>


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



<!-- =====================================================
     BOOTSTRAP JS
===================================================== -->

<script
    src="../assets/js/bootstrap.bundle.min.js"
></script>


</body>

</html>

