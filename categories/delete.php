
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";

$text = [
    "invalid_request" => $isPashto
        ? "ناسمه غوښتنه."
        : "Invalid request.",

    "not_found" => $isPashto
        ? "کټګوري پیدا نه شوه."
        : "Category not found.",

    "deleted" => $isPashto
        ? "کټګوري په بریالیتوب حذف شوه."
        : "Category deleted successfully.",

    "used" => $isPashto
        ? "دا کټګوري د نورو معلوماتو سره تړلې ده او حذف کېدای نشي."
        : "This category is in use and cannot be deleted.",

    "failed" => $isPashto
        ? "کټګوري حذف نه شوه."
        : "Category could not be deleted."
];


/* =====================================================
   ONLY POST REQUEST ALLOWED
===================================================== */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header(
        "Location: index.php?error=" .
        urlencode($text["invalid_request"])
    );

    exit;
}


/* =====================================================
   GET CATEGORY ID FROM POST
===================================================== */

$category_id = isset($_POST["id"])
    ? (int) $_POST["id"]
    : 0;


if ($category_id <= 0) {

    header(
        "Location: index.php?error=" .
        urlencode($text["invalid_request"])
    );

    exit;
}


/* =====================================================
   CHECK CATEGORY EXISTS
===================================================== */

$stmt = $conn->prepare("
    SELECT category_id
    FROM categories
    WHERE category_id = :category_id
    LIMIT 1
");

$stmt->execute([
    ":category_id" => $category_id
]);

$category = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$category) {

    header(
        "Location: index.php?error=not_found"
    );

    exit;
}


/* =====================================================
   DELETE CATEGORY
===================================================== */

try {

    $deleteStmt = $conn->prepare("
        DELETE FROM categories
        WHERE category_id = :category_id
    ");

    $deleteStmt->execute([
        ":category_id" => $category_id
    ]);


    /* =================================================
       CHECK DELETE RESULT
    ================================================= */

    if ($deleteStmt->rowCount() === 0) {

        header(
            "Location: index.php?error=failed"
        );

        exit;
    }


    /* =================================================
       SUCCESS
    ================================================= */

    header(
        "Location: index.php?success=deleted"
    );

    exit;


} catch (PDOException $e) {

    /*
     * SQLSTATE 23000 usually means an integrity
     * constraint / foreign-key problem.
     */

    if ($e->getCode() === "23000") {

        header(
            "Location: index.php?error=used"
        );

        exit;
    }


    /*
     * Do not show database error details to users.
     */

    header(
        "Location: index.php?error=failed"
    );

    exit;
}

?>
