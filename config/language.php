<?php

/*
|--------------------------------------------------------------------------
| LANGUAGE SYSTEM
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Set Language
|--------------------------------------------------------------------------
*/

if (isset($_GET["lang"])) {

    if ($_GET["lang"] === "ps") {
        $_SESSION["language"] = "ps";
    } elseif ($_GET["lang"] === "en") {
        $_SESSION["language"] = "en";
    }

}

/*
|--------------------------------------------------------------------------
| Current Language
|--------------------------------------------------------------------------
*/

function currentLanguage()
{
    return $_SESSION["language"] ?? "ps";
}

/*
|--------------------------------------------------------------------------
| Language Direction
|--------------------------------------------------------------------------
*/

function languageDirection()
{
    return currentLanguage() === "ps" ? "rtl" : "ltr";
}

/*
|--------------------------------------------------------------------------
| Translation
|--------------------------------------------------------------------------
*/

function t($key)
{
    $translations = [

        /*
        |--------------------------------------------------------------------------
        | Navbar
        |--------------------------------------------------------------------------
        */

        "bakery_system" => [
            "en" => "Bakery System",
            "ps" => "د نانوایۍ سیستم"
        ],

        "logout" => [
            "en" => "Logout",
            "ps" => "وتل"
        ],

        "english" => [
            "en" => "English",
            "ps" => "انګلیسي"
        ],

        "pashto" => [
            "en" => "Pashto",
            "ps" => "پښتو"
        ],

        "user" => [
            "en" => "User",
            "ps" => "کاروونکی"
        ],

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        "dashboard" => [
            "en" => "Dashboard",
            "ps" => "ډشبورډ"
        ],

        "overview" => [
            "en" => "Bakery Management System Overview",
            "ps" => "د نانوایۍ د مدیریت سیستم عمومي کتنه"
        ],

        "database_backup" => [
            "en" => "Database Backup",
            "ps" => "د ډیټابیس بک اپ"
        ],

        /*
        |--------------------------------------------------------------------------
        | Statistics
        |--------------------------------------------------------------------------
        */

        "todays_sales" => [
            "en" => "Today's Sales",
            "ps" => "د نن ورځې خرڅلاو"
        ],

        "todays_purchases" => [
            "en" => "Today's Purchases",
            "ps" => "د نن ورځې پېرودنې"
        ],

        "todays_expenses" => [
            "en" => "Today's Expenses",
            "ps" => "د نن ورځې لګښتونه"
        ],

        "todays_net" => [
            "en" => "Today's Net",
            "ps" => "د نن ورځې خالصه ګټه"
        ],

        /*
        |--------------------------------------------------------------------------
        | Main Sections
        |--------------------------------------------------------------------------
        */

        "customers" => [
            "en" => "Customers",
            "ps" => "پېرودونکي"
        ],

        "suppliers" => [
            "en" => "Suppliers",
            "ps" => "عرضه کوونکي"
        ],

        "products" => [
            "en" => "Products",
            "ps" => "محصولات"
        ],

        "ingredients" => [
            "en" => "Ingredients",
            "ps" => "اجزا"
        ],

        "manage_customers" => [
            "en" => "Manage Customers",
            "ps" => "پېرودونکي اداره کړئ"
        ],

        "manage_suppliers" => [
            "en" => "Manage Suppliers",
            "ps" => "عرضه کوونکي اداره کړئ"
        ],

        "manage_products" => [
            "en" => "Manage Products",
            "ps" => "محصولات اداره کړئ"
        ],

        "manage_ingredients" => [
            "en" => "Manage Ingredients",
            "ps" => "اجزا اداره کړئ"
        ],

        /*
        |--------------------------------------------------------------------------
        | Quick Actions
        |--------------------------------------------------------------------------
        */

        "quick_actions" => [
            "en" => "Quick Actions",
            "ps" => "چټک کارونه"
        ],

        "new_sale" => [
            "en" => "New Sale",
            "ps" => "نوی خرڅلاو"
        ],

        "new_purchase" => [
            "en" => "New Purchase",
            "ps" => "نوې پېرودنه"
        ],

        "add_product" => [
            "en" => "Add Product",
            "ps" => "محصول اضافه کړئ"
        ],

        "add_customer" => [
            "en" => "Add Customer",
            "ps" => "پېرودونکی اضافه کړئ"
        ],

        "add_supplier" => [
            "en" => "Add Supplier",
            "ps" => "عرضه کوونکی اضافه کړئ"
        ],

        "add_expense" => [
            "en" => "Add Expense",
            "ps" => "لګښت اضافه کړئ"
        ],

        /*
        |--------------------------------------------------------------------------
        | Due
        |--------------------------------------------------------------------------
        */

        "customer_due" => [
            "en" => "Customer Due",
            "ps" => "د پېرودونکو پاتې پیسې"
        ],

        "supplier_due" => [
            "en" => "Supplier Due",
            "ps" => "د عرضه کوونکو پاتې پیسې"
        ],

        "view_sales" => [
            "en" => "View Sales",
            "ps" => "خرڅلاو وګورئ"
        ],

        "view_purchases" => [
            "en" => "View Purchases",
            "ps" => "پېرودنې وګورئ"
        ],

        /*
        |--------------------------------------------------------------------------
        | Stock
        |--------------------------------------------------------------------------
        */

        "low_stock_products" => [
            "en" => "Low Stock Products",
            "ps" => "د کم موجودي محصولات"
        ],

        "low_stock_ingredients" => [
            "en" => "Low Stock Ingredients",
            "ps" => "د کم موجودي اجزا"
        ],

        "view_all" => [
            "en" => "View All",
            "ps" => "ټول وګورئ"
        ],

        "product" => [
            "en" => "Product",
            "ps" => "محصول"
        ],

        "ingredient" => [
            "en" => "Ingredient",
            "ps" => "جز"
        ],

        "stock" => [
            "en" => "Stock",
            "ps" => "موجودي"
        ],

        "minimum" => [
            "en" => "Minimum",
            "ps" => "لږ تر لږه"
        ],

        "all_products_enough" => [
            "en" => "All products have enough stock.",
            "ps" => "د ټولو محصولاتو موجودي کافي ده."
        ],

        "all_ingredients_enough" => [
            "en" => "All ingredients have enough stock.",
            "ps" => "د ټولو اجزاوو موجودي کافي ده."
        ],

        /*
        |--------------------------------------------------------------------------
        | Recent
        |--------------------------------------------------------------------------
        */

        "recent_sales" => [
            "en" => "Recent Sales",
            "ps" => "وروستي خرڅلاوونه"
        ],

        "recent_purchases" => [
            "en" => "Recent Purchases",
            "ps" => "وروستۍ پېرودنې"
        ],

        "date" => [
            "en" => "Date",
            "ps" => "نېټه"
        ],

        "customer" => [
            "en" => "Customer",
            "ps" => "پېرودونکی"
        ],

        "supplier" => [
            "en" => "Supplier",
            "ps" => "عرضه کوونکی"
        ],

        "total" => [
            "en" => "Total",
            "ps" => "ټول"
        ],

        "paid" => [
            "en" => "Paid",
            "ps" => "ورکړل شوي"
        ],

        "due" => [
            "en" => "Due",
            "ps" => "پاتې"
        ],

        "status" => [
            "en" => "Status",
            "ps" => "حالت"
        ],

        "action" => [
            "en" => "Action",
            "ps" => "عمل"
        ],

        "actions" => [
            "en" => "Actions",
            "ps" => "کړنې"
        ],

        "no_sales" => [
            "en" => "No sales found.",
            "ps" => "هیڅ خرڅلاو ونه موندل شو."
        ],

        "no_purchases" => [
            "en" => "No purchases found.",
            "ps" => "هیڅ پېرودنه ونه موندل شوه."
        ],

        "walk_in" => [
            "en" => "Walk-in",
            "ps" => "مستقیم پېرودونکی"
        ],

        "no_supplier" => [
            "en" => "No Supplier",
            "ps" => "هیڅ عرضه کوونکی نشته"
        ],

        /*
        |--------------------------------------------------------------------------
        | Payment Status
        |--------------------------------------------------------------------------
        */

        "payment_paid" => [
            "en" => "Paid",
            "ps" => "ورکړل شوي"
        ],

        "payment_partial" => [
            "en" => "Partial",
            "ps" => "یوه برخه"
        ],

        "payment_unpaid" => [
            "en" => "Unpaid",
            "ps" => "ناورکړل شوي"
        ],

        /*
        |--------------------------------------------------------------------------
        | Sales / POS
        |--------------------------------------------------------------------------
        */

        "sale" => [
            "en" => "Sale",
            "ps" => "خرڅلاو"
        ],

        "sales" => [
            "en" => "Sales",
            "ps" => "خرڅلاو"
        ],

        "sales_pos" => [
            "en" => "Sales POS",
            "ps" => "د خرڅلاو سیستم"
        ],

        "bakery_management" => [
            "en" => "Bakery Management",
            "ps" => "د بیکري مدیریت"
        ],

        "sales_pos_page" => [
            "en" => "Sales / POS",
            "ps" => "خرڅلاو / POS"
        ],

        "create_manage_sales" => [
            "en" => "Create and manage bakery sales",
            "ps" => "د بیکري خرڅلاو جوړول او اداره کول"
        ],

        "sales_history" => [
            "en" => "Sales History",
            "ps" => "د خرڅلاو تاریخچه"
        ],

        "all_completed_sales" => [
            "en" => "All completed sales",
            "ps" => "ټول بشپړ شوي خرڅلاوونه"
        ],

        "no_sales_found" => [
            "en" => "No Sales Found",
            "ps" => "هیڅ خرڅلاو پیدا نه شو"
        ],

        "no_sales_recorded" => [
            "en" => "There are no sales recorded yet.",
            "ps" => "تر اوسه هېڅ خرڅلاو ثبت شوی نه دی."
        ],

        /*
        |--------------------------------------------------------------------------
        | Sales Form
        |--------------------------------------------------------------------------
        */

        "select_customer" => [
            "en" => "Select Customer",
            "ps" => "پېرودونکی انتخاب کړئ"
        ],

        "walk_in_customer" => [
            "en" => "Walk-in Customer",
            "ps" => "مستقیم پېرودونکی"
        ],

        "select_product" => [
            "en" => "Select Product",
            "ps" => "محصول انتخاب کړئ"
        ],

        "quantity" => [
            "en" => "Quantity",
            "ps" => "مقدار"
        ],

        "unit_price" => [
            "en" => "Unit Price",
            "ps" => "د واحد بیه"
        ],

        "remove_product" => [
            "en" => "Remove Product",
            "ps" => "محصول لرې کول"
        ],

        "payment" => [
            "en" => "Payment",
            "ps" => "تادیه"
        ],

        "subtotal" => [
            "en" => "Subtotal",
            "ps" => "فرعي مجموعه"
        ],

        "discount" => [
            "en" => "Discount",
            "ps" => "تخفیف"
        ],

        "grand_total" => [
            "en" => "Grand Total",
            "ps" => "ټولیزه بیه"
        ],

        "paid_amount" => [
            "en" => "Paid Amount",
            "ps" => "ورکړل شوې پیسې"
        ],

        "due_amount" => [
            "en" => "Due Amount",
            "ps" => "پاتې پیسې"
        ],

        "payment_method" => [
            "en" => "Payment Method",
            "ps" => "د تادیې طریقه"
        ],

        "cash" => [
            "en" => "Cash",
            "ps" => "نغدې"
        ],

        "card" => [
            "en" => "Card",
            "ps" => "کارت"
        ],

        "bank" => [
            "en" => "Bank",
            "ps" => "بانک"
        ],

        "credit" => [
            "en" => "Credit",
            "ps" => "پور"
        ],

        "notes" => [
            "en" => "Notes",
            "ps" => "یادښتونه"
        ],

        "optional_notes" => [
            "en" => "Optional notes...",
            "ps" => "اختیاري یادښتونه..."
        ],

        "complete_sale" => [
            "en" => "Complete Sale",
            "ps" => "خرڅلاو بشپړول"
        ],

        /*
        |--------------------------------------------------------------------------
        | Additional Sales
        |--------------------------------------------------------------------------
        */

        "sale_number" => [
            "en" => "Sale #",
            "ps" => "د خرڅلاو شمېره #"
        ],

        "sale_date" => [
            "en" => "Sale Date",
            "ps" => "د خرڅلاو نېټه"
        ],

        "customer_name" => [
            "en" => "Customer Name",
            "ps" => "د پېرودونکي نوم"
        ],

        "payment_status" => [
            "en" => "Payment Status",
            "ps" => "د تادیې حالت"
        ],

        "sale_details" => [
            "en" => "Sale Details",
            "ps" => "د خرڅلاو معلومات"
        ],

        "sale_items" => [
            "en" => "Sale Items",
            "ps" => "د خرڅلاو محصولات"
        ],

        "unit" => [
            "en" => "Unit",
            "ps" => "واحد"
        ],

        "price" => [
            "en" => "Price",
            "ps" => "بیه"
        ],

        "item_total" => [
            "en" => "Item Total",
            "ps" => "د محصول ټول"
        ],

        "back_to_sales" => [
            "en" => "Back to Sales",
            "ps" => "خرڅلاو ته بېرته تلل"
        ],

        "sale_not_found" => [
            "en" => "Sale not found.",
            "ps" => "خرڅلاو پیدا نه شو."
        ],

        "invalid_sale_id" => [
            "en" => "Invalid sale ID.",
            "ps" => "د خرڅلاو شمېره ناسمه ده."
        ],

        "invalid_payment_method" => [
            "en" => "Invalid payment method.",
            "ps" => "د تادیې طریقه ناسمه ده."
        ],

        "invalid_quantity" => [
            "en" => "Invalid quantity.",
            "ps" => "مقدار ناسم دی."
        ],

        "invalid_discount" => [
            "en" => "Invalid discount.",
            "ps" => "تخفیف ناسم دی."
        ],

        "sale_save_failed" => [
            "en" => "Failed to save the sale.",
            "ps" => "د خرڅلاو ثبتول ناکام شول."
        ],

        "error_occurred" => [
            "en" => "An error occurred.",
            "ps" => "یوه ستونزه رامنځته شوه."
        ],

        /*
        |--------------------------------------------------------------------------
        | Sales Actions
        |--------------------------------------------------------------------------
        */

        "view_sale" => [
            "en" => "View Sale",
            "ps" => "خرڅلاو کتل"
        ],

        "edit_sale" => [
            "en" => "Edit Sale",
            "ps" => "خرڅلاو سمول"
        ],

        "print_sale" => [
            "en" => "Print Sale",
            "ps" => "خرڅلاو چاپول"
        ],

        "delete_sale" => [
            "en" => "Delete Sale",
            "ps" => "خرڅلاو حذف کول"
        ],

        /*
        |--------------------------------------------------------------------------
        | Sales Messages
        |--------------------------------------------------------------------------
        */

        "sale_completed_successfully" => [
            "en" => "Sale completed successfully!",
            "ps" => "خرڅلاو په بریالیتوب سره بشپړ شو!"
        ],

        "sale_deleted_successfully" => [
            "en" => "Sale deleted successfully!",
            "ps" => "خرڅلاو په بریالیتوب سره حذف شو!"
        ],

        "product_stock_restored" => [
            "en" => "Product stock has been restored.",
            "ps" => "د محصول ذخیره بېرته ورزیاته شوه."
        ],

        "please_add_product" => [
            "en" => "Please add at least one product.",
            "ps" => "لږ تر لږه یو محصول اضافه کړئ."
        ],

        "please_select_product" => [
            "en" => "Please select a valid product.",
            "ps" => "مهرباني وکړئ یو سم محصول انتخاب کړئ."
        ],

        "product_not_found" => [
            "en" => "Product not found.",
            "ps" => "محصول پیدا نه شو."
        ],

        "not_enough_stock" => [
            "en" => "Not enough stock for:",
            "ps" => "د دې محصول ذخیره کافي نه ده:"
        ],

        "available_stock" => [
            "en" => "Available stock:",
            "ps" => "موجوده ذخیره:"
        ],

        "stock_update_failed" => [
            "en" => "Stock update failed for product ID:",
            "ps" => "د محصول د ذخیرې تازه کول ناکام شول:"
        ],

        "quantity_greater_stock" => [
            "en" => "Quantity is greater than available stock.",
            "ps" => "مقدار له موجودې ذخیرې څخه زیات دی."
        ],

        "duplicate_product" => [
            "en" => "This product is already selected. Please use one row.",
            "ps" => "دا محصول مخکې انتخاب شوی دی. یوازې یوه کرښه وکاروئ."
        ],

        "same_product" => [
            "en" => "The same product cannot be selected more than once.",
            "ps" => "یو محصول له یو ځل څخه زیات انتخابېدای نشي."
        ],

        "select_valid_product" => [
            "en" => "Please select at least one valid product.",
            "ps" => "مهرباني وکړئ لږ تر لږه یو سم محصول انتخاب کړئ."
        ],

        "complete_this_sale" => [
            "en" => "Complete this sale?",
            "ps" => "ایا دا خرڅلاو بشپړ کړئ؟"
        ],

        "confirm_delete_sale" => [
            "en" => "Are you sure you want to delete this sale? Product stock will be restored.",
            "ps" => "ایا ډاډه یاست چې دا خرڅلاو حذف کړئ؟ د محصول ذخیره به بېرته ورزیاته شي."
        ],

        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        "bakery_management_system" => [
            "en" => "Bakery Management System",
            "ps" => "د نانوایۍ د مدیریت سیستم"
        ]
    ];

    $lang = currentLanguage();

    return $translations[$key][$lang] ?? $key;
}