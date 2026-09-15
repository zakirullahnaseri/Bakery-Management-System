<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if ($name === "") {

        $error = "Customer name is required.";

    } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        try {

            $stmt = $conn->prepare("
                INSERT INTO customers
                (
                    name,
                    phone,
                    address,
                    email
                )
                VALUES
                (
                    :name,
                    :phone,
                    :address,
                    :email
                )
            ");

            $stmt->execute([
                ":name" => $name,
                ":phone" => $phone !== "" ? $phone : null,
                ":address" => $address !== "" ? $address : null,
                ":email" => $email !== "" ? $email : null
            ]);

            header(
                "Location: index.php?success="
                . urlencode("Customer added successfully.")
            );

            exit;

        } catch (PDOException $e) {

            $error = "Unable to add customer.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0">

<title>Add Customer</title>
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

</style>

</head>

<body>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="card shadow-sm">

                <div class="card-header bg-white py-3">

                    <h4 class="mb-0">

                        <i class="bi bi-person-plus"></i>

                        Add Customer

                    </h4>

                </div>

                <div class="card-body">

                    <?php if ($error): ?>

                        <div class="alert alert-danger">

                            <i class="bi bi-exclamation-triangle"></i>

                            <?= htmlspecialchars($error) ?>

                        </div>

                    <?php endif; ?>


                    <form method="POST">

                        <div class="mb-3">

                            <label class="form-label">

                                Name <span class="text-danger">*</span>

                            </label>

                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                required
                                maxlength="100"
                                value="<?= htmlspecialchars(
                                    $_POST["name"] ?? ""
                                ) ?>">

                        </div>


                        <div class="mb-3">

                            <label class="form-label">

                                Phone

                            </label>

                            <input
                                type="text"
                                name="phone"
                                class="form-control"
                                maxlength="30"
                                value="<?= htmlspecialchars(
                                    $_POST["phone"] ?? ""
                                ) ?>">

                        </div>


                        <div class="mb-3">

                            <label class="form-label">

                                Email

                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                maxlength="100"
                                value="<?= htmlspecialchars(
                                    $_POST["email"] ?? ""
                                ) ?>">

                        </div>


                        <div class="mb-4">

                            <label class="form-label">

                                Address

                            </label>

                            <textarea
                                name="address"
                                class="form-control"
                                rows="3"
                                maxlength="255"><?= htmlspecialchars(
                                    $_POST["address"] ?? ""
                                ) ?></textarea>

                        </div>


                        <div class="d-flex justify-content-between">

                            <a
                                href="index.php"
                                class="btn btn-secondary">

                                <i class="bi bi-arrow-left"></i>

                                Back

                            </a>

                            <button
                                type="submit"
                                class="btn btn-success">

                                <i class="bi bi-check-circle"></i>

                                Save Customer

                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>