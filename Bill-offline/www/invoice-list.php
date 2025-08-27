<?php
require_once 'config.php'; // Make sure this file contains your PDO connection ($pdo)
session_start();

// Check for user session
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Handle AJAX requests for the invoice list
if(isset($_GET['action']) && $_GET['action'] == 'list'){
    header('Content-Type: text/html'); // Ensure browser interprets the response as HTML

    // --- Filter and Pagination Logic ---
    $limit = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $start = ($page - 1) * $limit;
    
    // Get filter parameters from the AJAX request
    $customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
    $phone = $_GET['phone'] ?? '';
    $invoice_type = $_GET['invoice_type'] ?? '';

    $where = [];
    $params = [];

    if($customer_id > 0){
        $where[] = "c.id = ?";
        $params[] = $customer_id;
    }
    if($phone != ''){
        $where[] = "c.customer_contact LIKE ?";
        $params[] = "%$phone%";
    }
    if($invoice_type != ''){
        $where[] = "i.invoice_type = ?";
        $params[] = $invoice_type;
    }

    $where_sql = count($where) ? "WHERE ".implode(" AND ", $where) : "";

    // Get total records for pagination
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices i JOIN customers c ON i.customer_id=c.id $where_sql");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    $pages = ceil($total / $limit);

    // Get invoices for the current page
    $stmt = $pdo->prepare("SELECT i.*, c.customer_name, c.customer_contact 
        FROM invoices i 
        JOIN customers c ON i.customer_id=c.id
        $where_sql
        ORDER BY i.id DESC
        LIMIT $start,$limit");
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Generate HTML Output for AJAX Response ---

    // 1. The Table
    echo '<div class="card">
            <div class="card-header">
                <h3 class="card-title">Invoice List</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 10px">#</th>
                                <th>Invoice Number</th>
                                <th>Customer Name</th>
                                <th>Phone</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th style="width: 150px">Action</th>
                            </tr>
                        </thead>
                        <tbody>';
    if($invoices){
        $sno = $start + 1;
        foreach($invoices as $inv){
            echo "<tr>
                <td>{$sno}</td>
                <td>{$inv['invoice_number']}</td>
                <td>{$inv['customer_name']}</td>
                <td>{$inv['customer_contact']}</td>
                <td>{$inv['invoice_type']}</td>
                <td>".date('d-m-Y', strtotime($inv['invoice_date']))."</td>
                <td>
                    <button class='btn btn-sm btn-warning edit_invoice' data-id='{$inv['id']}' title='Edit'><i class='fa fa-edit'></i></button>
                    <button class='btn btn-sm btn-danger delete_invoice' data-id='{$inv['id']}' title='Delete'><i class='fa fa-trash'></i></button>
                </td>
            </tr>";
            $sno++;
        }
    } else {
        echo "<tr><td colspan='7' class='text-center'>No invoices found matching your criteria.</td></tr>";
    }
    echo '          </tbody>
                </table>
            </div>
        </div>'; // end card-body, card

    // 2. The Pagination
    if($pages > 1){
        echo '<div class="card-footer clearfix">
                <nav><ul class="pagination pagination-sm m-0 float-right">';
        for($i = 1; $i <= $pages; $i++){
            $active = $i == $page ? 'active' : '';
            echo "<li class='page-item $active'><a class='page-link' href='#' data-page='$i'>$i</a></li>";
        }
        echo '</ul></nav></div>';
    }
    echo '</div>'; // end card wrapper
    exit; // Stop script execution after sending AJAX response
}

// Fetch customers for the dropdown filter
$stmt = $pdo->query("SELECT id, customer_name, customer_contact FROM customers ORDER BY customer_name ASC");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Invoice types from ENUM definition
$invoice_types = ['Invoice', 'Tax Invoice', 'Estimate', 'Tax Estimate', 'Supplementary', 'Tax Supplementary'];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice List</title>
    <!-- AdminLTE & Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="./css/adminlte.css"> <!-- Make sure this path is correct -->
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <!-- Select2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <!-- Fonts and other CSS from original code -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" media="print" onload="this.media='all'" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
<div class="app-wrapper">
    <?php include 'header.php'; ?>
    <?php include 'sidebar.php'; ?>

    <main class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Invoices</h1>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <!-- Filter Card -->
                <div class="card card-primary card-outline mb-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-filter"></i> Filter Options</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="filter_customer">Customer Name</label>
                                    <select id="filter_customer" class="form-select">
                                        <option value="0" data-contact="">All Customers</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?= htmlspecialchars($customer['id']) ?>" data-contact="<?= htmlspecialchars($customer['customer_contact']) ?>">
                                                <?= htmlspecialchars($customer['customer_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                 <div class="form-group">
                                    <label for="filter_phone">Phone Number</label>
                                    <input type="text" id="filter_phone" class="form-control" placeholder="Filter by Phone">
                                 </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter_invoice_type">Invoice Type</label>
                                    <select id="filter_invoice_type" class="form-select">
                                        <option value="">All Types</option>
                                        <?php foreach ($invoice_types as $type): ?>
                                            <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-group w-100">
                                    <button class="btn btn-primary w-100 me-2" id="filter_btn">Filter</button>
                                    <button class="btn btn-secondary w-100 mt-2" id="reset_btn">Reset</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Invoice Table Container -->
                <div id="invoice_table_container">
                    <!-- AJAX content will be loaded here -->
                </div>
            </div>
        </div>
    </main>
</div>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- AdminLTE App -->
<script src="./js/adminlte.js"></script> <!-- Make sure this path is correct -->

<script>
$(document).ready(function(){
    // Initialize Select2 for the customer dropdown
    $('#filter_customer').select2({
        theme: 'bootstrap-5'
    });

    const customerIdFromUrl = new URLSearchParams(window.location.search).get('customer_id') || 0;

    // --- Main function to load invoices via AJAX ---
    function loadInvoices(page = 1){
        // Show a loading indicator (optional but good for UX)
        $('#invoice_table_container').html('<div class="d-flex justify-content-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        
        $.get('', {
            action: 'list', 
            page: page, 
            customer_id: $('#filter_customer').val(), 
            phone: $('#filter_phone').val(),
            invoice_type: $('#filter_invoice_type').val()
        }, function(data){
            $('#invoice_table_container').html(data);
        }).fail(function() {
            $('#invoice_table_container').html('<div class="alert alert-danger">Failed to load invoices. Please try again.</div>');
        });
    }

    // --- Event Handlers ---

    // Initial load
    if (customerIdFromUrl > 0) {
        $('#filter_customer').val(customerIdFromUrl).trigger('change');
    }
    loadInvoices();

    // Filter button click
    $('#filter_btn').click(function(){ 
        loadInvoices(1); // Reset to page 1 when filtering
    });

    // Reset button click
    $('#reset_btn').click(function() {
        $('#filter_customer').val('0').trigger('change'); // Resets select2 and triggers change event
        $('#filter_phone').val('');
        $('#filter_invoice_type').val('');
        loadInvoices(1);
    });

    // Update phone number when customer selection changes
    $('#filter_customer').on('change', function() {
        const selectedContact = $(this).find('option:selected').data('contact');
        $('#filter_phone').val(selectedContact || '');
    });
    
    // Pagination link click (delegated event)
    $(document).on('click', '.page-link', function(e){
        e.preventDefault();
        let page = $(this).data('page');
        loadInvoices(page);
    });

    // Edit button click (delegated event)
    $(document).on('click', '.edit_invoice', function(){
        let id = $(this).data('id');
        window.location.href = 'edit-step-1.php?id=' + id; // Or your specific edit page
    });

    // Delete button click (delegated event)
    $(document).on('click', '.delete_invoice', function(){
        if(confirm('Are you sure you want to delete this invoice? This action cannot be undone.')){
            let id = $(this).data('id');
            $.post('delete-invoice.php', {id: id}, function(resp){
                if(resp.success){
                    alert(resp.message || 'Invoice deleted successfully!');
                    loadInvoices(); // Refresh the list
                } else {
                    alert(resp.message || 'An error occurred.');
                }
            }, 'json').fail(function(){
                alert('Could not connect to the server to delete the invoice.');
            });
        }
    });
});
</script>
</body>
</html>