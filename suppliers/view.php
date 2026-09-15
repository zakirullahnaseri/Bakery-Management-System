
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";


/* =====================================================
   GET SUPPLIER ID
===================================================== */

$supplier_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


if ($supplier_id <= 0) {
    header("Location: index.php");
    exit;
}


/* =====================================================
   GET SUPPLIER
===================================================== */

$stmt = $conn->prepare("
    SELECT
        supplier_id,
        name,
        phone,
        address,
        email,
        created_at
    FROM suppliers
    WHERE supplier_id = :supplier_id
");

$stmt->execute([
    ":supplier_id" => $supplier_id
]);

$supplier = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$supplier) {
    die("Supplier not found.");
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        View Supplier - Bakery Management
    </title>


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
            background-color: #f5f6fa;
        }

        .navbar {
            background-color: white;
            border-bottom: 1px solid #ddd;
        }

        .card {
            border: none;
            border-radius: 14px;
        }

        .info-label {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 17px;
            font-weight: 500;
        }

    </style>

</head>


<body>


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar">

    <div class="container-fluid px-4">

        <a
            href="../admin/dashboard.php"
            class="navbar-brand fw-bold"
        >

            <i class="bi bi-shop"></i>

            Bakery System

        </a>


        <div class="d-flex align-items-center">

            <span class="me-3">

                <i class="bi bi-person-circle"></i>

                <?= htmlspecialchars(
                    $_SESSION["full_name"] ?? "User"
                ) ?>

            </span>


            <a
                href="../public/logout.php"
                class="btn btn-outline-danger btn-sm"
            >

                <i class="bi bi-box-arrow-right"></i>

                Logout

            </a>

        </div>

    </div>

</nav>



<!-- =====================================================
     MAIN
===================================================== -->

<div class="container p-4">


    <!-- HEADER -->

    <div
        class="d-flex justify-content-between align-items-center mb-4"
    >

        <div>

            <h2>

                <i class="bi bi-person-vcard"></i>

                Supplier Details

            </h2>

            <p class="text-muted mb-0">

                View supplier information

            </p>

        </div>


        <div>

            <a
                href="index.php"
                class="btn btn-secondary"
            >

                <i class="bi bi-arrow-left"></i>

                Back

            </a>

        </div>

    </div>



    <!-- =====================================================
         SUPPLIER CARD
    ===================================================== -->

    <div class="card shadow-sm">

        <div class="card-header bg-white py-3">

            <div
                class="d-flex justify-content-between align-items-center"
            >

                <h5 class="mb-0">

                    <i class="bi bi-truck"></i>

                    <?= htmlspecialchars(
                        $supplier["name"]
                    ) ?>

                </h5>


                <span class="badge bg-primary">

                    Supplier #

                    <?= (int)
                        $supplier["supplier_id"] ?>

                </span>

            </div>

        </div>


        <div class="card-body">


            <div class="row g-4">


                <!-- NAME -->

                <div class="col-md-6">

                    <div class="info-label">

                        Supplier Name

                    </div>

                    <div class="info-value">

                        <i class="bi bi-person"></i>

                        <?= htmlspecialchars(
                            $supplier["name"]
                        ) ?>

                    </div>

                </div>



                <!-- PHONE -->

                <div class="col-md-6">

                    <div class="info-label">

                        Phone

                    </div>

                    <div class="info-value">

                        <i class="bi bi-telephone"></i>

                        <?= htmlspecialchars(
                            $supplier["phone"]
                            ?: "-"
                        ) ?>

                    </div>

                </div>



                <!-- EMAIL -->

                <div class="col-md-6">

                    <div class="info-label">

                        Email

                    </div>

                    <div class="info-value">

                        <i class="bi bi-envelope"></i>

                        <?= htmlspecialchars(
                            $supplier["email"]
                            ?: "-"
                        ) ?>

                    </div>

                </div>



                <!-- ADDRESS -->

                <div class="col-md-6">

                    <div class="info-label">

                        Address

                    </div>

                    <div class="info-value">

                        <i class="bi bi-geo-alt"></i>

                        <?= htmlspecialchars(
                            $supplier["address"]
                            ?: "-"
                        ) ?>

                    </div>

                </div>



                <!-- CREATED -->

                <div class="col-md-6">

                    <div class="info-label">

                        Created At

                    </div>

                    <div class="info-value">

                        <i class="bi bi-calendar"></i>

                        <?= htmlspecialchars(
                            $supplier["created_at"]
                        ) ?>

                    </div>

                </div>

            </div>


            <hr class="my-4">


            <!-- ACTIONS -->

            <div class="d-flex gap-2">


                <!-- EDIT -->

                <a
                    href="edit.php?id=<?= (int) $supplier["supplier_id"] ?>"
                    class="btn btn-warning"
                >

                    <i class="bi bi-pencil"></i>

                    Edit Supplier

                </a>


                <!-- DELETE -->

                <a
                    href="delete.php?id=<?= (int) $supplier["supplier_id"] ?>"
                    class="btn btn-danger"
                    onclick="return confirm('Are you sure you want to delete this supplier?');"
                >

                    <i class="bi bi-trash"></i>

                    Delete Supplier

                </a>


                <!-- BACK -->

                <a
                    href="index.php"
                    class="btn btn-secondary"
                >

                    <i class="bi bi-arrow-left"></i>

                    Back to Suppliers

                </a>

            </div>

        </div>

    </div>

</div>



    <!-- javascript links  -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>



</body>

</html>

