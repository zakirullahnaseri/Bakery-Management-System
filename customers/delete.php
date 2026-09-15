<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";

$id = (int)($_GET["customer_id"] ?? 0);

if ($id <= 0) {

    header(
        "Location: index.php?error="
        . urlencode("Invalid customer ID.")
    );

    exit;
}


try {

    /*
     * Check whether customer has sales
     */

    $check = $conn->prepare("
        SELECT COUNT(*)
        FROM sales
        WHERE customer_id = :id
    ");

    $check->execute([
        ":id" => $id
    ]);

    $saleCount = (int)$check->fetchColumn();


    /*
     * Do not delete customer
     * if sales exist.
     */

    if ($saleCount > 0) {

        header(
            "Location: index.php?error="
            . urlencode(
                "This customer cannot be deleted because sales records exist."
            )
        );

        exit;
    }


    /*
     * Delete customer
     */

    $stmt = $conn->prepare("
        DELETE FROM customers
        WHERE customer_id = :id
    ");

    $stmt->execute([
        ":id" => $id
    ]);


    if ($stmt->rowCount() > 0) {

        header(
            "Location: index.php?success="
            . urlencode("Customer deleted successfully.")
        );

    } else {

        header(
            "Location: index.php?error="
            . urlencode("Customer not found.")
        );
    }

    exit;

} catch (PDOException $e) {

    header(
        "Location: index.php?error="
        . urlencode("Unable to delete customer.")
    );

    exit;
}