
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

        "title" => "Add Expense",
        "description_title" =>
            "Record a new bakery expense",

        "back" => "Back",

        "information" =>
            "Expense Information",

        "expense_title" =>
            "Expense Title",

        "expense_title_placeholder" =>
            "Example: Electricity Bill",

        "amount" =>
            "Amount",

        "description" =>
            "Description",

        "description_placeholder" =>
            "Optional description...",

        "expense_date" =>
            "Expense Date",

        "cancel" =>
            "Cancel",

        "save" =>
            "Save Expense",

        "required_title" =>
            "Expense title is required.",

        "amount_error" =>
            "Amount must be greater than 0.",

        "invalid_date" =>
            "Invalid expense date.",

        "insert_error" =>
            "Failed to add expense.",

        "success" =>
            "Expense added successfully.",

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

        "title" =>
            "مصرف اضافه کول",

        "description_title" =>
            "د بیکرۍ نوی مصرف ثبت کړئ",

        "back" =>
            "بېرته",

        "information" =>
            "د لګښت معلومات",

        "expense_title" =>
            "د لګښت عنوان",

        "expense_title_placeholder" =>
            "بېلګه: د برېښنا بل",

        "amount" =>
            "مقدار",

        "description" =>
            "تشریح",

        "description_placeholder" =>
            "اختیاري تشریح...",

        "expense_date" =>
            "د لګښت نېټه",

        "cancel" =>
            "لغوه",

        "save" =>
            "مصرف ثبتول",

        "required_title" =>
            "د لګښت عنوان اړین دی.",

        "amount_error" =>
            "مقدار باید له صفر څخه زیات وي.",

        "invalid_date" =>
            "د لګښت نېټه ناسمه ده.",

        "insert_error" =>
            "د لګښت په ثبتولو کې ستونزه رامنځته شوه.",

        "success" =>
            "لګښت په بریالیتوب سره ثبت شو.",

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
   FORM VALUES
========================================================= */

$expense_title = trim(
    $_POST["expense_title"] ?? ""
);

$description = trim(
    $_POST["description"] ?? ""
);

$amountInput = trim(
    $_POST["amount"] ?? ""
);

$expense_date = trim(
    $_POST["expense_date"] ?? ""
);

$error = "";


/* =========================================================
   DEFAULT DATE
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] !== "POST"
    && $expense_date === ""
) {

    $expense_date = date(
        "Y-m-d\TH:i"
    );
}


/* =========================================================
   INSERT EXPENSE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* =====================================================
       VALIDATION
    ===================================================== */

    if ($expense_title === "") {

        $error = $t["required_title"];

    } elseif (
        $amountInput === ""
        || !is_numeric($amountInput)
        || (float)$amountInput <= 0
    ) {

        $error = $t["amount_error"];

    } else {


        /* =================================================
           DATE
        ================================================= */

        if ($expense_date === "") {

            $expense_date = date(
                "Y-m-d H:i:s"
            );

        } else {

            $timestamp = strtotime(
                $expense_date
            );


            if ($timestamp === false) {

                $error = $t["invalid_date"];

            } else {

                $expense_date = date(
                    "Y-m-d H:i:s",
                    $timestamp
                );
            }
        }
    }


    /* =====================================================
       DATABASE INSERT
    ===================================================== */

    if ($error === "") {

        try {

            $stmt = $conn->prepare("
                INSERT INTO expenses
                (
                    user_id,
                    expense_title,
                    description,
                    amount,
                    expense_date
                )
                VALUES
                (
                    :user_id,
                    :expense_title,
                    :description,
                    :amount,
                    :expense_date
                )
            ");


            $stmt->execute([

                ":user_id" =>
                    $_SESSION["user_id"],

                ":expense_title" =>
                    $expense_title,

                ":description" =>
                    $description,

                ":amount" =>
                    (float)$amountInput,

                ":expense_date" =>
                    $expense_date

            ]);


            /* =================================================
               POST → INSERT → REDIRECT → GET

               Refresh به نوی Expense نه جوړوي.
            ================================================= */

            header(
                "Location: index.php?success="
                . urlencode($t["success"])
            );

            exit;


        } catch (PDOException $e) {

            $error = $t["insert_error"];
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
            $t["title"]
        ) ?>

        -

        <?= htmlspecialchars(
            $t["system"]
        ) ?>

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

        [dir="rtl"] .form-label,
        [dir="rtl"] .card-header,
        [dir="rtl"] .navbar,
        [dir="rtl"] .alert {
            text-align: right;
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
                $t["system"]
            ) ?>

        </a>


        <!-- USER -->

        <div class="d-flex align-items-center">


            <span class="me-3">

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars(
                    $_SESSION["full_name"]
                    ?? "User"
                ) ?>

            </span>


            <span class="badge bg-primary me-3">

                <?= htmlspecialchars(
                    $_SESSION["role"]
                    ?? "User"
                ) ?>

            </span>


            <!-- LANGUAGE -->

            <a
                href="?lang=<?= $isPashto
                    ? "en"
                    : "ps"
                ?>"
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
        mb-4"
    >


        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-cash-stack"></i>

                <?= htmlspecialchars(
                    $t["title"]
                ) ?>

            </h2>


            <p class="text-muted mb-0">

                <?= htmlspecialchars(
                    $t["description_title"]
                ) ?>

            </p>

        </div>


        <!-- BACK -->

        <a
            href="index.php"
            class="btn btn-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            <?= htmlspecialchars(
                $t["back"]
            ) ?>

        </a>

    </div>


    <!-- =================================================
         ERROR
    ================================================== -->

    <?php if ($error !== ""): ?>

        <div class="alert alert-danger">

            <i
                class="bi
                bi-exclamation-triangle-fill"
            ></i>

            <?= htmlspecialchars(
                $error
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         CARD
    ================================================== -->

    <div class="card shadow-sm">


        <!-- CARD HEADER -->

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">

                <i class="bi bi-plus-circle"></i>

                <?= htmlspecialchars(
                    $t["information"]
                ) ?>

            </h5>

        </div>


        <!-- CARD BODY -->

        <div class="card-body">


            <form
                method="POST"
                autocomplete="off"
            >


                <div class="row g-3">


                    <!-- TITLE -->

                    <div class="col-md-8">

                        <label class="form-label">

                            <?= htmlspecialchars(
                                $t["expense_title"]
                            ) ?>

                            <span class="text-danger">
                                *
                            </span>

                        </label>


                        <input
                            type="text"
                            name="expense_title"
                            class="form-control"
                            placeholder="<?= htmlspecialchars(
                                $t["expense_title_placeholder"]
                            ) ?>"
                            value="<?= htmlspecialchars(
                                $expense_title
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- AMOUNT -->

                    <div class="col-md-4">

                        <label class="form-label">

                            <?= htmlspecialchars(
                                $t["amount"]
                            ) ?>

                            <span class="text-danger">
                                *
                            </span>

                        </label>


                        <div class="input-group">

                            <span class="input-group-text">
                                $
                            </span>


                            <input
                                type="number"
                                name="amount"
                                class="form-control"
                                min="0.01"
                                step="0.01"
                                placeholder="0.00"
                                value="<?= htmlspecialchars(
                                    $amountInput
                                ) ?>"
                                required
                            >

                        </div>

                    </div>


                    <!-- DESCRIPTION -->

                    <div class="col-12">

                        <label class="form-label">

                            <?= htmlspecialchars(
                                $t["description"]
                            ) ?>

                        </label>


                        <textarea
                            name="description"
                            class="form-control"
                            rows="4"
                            placeholder="<?= htmlspecialchars(
                                $t["description_placeholder"]
                            ) ?>"
                        ><?= htmlspecialchars(
                            $description
                        ) ?></textarea>

                    </div>


                    <!-- DATE -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= htmlspecialchars(
                                $t["expense_date"]
                            ) ?>

                        </label>


                        <input
                            type="datetime-local"
                            name="expense_date"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $expense_date
                            ) ?>"
                        >

                    </div>

                </div>


                <hr class="my-4">


                <!-- BUTTONS -->

                <div
                    class="d-flex
                    justify-content-end
                    gap-2"
                >


                    <!-- CANCEL -->

                    <a
                        href="index.php"
                        class="btn btn-secondary"
                    >

                        <i class="bi bi-x-circle"></i>

                        <?= htmlspecialchars(
                            $t["cancel"]
                        ) ?>

                    </a>


                    <!-- SAVE -->

                    <button
                        type="submit"
                        class="btn btn-success"
                    >

                        <i class="bi bi-check-circle"></i>

                        <?= htmlspecialchars(
                            $t["save"]
                        ) ?>

                    </button>

                </div>


            </form>

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

