<?php
require_once '../config.php';
session_start();

$stmt = $pdo->query("SELECT * FROM receipts ORDER BY created_at DESC");
$receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Receipts</title>
    
    <!-- AdminLTE and Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/adminlte.css"> <!-- Make sure path is correct -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />    
    <!-- DataTables CSS for Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
    <?php include '../header.php'; ?>
    <?php include '../sidebar.php'; ?>

    <main class="app-main">
        <!-- Content Header (Page header) -->
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h3 class="mb-0">All Receipts</h3>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Receipts</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main content -->
        <div class="app-content">
            <div class="container-fluid">
                <div class="card card-primary card-outline">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">
                            <i class="fa fa-file-invoice-dollar me-2"></i>
                            Receipt List
                        </h3>
                        <a href="receipts.php" class="btn btn-success">
                            <i class="fa fa-plus me-2"></i>Add New Receipt
                        </a>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="receiptsTable" class="table table-bordered table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Receipt Number</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Payment Mode</th>
                                        <th>Date</th>
                                        <!-- The 'no-sort' class can be used to disable sorting on this column -->
                                        <th class="text-center no-sort" style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($receipts as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['id']) ?></td>
                                        <td><?= htmlspecialchars($r['receipt_number']) ?></td>
                                        <td><?= htmlspecialchars($r['customer_name']) ?></td>
                                        <td>₹<?= number_format((float)$r['amount'], 2) ?></td>
                                        <td><?= htmlspecialchars($r['payment_mode']) ?></td>
                                        <td><?= htmlspecialchars(date('d-m-Y', strtotime($r['created_at']))) ?></td>
                                        <td class="text-center">
                                            <a class="btn btn-primary btn-sm mx-1" href="preview-receipt.php?receipt_number=<?= $r['receipt_number'] ?>" target="_blank" title="View">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <a class="btn btn-warning btn-sm mx-1" href="edit-receipt.php?id=<?= $r['id'] ?>" title="Edit">
                                                <i class="fa fa-edit text-white"></i>
                                            </a>
                                            <a class="btn btn-danger btn-sm mx-1" href="delete-receipt.php?id=<?= $r['id'] ?>" onclick="return confirm('Are you sure?');" title="Delete">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
        </div>
    </main>
</div>

<!-- JS Includes -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/adminlte.js"></script> <!-- Make sure path is correct -->

<!-- DataTables & Plugins JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<!-- Initialize DataTables -->
<script>
    $(document).ready(function() {
        $('#receiptsTable').DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "order": [[ 0, "desc" ]], // Order by the first column (ID) in descending order by default
            "language": {
                "search": "_INPUT_",
                "searchPlaceholder": "Search receipts..."
            }
        });
    });
</script>

</body>
</html>