<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";

$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {

    $msg = $isPashto
        ? "د تادیې ID ناسم دی."
        : "Invalid payment ID.";

    header("Location: index.php?error=" . urlencode($msg));
    exit;
}

try {

    $conn->beginTransaction();

    /*
     * Get payment before deleting
     */

    $stmt = $conn->prepare("
        SELECT *
        FROM payments
        WHERE payment_id = ?
    ");

    $stmt->execute([$id]);

    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {

        throw new Exception(
            $isPashto
                ? "تادیه پیدا نه شوه."
                : "Payment not found."
        );
    }

    $sale_id = !empty($payment["sale_id"])
        ? (int)$payment["sale_id"]
        : null;

    $purchase_id = !empty($payment["purchase_id"])
        ? (int)$payment["purchase_id"]
        : null;

    /*
     * Delete payment
     */

    $stmt = $conn->prepare("
        DELETE FROM payments
        WHERE payment_id = ?
    ");

    $stmt->execute([$id]);

    /*
     * Recalculate related sale
     */

    if ($sale_id) {

        $stmt = $conn->prepare("
            SELECT total_amount
            FROM sales
            WHERE sale_id = ?
        ");

        $stmt->execute([$sale_id]);

        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sale) {

            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM payments
                WHERE sale_id = ?
            ");

            $stmt->execute([$sale_id]);

            $paid = (float)$stmt->fetchColumn();

            $total = (float)$sale["total_amount"];

            $due = max(0, $total - $paid);

            if ($paid >= $total) {
                $status = "paid";
            } elseif ($paid > 0) {
                $status = "partial";
            } else {
                $status = "unpaid";
            }

            $stmt = $conn->prepare("
                UPDATE sales
                SET paid_amount = ?,
                    due_amount = ?,
                    payment_status = ?
                WHERE sale_id = ?
            ");

            $stmt->execute([
                $paid,
                $due,
                $status,
                $sale_id
            ]);
        }
    }

    /*
     * Recalculate related purchase
     */

    if ($purchase_id) {

        $stmt = $conn->prepare("
            SELECT total_amount
            FROM purchases
            WHERE purchase_id = ?
        ");

        $stmt->execute([$purchase_id]);

        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($purchase) {

            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM payments
                WHERE purchase_id = ?
            ");

            $stmt->execute([$purchase_id]);

            $paid = (float)$stmt->fetchColumn();

            $total = (float)$purchase["total_amount"];

            $due = max(0, $total - $paid);

            if ($paid >= $total) {
                $status = "paid";
            } elseif ($paid > 0) {
                $status = "partial";
            } else {
                $status = "unpaid";
            }

            $stmt = $conn->prepare("
                UPDATE purchases
                SET paid_amount = ?,
                    due_amount = ?,
                    payment_status = ?
                WHERE purchase_id = ?
            ");

            $stmt->execute([
                $paid,
                $due,
                $status,
                $purchase_id
            ]);
        }
    }

    $conn->commit();

    $msg = $isPashto
        ? "تادیه په بریالیتوب سره حذف شوه."
        : "Payment deleted successfully.";

    header("Location: index.php?success=" . urlencode($msg));

    exit;

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    header("Location: index.php?error=" . urlencode(
        $e->getMessage()
    ));

    exit;
}