<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "admin") {
    die("Access Denied");
}

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| Get Backup File
|--------------------------------------------------------------------------
*/

$filename = $_GET["file"] ?? $_POST["file"] ?? "";

$filename = basename($filename);

if (empty($filename)) {
    die("No backup file specified.");
}

if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== "sql") {
    die("Invalid backup file.");
}

$backupDir = __DIR__ . "/files/";
$filePath = $backupDir . $filename;

if (!is_file($filePath)) {
    die("Backup file not found.");
}


/*
|--------------------------------------------------------------------------
| Show Confirmation Page
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Confirm Restore</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body class="bg-light">

<div class="container mt-5">

    <div class="row justify-content-center">

        <div class="col-md-7">

            <div class="card shadow">

                <div class="card-header bg-warning">

                    <h4 class="mb-0">
                        Confirm Database Restore
                    </h4>

                </div>

                <div class="card-body">

                    <div class="alert alert-danger">

                        <strong>Warning!</strong>

                        <br>

                        Restoring this backup may replace
                        your current database data.

                    </div>

                    <p>
                        You are about to restore:
                    </p>

                    <p>
                        <strong>
                            <?= htmlspecialchars($filename) ?>
                        </strong>
                    </p>

                    <div class="alert alert-info">

                        Before restoring, the current database
                        will be backed up automatically.

                    </div>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="file"
                            value="<?= htmlspecialchars($filename) ?>"
                        >

                        <input
                            type="hidden"
                            name="confirm_restore"
                            value="1"
                        >

                        <button
                            type="submit"
                            class="btn btn-danger"
                        >
                            Yes, Restore
                        </button>

                        <a
                            href="index.php"
                            class="btn btn-secondary"
                        >
                            Cancel
                        </a>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

</body>

</html>

<?php

exit;

}


/*
|--------------------------------------------------------------------------
| Restore Process
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["confirm_restore"])
    &&
    $_POST["confirm_restore"] === "1"
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | Create Safety Backup
        |--------------------------------------------------------------------------
        */

        $safetyFilename =
            "before_restore_" .
            date("Y-m-d_H-i-s") .
            ".sql";

        $safetyFilePath =
            $backupDir . $safetyFilename;

        $backupSql = "";

        $backupSql .=
            "-- Safety Backup Before Restore\n";

        $backupSql .=
            "-- Created: " .
            date("Y-m-d H:i:s") .
            "\n\n";


        /*
        |--------------------------------------------------------------------------
        | Get Tables
        |--------------------------------------------------------------------------
        */

        $tables = $conn
            ->query("SHOW TABLES")
            ->fetchAll(PDO::FETCH_COLUMN);


        /*
        |--------------------------------------------------------------------------
        | Export Current Database
        |--------------------------------------------------------------------------
        */

        foreach ($tables as $table) {

            $createStmt = $conn
                ->query("SHOW CREATE TABLE `$table`")
                ->fetch(PDO::FETCH_ASSOC);

            $createSql =
                $createStmt["Create Table"];

            $backupSql .=
                "DROP TABLE IF EXISTS `$table`;\n";

            $backupSql .=
                $createSql . ";\n\n";


            $rows = $conn
                ->query("SELECT * FROM `$table`")
                ->fetchAll(PDO::FETCH_ASSOC);


            foreach ($rows as $row) {

                $columns = array_map(
                    fn($column) => "`$column`",
                    array_keys($row)
                );

                $values = [];

                foreach ($row as $value) {

                    if ($value === null) {

                        $values[] = "NULL";

                    } else {

                        $values[] =
                            $conn->quote($value);

                    }
                }

                $backupSql .=
                    "INSERT INTO `$table` (" .
                    implode(", ", $columns) .
                    ") VALUES (" .
                    implode(", ", $values) .
                    ");\n";
            }

            $backupSql .= "\n";
        }


        /*
        |--------------------------------------------------------------------------
        | Save Safety Backup
        |--------------------------------------------------------------------------
        */

        file_put_contents(
            $safetyFilePath,
            $backupSql
        );


        /*
        |--------------------------------------------------------------------------
        | Read Selected Backup
        |--------------------------------------------------------------------------
        */

        $restoreSql =
            file_get_contents($filePath);

        if ($restoreSql === false) {

            throw new Exception(
                "Unable to read backup file."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Disable Foreign Keys
        |--------------------------------------------------------------------------
        */

        $conn->exec(
            "SET FOREIGN_KEY_CHECKS=0"
        );


        /*
        |--------------------------------------------------------------------------
        | Remove SQL Comments
        |--------------------------------------------------------------------------
        */

        $restoreSql = preg_replace(
            '/^\s*--.*$/m',
            '',
            $restoreSql
        );


        /*
        |--------------------------------------------------------------------------
        | Split SQL Statements
        |--------------------------------------------------------------------------
        */

        $statements = preg_split(
            '/;\s*(?:\r\n|\r|\n|$)/',
            $restoreSql
        );


        /*
        |--------------------------------------------------------------------------
        | Execute SQL
        |--------------------------------------------------------------------------
        */

        foreach ($statements as $statement) {

            $statement = trim($statement);

            if ($statement === "") {
                continue;
            }

            $conn->exec($statement);
        }


        /*
        |--------------------------------------------------------------------------
        | Enable Foreign Keys
        |--------------------------------------------------------------------------
        */

        $conn->exec(
            "SET FOREIGN_KEY_CHECKS=1"
        );


        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        header(
            "Location: index.php?success=" .
            urlencode(
                "Database restored successfully from: " .
                $filename .
                ". Safety backup created: " .
                $safetyFilename
            )
        );

        exit;


    } catch (Exception $e) {

        try {

            $conn->exec(
                "SET FOREIGN_KEY_CHECKS=1"
            );

        } catch (Exception $ignore) {
        }


        header(
            "Location: index.php?error=" .
            urlencode(
                "Restore failed: " .
                $e->getMessage()
            )
        );

        exit;
    }

}

?>
