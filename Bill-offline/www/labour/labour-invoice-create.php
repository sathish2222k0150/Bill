<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Generate invoice number
        $lastIdStmt = $pdo->query("SELECT id FROM labour_invoices ORDER BY id DESC LIMIT 1");
        $lastId = $lastIdStmt->fetchColumn();
        $invoice_number = 'LAB-' . str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);
        
        // Insert main invoice
        $stmt = $pdo->prepare("
            INSERT INTO labour_invoices 
            (invoice_number, customer_name, customer_contact, vehicle_number, vehicle_model, 
             total_hours, rate_per_hour, subtotal, gst_percent, gst_amount, grand_total, invoice_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $subtotal = $_POST['total_hours'] * $_POST['rate_per_hour'];
        $gst_amount = $subtotal * ($_POST['gst_percent'] / 100);
        $grand_total = $subtotal + $gst_amount;
        
        $stmt->execute([
            $invoice_number,
            $_POST['customer_name'],
            $_POST['customer_contact'],
            $_POST['vehicle_number'],
            $_POST['vehicle_model'],
            $_POST['total_hours'],
            $_POST['rate_per_hour'],
            $subtotal,
            $_POST['gst_percent'],
            $gst_amount,
            $grand_total,
            $_POST['invoice_date']
        ]);
        
        $invoice_id = $pdo->lastInsertId();
        
        // Insert items
        if (!empty($_POST['description'])) {
            $item_stmt = $pdo->prepare("
                INSERT INTO labour_invoice_items 
                (invoice_id, description, hours, rate, amount)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['description'] as $index => $description) {
                if (!empty($description)) {
                    $hours = $_POST['hours'][$index];
                    $rate = $_POST['rate'][$index];
                    $amount = $hours * $rate;
                    
                    $item_stmt->execute([
                        $invoice_id,
                        $description,
                        $hours,
                        $rate,
                        $amount
                    ]);
                }
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Labour invoice created successfully!";
        header("Location: labour-invoice-view.php?id=$invoice_id");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}
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
                        <h2 class="mb-2">Create Labour Invoice</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="app-content">
            <div class="container-fluid">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <!-- Customer & Vehicle Details Card -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Customer & Vehicle Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Customer Name *</label>
                                    <input type="text" class="form-control" name="customer_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" name="customer_contact">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Vehicle Number</label>
                                    <input type="text" class="form-control" name="vehicle_number">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Vehicle Model</label>
                                    <input type="text" class="form-control" name="vehicle_model">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Invoice Date *</label>
                                    <input type="date" class="form-control" name="invoice_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Rate per Hour (₹) *</label>
                                    <input type="number" class="form-control" name="rate_per_hour" step="0.01" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">GST % *</label>
                                    <input type="number" class="form-control" name="gst_percent" value="18" step="0.01" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Labour Services Card -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Labour Services</h5>
                        </div>
                        <div class="card-body">
                            <div id="labour-items">
                                <div class="row labour-item mb-3 align-items-end">
                                    <div class="col-md-5">
                                        <label class="form-label">Description *</label>
                                        <input type="text" class="form-control" name="description[]" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Hours *</label>
                                        <input type="number" class="form-control hours" name="hours[]" step="0.5" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Rate (₹)</label>
                                        <input type="number" class="form-control rate" name="rate[]" step="0.01" readonly>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger w-100" disabled>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary" id="add-item">
                                <i class="fas fa-plus"></i> Add Service
                            </button>
                        </div>
                    </div>

                    <!-- Invoice Summary Card -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Invoice Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Total Hours</label>
                                        <input type="text" class="form-control" id="total-hours" readonly>
                                        <input type="hidden" name="total_hours" id="total-hours-hidden">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tbody>
                                            <tr>
                                                <td>Subtotal</td>
                                                <td class="text-end">₹<span id="subtotal">0.00</span></td>
                                            </tr>
                                            <tr>
                                                <td>GST (<span id="gst-percent">18</span>%)</td>
                                                <td class="text-end">₹<span id="gst-amount">0.00</span></td>
                                            </tr>
                                            <tr class="table-primary">
                                                <td><strong>Grand Total</strong></td>
                                                <td class="text-end"><strong>₹<span id="grand-total">0.00</span></strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="text-center">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i> Create Invoice
                        </button>
                        <a href="labour-invoice-list.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-list"></i> View All Invoices
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="../js/adminlte.js"></script>
<script>
$(document).ready(function() {
    function updateRates() {
        const rate = parseFloat($('input[name="rate_per_hour"]').val()) || 0;
        $('.rate').val(rate.toFixed(2));
        updateTotals();
    }

    function updateTotals() {
        let totalHours = 0;
        const rate = parseFloat($('input[name="rate_per_hour"]').val()) || 0;
        const gstPercent = parseFloat($('input[name="gst_percent"]').val()) || 0;
        
        $('.hours').each(function() {
            totalHours += parseFloat($(this).val()) || 0;
        });
        
        const subtotal = totalHours * rate;
        const gstAmount = subtotal * (gstPercent / 100);
        const grandTotal = subtotal + gstAmount;
        
        $('#total-hours').val(totalHours.toFixed(2));
        $('#total-hours-hidden').val(totalHours.toFixed(2));
        $('#subtotal').text(subtotal.toFixed(2));
        $('#gst-percent').text(gstPercent.toFixed(2));
        $('#gst-amount').text(gstAmount.toFixed(2));
        $('#grand-total').text(grandTotal.toFixed(2));
    }

    // Add new item
    $('#add-item').click(function() {
        const newItem = `
            <div class="row labour-item mb-3 align-items-end">
                <div class="col-md-5">
                    <input type="text" class="form-control" name="description[]" required>
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control hours" name="hours[]" step="0.5" required>
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control rate" name="rate[]" step="0.01" readonly>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger w-100 remove-item">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        $('#labour-items').append(newItem);
        updateRates();
    });

    // Remove item
    $(document).on('click', '.remove-item', function() {
        $(this).closest('.labour-item').remove();
        updateTotals();
    });

    // Event listeners for inputs
    $('input[name="rate_per_hour"], input[name="gst_percent"]').on('input', updateRates);
    $(document).on('input', '.hours', updateTotals);

    // Initial calculation on page load
    updateRates();
});
</script>
</body>
</html>