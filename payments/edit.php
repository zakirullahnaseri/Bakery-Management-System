<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";

$id = (int)($_GET["id"] ?? $_POST["payment_id"] ?? 0);

if ($id <= 0) {
    $msg = $isPashto ? "د تادیې ID ناسم دی." : "Invalid payment ID.";
    header("Location: index.php?error=" . urlencode($msg));
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM payments
    WHERE payment_id = ?
");

$stmt->execute([$id]);

$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    $msg = $isPashto ? "تادیه پیدا نه شوه." : "Payment not found.";
    header("Location: index.php?error=" . urlencode($msg));
    exit;
}

$customers = $conn->query("
    SELECT customer_id, name, phone
    FROM customers
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$suppliers = $conn->query("
    SELECT supplier_id, name, phone
    FROM suppliers
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $amount = (float)($_POST["amount"] ?? 0);

    $payment_method = $_POST["payment_method"] ?? "cash";

    $payment_date = $_POST["payment_date"] ?? "";

    $notes = trim($_POST["notes"] ?? "");

    if ($amount <= 0) {

        $error = $isPashto
            ? "د تادیې اندازه باید له ۰ څخه زیاته وي."
            : "Payment amount must be greater than 0.";

    } elseif (!in_array(
        $payment_method,
        ["cash", "card", "bank"],
        true
    )) {

        $error = $isPashto
            ? "د تادیې طریقه ناسم ده."
            : "Invalid payment method.";

    } elseif (
        !preg_match(
            "/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/",
            $payment_date
        )
    ) {

        $error = $isPashto
            ? "د تادیې نېټه ناسم ده."
            : "Invalid payment date.";

    } else {

        try {

            $conn->beginTransaction();

            $stmt = $conn->prepare("
                UPDATE payments
                SET amount = ?,
                    payment_method = ?,
                    payment_date = ?,
                    notes = ?
                WHERE payment_id = ?
            ");

            $stmt->execute([
                $amount,
                $payment_method,
                str_replace("T", " ", $payment_date),
                $notes,
                $id
            ]);

            /*
             * Recalculate related sale
             */

            if (!empty($payment["sale_id"])) {

                $sale_id = (int)$payment["sale_id"];

                $stmt = $conn->prepare("
                    SELECT total_amount
                    FROM sales
                    WHERE sale_id = ?
                ");

                $stmt->execute([$sale_id]);

                $sale = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($sale) {

                    $stmt = $conn->prepare("
                        SELECT COALESCE(SUM(amount), 0)
                        FROM payments
                        WHERE sale_id = ?
                    ");

                    $stmt->execute([$sale_id]);

                    $paid = (float)$stmt->fetchColumn();

                    $total = (float)$sale["total_amount"];

                    $due = max(0, $total - $paid);

                    if ($paid >= $total) {
                        $status = "paid";
                    } elseif ($paid > 0) {
                        $status = "partial";
                    } else {
                        $status = "unpaid";
                    }

                    $stmt = $conn->prepare("
                        UPDATE sales
                        SET paid_amount = ?,
                            due_amount = ?,
                            payment_status = ?
                        WHERE sale_id = ?
                    ");

                    $stmt->execute([
                        $paid,
                        $due,
                        $status,
                        $sale_id
                    ]);
                }
            }

            /*
             * Recalculate related purchase
             */

            if (!empty($payment["purchase_id"])) {

                $purchase_id = (int)$payment["purchase_id"];

                $stmt = $conn->prepare("
                    SELECT total_amount
                    FROM purchases
                    WHERE purchase_id = ?
                ");

                $stmt->execute([$purchase_id]);

                $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($purchase) {

                    $stmt = $conn->prepare("
                        SELECT COALESCE(SUM(amount), 0)
                        FROM payments
                        WHERE purchase_id = ?
                    ");

                    $stmt->execute([$purchase_id]);

                    $paid = (float)$stmt->fetchColumn();

                    $total = (float)$purchase["total_amount"];

                    $due = max(0, $total - $paid);

                    if ($paid >= $total) {
                        $status = "paid";
                    } elseif ($paid > 0) {
                        $status = "partial";
                    } else {
                        $status = "unpaid";
                    }

                    $stmt = $conn->prepare("
                        UPDATE purchases
                        SET paid_amount = ?,
                            due_amount = ?,
                            payment_status = ?
                        WHERE purchase_id = ?
                    ");

                    $stmt->execute([
                        $paid,
                        $due,
                        $status,
                        $purchase_id
                    ]);
                }
            }

            $conn->commit();

            header("Location: view.php?id=" . $id);
            exit;

        } catch (Throwable $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}

$currentDate = date(
    "Y-m-d\TH:i",
    strtotime($payment["payment_date"])
);

?>

<!DOCTYPE html>
<html
    lang="<?= currentLanguage() ?>"
    dir="<?= languageDirection() ?>"
>

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= $isPashto ? "د تادیې سمول" : "Edit Payment" ?>
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
            background: #f8f9fa;
        }

        [dir="rtl"] {
            text-align: right;
        }

        [dir="rtl"] .form-select,
        [dir="rtl"] .form-control {
            text-align: right;
        }

        .language-switch {
            position: absolute;
            top: 20px;
            left: 20px;
        }

        [dir="rtl"] .language-switch {
            left: auto;
            right: 20px;
        }
    </style>

</head>

<body>

<div class="container py-5">

    <!-- Language Switch -->
    <div class="language-switch">

        <a
            href="?id=<?= $id ?>&lang=ps"
            class="btn btn-sm <?= $isPashto ? "btn-primary" : "btn-outline-primary" ?>">

            پښتو

        </a>

        <a
            href="?id=<?= $id ?>&lang=en"
            class="btn btn-sm <?= !$isPashto ? "btn-primary" : "btn-outline-primary" ?>">

            English

        </a>

    </div>

    <div class="card shadow-sm border-0">

        <div class="card-header bg-white">

            <h4 class="mb-0">

                <i class="bi bi-pencil-square"></i>

                <?= $isPashto
                    ? "د تادیې سمول #"
                    : "Edit Payment #" ?>

                <?= $id ?>

            </h4>

        </div>

        <div class="card-body">

            <?php if ($error): ?>

                <div class="alert alert-danger">

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>

            <div class="alert alert-info">

                <i class="bi bi-info-circle"></i>

                <?= $isPashto
                    ? "د مشتری/عرضه کوونکي او خرڅلاو/پېرود اړیکې د تادیې له جوړېدو وروسته نه شي بدلیدای."
                    : "Customer/Supplier and Sale/Purchase links cannot be changed after the payment is created." ?>

            </div>

            <form method="POST">

                <input
                    type="hidden"
                    name="payment_id"
                    value="<?= $id ?>">

                <div class="row g-3">

                    <!-- Customer -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= $isPashto
                                ? "مشتری"
                                : "Customer" ?>

                        </label>

                        <select class="form-select" disabled>

                            <option>

                                <?=
                                !empty($payment["customer_id"])
                                    ? (
                                        $isPashto
                                            ? "مشتری #"
                                            : "Customer #"
                                      )
                                      . (int)$payment["customer_id"]
                                    : (
                                        $isPashto
                                            ? "-- نشته --"
                                            : "-- None --"
                                      )
                                ?>

                            </option>

                        </select>

                    </div>

                    <!-- Supplier -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= $isPashto
                                ? "عرضه کوونکی"
                                : "Supplier" ?>

                        </label>

                        <select class="form-select" disabled>

                            <option>

                                <?=
                                !empty($payment["supplier_id"])
                                    ? (
                                        $isPashto
                                            ? "عرضه کوونکی #"
                                            : "Supplier #"
                                      )
                                      . (int)$payment["supplier_id"]
                                    : (
                                        $isPashto
                                            ? "-- نشته --"
                                            : "-- None --"
                                      )
                                ?>

                            </option>

                        </select>

                    </div>

                    <!-- Amount -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= $isPashto
                                ? "اندازه"
                                : "Amount" ?>

                        </label>

                        <input
                            type="number"
                            name="amount"
                            class="form-control"
                            step="0.01"
                            min="0.01"
                            value="<?= htmlspecialchars(
                                $payment["amount"]
                            ) ?>"
                            required>

                    </div>

                    <!-- Payment Method -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= $isPashto
                                ? "د تادیې طریقه"
                                : "Payment Method" ?>

                        </label>

                        <select
                            name="payment_method"
                            class="form-select">

                            <option
                                value="cash"
                                <?= $payment["payment_method"] === "cash"
                                    ? "selected"
                                    : "" ?>>

                                <?= $isPashto
                                    ? "نغدې"
                                    : "Cash" ?>

                            </option>

                            <option
                                value="card"
                                <?= $payment["payment_method"] === "card"
                                    ? "selected"
                                    : "" ?>>

                                <?= $isPashto
                                    ? "کارت"
                                    : "Card" ?>

                            </option>

                            <option
                                value="bank"
                                <?= $payment["payment_method"] === "bank"
                                    ? "selected"
                                    : "" ?>>

                                <?= $isPashto
                                    ? "بانک"
                                    : "Bank" ?>

                            </option>

                        </select>

                    </div>

                    <!-- Payment Date -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= $isPashto
                                ? "د تادیې نېټه"
                                : "Payment Date" ?>

                        </label>

                        <input
                            type="datetime-local"
                            name="payment_date"
                            class="form-control"
                            value="<?= htmlspecialchars($currentDate) ?>"
                            required>

                    </div>

                    <!-- Notes -->

                    <div class="col-12">

                        <label class="form-label">

                            <?= $isPashto
                                ? "یادښت"
                                : "Notes" ?>

                        </label>

                        <textarea
                            name="notes"
                            class="form-control"
                            rows="3"><?= htmlspecialchars(
                                $payment["notes"] ?? ""
                            ) ?></textarea>

                    </div>

                </div>

                <div class="mt-4">

                    <button class="btn btn-primary">

                        <i class="bi bi-check-circle"></i>

                        <?= $isPashto
                            ? "تادیه تازه کول"
                            : "Update Payment" ?>

                    </button>

                    <a
                        href="view.php?id=<?= $id ?>"
                        class="btn btn-secondary">

                        <?= $isPashto
                            ? "لغوه"
                            : "Cancel" ?>

                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>