<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";

$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {

    $msg = $isPashto
        ? "د تادیې ID ناسم دی."
        : "Invalid payment ID.";

    header("Location: index.php?error=" . urlencode($msg));
    exit;
}

$stmt = $conn->prepare("
    SELECT
        p.*,
        c.name AS customer_name,
        c.phone AS customer_phone,
        s.name AS supplier_name,
        s.phone AS supplier_phone
    FROM payments p
    LEFT JOIN customers c
        ON p.customer_id = c.customer_id
    LEFT JOIN suppliers s
        ON p.supplier_id = s.supplier_id
    WHERE p.payment_id = ?
");

$stmt->execute([$id]);

$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {

    $msg = $isPashto
        ? "تادیه پیدا نه شوه."
        : "Payment not found.";

    header("Location: index.php?error=" . urlencode($msg));
    exit;
}

function money($amount)
{
    return "$" . number_format((float)$amount, 2);
}

if (!empty($payment["customer_id"])) {

    $type = $isPashto ? "مشتری" : "Customer";
    $name = $payment["customer_name"];
    $phone = $payment["customer_phone"];

} elseif (!empty($payment["supplier_id"])) {

    $type = $isPashto ? "عرضه کوونکی" : "Supplier";
    $name = $payment["supplier_name"];
    $phone = $payment["supplier_phone"];

} else {

    $type = $isPashto ? "نور" : "Other";
    $name = $isPashto ? "نشته" : "N/A";
    $phone = "";

}

$paymentMethod = match ($payment["payment_method"]) {
    "cash" => $isPashto ? "نغدې" : "Cash",
    "card" => $isPashto ? "کارت" : "Card",
    "bank" => $isPashto ? "بانک" : "Bank",
    default => ucfirst($payment["payment_method"])
};

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
        <?= $isPashto ? "د تادیې معلومات" : "View Payment" ?>
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

        .card {
            border: none;
            border-radius: 14px;
        }

        .amount {
            font-size: 32px;
            font-weight: 700;
        }

        [dir="rtl"] {
            text-align: right;
        }

        [dir="rtl"] .text-center {
            text-align: center !important;
        }

        .language-switch {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }

        [dir="rtl"] .language-switch {
            left: auto;
            right: 20px;
        }

    </style>

</head>

<body>

<!-- Language Switch -->

<div class="language-switch">

    <a
        href="?id=<?= (int)$payment["payment_id"] ?>&lang=ps"
        class="btn btn-sm <?= $isPashto
            ? "btn-primary"
            : "btn-outline-primary" ?>">

        پښتو

    </a>

    <a
        href="?id=<?= (int)$payment["payment_id"] ?>&lang=en"
        class="btn btn-sm <?= !$isPashto
            ? "btn-primary"
            : "btn-outline-primary" ?>">

        English

    </a>

</div>


<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold">

                <i class="bi bi-cash-stack"></i>

                <?= $isPashto
                    ? "د تادیې معلومات"
                    : "Payment Details" ?>

            </h2>

            <p class="text-muted mb-0">

                <?= $isPashto
                    ? "تادیه #"
                    : "Payment #" ?>

                <?= (int)$payment["payment_id"] ?>

            </p>

        </div>

        <div>

            <a
                href="index.php"
                class="btn btn-secondary">

                <i class="bi bi-arrow-left"></i>

                <?= $isPashto
                    ? "بېرته"
                    : "Back" ?>

            </a>

            <a
                href="edit.php?id=<?= (int)$payment["payment_id"] ?>"
                class="btn btn-warning">

                <i class="bi bi-pencil"></i>

                <?= $isPashto
                    ? "سمول"
                    : "Edit" ?>

            </a>

        </div>

    </div>


    <div class="row g-4">

        <!-- Amount Card -->

        <div class="col-lg-4">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <i class="bi bi-cash-coin fs-1 text-success"></i>

                    <div class="amount text-success mt-3">

                        <?= money($payment["amount"]) ?>

                    </div>

                    <span class="badge bg-primary mt-2">

                        <?= htmlspecialchars($type) ?>

                    </span>

                </div>

            </div>

        </div>


        <!-- Payment Information -->

        <div class="col-lg-8">

            <div class="card shadow-sm">

                <div class="card-header bg-white">

                    <h5 class="mb-0">

                        <?= $isPashto
                            ? "د تادیې معلومات"
                            : "Payment Information" ?>

                    </h5>

                </div>

                <div class="card-body">

                    <div class="row g-3">


                        <!-- Payment ID -->

                        <div class="col-md-6">

                            <strong>

                                <?= $isPashto
                                    ? "د تادیې ID"
                                    : "Payment ID" ?>

                            </strong>

                            <div>

                                #<?= (int)$payment["payment_id"] ?>

                            </div>

                        </div>


                        <!-- Type -->

                        <div class="col-md-6">

                            <strong>

                                <?= $isPashto
                                    ? "ډول"
                                    : "Type" ?>

                            </strong>

                            <div>

                                <?= htmlspecialchars($type) ?>

                            </div>

                        </div>


                        <!-- Customer / Supplier -->

                        <div class="col-md-6">

                            <strong>

                                <?= $isPashto
                                    ? "مشتری / عرضه کوونکی"
                                    : "Customer / Supplier" ?>

                            </strong>

                            <div>

                                <?= htmlspecialchars(
                                    $name ?? ($isPashto ? "نشته" : "N/A")
                                ) ?>

                            </div>

                        </div>


                        <!-- Phone -->

                        <div class="col-md-6">

                            <strong>

                                <?= $isPashto
                                    ? "ټیلیفون"
                                    : "Phone" ?>

                            </strong>

                            <div>

                                <?= htmlspecialchars(
                                    $phone ?? ($isPashto ? "نشته" : "N/A")
                                ) ?>

                            </div>

                        </div>


                        <!-- Amount -->

                        <div class="col-md-6">

                            <strong>

                                <?= $isPashto
                                    ? "اندازه"
                                    : "Amount" ?>

                            </strong>

                            <div class="text-success fw-bold">

                                <?= money($payment["amount"]) ?>

                            </div>

                        </div>


                        <!-- Payment Method -->

                        <div class="col-md-6">

                            <strong>

                                <?= $isPashto
                                    ? "د تادیې طریقه"
                                    : "Payment Method" ?>

                            </strong>

                            <div>

                                <span class="badge bg-secondary">

                                    <?= htmlspecialchars($paymentMethod) ?>

                                </span>

                            </div>

                        </div>


                        <!-- Sale ID -->

                        <div class="col-md-6">

                            <strong>

                                <?= $isPashto
                                    ? "د خرڅلاو ID"
                                    : "Sale ID" ?>

                            </strong>

                            <div>

                                <?= !empty($payment["sale_id"])
                                    ? "#" . (int)$payment["sale_id"]
                                    : ($isPashto ? "نشته" : "N/A") ?>

                            </div>

                        </div>


                        <!-- Purchase ID -->

                        <div class="col-md-6">

                            <strong>

                                <?= $isPashto
                                    ? "د پېرود ID"
                                    : "Purchase ID" ?>

                            </strong>

                            <div>

                                <?= !empty($payment["purchase_id"])
                                    ? "#" . (int)$payment["purchase_id"]
                                    : ($isPashto ? "نشته" : "N/A") ?>

                            </div>

                        </div>


                        <!-- Payment Date -->

                        <div class="col-md-6">

                            <strong>

                                <?= $isPashto
                                    ? "د تادیې نېټه"
                                    : "Payment Date" ?>

                            </strong>

                            <div>

                                <?= htmlspecialchars(
                                    $payment["payment_date"]
                                ) ?>

                            </div>

                        </div>


                        <!-- Notes -->

                        <div class="col-12">

                            <strong>

                                <?= $isPashto
                                    ? "یادښت"
                                    : "Notes" ?>

                            </strong>

                            <div class="mt-2 p-3 bg-light rounded">

                                <?=
                                !empty($payment["notes"])
                                    ? nl2br(
                                        htmlspecialchars(
                                            $payment["notes"]
                                        )
                                      )
                                    : '<span class="text-muted">'
                                      . (
                                            $isPashto
                                                ? "یادښت نشته"
                                                : "No notes"
                                        )
                                      . '</span>'
                                ?>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>