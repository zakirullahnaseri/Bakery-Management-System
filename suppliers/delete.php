
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";


/* =====================================================
   GET SUPPLIER ID
===================================================== */

$supplier_id = isset($_GET["supplier_id"])
    ? (int) $_GET["supplier_id"]
    : 0;

if ($supplier_id <= 0) {
    header("Location: index.php");
    exit;
}


/* =====================================================
   CHECK SUPPLIER
===================================================== */

$stmt = $conn->prepare("
    SELECT
        supplier_id,
        name
    FROM suppliers
    WHERE supplier_id = :supplier_id
");

$stmt->execute([
    ":supplier_id" => $supplier_id
]);

$supplier = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$supplier) {
    die("Supplier not found.");
}


/* =====================================================
   DELETE SUPPLIER
===================================================== */

try {

    $deleteStmt = $conn->prepare("
        DELETE FROM suppliers
        WHERE supplier_id = :supplier_id
    ");

    $deleteStmt->execute([
        ":supplier_id" => $supplier_id
    ]);


    if ($deleteStmt->rowCount() === 0) {

        throw new Exception(
            "Supplier could not be deleted."
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

    /*
     * If another table prevents deletion,
     * show a friendly message.
     */

    die(
        "Error deleting supplier: "
        . htmlspecialchars(
            $e->getMessage()
        )
    );

} catch (Exception $e) {

    die(
        "Error deleting supplier: "
        . htmlspecialchars(
            $e->getMessage()
        )
    );
}

?>
