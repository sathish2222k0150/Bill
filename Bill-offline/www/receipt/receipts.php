<?php
require '../config.php';
session_start();
$error_message = '';

// Handle Receipt Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_receipt'])) {
    try {
        $receipt_number = "RCPT-" . date("YmdHis");
        $sql = "INSERT INTO receipts (receipt_number, customer_name, amount, payment_mode, amount_in_words, vehicle_no, customer_phone, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $receipt_number,
            $_POST['customer_name_text'],
            $_POST['amount'],
            $_POST['payment_mode'],
            $_POST['amount_in_words'],
            $_POST['vehicle_no'],
            $_POST['customer_phone'],
            $_POST['notes']
        ]);

        // --- KEY CHANGE: Redirect to preview page on success ---
        header("Location: preview-receipt.php?receipt_number=" . urlencode($receipt_number));
        exit(); // Stop script execution after redirect

    } catch (PDOException $e) {
        $error_message = "Error saving receipt: " . $e->getMessage();
    }
}

// Fetch all customers to populate the dropdown
try {
    $customer_stmt = $pdo->query("SELECT id, customer_name, customer_contact, reg_no FROM customers ORDER BY customer_name ASC");
    $customers = $customer_stmt->fetchAll();
} catch (PDOException $e) {
    $customers = [];
    $error_message = "Could not fetch customers: " . $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Receipt</title>
    <!-- AdminLTE, Bootstrap, and Select2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/adminlte.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style> .select2-container--open { z-index: 1056; } </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
    <?php include '../header.php'; ?>
    <?php include '../sidebar.php'; ?>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6"><h3 class="mb-0">Add New Receipt</h3></div>
                    <div class="col-sm-6"><ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Add Receipt</li>
                    </ol></div>
                </div>
            </div>
        </div>
        <div class="app-content">
            <div class="container-fluid">
                <!-- Only display error messages -->
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Receipt Details</h3></div>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <div class="card-body">
                            <!-- Form content remains the same as previous step -->
                             <div class="row">
                                <div class="col-md-8">
                                    <label for="customer_id" class="form-label">Customer Name:</label>
                                    <select class="form-select" id="customer_id" name="customer_id" required>
                                        <option value="" selected disabled>Type to search for a customer...</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?= htmlspecialchars($customer['id']) ?>" data-phone="<?= htmlspecialchars($customer['customer_contact']) ?>" data-vehicle="<?= htmlspecialchars($customer['reg_no']) ?>" data-name="<?= htmlspecialchars($customer['customer_name']) ?>">
                                                <?= htmlspecialchars($customer['customer_name']) ?> (<?= htmlspecialchars($customer['customer_contact']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="customer_name_text" id="customer_name_text">
                                </div>
                                <div class="col-md-4 align-self-end">
                                    <button type="button" class="btn btn-secondary w-100" data-bs-toggle="modal" data-bs-target="#addCustomerModal"><i class="fa fa-plus"></i> Add New Customer</button>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6"><label for="customer_phone" class="form-label">Customer Phone:</label><input type="text" class="form-control" name="customer_phone" id="customer_phone"></div>
                                <div class="col-md-6"><label for="vehicle_no" class="form-label">Vehicle No:</label><input type="text" class="form-control" name="vehicle_no" id="vehicle_no"></div>
                            </div>
                            <hr>
                            <div class="row mt-3">
                                <div class="col-md-6"><label for="amount" class="form-label">Amount:</label><input type="number" step="0.01" name="amount" id="amount" class="form-control" required></div>
                                <div class="col-md-6"><label for="payment_mode" class="form-label">Payment Mode:</label><select name="payment_mode" class="form-select" required><option value="Cash">Cash</option><option value="UPI">UPI</option><option value="Card">Card</option></select></div>
                            </div>
                            <div class="row mt-3"><div class="col-md-12"><label for="amount_in_words" class="form-label">Amount in Words:</label><input type="text" name="amount_in_words" id="amount_in_words" class="form-control" required readonly></div></div>
                            <div class="row mt-3"><div class="col-md-12"><label for="notes" class="form-label">Notes:</label><textarea name="notes" class="form-control" rows="3"></textarea></div></div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="save_receipt" class="btn btn-success">Save and Preview</button>
                            <a href="view-receipts.php" class="btn btn-info">View All Receipts</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Add Customer Modal (same as before) -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Add New Customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <form id="addCustomerForm">
            <div id="modal-alert" class="alert d-none"></div>
            <div class="mb-3"><label class="form-label">Customer Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="customer_name" required></div>
            <div class="mb-3"><label class="form-label">Customer Contact <span class="text-danger">*</span></label><input type="text" class="form-control" name="customer_contact" required></div>
            <div class="mb-3"><label class="form-label">Vehicle No.</label><input type="text" class="form-control" name="reg_no"></div>
            <div class="mb-3"><label class="form-label">GST No.</label><input type="text" class="form-control" name="gst_number"></div>
            <div class="mb-3"><label class="form-label">Address</label><textarea class="form-control" name="customer_address" rows="2"></textarea></div>
        </form>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" id="saveCustomerBtn">Save Customer</button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- JS includes and scripts (same as before) -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="../js/adminlte.js"></script>
<script>
$(document).ready(function() {
    const customerSelect = $('#customer_id');
    customerSelect.select2({ theme: 'bootstrap-5', placeholder: $(this).data('placeholder') });
    customerSelect.on('change', function() {
        const selectedOption = $(this).find('option:selected');
        $('#customer_phone').val(selectedOption.data('phone'));
        $('#vehicle_no').val(selectedOption.data('vehicle'));
        $('#customer_name_text').val(selectedOption.data('name'));
    });
    $('#saveCustomerBtn').on('click', function() {
        const form = $('#addCustomerForm'), alertDiv = $('#modal-alert');
        if ($('#modal_customer_name').val().trim()===''||$('#modal_customer_contact').val().trim()==='') { alertDiv.removeClass('d-none alert-success').addClass('alert-danger').text('Please fill Name and Contact.'); return; }
        $.ajax({ type: 'POST', url: 'ajax-add-customer.php', data: form.serialize(), dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const newCustomer=response.customer, newOption=new Option(`${newCustomer.name} (${newCustomer.phone})`,newCustomer.id,true,true);
                    $(newOption).data({phone:newCustomer.phone, vehicle:newCustomer.vehicle, name:newCustomer.name});
                    customerSelect.append(newOption).trigger('change');
                    alertDiv.removeClass('d-none alert-danger').addClass('alert-success').text(response.message);
                    setTimeout(() => { $('#addCustomerModal').modal('hide'); form[0].reset(); alertDiv.addClass('d-none'); }, 1500);
                } else { alertDiv.removeClass('d-none alert-success').addClass('alert-danger').text(response.message); }
            },
            error: function() { alertDiv.removeClass('d-none alert-success').addClass('alert-danger').text('Server communication error.'); }
        });
    });
    function numberToWords(n) { if (n === 0) return 'Zero'; var nums = ['Zero','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen']; var tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety']; function convert(num) { if (num < 20) return nums[num]; if (num < 100) return tens[Math.floor(num/10)] + (num % 10 ? ' ' + nums[num % 10] : ''); if (num < 1000) return nums[Math.floor(num/100)] + ' Hundred' + (num % 100 ? ' and ' + convert(num % 100) : ''); if (num < 100000) return convert(Math.floor(num/1000)) + ' Thousand' + (num % 1000 ? ' ' + convert(num % 1000) : ''); if (num < 10000000) return convert(Math.floor(num/100000)) + ' Lakh' + (num % 100000 ? ' ' + convert(num % 100000) : ''); return ''; } return convert(n); }
    $('#amount').on('input', function() { var val = parseInt(this.value, 10); if (!isNaN(val) && val > 0 && val < 10000000) { $('#amount_in_words').val(numberToWords(val).trim() + ' Only'); } else { $('#amount_in_words').val(''); } });
});
</script>
</body>
</html>