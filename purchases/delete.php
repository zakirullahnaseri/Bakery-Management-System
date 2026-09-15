<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| ONLY POST
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CHECK CSRF
|--------------------------------------------------------------------------
*/
if (
    !isset($_POST["csrf_token"]) ||
    !hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"]
    )
) {
    $message = $isPashto
        ? "د امنیت ټوکن ناسم دی."
        : "Invalid security token.";

    header("Location: index.php?error=" . urlencode($message));
    exit;
}

/*
|--------------------------------------------------------------------------
| PURCHASE ID
|--------------------------------------------------------------------------
*/
$purchase_id = isset($_POST["purchase_id"])
    ? (int) $_POST["purchase_id"]
    : 0;

if ($purchase_id <= 0) {
    header("Location: index.php");
    exit;
}

try {

    $conn->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | GET PURCHASE
    |--------------------------------------------------------------------------
    */
    $purchaseStmt = $conn->prepare("
        SELECT
            purchase_id
        FROM purchases
        WHERE purchase_id = :purchase_id
        LIMIT 1
        FOR UPDATE
    ");

    $purchaseStmt->execute([
        ":purchase_id" => $purchase_id
    ]);

    $purchase = $purchaseStmt->fetch(PDO::FETCH_ASSOC);

    if (!$purchase) {

        $message = $isPashto
            ? "پېرود ونه موندل شو."
            : "Purchase not found.";

        throw new Exception($message);
    }

    /*
    |--------------------------------------------------------------------------
    | GET PURCHASE ITEMS
    |--------------------------------------------------------------------------
    */
    $itemStmt = $conn->prepare("
        SELECT
            purchase_item_id,
            ingredient_id,
            quantity
        FROM purchase_items
        WHERE purchase_id = :purchase_id
        ORDER BY purchase_item_id ASC
    ");

    $itemStmt->execute([
        ":purchase_id" => $purchase_id
    ]);

    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | LOCK ALL INGREDIENTS FIRST
    |--------------------------------------------------------------------------
    */
    $ingredientStocks = [];

    foreach ($items as $item) {

        $ingredient_id = (int) $item["ingredient_id"];

        $quantity = (float) $item["quantity"];

        if (
            $ingredient_id <= 0 ||
            $quantity <= 0
        ) {

            $message = $isPashto
                ? "په پېرود کې ناسم توکي موجود دي."
                : "Invalid purchase item found.";

            throw new Exception($message);
        }

        /*
        | If same ingredient appears more than once,
        | combine quantity.
        */
        if (!isset($ingredientStocks[$ingredient_id])) {

            $ingredientStocks[$ingredient_id] = [
                "quantity" => 0
            ];
        }

        $ingredientStocks[$ingredient_id]["quantity"] += $quantity;
    }

    /*
    |--------------------------------------------------------------------------
    | REDUCE STOCK
    |--------------------------------------------------------------------------
    */
    foreach (
        $ingredientStocks as $ingredient_id => $stockData
    ) {

        $quantity = (float) $stockData["quantity"];

        $ingredientStmt = $conn->prepare("
            SELECT
                ingredient_id,
                ingredient_name,
                current_stock
            FROM ingredients
            WHERE ingredient_id = :ingredient_id
            FOR UPDATE
        ");

        $ingredientStmt->execute([
            ":ingredient_id" => $ingredient_id
        ]);

        $ingredient = $ingredientStmt->fetch(PDO::FETCH_ASSOC);

        if (!$ingredient) {

            $message = $isPashto
                ? "د اجزاوو ID "
                    . $ingredient_id
                    . " ونه موندل شو."
                : "Ingredient ID "
                    . $ingredient_id
                    . " was not found.";

            throw new Exception($message);
        }

        $current_stock = (float) $ingredient["current_stock"];

        /*
        | Prevent negative stock
        */
        if ($current_stock < $quantity) {

            if ($isPashto) {

                $message =
                    "دا پېرود حذف کېدای نشي. "
                    . "د '"
                    . $ingredient["ingredient_name"]
                    . "' اجزاوو موجودي یوازې "
                    . $current_stock
                    . " ده، خو "
                    . $quantity
                    . " باید له موجودۍ څخه کم شي.";

            } else {

                $message =
                    "Cannot delete this purchase. "
                    . "Ingredient '"
                    . $ingredient["ingredient_name"]
                    . "' has only "
                    . $current_stock
                    . " in stock, but "
                    . $quantity
                    . " must be removed.";
            }

            throw new Exception($message);
        }

        /*
        | Reduce stock
        */
        $stockStmt = $conn->prepare("
            UPDATE ingredients
            SET current_stock =
                current_stock - :quantity
            WHERE ingredient_id =
                :ingredient_id
        ");

        $stockStmt->execute([
            ":quantity" => $quantity,
            ":ingredient_id" => $ingredient_id
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE PURCHASE
    |--------------------------------------------------------------------------
    |
    | purchase_items will be deleted automatically
    | because your database has:
    |
    | ON DELETE CASCADE
    |
    */
    $deleteStmt = $conn->prepare("
        DELETE FROM purchases
        WHERE purchase_id = :purchase_id
    ");

    $deleteStmt->execute([
        ":purchase_id" => $purchase_id
    ]);

    if ($deleteStmt->rowCount() !== 1) {

        $message = $isPashto
            ? "پېرود حذف نه شو."
            : "Purchase could not be deleted.";

        throw new Exception($message);
    }

    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */
    $conn->commit();

    /*
    |--------------------------------------------------------------------------
    | REDIRECT
    |--------------------------------------------------------------------------
    */
    header(
        "Location: index.php?deleted=1"
    );

    exit;

} catch (Exception $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    /*
    |--------------------------------------------------------------------------
    | REDIRECT ERROR
    |--------------------------------------------------------------------------
    */
    $message = urlencode(
        $e->getMessage()
    );

    header(
        "Location: index.php?error="
        . $message
    );

    exit;
}