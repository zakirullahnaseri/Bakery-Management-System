
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
        ? "پېرېدونکی په بریالیتوب سره اضافه شو."
        : "Customer added successfully.";
}


/*
|--------------------------------------------------------------------------
| Add Customer
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_customer"])) {

    $name    = trim($_POST["name"] ?? "");
    $phone   = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $email   = trim($_POST["email"] ?? "");


    if ($name === "") {

        $error = currentLanguage() === "ps"
            ? "د پېرېدونکي نوم ضروري دی."
            : "Customer name is required.";

    } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = currentLanguage() === "ps"
            ? "د ایمیل پته سمه نه ده."
            : "Invalid email address.";

    } else {

        try {

            $stmt = $conn->prepare("
                INSERT INTO customers
                (name, phone, address, email)
                VALUES
                (:name, :phone, :address, :email)
            ");

            $stmt->execute([
                ":name"    => $name,
                ":phone"   => $phone,
                ":address" => $address,
                ":email"   => $email
            ]);


            /*
            |--------------------------------------------------------------------------
            | IMPORTANT:
            | Redirect after successful POST.
            | This prevents duplicate insertion on refresh.
            |--------------------------------------------------------------------------
            */

            $lang = currentLanguage();

            header(
                "Location: index.php?success=1&lang=" . urlencode($lang)
            );

            exit;


        } catch (PDOException $e) {

            $error = currentLanguage() === "ps"
                ? "د پېرېدونکي په اضافه کولو کې ستونزه رامنځته شوه."
                : "Error occurred while adding customer.";
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
            customer_id,
            name,
            phone,
            address,
            email,
            created_at
        FROM customers
        WHERE
            name LIKE :search
            OR phone LIKE :search
            OR email LIKE :search
            OR address LIKE :search
        ORDER BY customer_id DESC
    ");

    $stmt->execute([
        ":search" => "%" . $search . "%"
    ]);

} else {

    $stmt = $conn->query("
        SELECT
            customer_id,
            name,
            phone,
            address,
            email,
            created_at
        FROM customers
        ORDER BY customer_id DESC
    ");
}


$customers = $stmt->fetchAll();

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
        <?= htmlspecialchars(t("customers")) ?>
    </title>


    <link
        rel="stylesheet"
        href="../assets/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/bootstrap-icons.css"
    >

</head>


<body class="bg-light bg-customers">


<div class="container-fluid py-4">


    <!--
    |--------------------------------------------------------------------------
    | Header
    |--------------------------------------------------------------------------
    -->

    <div class="d-flex justify-content-between align-items-center mb-4">


        <div>

            <h2 class="fw-bold">

                <i class="bi bi-people"></i>

                <?= htmlspecialchars(t("customers")) ?>

            </h2>


            <small class="text-muted">

                <?= htmlspecialchars(
                    $_SESSION["name"] ??
                    $_SESSION["username"] ??
                    ""
                ) ?>

            </small>

        </div>



        <div class="d-flex gap-2">


            <a
                href="?lang=ps"
                class="btn btn-outline-primary"
            >
                پښتو
            </a>


            <a
                href="?lang=en"
                class="btn btn-outline-secondary"
            >
                English
            </a>


            <a
                href="../admin/dashboard.php"
                class="btn btn-dark"
            >

                <i class="bi bi-speedometer2"></i>

                <?= htmlspecialchars(t("dashboard")) ?>

            </a>


            <a
                href="../public/login.php?logout=1"
                class="btn btn-danger"
            >

                <i class="bi bi-box-arrow-right"></i>

                <?= htmlspecialchars(t("logout")) ?>

            </a>


        </div>


    </div>



    <!--
    |--------------------------------------------------------------------------
    | Success Message
    |--------------------------------------------------------------------------
    -->

    <?php if ($message !== ""): ?>

        <div class="alert alert-success">

            <i class="bi bi-check-circle"></i>

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>



    <!--
    |--------------------------------------------------------------------------
    | Error Message
    |--------------------------------------------------------------------------
    -->

    <?php if ($error !== ""): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>



    <!--
    |--------------------------------------------------------------------------
    | Add Customer
    |--------------------------------------------------------------------------
    -->

    <div class="card shadow-sm mb-4">


        <div class="card-header bg-primary text-white">


            <h5 class="mb-0">

                <i class="bi bi-person-plus"></i>


                <?= currentLanguage() === "ps"
                    ? "نوی پېرېدونکی اضافه کول"
                    : "Add Customer"
                ?>

            </h5>


        </div>



        <div class="card-body">


            <form method="POST">


                <div class="row g-3">


                    <!-- Name -->

                    <div class="col-md-3">


                        <label class="form-label fw-bold">

                            <?= currentLanguage() === "ps"
                                ? "نوم"
                                : "Name"
                            ?>

                        </label>


                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            required
                        >


                    </div>



                    <!-- Phone -->

                    <div class="col-md-3">


                        <label class="form-label fw-bold">

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

                    <div class="col-md-3">


                        <label class="form-label fw-bold">

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

                    <div class="col-md-3">


                        <label class="form-label fw-bold">

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



                    <!-- Add Button -->

                    <div class="col-12">


                        <button
                            type="submit"
                            name="add_customer"
                            class="btn btn-success"
                        >

                            <i class="bi bi-plus-circle"></i>


                            <?= currentLanguage() === "ps"
                                ? "پېرېدونکی اضافه کړه"
                                : "Add Customer"
                            ?>

                        </button>


                    </div>


                </div>


            </form>


        </div>


    </div>



    <!--
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    -->

    <div class="card shadow-sm mb-4">


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
                            ? "د پېرېدونکي نوم، تلیفون یا ایمیل ولټوئ..."
                            : "Search customer by name, phone or email..."
                        ?>"
                    >


                </div>



                <div class="col-md-2 d-grid">


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-search"></i>


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
    | Customers List
    |--------------------------------------------------------------------------
    -->

    <div class="card shadow-sm">


        <div class="card-header">


            <h5 class="mb-0">

                <i class="bi bi-list-ul"></i>


                <?= currentLanguage() === "ps"
                    ? "د پېرېدونکو لېست"
                    : "Customers List"
                ?>

            </h5>


        </div>



        <div class="card-body p-0">


            <div class="table-responsive">


                <table class="table table-bordered table-hover mb-0">


                    <thead class="table-dark">


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
                                    ? "د ثبت نېټه"
                                    : "Created At"
                                ?>

                            </th>


                            <th>

                                <?= currentLanguage() === "ps"
                                    ? "عمل"
                                    : "Actions"
                                ?>

                            </th>


                        </tr>


                    </thead>



                    <tbody>


                    <?php if (count($customers) > 0): ?>


                        <?php foreach ($customers as $customer): ?>


                            <tr>


                                <td>

                                    <?= (int)$customer["customer_id"] ?>

                                </td>



                                <td class="fw-bold">

                                    <?= htmlspecialchars(
                                        $customer["name"]
                                    ) ?>

                                </td>



                                <td>

                                    <?= htmlspecialchars(
                                        $customer["phone"] ?? ""
                                    ) ?>

                                </td>



                                <td>

                                    <?= htmlspecialchars(
                                        $customer["address"] ?? ""
                                    ) ?>

                                </td>



                                <td>

                                    <?= htmlspecialchars(
                                        $customer["email"] ?? ""
                                    ) ?>

                                </td>



                                <td>

                                    <?= htmlspecialchars(
                                        $customer["created_at"] ?? ""
                                    ) ?>

                                </td>



                                <td>


                                    <!-- Edit -->

                                    <a
                                        href="edit.php?customer_id=<?= (int)$customer["customer_id"] ?>"
                                        class="btn btn-sm btn-warning"
                                    >

                                        <i class="bi bi-pencil"></i>


                                        <?= currentLanguage() === "ps"
                                            ? "سمون"
                                            : "Edit"
                                        ?>

                                    </a>



                                    <!-- Delete -->

                                    <a
                                        href="delete.php?customer_id=<?= (int)$customer["customer_id"] ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('<?= currentLanguage() === "ps"
                                            ? "ایا دا پېرېدونکی حذف کول غواړئ؟"
                                            : "Are you sure you want to delete this customer?"
                                        ?>');"
                                    >

                                        <i class="bi bi-trash"></i>


                                        <?= currentLanguage() === "ps"
                                            ? "حذف"
                                            : "Delete"
                                        ?>

                                    </a>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>


                            <td
                                colspan="7"
                                class="text-center py-4 text-muted"
                            >


                                <i class="bi bi-info-circle"></i>


                                <?= currentLanguage() === "ps"
                                    ? "هیڅ پېرېدونکی پیدا نه شو."
                                    : "No customers found."
                                ?>


                            </td>


                        </tr>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>


        </div>


    </div>


</div>



<script src="../assets/js/bootstrap.bundle.min.js"></script>


</body>

</html>
