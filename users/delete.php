
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";

$user_id = (int)($_GET["id"] ?? 0);

if ($user_id <= 0) {

    header(
        "Location: index.php?error="
        . urlencode("Invalid user ID.")
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Prevent deleting yourself
|--------------------------------------------------------------------------
*/

if ((int)$_SESSION["user_id"] === $user_id) {

    header(
        "Location: index.php?error="
        . urlencode("You cannot delete your own account.")
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Check user
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT user_id, full_name
    FROM users
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    ":user_id" => $user_id
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$user) {

    header(
        "Location: index.php?error="
        . urlencode("User not found.")
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

try {

    $deleteStmt = $conn->prepare("
        DELETE FROM users
        WHERE user_id = :user_id
    ");

    $deleteStmt->execute([
        ":user_id" => $user_id
    ]);


    header(
        "Location: index.php?success="
        . urlencode("User deleted successfully.")
    );

    exit;

} catch (PDOException $e) {

    /*
     * If user is connected to sales/purchases/expenses,
     * foreign keys may prevent deletion depending on
     * your database configuration.
     */

    header(
        "Location: index.php?error="
        . urlencode(
            "This user cannot be deleted because related records exist."
        )
    );

    exit;
}