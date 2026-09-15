<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {

    $message = $isPashto
        ? "د خام موادو ID ناسم دی."
        : "Invalid ingredient ID.";

    header(
        "Location: index.php?error="
        . urlencode($message)
        . "&lang="
        . urlencode(currentLanguage())
    );

    exit;
}


/* ===============================
   CHECK INGREDIENT
================================ */

$stmt = $conn->prepare("
    SELECT
        ingredient_id,
        ingredient_name
    FROM ingredients
    WHERE ingredient_id = :id
");

$stmt->execute([
    ":id" => $id
]);

$ingredient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ingredient) {

    $message = $isPashto
        ? "خام مواد ونه موندل شول."
        : "Ingredient not found.";

    header(
        "Location: index.php?error="
        . urlencode($message)
        . "&lang="
        . urlencode(currentLanguage())
    );

    exit;
}


/* ===============================
   DELETE
================================ */

try {

    $delete = $conn->prepare("
        DELETE FROM ingredients
        WHERE ingredient_id = :id
    ");

    $delete->execute([
        ":id" => $id
    ]);


    $message = $isPashto
        ? "خام مواد په بریالیتوب سره حذف شول."
        : "Ingredient deleted successfully.";

    header(
        "Location: index.php?success="
        . urlencode($message)
        . "&lang="
        . urlencode(currentLanguage())
    );

    exit;

} catch (PDOException $e) {

    /*
     * This can happen if the ingredient
     * is already used in product_ingredients
     * or purchase_items.
     */

    $message = $isPashto
        ? "دا خام مواد حذف کېدای نشي، ځکه چې په بل ریکارډ کې استعمال شوي دي."
        : "Cannot delete this ingredient because it is already used in another record.";

    header(
        "Location: index.php?error="
        . urlencode($message)
        . "&lang="
        . urlencode(currentLanguage())
    );

    exit;
}