<?php
// --- SETUP AND DATA FETCHING (from your dashboard.php) ---
require 'config.php'; // include DB connection
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// All data fetching logic is moved to the top of the file
$stats = [];
// Card 1: Total Invoices (Sales)
$stmt = $pdo->prepare("SELECT COUNT(i.id) as count, COALESCE(SUM(ii.grand_total), 0) as total FROM invoices i JOIN (SELECT invoice_id, SUM(grand_total) as grand_total FROM invoice_items GROUP BY invoice_id) ii ON i.id = ii.invoice_id WHERE i.invoice_type IN ('Invoice','Tax Invoice','Supplementary','Tax Supplementary')");
$stmt->execute();
$stats['invoices'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Card 2: Total Labour Invoices
$stmt = $pdo->prepare("SELECT COUNT(id) as count, COALESCE(SUM(grand_total), 0) as total FROM labour_invoices");
$stmt->execute();
$stats['labour_invoices'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Card 3: Total Purchase Invoices
$stmt = $pdo->prepare("SELECT COUNT(id) as count, COALESCE(SUM(total_amount + total_cgst + total_sgst), 0) as total FROM purchases");
$stmt->execute();
$stats['purchases'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Card 4: Total Customers
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM customers");
$stmt->execute();
$stats['customers_count'] = $stmt->fetchColumn();

// Card 5: Total Receipts
$stats['receipts'] = ['count' => 0, 'total' => 0.00];

// Card 6: Low Stock Products count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE quantity <= 5");
$stmt->execute();
$stats['low_stock_count'] = $stmt->fetchColumn();

// Monthly Sales Chart Data
$stmt = $pdo->prepare("
    SELECT 
        strftime('%m', i.invoice_date) as month, 
        COALESCE(SUM(ii.grand_total), 0) as total 
    FROM invoices i 
    JOIN invoice_items ii ON i.id = ii.invoice_id 
    WHERE 
        strftime('%Y', i.invoice_date) = strftime('%Y', 'now') 
        AND i.invoice_type IN ('Invoice','Tax Invoice','Supplementary','Tax Supplementary') 
    GROUP BY month
");

// Recent Activities Table Data
// --- CORRECTED FOR SQLITE ---
// Removed the parentheses around the first SELECT statement
$stmt = $pdo->prepare("
    SELECT 
        i.invoice_type as type, 
        i.invoice_number as number, 
        c.customer_name as name, 
        SUM(ii.grand_total) as amount, 
        i.created_at as activity_date 
    FROM invoices i 
    JOIN invoice_items ii ON i.id = ii.invoice_id 
    JOIN customers c ON i.customer_id = c.id 
    GROUP BY i.id

    UNION ALL 

    SELECT 
        'Labour Invoice' as type, 
        li.invoice_number as number, 
        li.customer_name as name, 
        li.grand_total as amount, 
        li.created_at as activity_date 
    FROM labour_invoices li 
    WHERE li.invoice_number IS NOT NULL

    UNION ALL 

    SELECT 
        'Purchase' as type, 
        p.bill_no as number, 
        v.name as name, 
        (p.total_amount + p.total_cgst + p.total_sgst) as amount, 
        p.date as activity_date 
    FROM purchases p 
    JOIN vendors v ON p.vendor_id = v.vendor_id

    ORDER BY activity_date DESC 
    LIMIT 10
");

// Low Stock Products Table Data
$stmt = $pdo->prepare("SELECT id as product_id, product_name, quantity FROM products WHERE quantity <= 5 ORDER BY quantity ASC LIMIT 5");
$stmt->execute();
$lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>SDS Automotive - Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <!-- Core CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css">
  <!-- AdminLTE Template CSS -->
  <link rel="stylesheet" href="./css/adminlte.css" />
  <!-- FontAwesome for icons (CRITICAL: This was missing for your icons to show) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    /* Custom styles for dashboard components for a polished look */
    .dashboard-card {
        background-color: #fff;
        border-radius: 0.75rem;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        text-align: center;
        border-left: 5px solid; /* Uses text color for border */
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }
    .dashboard-card .card-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
    .dashboard-card h3 { font-size: 2rem; font-weight: 700; margin: 0; }
    .dashboard-card p { font-size: 1rem; color: #6c757d; margin-bottom: 0.25rem; }
    .dashboard-card small { font-size: 0.9rem; font-weight: 600; }
    .dashboard-card.text-primary { border-color: var(--bs-primary); }
    .dashboard-card.text-secondary { border-color: var(--bs-secondary); }
    .dashboard-card.text-info { border-color: var(--bs-info); }
    .dashboard-card.text-success { border-color: var(--bs-success); }
    .dashboard-card.text-warning { border-color: var(--bs-warning); }
    .dashboard-card.text-danger { border-color: var(--bs-danger); }

    .chart-container, .table-container {
        background-color: #fff;
        border-radius: 0.75rem;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        height: 100%;
    }
  </style>
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
  <div class="app-wrapper">
    <?php include 'header.php'; ?>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="app-main">
        <div class="app-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
                        <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! Here's your overview.</p>
                    </div>
                </div>

                <!-- Low Stock Alert -->
                <?php if ($stats['low_stock_count'] > 0): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-warning">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><i class="fas fa-exclamation-triangle me-2"></i><strong>Stock Alert:</strong> <?php echo $stats['low_stock_count']; ?> product(s) are running low!</div>
                                <a href="product/product-list.php" class="btn btn-sm btn-outline-dark">View Products</a>
                            </div>
                            <div class="mt-2">
                                <?php foreach (array_slice($lowStockProducts, 0, 5) as $product): ?>
                                <span class="badge bg-danger me-2 mb-1"><?php echo htmlspecialchars($product['product_name']); ?> (<?php echo $product['quantity']; ?> left)</span>
                                <?php endforeach; ?>
                                <?php if ($stats['low_stock_count'] > 5): ?>
                                <span class="text-muted">+<?php echo ($stats['low_stock_count'] - 5); ?> more...</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Six Statistic Cards -->
                <div class="row mb-4">
                    <!-- IMPROVED: Better responsive classes for smoother scaling -->
                    <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6 mb-4"><div class="dashboard-card text-primary"><div class="card-icon"><i class="fas fa-file-invoice-dollar"></i></div><h3><?php echo $stats['invoices']['count']; ?></h3><p>Total Invoices</p><small>₹<?php echo number_format($stats['invoices']['total'], 2); ?></small></div></div>
                    <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6 mb-4"><div class="dashboard-card text-secondary"><div class="card-icon"><i class="fas fa-hard-hat"></i></div><h3><?php echo $stats['labour_invoices']['count']; ?></h3><p>Labour Invoices</p><small>₹<?php echo number_format($stats['labour_invoices']['total'], 2); ?></small></div></div>
                    <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6 mb-4"><div class="dashboard-card text-info"><div class="card-icon"><i class="fas fa-shopping-cart"></i></div><h3><?php echo $stats['purchases']['count']; ?></h3><p>Purchase Invoices</p><small>₹<?php echo number_format($stats['purchases']['total'], 2); ?></small></div></div>
                    <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6 mb-4"><div class="dashboard-card text-success"><div class="card-icon"><i class="fas fa-users"></i></div><h3><?php echo $stats['customers_count']; ?></h3><p>Total Customers</p><small>&nbsp;</small></div></div>
                    <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6 mb-4"><div class="dashboard-card text-warning"><div class="card-icon"><i class="fas fa-receipt"></i></div><h3><?php echo $stats['receipts']['count']; ?></h3><p>Total Receipts</p><small>₹<?php echo number_format($stats['receipts']['total'], 2); ?></small></div></div>
                    <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6 mb-4"><div class="dashboard-card text-danger"><div class="card-icon"><i class="fas fa-box-open"></i></div><h3><?php echo $stats['low_stock_count']; ?></h3><p>Low Stock Items</p><small>&nbsp;</small></div></div>
                </div>

                <!-- Chart and Quick Links -->
                <div class="row mb-4">
                    <div class="col-lg-8 mb-4">
                        <div class="chart-container" style="height: 370px;">
                            <h5><i class="fas fa-chart-line"></i> Monthly Sales Revenue (Current Year)</h5>
                            <canvas id="monthlySalesChart"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4">
                        <div class="chart-container">
                            <h5><i class="fas fa-link"></i> Quick Links</h5>
                            <div class="d-grid gap-3">
                                <a href="step-1.php" class="btn btn-success btn-lg"><i class="fas fa-file-invoice fa-fw me-2"></i> Create Invoice</a>
                                <a href="labour/labour-invoice-create.php" class="btn btn-secondary btn-lg"><i class="fas fa-hard-hat fa-fw me-2"></i> Create Labour</a>
                                <a href="purchase/purchase-entry.php" class="btn btn-info btn-lg"><i class="fas fa-shopping-cart fa-fw me-2"></i> Create Purchase</a>
                                <a href="receipt/receipts.php" class="btn btn-warning btn-lg"><i class="fas fa-receipt fa-fw me-2"></i> Create Receipt</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities & Low Stock Tables -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="table-container">
                            <h5><i class="fas fa-clock"></i> Recent Activities</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead><tr><th>Type</th><th>Number</th><th>Customer/Vendor</th><th>Amount</th><th>Date</th></tr></thead>
                                    <tbody>
                                    <?php if (!empty($recentActivities)): foreach ($recentActivities as $activity): ?>
                                    <tr>
                                        <td><span class="badge bg-<?php $type = strtolower($activity['type']); if (strpos($type, 'invoice') !== false) echo 'primary'; elseif (strpos($type, 'labour') !== false) echo 'secondary'; elseif (strpos($type, 'purchase') !== false) echo 'info'; else echo 'dark'; ?>"><?php echo htmlspecialchars($activity['type']); ?></span></td>
                                        <td><?php echo htmlspecialchars($activity['number']); ?></td><td><?php echo htmlspecialchars($activity['name']); ?></td>
                                        <td>₹<?php echo number_format($activity['amount'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($activity['activity_date'])); ?></td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr><td colspan="5" class="text-center">No recent activities found.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4">
                        <div class="table-container">
                            <h5><i class="fas fa-exclamation-triangle text-danger"></i> Critical Stock</h5>
                            <?php if ($lowStockProducts): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead><tr><th>Product</th><th>Stock</th><th>Action</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($lowStockProducts as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td><span class="badge bg-<?php echo $product['quantity'] <= 2 ? 'danger' : 'warning'; ?>"><?php echo $product['quantity']; ?></span></td>
                                        <td><a href="product/product-list.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i> Restock</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-success mt-3"><i class="fas fa-check-circle"></i> All products are well stocked!</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <!-- End Main Content -->

    <?php include 'footer.php'; ?>
  </div>

  <!-- Core JS -->
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/browser/overlayscrollbars.browser.es6.min.js"></script>
  <!-- AdminLTE Template JS -->
  <script src="./js/adminlte.js"></script>
  <!-- Chart.js for the graph -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
  <script>
    // Chart.js Initialization Script
    document.addEventListener('DOMContentLoaded', function() {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const salesData = Array(12).fill(0);

        <?php foreach($monthlySales as $m): ?>
        salesData[<?php echo $m['month'] - 1; ?>] = <?php echo floatval($m['total']); ?>;
        <?php endforeach; ?>

        const salesChartCtx = document.getElementById('monthlySalesChart');
        if (salesChartCtx) {
            new Chart(salesChartCtx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Sales', data: salesData, borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)', fill: true, tension: 0.3
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return '₹' + value.toLocaleString(); }}}}
                }
            });
        }
    });
  </script>
</body>
</html>