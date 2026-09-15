
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";


/* =====================================================
   GET RECIPE ID
===================================================== */

$recipe_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


if ($recipe_id <= 0) {

    header("Location: index.php");
    exit;
}


/* =====================================================
   CHECK RECIPE
===================================================== */

$stmt = $conn->prepare("
    SELECT
        pi.product_ingredient_id,
        p.product_name,
        i.ingredient_name,
        pi.quantity_required,
        i.unit

    FROM product_ingredients pi

    INNER JOIN products p
        ON pi.product_id = p.product_id

    INNER JOIN ingredients i
        ON pi.ingredient_id = i.ingredient_id

    WHERE pi.product_ingredient_id = :recipe_id
");

$stmt->execute([
    ":recipe_id" => $recipe_id
]);

$recipe = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$recipe) {

    die("Recipe ingredient not found.");
}


/* =====================================================
   DELETE RECIPE INGREDIENT
===================================================== */

try {

    $deleteStmt = $conn->prepare("
        DELETE FROM product_ingredients
        WHERE product_ingredient_id = :recipe_id
    ");

    $deleteStmt->execute([
        ":recipe_id" => $recipe_id
    ]);


    if ($deleteStmt->rowCount() === 0) {

        throw new Exception(
            "Recipe ingredient could not be deleted."
        );
    }


    /* =================================================
       SUCCESS
    ================================================= */

    header(
        "Location: index.php?deleted=1"
    );

    exit;


} catch (PDOException $e) {

    /* =================================================
       DATABASE ERROR
    ================================================= */

    die(
        "Database error while deleting recipe ingredient: "
        . htmlspecialchars($e->getMessage())
    );

} catch (Exception $e) {

    /* =================================================
       GENERAL ERROR
    ================================================= */

    die(
        "Error deleting recipe ingredient: "
        . htmlspecialchars($e->getMessage())
    );
}

?>
