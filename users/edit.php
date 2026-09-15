
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";

$user_id = (int)($_GET["id"] ?? 0);

if ($user_id <= 0) {
    header("Location: index.php?error=" . urlencode("Invalid user ID."));
    exit;
}


/* GET USER */

$stmt = $conn->prepare("
    SELECT *
    FROM users
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    ":user_id" => $user_id
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.php?error=" . urlencode("User not found."));
    exit;
}


$error = "";


/* UPDATE */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $username  = trim($_POST["username"] ?? "");
    $password  = $_POST["password"] ?? "";
    $role      = $_POST["role"] ?? "staff";
    $phone     = trim($_POST["phone"] ?? "");
    $status    = $_POST["status"] ?? "active";


    if ($full_name === "") {

        $error = "Full name is required.";

    } elseif ($username === "") {

        $error = "Username is required.";

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
                AND user_id != :user_id
                LIMIT 1
            ");

            $checkStmt->execute([
                ":username" => $username,
                ":user_id" => $user_id
            ]);

            if ($checkStmt->fetch()) {

                $error = "Username already exists.";

            } else {

                if ($password !== "") {

                    if (strlen($password) < 6) {

                        $error = "Password must be at least 6 characters.";

                    } else {

                        $hashedPassword = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $updateStmt = $conn->prepare("
                            UPDATE users
                            SET
                                full_name = :full_name,
                                username = :username,
                                password = :password,
                                role = :role,
                                phone = :phone,
                                status = :status
                            WHERE user_id = :user_id
                        ");

                        $updateStmt->execute([

                            ":full_name" => $full_name,
                            ":username" => $username,
                            ":password" => $hashedPassword,
                            ":role" => $role,
                            ":phone" => $phone !== "" ? $phone : null,
                            ":status" => $status,
                            ":user_id" => $user_id

                        ]);

                    }

                } else {

                    $updateStmt = $conn->prepare("
                        UPDATE users
                        SET
                            full_name = :full_name,
                            username = :username,
                            role = :role,
                            phone = :phone,
                            status = :status
                        WHERE user_id = :user_id
                    ");

                    $updateStmt->execute([

                        ":full_name" => $full_name,
                        ":username" => $username,
                        ":role" => $role,
                        ":phone" => $phone !== "" ? $phone : null,
                        ":status" => $status,
                        ":user_id" => $user_id

                    ]);
                }


                if ($error === "") {

                    /* UPDATE CURRENT SESSION */

                    if (
                        (int)$_SESSION["user_id"] === $user_id
                    ) {

                        $_SESSION["full_name"] = $full_name;
                        $_SESSION["role"] = $role;

                    }


                    header(
                        "Location: index.php?success="
                        . urlencode("User updated successfully.")
                    );

                    exit;
                }
            }

        } catch (PDOException $e) {

            $error = "Database error. Please try again.";
        }
    }


    /* KEEP FORM DATA */

    $user["full_name"] = $full_name;
    $user["username"] = $username;
    $user["role"] = $role;
    $user["phone"] = $phone;
    $user["status"] = $status;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Edit User - Bakery Management</title>

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

                <i class="bi bi-pencil-square"></i>
                Edit User

            </h2>

            <p class="text-muted mb-0">
                Update user information
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
                User #<?= $user_id ?>
            </h5>

        </div>

        <div class="card-body">

            <form method="POST">

                <div class="row g-3">

                    <div class="col-md-6">

                        <label class="form-label">
                            Full Name
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            class="form-control"
                            required
                            value="<?= htmlspecialchars($user["full_name"]) ?>">

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Username
                        </label>

                        <input
                            type="text"
                            name="username"
                            class="form-control"
                            required
                            value="<?= htmlspecialchars($user["username"]) ?>">

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            New Password
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            minlength="6">

                        <small class="text-muted">
                            Leave empty to keep current password.
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
                            value="<?= htmlspecialchars($user["phone"] ?? "") ?>">

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Role
                        </label>

                        <select
                            name="role"
                            class="form-select">

                            <option
                                value="admin"
                                <?= $user["role"] === "admin" ? "selected" : "" ?>>
                                Admin
                            </option>

                            <option
                                value="manager"
                                <?= $user["role"] === "manager" ? "selected" : "" ?>>
                                Manager
                            </option>

                            <option
                                value="cashier"
                                <?= $user["role"] === "cashier" ? "selected" : "" ?>>
                                Cashier
                            </option>

                            <option
                                value="staff"
                                <?= $user["role"] === "staff" ? "selected" : "" ?>>
                                Staff
                            </option>

                        </select>

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Status
                        </label>

                        <select
                            name="status"
                            class="form-select">

                            <option
                                value="active"
                                <?= $user["status"] === "active" ? "selected" : "" ?>>
                                Active
                            </option>

                            <option
                                value="inactive"
                                <?= $user["status"] === "inactive" ? "selected" : "" ?>>
                                Inactive
                            </option>

                        </select>

                    </div>


                    <div class="col-12 mt-4">

                        <button
                            type="submit"
                            class="btn btn-success">

                            <i class="bi bi-check-circle"></i>
                            Update User

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