<?php
// Always start the session at the very beginning of the script.
session_start();

// Now include the configuration file.
require_once 'config.php'; // PDO connection

// Check for user session *after* starting it.
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Check for and process flash messages
$flash_message_json = 'null';
if (isset($_SESSION['flash_message'])) {
    $flash_message_json = json_encode($_SESSION['flash_message']);
    unset($_SESSION['flash_message']); // Unset the message so it doesn't show again
}

// Initialize variables
$all_customers = [];
$customers_by_id_json = '{}';

// Fetch all existing customers to populate the dropdown
if (isset($pdo)) {
    $stmt = $pdo->query("SELECT id, customer_name, customer_address, customer_contact, gst_number, reg_no FROM customers ORDER BY customer_name ASC");
    $all_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create an associative array for easy JavaScript lookup
    $customers_by_id = [];
    foreach ($all_customers as $c) {
        $customers_by_id[$c['id']] = $c;
    }
    $customers_by_id_json = json_encode($customers_by_id);
}

// Get customer_id for pre-selection (from URL or a previous step)
$customer_id_to_select = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

    $customer_name = $_POST['customer_name'];
    $customer_address = $_POST['customer_address'];
    $customer_contact = $_POST['customer_contact'];
    $gst_number = $_POST['gst_number'];
    $reg_no = $_POST['reg_no'];

    try {
        if (isset($pdo)) {
            if ($customer_id > 0) {
                // Update existing customer
                $// --- CORRECTED FOR SQLITE ---
                $stmt = $pdo->prepare("UPDATE customers 
                    SET customer_name=?, customer_address=?, customer_contact=?, gst_number=?, reg_no=?, updated_at=CURRENT_TIMESTAMP 
                    WHERE id=?");
                $stmt->execute([$customer_name, $customer_address, $customer_contact, $gst_number, $reg_no, $customer_id]);
                $message = 'Customer details updated successfully!';
            } else {
                // Insert new customer
                $stmt = $pdo->prepare("INSERT INTO customers 
                    (customer_name, customer_address, customer_contact, gst_number, reg_no, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $stmt->execute([$customer_name, $customer_address, $customer_contact, $gst_number, $reg_no]);
                $customer_id = $pdo->lastInsertId(); // Get the new ID
                $message = 'New customer added successfully!';
            }

            // Set success flash message
            $_SESSION['flash_message'] = [
                'status' => 'success',
                'message' => $message,
                'customer_id' => $customer_id // Pass the ID for redirection
            ];
        }
    } catch (PDOException $e) {
        // Set error flash message
        $_SESSION['flash_message'] = [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }

    // Redirect back to the same page to display the modal
    header("Location: " . $_SERVER['PHP_SELF'] . "?customer_id=" . $customer_id);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Details</title>
  
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
    /* Minor style adjustment for Select2 height consistency */
    .select2-container .select2-selection--single {
        height: calc(1.5em + .75rem + 2px); 
    }
  </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
  <div class="app-wrapper">
    <?php include 'header.php'; ?>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content: Using AdminLTE's standard structure for responsiveness -->
    <main class="app-main">
        <div class="app-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between my-4">
                            <h4 class="mb-sm-0">Customer Information</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Customers</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Centered and Sized for Responsiveness -->
                <div class="row justify-content-center">
                    <div class="col-lg-8 col-md-10 col-sm-12">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Add or Edit Customer Details</h3>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="customer_id" id="customer_id_hidden" value="<?= htmlspecialchars($customer_id_to_select) ?>">
                                <div class="card-body">
                                    <!-- Customer Selection -->
                                    <div class="mb-2">
                                        <label for="customer_select" class="form-label">Select Existing Customer or Add New</label>
                                        <select id="customer_select" class="form-select">
                                            <option value="0">-- Add New Customer --</option>
                                            <?php foreach ($all_customers as $customer): ?>
                                                <option value="<?= $customer['id'] ?>" <?= ($customer['id'] == $customer_id_to_select) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($customer['customer_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <hr/>

                                    <!-- Customer Details -->
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <label for="customer_name" class="form-label">Customer Name</label>
                                            <input type="text" id="customer_name" name="customer_name" class="form-control" required>
                                        </div>
                                        <div class="col-12 mb-3">
                                            <label for="customer_address" class="form-label">Address</label>
                                            <textarea id="customer_address" name="customer_address" class="form-control" rows="3" required></textarea>
                                        </div>
                                        
                                        <!-- This row will stack on mobile and be side-by-side on larger screens -->
                                        <div class="col-md-6 mb-3">
                                            <label for="customer_contact" class="form-label">Contact Number</label>
                                            <input type="text" id="customer_contact" name="customer_contact" class="form-control" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="gst_number" class="form-label">GST Number</label>
                                            <input type="text" id="gst_number" name="gst_number" class="form-control">
                                        </div>

                                        <div class="col-12 mb-3">
                                            <label for="reg_no" class="form-label">Registration Number</label>
                                            <input type="text" id="reg_no" name="reg_no" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer d-flex justify-content-end gap-2">
                                    <a href="invoice-list.php" class="btn btn-secondary">Back to Invoices</a>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right me-2"></i>Save and Continue</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
    <!-- End Main Content -->

    <?php include 'footer.php'; ?>
  </div>

  <!-- Status Modal (No changes needed here) -->
  <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="statusModalHeader"><h5 class="modal-title" id="statusModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" id="statusModalBody"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
  </div>

  <!-- Core JS -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/browser/overlayscrollbars.browser.es6.min.js"></script>
  <!-- AdminLTE Template JS -->
  <script src="./js/adminlte.js"></script>
  <!-- Select2 JS -->
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>                                   
  <script>
    $(document).ready(function() {
        // --- Customer Selection Logic ---
        const allCustomersData = <?= $customers_by_id_json ?>;
        
        $('#customer_select').select2({ theme: "bootstrap-5", placeholder: 'Search for a customer...' });

        function populateForm(customerId) {
            const id = parseInt(customerId, 10);
            $('#customer_id_hidden').val(id);

            if (id > 0 && allCustomersData[id]) {
                const c = allCustomersData[id];
                $('#customer_name').val(c.customer_name);
                $('#customer_address').val(c.customer_address);
                $('#customer_contact').val(c.customer_contact);
                $('#gst_number').val(c.gst_number);
                $('#reg_no').val(c.reg_no);
                $('#invoice_type').val(c.invoice_type);
                $('#with_tax').val(c.with_tax);
            } else {
                $('#customer_name').val(''); $('#customer_address').val(''); $('#customer_contact').val('');
                $('#gst_number').val(''); $('#invoice_type').val('Invoice'); $('#with_tax').val('Yes');
            }
        }

        $('#customer_select').on('change', function() { populateForm($(this).val()); });

        const initialCustomerId = $('#customer_select').val();
        populateForm(initialCustomerId);

        // --- Flash Message & Modal Logic ---
        const flashMessage = <?= $flash_message_json ?>;
        
        if (flashMessage) {
            const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
            const modalHeader = $('#statusModalHeader');
            const modalLabel = $('#statusModalLabel');
            const modalBody = $('#statusModalBody');

            // Clear previous classes
            modalHeader.removeClass('bg-success bg-danger text-white');

            if (flashMessage.status === 'success') {
                modalHeader.addClass('bg-success text-white');
                modalLabel.text('Success');
                modalBody.text(flashMessage.message);
                
                // Show modal and then redirect after 2 seconds
                statusModal.show();
                setTimeout(() => {
                    window.location.href = `invoice-add-products.php?customer_id=${flashMessage.customer_id}`;
                }, 2000);

            } else if (flashMessage.status === 'error') {
                modalHeader.addClass('bg-danger text-white');
                modalLabel.text('Error');
                modalBody.text(flashMessage.message);
                statusModal.show(); // Show modal and wait for user to close
            }
        }
    });
  </script>
</body>
</html>