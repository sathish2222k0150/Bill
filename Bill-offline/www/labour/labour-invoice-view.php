<?php
session_start();
require_once '../config.php'; // Ensure this path is correct

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Validate invoice ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: labour-invoice-list.php?error=invalid_id');
    exit;
}

$invoice_id = (int)$_GET['id'];

try {
    // Get main invoice details
    $stmt = $pdo->prepare("SELECT * FROM labour_invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    // If invoice doesn't exist, redirect
    if (!$invoice) {
        header('Location: labour-invoice-list.php?error=not_found');
        exit;
    }

    // Get all associated items
    $items_stmt = $pdo->prepare("SELECT * FROM labour_invoice_items WHERE invoice_id = ? ORDER BY id ASC");
    $items_stmt->execute([$invoice_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- PAGINATION LOGIC ---
    $items_per_page = 10; // Reduced to leave more space for the footer on the last page
    $item_pages = array_chunk($items, $items_per_page);
    $total_pages = count($item_pages);
    if ($total_pages === 0) {
        $total_pages = 1; // Ensure at least one page even if no items
        $item_pages = [[]]; // Create an empty page
    }

    // Calculate CGST and SGST
    $cgst_percent = $invoice['gst_percent'] / 2;
    $sgst_percent = $invoice['gst_percent'] / 2;
    $cgst_amount = $invoice['gst_amount'] / 2;
    $sgst_amount = $invoice['gst_amount'] / 2;

} catch (Exception $e) {
    die("Error: Could not retrieve invoice data. " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Preview - <?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-size: 14px; /* Reduced base font size */
        }
        .invoice-container {
            max-width: 800px;
            margin: 2rem auto;
            background-color: #fff;
            border: 1px solid #dee2e6;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .invoice-page {
            padding: 2rem;
            display: flex;
            flex-direction: column;
        }
        /* Set a fixed height for all pages except the last one */
        .invoice-page:not(.last-page) {
            min-height: 1120px;
        }
        .invoice-header h1 {
            color: #dc3545;
            font-weight: bold;
            font-size: 2.2rem; /* Reduced */
        }
        .invoice-header h2 {
            font-size: 1.4rem; /* Reduced */
        }
        .invoice-header p { margin-bottom: 0; color: #6c757d; }
        .section-title {
            font-size: 0.8rem; /* Reduced */
            font-weight: bold;
            color: #6c757d;
            text-transform: uppercase;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 4px; /* Reduced */
            margin-bottom: 8px; /* Reduced */
        }
        .billed-to h5 { font-size: 1rem; } /* Reduced */
        .billed-to p, .business-address p { margin-bottom: 1px; font-size: 0.9rem; }

        .table { font-size: 0.85rem; } /* Reduced table font size */
        .table td, .table th {
            padding: 0.4rem 0.5rem; /* Reduced cell padding */
            text-align: right;
            vertical-align: middle;
        }
        .table th:nth-child(1), .table th:nth-child(2),
        .table td:nth-child(1), .table td:nth-child(2) { text-align: left; }

        .invoice-footer {
            margin-top: 2rem; /* Consistent margin from the table */
            page-break-inside: avoid; /* Prevents the footer from splitting during print */
        }
        .summary-table { width: 55%; margin-left: auto; font-size: 0.9rem; } /* Reduced */
        .summary-table td { padding: 0.4rem; } /* Reduced */
        .summary-table tr:last-child { font-weight: bold; background-color: #f8f9fa; }
        .signature-section { margin-top: 60px; } /* Reduced */
        .signature-line {
            border-top: 1px solid #343a40;
            padding-top: 5px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        .actions { margin-top: 2rem; text-align: center; }

        /* Print Styles */
        @media print {
            body { background-color: #fff; font-size: 12px; }
            .invoice-container { margin: 0; padding: 0; border: none; box-shadow: none; max-width: 100%; }
            .invoice-page {
                min-height: 0 !important; /* Important to override screen style */
                page-break-after: always;
                padding: 1.5rem;
            }
            .invoice-page:last-child { page-break-after: auto; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <?php foreach ($item_pages as $page_index => $page_items): ?>
            <?php
                $current_page_num = $page_index + 1;
                $is_last_page = ($current_page_num === $total_pages);
            ?>
            <div class="invoice-page <?= $is_last_page ? 'last-page' : '' ?>">
                <!-- Header (repeated on each page) -->
                <header>
                    <div class="row mb-4 invoice-header">
                        <div class="col-4">
                            <h1>SDS Automotive</h1>
                            <p>9842365406, 9952513032</p>
                            <p>sdsmotors2019@gmail.com</p>
                        </div>
                        <div class="col-4 text-center">
                        <img src="../assets/sds.jpg" alt="SDS Automotive" style="max-height:80px;">
                        </div>
                        <div class="col-4 text-end">
                            <h2>Labour Invoice</h2>
                            <p><strong>Date:</strong> <?= htmlspecialchars(date("d-m-Y", strtotime($invoice['invoice_date']))) ?></p>
                            <p><strong>Invoice #:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?></p>
                            <p>Page: <?= $current_page_num ?> of <?= $total_pages ?></p>
                        </div>
                    </div>
                    <hr class="mb-4">
                    <div class="row mb-4">
                        <div class="col-6 billed-to">
                            <p class="section-title">Billed To</p>
                            <h5><?= htmlspecialchars($invoice['customer_name']) ?></h5>
                            <p><?= htmlspecialchars($invoice['customer_contact']) ?></p>
                            <p><strong>Vehicle No:</strong> <?= htmlspecialchars($invoice['vehicle_number']) ?></p>
                            <p><strong>Vehicle Model:</strong> <?= htmlspecialchars($invoice['vehicle_model']) ?></p>
                        </div>
                        <div class="col-6 business-address text-end">
                            <p class="section-title">Business Address</p>
                            <p>KARAIKADU, SIPCOT POST</p>
                            <p>CUDDALORE TO VIRUDHACHALAM MAIN ROAD</p>
                            <p>CUDDALORE - 607005</p>
                        </div>
                    </div>
                </header>

                <!-- Main content area that grows -->
                <main style="flex-grow: 1;">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">S.No</th>
                                <th scope="col" style="width: 45%;">Description</th>
                                <th scope="col">Hours</th>
                                <th scope="col">Rate (₹)</th>
                                <th scope="col">CGST %</th>
                                <th scope="col">SGST %</th>
                                <th scope="col">Total (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($page_items as $item_index => $item): ?>
                            <tr>
                                <td><?= ($page_index * $items_per_page) + $item_index + 1 ?></td>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td><?= number_format($item['hours'], 2) ?></td>
                                <td><?= number_format($item['rate'], 2) ?></td>
                                <td><?= number_format($cgst_percent, 0) ?>%</td>
                                <td><?= number_format($sgst_percent, 0) ?>%</td>
                                <td><?= number_format($item['amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </main>

                <!-- Footer: Shown only on the last page -->
                <?php if ($is_last_page): ?>
                <footer class="invoice-footer">
                    <div class="row justify-content-end">
                        <div class="col-6">
                            <table class="table summary-table">
                                <tbody>
                                    <tr>
                                        <td>Subtotal</td>
                                        <td class="text-end">₹<?= number_format($invoice['subtotal'], 2) ?></td>
                                    </tr>
                                    <tr>
                                        <td>CGST</td>
                                        <td class="text-end">₹<?= number_format($cgst_amount, 2) ?></td>
                                    </tr>
                                    <tr>
                                        <td>SGST</td>
                                        <td class="text-end">₹<?= number_format($sgst_amount, 2) ?></td>
                                    </tr>
                                    <tr class="table-light">
                                        <td><strong>Grand Total</strong></td>
                                        <td class="text-end"><strong>₹<?= number_format($invoice['grand_total'], 2) ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row signature-section">
                        <div class="col-6"></div>
                        <div class="col-6 text-center">
                            <div class="signature-line">Authorised Signatory</div>
                        </div>
                    </div>
                </footer>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Action Buttons -->
    <div class="actions">
        <button class="btn btn-primary" onclick="window.print();">
            <svg xmlns="http:/.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer" viewBox="0 0 16 16"><path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/><path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/></svg>
            Print
        </button>
        <a href="labour-invoice-list.php" class="btn btn-secondary">
            <svg xmlns="http:/.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left-circle" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5z"/></svg>
            Back to Invoice List
        </a>
    </div>
<script src="../js/adminlte.js"></script>
</body>
</html>