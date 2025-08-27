<?php
// auth_bridge.php

// We MUST start the session to create the session variables
session_start();
require_once 'config.php';

// Get the token and user details passed from the Electron app in the URL
$token = $_GET['token'] ?? '';
$userId = $_GET['userId'] ?? 0;
$username = $_GET['username'] ?? '';
$role = $_GET['role'] ?? '';

// In a real application, you would look up this token in your database
// to make sure it is valid and not expired.
// For this example, we will trust it if it's not empty.

if (!empty($token) && !empty($userId) && !empty($username)) {
    
    // The token is valid! Now we create the session for the rest of the PHP app.
    // These lines must match what your original index.php login did.
    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['role']     = $role;

    // Now that the session is created, redirect to the real dashboard.
    header('Location: dashboard.php');
    exit();

} else {
    // If the token is missing or invalid, send the user back to the PHP login page.
    echo "Authentication failed. Invalid token.";
    // header('Location: index.php');
    exit();
}
?>