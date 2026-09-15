<?php

session_start();

/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| ADMIN ONLY
|--------------------------------------------------------------------------
*/

if (($_SESSION["role"] ?? "") !== "admin") {
    die("Access Denied");
}

/*
|--------------------------------------------------------------------------
| GET FILE NAME
|--------------------------------------------------------------------------
*/

if (!isset($_GET["file"]) || empty($_GET["file"])) {
    die("No backup file specified.");
}

$filename = basename($_GET["file"]);

/*
|--------------------------------------------------------------------------
| SECURITY CHECK
|--------------------------------------------------------------------------
|
| Only .sql backup files are allowed.
|
*/

if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== "sql") {
    die("Invalid backup file.");
}

/*
|--------------------------------------------------------------------------
| BACKUP FILE PATH
|--------------------------------------------------------------------------
*/

$backupDir = __DIR__ . "/files/";
$filePath = $backupDir . $filename;

/*
|--------------------------------------------------------------------------
| CHECK FILE EXISTS
|--------------------------------------------------------------------------
*/

if (!is_file($filePath)) {
    die("Backup file not found.");
}

/*
|--------------------------------------------------------------------------
| DOWNLOAD FILE
|--------------------------------------------------------------------------
*/

header("Content-Description: File Transfer");
header("Content-Type: application/sql");
header(
    'Content-Disposition: attachment; filename="' .
    $filename .
    '"'
);
header("Content-Length: " . filesize($filePath));
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: public");

readfile($filePath);
exit;
