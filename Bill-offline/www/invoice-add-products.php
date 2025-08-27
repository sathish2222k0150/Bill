<?php
session_start();
require_once 'config.php';

// ACTION: ADD A NEW PRODUCT FROM MODAL
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

// ACTION: UPDATE PRODUCT TAX FROM INVOICE ROW
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


if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
if (!$customer_id || !isset($pdo)) {
    header('Location: step-1.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header('Location: step-1.php?error=notfound');
    exit;
}

// Fetch data for page display
$all_products = [];
$products_by_id_json = '{}';
$all_cgst_rates_json = '[]';
$all_sgst_rates_json = '[]';
$existing_items_json = '[]';

// Check for existing invoice data in the session to repopulate the form
if (isset($_SESSION['invoice_data']) && isset($_SESSION['invoice_data'][$customer_id])) {
    $existing_items_json = json_encode(array_values($_SESSION['invoice_data'][$customer_id]['items']));
}


if (isset($pdo)) {
    // Get all products for selection dropdown
    $stmt = $pdo->query("SELECT id, product_name, product_description, quantity, retail_price, cgst, sgst FROM products ORDER BY product_name ASC");
    $all_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $products_by_id = [];
    foreach ($all_products as $p) {
        $products_by_id[$p['id']] = $p;
    }
    $products_by_id_json = json_encode($products_by_id);

    // Get all unique CGST rates
    $stmt_cgst = $pdo->query("SELECT DISTINCT cgst FROM products ORDER BY cgst ASC");
    $all_cgst_rates = $stmt_cgst->fetchAll(PDO::FETCH_COLUMN, 0);
    $all_cgst_rates_json = json_encode($all_cgst_rates);

    // Get all unique SGST rates
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
  <title>Step 2: Add Products to Invoice</title>
  
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
    /* Ensure the totals table is right-aligned and looks clean */
    .totals-table { max-width: 400px; margin-left: auto; }
    .totals-table td:first-child { font-weight: bold; }
    /* On small screens, make table inputs a bit wider */
    @media (max-width: 768px) {
        #invoice-items-table .form-control { min-width: 80px; }
        #invoice-items-table .form-select { min-width: 100px; }
    }
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
                            <h4 class="mb-sm-0">Create Invoice</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="step-1.php">Customer</a></li>
                                    <li class="breadcrumb-item active">Add Products</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Form Container Card -->
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Step 2: Add Products for <?= htmlspecialchars($customer['customer_name']) ?></h3>
                    </div>
                    <form id="invoice-form" method="POST" action="preview.php">
                        <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                        <div class="card-body">
                            <!-- Invoice Details Section (Responsive) -->
                            <div class="row border-bottom pb-3 mb-4">
                                <div class="col-lg-3 col-md-6 col-12 mb-3">
                                    <label for="invoice_date" class="form-label"><strong>Invoice Date</strong></label>
                                    <input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-lg-3 col-md-6 col-12 mb-3">
                                    <label for="invoice_type_select" class="form-label"><strong>Invoice Type</strong></label>
                                    <select class="form-select" id="invoice_type_select" name="invoice_type" required>
                                        <option value="Invoice">Invoice</option>
                                        <option value="Tax Invoice" selected>Tax Invoice</option>
                                        <option value="Estimate">Estimate</option>
                                        <option value="Tax Estimate">Tax Estimate</option>
                                        <option value="Supplementary">Supplementary</option>
                                        <option value="Tax Supplementary">Tax Supplementary</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6 col-12 mb-3">
                                    <label for="with_tax_select" class="form-label"><strong>Apply Tax</strong></label>
                                    <select class="form-select" id="with_tax_select" name="with_tax" required>
                                        <option value="Yes" selected>Yes</option>
                                        <option value="No">No</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Product Selection Section (Responsive) -->
                            <div class="row align-items-end mb-4">
                                <div class="col-md-6 col-12 mb-2">
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
                                        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addProductModal"><i class="fas fa-edit me-1"></i> Add Manual Product</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice Items Table (Responsive Wrapper) -->
                            <h4 class="mb-3">Invoice Items</h4>
                            <div class="table-responsive">
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
                                    <tbody>
                                        <!-- Rows will be added by JavaScript -->
                                    </tbody>
                                </table>
                            </div>

                            <!-- Totals and Navigation (Responsive) -->
                            <div class="row mt-4">
                                <div class="col-md-6 d-flex align-items-center mb-3">
                                    <a href="step-1.php?customer_id=<?= $customer_id ?>" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Customer</a>
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
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-file-alt me-2"></i> Preview Invoice</button>
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
            <div class="modal-header"><h5 class="modal-title">Add New Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="addProductForm">
                    <input type="hidden" name="action" value="add_product">
                    <div class="row">
                        <div class="mb-3 col-md-6">
                            <label for="brand_select" class="form-label">Brand</label>
                            <select class="form-control" id="brand_select" name="brand_select"></select>
                            <input type="text" class="form-control mt-2" id="brand_manual" name="brand_manual" placeholder="Enter New Brand Name" style="display:none;">
                        </div>
                        <div class="mb-3 col-md-6">
                            <label for="model_select" class="form-label">Model</label>
                            <select class="form-control" id="model_select" name="model_select">
                                <option value="">-- Select Brand First --</option>
                            </select>
                            <input type="text" class="form-control mt-2" id="model_manual" name="model_manual" placeholder="Enter New Model Name" style="display:none;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="product_name" class="form-label">Product Name*</label>
                        <input type="text" class="form-control" id="product_name" name="product_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="product_description" class="form-label">Product Description</label>
                        <textarea class="form-control" id="product_description" name="product_description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="mb-3 col-md-4">
                            <label for="product_location" class="form-label">Product Location</label>
                            <input type="text" class="form-control" id="product_location" name="product_location">
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="0">
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="dealer_price" class="form-label">Dealer Price</label>
                            <input type="number" step="0.01" class="form-control" id="dealer_price" name="dealer_price" placeholder="Enter price for 1 quantity">
                        </div>
                    </div>
                    <div class="row">
                        <div class="mb-3 col-md-4">
                            <label for="retail_price" class="form-label">Retail Price</label>
                            <input type="number" step="0.01" class="form-control" id="retail_price" name="retail_price" placeholder="Enter price for 1 quantity" required>
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="cgst" class="form-label">CGST (%)</label>
                            <select class="form-control" id="cgst" name="cgst"></select>
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="sgst" class="form-label">SGST (%)</label>
                            <select class="form-control" id="sgst" name="sgst"></select>
                        </div>
                    </div><!-- Your modal form fields are here, no changes needed as they use Bootstrap's grid -->
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveProductBtn">Save and Add</button>
            </div>
        </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="./js/adminlte.js"></script>                                         
  <script>
    $(document).ready(function() {
        const allProductsData = <?= $products_by_id_json ?>;
        const allCgstRates = <?= $all_cgst_rates_json ?>;
        const allSgstRates = <?= $all_sgst_rates_json ?>;
        const existingItems = <?= $existing_items_json ?>;

        $('#product_select').select2({
            theme: "bootstrap-5",
            placeholder: 'Search and select a product...',
            allowClear: true
        });

        function populateTaxDropdown(rates, selectedValue) {
            let options = '';
            rates.forEach(rate => {
                const isSelected = (parseFloat(rate) === parseFloat(selectedValue)) ? 'selected' : '';
                options += `<option value="${rate}" ${isSelected}>${rate}</option>`;
            });
            return options;
        }

        function addProductRow(product, isRepopulating = false) {
            const productId = product.id;
            const existingRow = $(`#invoice-items-table tbody tr[data-item-id="${productId}"]`);

            if (existingRow.length > 0) {
                if (!isRepopulating) {
                    alert('This product is already in the invoice. The quantity has been increased by 1.');
                    const qtyInput = existingRow.find('.item-qty');
                    const currentQty = parseInt(qtyInput.val()) || 0;
                    qtyInput.val(currentQty + 1);
                    qtyInput.trigger('input'); 
                }
                return;
            }

            const cgstOptions = populateTaxDropdown(allCgstRates, product.cgst);
            const sgstOptions = populateTaxDropdown(allSgstRates, product.sgst);
            
            // MODIFIED: Added discount input field to the new row
            const newRow = `
                <tr data-item-id="${productId}">
                    <td>
                        <input type="hidden" name="product_id[]" value="${productId}">
                        <input type="hidden" name="product_name[]" value="${product.name}">
                        ${product.name}
                    </td>
                    <td>
                        <input type="hidden" name="product_description[]" value="${product.description || ''}">
                        ${product.description || ''}
                    </td>
                    <td><input type="number" name="quantity[]" class="form-control item-qty" value="${product.quantity || 1}" min="1"></td>
                    <td><input type="number" name="price[]" class="form-control item-price" value="${parseFloat(product.price).toFixed(2)}" step="0.01" min="0"></td>
                    <td><input type="number" name="discount[]" class="form-control item-discount" value="${parseFloat(product.discount || 0).toFixed(2)}" step="0.01" min="0"></td>
                    <td class="tax-column">
                        <select class="form-select item-cgst" name="cgst[]">${cgstOptions}</select>
                    </td>
                    <td class="tax-column">
                        <select class="form-select item-sgst" name="sgst[]">${sgstOptions}</select>
                    </td>
                    <td class="row-total text-end"></td>
                    <td><button type="button" class="btn btn-danger btn-sm remove-item-btn"><i class="fas fa-trash"></i></button></td>
                </tr>`;
            
            $('#invoice-items-table tbody').append(newRow);
            const appendedRow = $('#invoice-items-table tbody tr:last');
            appendedRow.find('.item-cgst').val(product.cgst);
            appendedRow.find('.item-sgst').val(product.sgst);

            updateTotals(); 
        }

        // MODIFIED: Complete overhaul of the updateTotals function to include discount
        function updateTotals() {
            const withTax = $('#with_tax_select').val() === 'Yes';
            let subtotal = 0;
            let totalDiscount = 0;
            let totalCgst = 0;
            let totalSgst = 0;

            if (withTax) {
                $('.tax-column, .tax-row').show();
            } else {
                $('.tax-column, .tax-row').hide();
            }

            $('#invoice-items-table tbody tr').each(function() {
                const row = $(this);
                const qty = parseFloat(row.find('.item-qty').val()) || 0;
                const price = parseFloat(row.find('.item-price').val()) || 0;
                const discount = parseFloat(row.find('.item-discount').val()) || 0;

                const lineSubtotal = qty * price;
                const valueAfterDiscount = lineSubtotal - discount;
                
                let rowTotal = valueAfterDiscount;

                if (withTax) {
                    const cgstRate = parseFloat(row.find('.item-cgst').val()) || 0;
                    const sgstRate = parseFloat(row.find('.item-sgst').val()) || 0;
                    
                    // Tax is calculated on the price AFTER discount
                    const cgstAmount = valueAfterDiscount * (cgstRate / 100);
                    const sgstAmount = valueAfterDiscount * (sgstRate / 100);
                    
                    totalCgst += cgstAmount;
                    totalSgst += sgstAmount;
                    rowTotal += cgstAmount + sgstAmount;
                }
                
                subtotal += lineSubtotal;
                totalDiscount += discount;
                row.find('.row-total').text(rowTotal.toFixed(2));
            });

            $('#subtotal').text(subtotal.toFixed(2));
            $('#discount-total').text(totalDiscount.toFixed(2)); // Display total discount
            
            if (withTax) {
                $('#cgst-total').text(totalCgst.toFixed(2));
                $('#sgst-total').text(totalSgst.toFixed(2));
            }

            const grandTotal = subtotal - totalDiscount + totalCgst + totalSgst;
            $('#grand-total').html(`<strong>${grandTotal.toFixed(2)}</strong>`);
        }
        
        $('#with_tax_select').on('change', function() {
            updateTotals();
        });

        $('#add-db-product-btn').on('click', function() {
            const selectedId = $('#product_select').val();
            if (!selectedId) {
                alert('Please select a product first.');
                return;
            }

            const dbProduct = allProductsData[selectedId];

            if (parseInt(dbProduct.quantity) <= 0) {
                alert('The product "' + dbProduct.product_name + '" has a quantity of 0 and cannot be added to the invoice.');
                $('#product_select').val(null).trigger('change');
                return;
            }

            addProductRow({
                id: dbProduct.id,
                name: dbProduct.product_name,
                description: dbProduct.product_description || '',
                price: parseFloat(dbProduct.retail_price).toFixed(2),
                quantity: 1,
                discount: 0, // Default discount to 0
                cgst: dbProduct.cgst,
                sgst: dbProduct.sgst
            });
            $('#product_select').val(null).trigger('change');
        });
        
        // MODIFIED: Added .item-discount to the event listener
        $('#invoice-items-table tbody').on('input', '.item-qty, .item-price, .item-discount', function() {
            updateTotals();
        });

        $('#invoice-items-table tbody').on('click', '.remove-item-btn', function() {
            $(this).closest('tr').remove();
            updateTotals();
        });

        $('#invoice-items-table tbody').on('change', '.item-cgst, .item-sgst', function() {
            const row = $(this).closest('tr');
            const productId = row.data('item-id');
            const newCgst = row.find('.item-cgst').val();
            const newSgst = row.find('.item-sgst').val();

            $.ajax({
                type: 'POST',
                url: '',
                data: { action: 'update_product_tax', product_id: productId, cgst: newCgst, sgst: newSgst },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        console.log(`Product ID ${productId} tax updated.`);
                        if(allProductsData[productId]){
                            allProductsData[productId].cgst = newCgst;
                            allProductsData[productId].sgst = newSgst;
                        }
                    } else {
                        console.error('Failed to update product tax:', response.message);
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('A server error occurred while updating product tax.');
                }
            });
            
            updateTotals();
        });

        const carBrandsAndModels = {
            "Toyota": ["Corolla", "Camry", "RAV4", "Highlander", "Prius", "Land Cruiser", "Hilux", "Fortuner"],
            "Honda": ["Civic", "Accord", "CR-V", "Pilot", "Odyssey", "City", "BR-V"],
            "Ford": ["F-150", "Mustang", "Explorer", "Escape", "Ranger", "Everest", "Focus"],
            "BMW": ["3 Series", "5 Series", "7 Series", "X1", "X3", "X5", "X7"],
            "Mercedes-Benz": ["C-Class", "E-Class", "S-Class", "GLA", "GLC", "GLE", "GLS"],
            "Audi": ["A3", "A4", "A6", "A8", "Q3", "Q5", "Q7", "Q8"],
            "Hyundai": ["Tucson", "Santa Fe", "Elantra", "Sonata", "Creta", "Venue", "Kona"],
            "Kia": ["Seltos", "Sonet", "Carens", "Carnival", "Sorento", "Sportage"],
            "Volkswagen": ["Polo", "Vento", "Virtus", "Taigun", "Tiguan", "Passat"],
            "Tata": ["Nexon", "Harrier", "Safari", "Punch", "Altroz", "Tiago", "Tigor"],
            "Mahindra": ["Scorpio", "XUV700", "Thar", "Bolero", "XUV300", "Marazzo"],
            "Maruti Suzuki": ["Swift", "Baleno", "Dzire", "Ertiga", "Brezza", "Alto", "Wagon R"],
            "Other": []
        };
        
        const brandSelect = $('#brand_select');
        brandSelect.append('<option value="">-- Select Brand --</option>');
        for (const brand in carBrandsAndModels) {
            brandSelect.append(`<option value="${brand}">${brand}</option>`);
        }

        brandSelect.on('change', function() {
            const selectedBrand = $(this).val();
            const modelSelect = $('#model_select');
            modelSelect.empty();
            $('#brand_manual, #model_manual').hide();

            if (selectedBrand === 'Other') {
                $('#brand_manual').show();
                modelSelect.html('<option value="">-- Enter Brand First --</option>');
            } else if (selectedBrand) {
                modelSelect.append('<option value="">-- Select Model --</option>');
                carBrandsAndModels[selectedBrand].forEach(model => {
                    modelSelect.append(`<option value="${model}">${model}</option>`);
                });
                modelSelect.append('<option value="Other">Other</option>');
            } else {
                modelSelect.html('<option value="">-- Select Brand First --</option>');
            }
        });

        $('#model_select').on('change', function() {
            if ($(this).val() === 'Other') {
                $('#model_manual').show();
            } else {
                $('#model_manual').hide();
            }
        });

        $('#saveProductBtn').on('click', function() {
            const productName = $('#product_name').val();
            const retailPrice = $('#retail_price').val();
            
            if (!productName || !retailPrice || retailPrice <= 0) {
                alert('Please enter a valid Product Name and Retail Price.');
                return;
            }

            const formData = $('#addProductForm').serialize();

            $.ajax({
                type: 'POST',
                url: '',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const newProduct = response.product;
                        
                        allProductsData[newProduct.id] = newProduct;
                        const newOption = new Option(newProduct.product_name, newProduct.id, false, false);
                        $('#product_select').append(newOption);
                        
                        if (parseInt(newProduct.quantity) <= 0) {
                            alert('The new product "' + newProduct.product_name + '" was saved, but not added to the invoice because its quantity is 0.');
                        } else {
                            addProductRow({
                                id: newProduct.id,
                                name: newProduct.product_name,
                                description: newProduct.product_description || '',
                                price: parseFloat(newProduct.retail_price).toFixed(2),
                                quantity: 1, 
                                discount: 0, // Default discount to 0
                                cgst: newProduct.cgst,
                                sgst: newProduct.sgst
                            });
                        }
                        
                        bootstrap.Modal.getInstance($('#addProductModal')).hide();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('A server error occurred. Please try again.');
                }
            });
        });
        
        $('#addProductModal').on('show.bs.modal', function(){
            $('#cgst').html(populateTaxDropdown(allCgstRates, 0));
            $('#sgst').html(populateTaxDropdown(allSgstRates, 0));
        });

        $('#addProductModal').on('hidden.bs.modal', function(){
            $('#addProductForm')[0].reset();
            $('#model_select').html('<option value="">-- Select Brand First --</option>');
            $('#brand_manual, #model_manual').hide();
        });

        function repopulateTable() {
            if (existingItems.length > 0) {
                existingItems.forEach(item => {
                    addProductRow({
                        id: item.product_id,
                        name: item.product_name,
                        description: item.product_description,
                        price: item.price,
                        quantity: item.quantity,
                        discount: item.discount || 0, // MODIFIED: Pass discount
                        cgst: item.cgst,
                        sgst: item.sgst
                    }, true); 
                });
            }
        }
        
        repopulateTable();
        updateTotals();
    });
  </script>
</body>
</html>