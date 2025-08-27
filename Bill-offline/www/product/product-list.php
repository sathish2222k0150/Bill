<?php require '../config.php'; 
session_start();
if (!isset($_SESSION['user_id'])) { 
    header('Location: index.php'); 
    exit; 
} 
?>
<!doctype html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>SDS Automotive - Product Management</title>
    <link rel="preload" href="../css/adminlte.css" as="style" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css"
        integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q=" crossorigin="anonymous" media="print"
        onload="this.media='all'" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="../css/adminlte.css" />
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css"
        integrity="sha256-4MX+61mt9NVvvuPjUWdUdyfZfxSB1/Rf9WtqRHgG5S0=" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .chart-container {
            padding: 30px;
        }
        .small-box {
            border-radius: 0.5rem;
            padding: 1rem;
            color: #fff;
            margin-bottom: 20px;
        }
        .small-box .inner h3 {
            font-size: 2.5rem;
        }
        .small-box-footer {
            display: block;
            padding-top: 10px;
        }
        .sortable {
            cursor: pointer;
        }
        .sortable .fa-sort, .sortable .fa-sort-up, .sortable .fa-sort-down {
            margin-left: 5px;
        }
        .sortable:hover {
            background-color: #f2f2f2;
        }
        .mr-2 {
            margin-right: 0.5rem;
        }
    </style>
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
                            <h3 class="mb-0">Product Management</h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                <li class="breadcrumb-item"><a href="#">Home</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Products</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div class="app-content">
                <div class="container-fluid">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Product List</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addProductModal">
                                    <i class="fas fa-plus"></i> Add Product
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Filter Section -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="brandFilter">Filter by Brand</label>
                                    <select id="brandFilter" class="form-control">
                                        <option value="">All Brands</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="modelFilter">Filter by Model</label>
                                    <select id="modelFilter" class="form-control" disabled>
                                        <option value="">All Models</option>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button id="applyFiltersBtn" class="btn btn-success mr-2">Apply Filters</button>
                                    <button id="resetFiltersBtn" class="btn btn-secondary">Reset Filters</button>
                                </div>
                            </div>
                            
                            <!-- Product Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th class="sortable" data-sort="id">ID <i class="fas fa-sort"></i></th>
                                            <th class="sortable" data-sort="product_name">Product Name <i class="fas fa-sort"></i></th>
                                            <th class="sortable" data-sort="brand_name">Brand <i class="fas fa-sort"></i></th>
                                            <th class="sortable" data-sort="model">Model <i class="fas fa-sort"></i></th>
                                            <th class="sortable" data-sort="quantity">Quantity <i class="fas fa-sort"></i></th>
                                            <th class="sortable" data-sort="retail_price">Retail Price <i class="fas fa-sort"></i></th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="productTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Add Product Modal -->
        <div class="modal fade" id="addProductModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Add New Product</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
                    <div class="modal-body">
                        <form id="addProductForm">
                            <input type="hidden" name="action" value="add_product">
                            <div class="row">
                                <div class="form-group col-md-6"><label for="brand_select">Brand</label><select class="form-control" id="brand_select" name="brand_select"></select><input type="text" class="form-control mt-2" id="brand_manual" name="brand_manual" placeholder="Enter New Brand Name" style="display:none;"></div>
                                <div class="form-group col-md-6"><label for="model_select">Model</label><select class="form-control" id="model_select" name="model_select"><option value="">-- Select Brand First --</option></select><input type="text" class="form-control mt-2" id="model_manual" name="model_manual" placeholder="Enter New Model Name" style="display:none;"></div>
                            </div>
                            <div class="form-group"><label for="product_name">Product Name*</label><input type="text" class="form-control" id="product_name" name="product_name" required></div>
                            <div class="form-group"><label for="product_description">Product Description</label><textarea class="form-control" id="product_description" name="product_description" rows="3"></textarea></div>
                            <div class="row">
                                <div class="form-group col-md-4"><label for="product_location">Product Location</label><input type="text" class="form-control" id="product_location" name="product_location"></div>
                                <div class="form-group col-md-4"><label for="quantity">Quantity</label><input type="number" class="form-control" id="quantity" name="quantity" value=""></div>
                                <div class="form-group col-md-4"><label for="dealer_price">Dealer Price</label><input type="number" step="0.01" class="form-control" id="dealer_price" name="dealer_price" placeholder="Enter price for 1 quantity"></div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-4"><label for="retail_price">Retail Price</label><input type="number" step="0.01" class="form-control" id="retail_price" name="retail_price" placeholder="Enter price for 1 quantity"></div>
                                <div class="form-group col-md-4"><label for="cgst">CGST (%)</label><input type="number" step="0.01" class="form-control" id="cgst" name="cgst" placeholder="Enter CGST percentage"></div>
                                <div class="form-group col-md-4"><label for="sgst">SGST (%)</label><input type="number" step="0.01" class="form-control" id="sgst" name="sgst" placeholder="Enter SGST percentage"></div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="button" class="btn btn-primary" id="saveProductBtn">Save Product</button></div>
                </div>
            </div>
        </div>

        <!-- Edit Product Modal -->
        <div class="modal fade" id="editProductModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Edit Product</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
                    <div class="modal-body">
                        <form id="editProductForm">
                            <input type="hidden" name="action" value="update_product"><input type="hidden" id="edit_product_id" name="product_id">
                            <div class="row">
                                <div class="form-group col-md-6"><label for="edit_brand_select">Brand</label><select class="form-control" id="edit_brand_select" name="brand_select"></select><input type="text" class="form-control mt-2" id="edit_brand_manual" name="brand_manual" placeholder="Enter New Brand Name" style="display:none;"></div>
                                <div class="form-group col-md-6"><label for="edit_model_select">Model</label><select class="form-control" id="edit_model_select" name="model_select"></select><input type="text" class="form-control mt-2" id="edit_model_manual" name="model_manual" placeholder="Enter New Model Name" style="display:none;"></div>
                            </div>
                            <div class="form-group"><label for="edit_product_name">Product Name*</label><input type="text" class="form-control" id="edit_product_name" name="product_name" required></div>
                            <div class="form-group"><label for="edit_product_description">Product Description</label><textarea class="form-control" id="edit_product_description" name="product_description" rows="3"></textarea></div>
                            <div class="row">
                                <div class="form-group col-md-4"><label for="edit_product_location">Product Location</label><input type="text" class="form-control" id="edit_product_location" name="product_location"></div>
                                <div class="form-group col-md-4"><label for="edit_quantity">Quantity</label><input type="number" class="form-control" id="edit_quantity" name="quantity"></div>
                                <div class="form-group col-md-4"><label for="edit_dealer_price">Dealer Price</label><input type="number" step="0.01" class="form-control" id="edit_dealer_price" name="dealer_price"></div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-4"><label for="edit_retail_price">Retail Price</label><input type="number" step="0.01" class="form-control" id="edit_retail_price" name="retail_price"></div>
                                <div class="form-group col-md-4"><label for="edit_cgst">CGST (%)</label><input type="number" step="0.01" class="form-control" id="edit_cgst" name="cgst"></div>
                                <div class="form-group col-md-4"><label for="edit_sgst">SGST (%)</label><input type="number" step="0.01" class="form-control" id="edit_sgst" name="sgst"></div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="button" class="btn btn-primary" id="updateProductBtn">Save Changes</button></div>
                </div>
            </div>
        </div>

        <!-- Message Modal -->
        <div class="modal fade" id="messageModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header" id="messageModalHeader"><h5 class="modal-title" id="messageModalTitle"></h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
                    <div class="modal-body"><p id="messageModalBody"></p></div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></div>
                </div>
            </div>
        </div>

        <?php include '../footer.php'; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <script src="../js/adminlte.js"></script>

    <script>
    $(document).ready(function() {
        let currentSortBy = 'id';
        let currentSortOrder = 'DESC';

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
            "Renault": ["Kwid", "Triber", "Kiger", "Duster"],
            "Skoda": ["Kushaq", "Slavia", "Octavia", "Superb", "Kodiaq"],
            "MG": ["Hector", "Astor", "Gloster", "ZS EV"],
            "Jeep": ["Compass", "Meridian", "Wrangler", "Grand Cherokee"],
            "Volvo": ["XC40", "XC60", "XC90", "S60", "S90"],
            "Lexus": ["ES", "LS", "RX", "NX", "UX"],
            "Jaguar": ["XE", "XF", "F-Pace", "E-Pace"],
            "Land Rover": ["Range Rover", "Range Rover Sport", "Discovery", "Defender"],
            "Porsche": ["911", "Cayenne", "Macan", "Panamera"],
            "Ferrari": ["Portofino", "Roma", "SF90", "F8 Tributo"],
            "Lamborghini": ["Huracan", "Aventador", "Urus"],
            "Maserati": ["Ghibli", "Quattroporte", "Levante", "MC20"],
            "Other": []
        };

        /**
         * Dynamically updates the local carBrandsAndModels object and refreshes filters.
         */
        function updateLocalData(newBrand, newModel, associatedBrand) {
            let dataUpdated = false;
            if (newBrand && !carBrandsAndModels.hasOwnProperty(newBrand)) {
                carBrandsAndModels[newBrand] = [];
                dataUpdated = true;
            }

            let brandForModel = newBrand || associatedBrand;
            if (newModel && brandForModel && carBrandsAndModels.hasOwnProperty(brandForModel)) {
                if (!carBrandsAndModels[brandForModel].includes(newModel)) {
                    carBrandsAndModels[brandForModel].push(newModel);
                    carBrandsAndModels[brandForModel].sort();
                    dataUpdated = true;
                }
            }
            
            if (dataUpdated) {
                const currentBrand = $('#brandFilter').val(); // Preserve current filter selection
                populateBrandFilter();
                $('#brandFilter').val(currentBrand);
            }
        }

        function showMessage(title, message, isSuccess) {
            $('#messageModalTitle').text(title);
            $('#messageModalBody').text(message);
            $('#messageModalHeader').removeClass('bg-success bg-danger').addClass(isSuccess ? 'bg-success' : 'bg-danger');
            
            const addModalOpen = $('#addProductModal').hasClass('show');
            const editModalOpen = $('#editProductModal').hasClass('show');
            
            $('#messageModal').modal('show');
            
            $('#messageModal').off('hidden.bs.modal').on('hidden.bs.modal', function() {
                if (addModalOpen) $('#addProductModal').modal('show');
                else if (editModalOpen) $('#editProductModal').modal('show');
            });
        }

        function fetchProducts() {
            const brand = $('#brandFilter').val();
            const model = $('#modelFilter').val();
            $('#productTableBody').html('<tr><td colspan="7" class="text-center">Loading...</td></tr>');
            
            $.ajax({
                url: 'product-actions.php', type: 'GET',
                data: { action: 'fetch_products', brand: brand, model: model, sortBy: currentSortBy, sortOrder: currentSortOrder },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        let rows = '';
                        if(response.data.length > 0) {
                            response.data.forEach(product => {
                                rows += `
                                    <tr>
                                        <td>${product.id}</td>
                                        <td>${product.product_name}</td>
                                        <td>${product.brand_name || 'N/A'}</td>
                                        <td>${product.model || 'N/A'}</td>
                                        <td>${product.quantity}</td>
                                        <td>₹${parseFloat(product.retail_price || 0).toFixed(2)}</td>
                                        <td>
                                            <button class="btn btn-sm btn-info edit-btn" data-id="${product.id}"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger delete-btn" data-id="${product.id}"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>`;
                            });
                        } else {
                            rows = '<tr><td colspan="7" class="text-center">No products found.</td></tr>';
                        }
                        $('#productTableBody').html(rows);
                    } else { showMessage('Error', response.message || 'Could not load data.', false); }
                },
                error: function() { showMessage('Error', 'An error occurred while fetching products.', false); }
            });
        }

        function populateBrandFilter() {
            let options = '<option value="">All Brands</option>';
            Object.keys(carBrandsAndModels).sort().forEach(brand => {
                options += `<option value="${brand}">${brand}</option>`;
            });
            $('#brandFilter').html(options);
        }

        function populateModelFilter(brandName) {
            let options = '<option value="">All Models</option>';
            if (brandName && carBrandsAndModels[brandName]) {
                carBrandsAndModels[brandName].forEach(model => {
                    options += `<option value="${model}">${model}</option>`;
                });
                $('#modelFilter').prop('disabled', false);
            } else {
                $('#modelFilter').prop('disabled', true);
            }
            $('#modelFilter').html(options);
        }

        function populateBrandsForModal(selectElementId) {
            let options = '<option value="">-- Select Brand --</option>';
            Object.keys(carBrandsAndModels).sort().forEach(brand => {
                options += `<option value="${brand}">${brand}</option>`;
            });
            options += '<option value="new_brand">-- Other (Add New Brand) --</option>';
            $(selectElementId).html(options);
        }

        function populateModelsForModal(brandName, selectElementId) {
            let options = '<option value="">-- Select Model --</option>';
            if (brandName && carBrandsAndModels[brandName]) {
                carBrandsAndModels[brandName].sort().forEach(model => {
                    options += `<option value="${model}">${model}</option>`;
                });
            }
            options += '<option value="new_model">-- Other (Add New Model) --</option>';
            $(selectElementId).html(options);
        }

        // --- Initial Load ---
        fetchProducts();
        populateBrandFilter();

        // --- Event Listeners ---
        $('#brandFilter').on('change', function() { populateModelFilter($(this).val()); });
        $('#applyFiltersBtn').on('click', fetchProducts);
        $('#resetFiltersBtn').on('click', function() {
            $('#brandFilter').val('');
            populateModelFilter(''); 
            fetchProducts(); 
        });

        $('.sortable').on('click', function() {
            const sortBy = $(this).data('sort');
            if (currentSortBy === sortBy) {
                currentSortOrder = (currentSortOrder === 'ASC') ? 'DESC' : 'ASC';
            } else {
                currentSortBy = sortBy; currentSortOrder = 'ASC';
            }
            $('.sortable i').removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
            $(this).find('i').removeClass('fa-sort').addClass((currentSortOrder === 'ASC') ? 'fa-sort-up' : 'fa-sort-down');
            fetchProducts();
        });

        $('#productTableBody').on('click', '.delete-btn', function() {
            const productId = $(this).data('id');
            if (confirm('Are you sure you want to delete this product?')) {
                $.ajax({
                    url: 'product-actions.php', type: 'POST', data: { action: 'delete_product', id: productId }, dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            showMessage('Success', response.message, true);
                            fetchProducts(); 
                        } else { showMessage('Error', response.message, false); }
                    },
                    error: function() { showMessage('Error', 'An error occurred.', false); }
                });
            }
        });

        // --- Add Product Modal ---
        $('#addProductModal').on('show.bs.modal', function() {
            $('#addProductForm')[0].reset();
            populateBrandsForModal('#brand_select');
            $('#brand_manual, #model_manual').hide().val('');
            $('#model_select').html('<option value="">-- Select Brand First --</option>').show();
        });

        $('#brand_select').on('change', function() {
            const selected = $(this).val();
            $('#model_manual').hide().val('');
            if (selected === 'new_brand') {
                $('#brand_manual').show().focus();
                $('#model_manual').show();
                $('#model_select').hide();
            } else {
                $('#brand_manual').hide().val('');
                $('#model_select').show();
                populateModelsForModal(selected, '#model_select');
            }
        });

        $('#model_select').on('change', function() {
            if ($(this).val() === 'new_model') $('#model_manual').show().focus();
            else $('#model_manual').hide().val('');
        });

        $('#saveProductBtn').on('click', function() {
            const form = $('#addProductForm');
            if (form.find('#product_name').val().trim() === '') { showMessage('Error', 'Product Name is required.', false); return; }
            if ($('#brand_select').val() === '') { showMessage('Error', 'Please select a brand.', false); return; }

            $.ajax({
                url: 'product-actions.php', type: 'POST', data: form.serialize(), dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#addProductModal').modal('hide');
                        showMessage('Success', response.message, true);
                        updateLocalData(response.newBrand, response.newModel, response.associatedBrand);
                        fetchProducts();
                    } else { showMessage('Error', response.message, false); }
                },
                error: function() { showMessage('Error', 'An unexpected error occurred.', false); }
            });
        });

        // --- Edit Product Modal ---
        $('#productTableBody').on('click', '.edit-btn', function() {
            const productId = $(this).data('id');
            $.ajax({
                url: 'product-actions.php', type: 'GET', data: { action: 'fetch_single_product', id: productId }, dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        const product = response.data;
                        $('#edit_product_id').val(product.id);
                        $('#edit_product_name').val(product.product_name);
                        $('#edit_product_description').val(product.product_description);
                        $('#edit_product_location').val(product.product_location);
                        $('#edit_quantity').val(product.quantity);
                        $('#edit_dealer_price').val(product.dealer_price);
                        $('#edit_retail_price').val(product.retail_price);
                        $('#edit_cgst').val(product.cgst);
                        $('#edit_sgst').val(product.sgst);

                        populateBrandsForModal('#edit_brand_select');
                        $('#edit_brand_select').val(product.brand_name);
                        
                        populateModelsForModal(product.brand_name, '#edit_model_select');
                        $('#edit_model_select').val(product.model);
                        
                        $('#edit_brand_manual, #edit_model_manual').hide().val('');
                        $('#editProductModal').modal('show');
                    } else { showMessage('Error', response.message, false); }
                },
                error: function() { showMessage('Error', 'Failed to retrieve product details.', false); }
            });
        });

        $('#edit_brand_select').on('change', function() {
            const selected = $(this).val();
            $('#edit_model_manual').hide().val('');
            if (selected === 'new_brand') {
                $('#edit_brand_manual').show().focus();
                $('#edit_model_manual').show();
                $('#edit_model_select').hide();
            } else {
                $('#edit_brand_manual').hide().val('');
                $('#edit_model_select').show();
                populateModelsForModal(selected, '#edit_model_select');
            }
        });

        $('#edit_model_select').on('change', function() {
            if ($(this).val() === 'new_model') $('#edit_model_manual').show().focus();
            else $('#edit_model_manual').hide().val('');
        });

        $('#updateProductBtn').on('click', function() {
             const form = $('#editProductForm');
             if (form.find('#edit_product_name').val().trim() === '') { showMessage('Error', 'Product Name is required.', false); return; }
             $.ajax({
                url: 'product-actions.php', type: 'POST', data: form.serialize(), dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#editProductModal').modal('hide');
                        showMessage('Success', response.message, true);
                        updateLocalData(response.newBrand, response.newModel, response.associatedBrand);
                        fetchProducts();
                    } else { showMessage('Error', response.message, false); }
                },
                error: function() { showMessage('Error', 'An unexpected error occurred.', false); }
            });
        });
    });
    </script>
</body>
</html>