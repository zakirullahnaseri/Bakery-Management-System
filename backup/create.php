<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";

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
| BACKUP FOLDER
|--------------------------------------------------------------------------
*/

$backupDir = __DIR__ . "/files/";

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

/*
|--------------------------------------------------------------------------
| DATABASE INFORMATION
|--------------------------------------------------------------------------
|
| IMPORTANT:
| These variables should match your config/database.php
|
*/

$dbHost = "localhost";
$dbName = "bakery_management";
$dbUser = "root";
$dbPass = "";

/*
|--------------------------------------------------------------------------
| CREATE BACKUP FILE
|--------------------------------------------------------------------------
*/

$filename = "bakery_backup_" . date("Y-m-d_H-i-s") . ".sql";

$filePath = $backupDir . $filename;

try {

    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $sql = "";

    $sql .= "-- Bakery Management System Database Backup\n";
    $sql .= "-- Database: " . $dbName . "\n";
    $sql .= "-- Date: " . date("Y-m-d H:i:s") . "\n\n";

    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";


    /*
    |--------------------------------------------------------------------------
    | GET TABLES
    |--------------------------------------------------------------------------
    */

    $tablesStmt = $pdo->query("SHOW TABLES");

    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);


    /*
    |--------------------------------------------------------------------------
    | EXPORT TABLE STRUCTURE + DATA
    |--------------------------------------------------------------------------
    */

    foreach ($tables as $table) {

        $sql .= "\n";
        $sql .= "-- ----------------------------------------\n";
        $sql .= "-- Table: `$table`\n";
        $sql .= "-- ----------------------------------------\n\n";


        /*
        |--------------------------------------------------------------
        | DROP TABLE
        |--------------------------------------------------------------
        */

        $sql .= "DROP TABLE IF EXISTS `$table`;\n\n";


        /*
        |--------------------------------------------------------------
        | CREATE TABLE
        |--------------------------------------------------------------
        */

        $createStmt = $pdo->query(
            "SHOW CREATE TABLE `$table`"
        );

        $createRow = $createStmt->fetch();

        $createSql = $createRow["Create Table"];

        $sql .= $createSql . ";\n\n";


        /*
        |--------------------------------------------------------------
        | TABLE DATA
        |--------------------------------------------------------------
        */

        $dataStmt = $pdo->query(
            "SELECT * FROM `$table`"
        );

        $rows = $dataStmt->fetchAll();


        foreach ($rows as $row) {

            $columns = array_keys($row);

            $columnNames = [];

            foreach ($columns as $column) {

                $columnNames[] =
                    "`" . str_replace("`", "``", $column) . "`";
            }


            $values = [];

            foreach ($row as $value) {

                if ($value === null) {

                    $values[] = "NULL";

                } else {

                    $values[] =
                        $pdo->quote($value);

                }
            }


            $sql .=
                "INSERT INTO `$table` (" .
                implode(", ", $columnNames) .
                ") VALUES (" .
                implode(", ", $values) .
                ");\n";
        }


        $sql .= "\n";
    }


    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";


    /*
    |--------------------------------------------------------------------------
    | SAVE FILE
    |--------------------------------------------------------------------------
    */

    if (
        file_put_contents(
            $filePath,
            $sql
        ) === false
    ) {

        throw new Exception(
            "Unable to create backup file."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    header(
        "Location: index.php?success=" .
        urlencode(
            "Backup created successfully: " . $filename
        )
    );

    exit;


} catch (Exception $e) {

    header(
        "Location: index.php?error=" .
        urlencode(
            "Backup failed: " . $e->getMessage()
        )
    );

    exit;
}
