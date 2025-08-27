<?php

header('Content-Type: application/json');
require '../config.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['customer_name']) && !empty($_POST['customer_contact'])) {
    try {
        $sql = "INSERT INTO customers (customer_name, customer_contact, reg_no, customer_address, gst_number) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['customer_name'],
            $_POST['customer_contact'],
            $_POST['reg_no'] ?? null,
            $_POST['customer_address'] ?? null,
            $_POST['gst_number'] ?? null
        ]);
        
        $new_customer_id = $pdo->lastInsertId();

        if ($new_customer_id) {
            $response = [
                'success' => true,
                'message' => 'Customer added successfully!',
                'customer' => [
                    'id' => $new_customer_id,
                    'name' => $_POST['customer_name'],
                    'phone' => $_POST['customer_contact'],
                    'vehicle' => $_POST['reg_no'] ?? ''
                ]
            ];
        } else {
            $response['message'] = 'Failed to add customer to the database.';
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
             $response['message'] = "Error: A customer with similar details might already exist.";
        } else {
             $response['message'] = "Database Error: " . $e->getMessage();
        }
    }
} else {
    $response['message'] = 'Invalid request. Please provide customer name and contact.';
}

echo json_encode($response);