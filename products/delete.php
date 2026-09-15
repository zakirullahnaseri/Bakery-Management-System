<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";


/* =====================================================
   GET PRODUCT ID
===================================================== */

$product_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($product_id <= 0) {
    header("Location: index.php");
    exit;
}


/* =====================================================
   CHECK PRODUCT
===================================================== */

$productStmt = $conn->prepare("
    SELECT
        product_id,
        product_name
    FROM products
    WHERE product_id = :product_id
");

$productStmt->execute([
    ":product_id" => $product_id
]);

$product = $productStmt->fetch(PDO::FETCH_ASSOC);


if (!$product) {
    die(
        $isPashto
            ? "محصول ونه موندل شو."
            : "Product not found."
    );
}


/* =====================================================
   DELETE PRODUCT
===================================================== */

try {

    $conn->beginTransaction();


    /* =================================================
       DELETE PRODUCT
    ================================================= */

    $deleteStmt = $conn->prepare("
        DELETE FROM products
        WHERE product_id = :product_id
    ");

    $deleteStmt->execute([
        ":product_id" => $product_id
    ]);


    if ($deleteStmt->rowCount() === 0) {

        throw new Exception(
            $isPashto
                ? "محصول حذف نه شو."
                : "Product could not be deleted."
        );
    }


    /* =================================================
       COMMIT
    ================================================= */

    $conn->commit();


    $lang = currentLanguage();

    header(
        "Location: index.php?deleted=1&lang="
        . urlencode($lang)
    );

    exit;


} catch (Exception $e) {


    /* =================================================
       ROLLBACK
    ================================================= */

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }


    $errorMessage = $isPashto
        ? "د محصول د حذف پر مهال ستونزه رامنځته شوه."
        : "Error deleting product: "
            . $e->getMessage();

    die(
        htmlspecialchars($errorMessage)
    );
}

?>
