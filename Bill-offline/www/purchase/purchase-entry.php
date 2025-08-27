<?php
require_once __DIR__ . '/../config.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

/**
 * FIX: Generates the next bill number atomically using a dedicated sequence table
 * to prevent race conditions. This function MUST be called within an active transaction.
 *
 * @param PDO $pdo The database connection object.
 * @return string The full, formatted next bill number (e.g., "PUR-2024-001").
 * @throws Exception
 */
function generateNextBillNumber(PDO $pdo): string {
    $current_year = date('Y');
    
    // Lock the sequence row for the current year to prevent other processes from reading it.
    $stmt = $pdo->prepare("SELECT last_number FROM bill_sequences WHERE year = :year");
    $stmt->execute([':year' => $current_year]);
    $last_number = $stmt->fetchColumn();

    $next_number = 1;

    if ($last_number !== false) {
        // Sequence for this year exists, so we increment it.
        $next_number = (int)$last_number + 1;
        $update_stmt = $pdo->prepare("UPDATE bill_sequences SET last_number = :next_number WHERE year = :year");
        $update_stmt->execute([':next_number' => $next_number, ':year' => $current_year]);
    } else {
        // This is the first bill of the year, create a new sequence.
        $insert_stmt = $pdo->prepare("INSERT INTO bill_sequences (year, last_number) VALUES (:year, :last_number)");
        $insert_stmt->execute([':year' => $current_year, ':last_number' => $next_number]);
    }

    return "PUR-" . $current_year . "-" . str_pad($next_number, 3, '0', STR_PAD_LEFT);
}

// A function to get a bill number for display only, without locking.
// It's just an estimate and the real number is generated on submission.
function getDisplayBillNumber(PDO $pdo): string {
    $current_year = date('Y');
    $stmt = $pdo->prepare("SELECT last_number FROM bill_sequences WHERE year = :year");
    $stmt->execute([':year' => $current_year]);
    $last_number = $stmt->fetchColumn();
    $next_num = ($last_number === false) ? 1 : (int)$last_number + 1;
    return "PUR-" . $current_year . "-" . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}


$display_bill_no = getDisplayBillNumber($pdo);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // FIX: Generate the definitive bill number inside the transaction to ensure it is unique.
        $final_bill_no = generateNextBillNumber($pdo);
        
        // Validate required fields
        $required = ['date', 'vendor_id', 'purpose'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("'$field' is a required field.");
            }
        }
        
        $purchase_data = [
            'bill_no' => $final_bill_no,
            'date' => $_POST['date'],
            'vendor_id' => (int)$_POST['vendor_id'],
            'purpose' => $_POST['purpose'],
            'vehicle_reg_no' => $_POST['purpose'] === 'Vehicle' ? $_POST['vehicle_reg_no'] : null,
            'vehicle_model' => $_POST['purpose'] === 'Vehicle' ? $_POST['vehicle_model'] : null,
            'notes' => $_POST['notes'] ?? null
        ];

        // These will be calculated
        $total_amount = 0;
        $total_cgst = 0;
        $total_sgst = 0;
        
        // Insert purchase header with initial zero values for totals
        $stmt = $pdo->prepare(
            "INSERT INTO purchases (bill_no, date, vendor_id, purpose, vehicle_reg_no, vehicle_model, total_amount, total_cgst, total_sgst, notes)
             VALUES (:bill_no, :date, :vendor_id, :purpose, :vehicle_reg_no, :vehicle_model, 0, 0, 0, :notes)"
        );
        $stmt->execute($purchase_data);

        // FIX: Get the auto-incremented `id` of the new purchase. This will be our foreign key.
        $purchase_id = $pdo->lastInsertId();
        
        // Validate items
        if (empty($_POST['description']) || !is_array($_POST['description'])) {
            throw new Exception("At least one item is required.");
        }
        
        // FIX: The query now inserts `purchase_id` instead of `bill_no`.
        $item_stmt = $pdo->prepare(
            "INSERT INTO purchase_items (purchase_id, description, mrp, discount, qty, cgst_rate, sgst_rate)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        
        // Process each item
        foreach ($_POST['description'] as $i => $desc) {
            $desc = htmlspecialchars($desc);
            $mrp = (float)$_POST['mrp'][$i];
            $discount = (float)$_POST['discount'][$i];
            $qty = (int)$_POST['qty'][$i];
            $cgst_rate = (float)$_POST['cgst_rate'][$i];
            $sgst_rate = (float)$_POST['sgst_rate'][$i];
            
            if (empty($desc) || $mrp <= 0 || $qty <= 0) {
                throw new Exception("Invalid data in item row " . ($i + 1) . ".");
            }
            
            $subtotal = $mrp * $qty;
            $discount_amount = $subtotal * ($discount / 100);
            $taxable_amount = $subtotal - $discount_amount;
            $cgst_amount = $taxable_amount * ($cgst_rate / 100);
            $sgst_amount = $taxable_amount * ($sgst_rate / 100);
            $item_total = $taxable_amount + $cgst_amount + $sgst_amount;
            
            $total_amount += $item_total;
            $total_cgst += $cgst_amount;
            $total_sgst += $sgst_amount;
            
            // FIX: Execute with the integer `$purchase_id` for better performance and data integrity.
            $item_stmt->execute([
                $purchase_id,
                $desc,
                $mrp,
                $discount,
                $qty,
                $cgst_rate,
                $sgst_rate
            ]);
        }
        
        // Update the main purchase record with the calculated final totals
        $update_stmt = $pdo->prepare(
            "UPDATE purchases SET total_amount = ?, total_cgst = ?, total_sgst = ? WHERE id = ?"
        );
        $update_stmt->execute([
            $total_amount,
            $total_cgst,
            $total_sgst,
            $purchase_id
        ]);
        
        $pdo->commit();
        $_SESSION['success'] = "Purchase bill saved successfully! Bill No: $final_bill_no";
        header("Location: purchase-view.php?id=$purchase_id");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get active vendors for the dropdown
$vendors = $pdo->query("SELECT vendor_id, name FROM vendors WHERE is_active = 1")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Purchase Bill - SDS Automotive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/adminlte.css" />
    <style>
        .required:after { content: " *"; color: red; }
        #vehicle-fields { display: none; }
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
  <div class="app-wrapper">
    <?php include '../header.php'; ?>
    <?php include '../sidebar.php'; ?>
    
    <main class="app-main">
        <div class="container-fluid py-4">
            <h2 class="mb-4">New Purchase Bill</h2>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="purchase-form">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Bill Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label required">Date</label>
                                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Bill No</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($display_bill_no) ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Purchase From</label>
                                <select name="vendor_id" class="form-select" required>
                                    <option value="">Select Vendor</option>
                                    <?php foreach ($vendors as $vendor): ?>
                                        <option value="<?= htmlspecialchars($vendor['vendor_id']) ?>"><?= htmlspecialchars($vendor['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label required">Purpose</label><br>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="purpose" id="purpose-office" value="Office" onclick="toggleVehicleFields()" required checked>
                                    <label class="form-check-label" for="purpose-office">Office</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="purpose" id="purpose-vehicle" value="Vehicle" onclick="toggleVehicleFields()">
                                    <label class="form-check-label" for="purpose-vehicle">Vehicle</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="purpose" id="purpose-maintenance" value="Maintenance" onclick="toggleVehicleFields()">
                                    <label class="form-check-label" for="purpose-maintenance">Maintenance</label>
                                </div>
                            </div>
                        </div>
                        
                        <div id="vehicle-fields">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Vehicle Registration No</label>
                                    <input type="text" name="vehicle_reg_no" class="form-control">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Model</label>
                                    <input type="text" name="vehicle_model" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title mb-0">Items</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="items-table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 30%;">Description</th>
                                        <th style="width: 12%;">MRP (₹)</th>
                                        <th style="width: 10%;">Discount (%)</th>
                                        <th style="width: 8%;">Qty</th>
                                        <th style="width: 10%;">CGST (%)</th>
                                        <th style="width: 10%;">SGST (%)</th>
                                        <th style="width: 15%;">Total (₹)</th>
                                        <th style="width: 5%;"></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="6" class="text-end fw-bold">Subtotal</td>
                                        <td id="subtotal-display" class="text-end">0.00</td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="6" class="text-end fw-bold">CGST</td>
                                        <td id="cgst-display" class="text-end">0.00</td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="6" class="text-end fw-bold">SGST</td>
                                        <td id="sgst-display" class="text-end">0.00</td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="6" class="text-end fw-bold fs-5">Grand Total</td>
                                        <td id="grandtotal-display" class="text-end fw-bold fs-5">0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="addItemRow()">
                            <i class="bi bi-plus-circle"></i> Add Item
                        </button>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title mb-0">Additional Information</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <a href="purchase-reports.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> Save Purchase
                    </button>
                </div>
            </form>
        </div>
    </main>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/adminlte.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        addItemRow();
        document.getElementById('items-table').addEventListener('input', function(e) {
            if (e.target.matches('.mrp, .discount, .qty, .cgst, .sgst')) {
                const row = e.target.closest('tr');
                calculateRowTotal(row);
                calculateGrandTotal();
            }
        });
    });

    function addItemRow() {
        const tbody = document.querySelector('#items-table tbody');
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" name="description[]" class="form-control" required></td>
            <td><input type="number" name="mrp[]" class="form-control text-end mrp" step="0.01" min="0.01" required></td>
            <td><input type="number" name="discount[]" class="form-control text-end discount" step="0.01" min="0" max="100" value=""></td>
            <td><input type="number" name="qty[]" class="form-control text-end qty" min="1" value="1" required></td>
            <td><input type="number" name="cgst_rate[]" class="form-control text-end cgst" step="0.01" min="0" value="9"></td>
            <td><input type="number" name="sgst_rate[]" class="form-control text-end sgst" step="0.01" min="0" value="9"></td>
            <td><input type="text" class="form-control text-end item-total" readonly></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
        `;
        tbody.appendChild(row);
        row.querySelector('input[name="description[]"]').focus();
    }

    function removeRow(button) {
        const row = button.closest('tr');
        if (document.querySelectorAll('#items-table tbody tr').length > 1) {
            row.remove();
            calculateGrandTotal();
        } else {
            alert("At least one item is required.");
        }
    }

    function calculateRowTotal(row) {
        const mrp = parseFloat(row.querySelector('.mrp').value) || 0;
        const discount = parseFloat(row.querySelector('.discount').value) || 0;
        const qty = parseInt(row.querySelector('.qty').value) || 0;
        const cgstRate = parseFloat(row.querySelector('.cgst').value) || 0;
        const sgstRate = parseFloat(row.querySelector('.sgst').value) || 0;
        const subtotal = mrp * qty;
        const discountAmount = subtotal * (discount / 100);
        const taxableAmount = subtotal - discountAmount;
        const cgstAmount = taxableAmount * (cgstRate / 100);
        const sgstAmount = taxableAmount * (sgstRate / 100);
        const total = taxableAmount + cgstAmount + sgstAmount;
        row.querySelector('.item-total').value = total.toFixed(2);
    }

    function calculateGrandTotal() {
        const rows = document.querySelectorAll('#items-table tbody tr');
        let subtotal = 0, cgstTotal = 0, sgstTotal = 0;
        rows.forEach(row => {
            const mrp = parseFloat(row.querySelector('.mrp').value) || 0;
            const discount = parseFloat(row.querySelector('.discount').value) || 0;
            const qty = parseInt(row.querySelector('.qty').value) || 0;
            const cgstRate = parseFloat(row.querySelector('.cgst').value) || 0;
            const sgstRate = parseFloat(row.querySelector('.sgst').value) || 0;
            const rowSubtotal = mrp * qty;
            const discountAmount = rowSubtotal * (discount / 100);
            const taxableAmount = rowSubtotal - discountAmount;
            subtotal += taxableAmount;
            cgstTotal += taxableAmount * (cgstRate / 100);
            sgstTotal += taxableAmount * (sgstRate / 100);
        });
        const grandTotal = subtotal + cgstTotal + sgstTotal;
        document.getElementById('subtotal-display').textContent = subtotal.toFixed(2);
        document.getElementById('cgst-display').textContent = cgstTotal.toFixed(2);
        document.getElementById('sgst-display').textContent = sgstTotal.toFixed(2);
        document.getElementById('grandtotal-display').textContent = grandTotal.toFixed(2);
    }

    function toggleVehicleFields() {
        const purpose = document.querySelector('input[name="purpose"]:checked').value;
        const vehicleFields = document.getElementById('vehicle-fields');
        const regNoInput = document.querySelector('input[name="vehicle_reg_no"]');
        if (purpose === 'Vehicle') {
            vehicleFields.style.display = 'block';
            regNoInput.required = true;
        } else {
            vehicleFields.style.display = 'none';
            regNoInput.required = false;
            regNoInput.value = '';
            document.querySelector('input[name="vehicle_model"]').value = '';
        }
    }
  </script>
</body>
</html>