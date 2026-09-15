<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";

$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {

    header(
        "Location: index.php?error="
        . urlencode("Invalid customer ID.")
    );

    exit;
}


$stmt = $conn->prepare("
    SELECT *
    FROM customers
    WHERE customer_id = :id
");

$stmt->execute([
    ":id" => $id
]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {

    header(
        "Location: index.php?error="
        . urlencode("Customer not found.")
    );

    exit;
}


/* CUSTOMER SALES */

$salesStmt = $conn->prepare("
    SELECT
        sale_id,
        sale_date,
        total_amount,
        paid_amount,
        due_amount,
        payment_method,
        payment_status
    FROM sales
    WHERE customer_id = :id
    ORDER BY sale_id DESC
");

$salesStmt->execute([
    ":id" => $id
]);

$sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0">

<title>View Customer</title>

<!-- bootstrap -->
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

.table td {
    vertical-align: middle;
}

</style>

</head>

<body>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">

                <i class="bi bi-person-circle"></i>

                Customer Details

            </h2>

            <p class="text-muted mb-0">

                Customer #<?= (int)$customer["customer_id"] ?>

            </p>

        </div>

        <div>

            <a
                href="index.php"
                class="btn btn-secondary">

                <i class="bi bi-arrow-left"></i>

                Back

            </a>

            <a
                href="edit.php?id=<?= (int)$customer["customer_id"] ?>"
                class="btn btn-warning">

                <i class="bi bi-pencil-square"></i>

                Edit

            </a>

        </div>

    </div>


    <!-- CUSTOMER INFO -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">

                <i class="bi bi-person-vcard"></i>

                Customer Information

            </h5>

        </div>

        <div class="card-body">

            <div class="row g-4">

                <div class="col-md-6">

                    <small class="text-muted">
                        Name
                    </small>

                    <h5>

                        <?= htmlspecialchars(
                            $customer["name"]
                        ) ?>

                    </h5>

                </div>


                <div class="col-md-6">

                    <small class="text-muted">
                        Phone
                    </small>

                    <h5>

                        <?= !empty($customer["phone"])
                            ? htmlspecialchars($customer["phone"])
                            : "N/A" ?>

                    </h5>

                </div>


                <div class="col-md-6">

                    <small class="text-muted">
                        Email
                    </small>

                    <h5>

                        <?= !empty($customer["email"])
                            ? htmlspecialchars($customer["email"])
                            : "N/A" ?>

                    </h5>

                </div>


                <div class="col-md-6">

                    <small class="text-muted">
                        Created
                    </small>

                    <h5>

                        <?= htmlspecialchars(
                            $customer["created_at"]
                        ) ?>

                    </h5>

                </div>


                <div class="col-12">

                    <small class="text-muted">
                        Address
                    </small>

                    <p class="mb-0">

                        <?= !empty($customer["address"])
                            ? nl2br(
                                htmlspecialchars(
                                    $customer["address"]
                                )
                            )
                            : "N/A" ?>

                    </p>

                </div>

            </div>

        </div>

    </div>


    <!-- SALES -->

    <div class="card shadow-sm">

        <div class="card-header bg-white py-3">

            <div class="d-flex justify-content-between">

                <h5 class="mb-0">

                    <i class="bi bi-receipt"></i>

                    Customer Sales

                </h5>

                <span class="badge bg-primary">

                    <?= count($sales) ?> Sales

                </span>

            </div>

        </div>


        <div class="card-body">

            <?php if (empty($sales)): ?>

                <div class="text-center py-4">

                    <i class="bi bi-receipt fs-1 text-muted"></i>

                    <p class="text-muted mt-2 mb-0">

                        No sales found for this customer.

                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover">

                        <thead class="table-light">

                            <tr>

                                <th>#</th>

                                <th>Date</th>

                                <th>Total</th>

                                <th>Paid</th>

                                <th>Due</th>

                                <th>Payment</th>

                                <th>Status</th>

                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($sales as $sale): ?>

                            <tr>

                                <td>

                                    #<?= (int)$sale["sale_id"] ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $sale["sale_date"]
                                    ) ?>

                                </td>

                                <td>

                                    <strong>

                                        $<?= number_format(
                                            (float)$sale["total_amount"],
                                            2
                                        ) ?>

                                    </strong>

                                </td>

                                <td class="text-success">

                                    $<?= number_format(
                                        (float)$sale["paid_amount"],
                                        2
                                    ) ?>

                                </td>

                                <td class="text-danger">

                                    $<?= number_format(
                                        (float)$sale["due_amount"],
                                        2
                                    ) ?>

                                </td>

                                <td>

                                    <span class="badge bg-secondary">

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $sale["payment_method"]
                                            )
                                        ) ?>

                                    </span>

                                </td>

                                <td>

                                    <?php

                                    $class = "secondary";

                                    if (
                                        $sale["payment_status"] === "paid"
                                    ) {
                                        $class = "success";
                                    } elseif (
                                        $sale["payment_status"] === "partial"
                                    ) {
                                        $class = "warning";
                                    } elseif (
                                        $sale["payment_status"] === "unpaid"
                                    ) {
                                        $class = "danger";
                                    }

                                    ?>

                                    <span
                                        class="badge bg-<?= $class ?>">

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $sale["payment_status"]
                                            )
                                        ) ?>

                                    </span>

                                </td>

                                <td>

                                    <a
                                        href="../sales/view.php?id=<?= (int)$sale["sale_id"] ?>"
                                        class="btn btn-sm btn-primary">

                                        <i class="bi bi-eye"></i>

                                    </a>

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

</body>
</html>