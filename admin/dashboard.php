<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../public/login.php");
    exit;
}

require_once "../config/database.php";
require_once "../config/language.php";

$isPashto = currentLanguage() === "ps";

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function money($amount)
{
    return "$" . number_format((float)$amount, 2);
}

function paymentBadge($status)
{
    switch ($status) {

        case "paid":
            return [
                "class" => "success",
                "icon"  => "bi-check-circle-fill",
                "text"  => t("payment_paid")
            ];

        case "partial":
            return [
                "class" => "warning",
                "icon"  => "bi-clock-fill",
                "text"  => t("payment_partial")
            ];

        case "unpaid":
            return [
                "class" => "danger",
                "icon"  => "bi-exclamation-circle-fill",
                "text"  => t("payment_unpaid")
            ];

        default:
            return [
                "class" => "secondary",
                "icon"  => "bi-question-circle-fill",
                "text"  => e($status)
            ];
    }
}


/*
|--------------------------------------------------------------------------
| DASHBOARD DATA
|--------------------------------------------------------------------------
*/

try {

    /* TOTAL CUSTOMERS */
    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM customers
    ");
    $totalCustomers = (int)$stmt->fetchColumn();


    /* TOTAL SUPPLIERS */
    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM suppliers
    ");
    $totalSuppliers = (int)$stmt->fetchColumn();


    /* TOTAL PRODUCTS */
    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM products
        WHERE status = 'available'
    ");
    $totalProducts = (int)$stmt->fetchColumn();


    /* TOTAL INGREDIENTS */
    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM ingredients
    ");
    $totalIngredients = (int)$stmt->fetchColumn();


    /* TODAY SALES */
    $stmt = $conn->query("
        SELECT COALESCE(SUM(total_amount), 0)
        FROM sales
        WHERE DATE(sale_date) = CURDATE()
    ");
    $todaySales = (float)$stmt->fetchColumn();


    /* TODAY PURCHASES */
    $stmt = $conn->query("
        SELECT COALESCE(SUM(total_amount), 0)
        FROM purchases
        WHERE DATE(purchase_date) = CURDATE()
    ");
    $todayPurchases = (float)$stmt->fetchColumn();


    /* TODAY EXPENSES */
    $stmt = $conn->query("
        SELECT COALESCE(SUM(amount), 0)
        FROM expenses
        WHERE DATE(expense_date) = CURDATE()
    ");
    $todayExpenses = (float)$stmt->fetchColumn();


    /* TODAY PAID */
    $stmt = $conn->query("
        SELECT COALESCE(SUM(paid_amount), 0)
        FROM sales
        WHERE DATE(sale_date) = CURDATE()
    ");
    $todayPaid = (float)$stmt->fetchColumn();


    /* CUSTOMER DUE */
    $stmt = $conn->query("
        SELECT COALESCE(SUM(due_amount), 0)
        FROM sales
        WHERE due_amount > 0
    ");
    $customerDue = (float)$stmt->fetchColumn();


    /* SUPPLIER DUE */
    $stmt = $conn->query("
        SELECT COALESCE(SUM(due_amount), 0)
        FROM purchases
        WHERE due_amount > 0
    ");
    $supplierDue = (float)$stmt->fetchColumn();


    /* LOW STOCK PRODUCTS */
    $stmt = $conn->query("
        SELECT
            product_id,
            product_name,
            stock_quantity,
            minimum_stock,
            unit
        FROM products
        WHERE status = 'available'
        AND stock_quantity <= minimum_stock
        ORDER BY stock_quantity ASC
        LIMIT 10
    ");

    $lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /* LOW STOCK INGREDIENTS */
    $stmt = $conn->query("
        SELECT
            ingredient_id,
            ingredient_name,
            current_stock,
            minimum_stock,
            unit
        FROM ingredients
        WHERE current_stock <= minimum_stock
        ORDER BY current_stock ASC
        LIMIT 10
    ");

    $lowStockIngredients = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /* RECENT SALES */
    $stmt = $conn->query("
        SELECT
            s.sale_id,
            s.sale_date,
            s.total_amount,
            s.paid_amount,
            s.due_amount,
            s.payment_status,
            c.name AS customer_name
        FROM sales s
        LEFT JOIN customers c
            ON s.customer_id = c.customer_id
        ORDER BY s.sale_id DESC
        LIMIT 10
    ");

    $recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /* RECENT PURCHASES */
    $stmt = $conn->query("
        SELECT
            p.purchase_id,
            p.purchase_date,
            p.total_amount,
            p.paid_amount,
            p.due_amount,
            p.payment_status,
            s.name AS supplier_name
        FROM purchases p
        LEFT JOIN suppliers s
            ON p.supplier_id = s.supplier_id
        ORDER BY p.purchase_id DESC
        LIMIT 10
    ");

    $recentPurchases = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /* TODAY NET */
    $todayNet =
        $todaySales
        - $todayPurchases
        - $todayExpenses;


} catch (PDOException $e) {

    die(
        "Database Error: " . e($e->getMessage())
    );
}

?>

<!DOCTYPE html>
<html
    lang="<?= e(currentLanguage()) ?>"
    dir="<?= e(languageDirection()) ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        <?= e(t("dashboard")) ?> -
        <?= e(t("bakery_system")) ?>
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/backgrounds.css">

    <link
        rel="stylesheet"
        href="../assets/css/bootstrap.min.css">

    <link
        rel="stylesheet"
        href="../assets/css/bootstrap-icons.css">


<style>

/* =========================================================
   ROOT
========================================================= */

:root {
    --primary: #4f46e5;
    --primary-dark: #3730a3;
    --secondary: #7c3aed;

    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #0ea5e9;

    --dark: #111827;
    --muted: #6b7280;

    --bg: #f4f7fb;
    --card: #ffffff;

    --border: #e5e7eb;

    --sidebar: #111827;
    --sidebar-light: #1f2937;

    --shadow:
        0 10px 35px rgba(15, 23, 42, .07);
}

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    min-height: 100vh;

    background:
        radial-gradient(
            circle at top left,
            rgba(79,70,229,.08),
            transparent 35%
        ),
        linear-gradient(
            135deg,
            #f8fafc,
            #eef2ff
        );

    color: var(--dark);

    font-family:
        Inter,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;
}

a {
    text-decoration: none;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    position: fixed;

    top: 0;
    bottom: 0;
    left: 0;

    width: 260px;

    z-index: 1200;

    display: flex;
    flex-direction: column;

    padding: 18px 14px;

    color: #fff;

    background:
        linear-gradient(
            180deg,
            #111827 0%,
            #172033 50%,
            #111827 100%
            
        );

    box-shadow:
        10px 0 35px rgba(15,23,42,.16);

    transition:
        transform .3s ease;
}

.sidebar-brand {
    display: flex;
    align-items: center;

    padding: 7px 9px 22px;

    color: white;

    border-bottom:
        1px solid rgba(255,255,255,.08);

    margin-bottom: 15px;
}

.sidebar-brand:hover {
    color: white;
  
}

.sidebar-logo {
    width: 46px;
    height: 46px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    border-radius: 14px;

    background:
        linear-gradient(
            135deg,
            #6366f1,
            #8b5cf6
        );

    box-shadow:
        0 10px 25px rgba(99,102,241,.35);

    font-size: 21px;
}

.sidebar-brand-text {
    margin-left: 11px;

    font-size: .98rem;

    font-weight: 900;

    line-height: 1.2;
}

.sidebar-brand-text small {
    display: block;

    margin-top: 4px;

    color: #9ca3af;

    font-size: .65rem;

    font-weight: 600;
}

html[dir="rtl"] .sidebar {
    left: auto;
    right: 0;
}

html[dir="rtl"] .sidebar-brand-text {
    margin-left: 0;
    margin-right: 11px;
}


/* SIDEBAR MENU */

.sidebar-menu {
    flex: 1;

    overflow-y: auto;

    padding-right: 3px;
}

.sidebar-menu::-webkit-scrollbar {
    width: 4px;
}

.sidebar-menu::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,.15);
    border-radius: 10px;
}

.sidebar-section-title {
    padding: 13px 12px 7px;

    color: #6b7280;

    font-size: .62rem;

    font-weight: 900;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.sidebar-link {
    position: relative;

    display: flex;
    align-items: center;

    gap: 12px;

    min-height: 45px;

    margin: 4px 0;

    padding: 9px 12px;

    color: #cbd5e1;

    border-radius: 12px;

    font-size: .78rem;

    font-weight: 700;

    transition:
        color .2s ease,
        background .2s ease,
        transform .2s ease;
}

.sidebar-link i {
    width: 22px;

    text-align: center;

    font-size: 17px;
}

.sidebar-link:hover {
    color: white;

    background:
        rgba(255,255,255,.08);

    transform: translateX(3px);
}

.sidebar-link.active {
    color: white;

    background:
        linear-gradient(
            135deg,
            #4f46e5,
            #7c3aed
        );

    box-shadow:
        0 8px 20px rgba(79,70,229,.28);
}

.sidebar-link.active::before {
    content: "";

    position: absolute;

    left: 0;
    top: 10px;
    bottom: 10px;

    width: 3px;

    border-radius: 5px;

    background: white;
}

html[dir="rtl"] .sidebar-link.active::before {
    left: auto;
    right: 0;
}

html[dir="rtl"] .sidebar-link:hover {
    transform: translateX(-3px);
}


/* SIDEBAR USER */

.sidebar-user {
    margin-top: 12px;

    padding: 12px;

    border-top:
        1px solid rgba(255,255,255,.08);
}

.sidebar-user-box {
    display: flex;
    align-items: center;

    padding: 9px;

    border-radius: 14px;

    background:
        rgba(255,255,255,.06);
}

.sidebar-user-avatar {
    width: 39px;
    height: 39px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            #6366f1,
            #8b5cf6
        );

    font-size: 16px;
}

.sidebar-user-info {
    min-width: 0;

    margin-left: 10px;
}

.sidebar-user-info strong {
    display: block;

    overflow: hidden;

    color: white;

    font-size: .75rem;

    font-weight: 800;

    text-overflow: ellipsis;

    white-space: nowrap;
}

.sidebar-user-info span {
    display: block;

    margin-top: 2px;

    color: #9ca3af;

    font-size: .65rem;
}

html[dir="rtl"] .sidebar-user-info {
    margin-left: 0;
    margin-right: 10px;
}


/* =========================================================
   SIDEBAR OVERLAY
========================================================= */

.sidebar-overlay {
    position: fixed;

    inset: 0;

    z-index: 1100;

    display: none;

    background:
        rgba(15,23,42,.55);

    backdrop-filter: blur(2px);
}


/* =========================================================
   APP MAIN
========================================================= */

.app-main {
    min-height: 100vh;

    margin-left: 260px;

    transition:
        margin .3s ease;
}

html[dir="rtl"] .app-main {
    margin-left: 0;
    margin-right: 260px;
}


/* =========================================================
   TOP NAVBAR
========================================================= */

.top-navbar {
    position: sticky;

    top: 0;

    z-index: 1000;

    background:
        rgba(255,255,255,.88);

    backdrop-filter: blur(18px);

    border-bottom:
        1px solid rgba(226,232,240,.8);

    box-shadow:
        0 5px 25px rgba(15,23,42,.04);
}

.navbar-inner {
    min-height: 72px;
}

.sidebar-toggle {
    width: 42px;
    height: 42px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    margin-right: 10px;

    border: 0;

    border-radius: 12px;

    color: var(--primary);

    background: #eef2ff;

    font-size: 20px;

    cursor: pointer;

    transition: .2s ease;
}

.sidebar-toggle:hover {
    color: white;

    background: var(--primary);

    transform: translateY(-2px);
}

html[dir="rtl"] .sidebar-toggle {
    margin-right: 0;
    margin-left: 10px;
}

.brand {
    display: flex;
    align-items: center;

    color: var(--dark);

    font-weight: 800;

    font-size: 1.08rem;
}

.brand:hover {
    color: var(--primary);
}

.brand-icon {
    width: 44px;
    height: 44px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    margin-right: 11px;

    border-radius: 14px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #6366f1,
            #8b5cf6
        );

    box-shadow:
        0 8px 20px rgba(79,70,229,.25);

    font-size: 20px;
}

html[dir="rtl"] .brand-icon {
    margin-right: 0;
    margin-left: 11px;
}

.user-avatar {
    width: 40px;
    height: 40px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background: #eef2ff;

    color: var(--primary);

    font-size: 18px;
}

.user-name {
    font-size: .85rem;
    font-weight: 800;
}

.user-role {
    color: var(--muted);
    font-size: .72rem;
}

.language-btn {
    border-radius: 10px !important;
    font-weight: 700;
}


/* =========================================================
   PAGE
========================================================= */

.dashboard-wrapper {
    padding: 28px;
}


/* =========================================================
   WELCOME
========================================================= */

.welcome-card {
    position: relative;
   

    overflow: hidden;

    border: 0;

    border-radius: 26px;

    padding: 32px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #4338ca,
            #6366f1 55%,
            #8b5cf6
        );

    box-shadow:
        0 20px 50px rgba(79,70,229,.25);
}

.welcome-card::before {
    content: "";

    position: absolute;

    width: 300px;
    height: 300px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.07);

    top: -150px;
    right: -70px;
}

.welcome-card::after {
    content: "";

    position: absolute;

    width: 180px;
    height: 180px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.05);

    bottom: -100px;
    left: 30%;
}

html[dir="rtl"] .welcome-card::before {
    right: auto;
    left: -70px;
}

.welcome-content {
    position: relative;
    z-index: 2;
}

.welcome-title {
    font-size: 1.8rem;
    font-weight: 900;

    margin-bottom: 7px;
}

.welcome-subtitle {
    opacity: .85;

    font-size: .92rem;
}

.welcome-actions {
    margin-top: 22px;
}

.welcome-actions .btn {
    border-radius: 12px;

    font-weight: 700;

    padding: 9px 15px;
}

.date-box {
    position: relative;

    z-index: 3;

    padding: 18px;

    text-align: center;

    border-radius: 18px;

    background:
        rgba(255,255,255,.13);

    border:
        1px solid rgba(255,255,255,.20);

    backdrop-filter: blur(12px);
}

.date-box-icon {
    font-size: 25px;
}

.current-date {
    font-size: 1.15rem;

    font-weight: 800;

    margin-top: 6px;
}

.current-time {
    font-size: .8rem;

    opacity: .75;
}


/* =========================================================
   STAT CARDS
========================================================= */

.stat-card {
    position: relative;

    height: 100%;

    border: 1px solid rgba(226,232,240,.7);

    border-radius: 20px;

    background: rgba(255,255,255,.94);

    box-shadow: var(--shadow);

    overflow: hidden;

    transition:
        transform .25s ease,
        box-shadow .25s ease;
}

.stat-card:hover {
    transform: translateY(-5px);

    box-shadow:
        0 18px 45px rgba(15,23,42,.11);
}

.stat-card::before {
    content: "";

    position: absolute;

    width: 70px;
    height: 70px;

    border-radius: 50%;

    right: -28px;
    top: -28px;

    background:
        rgba(79,70,229,.05);
}

html[dir="rtl"] .stat-card::before {
    right: auto;
    left: -28px;
}

.stat-body {
    position: relative;

    z-index: 2;

    padding: 22px;
}

.stat-icon {
    width: 54px;
    height: 54px;

    flex-shrink: 0;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 16px;

    font-size: 23px;
}

.icon-green {
    background: #ecfdf5;
    color: #059669;
}

.icon-blue {
    background: #eff6ff;
    color: #2563eb;
}

.icon-red {
    background: #fef2f2;
    color: #dc2626;
}

.icon-purple {
    background: #f5f3ff;
    color: #7c3aed;
 
}

.icon-orange {
    background: #fff7ed;
    color: #ea580c;
}

.stat-label {
    color: var(--muted);

    font-size: .78rem;

    font-weight: 700;
}

.stat-value {
    font-size: 1.55rem;

    font-weight: 900;

    margin-top: 5px;

    line-height: 1.2;
}

.stat-link {
    display: inline-flex;

    align-items: center;

    gap: 5px;

    margin-top: 15px;

    font-size: .76rem;

    font-weight: 700;

    color: var(--primary);
}

.stat-link:hover {
    color: var(--primary-dark);
}


/* =========================================================
   SECTION CARD
========================================================= */

.section-card {
    border: 1px solid rgba(226,232,240,.7);

    border-radius: 20px;

    background:
        rgba(255,255,255,.95);

    box-shadow: var(--shadow);

    overflow: hidden;
}

.section-header {
    padding: 18px 22px;

    border-bottom:
        1px solid #eef0f4;
}

.section-title {
    display: flex;

    align-items: center;

    gap: 9px;

    margin: 0;

    font-size: .96rem;

    font-weight: 900;
}

.section-title i {
    font-size: 17px;
}


/* =========================================================
   QUICK ACTIONS
========================================================= */

.quick-action {
    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    min-height: 120px;

    padding: 15px;

    text-align: center;

    border-radius: 17px;

    background:
        linear-gradient(
            145deg,
            #f8fafc,
            #f1f5f9
        );

    border:
        1px solid #e8edf3;

    color: #334155;

    transition:
        all .25s ease;
}

.quick-action:hover {
    transform: translateY(-5px);

    color: var(--primary);

    background: white;

    border-color:
        rgba(79,70,229,.20);

    box-shadow:
        0 12px 28px rgba(15,23,42,.08);
}

.quick-icon {
    width: 49px;
    height: 49px;

    display: flex;

    align-items: center;
    justify-content: center;

    margin-bottom: 9px;

    border-radius: 14px;

    background: #eef2ff;

    color: var(--primary);

    font-size: 20px;
}

.quick-text {
    font-size: .78rem;

    font-weight: 800;
}


/* =========================================================
   DUE
========================================================= */

.due-card {
    position: relative;

    height: 100%;

    overflow: hidden;

    padding: 24px;

    border-radius: 20px;

    background: white;

    border:
        1px solid rgba(226,232,240,.7);

    box-shadow: var(--shadow);
}

.due-card::before {
    content: "";

    position: absolute;

    top: 0;
    bottom: 0;

    width: 5px;

    left: 0;

    background: var(--danger);
}

html[dir="rtl"] .due-card::before {
    left: auto;
    right: 0;
}

.supplier-due::before {
    background: var(--warning);
}

.due-value {
    font-size: 2rem;

    font-weight: 900;

    margin-top: 5px;
}

.due-icon {
    font-size: 55px;

    opacity: .13;
}


/* =========================================================
   STOCK
========================================================= */

.stock-item {
    padding: 14px 20px;

    border-bottom:
        1px solid #f0f2f5;

    transition:
        background .2s ease;
}

.stock-item:last-child {
    border-bottom: 0;
}

.stock-item:hover {
    background: #fafbff;
}

.stock-icon {
    width: 42px;
    height: 42px;

    flex-shrink: 0;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 12px;

    background: #fef2f2;

    color: var(--danger);
}

.stock-name {
    font-size: .84rem;

    font-weight: 800;
}

.stock-info {
    color: var(--muted);

    font-size: .72rem;

    margin-top: 3px;
}

.stock-badge {
    padding: 7px 10px;

    border-radius: 20px;

    font-size: .7rem;

    font-weight: 800;
}


/* =========================================================
   TABLE
========================================================= */

.table-responsive {
    overflow-x: auto;
}

.dashboard-table {
    min-width: 850px;
     
    margin-bottom: 0;
}

.dashboard-table thead th {
    padding:
        13px 15px;

    color: #64748b;

    background: #f8fafc;

    border-bottom:
        1px solid #e9edf2;

    font-size: .69rem;

    font-weight: 800;

    white-space: nowrap;
}

.dashboard-table tbody td {
    padding:
        14px 15px;

    vertical-align: middle;

    border-color:
        #f1f3f6;

    font-size: .8rem;

    white-space: nowrap;
}

.dashboard-table tbody tr {
    transition:
        background .15s ease;
}

.dashboard-table tbody tr:hover {
    background:
        #fafbff;
}

.sale-id {
    color: var(--primary);

    font-weight: 900;
}

.status-badge {
    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding:
        7px 10px;

    border-radius: 20px;

    font-size: .68rem;

    font-weight: 800;
}

.action-btn {
    width: 34px;
    height: 34px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    border-radius: 10px;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-state {
    padding: 42px 20px;

    text-align: center;

    color: var(--muted);

    font-size: .82rem;
}

.empty-state i {
    display: block;

    margin-bottom: 10px;

    font-size: 42px;

    color: var(--success);
}


/* =========================================================
   FOOTER
========================================================= */

.footer {
    padding: 20px 0 5px;

    text-align: center;

    color: #94a3b8;

    font-size: .76rem;
}


/* =========================================================
   RTL
========================================================= */

html[dir="rtl"] body {
    text-align: right;
}

html[dir="rtl"] .me-1 {
    margin-right: 0 !important;
    margin-left: .25rem !important;
}

html[dir="rtl"] .me-2 {
    margin-right: 0 !important;
    margin-left: .5rem !important;
}

html[dir="rtl"] .me-3 {
    margin-right: 0 !important;
    margin-left: 1rem !important;
}

html[dir="rtl"] .ms-auto {
    margin-left: 0 !important;
    margin-right: auto !important;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 991px) {

    .sidebar {
        transform: translateX(-105%);
    }

    html[dir="rtl"] .sidebar {
        transform: translateX(105%);
    }

    body.sidebar-open .sidebar {
        transform: translateX(0);
    }

    body.sidebar-open .sidebar-overlay {
        display: block;
    }

    .app-main {
        margin-left: 0;
    }

    html[dir="rtl"] .app-main {
        margin-left: 0;
        margin-right: 0;
    }

    .sidebar-toggle {
        display: inline-flex;
    }
}

@media (min-width: 992px) {

    .sidebar-toggle {
        display: none;
    }
}

@media (max-width: 768px) {

    .dashboard-wrapper {
        padding: 15px;
    }

    .navbar-inner {
        min-height: 65px;
    }

    .brand {
        font-size: .9rem;
    }

    .brand-icon {
        width: 39px;
        height: 39px;

        margin-right: 8px;
    }

    html[dir="rtl"] .brand-icon {
        margin-right: 0;
        margin-left: 8px;
    }

    .user-info {
        display: none;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
    }

    .welcome-card {
        padding: 23px;

        border-radius: 21px;
    }

    .welcome-title {
        font-size: 1.35rem;
    }

    .date-box {
        margin-top: 20px;
    }

    .stat-value {
        font-size: 1.35rem;
    }

    .due-value {
        font-size: 1.65rem;
    }

    .section-header {
        padding: 16px;
    }
}

@media (max-width: 480px) {

    .language-btn {
        padding:
            5px 7px;

        font-size: .7rem;
    }

    .logout-text {
        display: none;
    }

    .welcome-actions .btn {
        width: 100%;

        margin-bottom: 8px;
    }

    .welcome-actions .btn:last-child {
        margin-bottom: 0;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">

    <a
        href="dashboard.php"
        class="sidebar-brand">

        <span class="sidebar-logo">
            <i class="bi bi-shop"></i>
        </span>

        <span class="sidebar-brand-text">

            <?= e(t("bakery_system")) ?>

            <small>
                <?= $isPashto
                    ? "د مدیریت سیستم"
                    : "Management System" ?>
            </small>

        </span>

    </a>


    <div class="sidebar-menu">


        <!-- MAIN -->

        <div class="sidebar-section-title">
            <?= $isPashto ? "اصلي" : "MAIN" ?>
        </div>


        <a
            href="dashboard.php"
            class="sidebar-link active">

            <i class="bi bi-grid-1x2-fill"></i>

            <span>
                <?= $isPashto ? "ډشبورډ" : "Dashboard" ?>
            </span>

        </a>


        <a
            href="../sales/index.php"
            class="sidebar-link">

            <i class="bi bi-cart-check-fill"></i>

            <span>
                <?= $isPashto ? "خرڅلاو" : "Sales" ?>
            </span>

        </a>


        <a
            href="../purchases/index.php"
            class="sidebar-link">

            <i class="bi bi-bag-check-fill"></i>

            <span>
                <?= $isPashto ? "پېرودنې" : "Purchases" ?>
            </span>

        </a>


        <!-- MANAGEMENT -->

        <div class="sidebar-section-title">
            <?= $isPashto ? "مدیریت" : "MANAGEMENT" ?>
        </div>


        <a
            href="../products/index.php"
            class="sidebar-link">

            <i class="bi bi-box-seam-fill"></i>

            <span>
                <?= $isPashto ? "محصولات" : "Products" ?>
            </span>

        </a>


        <a
            href="../ingredients/index.php"
            class="sidebar-link">

            <i class="bi bi-droplet-fill"></i>

            <span>
                <?= $isPashto ? "اجزا" : "Ingredients" ?>
            </span>

        </a>


        <a
            href="../customers/index.php"
            class="sidebar-link">

            <i class="bi bi-people-fill"></i>

            <span>
                <?= $isPashto ? "پېرودونکي" : "Customers" ?>
            </span>

        </a>


        <a
            href="../suppliers/index.php"
            class="sidebar-link">

            <i class="bi bi-truck"></i>

            <span>
                <?= $isPashto ? "عرضه کوونکي" : "Suppliers" ?>
            </span>

        </a>


        <!-- FINANCE -->

        <div class="sidebar-section-title">
            <?= $isPashto ? "مالي" : "FINANCE" ?>
        </div>


        <a
            href="../expenses/add.php"
            class="sidebar-link">

            <i class="bi bi-wallet2"></i>

            <span>
                <?= $isPashto ? "مصرف اضافه کول" : "Add Expense" ?>
            </span>

        </a>


        <a
            href="../reports/"
            class="sidebar-link">

            <i class="bi bi-bar-chart-line-fill"></i>

            <span>
                <?= $isPashto ? "راپورونه" : "Reports" ?>
            </span>

        </a>


        <a
            href="../backup/index.php"
            class="sidebar-link">

            <i class="bi bi-database-fill"></i>

            <span>
                <?= $isPashto ? "Backup" : "Database Backup" ?>
            </span>

        </a>

    </div>


    <!-- SIDEBAR USER -->

    <div class="sidebar-user">

        <div class="sidebar-user-box">

            <div class="sidebar-user-avatar">
                <i class="bi bi-person-fill"></i>
            </div>

            <div class="sidebar-user-info">

                <strong>
                    <?= e(
                        $_SESSION["full_name"] ?? "User"
                    ) ?>
                </strong>

                <span>
                    <?= e(
                        $_SESSION["role"] ?? "User"
                    ) ?>
                </span>

            </div>

        </div>


        <a
            href="../public/logout.php"
            class="sidebar-link mt-2">

            <i class="bi bi-box-arrow-right"></i>

            <span>
                <?= e(t("logout")) ?>
            </span>

        </a>

    </div>

</aside>


<!-- SIDEBAR OVERLAY -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay">
</div>


<!-- =========================================================
     APP MAIN
========================================================= -->

<div class="app-main">


<!-- =========================================================
     TOP NAVBAR
========================================================= -->

<nav class="top-navbar">

    <div class="container-fluid px-3 px-md-4">

        <div class="navbar-inner d-flex align-items-center">


            <!-- MOBILE SIDEBAR BUTTON -->

            <button
                type="button"
                class="sidebar-toggle"
                id="sidebarToggle"
                aria-label="Menu">

                <i class="bi bi-list"></i>

            </button>


            <!-- BRAND -->

            <a
                href="dashboard.php"
                class="brand">

                <span class="brand-icon">
                    <i class="bi bi-shop"></i>
                </span>

                <?= e(t("bakery_system")) ?>

            </a>


            <div class="ms-auto d-flex align-items-center">


                <!-- USER -->

                <div class="user-avatar me-2">

                    <i class="bi bi-person-fill"></i>

                </div>


                <div class="user-info me-3">

                    <div class="user-name">

                        <?= e(
                            $_SESSION["full_name"] ?? "User"
                        ) ?>

                    </div>

                    <div class="user-role">

                        <?= e(
                            $_SESSION["role"] ?? "User"
                        ) ?>

                    </div>

                </div>


                <!-- LANGUAGE -->

                <div class="btn-group me-2">

                    <a
                        href="?lang=en"
                        class="btn btn-sm language-btn
                        <?= currentLanguage() === "en"
                            ? "btn-primary"
                            : "btn-outline-primary" ?>">

                        EN

                    </a>


                    <a
                        href="?lang=ps"
                        class="btn btn-sm language-btn
                        <?= currentLanguage() === "ps"
                            ? "btn-primary"
                            : "btn-outline-primary" ?>">

                        پښتو

                    </a>

                </div>


                <!-- LOGOUT -->

                <a
                    href="../public/logout.php"
                    class="btn btn-sm btn-outline-danger rounded-3">

                    <i class="bi bi-box-arrow-right"></i>

                    <span class="logout-text d-none d-md-inline">

                        <?= e(t("logout")) ?>

                    </span>

                </a>

            </div>

        </div>

    </div>

</nav>


<!-- =========================================================
     MAIN DASHBOARD
========================================================= -->

<main class="container-fluid dashboard-wrapper">


<!-- =========================================================
     WELCOME
========================================================= -->

<section class="welcome-card mb-4">

    <div class="welcome-content">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <h1 class="welcome-title">

                    <!-- <i class="bi bi-stars"></i> -->

                    <?= e(t("dashboard")) ?>

                </h1>


                <div class="welcome-subtitle">

                    <?= e(t("overview")) ?>

                </div>


                <div class="welcome-actions">

                    <a
                        href="../reports/"
                        class="btn btn-light me-2">

                        <i class="bi bi-bar-chart-line-fill"></i>

                        <?= $isPashto
                            ? "ټول راپورونه"
                            : "All Reports" ?>

                    </a>


                    <a
                        href="../backup/index.php"
                        class="btn btn-outline-light">

                        <i class="bi bi-database-fill"></i>

                        <?= e(t("database_backup")) ?>

                    </a>

                </div>

            </div>


            <div class="col-lg-4">

                <div class="date-box">

                    <div class="date-box-icon">

                        <i class="bi bi-calendar3"></i>

                    </div>


                    <div class="current-date">

                        <?= date("Y-m-d") ?>

                    </div>


                    <div
                        class="current-time"
                        id="liveTime">

                        <?= date("h:i:s A") ?>

                    </div>


                    <small>

                        <?= $isPashto
                            ? "د نن ورځې معلومات"
                            : "Today's Overview" ?>

                    </small>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     FINANCIAL STATS
========================================================= -->

<div class="row g-4 mb-4">


    <!-- SALES -->

    <div class="col-xl-3 col-md-6">

        <div class="stat-card">

            <div class="stat-body">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="stat-label">
                            <?= e(t("todays_sales")) ?>
                        </div>

                        <div class="stat-value text-success">
                            <?= money($todaySales) ?>
                        </div>

                    </div>


                    <div class="stat-icon icon-green">

                        <i class="bi bi-cart-check-fill"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- PURCHASES -->

    <div class="col-xl-3 col-md-6">

        <div class="stat-card">

            <div class="stat-body">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="stat-label">
                            <?= e(t("todays_purchases")) ?>
                        </div>

                        <div class="stat-value text-primary">
                            <?= money($todayPurchases) ?>
                        </div>

                    </div>


                    <div class="stat-icon icon-blue">

                        <i class="bi bi-bag-check-fill"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- EXPENSES -->

    <div class="col-xl-3 col-md-6">

        <div class="stat-card">

            <div class="stat-body">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="stat-label">
                            <?= e(t("todays_expenses")) ?>
                        </div>

                        <div class="stat-value text-danger">
                            <?= money($todayExpenses) ?>
                        </div>

                    </div>


                    <div class="stat-icon icon-red">

                        <i class="bi bi-wallet2"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- NET -->

    <div class="col-xl-3 col-md-6">

        <div class="stat-card">

            <div class="stat-body">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="stat-label">
                            <?= e(t("todays_net")) ?>
                        </div>

                        <div
                            class="stat-value
                            <?= $todayNet >= 0
                                ? "text-success"
                                : "text-danger" ?>">

                            <?= money($todayNet) ?>

                        </div>

                    </div>


                    <div class="stat-icon icon-purple">

                        <i class="bi bi-graph-up-arrow"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     BASIC STATS
========================================================= -->

<div class="row g-4 mb-4">


    <!-- CUSTOMERS -->

    <div class="col-xl-3 col-md-6">

        <div class="stat-card">

            <div class="stat-body">

                <div class="d-flex align-items-center">

                    <div class="stat-icon icon-blue me-3">

                        <i class="bi bi-people-fill"></i>

                    </div>


                    <div>

                        <div class="stat-label">
                            <?= e(t("customers")) ?>
                        </div>

                        <div class="stat-value">
                            <?= number_format($totalCustomers) ?>
                        </div>

                    </div>

                </div>


                <a
                    href="../customers/index.php"
                    class="stat-link">

                    <?= e(t("manage_customers")) ?>

                    <i class="bi bi-arrow-right"></i>

                </a>

            </div>

        </div>

    </div>


    <!-- SUPPLIERS -->

    <div class="col-xl-3 col-md-6">

        <div class="stat-card">

            <div class="stat-body">

                <div class="d-flex align-items-center">

                    <div class="stat-icon icon-orange me-3">

                        <i class="bi bi-truck"></i>

                    </div>


                    <div>

                        <div class="stat-label">
                            <?= e(t("suppliers")) ?>
                        </div>

                        <div class="stat-value">
                            <?= number_format($totalSuppliers) ?>
                        </div>

                    </div>

                </div>


                <a
                    href="../suppliers/index.php"
                    class="stat-link">

                    <?= e(t("manage_suppliers")) ?>

                    <i class="bi bi-arrow-right"></i>

                </a>

            </div>

        </div>

    </div>


    <!-- PRODUCTS -->

    <div class="col-xl-3 col-md-6">

        <div class="stat-card">

            <div class="stat-body">

                <div class="d-flex align-items-center">

                    <div class="stat-icon icon-purple me-3">

                        <i class="bi bi-box-seam-fill"></i>

                    </div>


                    <div>

                        <div class="stat-label">
                            <?= e(t("products")) ?>
                        </div>

                        <div class="stat-value">
                            <?= number_format($totalProducts) ?>
                        </div>

                    </div>

                </div>


                <a
                    href="../products/index.php"
                    class="stat-link">

                    <?= e(t("manage_products")) ?>

                    <i class="bi bi-arrow-right"></i>

                </a>

            </div>

        </div>

    </div>


    <!-- INGREDIENTS -->

    <div class="col-xl-3 col-md-6">

        <div class="stat-card">

            <div class="stat-body">

                <div class="d-flex align-items-center">

                    <div class="stat-icon icon-green me-3">

                        <i class="bi bi-droplet-fill"></i>

                    </div>


                    <div>

                        <div class="stat-label">
                            <?= e(t("ingredients")) ?>
                        </div>

                        <div class="stat-value">
                            <?= number_format($totalIngredients) ?>
                        </div>

                    </div>

                </div>


                <a
                    href="../ingredients/index.php"
                    class="stat-link">

                    <?= e(t("manage_ingredients")) ?>

                    <i class="bi bi-arrow-right"></i>

                </a>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     QUICK ACTIONS
========================================================= -->

<div class="section-card mb-4">

    <div class="section-header">

        <h5 class="section-title">

            <i class="bi bi-lightning-charge-fill text-warning"></i>

            <?= e(t("quick_actions")) ?>

        </h5>

    </div>


    <div class="p-4">

        <div class="row g-3">


            <div class="col-xl-2 col-md-4 col-6">

                <a
                    href="../sales/index.php"
                    class="quick-action">

                    <div class="quick-icon">

                        <i class="bi bi-cart-plus-fill"></i>

                    </div>

                    <div class="quick-text">

                        <?= e(t("new_sale")) ?>

                    </div>

                </a>

            </div>


            <div class="col-xl-2 col-md-4 col-6">

                <a
                    href="../purchases/index.php"
                    class="quick-action">

                    <div class="quick-icon">

                        <i class="bi bi-bag-plus-fill"></i>

                    </div>

                    <div class="quick-text">

                        <?= e(t("new_purchase")) ?>

                    </div>

                </a>

            </div>


            <div class="col-xl-2 col-md-4 col-6">

                <a
                    href="../products/index.php"
                    class="quick-action">

                    <div class="quick-icon">

                        <i class="bi bi-box-seam-fill"></i>

                    </div>

                    <div class="quick-text">

                        <?= e(t("add_product")) ?>

                    </div>

                </a>

            </div>


            <div class="col-xl-2 col-md-4 col-6">

                <a
                    href="../customers/add.php"
                    class="quick-action">

                    <div class="quick-icon">

                        <i class="bi bi-person-plus-fill"></i>

                    </div>

                    <div class="quick-text">

                        <?= e(t("add_customer")) ?>

                    </div>

                </a>

            </div>


            <div class="col-xl-2 col-md-4 col-6">

                <a
                    href="../suppliers/index.php"
                    class="quick-action">

                    <div class="quick-icon">

                        <i class="bi bi-truck"></i>

                    </div>

                    <div class="quick-text">

                        <?= e(t("add_supplier")) ?>

                    </div>

                </a>

            </div>


            <div class="col-xl-2 col-md-4 col-6">

                <a
                    href="../expenses/add.php"
                    class="quick-action">

                    <div class="quick-icon">

                        <i class="bi bi-cash-stack"></i>

                    </div>

                    <div class="quick-text">

                        <?= e(t("add_expense")) ?>

                    </div>

                </a>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     DUE AMOUNTS
========================================================= -->

<div class="row g-4 mb-4">


    <div class="col-lg-6">

        <div class="due-card">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <div class="stat-label">

                        <?= e(t("customer_due")) ?>

                    </div>


                    <div class="due-value text-danger">

                        <?= money($customerDue) ?>

                    </div>


                    <a
                        href="../sales/index.php"
                        class="btn btn-sm btn-outline-danger rounded-3 mt-3">

                        <?= e(t("view_sales")) ?>

                        <i class="bi bi-arrow-right"></i>

                    </a>

                </div>


                <i
                    class="bi bi-person-exclamation due-icon text-danger">
                </i>

            </div>

        </div>

    </div>


    <div class="col-lg-6">

        <div class="due-card supplier-due">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <div class="stat-label">

                        <?= e(t("supplier_due")) ?>

                    </div>


                    <div class="due-value text-warning">

                        <?= money($supplierDue) ?>

                    </div>


                    <a
                        href="../purchases/index.php"
                        class="btn btn-sm btn-outline-warning rounded-3 mt-3">

                        <?= e(t("view_purchases")) ?>

                        <i class="bi bi-arrow-right"></i>

                    </a>

                </div>


                <i
                    class="bi bi-truck due-icon text-warning">
                </i>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     LOW STOCK
========================================================= -->

<div class="row g-4 mb-4">


    <!-- PRODUCTS -->

    <div class="col-lg-6">

        <div class="section-card">

            <div class="section-header">

                <div class="d-flex justify-content-between align-items-center">

                    <h5 class="section-title">

                        <i class="bi bi-box-seam-fill text-danger"></i>

                        <?= e(t("low_stock_products")) ?>

                    </h5>


                    <a
                        href="../products/index.php"
                        class="btn btn-sm btn-outline-primary rounded-3">

                        <?= e(t("view_all")) ?>

                    </a>

                </div>

            </div>


            <?php if (empty($lowStockProducts)): ?>

                <div class="empty-state">

                    <i class="bi bi-check-circle-fill"></i>

                    <?= e(t("all_products_enough")) ?>

                </div>

            <?php else: ?>


                <?php foreach ($lowStockProducts as $product): ?>

                    <div class="stock-item">

                        <div class="d-flex align-items-center">

                            <div class="stock-icon me-3">

                                <i class="bi bi-box"></i>

                            </div>


                            <div class="flex-grow-1">

                                <div class="stock-name">

                                    <?= e(
                                        $product["product_name"]
                                    ) ?>

                                </div>


                                <div class="stock-info">

                                    <?= $isPashto
                                        ? "حد اقل: "
                                        : "Minimum: " ?>

                                    <?= e(
                                        $product["minimum_stock"]
                                    ) ?>

                                    <?= e(
                                        $product["unit"]
                                    ) ?>

                                </div>

                            </div>


                            <span
                                class="badge bg-danger stock-badge">

                                <?= e(
                                    $product["stock_quantity"]
                                ) ?>

                                <?= e(
                                    $product["unit"]
                                ) ?>

                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>


    <!-- INGREDIENTS -->

    <div class="col-lg-6">

        <div class="section-card">

            <div class="section-header">

                <div class="d-flex justify-content-between align-items-center">

                    <h5 class="section-title">

                        <i class="bi bi-droplet-fill text-danger"></i>

                        <?= e(t("low_stock_ingredients")) ?>

                    </h5>


                    <a
                        href="../ingredients/index.php"
                        class="btn btn-sm btn-outline-primary rounded-3">

                        <?= e(t("view_all")) ?>

                    </a>

                </div>

            </div>


            <?php if (empty($lowStockIngredients)): ?>

                <div class="empty-state">

                    <i class="bi bi-check-circle-fill"></i>

                    <?= e(t("all_ingredients_enough")) ?>

                </div>

            <?php else: ?>


                <?php foreach ($lowStockIngredients as $ingredient): ?>

                    <div class="stock-item">

                        <div class="d-flex align-items-center">

                            <div class="stock-icon me-3">

                                <i class="bi bi-droplet"></i>

                            </div>


                            <div class="flex-grow-1">

                                <div class="stock-name">

                                    <?= e(
                                        $ingredient["ingredient_name"]
                                    ) ?>

                                </div>


                                <div class="stock-info">

                                    <?= $isPashto
                                        ? "حد اقل: "
                                        : "Minimum: " ?>

                                    <?= e(
                                        $ingredient["minimum_stock"]
                                    ) ?>

                                    <?= e(
                                        $ingredient["unit"]
                                    ) ?>

                                </div>

                            </div>


                            <span
                                class="badge bg-danger stock-badge">

                                <?= e(
                                    $ingredient["current_stock"]
                                ) ?>

                                <?= e(
                                    $ingredient["unit"]
                                ) ?>

                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>


<!-- =========================================================
     RECENT SALES
========================================================= -->

<div class="section-card mb-4">

    <div class="section-header">

        <div class="d-flex justify-content-between align-items-center">

            <h5 class="section-title">

                <i class="bi bi-cart-check-fill text-success"></i>

                <?= e(t("recent_sales")) ?>

            </h5>


            <a
                href="../sales/index.php"
                class="btn btn-sm btn-outline-primary rounded-3">

                <?= e(t("view_all")) ?>

            </a>

        </div>

    </div>


    <?php if (empty($recentSales)): ?>

        <div class="empty-state">

            <i class="bi bi-receipt"></i>

            <?= e(t("no_sales")) ?>

        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="table dashboard-table table-hover">

                <thead>

                    <tr>

                        <th>#</th>

                        <th><?= e(t("date")) ?></th>

                        <th><?= e(t("customer")) ?></th>

                        <th><?= e(t("total")) ?></th>

                        <th><?= e(t("paid")) ?></th>

                        <th><?= e(t("due")) ?></th>

                        <th><?= e(t("status")) ?></th>

                        <th><?= e(t("action")) ?></th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($recentSales as $sale): ?>

                    <?php

                    $status = paymentBadge(
                        $sale["payment_status"]
                    );

                    ?>

                    <tr>

                        <td>

                            <span class="sale-id">

                                #<?= (int)$sale["sale_id"] ?>

                            </span>

                        </td>


                        <td>

                            <?= e(
                                $sale["sale_date"]
                            ) ?>

                        </td>


                        <td>

                            <?php if (!empty($sale["customer_name"])): ?>

                                <strong>

                                    <?= e(
                                        $sale["customer_name"]
                                    ) ?>

                                </strong>

                            <?php else: ?>

                                <span class="text-muted">

                                    <?= e(t("walk_in")) ?>

                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <strong>

                                <?= money(
                                    $sale["total_amount"]
                                ) ?>

                            </strong>

                        </td>


                        <td class="text-success">

                            <?= money(
                                $sale["paid_amount"]
                            ) ?>

                        </td>


                        <td class="text-danger">

                            <?= money(
                                $sale["due_amount"]
                            ) ?>

                        </td>


                        <td>

                            <span
                                class="badge bg-<?= e($status["class"]) ?> status-badge">

                                <i class="bi <?= e($status["icon"]) ?>"></i>

                                <?= e($status["text"]) ?>

                            </span>

                        </td>


                        <td>

                            <a
                                href="../sales/view.php?id=<?= (int)$sale["sale_id"] ?>"
                                class="btn btn-primary btn-sm action-btn"
                                title="<?= e(t("view")) ?>">

                                <i class="bi bi-eye-fill"></i>

                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>


<!-- =========================================================
     RECENT PURCHASES
========================================================= -->

<div class="section-card mb-4">

    <div class="section-header">

        <div class="d-flex justify-content-between align-items-center">

            <h5 class="section-title">

                <i class="bi bi-bag-check-fill text-primary"></i>

                <?= e(t("recent_purchases")) ?>

            </h5>


            <a
                href="../purchases/index.php"
                class="btn btn-sm btn-outline-primary rounded-3">

                <?= e(t("view_all")) ?>

            </a>

        </div>

    </div>


    <?php if (empty($recentPurchases)): ?>

        <div class="empty-state">

            <i class="bi bi-bag"></i>

            <?= e(t("no_purchases")) ?>

        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="table dashboard-table table-hover">

                <thead>

                    <tr>

                        <th>#</th>

                        <th><?= e(t("date")) ?></th>

                        <th><?= e(t("supplier")) ?></th>

                        <th><?= e(t("total")) ?></th>

                        <th><?= e(t("paid")) ?></th>

                        <th><?= e(t("due")) ?></th>

                        <th><?= e(t("status")) ?></th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($recentPurchases as $purchase): ?>

                    <?php

                    $status = paymentBadge(
                        $purchase["payment_status"]
                    );

                    ?>

                    <tr>

                        <td>

                            <span class="sale-id">

                                #<?= (int)$purchase["purchase_id"] ?>

                            </span>

                        </td>


                        <td>

                            <?= e(
                                $purchase["purchase_date"]
                            ) ?>

                        </td>


                        <td>

                            <?php if (!empty($purchase["supplier_name"])): ?>

                                <strong>

                                    <?= e(
                                        $purchase["supplier_name"]
                                    ) ?>

                                </strong>

                            <?php else: ?>

                                <span class="text-muted">

                                    <?= e(t("no_supplier")) ?>

                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <strong>

                                <?= money(
                                    $purchase["total_amount"]
                                ) ?>

                            </strong>

                        </td>


                        <td class="text-success">

                            <?= money(
                                $purchase["paid_amount"]
                            ) ?>

                        </td>


                        <td class="text-danger">

                            <?= money(
                                $purchase["due_amount"]
                            ) ?>

                        </td>


                        <td>

                            <span
                                class="badge bg-<?= e($status["class"]) ?> status-badge">

                                <i class="bi <?= e($status["icon"]) ?>"></i>

                                <?= e($status["text"]) ?>

                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer">

    <i class="bi bi-shop"></i>

    <?= e(t("bakery_management_system")) ?>

    &copy;

    <?= date("Y") ?>

</footer>


</main>

</div>


<script src="../assets/js/bootstrap.bundle.min.js"></script>


<script>

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

const sidebarToggle =
    document.getElementById("sidebarToggle");

const sidebarOverlay =
    document.getElementById("sidebarOverlay");


if (sidebarToggle) {

    sidebarToggle.addEventListener("click", function () {

        document.body.classList.toggle(
            "sidebar-open"
        );

    });

}


if (sidebarOverlay) {

    sidebarOverlay.addEventListener("click", function () {

        document.body.classList.remove(
            "sidebar-open"
        );

    });

}


/*
|--------------------------------------------------------------------------
| CLOSE SIDEBAR AFTER CLICK ON MOBILE
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(".sidebar-link")
    .forEach(function(link) {

        link.addEventListener("click", function() {

            if (window.innerWidth <= 991) {

                document.body.classList.remove(
                    "sidebar-open"
                );

            }

        });

    });


/*
|--------------------------------------------------------------------------
| LIVE CLOCK
|--------------------------------------------------------------------------
*/

function updateClock() {

    const clock =
        document.getElementById("liveTime");

    if (!clock) {
        return;
    }

    const now = new Date();

    let hours =
        now.getHours();

    const minutes =
        String(
            now.getMinutes()
        ).padStart(2, "0");

    const seconds =
        String(
            now.getSeconds()
        ).padStart(2, "0");

    const ampm =
        hours >= 12
            ? "PM"
            : "AM";

    hours =
        hours % 12 || 12;

    clock.textContent =
        hours + ":" +
        minutes + ":" +
        seconds + " " +
        ampm;
}


updateClock();

setInterval(
    updateClock,
    1000
);

</script>


</body>

</html>