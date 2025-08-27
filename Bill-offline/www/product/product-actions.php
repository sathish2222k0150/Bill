<?php
// admin/product-actions.php

header('Content-Type: application/json');
require_once '../config.php'; // Ensure your DB connection ($pdo) is established here
session_start();

// Basic security check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$response = ['status' => 'error', 'message' => 'Invalid request'];
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    switch ($action) {
        case 'fetch_products':
            // This logic remains unchanged
            $sql = "SELECT id, product_name, brand_name, model, quantity, retail_price FROM products WHERE 1=1";
            $params = [];

            if (!empty($_GET['brand'])) {
                $sql .= " AND brand_name = :brand";
                $params[':brand'] = trim($_GET['brand']);
            }
            
            if (!empty($_GET['model'])) {
                $sql .= " AND model = :model";
                $params[':model'] = trim($_GET['model']);
            }
            
            $allowedSortColumns = ['id', 'product_name', 'brand_name', 'model', 'quantity', 'retail_price'];
            $sortBy = in_array($_GET['sortBy'] ?? '', $allowedSortColumns) ? $_GET['sortBy'] : 'id';
            $sortOrder = isset($_GET['sortOrder']) && strtoupper($_GET['sortOrder']) === 'ASC' ? 'ASC' : 'DESC';
            $sql .= " ORDER BY $sortBy $sortOrder";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = ['status' => 'success', 'data' => $products];
            break;

        case 'fetch_single_product':
            // This logic remains unchanged
            if (empty($_GET['id'])) {
                throw new Exception('Product ID is required.');
            }
            $productId = intval($_GET['id']);
            
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
            $stmt->execute([':id' => $productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $response = ['status' => 'success', 'data' => $product];
            } else {
                throw new Exception('Product not found.');
            }
            break;

        case 'add_product':
        case 'update_product':
            // Consolidate logic for add and update
            if ($action === 'update_product' && empty(trim($_POST['product_id']))) {
                 throw new Exception('Product ID is missing.');
            }
            if (empty(trim($_POST['product_name']))) {
                throw new Exception('Product Name is required.');
            }

            // Determine final brand and model names
            $brand_name = !empty(trim($_POST['brand_manual'] ?? '')) 
                ? trim($_POST['brand_manual']) 
                : trim($_POST['brand_select'] ?? '');
            
            $model = !empty(trim($_POST['model_manual'] ?? '')) 
                ? trim($_POST['model_manual']) 
                : trim($_POST['model_select'] ?? '');

            if ($action === 'add_product') {
                // --- CORRECTED FOR SQLITE ---
                    $stmt = $pdo->prepare("
                        INSERT INTO products (brand_name, model, product_name, product_description, product_location, quantity, dealer_price, retail_price, cgst, sgst, created_at, updated_at) 
                        VALUES (:brand_name, :model, :product_name, :product_description, :product_location, :quantity, :dealer_price, :retail_price, :cgst, :sgst, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                    ");
            } else { // update_product
                // --- CORRECTED FOR SQLITE ---
                    $stmt = $pdo->prepare("
                        UPDATE products SET brand_name = :brand_name, model = :model, product_name = :product_name, product_description = :product_description, product_location = :product_location, quantity = :quantity, dealer_price = :dealer_price, retail_price = :retail_price, cgst = :cgst, sgst = :sgst, updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id
                    ");
            }

            $params = [
                ':brand_name' => ($brand_name === 'new_brand' || empty($brand_name)) ? null : $brand_name,
                ':model' => ($model === 'new_model' || empty($model)) ? null : $model,
                ':product_name' => trim($_POST['product_name']),
                ':product_description' => trim($_POST['product_description'] ?? ''),
                ':product_location' => trim($_POST['product_location'] ?? ''),
                ':quantity' => intval($_POST['quantity'] ?? 0),
                ':dealer_price' => !empty($_POST['dealer_price']) ? floatval($_POST['dealer_price']) : null,
                ':retail_price' => !empty($_POST['retail_price']) ? floatval($_POST['retail_price']) : null,
                ':cgst' => !empty($_POST['cgst']) ? floatval($_POST['cgst']) : null,
                ':sgst' => !empty($_POST['sgst']) ? floatval($_POST['sgst']) : null
            ];

            if ($action === 'update_product') {
                $params[':id'] = intval($_POST['product_id']);
            }
            
            $success = $stmt->execute($params);
            
            if ($success) {
                $message = $action === 'add_product' ? 'Product added successfully.' : 'Product updated successfully.';
                $response = ['status' => 'success', 'message' => $message];

                // **MODIFICATION**: Check for new brand/model and add to response
                if (!empty(trim($_POST['brand_manual'] ?? ''))) {
                    $response['newBrand'] = trim($_POST['brand_manual']);
                }
                if (!empty(trim($_POST['model_manual'] ?? ''))) {
                    $response['newModel'] = trim($_POST['model_manual']);
                    $response['associatedBrand'] = $brand_name; // The brand this new model belongs to
                }
                 if ($action === 'add_product') {
                    $response['product_id'] = $pdo->lastInsertId();
                }

            } else {
                throw new Exception("Failed to {$action}: " . implode(' ', $stmt->errorInfo()));
            }
            break;

        case 'delete_product':
            // This logic remains unchanged
            if (empty($_POST['id'])) {
                throw new Exception('Product ID is required.');
            }
            
            $productId = intval($_POST['id']);
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $success = $stmt->execute([':id' => $productId]);

            if ($success && $stmt->rowCount() > 0) {
                $response = ['status' => 'success', 'message' => 'Product deleted successfully.'];
            } else {
                 throw new Exception('Product not found or could not be deleted.');
            }
            break;

        default:
            $response = ['status' => 'error', 'message' => 'Invalid action specified.'];
            break;
    }
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}


echo json_encode($response);
?>