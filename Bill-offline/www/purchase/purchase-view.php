<?php
require_once __DIR__ . '/../config.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// 1. VALIDATE AND GET PURCHASE ID
// ----------------------------------------------------
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid Purchase ID provided.";
    header('Location: purchase-reports.php');
    exit;
}
$purchase_id = (int)$_GET['id'];


// 2. FETCH DATA FROM DATABASE
// ----------------------------------------------------
try {
    // Fetch main purchase details along with vendor information
    // Using LEFT JOIN is safer in case a vendor record is deleted
    $stmt = $pdo->prepare(
        "SELECT p.*, v.name as vendor_name, v.gstin as vendor_gstin 
         FROM purchases p 
         LEFT JOIN vendors v ON p.vendor_id = v.vendor_id 
         WHERE p.id = ?"
    );
    $stmt->execute([$purchase_id]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no purchase record is found, redirect with an error
    if (!$purchase) {
        $_SESSION['error'] = "Purchase bill with ID #$purchase_id not found.";
        header('Location: purchase-reports.php');
        exit;
    }

    // Fetch all associated items for this purchase
    $items_stmt = $pdo->prepare("SELECT * FROM purchase_items WHERE purchase_id = ? ORDER BY id ASC");
    $items_stmt->execute([$purchase_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Handle any database errors
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: purchase-reports.php');
    exit;
}


// 3. HELPER FUNCTION - CONVERT NUMBER TO WORDS (INDIAN CURRENCY)
// ----------------------------------------------------
function numberToWords(float $number): string {
    $hyphen      = '-';
    $conjunction = ' and ';
    $separator   = ', ';
    $negative    = 'negative ';
    $decimal     = ' point ';
    $dictionary  = [
        0                   => 'zero',
        1                   => 'one',
        2                   => 'two',
        3                   => 'three',
        4                   => 'four',
        5                   => 'five',
        6                   => 'six',
        7                   => 'seven',
        8                   => 'eight',
        9                   => 'nine',
        10                  => 'ten',
        11                  => 'eleven',
        12                  => 'twelve',
        13                  => 'thirteen',
        14                  => 'fourteen',
        15                  => 'fifteen',
        16                  => 'sixteen',
        17                  => 'seventeen',
        18                  => 'eighteen',
        19                  => 'nineteen',
        20                  => 'twenty',
        30                  => 'thirty',
        40                  => 'forty',
        50                  => 'fifty',
        60                  => 'sixty',
        70                  => 'seventy',
        80                  => 'eighty',
        90                  => 'ninety',
        100                 => 'hundred',
        1000                => 'thousand',
        100000              => 'lakh',
        10000000            => 'crore'
    ];

    if (!is_numeric($number)) {
        return false;
    }

    if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
        // overflow
        trigger_error('numberToWords only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX, E_USER_WARNING);
        return false;
    }

    if ($number < 0) {
        return $negative . numberToWords(abs($number));
    }

    $string = $fraction = null;

    if (strpos($number, '.') !== false) {
        list($number, $fraction) = explode('.', $number);
    }

    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens   = ((int) ($number / 10)) * 10;
            $units  = $number % 10;
            $string = $dictionary[$tens];
            if ($units) {
                $string .= $hyphen . $dictionary[$units];
            }
            break;
        case $number < 1000:
            $hundreds  = $number / 100;
            $remainder = $number % 100;
            $string = $dictionary[(int)$hundreds] . ' ' . $dictionary[100];
            if ($remainder) {
                $string .= $conjunction . numberToWords($remainder);
            }
            break;
        default:
            $baseUnit = pow(1000, floor(log($number, 1000)));
            // Handle Indian numbering system (Lakhs, Crores)
            if ($number >= 10000000) { // Crores
                $baseUnit = 10000000;
            } elseif ($number >= 100000) { // Lakhs
                $baseUnit = 100000;
            } elseif ($number >= 1000) { // Thousands
                $baseUnit = 1000;
            }
            $numBaseUnits = (int) ($number / $baseUnit);
            $remainder = $number % $baseUnit;
            $string = numberToWords($numBaseUnits) . ' ' . $dictionary[$baseUnit];
            if ($remainder) {
                $string .= $remainder < 100 ? $conjunction : $separator;
                $string .= numberToWords($remainder);
            }
            break;
    }

    $words = 'Rupees ' . ucwords($string);
    if (null !== $fraction && is_numeric($fraction)) {
        $words .= $decimal;
        $words .= numberToWords((int)$fraction);
    }
    return $words . ' Only';
}

$grand_total_words = numberToWords($purchase['total_amount']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Invoice - <?= htmlspecialchars($purchase['bill_no']) ?> - SDS Automotive</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="../css/adminlte.css" />

    <style>
        .invoice-container {
            max-width: 850px;
            margin: 0 auto; /* Centered within the main content area */
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            font-size: 0.9rem;
        }
        .invoice-header h2 { font-size: 1.5rem; color: red; }
        .invoice-header h3 { font-size: 1.2rem; color: red; }
        address { margin-bottom: 0; font-style: normal; line-height: 1.5; }
        .table-bordered { border-color: #dee2e6; }
        .table tfoot td { background-color: #f8f9fa; }

        /* Styles for Printing */
        @media print {
            body { 
                background: #fff !important; 
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important;
                font-size: 9pt !important;
            }
            /* Hide AdminLTE UI elements */
            .app-header, .app-sidebar, .action-buttons-container, .main-footer { 
                display: none !important; 
            }
            .app-main {
                margin-left: 0 !important;
            }
            .invoice-container {
                width: 100%;
                max-width: 100%;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
            }
            .card-body {
                padding: 1.5cm 1cm !important; /* Standard A4 margins */
            }
            .invoice-header.mb-3, .row.mb-3 { margin-bottom: 1rem !important; }
            .table { font-size: 8pt !important; }
            .table td, .table th { padding: 0.2rem 0.3rem !important; }
            .table small { font-size: 7pt !important; font-weight: normal; }
            .signatures { page-break-inside: avoid; margin-top: 2rem !important; }
        }
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
  <div class="app-wrapper">
    <?php include '../header.php'; ?>
    <?php include '../sidebar.php'; ?>
    
    <main class="app-main">
        <div class="container-fluid py-4">

            <!-- Action Buttons (Non-Printable) -->
            <div class="action-buttons-container d-flex justify-content-center gap-2 mb-4">
                <a href="purchase-reports.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Reports</a>
                <a href="purchase-edit.php?id=<?= $purchase_id ?>" class="btn btn-warning"><i class="bi bi-pencil-square"></i> Edit</a>
                <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print Invoice</button>
            </div>

            <!-- Printable Invoice Area -->
            <div class="invoice-container">
                <div class="card border-0">
                    <div class="card-body p-4 p-md-5">
                        
                        <header class="row invoice-header mb-3">
                            <div class="col-sm-4">
                                <h2 class="mb-1">SDS Automotive</h2>
                                <p class="mb-2">9842845A5, 982313B32<br>administer2019@gmail.com</p>
                                <strong>Business Address</strong>
                                <address class="mt-1">
                                    karaikadu, sipcot post<br>
                                    cuddalore to virudhachalam<br>
                                    main road, cuddalore - 607005
                                </address>
                            </div>
                            <div class="col-4 text-center">
                            <img src="../assets/sds.jpg" alt="SDS Automotive" style="max-height:80px;">
                            </div>
                            <div class="col-sm-4 text-sm-end">
                                <h3 class="mb-3">Purchase Invoice</h3>
                                <p class="mb-1"><strong>GST NO:</strong> 33KNYPS2440P1ZW</p>
                                <p class="mb-1"><strong>Date:</strong> <?= htmlspecialchars(date("d-m-Y", strtotime($purchase['date']))) ?></p>
                                <p class="mb-1"><strong>Bill No:</strong> <?= htmlspecialchars($purchase['bill_no']) ?></p>
                            </div>
                        </header>

                        <section class="row mb-3 align-items-center">
                            <div class="col-sm-6">
                                <p class="mb-0"><strong>Purpose:</strong> <?= htmlspecialchars($purchase['purpose']) ?><br>
                                <?php if ($purchase['purpose'] === 'Vehicle'): ?>
                                <strong>Vehicle No:</strong> <?= htmlspecialchars($purchase['vehicle_reg_no']) ?><br>
                                <strong>Model:</strong> <?= htmlspecialchars($purchase['vehicle_model']) ?>
                                <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-sm-6 text-sm-end">
                               <p class="mb-0"><strong>Vendor:</strong> <?= htmlspecialchars($purchase['vendor_name']) ?><br>
                                <strong>GSTIN:</strong> <?= htmlspecialchars($purchase['vendor_gstin'] ?? 'N/A') ?></p>
                            </div>
                        </section>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width: 4%;">#</th>
                                        <th style="width: 24%;">Description</th>
                                        <th class="text-end" style="width: 10%;">MRP</th>
                                        <th class="text-center" style="width: 5%;">Qty</th>
                                        <th class="text-center" style="width: 8%;">Discount</th>
                                        <th class="text-end" style="width: 11%;">Taxable</th>
                                        <th class="text-end" style="width: 12%;">CGST</th>
                                        <th class="text-end" style="width: 12%;">SGST</th>
                                        <th class="text-end" style="width: 14%;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ($items as $index => $item): 
                                        $subtotal = $item['mrp'] * $item['qty'];
                                        $discount_amount = $subtotal * ($item['discount'] / 100);
                                        $taxable_amount = $subtotal - $discount_amount;
                                        $cgst_amount = $taxable_amount * ($item['cgst_rate'] / 100);
                                        $sgst_amount = $taxable_amount * ($item['sgst_rate'] / 100);
                                        $item_total = $taxable_amount + $cgst_amount + $sgst_amount;
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($item['description']) ?></td>
                                        <td class="text-end"><?= number_format($item['mrp'], 2) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($item['qty']) ?></td>
                                        <td class="text-center"><?= number_format($item['discount'], 2) ?>%</td>
                                        <td class="text-end"><?= number_format($taxable_amount, 2) ?></td>
                                        <td class="text-end">
                                            <span class="d-block"><?= number_format($cgst_amount, 2) ?></span>
                                            <small class="text-muted">(<?= number_format($item['cgst_rate'], 2) ?>%)</small>
                                        </td>
                                        <td class="text-end">
                                            <span class="d-block"><?= number_format($sgst_amount, 2) ?></span>
                                            <small class="text-muted">(<?= number_format($item['sgst_rate'], 2) ?>%)</small>
                                        </td>
                                        <td class="text-end fw-bold"><?= number_format($item_total, 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="8" class="text-end"><strong>Subtotal (Taxable Value)</strong></td>
                                        <td class="text-end"><?= number_format($purchase['total_amount'] - $purchase['total_cgst'] - $purchase['total_sgst'], 2) ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="8" class="text-end"><strong>Total CGST</strong></td>
                                        <td class="text-end"><?= number_format($purchase['total_cgst'], 2) ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="8" class="text-end"><strong>Total SGST</strong></td>
                                        <td class="text-end"><?= number_format($purchase['total_sgst'], 2) ?></td>
                                    </tr>
                                    <tr class="table-light fs-6">
                                        <td colspan="8" class="text-end"><strong>Grand Total</strong></td>
                                        <td class="text-end"><strong><i class="bi bi-currency-rupee"></i><?= number_format($purchase['total_amount'], 2) ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mt-3">
                            <p class="mb-1"><strong>Amount in Words:</strong></p>
                            <p><em><?= htmlspecialchars($grand_total_words) ?></em></p>
                        </div>

                        <?php if (!empty($purchase['notes'])): ?>
                        <div class="mt-3">
                            <p class="mb-1"><strong>Notes:</strong></p>
                            <p><?= nl2br(htmlspecialchars($purchase['notes'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <footer class="row signatures" style="margin-top: 5rem;">
                            <div class="col-6">
                                <p class="mb-0">Signature of Customer</p>
                            </div>
                            <div class="col-6 text-end">
                                <p class="mb-0">For SDS Automotive</p>
                                <br><br>
                                <p>Authorized Signatory</p>
                            </div>
                        </footer>
                    </div>
                </div>
            </div>
        </div>
    </main>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/adminlte.js"></script>
</body>
</html>