<?php
require_once '../config.php';
session_start();

$receipt = null;
$error_message = '';
$id = $_GET['id'] ?? null;

if (!$id || !filter_var($id, FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid receipt ID.";
    header("Location: view-receipts.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $sql = "UPDATE receipts SET 
                    customer_name = ?, amount = ?, payment_mode = ?, 
                    amount_in_words = ?, vehicle_no = ?, customer_phone = ?, notes = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['customer_name'], $_POST['amount'], $_POST['payment_mode'],
            $_POST['amount_in_words'], $_POST['vehicle_no'], $_POST['customer_phone'],
            $_POST['notes'], $id
        ]);

        $_SESSION['success_message'] = "Receipt #" . htmlspecialchars($_POST['receipt_number']) . " updated successfully!";
        header("Location: view-receipts.php");
        exit();

    } catch (PDOException $e) {
        $error_message = "Error updating receipt: " . $e->getMessage();
    }
}

try {
    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE id = ?");
    $stmt->execute([$id]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receipt) {
        $_SESSION['error_message'] = "Receipt not found.";
        header("Location: view-receipts.php");
        exit();
    }
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Receipt</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/adminlte.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
    <?php include '../header.php'; ?>
    <?php include '../sidebar.php'; ?>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h3 class="mb-0">Edit Receipt</h3>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item"><a href="/view-receipts.php">Receipts</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="app-content">
            <div class="container-fluid">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?= $error_message ?></div>
                <?php endif; ?>

                <div class="card card-warning card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Editing Receipt #<?= htmlspecialchars($receipt['receipt_number']) ?></h3>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="receipt_number" value="<?= htmlspecialchars($receipt['receipt_number']) ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3"><label for="customer_name" class="form-label">Customer Name:</label><input type="text" id="customer_name" name="customer_name" class="form-control" value="<?= htmlspecialchars($receipt['customer_name']) ?>" required></div>
                                <div class="col-md-6 mb-3"><label for="customer_phone" class="form-label">Customer Phone:</label><input type="text" id="customer_phone" name="customer_phone" class="form-control" value="<?= htmlspecialchars($receipt['customer_phone']) ?>"></div>
                            </div>
                             <div class="row">
                                <div class="col-md-6 mb-3"><label for="amount" class="form-label">Amount:</label><input type="number" step="0.01" name="amount" id="amount" class="form-control" value="<?= htmlspecialchars($receipt['amount']) ?>" required></div>
                                <div class="col-md-6 mb-3"><label for="payment_mode" class="form-label">Payment Mode:</label><select name="payment_mode" id="payment_mode" class="form-select" required><?php foreach(['Cash','UPI','Card'] as $mode): ?><option value="<?= $mode ?>" <?= $receipt['payment_mode'] == $mode ? 'selected' : '' ?>><?= $mode ?></option><?php endforeach; ?></select></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label for="amount_in_words" class="form-label">Amount in Words:</label><input type="text" name="amount_in_words" id="amount_in_words" class="form-control" value="<?= htmlspecialchars($receipt['amount_in_words']) ?>" required readonly></div>
                                <div class="col-md-6 mb-3"><label for="vehicle_no" class="form-label">Vehicle No:</label><input type="text" id="vehicle_no" name="vehicle_no" class="form-control" value="<?= htmlspecialchars($receipt['vehicle_no']) ?>"></div>
                            </div>
                            <div class="mb-3"><label for="notes" class="form-label">Notes:</label><textarea name="notes" id="notes" class="form-control" rows="3"><?= htmlspecialchars($receipt['notes']) ?></textarea></div>
                            
                            <div class="card-footer bg-white d-flex justify-content-end">
                                <a href="view-receipts.php" class="btn btn-secondary me-auto">Cancel</a>
                                <a href="preview-receipt.php?receipt_number=<?= htmlspecialchars($receipt['receipt_number']) ?>" class="btn btn-info mx-2" target="_blank"><i class="fa fa-eye me-2"></i>Preview</a>
                                <button type="submit" class="btn btn-primary"><i class="fa fa-check me-2"></i>Update Receipt</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/adminlte.js"></script>

<script>
    function numberToWords(n) {
        if (n === 0) return 'Zero';
        var nums = ['Zero','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
        var tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
        function convert(num) {
            if (num < 20) return nums[num];
            if (num < 100) return tens[Math.floor(num/10)] + (num % 10 ? ' ' + nums[num % 10] : '');
            if (num < 1000) return nums[Math.floor(num/100)] + ' Hundred' + (num % 100 ? ' and ' + convert(num % 100) : '');
            if (num < 100000) return convert(Math.floor(num/1000)) + ' Thousand' + (num % 1000 ? ' ' + convert(num % 1000) : '');
            if (num < 10000000) return convert(Math.floor(num/100000)) + ' Lakh' + (num % 100000 ? ' ' + convert(num % 100000) : '');
            return '';
        }
        return convert(n);
    }
    document.getElementById('amount').addEventListener('input', function() {
        var val = parseInt(this.value, 10);
        if (!isNaN(val) && val >= 0 && val < 10000000) {
            document.getElementById('amount_in_words').value = numberToWords(val).trim() + ' Only';
        } else {
            document.getElementById('amount_in_words').value = '';
        }
    });
</script>

</body>
</html>