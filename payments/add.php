
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";

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

    $customer_id = !empty($_POST["customer_id"])
        ? (int)$_POST["customer_id"]
        : null;

    $supplier_id = !empty($_POST["supplier_id"])
        ? (int)$_POST["supplier_id"]
        : null;

    $sale_id = !empty($_POST["sale_id"])
        ? (int)$_POST["sale_id"]
        : null;

    $purchase_id = !empty($_POST["purchase_id"])
        ? (int)$_POST["purchase_id"]
        : null;

    $amount = (float)($_POST["amount"] ?? 0);

    $payment_method = $_POST["payment_method"] ?? "cash";

    $payment_date = $_POST["payment_date"] ?? date("Y-m-d H:i:s");

    $notes = trim($_POST["notes"] ?? "");

    if ($amount <= 0) {

        $error = $isPashto
            ? "د تادیې مقدار باید له صفر څخه زیات وي."
            : "Payment amount must be greater than 0.";

    } elseif ($customer_id && $supplier_id) {

        $error = $isPashto
            ? "یوازې مشتری یا عرضه کوونکی انتخاب کړئ، دواړه مه انتخابوئ."
            : "Select either Customer OR Supplier, not both.";

    } elseif (!$customer_id && !$supplier_id) {

        $error = $isPashto
            ? "مهرباني وکړئ مشتری یا عرضه کوونکی انتخاب کړئ."
            : "Please select a Customer or Supplier.";

    } elseif (!in_array($payment_method, ["cash", "card", "bank"], true)) {

        $error = $isPashto
            ? "د تادیې طریقه ناسم ده."
            : "Invalid payment method.";

    } else {

        try {

            $conn->beginTransaction();

            /*
             * Validate customer/supplier
             */

            if ($customer_id) {

                $stmt = $conn->prepare("
                    SELECT customer_id
                    FROM customers
                    WHERE customer_id = ?
                ");

                $stmt->execute([$customer_id]);

                if (!$stmt->fetch()) {

                    $errorMessage = $isPashto
                        ? "مشتری ونه موندل شو."
                        : "Customer not found.";

                    throw new Exception($errorMessage);
                }
            }

            if ($supplier_id) {

                $stmt = $conn->prepare("
                    SELECT supplier_id
                    FROM suppliers
                    WHERE supplier_id = ?
                ");

                $stmt->execute([$supplier_id]);

                if (!$stmt->fetch()) {

                    $errorMessage = $isPashto
                        ? "عرضه کوونکی ونه موندل شو."
                        : "Supplier not found.";

                    throw new Exception($errorMessage);
                }
            }

            /*
             * Validate sale
             */

            if ($sale_id) {

                $stmt = $conn->prepare("
                    SELECT sale_id
                    FROM sales
                    WHERE sale_id = ?
                ");

                $stmt->execute([$sale_id]);

                if (!$stmt->fetch()) {

                    $errorMessage = $isPashto
                        ? "Sale ونه موندل شو."
                        : "Sale not found.";

                    throw new Exception($errorMessage);
                }
            }

            /*
             * Validate purchase
             */

            if ($purchase_id) {

                $stmt = $conn->prepare("
                    SELECT purchase_id
                    FROM purchases
                    WHERE purchase_id = ?
                ");

                $stmt->execute([$purchase_id]);

                if (!$stmt->fetch()) {

                    $errorMessage = $isPashto
                        ? "Purchase ونه موندل شو."
                        : "Purchase not found.";

                    throw new Exception($errorMessage);
                }
            }

            /*
             * Insert payment
             */

            $stmt = $conn->prepare("
                INSERT INTO payments
                (
                    sale_id,
                    purchase_id,
                    customer_id,
                    supplier_id,
                    amount,
                    payment_method,
                    payment_date,
                    notes
                )
                VALUES
                (
                    :sale_id,
                    :purchase_id,
                    :customer_id,
                    :supplier_id,
                    :amount,
                    :payment_method,
                    :payment_date,
                    :notes
                )
            ");

            $stmt->execute([
                ":sale_id"        => $sale_id,
                ":purchase_id"    => $purchase_id,
                ":customer_id"    => $customer_id,
                ":supplier_id"    => $supplier_id,
                ":amount"         => $amount,
                ":payment_method" => $payment_method,
                ":payment_date"   => $payment_date,
                ":notes"          => $notes
            ]);

            /*
             * Update sale paid/due if payment belongs to sale
             */

            if ($sale_id) {

                $stmt = $conn->prepare("
                    SELECT total_amount, discount
                    FROM sales
                    WHERE sale_id = ?
                ");

                $stmt->execute([$sale_id]);

                $sale = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($sale) {

                    $total = max(
                        0,
                        (float)$sale["total_amount"]
                    );

                    $stmt = $conn->prepare("
                        SELECT COALESCE(SUM(amount), 0)
                        FROM payments
                        WHERE sale_id = ?
                    ");

                    $stmt->execute([$sale_id]);

                    $paid = (float)$stmt->fetchColumn();

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
             * Update purchase paid/due if payment belongs to purchase
             */

            if ($purchase_id) {

                $stmt = $conn->prepare("
                    SELECT total_amount
                    FROM purchases
                    WHERE purchase_id = ?
                ");

                $stmt->execute([$purchase_id]);

                $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($purchase) {

                    $total = (float)$purchase["total_amount"];

                    $stmt = $conn->prepare("
                        SELECT COALESCE(SUM(amount), 0)
                        FROM payments
                        WHERE purchase_id = ?
                    ");

                    $stmt->execute([$purchase_id]);

                    $paid = (float)$stmt->fetchColumn();

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

            $successMessage = $isPashto
                ? "تادیه په بریالیتوب سره اضافه شوه."
                : "Payment added successfully.";

            header(
                "Location: index.php?success="
                . urlencode($successMessage)
            );

            exit;

        } catch (Throwable $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $error = $e->getMessage();
        }
    }
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
        content="width=device-width, initial-scale=1.0">

    <title>
        <?= $isPashto
            ? "تادیه اضافه کول"
            : "Add Payment"
        ?>
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

        <?php if ($isPashto): ?>

        body {
            text-align: right;
        }

        .form-control,
        .form-select {
            text-align: right;
        }

        <?php endif; ?>

    </style>

</head>

<body class="bg-light">

<div class="container py-5">

    <div class="card shadow-sm border-0">

        <div class="card-header bg-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">

                    <i class="bi bi-plus-circle"></i>

                    <?= $isPashto
                        ? "تادیه اضافه کول"
                        : "Add Payment"
                    ?>

                </h4>

                <div>

                    <a
                        href="?lang=ps"
                        class="btn btn-sm <?= $isPashto
                            ? 'btn-primary'
                            : 'btn-outline-primary'
                        ?>">

                        پښتو

                    </a>

                    <a
                        href="?lang=en"
                        class="btn btn-sm <?= !$isPashto
                            ? 'btn-primary'
                            : 'btn-outline-primary'
                        ?>">

                        English

                    </a>

                </div>

            </div>

        </div>

        <div class="card-body">

            <?php if ($error): ?>

                <div class="alert alert-danger">

                    <i class="bi bi-exclamation-triangle"></i>

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>

            <form method="POST">

                <div class="row g-3">

                    <!-- Customer -->

                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            <?= $isPashto
                                ? "مشتری"
                                : "Customer"
                            ?>

                        </label>

                        <select
                            name="customer_id"
                            id="customer_id"
                            class="form-select">

                            <option value="">

                                <?= $isPashto
                                    ? "-- مشتری انتخاب کړئ --"
                                    : "-- Select Customer --"
                                ?>

                            </option>

                            <?php foreach ($customers as $customer): ?>

                                <option
                                    value="<?= (int)$customer["customer_id"] ?>"
                                    <?= (
                                        isset($_POST["customer_id"]) &&
                                        (int)$_POST["customer_id"] ===
                                        (int)$customer["customer_id"]
                                    ) ? "selected" : "" ?>>

                                    <?= htmlspecialchars($customer["name"]) ?>

                                    <?php if (!empty($customer["phone"])): ?>

                                        -
                                        <?= htmlspecialchars($customer["phone"]) ?>

                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- Supplier -->

                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            <?= $isPashto
                                ? "عرضه کوونکی"
                                : "Supplier"
                            ?>

                        </label>

                        <select
                            name="supplier_id"
                            id="supplier_id"
                            class="form-select">

                            <option value="">

                                <?= $isPashto
                                    ? "-- عرضه کوونکی انتخاب کړئ --"
                                    : "-- Select Supplier --"
                                ?>

                            </option>

                            <?php foreach ($suppliers as $supplier): ?>

                                <option
                                    value="<?= (int)$supplier["supplier_id"] ?>"
                                    <?= (
                                        isset($_POST["supplier_id"]) &&
                                        (int)$_POST["supplier_id"] ===
                                        (int)$supplier["supplier_id"]
                                    ) ? "selected" : "" ?>>

                                    <?= htmlspecialchars($supplier["name"]) ?>

                                    <?php if (!empty($supplier["phone"])): ?>

                                        -
                                        <?= htmlspecialchars($supplier["phone"]) ?>

                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- Sale ID -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= $isPashto
                                ? "Sale ID (اختیاري)"
                                : "Sale ID (optional)"
                            ?>

                        </label>

                        <input
                            type="number"
                            name="sale_id"
                            class="form-control"
                            min="1"
                            value="<?= htmlspecialchars(
                                $_POST["sale_id"] ?? ""
                            ) ?>">

                        <small class="text-muted">

                            <?= $isPashto
                                ? "کله چې د مشتری تادیه د Sale لپاره وي، دلته Sale ID ولیکئ."
                                : "Use this when customer payment is for a sale."
                            ?>

                        </small>

                    </div>

                    <!-- Purchase ID -->

                    <div class="col-md-6">

                        <label class="form-label">

                            <?= $isPashto
                                ? "Purchase ID (اختیاري)"
                                : "Purchase ID (optional)"
                            ?>

                        </label>

                        <input
                            type="number"
                            name="purchase_id"
                            class="form-control"
                            min="1"
                            value="<?= htmlspecialchars(
                                $_POST["purchase_id"] ?? ""
                            ) ?>">

                        <small class="text-muted">

                            <?= $isPashto
                                ? "کله چې د عرضه کوونکي تادیه د Purchase لپاره وي، دلته Purchase ID ولیکئ."
                                : "Use this when supplier payment is for a purchase."
                            ?>

                        </small>

                    </div>

                    <!-- Amount -->

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">

                            <?= $isPashto
                                ? "مقدار"
                                : "Amount"
                            ?>

                        </label>

                        <input
                            type="number"
                            name="amount"
                            class="form-control"
                            step="0.01"
                            min="0.01"
                            value="<?= htmlspecialchars(
                                $_POST["amount"] ?? ""
                            ) ?>"
                            required>

                    </div>

                    <!-- Payment Method -->

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">

                            <?= $isPashto
                                ? "د تادیې طریقه"
                                : "Payment Method"
                            ?>

                        </label>

                        <select
                            name="payment_method"
                            class="form-select"
                            required>

                            <option
                                value="cash"
                                <?= (
                                    ($_POST["payment_method"] ?? "cash")
                                    === "cash"
                                ) ? "selected" : "" ?>>

                                <?= $isPashto
                                    ? "نغدې"
                                    : "Cash"
                                ?>

                            </option>

                            <option
                                value="card"
                                <?= (
                                    ($_POST["payment_method"] ?? "")
                                    === "card"
                                ) ? "selected" : "" ?>>

                                <?= $isPashto
                                    ? "کارت"
                                    : "Card"
                                ?>

                            </option>

                            <option
                                value="bank"
                                <?= (
                                    ($_POST["payment_method"] ?? "")
                                    === "bank"
                                ) ? "selected" : "" ?>>

                                <?= $isPashto
                                    ? "بانک"
                                    : "Bank"
                                ?>

                            </option>

                        </select>

                    </div>

                    <!-- Payment Date -->

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">

                            <?= $isPashto
                                ? "د تادیې نېټه"
                                : "Payment Date"
                            ?>

                        </label>

                        <input
                            type="datetime-local"
                            name="payment_date"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $_POST["payment_date"]
                                ?? date("Y-m-d\TH:i")
                            ) ?>"
                            required>

                    </div>

                    <!-- Notes -->

                    <div class="col-12">

                        <label class="form-label">

                            <?= $isPashto
                                ? "یادښت"
                                : "Notes"
                            ?>

                        </label>

                        <textarea
                            name="notes"
                            class="form-control"
                            rows="3"
                            placeholder="<?= $isPashto
                                ? 'د تادیې یادښت...'
                                : 'Payment notes...'
                            ?>"><?= htmlspecialchars(
                                $_POST["notes"] ?? ""
                            ) ?></textarea>

                    </div>

                </div>

                <div class="mt-4">

                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="bi bi-check-circle"></i>

                        <?= $isPashto
                            ? "تادیه ثبت کړئ"
                            : "Save Payment"
                        ?>

                    </button>

                    <a
                        href="index.php"
                        class="btn btn-secondary">

                        <i class="bi bi-arrow-left"></i>

                        <?= $isPashto
                            ? "بېرته"
                            : "Cancel"
                        ?>

                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

<script>

document.getElementById("customer_id").addEventListener(
    "change",
    function () {

        if (this.value !== "") {
            document.getElementById("supplier_id").value = "";
        }

    }
);

document.getElementById("supplier_id").addEventListener(
    "change",
    function () {

        if (this.value !== "") {
            document.getElementById("customer_id").value = "";
        }

    }
);

</script>

</body>
</html>
