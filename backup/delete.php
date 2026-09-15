<?php

session_start();

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
    header("Location: index.php?error=" . urlencode("No backup file specified."));
    exit;
}

/*
|--------------------------------------------------------------------------
| SECURITY
|--------------------------------------------------------------------------
*/

$filename = basename($_GET["file"]);

/*
|--------------------------------------------------------------------------
| ONLY SQL BACKUPS
|--------------------------------------------------------------------------
*/

if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== "sql") {
    header("Location: index.php?error=" . urlencode("Invalid backup file."));
    exit;
}

/*
|--------------------------------------------------------------------------
| BACKUP PATH
|--------------------------------------------------------------------------
*/

$backupDir = __DIR__ . "/files/";
$filePath = $backupDir . $filename;

/*
|--------------------------------------------------------------------------
| CHECK FILE
|--------------------------------------------------------------------------
*/

if (!is_file($filePath)) {
    header("Location: index.php?error=" . urlencode("Backup file not found."));
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE BACKUP
|--------------------------------------------------------------------------
*/

if (unlink($filePath)) {

    header(
        "Location: index.php?success=" .
        urlencode("Backup deleted successfully.")
    );
    exit;

} else {

    header(
        "Location: index.php?error=" .
        urlencode("Unable to delete backup.")
    );
    exit;
}


