<?php
// api_login.php

// Allow requests from any origin (for local testing)
header("Access-Control-Allow-Origin: *");
// Allow the 'Content-Type' header, which is needed for JSON
header("Access-Control-Allow-Headers: Content-Type");
// Tell the client that this file returns JSON data
header("Content-Type: application/json; charset=UTF-8");

require_once 'config.php';

$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ---- THIS IS THE MAIN CHANGE ----
    // Read the raw JSON data sent from the Electron app
    $json_data = file_get_contents('php://input');
    // Decode the JSON string into a PHP object
    $data = json_decode($json_data);

    // Get the username and password from the decoded data
    // We check if they exist before using them
    $username = trim($data->username ?? '');
    $password = $data->password ?? ''; // Passwords should not be trimmed
    
    // The rest of the logic is the same as before
    if (!empty($username) && !empty($password)) {
        try {
            $query = "SELECT id, username, password, role 
                      FROM users 
                      WHERE username = :username 
                      LIMIT 1";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $token = bin2hex(random_bytes(32));
                $response['success'] = true;
                $response['message'] = 'Login successful!';
                $response['token'] = $token;
                $response['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ];
            } else {
                $response['success'] = false;
                $response['message'] = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = 'Database error. Please contact support.';
        }
    } else {
        $response['success'] = false;
        $response['message'] = 'Please fill in all fields';
    }
} else {
    $response['success'] = false;
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>