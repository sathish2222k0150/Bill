<?php
session_start();
require_once 'config.php'; // PDO connection

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get customer ID from URL to pre-select the dropdown
$customer_id_from_url = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

// --- Fetch all customers for the main dropdown ---
$stmt_customers = $pdo->query("SELECT id, customer_name, customer_address, customer_contact, gst_number, reg_no FROM customers ORDER BY customer_name ASC");
$all_customers = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch ALL invoices and group them by customer_id for JavaScript ---
$stmt_invoices = $pdo->query("SELECT id, customer_id, invoice_number FROM invoices ORDER BY id DESC");
$invoices_by_customer = [];
while ($invoice = $stmt_invoices->fetch(PDO::FETCH_ASSOC)) {
    $invoices_by_customer[$invoice['customer_id']][] = [
        'id' => $invoice['id'],
        'invoice_number' => $invoice['invoice_number']
    ];
}

// --- Prepare data as JSON for JavaScript ---
$customers_by_id_json = json_encode(array_column($all_customers, null, 'id'));
$invoices_by_customer_json = json_encode($invoices_by_customer);

// --- Flash message logic ---
$flash_message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
$flash_message_json = json_encode($flash_message);

// --- Handle POST submission for UPDATING/CREATING a customer ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = (int)$_POST['customer_id'];
    $customer_name = $_POST['customer_name'];
    $customer_address = $_POST['customer_address'];
    $customer_contact = $_POST['customer_contact'];
    $gst_number = $_POST['gst_number'] ?? '';
    $reg_no = $_POST['reg_no'] ?? '';
    $new_id = 0;

    if ($customer_id > 0) {
        // Update existing customer
        $stmt = $pdo->prepare("UPDATE customers SET customer_name = :name, customer_address = :address, customer_contact = :contact, gst_number = :gst, reg_no = :reg WHERE id = :id");
        $stmt->execute([':name' => $customer_name, ':address' => $customer_address, ':contact' => $customer_contact, ':gst' => $gst_number, ':reg' => $reg_no, ':id' => $customer_id]);
        $_SESSION['flash_message'] = ['status' => 'success', 'message' => 'Customer updated successfully!'];
    } else {
        // Insert new customer
        $stmt = $pdo->prepare("INSERT INTO customers (customer_name, customer_address, customer_contact, gst_number, reg_no) VALUES (:name, :address, :contact, :gst, :reg)");
        $stmt->execute([':name' => $customer_name, ':address' => $customer_address, ':contact' => $customer_contact, ':gst' => $gst_number, ':reg' => $reg_no]);
        $new_id = $pdo->lastInsertId();
        $_SESSION['flash_message'] = ['status' => 'success', 'message' => 'Customer added successfully!'];
    }

    // Redirect back to this page with the correct customer ID selected
    $redirect_id = $customer_id > 0 ? $customer_id : $new_id;
    header("Location: " . $_SERVER['PHP_SELF'] . "?customer_id=" . $redirect_id);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Step 1: Select Invoice to Edit</title>

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

<!-- Main Content Wrapper -->
<main class="app-main">
    <div class="app-content">
        <div class="container-fluid">
            <!-- Page Header for context and navigation -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between my-4">
                        <h4 class="mb-sm-0">Select Invoice to Edit</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="admin-dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="invoice-list.php">Invoices</a></li>
                                <li class="breadcrumb-item active">Select</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Centered, responsive container for the cards -->
            <div class="row justify-content-center">
                <div class="col-lg-8 col-md-10 col-12">
                    <!-- Card for Selecting Customer and Invoice -->
                    <div class="card card-primary">
                        <div class="card-header"><h3 class="card-title">Step 1: Select Customer & Invoice</h3></div>
                        <div class="card-body">
                            <!-- Responsive row for the dropdowns -->
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label for="customer_select" class="form-label"><strong>1. Select a Customer</strong></label>
                                    <select id="customer_select" class="form-select">
                                        <option value="0">-- Select a Customer --</option>
                                        <?php foreach ($all_customers as $customer): ?>
                                            <option value="<?= $customer['id'] ?>" <?= ($customer['id'] == $customer_id_from_url) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($customer['customer_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3" id="invoice_select_container" style="display: none;">
                                    <label for="invoice_select" class="form-label"><strong>2. Select Invoice to Edit</strong></label>
                                    <select id="invoice_select" class="form-select"></select>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-between flex-wrap gap-2">
                            <a href="invoice-list.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Back to Invoice List</a>
                            <a href="#" id="proceed_button" class="btn btn-primary disabled">Proceed to Edit <i class="fas fa-arrow-right ms-2"></i></a>
                        </div>
                    </div>

                    <!-- Customer Details Form (for editing) -->
                    <div class="card card-info mt-4" id="customer_details_card" style="display: none;">
                        <div class="card-header"><h3 class="card-title">Or, Edit Details for Selected Customer</h3></div>
                        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                            <input type="hidden" name="customer_id" id="customer_id_hidden">
                            <div class="card-body">
                                <div class="mb-3"><label for="customer_name" class="form-label">Customer Name</label><input type="text" id="customer_name" name="customer_name" class="form-control" required></div>
                                <div class="mb-3"><label for="customer_address" class="form-label">Address</label><textarea id="customer_address" name="customer_address" class="form-control" rows="3"></textarea></div>
                                <div class="mb-3"><label for="customer_contact" class="form-label">Contact</label><input type="text" id="customer_contact" name="customer_contact" class="form-control"></div>
                                <div class="mb-3"><label for="gst_number" class="form-label">GST Number</label><input type="text" id="gst_number" name="gst_number" class="form-control"></div>
                                <div class="mb-3"><label for="reg_no" class="form-label">Registration Number</label><input type="text" id="reg_no" name="reg_no" class="form-control"></div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-info"><i class="fas fa-save me-2"></i> Update Customer Info</button>
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

<!-- Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="statusModalHeader">
                <h5 class="modal-title" id="statusModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="statusModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="./js/adminlte.js"></script>
<script>
$(document).ready(function() {
    const allCustomersData = <?= $customers_by_id_json ?>;
    const invoicesByCustomer = <?= $invoices_by_customer_json ?>;
    
    $('#customer_select, #invoice_select').select2({ theme: "bootstrap-5" });

    function handleCustomerChange() {
        const customerId = $('#customer_select').val();
        const invoiceSelect = $('#invoice_select');
        const invoiceContainer = $('#invoice_select_container');
        const proceedButton = $('#proceed_button');
        const customerDetailsCard = $('#customer_details_card');

        // Reset everything first
        invoiceSelect.html('<option value="0">-- Select an Invoice --</option>').val(0).trigger('change.select2');
        invoiceContainer.hide();
        proceedButton.addClass('disabled').attr('href', '#');
        customerDetailsCard.hide();
        
        if (customerId > 0) {
            // Populate and show the customer details form
            const c = allCustomersData[customerId];
            if (c) {
                $('#customer_id_hidden').val(customerId);
                $('#customer_name').val(c.customer_name);
                $('#customer_address').val(c.customer_address);
                $('#customer_contact').val(c.customer_contact);
                $('#gst_number').val(c.gst_number);
                $('#reg_no').val(c.reg_no);
                customerDetailsCard.show();
            }
            
            // Populate the invoice dropdown if invoices exist for this customer
            if (invoicesByCustomer[customerId] && invoicesByCustomer[customerId].length > 0) {
                invoicesByCustomer[customerId].forEach(invoice => {
                    invoiceSelect.append(new Option(invoice.invoice_number, invoice.id, false, false));
                });
                invoiceContainer.show();
            } else {
                invoiceSelect.html('<option value="0">-- No invoices found for this customer --</option>');
                invoiceContainer.show();
            }
        }
    }

    function handleInvoiceChange() {
        const invoiceId = $('#invoice_select').val();
        const proceedButton = $('#proceed_button');
        
        if (invoiceId > 0) {
            proceedButton.removeClass('disabled').attr('href', `invoice-edit-products.php?invoice_id=${invoiceId}`);
        } else {
            proceedButton.addClass('disabled').attr('href', '#');
        }
    }

    // Event Listeners
    $('#customer_select').on('change', handleCustomerChange);
    $('#invoice_select').on('change', handleInvoiceChange);

    // Initial load: if a customer was pre-selected via URL, trigger the change handler
    if ($('#customer_select').val() > 0) {
        $('#customer_select').trigger('change');
    }

    // Handle flash messages from customer updates
    const flashMessage = <?= $flash_message_json ?>;
    if (flashMessage) {
        const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
        const modalHeader = $('#statusModalHeader');
        const modalLabel = $('#statusModalLabel');
        const modalBody = $('#statusModalBody');
        modalHeader.removeClass('bg-success bg-danger text-white');

        if (flashMessage.status === 'success') {
            modalHeader.addClass('bg-success text-white');
            modalLabel.text('Success');
        } else {
            modalHeader.addClass('bg-danger text-white');
            modalLabel.text('Error');
        }
        modalBody.text(flashMessage.message);
        statusModal.show();
    }
});
</script>
</body>
</html>