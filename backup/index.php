<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| ONLY ADMIN CAN ACCESS BACKUP
|--------------------------------------------------------------------------
*/

if (($_SESSION["role"] ?? "") !== "admin") {
    die("Access Denied");
}

/*
|--------------------------------------------------------------------------
| BACKUP DIRECTORY
|--------------------------------------------------------------------------
*/

$backupDir = __DIR__ . "/files/";

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

/*
|--------------------------------------------------------------------------
| GET BACKUP FILES
|--------------------------------------------------------------------------
*/

$files = [];

if (is_dir($backupDir)) {

    $allFiles = scandir($backupDir);

    foreach ($allFiles as $file) {

        if (
            $file !== "." &&
            $file !== ".." &&
            strtolower(pathinfo($file, PATHINFO_EXTENSION)) === "sql" &&
            is_file($backupDir . $file)
        ) {

            $files[] = [
                "name" => $file,
                "size" => filesize($backupDir . $file),
                "time" => filemtime($backupDir . $file)
            ];
        }
    }
}

/*
|--------------------------------------------------------------------------
| SORT NEWEST FIRST
|--------------------------------------------------------------------------
*/

usort($files, function ($a, $b) {
    return $b["time"] <=> $a["time"];
});

/*
|--------------------------------------------------------------------------
| FORMAT FILE SIZE
|--------------------------------------------------------------------------
*/

function formatSize($bytes)
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . " MB";
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . " KB";
    }

    return $bytes . " Bytes";
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

     <link rel="stylesheet" href="../assets/css/backgrounds.css">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Database Backup - Bakery Management</title>

    <link
        href="../assets/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="../assets/css/bootstrap-icons.css">

    <link
        rel="stylesheet"
        href="../assets/css/backgrounds.css">

    <style>
        body {
            background-color: #f5f6fa;
        }

        .card {
            border: none;
            border-radius: 15px;
        }

        .backup-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
        }

        .table td {
            vertical-align: middle;
        }
    </style>

</head>

<body  class="bg-backups">

    <!-- =====================================================
     NAVBAR
===================================================== -->

    <nav class="navbar navbar-light bg-white border-bottom">

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

                    <?= htmlspecialchars(
                        $_SESSION["full_name"] ?? "Admin"
                    ) ?>

                </span>

                <span class="badge bg-primary me-3">

                    <?= htmlspecialchars(
                        $_SESSION["role"] ?? "admin"
                    ) ?>

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


    <!-- =====================================================
     MAIN
===================================================== -->

    <div class="container-fluid p-4">

        <!-- HEADER -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold mb-1">

                    <i class="bi bi-database"></i>

                    Database Backup

                </h2>

                <p class="text-muted mb-0">

                    Create and manage your Bakery Management database backups.

                </p>

            </div>

            <a
                href="../admin/dashboard.php"
                class="btn btn-secondary">

                <i class="bi bi-arrow-left"></i>

                Dashboard

            </a>

        </div>


        <!-- =================================================
         SUCCESS MESSAGE
    ================================================== -->

        <?php if (isset($_GET["success"])): ?>

            <div class="alert alert-success alert-dismissible fade show">

                <i class="bi bi-check-circle-fill"></i>

                <?= htmlspecialchars($_GET["success"]) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>

            </div>

        <?php endif; ?>


        <!-- =================================================
         ERROR MESSAGE
    ================================================== -->

        <?php if (isset($_GET["error"])): ?>

            <div class="alert alert-danger alert-dismissible fade show">

                <i class="bi bi-exclamation-triangle-fill"></i>

                <?= htmlspecialchars($_GET["error"]) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>

            </div>

        <?php endif; ?>


        <!-- =================================================
         BACKUP ACTION
    ================================================== -->

        <div class="row g-4 mb-4">

            <div class="col-md-6">

                <div class="card shadow-sm">

                    <div class="card-body p-4">

                        <div class="d-flex align-items-center">

                            <div class="backup-icon bg-primary-subtle text-primary me-3">

                                <i class="bi bi-database-add"></i>

                            </div>

                            <div>

                                <h5 class="fw-bold mb-1">

                                    Create New Backup

                                </h5>

                                <p class="text-muted mb-0">

                                    Create a complete SQL backup of your database.

                                </p>

                            </div>

                        </div>

                        <hr>

                        <a
                            href="create.php"
                            class="btn btn-primary">

                            <i class="bi bi-database-add"></i>

                            Create Backup

                        </a>

                    </div>

                </div>

            </div>


            <!-- BACKUP INFO -->

            <div class="col-md-6">

                <div class="card shadow-sm">

                    <div class="card-body p-4">

                        <div class="d-flex align-items-center">

                            <div class="backup-icon bg-success-subtle text-success me-3">

                                <i class="bi bi-shield-check"></i>

                            </div>

                            <div>

                                <h5 class="fw-bold mb-1">

                                    Backup Protection

                                </h5>

                                <p class="text-muted mb-0">

                                    Only administrators can manage database backups.

                                </p>

                            </div>

                        </div>

                        <hr>

                        <span class="badge bg-success">

                            <i class="bi bi-check-circle"></i>

                            Admin Only

                        </span>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
         BACKUP HISTORY
    ================================================== -->

        <div class="card shadow-sm">

            <div class="card-header bg-white py-3">

                <h5 class="mb-0">

                    <i class="bi bi-clock-history"></i>

                    Backup History

                </h5>

            </div>

            <div class="card-body">

                <?php if (empty($files)): ?>

                    <div class="text-center text-muted py-5">

                        <i class="bi bi-database-x fs-1"></i>

                        <h5 class="mt-3">

                            No backups found

                        </h5>

                        <p>

                            Create your first database backup.

                        </p>

                        <a
                            href="create.php"
                            class="btn btn-primary">

                            <i class="bi bi-database-add"></i>

                            Create Backup

                        </a>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table table-hover align-middle">

                            <thead class="table-light">

                                <tr>

                                    <th>#</th>

                                    <th>Backup File</th>

                                    <th>Size</th>

                                    <th>Date & Time</th>

                                    <th class="text-end">
                                        Action
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php

                                $number = 1;

                                foreach ($files as $file):

                                ?>

                                    <tr>

                                        <td>
                                            <?= $number++ ?>
                                        </td>

                                        <td>

                                            <i class="bi bi-filetype-sql text-primary"></i>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $file["name"]
                                                ) ?>

                                            </strong>

                                        </td>

                                        <td>

                                            <?= formatSize(
                                                $file["size"]
                                            ) ?>

                                        </td>

                                        <td>

                                            <?= date(
                                                "Y-m-d H:i:s",
                                                $file["time"]
                                            ) ?>

                                        </td>

                                        <td class="text-end">

                                            <!-- DOWNLOAD -->

                                            <a
                                                href="download.php?file=<?= urlencode($file["name"]) ?>"
                                                class="btn btn-sm btn-outline-primary me-1">

                                                <i class="bi bi-download"></i>

                                                Download

                                            </a>

                                            <!-- restore section  -->
                                            <a
                                                href="restore.php?file=<?= urlencode($file["name"]) ?>"
                                                class="btn btn-sm btn-outline-success me-1">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                                Restore
                                            </a>


                                            <!-- DELETE -->

                                            <a
                                                href="delete.php?file=<?= urlencode($file["name"]) ?>"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Are you sure you want to delete this backup?');">

                                                <i class="bi bi-trash"></i>

                                                Delete

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


    <script
        src="../assets/js/bootstrap.bundle.min.js">
    </script>

</body>

</html>