
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";


/* =========================================================
   LANGUAGE
========================================================= */

$isPashto = currentLanguage() === "ps";


/* =========================================================
   TRANSLATIONS
========================================================= */

$text = [

    "en" => [

        "title" => "Expense Details",
        "expense_no" => "Expense #",

        "back" => "Back",
        "edit" => "Edit",
        "print" => "Print",

        "information" => "Expense Information",

        "expense_title" => "Expense Title",
        "amount" => "Amount",
        "expense_date" => "Expense Date",
        "recorded_by" => "Recorded By",
        "description" => "Description",
        "expense_id" => "Expense ID",

        "no_description" =>
            "No description provided.",

        "unknown" =>
            "Unknown",

        "dashboard" =>
            "Dashboard",

        "logout" =>
            "Logout",

        "language" =>
            "پښتو",

        "system" =>
            "Bakery System"

    ],


    "ps" => [

        "title" => "د لګښت معلومات",
        "expense_no" => "د لګښت شمېره #",

        "back" => "بېرته",
        "edit" => "سمول",
        "print" => "چاپ",

        "information" => "د لګښت معلومات",

        "expense_title" => "د لګښت عنوان",
        "amount" => "مقدار",
        "expense_date" => "د لګښت نېټه",
        "recorded_by" => "ثبت کوونکی",
        "description" => "تشریح",
        "expense_id" => "د لګښت ID",

        "no_description" =>
            "هیڅ تشریح نه ده ورکړل شوې.",

        "unknown" =>
            "نامعلوم",

        "dashboard" =>
            "ډشبورډ",

        "logout" =>
            "وتل",

        "language" =>
            "English",

        "system" =>
            "د بیکرۍ سیسټم"

    ]

];

$lang = $isPashto ? "ps" : "en";

$t = $text[$lang];


/* =========================================================
   GET EXPENSE ID
========================================================= */

$id = (int) ($_GET["id"] ?? 0);


if ($id <= 0) {

    header(
        "Location: index.php?error="
        . urlencode(
            $isPashto
                ? "د لګښت ID ناسم دی."
                : "Invalid expense ID."
        )
    );

    exit;
}


/* =========================================================
   GET EXPENSE
========================================================= */

$stmt = $conn->prepare("
    SELECT
        e.*,
        u.full_name AS user_name
    FROM expenses e
    LEFT JOIN users u
        ON e.user_id = u.user_id
    WHERE e.expense_id = :id
    LIMIT 1
");

$stmt->execute([
    ":id" => $id
]);

$expense = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$expense) {

    header(
        "Location: index.php?error="
        . urlencode(
            $isPashto
                ? "لګښت پیدا نه شو."
                : "Expense not found."
        )
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

        <?= htmlspecialchars($t["title"]) ?>

        -

        <?= htmlspecialchars($t["system"]) ?>

    </title>


    <!-- Background CSS -->

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

        .info-label {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .info-value {
            font-weight: 600;
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

        [dir="rtl"] body {
            font-family:
                Tahoma,
                Arial,
                sans-serif;
        }

        [dir="rtl"] .card-header,
        [dir="rtl"] .alert {
            text-align: right;
        }

        @media print {

            .no-print {
                display: none !important;
            }

            body {
                background: white;
            }

            .card {
                box-shadow: none !important;
                border: 1px solid #ddd;
            }

        }

    </style>

</head>


<body class="bg-expenses">


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar no-print">

    <div class="container-fluid px-4">


        <!-- SYSTEM -->

        <a
            href="../admin/dashboard.php"
            class="navbar-brand fw-bold"
        >

            <i class="bi bi-shop"></i>

            <?= htmlspecialchars($t["system"]) ?>

        </a>


        <!-- USER -->

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


            <!-- LANGUAGE -->

            <a
                href="?id=<?= $id ?>&lang=<?= $isPashto ? "en" : "ps" ?>"
                class="btn btn-outline-primary btn-sm me-2"
            >

                <i class="bi bi-translate"></i>

                <?= htmlspecialchars(
                    $t["language"]
                ) ?>

            </a>


            <!-- LOGOUT -->

            <a
                href="../public/logout.php"
                class="btn btn-outline-danger btn-sm"
            >

                <i class="bi bi-box-arrow-right"></i>

                <?= htmlspecialchars(
                    $t["logout"]
                ) ?>

            </a>

        </div>

    </div>

</nav>


<!-- =====================================================
     CONTENT
===================================================== -->

<div class="container py-4">


    <!-- HEADER -->

    <div
        class="d-flex
        justify-content-between
        align-items-center
        mb-4
        no-print"
    >


        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-receipt"></i>

                <?= htmlspecialchars(
                    $t["title"]
                ) ?>

            </h2>


            <p class="text-muted mb-0">

                <?= htmlspecialchars(
                    $t["expense_no"]
                ) ?>

                <?= $id ?>

            </p>

        </div>


        <div>


            <!-- BACK -->

            <a
                href="index.php"
                class="btn btn-secondary me-2"
            >

                <i class="bi bi-arrow-left"></i>

                <?= htmlspecialchars(
                    $t["back"]
                ) ?>

            </a>


            <!-- EDIT -->

            <a
                href="edit.php?id=<?= $id ?>"
                class="btn btn-warning me-2"
            >

                <i class="bi bi-pencil-square"></i>

                <?= htmlspecialchars(
                    $t["edit"]
                ) ?>

            </a>


            <!-- PRINT -->

            <button
                type="button"
                onclick="window.print()"
                class="btn btn-primary"
            >

                <i class="bi bi-printer"></i>

                <?= htmlspecialchars(
                    $t["print"]
                ) ?>

            </button>

        </div>

    </div>


    <!-- =================================================
         EXPENSE CARD
    ================================================== -->

    <div class="card shadow-sm">


        <!-- CARD HEADER -->

        <div class="card-header bg-white py-3">


            <div
                class="d-flex
                justify-content-between
                align-items-center"
            >


                <h5 class="mb-0">

                    <i class="bi bi-cash-stack"></i>

                    <?= htmlspecialchars(
                        $t["information"]
                    ) ?>

                </h5>


                <span class="badge bg-danger">

                    <?= htmlspecialchars(
                        $t["expense_no"]
                    ) ?>

                    <?= $id ?>

                </span>

            </div>

        </div>


        <!-- CARD BODY -->

        <div class="card-body">


            <div class="row g-4">


                <!-- TITLE -->

                <div class="col-md-6">

                    <div class="info-label">

                        <?= htmlspecialchars(
                            $t["expense_title"]
                        ) ?>

                    </div>


                    <div class="info-value fs-5">

                        <?= htmlspecialchars(
                            $expense["expense_title"]
                        ) ?>

                    </div>

                </div>


                <!-- AMOUNT -->

                <div class="col-md-6">

                    <div class="info-label">

                        <?= htmlspecialchars(
                            $t["amount"]
                        ) ?>

                    </div>


                    <div
                        class="info-value
                        fs-4
                        text-danger"
                    >

                        $<?= number_format(
                            (float)
                            $expense["amount"],
                            2
                        ) ?>

                    </div>

                </div>


                <!-- DATE -->

                <div class="col-md-6">

                    <div class="info-label">

                        <?= htmlspecialchars(
                            $t["expense_date"]
                        ) ?>

                    </div>


                    <div class="info-value">

                        <?= htmlspecialchars(
                            $expense["expense_date"]
                        ) ?>

                    </div>

                </div>


                <!-- USER -->

                <div class="col-md-6">

                    <div class="info-label">

                        <?= htmlspecialchars(
                            $t["recorded_by"]
                        ) ?>

                    </div>


                    <div class="info-value">

                        <?= htmlspecialchars(
                            $expense["user_name"]
                            ?? $t["unknown"]
                        ) ?>

                    </div>

                </div>


                <!-- DESCRIPTION -->

                <div class="col-12">

                    <div class="info-label mb-2">

                        <?= htmlspecialchars(
                            $t["description"]
                        ) ?>

                    </div>


                    <div class="p-3 bg-light rounded">


                        <?php if (
                            !empty(
                                $expense["description"]
                            )
                        ): ?>


                            <?= nl2br(
                                htmlspecialchars(
                                    $expense[
                                        "description"
                                    ]
                                )
                            ) ?>


                        <?php else: ?>


                            <span
                                class="text-muted"
                            >

                                <?= htmlspecialchars(
                                    $t["no_description"]
                                ) ?>

                            </span>


                        <?php endif; ?>


                    </div>

                </div>


                <!-- EXPENSE ID -->

                <div class="col-12">

                    <div class="info-label">

                        <?= htmlspecialchars(
                            $t["expense_id"]
                        ) ?>

                    </div>


                    <div class="info-value">

                        #<?= (int)
                            $expense[
                                "expense_id"
                            ] ?>

                    </div>

                </div>


            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script
    src="../assets/js/bootstrap.bundle.min.js"
></script>


</body>

</html>

