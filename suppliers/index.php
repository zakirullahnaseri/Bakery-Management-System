
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Success Message After Redirect
|--------------------------------------------------------------------------
*/
if (isset($_GET["success"]) && $_GET["success"] === "1") {

    $message = currentLanguage() === "ps"
        ? "عرضه کوونکی په بریالیتوب سره اضافه شو."
        : "Supplier added successfully.";
}


/*
|--------------------------------------------------------------------------
| Add Supplier
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_supplier"])) {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if ($name === "") {

        $error = currentLanguage() === "ps"
            ? "د عرضه کوونکي نوم ضروري دی."
            : "Supplier name is required.";

    } else {

        try {

            $stmt = $conn->prepare("
                INSERT INTO suppliers
                (name, phone, address, email)
                VALUES
                (:name, :phone, :address, :email)
            ");

            $stmt->execute([
                ":name" => $name,
                ":phone" => $phone,
                ":address" => $address,
                ":email" => $email
            ]);

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT:
            | Redirect after successful POST.
            | This prevents duplicate insertion when page is refreshed.
            |--------------------------------------------------------------------------
            */

            $lang = currentLanguage();

            header(
                "Location: index.php?success=1&lang=" . urlencode($lang)
            );

            exit;

        } catch (PDOException $e) {

            $error = currentLanguage() === "ps"
                ? "د عرضه کوونکي په اضافه کولو کې ستونزه رامنځته شوه."
                : "Failed to add supplier.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $stmt = $conn->prepare("
        SELECT
            supplier_id,
            name,
            phone,
            address,
            email,
            created_at
        FROM suppliers
        WHERE
            name LIKE :search
            OR phone LIKE :search
            OR address LIKE :search
            OR email LIKE :search
        ORDER BY name ASC
    ");

    $stmt->execute([
        ":search" => "%" . $search . "%"
    ]);

} else {

    $stmt = $conn->query("
        SELECT
            supplier_id,
            name,
            phone,
            address,
            email,
            created_at
        FROM suppliers
        ORDER BY name ASC
    ");
}

$suppliers = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html
    lang="<?= currentLanguage() ?>"
    dir="<?= languageDirection() ?>"
>

<head>

    <meta charset="UTF-8">

<link rel="stylesheet" href="../assets/css/backgrounds.css">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= currentLanguage() === "ps"
            ? "عرضه کوونکي"
            : "Suppliers"
        ?>
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/bootstrap.min.css"
    >

    <style>

        body {
            background-color: #f8f9fa;
        }

        <?php if (currentLanguage() === "ps"): ?>

        body {
            direction: rtl;
            text-align: right;
        }

        .form-control,
        .form-select {
            text-align: right;
        }

        .table th,
        .table td {
            text-align: right;
        }

        <?php endif; ?>

    </style>

</head>


<body class="bg-suppliers">


<div class="container mt-4">


    <!--
    |--------------------------------------------------------------------------
    | Header
    |--------------------------------------------------------------------------
    -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>

            <?= currentLanguage() === "ps"
                ? "عرضه کوونکي"
                : "Suppliers"
            ?>

        </h2>


        <div>
             <a href="../admin/dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left">Dashboard</i> 

        </a>

            <a
                href="?lang=ps"
                class="btn btn-outline-primary btn-sm"
            >
                پښتو
            </a>


            <a
                href="?lang=en"
                class="btn btn-outline-secondary btn-sm"
            >
                English
            </a>

        </div>

    </div>



    <!--
    |--------------------------------------------------------------------------
    | Success Message
    |--------------------------------------------------------------------------
    -->

    <?php if ($message): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>



    <!--
    |--------------------------------------------------------------------------
    | Error Message
    |--------------------------------------------------------------------------
    -->

    <?php if ($error): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>



    <!--
    |--------------------------------------------------------------------------
    | Add Supplier
    |--------------------------------------------------------------------------
    -->

    <div class="card mb-4">

        <div class="card-header">

            <strong>

                <?= currentLanguage() === "ps"
                    ? "نوی عرضه کوونکی اضافه کړئ"
                    : "Add New Supplier"
                ?>

            </strong>

        </div>


        <div class="card-body">


            <form method="POST">


                <div class="row">


                    <!-- Name -->

                    <div class="col-md-6 mb-3">

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
                        >

                    </div>



                    <!-- Phone -->

                    <div class="col-md-6 mb-3">

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
                        >

                    </div>



                    <!-- Address -->

                    <div class="col-md-6 mb-3">

                        <label class="form-label">

                            <?= currentLanguage() === "ps"
                                ? "پته"
                                : "Address"
                            ?>

                        </label>


                        <input
                            type="text"
                            name="address"
                            class="form-control"
                        >

                    </div>



                    <!-- Email -->

                    <div class="col-md-6 mb-3">

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
                        >

                    </div>

                </div>



                <!-- Add Button -->

                <button
                    type="submit"
                    name="add_supplier"
                    class="btn btn-primary"
                >

                    <?= currentLanguage() === "ps"
                        ? "عرضه کوونکی اضافه کړئ"
                        : "Add Supplier"
                    ?>

                </button>


            </form>


        </div>

    </div>



    <!--
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    -->

    <div class="card mb-4">

        <div class="card-body">


            <form
                method="GET"
                class="row g-2"
            >


                <div class="col-md-10">

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="<?= currentLanguage() === "ps"
                            ? "د عرضه کوونکي نوم، تلیفون، پته یا ایمیل ولټوئ..."
                            : "Search by supplier name, phone, address or email..."
                        ?>"
                    >

                </div>



                <div class="col-md-2">

                    <button
                        type="submit"
                        class="btn btn-success w-100"
                    >

                        <?= currentLanguage() === "ps"
                            ? "لټون"
                            : "Search"
                        ?>

                    </button>

                </div>


            </form>


        </div>

    </div>



    <!--
    |--------------------------------------------------------------------------
    | Suppliers List
    |--------------------------------------------------------------------------
    -->

    <div class="card">


        <div class="card-header">

            <strong>

                <?= currentLanguage() === "ps"
                    ? "د عرضه کوونکو لست"
                    : "Supplier List"
                ?>

            </strong>

        </div>



        <div class="card-body p-0">


            <div class="table-responsive">


                <table class="table table-bordered table-striped mb-0">


                    <thead>

                    <tr>


                        <th>
                            #
                        </th>


                        <th>

                            <?= currentLanguage() === "ps"
                                ? "نوم"
                                : "Name"
                            ?>

                        </th>


                        <th>

                            <?= currentLanguage() === "ps"
                                ? "تلیفون"
                                : "Phone"
                            ?>

                        </th>


                        <th>

                            <?= currentLanguage() === "ps"
                                ? "پته"
                                : "Address"
                            ?>

                        </th>


                        <th>

                            <?= currentLanguage() === "ps"
                                ? "ایمیل"
                                : "Email"
                            ?>

                        </th>


                        <th>

                            <?= currentLanguage() === "ps"
                                ? "د جوړېدو نېټه"
                                : "Created At"
                            ?>

                        </th>


                        <th>

                            <?= currentLanguage() === "ps"
                                ? "عمل"
                                : "Action"
                            ?>

                        </th>


                    </tr>

                    </thead>



                    <tbody>


                    <?php if (empty($suppliers)): ?>


                        <tr>


                            <td
                                colspan="7"
                                class="text-center"
                            >

                                <?= currentLanguage() === "ps"
                                    ? "هیڅ عرضه کوونکی ونه موندل شو."
                                    : "No suppliers found."
                                ?>

                            </td>


                        </tr>


                    <?php else: ?>


                        <?php foreach ($suppliers as $supplier): ?>


                            <tr>


                                <td>

                                    <?= (int)$supplier["supplier_id"] ?>

                                </td>



                                <td>

                                    <?= htmlspecialchars(
                                        $supplier["name"]
                                    ) ?>

                                </td>



                                <td>

                                    <?= htmlspecialchars(
                                        $supplier["phone"] ?? ""
                                    ) ?>

                                </td>



                                <td>

                                    <?= htmlspecialchars(
                                        $supplier["address"] ?? ""
                                    ) ?>

                                </td>



                                <td>

                                    <?= htmlspecialchars(
                                        $supplier["email"] ?? ""
                                    ) ?>

                                </td>



                                <td>

                                    <?= htmlspecialchars(
                                        $supplier["created_at"] ?? ""
                                    ) ?>

                                </td>



                                <td>


                                    <!-- Edit -->

                                    <a
                                        href="edit.php?supplier_id=<?= (int)$supplier["supplier_id"] ?>"
                                        class="btn btn-sm btn-warning"
                                    >

                                        <?= currentLanguage() === "ps"
                                            ? "سمون"
                                            : "Edit"
                                        ?>

                                    </a>



                                    <!-- Delete -->

                                    <a
                                        href="delete.php?supplier_id=<?= (int)$supplier["supplier_id"] ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('<?= currentLanguage() === "ps"
                                            ? "ایا ډاډه یاست چې دا عرضه کوونکی حذف کړئ؟"
                                            : "Are you sure you want to delete this supplier?"
                                        ?>')"
                                    >

                                        <?= currentLanguage() === "ps"
                                            ? "حذف"
                                            : "Delete"
                                        ?>

                                    </a>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>


        </div>


    </div>


</div>


</body>

</html>
