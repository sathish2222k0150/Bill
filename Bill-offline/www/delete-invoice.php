<?php
require_once 'config.php';
session_start();

if(!isset($_SESSION['user_id'])){
    echo json_encode(['status'=>0,'message'=>'Unauthorized']); exit;
}

$id = (int)($_POST['id'] ?? 0);

if($id){
    $stmt = $pdo->prepare("DELETE FROM invoices WHERE id=?");
    if($stmt->execute([$id])){
        echo json_encode(['status'=>1,'message'=>'Invoice deleted successfully']);
    }else{
        echo json_encode(['status'=>0,'message'=>'Failed to delete invoice']);
    }
} else {
    echo json_encode(['status'=>0,'message'=>'Invalid invoice ID']);
}
?>
