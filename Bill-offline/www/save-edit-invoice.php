<?php
session_start();
require_once 'config.php';

// 1. Security and Pre-checks
// =====================================
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_POST['invoice_id']) ||
    !isset($_POST['invoice_date']) ||
    !isset($_POST['invoice_type']) ||
    !isset($_POST['with_tax'])
) {
    header('Location: edit-step-1.php?error=invalid_request');
    exit;
}

$invoice_id = intval($_POST['invoice_id']);

if (!isset($_SESSION['preview_invoice_data'][$invoice_id]) || empty($_SESSION['preview_invoice_data'][$invoice_id]['items'])) {
    header('Location: invoice-edit-products.php?id=' . $invoice_id . '&error=no_items_in_session');
    exit;
}

// 2. Data Retrieval from Session and POST
// =====================================
$invoice_data_from_session = $_SESSION['preview_invoice_data'][$invoice_id];
$invoice_items_new = $invoice_data_from_session['items']; // The new state of the invoice

$invoice_date = $_POST['invoice_date'];
$invoice_type = $_POST['invoice_type'];
$with_tax_value = $_POST['with_tax'];
$with_tax = ($with_tax_value === 'Yes');

// 3. Database Transaction (Update Invoice and Stock)
// =====================================
try {
    $pdo->beginTransaction();

    // *** STEP A: FETCH ORIGINAL QUANTITIES BEFORE MAKING CHANGES ***
    $orig_items_stmt = $pdo->prepare("SELECT product_id, quantity FROM invoice_items WHERE invoice_id = ?");
    $orig_items_stmt->execute([$invoice_id]);
    $original_quantities = $orig_items_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 1. Update the main invoice record with the new date and settings.
    $update_inv_stmt = $pdo->prepare(
        "UPDATE invoices SET invoice_date = ?, invoice_type = ?, with_tax = ? WHERE id = ?"
    );
    $update_inv_stmt->execute([
        $invoice_date,
        $invoice_type,
        $with_tax_value,
        $invoice_id
    ]);

    // 2. Delete all old items for this invoice to replace them with the new set.
    $delete_stmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
    $delete_stmt->execute([$invoice_id]);

    // 3. Prepare statements for inserting new items and updating product stock.
    // MODIFIED: Added the 'discount' column
    $item_insert_stmt = $pdo->prepare(
        "INSERT INTO invoice_items (invoice_id, product_id, quantity, discount, cgst_percent, sgst_percent, cgst, sgst, subtotal, grand_total) 
         VALUES (:invoice_id, :product_id, :quantity, :discount, :cgst_percent, :sgst_percent, :cgst, :sgst, :subtotal, :grand_total)"
    );
    
    $product_update_stmt = $pdo->prepare(
        "UPDATE products SET quantity = quantity - :quantity_diff WHERE id = :product_id"
    );

    // 4. Loop through the NEW items to insert them and update stock
    foreach ($invoice_items_new as $item) {
        // --- MODIFICATION START: Updated calculation logic ---
        $new_quantity = floatval($item['quantity']);
        $price = floatval($item['price']);
        $discount_amount = floatval($item['discount'] ?? 0); // Get discount

        $cgst_percent = $with_tax ? floatval($item['cgst']) : 0;
        $sgst_percent = $with_tax ? floatval($item['sgst']) : 0;

        $item_subtotal = $new_quantity * $price;
        $taxable_value = $item_subtotal - $discount_amount; // Calculate taxable value after discount

        $cgst_amount = $taxable_value * ($cgst_percent / 100);
        $sgst_amount = $taxable_value * ($sgst_percent / 100);
        $item_grand_total = $taxable_value + $cgst_amount + $sgst_amount;

        // --- Insert new invoice item record ---
        // MODIFIED: Added ':discount' parameter
        $item_insert_stmt->execute([
            ':invoice_id'    => $invoice_id,
            ':product_id'    => $item['product_id'],
            ':quantity'      => $new_quantity,
            ':discount'      => $discount_amount, // <-- ADDED
            ':cgst_percent'  => $cgst_percent,
            ':sgst_percent'  => $sgst_percent,
            ':cgst'          => $cgst_amount,
            ':sgst'          => $sgst_amount,
            ':subtotal'      => $item_subtotal,
            ':grand_total'   => $item_grand_total
        ]);
        // --- MODIFICATION END ---
        
        // --- Calculate stock difference and update products table ---
        $old_quantity = isset($original_quantities[$item['product_id']]) ? floatval($original_quantities[$item['product_id']]) : 0;
        $quantity_difference = $new_quantity - $old_quantity;

        if ($quantity_difference != 0) {
            $product_update_stmt->execute([
                ':quantity_diff' => $quantity_difference,
                ':product_id'    => $item['product_id']
            ]);
        }
        
        unset($original_quantities[$item['product_id']]);
    }

    // *** STEP B: HANDLE PRODUCTS THAT WERE COMPLETELY REMOVED ***
    foreach ($original_quantities as $product_id => $old_quantity) {
        $product_update_stmt->execute([
            ':quantity_diff' => -$old_quantity,
            ':product_id'    => $product_id
        ]);
    }

    $pdo->commit();

    // 5. Cleanup and Redirect
    // =====================================
    unset($_SESSION['preview_invoice_data'][$invoice_id]);
    header('Location: edit-preview.php?status=success&invoice_id=' . $invoice_id);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Save Edit Failed for Invoice ID $invoice_id: " . $e->getMessage()); 
    header('Location: invoice-edit-products.php?id=' . $invoice_id . '&error=save_failed');
    exit;
}
?>