<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$id = (int)($_GET["customer_id"] ?? 0);

if ($id <= 0) {

    header(
        "Location: index.php?error=" .
        urlencode(
            currentLanguage() === "ps"
                ? "د پېرېدونکي ID ناسم دی."
                : "Invalid customer ID."
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Customer
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        customer_id,
        name,
        phone,
        address,
        email,
        created_at
    FROM customers
    WHERE customer_id = :id
");

$stmt->execute([
    ":id" => $id
]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {

    header(
        "Location: index.php?error=" .
        urlencode(
            currentLanguage() === "ps"
                ? "پېرېدونکی پیدا نه شو."
                : "Customer not found."
        )
    );

    exit;
}


$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| Update Customer
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $email = trim($_POST["email"] ?? "");


    /*
     * Validation
     */

    if ($name === "") {

        $error = currentLanguage() === "ps"
            ? "د پېرېدونکي نوم ضروري دی."
            : "Customer name is required.";

    } elseif (
        $email !== ""
        && !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $error = currentLanguage() === "ps"
            ? "مهرباني وکړئ سم ایمیل ولیکئ."
            : "Please enter a valid email address.";

    } else {

        try {

            $update = $conn->prepare("
                UPDATE customers
                SET
                    name = :name,
                    phone = :phone,
                    address = :address,
                    email = :email
                WHERE customer_id = :id
            ");

            $update->execute([
                ":name" => $name,
                ":phone" => $phone !== "" ? $phone : null,
                ":address" => $address !== "" ? $address : null,
                ":email" => $email !== "" ? $email : null,
                ":id" => $id
            ]);


            /*
             * Redirect after successful update
             */

            header(
                "Location: index.php?success=" .
                urlencode(
                    currentLanguage() === "ps"
                        ? "پېرېدونکی په بریالیتوب سره بدل شو."
                        : "Customer updated successfully."
                )
            );

            exit;

        } catch (PDOException $e) {

            $error = currentLanguage() === "ps"
                ? "پېرېدونکی نه شو بدلولی."
                : "Unable to update customer.";
        }
    }


    /*
     * Keep entered values if validation fails
     */

    $customer["name"] = $name;
    $customer["phone"] = $phone;
    $customer["address"] = $address;
    $customer["email"] = $email;
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
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= currentLanguage() === "ps"
            ? "پېرېدونکی سمول"
            : "Edit Customer"
        ?>
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

        .card {
            border: none;
            border-radius: 14px;
        }

        .form-label {
            font-weight: 600;
        }

    </style>

</head>


<body>


<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-7">


            <div class="card shadow-sm">


                <!-- Header -->

                <div class="card-header bg-primary text-white py-3">

                    <h4 class="mb-0">

                        <i class="bi bi-pencil-square"></i>

                        <?= currentLanguage() === "ps"
                            ? "پېرېدونکی سمول"
                            : "Edit Customer"
                        ?>

                    </h4>

                </div>


                <!-- Body -->

                <div class="card-body">


                    <!-- Error -->

                    <?php if ($error !== ""): ?>

                        <div class="alert alert-danger">

                            <i class="bi bi-exclamation-triangle"></i>

                            <?= htmlspecialchars($error) ?>

                        </div>

                    <?php endif; ?>


                    <form method="POST">


                        <!-- Name -->

                        <div class="mb-3">

                            <label class="form-label">

                                <?= currentLanguage() === "ps"
                                    ? "نوم"
                                    : "Name"
                                ?>

                                <span class="text-danger">*</span>

                            </label>


                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                required
                                maxlength="100"
                                value="<?= htmlspecialchars(
                                    $customer["name"] ?? ""
                                ) ?>"
                            >

                        </div>


                        <!-- Phone -->

                        <div class="mb-3">

                            <label class="form-label">

                                <?= currentLanguage() === "ps"
                                    ? "د تلیفون شمېره"
                                    : "Phone"
                                ?>

                            </label>


                            <input
                                type="text"
                                name="phone"
                                class="form-control"
                                maxlength="30"
                                value="<?= htmlspecialchars(
                                    $customer["phone"] ?? ""
                                ) ?>"
                            >

                        </div>


                        <!-- Email -->

                        <div class="mb-3">

                            <label class="form-label">

                                <?= currentLanguage() === "ps"
                                    ? "ایمیل"
                                    : "Email"
                                ?>

                            </label>


                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                maxlength="100"
                                value="<?= htmlspecialchars(
                                    $customer["email"] ?? ""
                                ) ?>"
                            >

                        </div>


                        <!-- Address -->

                        <div class="mb-4">

                            <label class="form-label">

                                <?= currentLanguage() === "ps"
                                    ? "پته"
                                    : "Address"
                                ?>

                            </label>


                            <textarea
                                name="address"
                                class="form-control"
                                rows="3"
                                maxlength="255"
                            ><?= htmlspecialchars(
                                $customer["address"] ?? ""
                            ) ?></textarea>

                        </div>


                        <!-- Buttons -->

                        <div class="d-flex justify-content-between">


                            <!-- Back -->

                            <a
                                href="index.php"
                                class="btn btn-secondary"
                            >

                                <i class="bi bi-arrow-left"></i>

                                <?= currentLanguage() === "ps"
                                    ? "بېرته"
                                    : "Back"
                                ?>

                            </a>


                            <!-- Update -->

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                <i class="bi bi-save"></i>

                                <?= currentLanguage() === "ps"
                                    ? "بدلونونه خوندي کړه"
                                    : "Update Customer"
                                ?>

                            </button>

                        </div>


                    </form>


                </div>

            </div>


        </div>

    </div>

</div>


<!-- Bootstrap JS -->

<script src="../assets/js/bootstrap.bundle.min.js"></script>


</body>

</html>