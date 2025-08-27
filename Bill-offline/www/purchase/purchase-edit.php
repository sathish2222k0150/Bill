<?php
require_once __DIR__ . '/../config.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// 1. GET THE PURCHASE ID AND FETCH DATA
// ----------------------------------------------------
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid Purchase ID.";
    header('Location: purchase-reports.php');
    exit;
}
$purchase_id = (int)$_GET['id'];

try {
    // Fetch the main purchase record
    $stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = ?");
    $stmt->execute([$purchase_id]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$purchase) {
        $_SESSION['error'] = "Purchase bill not found.";
        header('Location: purchase-reports.php');
        exit;
    }

    // Fetch the associated items for the purchase
    $items_stmt = $pdo->prepare("SELECT * FROM purchase_items WHERE purchase_id = ? ORDER BY id ASC");
    $items_stmt->execute([$purchase_id]);
    $purchase_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching purchase data: " . $e->getMessage();
    header('Location: purchase-reports.php');
    exit;
}


// 2. PROCESS FORM SUBMISSION FOR UPDATING
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure the posted ID matches the one from the URL
    if (empty($_POST['purchase_id']) || (int)$_POST['purchase_id'] !== $purchase_id) {
         $_SESSION['error'] = "Form submission mismatch. Please try again.";
         header("Location: purchase-edit.php?id=$purchase_id");
         exit;
    }

    try {
        $pdo->beginTransaction();
        
        // Validate required fields
        $required = ['date', 'vendor_id', 'purpose'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("'$field' is a required field.");
            }
        }
        
        // These will be recalculated from the submitted items
        $total_amount = 0;
        $total_cgst = 0;
        $total_sgst = 0;
        
        // Validate items and calculate totals
        if (empty($_POST['description']) || !is_array($_POST['description'])) {
            throw new Exception("At least one item is required.");
        }

        // A. DELETE existing items for this purchase.
        // This is the simplest and most robust way to handle edits, additions, and deletions.
        $delete_stmt = $pdo->prepare("DELETE FROM purchase_items WHERE purchase_id = ?");
        $delete_stmt->execute([$purchase_id]);

        // B. RE-INSERT all submitted items as if they were new.
        $item_stmt = $pdo->prepare(
            "INSERT INTO purchase_items (purchase_id, description, mrp, discount, qty, cgst_rate, sgst_rate)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

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
            
            // Recalculate totals for accuracy
            $subtotal = $mrp * $qty;
            $discount_amount = $subtotal * ($discount / 100);
            $taxable_amount = $subtotal - $discount_amount;
            $cgst_amount = $taxable_amount * ($cgst_rate / 100);
            $sgst_amount = $taxable_amount * ($sgst_rate / 100);
            $item_total = $taxable_amount + $cgst_amount + $sgst_amount;
            
            $total_amount += $item_total;
            $total_cgst += $cgst_amount;
            $total_sgst += $sgst_amount;
            
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
        
        // C. UPDATE the main purchase record with the new data and calculated totals.
        $update_stmt = $pdo->prepare(
            "UPDATE purchases SET 
                date = :date, 
                vendor_id = :vendor_id, 
                purpose = :purpose, 
                vehicle_reg_no = :vehicle_reg_no, 
                vehicle_model = :vehicle_model, 
                total_amount = :total_amount, 
                total_cgst = :total_cgst, 
                total_sgst = :total_sgst, 
                notes = :notes
             WHERE id = :purchase_id"
        );
        
        $update_stmt->execute([
            ':date' => $_POST['date'],
            ':vendor_id' => (int)$_POST['vendor_id'],
            ':purpose' => $_POST['purpose'],
            ':vehicle_reg_no' => $_POST['purpose'] === 'Vehicle' ? $_POST['vehicle_reg_no'] : null,
            ':vehicle_model' => $_POST['purpose'] === 'Vehicle' ? $_POST['vehicle_model'] : null,
            ':total_amount' => $total_amount,
            ':total_cgst' => $total_cgst,
            ':total_sgst' => $total_sgst,
            ':notes' => $_POST['notes'] ?? null,
            ':purchase_id' => $purchase_id
        ]);
        
        $pdo->commit();
        $_SESSION['success'] = "Purchase bill '" . htmlspecialchars($purchase['bill_no']) . "' updated successfully!";
        header("Location: purchase-view.php?id=$purchase_id");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        // Reload the page to show the error, but don't lose user's changes
        $_SESSION['error'] = "Error updating bill: " . $e->getMessage();
        // Repopulate from $_POST if an error occurs so user doesn't lose their work
        $purchase = $_POST;
        $purchase['bill_no'] = $purchase['bill_no']; // Keep original bill no
        $purchase_items = [];
        if(isset($_POST['description'])) {
            foreach($_POST['description'] as $i => $desc) {
                $purchase_items[] = [
                    'description' => $desc,
                    'mrp' => $_POST['mrp'][$i],
                    'discount' => $_POST['discount'][$i],
                    'qty' => $_POST['qty'][$i],
                    'cgst_rate' => $_POST['cgst_rate'][$i],
                    'sgst_rate' => $_POST['sgst_rate'][$i],
                ];
            }
        }
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
    <title>Edit Purchase Bill - <?= htmlspecialchars($purchase['bill_no']) ?> - SDS Automotive</title>
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
            <h2 class="mb-4">Edit Purchase Bill</h2>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="purchase-edit.php?id=<?= $purchase_id ?>" id="purchase-form">
                <!-- Hidden input to send the ID on submit -->
                <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Bill Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label required">Date</label>
                                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($purchase['date']) ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Bill No</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($purchase['bill_no']) ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Purchase From</label>
                                <select name="vendor_id" class="form-select" required>
                                    <option value="">Select Vendor</option>
                                    <?php foreach ($vendors as $vendor): ?>
                                        <option value="<?= htmlspecialchars($vendor['vendor_id']) ?>" <?= ($vendor['vendor_id'] == $purchase['vendor_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($vendor['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label required">Purpose</label><br>
                                <?php $purposes = ['Office', 'Vehicle', 'Maintenance']; ?>
                                <?php foreach($purposes as $p): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="purpose" id="purpose-<?= strtolower($p) ?>" value="<?= $p ?>" onclick="toggleVehicleFields()" <?= ($purchase['purpose'] === $p) ? 'checked' : '' ?> required>
                                    <label class="form-check-label" for="purpose-<?= strtolower($p) ?>"><?= $p ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div id="vehicle-fields">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Vehicle Registration No</label>
                                    <input type="text" name="vehicle_reg_no" class="form-control" value="<?= htmlspecialchars($purchase['vehicle_reg_no'] ?? '') ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Model</label>
                                    <input type="text" name="vehicle_model" class="form-control" value="<?= htmlspecialchars($purchase['vehicle_model'] ?? '') ?>">
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
                                <tbody>
                                    <?php foreach ($purchase_items as $item): ?>
                                    <tr>
                                        <td><input type="text" name="description[]" class="form-control" value="<?= htmlspecialchars($item['description']) ?>" required></td>
                                        <td><input type="number" name="mrp[]" class="form-control text-end mrp" step="0.01" min="0.01" value="<?= htmlspecialchars($item['mrp']) ?>" required></td>
                                        <td><input type="number" name="discount[]" class="form-control text-end discount" step="0.01" min="0" max="100" value="<?= htmlspecialchars($item['discount']) ?>"></td>
                                        <td><input type="number" name="qty[]" class="form-control text-end qty" min="1" value="<?= htmlspecialchars($item['qty']) ?>" required></td>
                                        <td><input type="number" name="cgst_rate[]" class="form-control text-end cgst" step="0.01" min="0" value="<?= htmlspecialchars($item['cgst_rate']) ?>"></td>
                                        <td><input type="number" name="sgst_rate[]" class="form-control text-end sgst" step="0.01" min="0" value="<?= htmlspecialchars($item['sgst_rate']) ?>"></td>
                                        <td><input type="text" class="form-control text-end item-total" readonly></td>
                                        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
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
                            <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($purchase['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <a href="purchase-reports.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> Update Purchase
                    </button>
                </div>
            </form>
        </div>
    </main>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/adminlte.js"></script>
  <script>
    // The JavaScript is identical to the "new purchase" page.
    // It's just being re-used here.

    document.addEventListener('DOMContentLoaded', function() {
        // Initial setup on page load
        toggleVehicleFields();
        document.querySelectorAll('#items-table tbody tr').forEach(row => calculateRowTotal(row));
        calculateGrandTotal();

        // Add event listener for dynamic calculations
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
        const purposeRadio = document.querySelector('input[name="purpose"]:checked');
        if (!purposeRadio) return; // Exit if no radio is checked
        
        const purpose = purposeRadio.value;
        const vehicleFields = document.getElementById('vehicle-fields');
        const regNoInput = document.querySelector('input[name="vehicle_reg_no"]');
        if (purpose === 'Vehicle') {
            vehicleFields.style.display = 'block';
            regNoInput.required = true;
        } else {
            vehicleFields.style.display = 'none';
            regNoInput.required = false;
        }
    }
  </script>
</body>
</html>