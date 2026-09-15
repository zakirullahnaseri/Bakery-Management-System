
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $username  = trim($_POST["username"] ?? "");
    $password  = $_POST["password"] ?? "";
    $role      = $_POST["role"] ?? "staff";
    $phone     = trim($_POST["phone"] ?? "");
    $status    = $_POST["status"] ?? "active";


    /* VALIDATION */

    if ($full_name === "") {

        $error = "Full name is required.";

    } elseif ($username === "") {

        $error = "Username is required.";

    } elseif ($password === "") {

        $error = "Password is required.";

    } elseif (strlen($password) < 6) {

        $error = "Password must be at least 6 characters.";

    }


    $allowedRoles = [
        "admin",
        "manager",
        "cashier",
        "staff"
    ];

    $allowedStatuses = [
        "active",
        "inactive"
    ];


    if (
        $error === "" &&
        !in_array($role, $allowedRoles, true)
    ) {
        $error = "Invalid role.";
    }


    if (
        $error === "" &&
        !in_array($status, $allowedStatuses, true)
    ) {
        $error = "Invalid status.";
    }


    if ($error === "") {

        try {

            /* CHECK USERNAME */

            $checkStmt = $conn->prepare("
                SELECT user_id
                FROM users
                WHERE username = :username
                LIMIT 1
            ");

            $checkStmt->execute([
                ":username" => $username
            ]);

            if ($checkStmt->fetch()) {

                $error = "Username already exists.";

            } else {

                /* PASSWORD HASH */

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


                /* INSERT */

                $stmt = $conn->prepare("
                    INSERT INTO users
                    (
                        full_name,
                        username,
                        password,
                        role,
                        phone,
                        status
                    )
                    VALUES
                    (
                        :full_name,
                        :username,
                        :password,
                        :role,
                        :phone,
                        :status
                    )
                ");

                $stmt->execute([

                    ":full_name" => $full_name,
                    ":username"  => $username,
                    ":password"  => $hashedPassword,
                    ":role"      => $role,
                    ":phone"     => $phone !== "" ? $phone : null,
                    ":status"    => $status

                ]);


                header(
                    "Location: index.php?success="
                    . urlencode("User added successfully.")
                );

                exit;
            }

        } catch (PDOException $e) {

            $error = "Database error. Please try again.";
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

    <title>Add User - Bakery Management</title>

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

    </style>

</head>

<body>

<nav class="navbar">

    <div class="container-fluid px-4">

        <a
            href="../admin/dashboard.php"
            class="navbar-brand fw-bold">

            <i class="bi bi-shop"></i>
            Bakery System

        </a>

        <div>

            <span class="me-3">

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars($_SESSION["full_name"] ?? "User") ?>

            </span>

            <a
                href="../public/logout.php"
                class="btn btn-outline-danger btn-sm">

                Logout

            </a>

        </div>

    </div>

</nav>


<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-person-plus"></i>
                Add User

            </h2>

            <p class="text-muted mb-0">
                Create a new system user
            </p>

        </div>

        <a
            href="index.php"
            class="btn btn-secondary">

            <i class="bi bi-arrow-left"></i>
            Back

        </a>

    </div>


    <?php if ($error !== ""): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle-fill"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <div class="card shadow-sm">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">
                User Information
            </h5>

        </div>

        <div class="card-body">

            <form method="POST">

                <div class="row g-3">

                    <div class="col-md-6">

                        <label class="form-label">
                            Full Name <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            class="form-control"
                            required
                            value="<?= htmlspecialchars($_POST["full_name"] ?? "") ?>">

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Username <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            name="username"
                            class="form-control"
                            required
                            value="<?= htmlspecialchars($_POST["username"] ?? "") ?>">

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Password <span class="text-danger">*</span>
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            minlength="6"
                            required>

                        <small class="text-muted">
                            Minimum 6 characters.
                        </small>

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Phone
                        </label>

                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST["phone"] ?? "") ?>">

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Role
                        </label>

                        <select
                            name="role"
                            class="form-select">

                            <?php
                            $selectedRole = $_POST["role"] ?? "staff";
                            ?>

                            <option
                                value="admin"
                                <?= $selectedRole === "admin" ? "selected" : "" ?>>
                                Admin
                            </option>

                            <option
                                value="manager"
                                <?= $selectedRole === "manager" ? "selected" : "" ?>>
                                Manager
                            </option>

                            <option
                                value="cashier"
                                <?= $selectedRole === "cashier" ? "selected" : "" ?>>
                                Cashier
                            </option>

                            <option
                                value="staff"
                                <?= $selectedRole === "staff" ? "selected" : "" ?>>
                                Staff
                            </option>

                        </select>

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Status
                        </label>

                        <?php
                        $selectedStatus = $_POST["status"] ?? "active";
                        ?>

                        <select
                            name="status"
                            class="form-select">

                            <option
                                value="active"
                                <?= $selectedStatus === "active" ? "selected" : "" ?>>
                                Active
                            </option>

                            <option
                                value="inactive"
                                <?= $selectedStatus === "inactive" ? "selected" : "" ?>>
                                Inactive
                            </option>

                        </select>

                    </div>


                    <div class="col-12 mt-4">

                        <button
                            type="submit"
                            class="btn btn-success">

                            <i class="bi bi-check-circle"></i>
                            Save User

                        </button>

                        <a
                            href="index.php"
                            class="btn btn-secondary">

                            Cancel

                        </a>

                    </div>

                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>

