<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: labour-invoice-list.php?error=invalid_id');
    exit;
}

$invoice_id = (int)$_GET['id'];

try {
    $pdo->beginTransaction();
    
    // Delete items first (due to foreign key constraint)
    $delete_items = $pdo->prepare("DELETE FROM labour_invoice_items WHERE invoice_id = ?");
    $delete_items->execute([$invoice_id]);
    
    // Delete invoice
    $delete_invoice = $pdo->prepare("DELETE FROM labour_invoices WHERE id = ?");
    $delete_invoice->execute([$invoice_id]);
    
    $pdo->commit();
    $_SESSION['success'] = "Labour invoice deleted successfully!";
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error deleting invoice: " . $e->getMessage();
}

header('Location: labour-invoice-list.php');
exit;