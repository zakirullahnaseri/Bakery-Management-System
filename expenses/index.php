```php
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
            e.*,
            u.full_name AS user_name
        FROM expenses e
        LEFT JOIN users u
            ON e.user_id = u.user_id
        WHERE e.expense_title LIKE :search
           OR e.description LIKE :search
           OR u.full_name LIKE :search
        ORDER BY e.expense_id DESC
    ");

    $stmt->execute([
        ":search" => "%$search%"
    ]);

} else {

    $stmt = $conn->query("
        SELECT
            e.*,
            u.full_name AS user_name
        FROM expenses e
        LEFT JOIN users u
            ON e.user_id = u.user_id
        ORDER BY e.expense_id DESC
    ");
}

$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ===============================
   TOTAL
================================ */

$totalStmt = $conn->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM expenses
");

$totalExpenses = (float) $totalStmt->fetchColumn();

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
            ? "مصارف - د بیکري مدیریت"
            : "Expenses - Bakery Management"
        ?>
    </title>


    <!-- Background CSS -->

    <link
        rel="stylesheet"
        href="../assets/css/backgrounds.css">


    <!-- Bootstrap -->

    <link
        rel="stylesheet"
        href="../assets/css/bootstrap.min.css">


    <!-- Bootstrap Icons -->

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

        .action-form {
            display: inline-block;
            margin: 0;
        }

    </style>

</head>


<body class="bg-expenses">


<!-- ===============================
     NAVBAR
================================ -->

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


            <!-- Language -->

            <a
                href="?lang=ps<?= $search !== ""
                    ? "&search=" . urlencode($search)
                    : ""
                ?>"
                class="btn btn-sm
                <?= $isPashto
                    ? "btn-primary"
                    : "btn-outline-primary"
                ?> me-1">

                پښتو

            </a>


            <a
                href="?lang=en<?= $search !== ""
                    ? "&search=" . urlencode($search)
                    : ""
                ?>"
                class="btn btn-sm
                <?= !$isPashto
                    ? "btn-primary"
                    : "btn-outline-primary"
                ?> me-3">

                English

            </a>


            <!-- Logout -->

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



<!-- ===============================
     MAIN
================================ -->

<div class="container-fluid p-4">


    <!-- HEADER -->

    <div
        class="d-flex justify-content-between
        align-items-center mb-4">

        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-cash-stack"></i>

                <?= $isPashto
                    ? "مصارف"
                    : "Expenses"
                ?>

            </h2>


            <p class="text-muted mb-0">

                <?= $isPashto
                    ? "د بیکري مصارف مدیریت کړئ"
                    : "Manage bakery expenses"
                ?>

            </p>

        </div>


        <div>

            <!-- Dashboard -->

            <a
                href="../admin/dashboard.php"
                class="btn btn-secondary me-2">

                <i class="bi bi-arrow-left"></i>

                <?= $isPashto
                    ? "ډشبورډ"
                    : "Dashboard"
                ?>

            </a>


            <!-- Add Expense -->

            <a
                href="add.php"
                class="btn btn-primary">

                <i class="bi bi-plus-circle"></i>

                <?= $isPashto
                    ? "مصرف اضافه کول"
                    : "Add Expense"
                ?>

            </a>

        </div>

    </div>



    <!-- ===============================
         SUCCESS MESSAGE
    ================================ -->

    <?php if (isset($_GET["success"])): ?>

        <div
            class="alert alert-success
            alert-dismissible fade show">

            <i class="bi bi-check-circle-fill"></i>

            <?= htmlspecialchars(
                $_GET["success"]
            ) ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>

    <?php endif; ?>



    <!-- ===============================
         ERROR MESSAGE
    ================================ -->

    <?php if (isset($_GET["error"])): ?>

        <div
            class="alert alert-danger
            alert-dismissible fade show">

            <i class="bi bi-exclamation-triangle-fill"></i>

            <?= htmlspecialchars(
                $_GET["error"]
            ) ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>

    <?php endif; ?>



    <!-- ===============================
         SUMMARY
    ================================ -->

    <div class="row g-4 mb-4">


        <!-- Total Expenses -->

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <div
                        class="d-flex
                        justify-content-between">

                        <div>

                            <small class="text-muted">

                                <?= $isPashto
                                    ? "ټول مصارف"
                                    : "Total Expenses"
                                ?>

                            </small>


                            <h3 class="mt-2 mb-0">

                                $<?= number_format(
                                    $totalExpenses,
                                    2
                                ) ?>

                            </h3>

                        </div>


                        <i
                            class="bi bi-cash-stack
                            fs-1 text-danger">
                        </i>

                    </div>

                </div>

            </div>

        </div>



        <!-- Records -->

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <small class="text-muted">

                        <?= $isPashto
                            ? "د مصارفو ریکارډونه"
                            : "Expense Records"
                        ?>

                    </small>


                    <h3 class="mt-2 mb-0">

                        <?= count($expenses) ?>

                    </h3>

                </div>

            </div>

        </div>

    </div>



    <!-- ===============================
         EXPENSE LIST
    ================================ -->

    <div class="card shadow-sm">


        <!-- CARD HEADER -->

        <div class="card-header bg-white py-3">

            <div
                class="d-flex
                justify-content-between
                align-items-center">

                <h5 class="mb-0">

                    <i class="bi bi-list-ul"></i>

                    <?= $isPashto
                        ? "د مصارفو لېست"
                        : "Expense List"
                    ?>

                </h5>


                <span class="badge bg-danger">

                    <?= count($expenses) ?>

                    <?= $isPashto
                        ? "مصارف"
                        : "Expenses"
                    ?>

                </span>

            </div>

        </div>



        <div class="card-body">


            <!-- ===============================
                 SEARCH
            ================================ -->

            <form
                method="GET"
                class="mb-4">

                <div class="input-group">


                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="<?= $isPashto
                            ? "مصرف ولټوئ..."
                            : "Search expense..."
                        ?>"
                        value="<?= htmlspecialchars(
                            $search
                        ) ?>">


                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="bi bi-search"></i>

                        <?= $isPashto
                            ? "لټون"
                            : "Search"
                        ?>

                    </button>


                    <?php if ($search !== ""): ?>

                        <a
                            href="index.php"
                            class="btn btn-secondary">

                            <i class="bi bi-x-circle"></i>

                            <?= $isPashto
                                ? "پاکول"
                                : "Clear"
                            ?>

                        </a>

                    <?php endif; ?>


                </div>

            </form>



            <!-- ===============================
                 EMPTY STATE
            ================================ -->

            <?php if (empty($expenses)): ?>

                <div class="text-center py-5">


                    <i
                        class="bi bi-cash-stack
                        fs-1 text-muted">
                    </i>


                    <h5 class="mt-3">

                        <?= $isPashto
                            ? "هیڅ مصرف ونه موندل شو"
                            : "No Expenses Found"
                        ?>

                    </h5>


                    <p class="text-muted">

                        <?= $isPashto
                            ? "خپل لومړی مصرف اضافه کړئ."
                            : "Add your first expense."
                        ?>

                    </p>


                    <a
                        href="add.php"
                        class="btn btn-primary">

                        <i class="bi bi-plus-circle"></i>

                        <?= $isPashto
                            ? "مصرف اضافه کول"
                            : "Add Expense"
                        ?>

                    </a>

                </div>


            <?php else: ?>


                <!-- ===============================
                     TABLE
                ================================ -->

                <div class="table-responsive">

                    <table
                        class="table table-hover
                        align-middle">


                        <thead class="table-light">

                            <tr>

                                <th>#</th>

                                <th>

                                    <?= $isPashto
                                        ? "عنوان"
                                        : "Title"
                                    ?>

                                </th>

                                <th>

                                    <?= $isPashto
                                        ? "تشریح"
                                        : "Description"
                                    ?>

                                </th>

                                <th>

                                    <?= $isPashto
                                        ? "مقدار"
                                        : "Amount"
                                    ?>

                                </th>

                                <th>

                                    <?= $isPashto
                                        ? "نېټه"
                                        : "Date"
                                    ?>

                                </th>

                                <th>

                                    <?= $isPashto
                                        ? "کاروونکی"
                                        : "User"
                                    ?>

                                </th>

                                <th class="text-center">

                                    <?= $isPashto
                                        ? "عملیات"
                                        : "Actions"
                                    ?>

                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $expenses
                            as $expense
                        ): ?>


                            <tr>


                                <!-- ID -->

                                <td>

                                    <strong>

                                        #<?= (int)
                                            $expense[
                                                "expense_id"
                                            ] ?>

                                    </strong>

                                </td>



                                <!-- TITLE -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $expense[
                                                "expense_title"
                                            ]
                                        ) ?>

                                    </strong>

                                </td>



                                <!-- DESCRIPTION -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $expense[
                                                "description"
                                            ]
                                        )
                                    ): ?>

                                        <?= htmlspecialchars(
                                            mb_strimwidth(
                                                $expense[
                                                    "description"
                                                ],
                                                0,
                                                50,
                                                "..."
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        <span
                                            class="text-muted">

                                            <?= $isPashto
                                                ? "نشته"
                                                : "N/A"
                                            ?>

                                        </span>

                                    <?php endif; ?>

                                </td>



                                <!-- AMOUNT -->

                                <td>

                                    <strong
                                        class="text-danger">

                                        $<?= number_format(
                                            (float)
                                            $expense[
                                                "amount"
                                            ],
                                            2
                                        ) ?>

                                    </strong>

                                </td>



                                <!-- DATE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $expense[
                                            "expense_date"
                                        ]
                                    ) ?>

                                </td>



                                <!-- USER -->

                                <td>

                                    <?= htmlspecialchars(
                                        $expense[
                                            "user_name"
                                        ] ?? (
                                            $isPashto
                                                ? "نامعلوم"
                                                : "Unknown"
                                        )
                                    ) ?>

                                </td>



                                <!-- ===============================
                                     ACTIONS
                                ================================ -->

                                <td class="text-center">

                                    <div
                                        class="btn-group"
                                        role="group">


                                        <!-- VIEW -->

                                        <a
                                            href="view.php?id=<?= (int)
                                                $expense[
                                                    "expense_id"
                                                ] ?>"
                                            class="btn
                                            btn-sm btn-primary"
                                            title="<?= $isPashto
                                                ? "کتل"
                                                : "View"
                                            ?>">

                                            <i
                                                class="bi bi-eye">
                                            </i>

                                        </a>



                                        <!-- EDIT -->

                                        <a
                                            href="edit.php?id=<?= (int)
                                                $expense[
                                                    "expense_id"
                                                ] ?>"
                                            class="btn
                                            btn-sm btn-warning"
                                            title="<?= $isPashto
                                                ? "سمول"
                                                : "Edit"
                                            ?>">

                                            <i
                                                class="
                                                bi
                                                bi-pencil-square">
                                            </i>

                                        </a>



                                        <!-- DELETE -->

                                        <form
                                            action="delete.php"
                                            method="POST"
                                            class="action-form"
                                            onsubmit="return confirm(
                                                '<?= $isPashto
                                                    ? "ایا ډاډه یاست چې دا مصرف حذف کړئ؟"
                                                    : "Are you sure you want to delete this expense?"
                                                ?>'
                                            );">


                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)
                                                    $expense[
                                                        "expense_id"
                                                    ] ?>">


                                            <button
                                                type="submit"
                                                class="btn
                                                btn-sm btn-danger"
                                                title="<?= $isPashto
                                                    ? "حذف"
                                                    : "Delete"
                                                ?>">

                                                <i
                                                    class="bi bi-trash">
                                                </i>

                                            </button>


                                        </form>


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



<!-- ===============================
     JAVASCRIPT
================================ -->

<script
    src="../assets/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
```
