
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";

/* ===============================
   SEARCH
================================ */

$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $stmt = $conn->prepare("
        SELECT *
        FROM users
        WHERE full_name LIKE :search
           OR username LIKE :search
           OR phone LIKE :search
           OR role LIKE :search
        ORDER BY user_id DESC
    ");

    $stmt->execute([
        ":search" => "%$search%"
    ]);

} else {

    $stmt = $conn->query("
        SELECT *
        FROM users
        ORDER BY user_id DESC
    ");
}

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Users - Bakery Management</title>

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

        .table td {
            vertical-align: middle;
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

        <div class="d-flex align-items-center">

            <span class="me-3">

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars($_SESSION["full_name"] ?? "User") ?>

            </span>

            <span class="badge bg-primary me-3">

                <?= htmlspecialchars($_SESSION["role"] ?? "User") ?>

            </span>

            <a
                href="../public/logout.php"
                class="btn btn-outline-danger btn-sm">

                <i class="bi bi-box-arrow-right"></i>
                Logout

            </a>

        </div>

    </div>

</nav>


<div class="container-fluid p-4">

    <!-- HEADER -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="page-title mb-1">

                <i class="bi bi-people-fill"></i>
                Users

            </h2>

            <p class="text-muted mb-0">
                Manage system users
            </p>

        </div>

        <div>

            <a
                href="../admin/dashboard.php"
                class="btn btn-secondary me-2">

                <i class="bi bi-arrow-left"></i>
                Dashboard

            </a>

            <a
                href="add.php"
                class="btn btn-primary">

                <i class="bi bi-person-plus"></i>
                Add User

            </a>

        </div>

    </div>


    <!-- MESSAGES -->

    <?php if (isset($_GET["success"])): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <i class="bi bi-check-circle-fill"></i>

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

            <i class="bi bi-exclamation-triangle-fill"></i>

            <?= htmlspecialchars($_GET["error"]) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>

    <?php endif; ?>


    <!-- USER LIST -->

    <div class="card shadow-sm">

        <div class="card-header bg-white py-3">

            <div class="d-flex justify-content-between align-items-center">

                <h5 class="mb-0">

                    <i class="bi bi-list-ul"></i>
                    User List

                </h5>

                <span class="badge bg-primary">

                    <?= count($users) ?> Users

                </span>

            </div>

        </div>


        <div class="card-body">

            <!-- SEARCH -->

            <form method="GET" class="mb-4">

                <div class="input-group">

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search name, username, phone or role..."
                        value="<?= htmlspecialchars($search) ?>">

                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="bi bi-search"></i>
                        Search

                    </button>

                    <?php if ($search !== ""): ?>

                        <a
                            href="index.php"
                            class="btn btn-secondary">

                            <i class="bi bi-x-circle"></i>
                            Clear

                        </a>

                    <?php endif; ?>

                </div>

            </form>


            <?php if (empty($users)): ?>

                <div class="text-center py-5">

                    <i class="bi bi-people fs-1 text-muted"></i>

                    <h5 class="mt-3">
                        No Users Found
                    </h5>

                    <p class="text-muted">
                        Add your first system user.
                    </p>

                    <a
                        href="add.php"
                        class="btn btn-primary">

                        <i class="bi bi-person-plus"></i>
                        Add User

                    </a>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>#</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-center">Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($users as $user): ?>

                            <tr>

                                <td>
                                    <strong>
                                        #<?= (int)$user["user_id"] ?>
                                    </strong>
                                </td>

                                <td>

                                    <strong>
                                        <?= htmlspecialchars($user["full_name"]) ?>
                                    </strong>

                                </td>

                                <td>
                                    <?= htmlspecialchars($user["username"]) ?>
                                </td>

                                <td>

                                    <?= !empty($user["phone"])
                                        ? htmlspecialchars($user["phone"])
                                        : '<span class="text-muted">N/A</span>' ?>

                                </td>

                                <td>

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

                                    <span class="badge bg-<?= $roleClass ?>">

                                        <?= htmlspecialchars(
                                            ucfirst($user["role"])
                                        ) ?>

                                    </span>

                                </td>

                                <td>

                                    <?php if ($user["status"] === "active"): ?>

                                        <span class="badge bg-success">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-danger">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $user["created_at"]
                                    ) ?>

                                </td>

                                <td class="text-center">

                                    <div
                                        class="btn-group"
                                        role="group">

                                        <!-- VIEW -->

                                        <a
                                            href="view.php?id=<?= (int)$user["user_id"] ?>"
                                            class="btn btn-sm btn-primary"
                                            title="View">

                                            <i class="bi bi-eye"></i>

                                        </a>


                                        <!-- EDIT -->

                                        <a
                                            href="edit.php?id=<?= (int)$user["user_id"] ?>"
                                            class="btn btn-sm btn-warning"
                                            title="Edit">

                                            <i class="bi bi-pencil-square"></i>

                                        </a>


                                        <!-- DELETE -->

                                        <a
                                            href="delete.php?id=<?= (int)$user["user_id"] ?>"
                                            class="btn btn-sm btn-danger"
                                            title="Delete"
                                            onclick="return confirm('Are you sure you want to delete this user?');">

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

  <!-- javascript links  -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>


</body>
</html>

