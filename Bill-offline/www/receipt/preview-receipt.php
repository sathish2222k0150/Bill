<?php
require '../config.php';
session_start();
$receipt = null;
$error_message = '';

if (isset($_GET['receipt_number']) && !empty($_GET['receipt_number'])) {
    try {
        $receipt_number = $_GET['receipt_number'];
        $sql = "SELECT * FROM receipts WHERE receipt_number = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$receipt_number]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receipt) {
            $error_message = "No receipt found with the number: " . htmlspecialchars($receipt_number);
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
} else {
    $error_message = "No receipt number provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SDS Automotive - <?= htmlspecialchars($receipt['receipt_number'] ?? 'Error') ?></title>
    
    <link rel="stylesheet" id="bootstrap-css" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/adminlte.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <style>
        .receipt-container {
            max-width: 850px;
            margin: 20px auto;
        }
        
        .receipt-label {
            width: 180px;
            flex-shrink: 0;
            font-weight: bold;
        }
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
    <?php include '../header.php'; ?>
    <?php include '../sidebar.php'; ?>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6"></div>
                    <div class="col-sm-6"><ol class="breadcrumb float-sm-end"><li class="breadcrumb-item"><a href="dashboard.php">Home</a></li><li class="breadcrumb-item active" aria-current="page">Preview</li></ol></div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                <div class="receipt-container" id="printableArea">
                    <?php if ($receipt): ?>
                    <div class="card border-danger border-2 receipt-card">
                        <div class="card-header bg-white p-4 text-center">
                             <img src="../assets/sds.jpg" alt="Logo" style="max-width: 100px;" class="border border-danger border-2 p-1 mb-3">
                             <h3 class="mb-0 text-danger fw-bold">Cash Receipt</h3>
                        </div>
                        <div class="card-body p-4 p-md-5">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex px-0"><strong class="receipt-label">Receipt Number:</strong> <span><?= htmlspecialchars($receipt['receipt_number']) ?></span></li>
                                <li class="list-group-item d-flex px-0"><strong class="receipt-label">Date:</strong> <span><?= htmlspecialchars(date('d-m-Y', strtotime($receipt['created_at']))) ?></span></li>
                                <li class="list-group-item d-flex px-0"><strong class="receipt-label">Received From:</strong> <span><?= htmlspecialchars($receipt['customer_name']) ?></span></li>
                                <li class="list-group-item d-flex px-0"><strong class="receipt-label">Customer Phone:</strong> <span><?= htmlspecialchars($receipt['customer_phone']) ?></span></li>
                                <li class="list-group-item d-flex px-0"><strong class="receipt-label">Vehicle No:</strong> <span><?= htmlspecialchars($receipt['vehicle_no']) ?></span></li>
                                <li class="list-group-item d-flex px-0"><strong class="receipt-label">Payment Mode:</strong> <span><?= htmlspecialchars($receipt['payment_mode']) ?></span></li>
                                <li class="list-group-item d-flex px-0"><strong class="receipt-label">Notes / For:</strong> <span><?= nl2br(htmlspecialchars($receipt['notes'])) ?></span></li>
                                <li class="list-group-item d-flex px-0 bg-light fw-bold mt-3 p-3">
                                    <strong class="receipt-label">Amount:</strong> 
                                    <span>₹<?= number_format((float)$receipt['amount'], 2) ?> (<?= htmlspecialchars($receipt['amount_in_words']) ?>)</span>
                                </li>
                            </ul>
                            <div class="text-end mt-5">
                                <p class="mb-0">_________________________</p>
                                <p>Received by</p>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">Error</h4>
                        <p><?= $error_message ?></p>
                        <hr>
                        <a href="receipts.php" class="btn btn-danger">Go Back</a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="text-center mt-4 mb-4">
                    <button class="btn btn-danger" id="printButton"><i class="fa fa-print me-2"></i>Print / Download</button>
                    <a href="/receipts.php" class="btn btn-secondary"><i class="fa fa-plus me-2"></i>Add Another Receipt</a>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/adminlte.js"></script>

<script>
    document.getElementById('printButton').addEventListener('click', function() {
        const printContents = document.getElementById('printableArea').innerHTML;
        const bootstrapLink = document.getElementById('bootstrap-css').href;

        const printWindow = window.open('', '', 'height=800,width=800');
        
        printWindow.document.write('<html><head><title>Receipt</title>');
        printWindow.document.write('<link rel="stylesheet" href="' + bootstrapLink + '">');
        printWindow.document.write(`
            <style>
                body {
                    padding: 30px;
                    display: flex;
                    justify-content: center;
                    align-items: flex-start;
                }
                .receipt-card {
                    max-width: 750px;
                    width: 100%;
                }
                .receipt-label {
                    width: 180px;
                    flex-shrink: 0;
                    font-weight: bold;
                }
                @page {
                    size: auto;
                    margin: 0;
                }
            </style>
        `);
        printWindow.document.write('</head><body>');
        printWindow.document.write(printContents);
        printWindow.document.write('</body></html>');
        
        printWindow.document.close();
        
        setTimeout(() => {
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }, 500);
    });
</script>

</body>
</html>