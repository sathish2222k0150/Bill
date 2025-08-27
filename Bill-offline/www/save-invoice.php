<?php
session_start();
require_once 'config.php';

// 1. Security and Pre-checks
// =====================================
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: preview.php?error=invalid_request');
    exit;
}

if (
    !isset($_POST['customer_id']) || empty($_POST['customer_id']) ||
    !isset($_POST['invoice_date']) || empty($_POST['invoice_date']) ||
    !isset($_POST['invoice_type']) || empty($_POST['invoice_type']) ||
    !isset($_POST['with_tax'])
) {
    header('Location: preview.php?error=missing_data');
    exit;
}

$customer_id = intval($_POST['customer_id']);

if (!isset($_SESSION['invoice_data'][$customer_id]['items']) || empty($_SESSION['invoice_data'][$customer_id]['items'])) {
    header('Location: preview.php?customer_id=' . $customer_id . '&error=no_items');
    exit;
}

// 2. Data Retrieval and Preparation
// =====================================
$invoice_items = $_SESSION['invoice_data'][$customer_id]['items'];
$invoice_date = $_POST['invoice_date'];
$invoice_type = $_POST['invoice_type'];
$with_tax_value = $_POST['with_tax'];
$with_tax = ($with_tax_value === 'Yes');


// 3. Database Transaction
// =====================================
try {
    $pdo->beginTransaction();

    // A. Create the main invoice record
    $lastIdStmt = $pdo->query("SELECT id FROM invoices ORDER BY id DESC LIMIT 1");
    $lastId = $lastIdStmt->fetchColumn();
    $newInvoiceNumber = 'SDS-' . str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare(
        "INSERT INTO invoices (customer_id, invoice_number, invoice_date, invoice_type, with_tax) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $customer_id, 
        $newInvoiceNumber, 
        $invoice_date,
        $invoice_type,
        $with_tax_value
    ]);
    $invoice_id = $pdo->lastInsertId();

    // B. Prepare statements for items and product stock update
    // MODIFIED: Added the 'discount' column to the INSERT statement
    $item_stmt = $pdo->prepare(
        "INSERT INTO invoice_items (invoice_id, product_id, quantity, discount, cgst_percent, sgst_percent, cgst, sgst, subtotal, grand_total) 
         VALUES (:invoice_id, :product_id, :quantity, :discount, :cgst_percent, :sgst_percent, :cgst, :sgst, :subtotal, :grand_total)"
    );
    
    $product_update_stmt = $pdo->prepare(
        "UPDATE products SET quantity = quantity - :sold_quantity WHERE id = :product_id"
    );


    // C. Loop through items, insert them, and update product stock
    foreach ($invoice_items as $item) {
        // --- MODIFICATION START: Updated calculation logic ---
        $quantity = floatval($item['quantity']);
        $price = floatval($item['price']);
        $discount_amount = floatval($item['discount'] ?? 0); // Get discount, default to 0 if not set
        
        $cgst_percent = $with_tax ? floatval($item['cgst']) : 0;
        $sgst_percent = $with_tax ? floatval($item['sgst']) : 0;
        
        // 'subtotal' is the value before discount
        $item_subtotal = $quantity * $price; 
        
        // Tax is calculated on the value AFTER discount
        $taxable_value = $item_subtotal - $discount_amount;
        
        $cgst_amount = $taxable_value * ($cgst_percent / 100);
        $sgst_amount = $taxable_value * ($sgst_percent / 100);

        // Grand total includes the discount
        $item_grand_total = $taxable_value + $cgst_amount + $sgst_amount;

        // Insert the invoice item record
        // MODIFIED: Added ':discount' to the execution array
        $item_stmt->execute([
            ':invoice_id' => $invoice_id,
            ':product_id' => $item['product_id'],
            ':quantity' => $quantity,
            ':discount' => $discount_amount, // <-- ADDED
            ':cgst_percent' => $cgst_percent,
            ':sgst_percent' => $sgst_percent,
            ':cgst' => $cgst_amount,
            ':sgst' => $sgst_amount,
            ':subtotal' => $item_subtotal,
            ':grand_total' => $item_grand_total
        ]);
        // --- MODIFICATION END ---
        
        // Reduce the quantity in the products table
        $product_update_stmt->execute([
            ':sold_quantity' => $quantity,
            ':product_id' => $item['product_id']
        ]);
    }

    $pdo->commit();

    // 4. Cleanup and Redirect
    // =====================================

    // Clear the saved invoice data from the session for this customer
    unset($_SESSION['invoice_data'][$customer_id]);

    // Redirect to the preview page to trigger the success modal
    header('Location: preview.php?status=success&invoice_id=' . $invoice_id);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // For production, you should log the error instead of displaying it
    error_log('Invoice save failed: ' . $e->getMessage());
    header('Location: preview.php?customer_id=' . $customer_id . '&error=save_failed');
    exit;
}
?>