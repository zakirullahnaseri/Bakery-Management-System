
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";

$lang = $isPashto ? "ps" : "en";

$t = [
    "en" => [
        "invalid_id"      => "Invalid expense ID.",
        "not_found"       => "Expense not found.",
        "deleted"         => "Expense deleted successfully.",
        "failed"          => "Failed to delete expense.",
        "invalid_request" => "Invalid request."
    ],

    "ps" => [
        "invalid_id"      => "د مصرف ID ناسم دی.",
        "not_found"       => "مصرف پیدا نه شو.",
        "deleted"         => "مصرف په بریالیتوب سره حذف شو.",
        "failed"          => "د مصرف په حذفولو کې ستونزه رامنځته شوه.",
        "invalid_request" => "ناسمه غوښتنه ده."
    ]
];

$text = $t[$lang];


/* ===============================
   ONLY POST REQUEST ALLOWED
================================ */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header(
        "Location: index.php?error="
        . urlencode($text["invalid_request"])
    );

    exit;
}


/* ===============================
   EXPENSE ID
================================ */

$id = (int) ($_POST["id"] ?? 0);

if ($id <= 0) {

    header(
        "Location: index.php?error="
        . urlencode($text["invalid_id"])
    );

    exit;
}


/* ===============================
   CHECK EXPENSE
================================ */

$stmt = $conn->prepare("
    SELECT expense_id
    FROM expenses
    WHERE expense_id = :id
");

$stmt->execute([
    ":id" => $id
]);

$expense = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$expense) {

    header(
        "Location: index.php?error="
        . urlencode($text["not_found"])
    );

    exit;
}


/* ===============================
   DELETE EXPENSE
================================ */

try {

    $deleteStmt = $conn->prepare("
        DELETE FROM expenses
        WHERE expense_id = :id
    ");

    $deleteStmt->execute([
        ":id" => $id
    ]);

    /*
        POST → DELETE → REDIRECT → GET

        Refreshing index.php will not delete again.
    */

    header(
        "Location: index.php?success="
        . urlencode($text["deleted"])
    );

    exit;

} catch (PDOException $e) {

    header(
        "Location: index.php?error="
        . urlencode($text["failed"])
    );

    exit;
}
