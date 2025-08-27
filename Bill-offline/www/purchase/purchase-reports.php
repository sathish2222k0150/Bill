<?php
require_once '../config.php';
session_start();
// Handle filters
$where = [];
$params = [];

if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where[] = "date BETWEEN ? AND ?";
    $params[] = $_GET['from_date'];
    $params[] = $_GET['to_date'];
}

if (!empty($_GET['month'])) {
    $where[] = "MONTH(date) = ? AND YEAR(date) = ?";
    $params[] = explode('-', $_GET['month'])[1];
    $params[] = explode('-', $_GET['month'])[0];
}

if (!empty($_GET['vehicle_no'])) {
    $where[] = "vehicle_reg_no LIKE ?";
    $params[] = '%' . $_GET['vehicle_no'] . '%';
}

$sql = "SELECT p.*, v.name as vendor_name FROM purchases p 
        JOIN vendors v ON p.vendor_id = v.vendor_id";
        
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$purchases = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Purchase Bill - SDS Automotive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="preload" href="../css/adminlte.css" as="style" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q=" crossorigin="anonymous" media="print" onload="this.media='all'" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../css/adminlte.css" />
    <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
  <div class="app-wrapper">
    <?php include '../header.php'; ?>
    <?php include '../sidebar.php'; ?>
    
    <main class="app-main">
        <div class="container-fluid"></div>
    <div class="container mt-4">
        <h2>Purchase Reports</h2>
        
        <form class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Month</label>
                <input type="month" name="month" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Vehicle No</label>
                <input type="text" name="vehicle_no" class="form-control">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="purchase-reports.php" class="btn btn-secondary">Reset</a>
                <a href="export-purchases.php?<?= http_build_query($_GET) ?>" class="btn btn-success">Export Excel</a>
            </div>
        </form>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Bill No</th>
                    <th>Vendor</th>
                    <th>Purpose</th>
                    <th>Vehicle No</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($purchases as $purchase): ?>
                <tr>
                    <td><?= $purchase['date'] ?></td>
                    <td><?= $purchase['bill_no'] ?></td>
                    <td><?= $purchase['vendor_name'] ?></td>
                    <td><?= $purchase['purpose'] ?></td>
                    <td><?= $purchase['vehicle_reg_no'] ?? '-' ?></td>
                    <td><?= $purchase['total_amount'] ?></td>
                    <td>
                        <a href="purchase-view.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-info">View</a>
                        <a href="purchase-edit.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="../js/adminlte.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>