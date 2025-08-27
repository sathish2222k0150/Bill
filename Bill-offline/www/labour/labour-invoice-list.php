<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(customer_name LIKE ? OR vehicle_number LIKE ? OR invoice_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date_from)) {
    $where[] = "invoice_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where[] = "invoice_date <= ?";
    $params[] = $date_to;
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM labour_invoices $where_sql");
$count_stmt->execute($params);
$total_invoices = $count_stmt->fetchColumn();
$total_pages = ceil($total_invoices / $limit);

// Get invoices
$stmt = $pdo->prepare("
    SELECT * FROM labour_invoices 
    $where_sql 
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Labour Invoice</title>
    
    <!-- Bootstrap, AdminLTE, and other CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/adminlte.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
    <?php include '../header.php'; ?>
    <?php include '../sidebar.php'; ?>

    <main class="app-main">
        <!-- Main content header -->
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                <h2>Labour Invoices</h2>
                <a href="labour-invoice-create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Labour Invoice
                </a>
            </div>

            <!-- Search and Filter Form -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Search & Filter</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="Search by customer, vehicle, or invoice no..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_from" placeholder="From Date" value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_to" placeholder="To Date" value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="labour-invoice-list.php" class="btn btn-secondary w-100">
                                <i class="fas fa-sync"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Invoices Table -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Invoices List (<?= $total_invoices ?> total)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Vehicle</th>
                                    <th>Hours</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($invoices): ?>
                                    <?php foreach ($invoices as $invoice): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                            <td><?= date('d M Y', strtotime($invoice['invoice_date'])) ?></td>
                                            <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                                            <td><?= htmlspecialchars($invoice['vehicle_number']) ?: 'N/A' ?></td>
                                            <td><?= $invoice['total_hours'] ?></td>
                                            <td>₹<?= number_format($invoice['grand_total'], 2) ?></td>
                                            <td>
                                                <a href="labour-invoice-view.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="labour-invoice-edit.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="labour-invoice-print.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-primary" title="Print" target="_blank">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                <button onclick="deleteInvoice(<?= $invoice['id'] ?>)" class="btn btn-sm btn-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="fas fa-receipt fa-3x mb-3"></i>
                                            <p>No labour invoices found</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../js/adminlte.js"></script>                            
    <script>
    function deleteInvoice(id) {
        if (confirm('Are you sure you want to delete this invoice? This action cannot be undone.')) {
            window.location.href = 'labour-invoice-delete.php?id=' + id;
        }
    }
    </script>
</body>
</html>