
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";

$search = trim($_GET["search"] ?? "");
$from   = $_GET["from"] ?? "";
$to     = $_GET["to"] ?? "";

$sql = "
    SELECT
        p.*,
        c.name AS customer_name,
        s.name AS supplier_name
    FROM payments p
    LEFT JOIN customers c
        ON p.customer_id = c.customer_id
    LEFT JOIN suppliers s
        ON p.supplier_id = s.supplier_id
    WHERE 1=1
";

$params = [];

if ($search !== "") {
    $sql .= "
        AND (
            c.name LIKE :search
            OR s.name LIKE :search
            OR p.payment_method LIKE :search
            OR p.notes LIKE :search
        )
    ";

    $params[":search"] = "%$search%";
}

if ($from !== "" && preg_match("/^\d{4}-\d{2}-\d{2}$/", $from)) {
    $sql .= " AND DATE(p.payment_date) >= :from ";
    $params[":from"] = $from;
}

if ($to !== "" && preg_match("/^\d{4}-\d{2}-\d{2}$/", $to)) {
    $sql .= " AND DATE(p.payment_date) <= :to ";
    $params[":to"] = $to;
}

$sql .= " ORDER BY p.payment_id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

function money($amount)
{
    return "$" . number_format((float)$amount, 2);
}

?>

<!DOCTYPE html>
<html
    lang="<?= currentLanguage() ?>"
    dir="<?= languageDirection() ?>"
>

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= $isPashto ? "تادیات - بیکري مدیریت" : "Payments - Bakery Management" ?>
    </title>

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

        <?php if ($isPashto): ?>

        body {
            text-align: right;
        }

        .navbar-brand {
            margin-right: 0;
        }

        .me-3 {
            margin-right: 0 !important;
            margin-left: 1rem !important;
        }

        .me-2 {
            margin-right: 0 !important;
            margin-left: .5rem !important;
        }

        <?php endif; ?>

    </style>

</head>

<body>

<nav class="navbar">

    <div class="container-fluid px-4">

        <a
            href="../admin/dashboard.php"
            class="navbar-brand fw-bold">

            <i class="bi bi-shop"></i>

            <?= $isPashto ? "د بیکري سیستم" : "Bakery System" ?>

        </a>

        <div class="d-flex align-items-center">

            <!-- Language -->
            <div class="me-3">

                <a
                    href="?lang=ps<?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $from !== '' ? '&from=' . urlencode($from) : '' ?><?= $to !== '' ? '&to=' . urlencode($to) : '' ?>"
                    class="btn btn-sm <?= $isPashto ? 'btn-primary' : 'btn-outline-primary' ?>">

                    پښتو

                </a>

                <a
                    href="?lang=en<?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $from !== '' ? '&from=' . urlencode($from) : '' ?><?= $to !== '' ? '&to=' . urlencode($to) : '' ?>"
                    class="btn btn-sm <?= !$isPashto ? 'btn-primary' : 'btn-outline-primary' ?>">

                    English

                </a>

            </div>

            <span class="me-3">

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars($_SESSION["full_name"] ?? ($isPashto ? "کارن" : "User")) ?>

            </span>

            <span class="badge bg-primary me-3">

                <?= htmlspecialchars($_SESSION["role"] ?? ($isPashto ? "کارن" : "User")) ?>

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

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-cash-stack"></i>

                <?= $isPashto ? "تادیات" : "Payments" ?>

            </h2>

            <p class="text-muted mb-0">

                <?= $isPashto
                    ? "د مشتریانو او عرضه کوونکو تادیات مدیریت کړئ"
                    : "Manage customer and supplier payments"
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

                <?= $isPashto ? "تادیه اضافه کړئ" : "Add Payment" ?>

            </a>

        </div>

    </div>

    <?php if (isset($_GET["success"])): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <i class="bi bi-check-circle"></i>

            <?= htmlspecialchars($_GET["success"]) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>

    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <i class="bi bi-exclamation-triangle"></i>

            <?= htmlspecialchars($_GET["error"]) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>

    <?php endif; ?>

    <div class="card shadow-sm">

        <div class="card-header bg-white py-3">

            <div class="d-flex justify-content-between">

                <h5 class="mb-0">

                    <i class="bi bi-list-ul"></i>

                    <?= $isPashto ? "د تادیاتو لست" : "Payment List" ?>

                </h5>

                <span class="badge bg-primary">

                    <?= count($payments) ?>

                    <?= $isPashto ? "تادیات" : "Payments" ?>

                </span>

            </div>

        </div>

        <div class="card-body">

            <form method="GET" class="mb-4">

                <div class="row g-2">

                    <div class="col-md-5">

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="<?= $isPashto
                                ? 'د مشتری، عرضه کوونکي یا تادیې لټون...'
                                : 'Search customer, supplier...'
                            ?>"
                            value="<?= htmlspecialchars($search) ?>">

                    </div>

                    <div class="col-md-2">

                        <input
                            type="date"
                            name="from"
                            class="form-control"
                            value="<?= htmlspecialchars($from) ?>">

                    </div>

                    <div class="col-md-2">

                        <input
                            type="date"
                            name="to"
                            class="form-control"
                            value="<?= htmlspecialchars($to) ?>">

                    </div>

                    <div class="col-md-3">

                        <button class="btn btn-primary">

                            <i class="bi bi-search"></i>

                            <?= $isPashto ? "لټون" : "Search" ?>

                        </button>

                        <a
                            href="index.php"
                            class="btn btn-secondary">

                            <i class="bi bi-x-circle"></i>

                            <?= $isPashto ? "پاکول" : "Clear" ?>

                        </a>

                    </div>

                </div>

            </form>

            <?php if (empty($payments)): ?>

                <div class="text-center py-5">

                    <i class="bi bi-cash-stack fs-1 text-muted"></i>

                    <h5 class="mt-3">

                        <?= $isPashto
                            ? "هیڅ تادیه ونه موندل شوه"
                            : "No Payments Found"
                        ?>

                    </h5>

                    <a
                        href="add.php"
                        class="btn btn-primary">

                        <i class="bi bi-plus-circle"></i>

                        <?= $isPashto
                            ? "تادیه اضافه کړئ"
                            : "Add Payment"
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
                                    <?= $isPashto ? "ډول" : "Type" ?>
                                </th>

                                <th>
                                    <?= $isPashto
                                        ? "مشتری / عرضه کوونکی"
                                        : "Customer / Supplier"
                                    ?>
                                </th>

                                <th>
                                    <?= $isPashto ? "مقدار" : "Amount" ?>
                                </th>

                                <th>
                                    <?= $isPashto ? "طریقه" : "Method" ?>
                                </th>

                                <th>
                                    <?= $isPashto ? "نېټه" : "Date" ?>
                                </th>

                                <th>
                                    <?= $isPashto ? "یادښت" : "Notes" ?>
                                </th>

                                <th class="text-center">
                                    <?= $isPashto ? "عملیات" : "Actions" ?>
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($payments as $payment): ?>

                            <?php

                            if (!empty($payment["customer_id"])) {

                                $type = $isPashto
                                    ? "مشتری"
                                    : "Customer";

                                $person = $payment["customer_name"];

                                $badge = "success";

                            } elseif (!empty($payment["supplier_id"])) {

                                $type = $isPashto
                                    ? "عرضه کوونکی"
                                    : "Supplier";

                                $person = $payment["supplier_name"];

                                $badge = "warning";

                            } else {

                                $type = $isPashto
                                    ? "نور"
                                    : "Other";

                                $person = $isPashto
                                    ? "نشته"
                                    : "N/A";

                                $badge = "secondary";
                            }

                            ?>

                            <tr>

                                <td>

                                    <strong>
                                        #<?= (int)$payment["payment_id"] ?>
                                    </strong>

                                </td>

                                <td>

                                    <span class="badge bg-<?= $badge ?>">

                                        <?= $type ?>

                                    </span>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $person ?? ($isPashto ? "نشته" : "N/A")
                                    ) ?>

                                </td>

                                <td class="fw-bold text-success">

                                    <?= money($payment["amount"]) ?>

                                </td>

                                <td>

                                    <span class="badge bg-secondary">

                                        <?php

                                        $method = $payment["payment_method"] ?? "";

                                        if ($method === "cash") {

                                            echo $isPashto
                                                ? "نغدې"
                                                : "Cash";

                                        } elseif ($method === "card") {

                                            echo $isPashto
                                                ? "کارت"
                                                : "Card";

                                        } elseif ($method === "bank") {

                                            echo $isPashto
                                                ? "بانک"
                                                : "Bank";

                                        } else {

                                            echo htmlspecialchars(
                                                ucfirst($method)
                                            );
                                        }

                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $payment["payment_date"]
                                    ) ?>

                                </td>

                                <td>

                                    <?= !empty($payment["notes"])

                                        ? htmlspecialchars($payment["notes"])

                                        : '<span class="text-muted">'
                                            . ($isPashto ? "نشته" : "N/A")
                                            . '</span>'
                                    ?>

                                </td>

                                <td class="text-center">

                                    <div class="btn-group">

                                        <a
                                            href="view.php?id=<?= (int)$payment["payment_id"] ?>"
                                            class="btn btn-sm btn-primary"
                                            title="<?= $isPashto ? 'کتل' : 'View' ?>">

                                            <i class="bi bi-eye"></i>

                                        </a>

                                        <a
                                            href="edit.php?id=<?= (int)$payment["payment_id"] ?>"
                                            class="btn btn-sm btn-warning"
                                            title="<?= $isPashto ? 'سمول' : 'Edit' ?>">

                                            <i class="bi bi-pencil-square"></i>

                                        </a>

                                        <a
                                            href="delete.php?id=<?= (int)$payment["payment_id"] ?>"
                                            class="btn btn-sm btn-danger"
                                            title="<?= $isPashto ? 'حذف کول' : 'Delete' ?>"
                                            onclick="return confirm('<?= $isPashto
                                                ? 'ایا ډاډه یاست چې دا تادیه حذف کول غواړئ؟'
                                                : 'Are you sure you want to delete this payment?'
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

<!-- Bootstrap JavaScript -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>

</body>
</html>
