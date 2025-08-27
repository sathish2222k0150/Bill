<?php
require_once '../config.php';
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM vendors WHERE vendor_id = ?");
        $stmt->execute([$_GET['id']]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vendor) {
            echo json_encode($vendor);
        } else {
            echo json_encode(['error' => 'Vendor not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'No vendor ID provided']);
}
?>