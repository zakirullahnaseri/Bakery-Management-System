
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

        "title" => "Edit Expense",
        "expense_no" => "Expense #",
        "back" => "Back",
        "information" => "Expense Information",

        "expense_title" => "Expense Title",
        "amount" => "Amount",
        "description" => "Description",
        "expense_date" => "Expense Date",

        "cancel" => "Cancel",
        "update" => "Update Expense",

        "required_title" =>
            "Expense title is required.",

        "amount_error" =>
            "Amount must be greater than 0.",

        "date_error" =>
            "Expense date is required.",

        "invalid_date" =>
            "Invalid expense date.",

        "update_error" =>
            "Failed to update expense.",

        "success" =>
            "Expense updated successfully.",

        "invalid_id" =>
            "Invalid expense ID.",

        "not_found" =>
            "Expense not found.",

        "dashboard" =>
            "Dashboard",

        "logout" =>
            "Logout",

        "language" =>
            "پښتو",

        "system" =>
            "Bakery System",

        "edit_description" =>
            "Update expense information"

    ],

    "ps" => [

        "title" => "د لګښت سمون",
        "expense_no" => "د لګښت شمېره #",
        "back" => "بېرته",
        "information" => "د لګښت معلومات",

        "expense_title" => "د لګښت عنوان",
        "amount" => "مقدار",
        "description" => "تشریح",
        "expense_date" => "د لګښت نېټه",

        "cancel" => "لغوه",
        "update" => "لګښت تازه کول",

        "required_title" =>
            "د لګښت عنوان اړین دی.",

        "amount_error" =>
            "مقدار باید له صفر څخه زیات وي.",

        "date_error" =>
            "د لګښت نېټه اړینه ده.",

        "invalid_date" =>
            "د لګښت نېټه ناسمه ده.",

        "update_error" =>
            "د لګښت په تازه کولو کې ستونزه رامنځته شوه.",

        "success" =>
            "لګښت په بریالیتوب سره تازه شو.",

        "invalid_id" =>
            "د لګښت ID ناسم دی.",

        "not_found" =>
            "لګښت پیدا نه شو.",

        "dashboard" =>
            "ډشبورډ",

        "logout" =>
            "وتل",

        "language" =>
            "English",

        "system" =>
            "د بیکرۍ سیسټم",

        "edit_description" =>
            "د لګښت معلومات تازه کړئ"
    ]
];

$lang = $isPashto ? "ps" : "en";
$t = $text[$lang];


/* =========================================================
   GET EXPENSE ID
========================================================= */

$id = (int) (
    $_GET["id"]
    ?? $_POST["expense_id"]
    ?? 0
);


if ($id <= 0) {

    header(
        "Location: index.php?error="
        . urlencode($t["invalid_id"])
    );

    exit;
}


/* =========================================================
   GET EXPENSE
========================================================= */

$stmt = $conn->prepare("
    SELECT *
    FROM expenses
    WHERE expense_id = :id
    LIMIT 1
");

$stmt->execute([
    ":id" => $id
]);

$expense = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$expense) {

    header(
        "Location: index.php?error="
        . urlencode($t["not_found"])
    );

    exit;
}


$error = "";


/* =========================================================
   UPDATE EXPENSE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

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

    } elseif ($expense_date === "") {

        $error = $t["date_error"];

    } else {

        $timestamp = strtotime($expense_date);

        if ($timestamp === false) {

            $error = $t["invalid_date"];

        } else {

            $expense_date = date(
                "Y-m-d H:i:s",
                $timestamp
            );
        }
    }


    /* =====================================================
       UPDATE DATABASE
    ===================================================== */

    if ($error === "") {

        try {

            $updateStmt = $conn->prepare("
                UPDATE expenses
                SET
                    expense_title = :expense_title,
                    description = :description,
                    amount = :amount,
                    expense_date = :expense_date
                WHERE expense_id = :id
            ");

            $updateStmt->execute([

                ":expense_title" =>
                    $expense_title,

                ":description" =>
                    $description,

                ":amount" =>
                    (float)$amountInput,

                ":expense_date" =>
                    $expense_date,

                ":id" =>
                    $id
            ]);


            /* =============================================
               POST → UPDATE → REDIRECT → GET

               Refresh به بیا UPDATE نه تکراروي
            ============================================= */

            header(
                "Location: index.php?success="
                . urlencode($t["success"])
            );

            exit;


        } catch (PDOException $e) {

            $error = $t["update_error"];
        }
    }


    /* =====================================================
       KEEP FORM VALUES AFTER ERROR
    ===================================================== */

    $expense["expense_title"] =
        $expense_title;

    $expense["description"] =
        $description;

    $expense["amount"] =
        $amountInput;

    $expense["expense_date"] =
        $expense_date;
}


/* =========================================================
   DATETIME LOCAL VALUE
========================================================= */

$dateValue = "";

if (!empty($expense["expense_date"])) {

    $timestamp = strtotime(
        $expense["expense_date"]
    );

    if ($timestamp !== false) {

        $dateValue = date(
            "Y-m-d\TH:i",
            $timestamp
        );
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
        <?= htmlspecialchars($t["title"]) ?>
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

            <?= htmlspecialchars($t["system"]) ?>

        </a>


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

                <?= htmlspecialchars($t["language"]) ?>

            </a>


            <!-- LOGOUT -->

            <a
                href="../public/logout.php"
                class="btn btn-outline-danger btn-sm"
            >

                <i class="bi bi-box-arrow-right"></i>

                <?= htmlspecialchars($t["logout"]) ?>

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

                <i class="bi bi-pencil-square"></i>

                <?= htmlspecialchars($t["title"]) ?>

            </h2>


            <p class="text-muted mb-0">

                <?= htmlspecialchars(
                    $t["expense_no"]
                ) ?>

                <?= $id ?>

            </p>

        </div>


        <a
            href="index.php"
            class="btn btn-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            <?= htmlspecialchars($t["back"]) ?>

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

                <i class="bi bi-cash-stack"></i>

                <?= htmlspecialchars(
                    $t["information"]
                ) ?>

            </h5>

        </div>


        <div class="card-body">


            <form method="POST">


                <!-- EXPENSE ID -->

                <input
                    type="hidden"
                    name="expense_id"
                    value="<?= $id ?>"
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
                            value="<?= htmlspecialchars(
                                $expense["expense_title"] ?? ""
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
                                value="<?= htmlspecialchars(
                                    $expense["amount"] ?? ""
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
                        ><?= htmlspecialchars(
                            $expense["description"] ?? ""
                        ) ?></textarea>

                    </div>


                    <!-- DATE -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= htmlspecialchars(
                                $t["expense_date"]
                            ) ?>

                            <span class="text-danger">
                                *
                            </span>

                        </label>


                        <input
                            type="datetime-local"
                            name="expense_date"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $dateValue
                            ) ?>"
                            required
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

                    <a
                        href="index.php"
                        class="btn btn-secondary"
                    >

                        <i class="bi bi-x-circle"></i>

                        <?= htmlspecialchars(
                            $t["cancel"]
                        ) ?>

                    </a>


                    <button
                        type="submit"
                        class="btn btn-success"
                    >

                        <i class="bi bi-save"></i>

                        <?= htmlspecialchars(
                            $t["update"]
                        ) ?>

                    </button>

                </div>


            </form>

        </div>

    </div>

</div>


<!-- JavaScript -->

<script
    src="../assets/js/bootstrap.bundle.min.js"
></script>


</body>

</html>

