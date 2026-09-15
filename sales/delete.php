<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";


/* =====================================================
   GET SALE ID
===================================================== */

$sale_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


if ($sale_id <= 0) {

    header(
        "Location: index.php?error=invalid_sale"
    );

    exit;
}


try {

    /* =================================================
       START TRANSACTION
    ================================================= */

    $conn->beginTransaction();


    /* =================================================
       CHECK SALE
    ================================================= */

    $saleStmt = $conn->prepare("
        SELECT sale_id
        FROM sales
        WHERE sale_id = :sale_id
        FOR UPDATE
    ");

    $saleStmt->execute([
        ":sale_id" => $sale_id
    ]);

    $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);


    if (!$sale) {

        throw new Exception(
            "Sale not found."
        );
    }


    /* =================================================
       GET SALE ITEMS
    ================================================= */

    $itemStmt = $conn->prepare("
        SELECT
            product_id,
            quantity
        FROM sale_items
        WHERE sale_id = :sale_id
    ");

    $itemStmt->execute([
        ":sale_id" => $sale_id
    ]);

    $items =
        $itemStmt->fetchAll(PDO::FETCH_ASSOC);


    /* =================================================
       RESTORE STOCK
    ================================================= */

    $stockStmt = $conn->prepare("
        UPDATE products
        SET stock_quantity =
            stock_quantity + :quantity
        WHERE product_id = :product_id
    ");


    foreach ($items as $item) {

        $product_id =
            (int) $item["product_id"];

        $quantity =
            (float) $item["quantity"];


        if (
            $product_id <= 0 ||
            $quantity <= 0
        ) {
            continue;
        }


        $stockStmt->execute([

            ":quantity" =>
                $quantity,

            ":product_id" =>
                $product_id
        ]);
    }


    /* =================================================
       DELETE SALE ITEMS
    ================================================= */

    $deleteItemsStmt = $conn->prepare("
        DELETE FROM sale_items
        WHERE sale_id = :sale_id
    ");

    $deleteItemsStmt->execute([
        ":sale_id" => $sale_id
    ]);


    /* =================================================
       DELETE SALE
    ================================================= */

    $deleteSaleStmt = $conn->prepare("
        DELETE FROM sales
        WHERE sale_id = :sale_id
    ");

    $deleteSaleStmt->execute([
        ":sale_id" => $sale_id
    ]);


    /* =================================================
       COMMIT
    ================================================= */

    $conn->commit();


    /* =================================================
       SUCCESS
    ================================================= */

    header(
        "Location: index.php?deleted=1"
    );

    exit;


} catch (Exception $e) {

    /* =================================================
       ROLLBACK
    ================================================= */

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }


    /* =================================================
       ERROR
    ================================================= */

    header(
        "Location: index.php?error="
        . urlencode($e->getMessage())
    );

    exit;
}