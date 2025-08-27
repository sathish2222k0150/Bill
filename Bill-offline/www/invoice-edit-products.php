<?php
session_start();
require_once 'config.php'; // Contains the PDO connection setup

// ACTION: ADD A NEW PRODUCT FROM MODAL (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An error occurred while adding the product.'];

    if (isset($pdo)) {
        try {
            $sql = "INSERT INTO products (brand_name, model, product_name, product_description, product_location, quantity, dealer_price, retail_price, cgst, sgst) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            $brand = (!empty($_POST['brand_select']) && $_POST['brand_select'] !== 'Other') ? $_POST['brand_select'] : (!empty($_POST['brand_manual']) ? $_POST['brand_manual'] : null);
            $model = (!empty($_POST['model_select']) && $_POST['model_select'] !== 'Other') ? $_POST['model_select'] : (!empty($_POST['model_manual']) ? $_POST['model_manual'] : null);

            $stmt->execute([
                $brand,
                $model,
                $_POST['product_name'],
                $_POST['product_description'] ?? null,
                $_POST['product_location'] ?? null,
                !empty($_POST['quantity']) ? intval($_POST['quantity']) : 0,
                !empty($_POST['dealer_price']) ? floatval($_POST['dealer_price']) : 0.00,
                !empty($_POST['retail_price']) ? floatval($_POST['retail_price']) : 0.00,
                !empty($_POST['cgst']) ? floatval($_POST['cgst']) : 0.00,
                !empty($_POST['sgst']) ? floatval($_POST['sgst']) : 0.00
            ]);

            $newProductId = $pdo->lastInsertId();

            if ($newProductId) {
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$newProductId]);
                $newProduct = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($newProduct) {
                    $response['success'] = true;
                    $response['message'] = 'Product added successfully!';
                    $response['product'] = $newProduct;
                } else {
                    $response['message'] = 'Failed to retrieve the newly added product.';
                }
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database Error: ' . $e->getMessage();
        }
    }

    echo json_encode($response);
    exit;
}

// ACTION: UPDATE PRODUCT TAX FROM INVOICE ROW (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_product_tax') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An error occurred while updating tax.'];

    if (isset($pdo) && isset($_POST['product_id'], $_POST['cgst'], $_POST['sgst'])) {
        try {
            $sql = "UPDATE products SET cgst = ?, sgst = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                floatval($_POST['cgst']),
                floatval($_POST['sgst']),
                intval($_POST['product_id'])
            ]);

            if ($stmt->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = 'Product tax updated successfully!';
            } else {
                $response['message'] = 'No changes were made or product not found.';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database Error: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Missing required data.';
    }

    echo json_encode($response);
    exit;
}

// --- Regular Page Load Logic ---

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// --- DATA FETCHING & STATE SETUP ---
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
$customer_id = 0; 
$invoice = null;
$customer = null;
$items_to_repopulate = [];
$display_date = date('Y-m-d');
$display_invoice_type = 'Tax Invoice';
$display_with_tax = 'Yes';

if ($invoice_id > 0 && isset($pdo)) {
    // Check if there is a temporary preview state in the session first.
    if (isset($_SESSION['preview_invoice_data'][$invoice_id])) {
        // --- STATE RESTORATION FROM SESSION ---
        $preview_data = $_SESSION['preview_invoice_data'][$invoice_id];
        $customer_id = $preview_data['customer_id'];
        $display_date = $preview_data['invoice_date'];
        $display_invoice_type = $preview_data['invoice_type'];
        $display_with_tax = $preview_data['with_tax'];
        $items_to_repopulate = $preview_data['items'];
        $stmt = $pdo->prepare("SELECT invoice_number FROM invoices WHERE id = ?");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        unset($_SESSION['preview_invoice_data'][$invoice_id]);

    } else {
        // --- ORIGINAL LOGIC: LOAD FROM DATABASE ---
        $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($invoice) {
            $customer_id = $invoice['customer_id'];
            $display_date = $invoice['invoice_date'];
            $display_invoice_type = $invoice['invoice_type'];
            $display_with_tax = $invoice['with_tax'];
            
            // MODIFIED: Added 'ii.discount' to the SELECT statement
            $sql = "SELECT 
                        ii.product_id, ii.quantity, ii.discount, ii.cgst_percent, ii.sgst_percent, ii.subtotal, 
                        p.product_name, p.product_description 
                    FROM invoice_items ii 
                    JOIN products p ON ii.product_id = p.id 
                    WHERE ii.invoice_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$invoice_id]);
            $db_invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($db_invoice_items as $item) {
                $price_per_unit = ($item['quantity'] > 0) ? ($item['subtotal'] / $item['quantity']) : 0;
                // MODIFIED: Added 'discount' to the repopulation array
                $items_to_repopulate[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'product_description' => $item['product_description'] ?? '',
                    'price' => number_format($price_per_unit, 2, '.', ''),
                    'quantity' => $item['quantity'],
                    'discount' => (float)($item['discount'] ?? 0), // Handle old invoices with NULL
                    'cgst' => $item['cgst_percent'],
                    'sgst' => $item['sgst_percent']
                ];
            }
        } else {
            header('Location: edit-step-1.php?error=notfound');
            exit;
        }
    }
}

$existing_items_json = json_encode($items_to_repopulate);

if (!$customer_id || !$invoice_id || !isset($pdo)) {
    header('Location: edit-step-1.php');
    exit;
}

// Fetch customer details regardless of data source (session or DB)
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header('Location: edit-step-1.php?error=customer_not_found');
    exit;
}

// --- Data Fetching for Page Dropdowns and JS ---
$all_products = [];
$products_by_id_json = '{}';
$all_cgst_rates_json = '[]';
$all_sgst_rates_json = '[]';

if (isset($pdo)) {
    $stmt = $pdo->query("SELECT id, product_name, product_description, quantity, retail_price, cgst, sgst FROM products ORDER BY product_name ASC");
    $all_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $products_by_id = [];
    foreach ($all_products as $p) {
        $products_by_id[$p['id']] = $p;
    }
    $products_by_id_json = json_encode($products_by_id);

    $stmt_cgst = $pdo->query("SELECT DISTINCT cgst FROM products ORDER BY cgst ASC");
    $all_cgst_rates = $stmt_cgst->fetchAll(PDO::FETCH_COLUMN, 0);
    $all_cgst_rates_json = json_encode($all_cgst_rates);

    $stmt_sgst = $pdo->query("SELECT DISTINCT sgst FROM products ORDER BY sgst ASC");
    $all_sgst_rates = $stmt_sgst->fetchAll(PDO::FETCH_COLUMN, 0);
    $all_sgst_rates_json = json_encode($all_sgst_rates);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Invoice #<?= isset($invoice) ? htmlspecialchars($invoice['invoice_number']) : '' ?></title>
  
  <!-- Core CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css">
  <!-- AdminLTE Template CSS -->
  <link rel="stylesheet" href="./css/adminlte.css" />
  <!-- FontAwesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <!-- Select2 CSS for searchable dropdowns -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

  <style>
    /* CSS for initially hidden tax columns and rows */
    .tax-column, .tax-row { display: none; }
    /* Style for the sticky table header */
    .invoice-table-container { 
        max-height: 50vh; /* Adjust height as needed */
        overflow-y: auto; 
        position: relative; 
    }
    .invoice-table-container thead th { 
        position: sticky; 
        top: 0; 
        z-index: 10; 
        background-color: #f8f9fa; /* Match table-light background */
    }
    .totals-table { max-width: 400px; margin-left: auto; }
  </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
  <div class="app-wrapper">
    <?php include 'header.php'; ?>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <main class="app-main">
        <div class="app-content">
            <div class="container-fluid">
                <!-- Page Header for context and navigation -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between my-4">
                            <h4 class="mb-sm-0">Edit Invoice #<?= isset($invoice) ? htmlspecialchars($invoice['invoice_number']) : '...' ?></h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="invoice-list.php">Invoices</a></li>
                                    <li class="breadcrumb-item active">Edit</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Form Container Card -->
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Editing Invoice for <?= htmlspecialchars($customer['customer_name']) ?></h3>
                    </div>
                    <form id="invoice-form" method="POST" action="edit-preview.php">
                        <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                        <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
                        <div class="card-body">
                            <!-- Invoice Details Section (Responsive) -->
                            <div class="row border-bottom pb-3 mb-4">
                                <div class="col-lg-3 col-md-6 col-12 mb-3">
                                    <label for="invoice_date" class="form-label"><strong>Invoice Date</strong></label>
                                    <input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?= htmlspecialchars($display_date) ?>" required>
                                </div>
                                <div class="col-lg-3 col-md-6 col-12 mb-3">
                                    <label for="invoice_type_select" class="form-label"><strong>Invoice Type</strong></label>
                                    <select class="form-select" id="invoice_type_select" name="invoice_type" required>
                                        <?php $invoice_types = ['Invoice','Tax Invoice','Estimate','Tax Estimate','Supplementary','Tax Supplementary'];
                                        foreach ($invoice_types as $type): ?>
                                            <option value="<?= $type ?>" <?= ($display_invoice_type === $type) ? 'selected' : '' ?>><?= $type ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6 col-12 mb-3">
                                    <label for="with_tax_select" class="form-label"><strong>Apply Tax</strong></label>
                                    <select class="form-select" id="with_tax_select" name="with_tax" required>
                                        <option value="Yes" <?= ($display_with_tax === 'Yes') ? 'selected' : '' ?>>Yes</option>
                                        <option value="No" <?= ($display_with_tax === 'No') ? 'selected' : '' ?>>No</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Product Selection Section (Responsive) -->
                            <div class="row align-items-end mb-4">
                                <div class="col-md-6 col-12 mb-3">
                                    <label for="product_select" class="form-label">Add Product from Database</label>
                                    <select id="product_select" class="form-select"><option></option>
                                        <?php foreach ($all_products as $product): ?>
                                            <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['product_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-success" id="add-db-product-btn"><i class="fas fa-plus me-1"></i> Add Selected</button>
                                        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addProductModal"><i class="fas fa-plus-circle me-1"></i> Add New Product</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice Items Table (Responsive Wrapper with Sticky Header) -->
                            <h4 class="mb-3">Invoice Items</h4>
                            <div class="table-responsive invoice-table-container">
                                <table class="table table-bordered table-hover" id="invoice-items-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th>Description</th>
                                            <th style="width: 10%;">Qty</th>
                                            <th style="width: 15%;">Price</th>
                                            <th style="width: 15%;">Discount</th>
                                            <th class="tax-column" style="width: 12%;">CGST (%)</th>
                                            <th class="tax-column" style="width: 12%;">SGST (%)</th>
                                            <th style="width: 15%;" class="text-end">Total</th>
                                            <th style="width: 5%;" class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <!-- Totals and Navigation (Responsive) -->
                            <div class="row mt-4">
                                <div class="col-md-6 d-flex align-items-center mb-3">
                                    <a href="invoice-list.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Invoice List</a>
                                </div>
                                <div class="col-md-6">
                                    <table class="table totals-table">
                                        <tbody>
                                            <tr><td>Subtotal</td><td class="text-end" id="subtotal">0.00</td></tr>
                                            <tr><td>Discount</td><td class="text-end" id="discount-total">0.00</td></tr>
                                            <tr class="tax-row" id="cgst-row"><td>CGST</td><td class="text-end" id="cgst-total">0.00</td></tr>
                                            <tr class="tax-row" id="sgst-row"><td>SGST</td><td class="text-end" id="sgst-total">0.00</td></tr>
                                            <tr class="table-primary h5"><td><strong>Grand Total</strong></td><td class="text-end" id="grand-total"><strong>0.00</strong></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-file-alt me-2"></i> Update & Preview</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <!-- End Main Content -->

    <?php include 'footer.php'; ?>
  </div>

  <!-- Add Product Modal (Already Responsive) -->
  <div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="addProductModalLabel">Add New Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <form id="addProductForm">
                    <input type="hidden" name="action" value="add_product">
                    <div class="row">
                        <div class="mb-3 col-md-6"><label for="brand_select" class="form-label">Brand</label><select class="form-control" id="brand_select" name="brand_select"></select><input type="text" class="form-control mt-2" id="brand_manual" name="brand_manual" placeholder="Enter New Brand Name" style="display:none;"></div>
                        <div class="mb-3 col-md-6"><label for="model_select" class="form-label">Model</label><select class="form-control" id="model_select" name="model_select"><option value="">-- Select Brand First --</option></select><input type="text" class="form-control mt-2" id="model_manual" name="model_manual" placeholder="Enter New Model Name" style="display:none;"></div>
                    </div>
                    <div class="mb-3"><label for="product_name" class="form-label">Product Name*</label><input type="text" class="form-control" id="product_name" name="product_name" required></div>
                    <div class="mb-3"><label for="product_description" class="form-label">Product Description</label><textarea class="form-control" id="product_description" name="product_description" rows="3"></textarea></div>
                    <div class="row">
                        <div class="mb-3 col-md-4"><label for="product_location" class="form-label">Product Location</label><input type="text" class="form-control" id="product_location" name="product_location"></div>
                        <div class="mb-3 col-md-4"><label for="quantity" class="form-label">Quantity</label><input type="number" class="form-control" id="quantity" name="quantity" value="0"></div>
                        <div class="mb-3 col-md-4"><label for="dealer_price" class="form-label">Dealer Price</label><input type="number" step="0.01" class="form-control" id="dealer_price" name="dealer_price" placeholder="0.00"></div>
                    </div>
                    <div class="row">
                        <div class="mb-3 col-md-4"><label for="retail_price" class="form-label">Retail Price*</label><input type="number" step="0.01" class="form-control" id="retail_price" name="retail_price" placeholder="0.00" required></div>
                        <div class="mb-3 col-md-4"><label for="cgst" class="form-label">CGST (%)</label><select class="form-control" id="cgst" name="cgst"></select></div>
                        <div class="mb-3 col-md-4"><label for="sgst" class="form-label">SGST (%)</label><select class="form-control" id="sgst" name="sgst"></select></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" id="saveProductBtn">Save and Add to Invoice</button></div>
        </div>
    </div>
</div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="./js/adminlte.js"></script>
  <script>
    $(document).ready(function() {
        // --- INITIALIZATION ---
        const allProductsData = <?= $products_by_id_json ?>;
        const allCgstRates = <?= $all_cgst_rates_json ?>;
        const allSgstRates = <?= $all_sgst_rates_json ?>;
        const existingItems = <?= $existing_items_json ?>;

        $('#product_select').select2({
            theme: "bootstrap-5",
            placeholder: 'Search and select a product...',
            allowClear: true
        });

        // --- CORE FUNCTIONS ---
        function populateTaxDropdown(rates, selectedValue) {
            let options = '';
            const numericSelectedValue = parseFloat(selectedValue);
            rates.forEach(rate => {
                const numericRate = parseFloat(rate);
                const isSelected = (numericRate === numericSelectedValue) ? 'selected' : '';
                options += `<option value="${numericRate}" ${isSelected}>${numericRate}</option>`;
            });
            return options;
        }

        // MODIFIED: Added discount handling
        function addProductRow(product) {
            const productId = product.product_id;
            const itemName = `items[${productId}]`;

            if ($(`#invoice-items-table tbody tr[data-item-id="${productId}"]`).length > 0) {
                alert('This product is already in the invoice. You can edit the quantity directly.');
                return;
            }

            const cgstOptions = populateTaxDropdown(allCgstRates, product.cgst);
            const sgstOptions = populateTaxDropdown(allSgstRates, product.sgst);
            const price = parseFloat(product.price).toFixed(2);
            const discount = parseFloat(product.discount || 0).toFixed(2);

            const newRow = `
                <tr data-item-id="${productId}">
                    <td>
                        <input type="hidden" name="${itemName}[product_id]" value="${productId}">
                        <input type="hidden" name="${itemName}[product_name]" value="${product.product_name}">
                        ${product.product_name}
                    </td>
                    <td>
                        <input type="hidden" name="${itemName}[product_description]" value="${product.product_description || ''}">
                        ${product.product_description || ''}
                    </td>
                    <td><input type="number" name="${itemName}[quantity]" class="form-control item-qty" value="${product.quantity || 1}" min="1"></td>
                    <td><input type="number" name="${itemName}[price]" class="form-control item-price" value="${price}" step="0.01" min="0"></td>
                    <td><input type="number" name="${itemName}[discount]" class="form-control item-discount" value="${discount}" step="0.01" min="0"></td>
                    <td class="tax-column"><select class="form-select item-cgst" name="${itemName}[cgst]">${cgstOptions}</select></td>
                    <td class="tax-column"><select class="form-select item-sgst" name="${itemName}[sgst]">${sgstOptions}</select></td>
                    <td class="row-total text-end"></td>
                    <td><button type="button" class="btn btn-danger btn-sm remove-item-btn"><i class="fas fa-trash"></i></button></td>
                </tr>`;
            $('#invoice-items-table tbody').append(newRow);
            updateTotals();
        }

        // MODIFIED: Complete overhaul of updateTotals function to include discount
        function updateTotals() {
            const withTax = $('#with_tax_select').val() === 'Yes';
            let subtotal = 0, totalDiscount = 0, totalCgst = 0, totalSgst = 0;
            
            $('.tax-column, .tax-row').toggle(withTax);

            $('#invoice-items-table tbody tr').each(function() {
                const row = $(this);
                const qty = parseFloat(row.find('.item-qty').val()) || 0;
                const price = parseFloat(row.find('.item-price').val()) || 0;
                const discount = parseFloat(row.find('.item-discount').val()) || 0;

                const lineSubtotal = qty * price;
                const taxableValue = lineSubtotal - discount;
                
                let rowTotal = taxableValue;
                
                if (withTax) {
                    const cgstRate = parseFloat(row.find('.item-cgst').val()) || 0;
                    const sgstRate = parseFloat(row.find('.item-sgst').val()) || 0;
                    const cgstAmount = taxableValue * (cgstRate / 100);
                    const sgstAmount = taxableValue * (sgstRate / 100);
                    
                    totalCgst += cgstAmount;
                    totalSgst += sgstAmount;
                    rowTotal += cgstAmount + sgstAmount;
                }
                
                subtotal += lineSubtotal;
                totalDiscount += discount;
                row.find('.row-total').text(rowTotal.toFixed(2));
            });
            
            $('#subtotal').text(subtotal.toFixed(2));
            $('#discount-total').text(totalDiscount.toFixed(2));
            $('#cgst-total').text(totalCgst.toFixed(2));
            $('#sgst-total').text(totalSgst.toFixed(2));
            
            const grandTotal = subtotal - totalDiscount + totalCgst + totalSgst;
            $('#grand-total').html(`<strong>${grandTotal.toFixed(2)}</strong>`);
        }
        
        function repopulateTable() {
            if (existingItems && existingItems.length > 0) {
                existingItems.forEach(item => addProductRow(item));
            }
        }

        // --- EVENT HANDLERS ---
        $('#with_tax_select').on('change', updateTotals);

        // MODIFIED: Added discount: 0
        $('#add-db-product-btn').on('click', function() {
            const selectedId = $('#product_select').val();
            if (!selectedId) { alert('Please select a product first.'); return; }
            const dbProduct = allProductsData[selectedId];
            if (parseInt(dbProduct.quantity) <= 0) {
                alert('The quantity is 0 for "' + dbProduct.product_name + '". It cannot be added.');
                $('#product_select').val(null).trigger('change'); return;
            }
            addProductRow({
                product_id: dbProduct.id, product_name: dbProduct.product_name, product_description: dbProduct.product_description || '',
                price: parseFloat(dbProduct.retail_price).toFixed(2), quantity: 1, discount: 0, cgst: dbProduct.cgst, sgst: dbProduct.sgst
            });
            $('#product_select').val(null).trigger('change');
        });

        // MODIFIED: Added .item-discount to the input listener
        $('#invoice-items-table tbody').on('input', '.item-qty, .item-price, .item-discount', updateTotals);
        
        $('#invoice-items-table tbody').on('click', '.remove-item-btn', function() { $(this).closest('tr').remove(); updateTotals(); });
        
        $('#invoice-items-table tbody').on('change', '.item-cgst, .item-sgst', function() {
            updateTotals(); // Recalculate totals on tax change
            const row = $(this).closest('tr'), productId = row.data('item-id'), newCgst = row.find('.item-cgst').val(), newSgst = row.find('.item-sgst').val();
            $.ajax({ type: 'POST', url: '', data: { action: 'update_product_tax', product_id: productId, cgst: newCgst, sgst: newSgst }, dataType: 'json',
                success: (r) => { if (r.success) console.log(`Tax updated for ID ${productId}.`); else console.error(r.message); },
                error: () => console.error('Server error on tax update.')
            });
        });
        
        // --- MODAL & BRAND/MODEL LOGIC (No changes needed) ---
        const carBrandsAndModels = {
            "Toyota": ["Corolla", "Camry", "RAV4", "Highlander", "Prius", "Land Cruiser", "Hilux", "Fortuner"], "Honda": ["Civic", "Accord", "CR-V", "Pilot", "Odyssey", "City", "BR-V"], "Ford": ["F-150", "Mustang", "Explorer", "Escape", "Ranger", "Everest", "Focus"], "BMW": ["3 Series", "5 Series", "7 Series", "X1", "X3", "X5", "X7"], "Mercedes-Benz": ["C-Class", "E-Class", "S-Class", "GLA", "GLC", "GLE", "GLS"], "Audi": ["A3", "A4", "A6", "A8", "Q3", "Q5", "Q7", "Q8"], "Hyundai": ["Tucson", "Santa Fe", "Elantra", "Sonata", "Creta", "Venue", "Kona"], "Kia": ["Seltos", "Sonet", "Carens", "Carnival", "Sorento", "Sportage"], "Volkswagen": ["Polo", "Vento", "Virtus", "Taigun", "Tiguan", "Passat"], "Tata": ["Nexon", "Harrier", "Safari", "Punch", "Altroz", "Tiago", "Tigor"], "Mahindra": ["Scorpio", "XUV700", "Thar", "Bolero", "XUV300", "Marazzo"], "Maruti Suzuki": ["Swift", "Baleno", "Dzire", "Ertiga", "Brezza", "Alto", "Wagon R"], "Other": []
        };
        const brandSelect = $('#brand_select');
        brandSelect.append('<option value="">-- Select Brand --</option>');
        for (const brand in carBrandsAndModels) { brandSelect.append(`<option value="${brand}">${brand}</option>`); }
        brandSelect.on('change', function() {
            const selectedBrand = $(this).val(), modelSelect = $('#model_select');
            modelSelect.empty();
            $('#brand_manual, #model_manual').hide();
            if (selectedBrand === 'Other') { $('#brand_manual').show(); modelSelect.html('<option value="">-- Enter Brand First --</option>'); }
            else if (selectedBrand) { modelSelect.append('<option value="">-- Select Model --</option>'); carBrandsAndModels[selectedBrand].forEach(model => { modelSelect.append(`<option value="${model}">${model}</option>`); }); modelSelect.append('<option value="Other">Other</option>'); }
            else { modelSelect.html('<option value="">-- Select Brand First --</option>'); }
        });
        $('#model_select').on('change', function() { if ($(this).val() === 'Other') { $('#model_manual').show(); } else { $('#model_manual').hide(); } });
        $('#saveProductBtn').on('click', function() {
            if (!$('#product_name').val() || !$('#retail_price').val() || $('#retail_price').val() <= 0) { alert('Please enter a valid Product Name and Retail Price.'); return; }
            $.ajax({ type: 'POST', url: '', data: $('#addProductForm').serialize(), dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const newProduct = response.product;
                        allProductsData[newProduct.id] = newProduct;
                        $('#product_select').append(new Option(newProduct.product_name, newProduct.id, false, false));
                        if (parseInt(newProduct.quantity) <= 0) { alert('New product "' + newProduct.product_name + '" saved, but not added to invoice (quantity is 0).'); }
                        else { addProductRow({ product_id: newProduct.id, product_name: newProduct.product_name, product_description: newProduct.product_description || '', price: parseFloat(newProduct.retail_price).toFixed(2), quantity: 1, discount: 0, cgst: newProduct.cgst, sgst: newProduct.sgst }); }
                        bootstrap.Modal.getInstance($('#addProductModal')).hide();
                    } else { alert('Error: ' + response.message); }
                }, error: () => alert('A server error occurred.')
            });
        });
        $('#addProductModal').on('show.bs.modal', function(){ $('#cgst').html(populateTaxDropdown(allCgstRates, 0)); $('#sgst').html(populateTaxDropdown(allSgstRates, 0)); });
        $('#addProductModal').on('hidden.bs.modal', function(){ $('#addProductForm')[0].reset(); $('#brand_manual, #model_manual').hide(); brandSelect.val('').trigger('change'); });

        // --- INITIAL PAGE LOAD ---
        repopulateTable();
        updateTotals();
    }); 
  </script>
</body>
</html>