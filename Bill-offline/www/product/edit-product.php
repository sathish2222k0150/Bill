<?php
require '../config.php'; // Includes your PDO connection file
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$product = null;
$error_message = '';
$success_message = '';

// --- Handle form submission (POST request) to update the product ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

    if (empty(trim($_POST['product_name']))) {
        $error_message = 'Product Name is required.';
    } elseif (!$product_id) {
        $error_message = 'Invalid Product ID.';
    } else {
        try {
            // Determine the final brand and model names
            $brand_name = !empty(trim($_POST['brand_manual'] ?? '')) 
                ? trim($_POST['brand_manual']) 
                : trim($_POST['brand_select'] ?? '');
            
            $model = !empty(trim($_POST['model_manual'] ?? '')) 
                ? trim($_POST['model_manual']) 
                : trim($_POST['model_select'] ?? '');

            $sql = "UPDATE products SET 
                        brand_name = :brand_name, 
                        model = :model, 
                        product_name = :product_name, 
                        product_description = :product_description, 
                        product_location = :product_location, 
                        quantity = :quantity, 
                        dealer_price = :dealer_price, 
                        retail_price = :retail_price, 
                        cgst = :cgst, 
                        sgst = :sgst, 
                        updated_at = NOW() 
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':brand_name' => ($brand_name === 'new_brand' || empty($brand_name)) ? null : $brand_name,
                ':model' => ($model === 'new_model' || empty($model)) ? null : $model,
                ':product_name' => trim($_POST['product_name']),
                ':product_description' => trim($_POST['product_description'] ?? ''),
                ':product_location' => trim($_POST['product_location'] ?? ''),
                ':quantity' => intval($_POST['quantity'] ?? 0),
                ':dealer_price' => !empty($_POST['dealer_price']) ? floatval($_POST['dealer_price']) : null,
                ':retail_price' => !empty($_POST['retail_price']) ? floatval($_POST['retail_price']) : null,
                ':cgst' => !empty($_POST['cgst']) ? floatval($_POST['cgst']) : null,
                ':sgst' => !empty($_POST['sgst']) ? floatval($_POST['sgst']) : null,
                ':id' => $product_id
            ]);

            $_SESSION['success_message'] = "Product updated successfully!";
            header('Location: product-management.php');
            exit;

        } catch (PDOException $e) {
            // In a real application, you would log this error
            $error_message = "Database error: Failed to update product. " . $e->getMessage();
        }
    }
}

// --- Fetch product data for the form (GET request) ---
if ($product_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute([':id' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $error_message = "Product not found.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: Could not fetch product details.";
        $product = null; // Ensure product is null on error
    }
} else {
    $error_message = "No product ID provided.";
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>SDS Automotive - Edit Product</title>
    <!-- Using the same CSS files as product-management.php for consistency -->
    <link rel="stylesheet" href="../css/adminlte.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
    <div class="app-wrapper">
        <?php include '../header.php'; ?>
        <?php include '../sidebar.php'; ?>
        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6">
                            <h3 class="mb-0">Edit Product</h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                                <li class="breadcrumb-item"><a href="product-management.php">Products</a></li>
                                <li class="breadcrumb-item active" aria-current="page">
                                    Edit Product
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div class="app-content">
                <div class="container-fluid">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Product Details</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($error_message): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                            <?php endif; ?>
                            
                            <?php if ($product): ?>
                            <form id="editProductForm" method="POST" action="edit-product.php?id=<?php echo $product_id; ?>">
                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                                
                                <div class="row">
                                    <!-- Brand Selection -->
                                    <div class="form-group col-md-6 mb-3">
                                        <label for="brand_select">Brand</label>
                                        <select class="form-control" id="brand_select" name="brand_select">
                                            <!-- Brands will be loaded here by JS -->
                                        </select>
                                        <input type="text" class="form-control mt-2" id="brand_manual" name="brand_manual" placeholder="Enter New Brand Name" style="display:none;">
                                    </div>
                                    <!-- Model Selection -->
                                    <div class="form-group col-md-6 mb-3">
                                        <label for="model_select">Model</label>
                                        <select class="form-control" id="model_select" name="model_select">
                                            <option value="">-- Select Brand First --</option>
                                        </select>
                                        <input type="text" class="form-control mt-2" id="model_manual" name="model_manual" placeholder="Enter New Model Name" style="display:none;">
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="product_name">Product Name*</label>
                                    <input type="text" class="form-control" id="product_name" name="product_name" value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
                                </div>
                                <div class="form-group mb-3">
                                    <label for="product_description">Product Description</label>
                                    <textarea class="form-control" id="product_description" name="product_description" rows="3"><?php echo htmlspecialchars($product['product_description']); ?></textarea>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-4 mb-3">
                                        <label for="product_location">Product Location</label>
                                        <input type="text" class="form-control" id="product_location" name="product_location" value="<?php echo htmlspecialchars($product['product_location']); ?>">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label for="quantity">Quantity</label>
                                        <input type="number" class="form-control" id="quantity" name="quantity" value="<?php echo htmlspecialchars($product['quantity']); ?>">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label for="dealer_price">Dealer Price</label>
                                        <input type="number" step="0.01" class="form-control" id="dealer_price" name="dealer_price" value="<?php echo htmlspecialchars($product['dealer_price']); ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-4 mb-3">
                                        <label for="retail_price">Retail Price</label>
                                        <input type="number" step="0.01" class="form-control" id="retail_price" name="retail_price" value="<?php echo htmlspecialchars($product['retail_price']); ?>">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label for="cgst">CGST (%)</label>
                                        <input type="number" step="0.01" class="form-control" id="cgst" name="cgst" value="<?php echo htmlspecialchars($product['cgst']); ?>">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label for="sgst">SGST (%)</label>
                                        <input type="number" step="0.01" class="form-control" id="sgst" name="sgst" value="<?php echo htmlspecialchars($product['sgst']); ?>">
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                    <a href="product-management.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                            <?php else: ?>
                                <p>The requested product could not be found. Please <a href="product-management.php">go back</a> and try again.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <?php include '../footer.php'; ?>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

    <script>
    $(document).ready(function() {
        // This is the same brand/model data from your product-management page
        const carBrandsAndModels = { "Toyota": ["Corolla", "Camry", "RAV4"], "Honda": ["Civic", "Accord", "CR-V"], /* ...and so on */ };

        // Get product's current data from PHP
        const currentBrand = "<?php echo addslashes($product['brand_name'] ?? ''); ?>";
        const currentModel = "<?php echo addslashes($product['model'] ?? ''); ?>";

        function populateBrands() {
            let options = '<option value="">-- Select Brand --</option>';
            const sortedBrands = Object.keys(carBrandsAndModels).sort();
            for (const brand of sortedBrands) {
                options += `<option value="${brand}" ${currentBrand === brand ? 'selected' : ''}>${brand}</option>`;
            }
            options += '<option value="new_brand">-- Other (Add New Brand) --</option>';
            $('#brand_select').html(options);
        }

        function populateModels(brandName, selectedModel = '') {
            let options = '<option value="">-- Select Model --</option>';
            if (brandName && carBrandsAndModels[brandName]) {
                carBrandsAndModels[brandName].sort().forEach(model => {
                    options += `<option value="${model}" ${selectedModel === model ? 'selected' : ''}>${model}</option>`;
                });
            } else {
                 $('#model_select').html('<option value="">-- Select Brand First --</option>');
                 return;
            }
            options += '<option value="new_model">-- Other (Add New Model) --</option>';
            $('#model_select').html(options);
        }

        // --- Initial Load ---
        populateBrands();
        // Trigger population of models based on the current brand
        if (currentBrand) {
            populateModels(currentBrand, currentModel);
        }

        // --- Event Listeners ---
        $('#brand_select').on('change', function() {
            const selectedBrand = $(this).val();
            $('#model_manual').hide().val('');
            if (selectedBrand === 'new_brand') {
                $('#brand_manual').show().focus();
                $('#model_manual').show();
                $('#model_select').hide();
            } else {
                $('#brand_manual').hide().val('');
                $('#model_select').show();
                populateModels(selectedBrand);
            }
        });

        $('#model_select').on('change', function() {
            if ($(this).val() === 'new_model') {
                $('#model_manual').show().focus();
            } else {
                $('#model_manual').hide().val('');
            }
        });
    });
    </script>
</body>
</html>