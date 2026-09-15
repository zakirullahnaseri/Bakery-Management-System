
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";

$user_id = (int)($_GET["id"] ?? 0);

if ($user_id <= 0) {

    header(
        "Location: index.php?error="
        . urlencode("Invalid user ID.")
    );

    exit;
}


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

    header(
        "Location: index.php?error="
        . urlencode("User not found.")
    );

    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>View User - Bakery Management</title>

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

        .info-label {
            font-weight: 600;
            color: #6c757d;
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

                <i class="bi bi-person-vcard"></i>
                User Details

            </h2>

            <p class="text-muted mb-0">
                View user information
            </p>

        </div>

        <div>

            <a
                href="index.php"
                class="btn btn-secondary me-2">

                <i class="bi bi-arrow-left"></i>
                Back

            </a>

            <a
                href="edit.php?id=<?= $user_id ?>"
                class="btn btn-warning">

                <i class="bi bi-pencil-square"></i>
                Edit

            </a>

        </div>

    </div>


    <div class="card shadow-sm">

        <div class="card-header bg-white py-3">

            <h5 class="mb-0">

                User #<?= $user_id ?>

            </h5>

        </div>


        <div class="card-body">

            <div class="row g-4">

                <div class="col-md-6">

                    <div class="info-label">
                        Full Name
                    </div>

                    <div class="fs-5">
                        <?= htmlspecialchars($user["full_name"]) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <div class="info-label">
                        Username
                    </div>

                    <div class="fs-5">
                        <?= htmlspecialchars($user["username"]) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <div class="info-label">
                        Phone
                    </div>

                    <div class="fs-5">

                        <?= !empty($user["phone"])
                            ? htmlspecialchars($user["phone"])
                            : "N/A" ?>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="info-label">
                        Role
                    </div>

                    <div>

                        <?php

                        $roleClass = "secondary";

                        if ($user["role"] === "admin") {
                            $roleClass = "danger";
                        } elseif ($user["role"] === "manager") {
                            $roleClass = "warning";
                        } elseif ($user["role"] === "cashier") {
                            $roleClass = "primary";
                        } elseif ($user["role"] === "staff") {
                            $roleClass = "info";
                        }

                        ?>

                        <span class="badge bg-<?= $roleClass ?> fs-6">

                            <?= htmlspecialchars(
                                ucfirst($user["role"])
                            ) ?>

                        </span>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="info-label">
                        Status
                    </div>

                    <div>

                        <?php if ($user["status"] === "active"): ?>

                            <span class="badge bg-success fs-6">
                                Active
                            </span>

                        <?php else: ?>

                            <span class="badge bg-danger fs-6">
                                Inactive
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="info-label">
                        Created At
                    </div>

                    <div class="fs-5">

                        <?= htmlspecialchars(
                            $user["created_at"]
                        ) ?>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>

