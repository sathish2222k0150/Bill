<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

/**
 * Converts a number to the Indian currency word format.
 * @param int $number The number to convert.
 * @return string The number in words.
 */
function amountToWords(int $number) {
    if ($number === 0) {
        return 'Zero';
    }
    // Helper function to convert numbers from 1-999 into words
    function convertThreeDigit($num) {
        $words = [
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
            20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
        ];
        $str = '';
        if ($num >= 100) {
            $str .= $words[floor($num / 100)] . ' Hundred';
            $num %= 100;
            if ($num > 0) $str .= ' ';
        }
        if ($num > 0) {
            if ($num < 20) {
                $str .= $words[$num];
            } else {
                $str .= $words[floor($num / 10) * 10];
                if ($num % 10 > 0) {
                    $str .= ' ' . $words[$num % 10];
                }
            }
        }
        return $str;
    }
    $result = '';
    $crore = floor($number / 10000000);
    $number %= 10000000;
    $lakh = floor($number / 100000);
    $number %= 100000;
    $thousand = floor($number / 1000);
    $number %= 1000;
    $hundred = $number;
    if ($crore > 0) {
        $result .= convertThreeDigit($crore) . ' Crore ';
    }
    if ($lakh > 0) {
        $result .= convertThreeDigit($lakh) . ' Lakh ';
    }
    if ($thousand > 0) {
        $result .= convertThreeDigit($thousand) . ' Thousand ';
    }
    if ($hundred > 0) {
        $result .= convertThreeDigit($hundred);
    }
    return trim($result);
}

// Flags and initializations
$is_saved_view = false;
$invoice = null;
$customer = null;
$display_items = [];
$customer_id = 0;
$invoice_id = 0;
$display_date = date('Y-m-d');
$display_invoice_type = 'Invoice';
$display_with_tax = 'Yes';

const ITEMS_PER_PAGE = 15;

// LOGIC FOR PROCESSING AN UNSAVED INVOICE PREVIEW (from POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['items'])) {
    $customer_id = intval($_POST['customer_id']);
    $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
    
    if ($invoice_id === 0) {
        header('Location: edit-step-1.php?error=invoice_id_missing');
        exit;
    }

    $display_date = $_POST['invoice_date'] ?? date('Y-m-d');
    $display_invoice_type = $_POST['invoice_type'] ?? 'Invoice';
    $display_with_tax = $_POST['with_tax'] ?? 'Yes';

    $preview_items = [];
    foreach ($_POST['items'] as $productId => $itemData) {
        // --- MODIFICATION START ---
        $input_tax_percent = isset($itemData['cgst']) ? floatval($itemData['cgst']) : 0;
        $tax_type = 'C'; // Default to 'C' for CGST/SGST

        if ($input_tax_percent == 18 || $input_tax_percent == 28) {
            $tax_type = 'I'; // Use 'I' for ICGST/ISGST
        }

        $cgst_percent = $input_tax_percent;
        $sgst_percent = $input_tax_percent;
        // --- MODIFICATION END ---

        $preview_items[] = [
            'product_id'   => $itemData['product_id'],
            'product_name' => $itemData['product_name'],
            'quantity'     => floatval($itemData['quantity']),
            'price'        => floatval($itemData['price']),
            'discount'     => isset($itemData['discount']) ? floatval($itemData['discount']) : 0,
            'cgst'         => $cgst_percent,
            'sgst'         => $sgst_percent,
            'tax_type'     => $tax_type
        ];
    }
    
    $_SESSION['preview_invoice_data'][$invoice_id] = [
        'customer_id'  => $customer_id, 'invoice_date' => $display_date, 'invoice_type' => $display_invoice_type, 'with_tax' => $display_with_tax, 'items' => $preview_items
    ];
    $display_items = $preview_items;

// LOGIC FOR VIEWING A SAVED INVOICE (from Database via GET)
} elseif (isset($_GET['id'])) {
    $is_saved_view = true;
    $invoice_id = intval($_GET['id']);

    $invoice_stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $invoice_stmt->execute([$invoice_id]);
    $invoice = $invoice_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        header('Location: edit-step-1.php?error=invoice_not_found');
        exit;
    }

    $customer_id = $invoice['customer_id'];
    $display_date = $invoice['invoice_date'];
    $display_invoice_type = $invoice['invoice_type'];
    $display_with_tax = $invoice['with_tax'];

    $items_stmt = $pdo->prepare("SELECT p.product_name, ii.* FROM invoice_items ii JOIN products p ON ii.product_id = p.id WHERE ii.invoice_id = ?");
    $items_stmt->execute([$invoice_id]);
    $saved_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($saved_items as $item) {
        // --- MODIFICATION: Infer tax type from saved percentage ---
        $cgst_percent_from_db = $item['cgst_percent'];
        $tax_type = 'C';
        if ($cgst_percent_from_db == 18 || $cgst_percent_from_db == 28) {
            $tax_type = 'I';
        }
        // --- END MODIFICATION ---

        $display_items[] = [
            'product_name' => $item['product_name'],
            'quantity'     => $item['quantity'],
            'price'        => ($item['quantity'] > 0) ? ($item['subtotal'] / $item['quantity']) : 0,
            'discount'     => (float)($item['discount'] ?? 0),
            'cgst'         => $item['cgst_percent'],
            'sgst'         => $item['sgst_percent'],
            'tax_type'     => $tax_type
        ];
    }
} else {
    if (!isset($_GET['status'])) {
        header('Location: edit-step-1.php?error=no_data_provided');
        exit;
    }
}

// DATA FETCHING & OVERALL CALCULATIONS
if ($customer_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$customer && !isset($_GET['status'])) {
     header('Location: edit-step-1.php?error=customer_not_found');
     exit;
}

$with_tax = ($display_with_tax === 'Yes');
$overall_subtotal = 0;
$overall_total_discount = 0;
$overall_total_cgst = 0;
$overall_total_sgst = 0;
$has_icgst = false; // Flag to check if any ICGST/ISGST items exist

foreach ($display_items as $item) {
    $line_subtotal = $item['quantity'] * $item['price'];
    $discount_amount = $item['discount'] ?? 0;
    $taxable_value = $line_subtotal - $discount_amount;
    
    $overall_subtotal += $line_subtotal;
    $overall_total_discount += $discount_amount;
    
    if ($with_tax) {
        $overall_total_cgst += $taxable_value * ($item['cgst'] / 100);
        $overall_total_sgst += $taxable_value * ($item['sgst'] / 100);

        if (isset($item['tax_type']) && $item['tax_type'] === 'I') {
            $has_icgst = true;
        }
    }
}
$overall_grand_total = $overall_subtotal - $overall_total_discount + $overall_total_cgst + $overall_total_sgst;
$grand_total_in_words = amountToWords(round($overall_grand_total));

$item_pages = empty($display_items) ? [] : array_chunk($display_items, ITEMS_PER_PAGE);
$total_pages = count($item_pages);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice Preview</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
    body { background-color: #f8f9fa; }
    .invoice-container { max-width: 800px; margin: 20px auto; background: #fff; padding: 20px; border: 1px solid #ddd; font-size: 12px; }
    .invoice-header { border-bottom: 2px solid #dee2e6; padding-bottom: 15px; margin-bottom: 20px; }
    .invoice-header .logo { font-size: 1.6rem; font-weight: bold; color: #dc3545; }
    .invoice-details h2 { margin: 0; font-size: 1.6rem; font-weight: bold; text-transform: capitalize; }
    .table-items { font-size: 11px; }
    .totals-table { width: 100%; max-width: 300px; margin-left: auto; }
    .actions { margin-top: 40px; text-align: right; }
    .page-footer-totals { border-top: 1px solid #dee2e6; padding-top: 10px; margin-top: 20px; }
    .final-totals-section { background-color: #e9ecef; border: 1px solid #dee2e6; padding: 15px; margin-top: 20px; }
    @media print { @page { size: auto; margin: 0mm; } .actions, .modal { display: none !important; } body { background-color: #fff; margin: 0; } .invoice-container { margin: 1.5cm; padding: 0; box-shadow: none; border: none; width: auto; } .invoice-page { page-break-after: always; } .invoice-page:last-of-type { page-break-after: auto; } }
  </style>
</head>
<body>
<div class="invoice-container">
    <?php if ($customer && !empty($item_pages)): ?>
        <?php foreach ($item_pages as $page_index => $page_items): ?>
            <?php
            // Per-page calculation logic
            $page_subtotal = 0; $page_total_discount = 0; $page_total_cgst = 0; $page_total_sgst = 0;
            $page_tax_breakdown = [];

            foreach ($page_items as $item) {
                $line_subtotal = $item['quantity'] * $item['price'];
                $discount_amount = $item['discount'] ?? 0;
                $taxable_value = $line_subtotal - $discount_amount;
                
                $page_subtotal += $line_subtotal;
                $page_total_discount += $discount_amount;

                if ($with_tax) {
                    $cgst_percent = floatval($item['cgst']);
                    $sgst_percent = floatval($item['sgst']);
                    $tax_type = $item['tax_type'] ?? 'C';
                    
                    $current_cgst_amount = $taxable_value * ($cgst_percent / 100);
                    $current_sgst_amount = $taxable_value * ($sgst_percent / 100);
                    
                    $page_total_cgst += $current_cgst_amount;
                    $page_total_sgst += $current_sgst_amount;

                    if ($cgst_percent > 0) {
                        $key = $tax_type . '-' . (string)$cgst_percent;
                        if (!isset($page_tax_breakdown[$key])) {
                            $page_tax_breakdown[$key] = ['type' => $tax_type, 'percent' => $cgst_percent, 'taxable_value' => 0, 'cgst_amount' => 0, 'sgst_amount' => 0];
                        }
                        $page_tax_breakdown[$key]['taxable_value'] += $taxable_value;
                        $page_tax_breakdown[$key]['cgst_amount'] += $current_cgst_amount;
                        $page_tax_breakdown[$key]['sgst_amount'] += $current_sgst_amount;
                    }
                }
            }
            $page_grand_total = $page_subtotal - $page_total_discount + $page_total_cgst + $page_total_sgst;
            ?>
        <div class="invoice-page">
            <div class="row invoice-header align-items-center">
                <div class="col-4"><div class="logo">SDS Automotive</div><div>9842365406, 9952513032</div><div>sdsmotors2019@gmail.com</div></div>
                 <div class="col-4 text-center">
                <img src="assets/sds.jpg" alt="SDS Automotive" style="max-height:80px;">
                </div>
                <div class="col-4 text-end invoice-details">
                    <h2><?= htmlspecialchars($display_invoice_type) ?></h2>
                    <div><strong>Date:</strong> <?= date('d-m-Y', strtotime($display_date)) ?></div>
                    <div><strong>Invoice :</strong> <?= $is_saved_view && $invoice ? htmlspecialchars($invoice['invoice_number']) : '[Pending Update]' ?></div>
                    <?php if ($total_pages > 1): ?><div><strong>Page:</strong> <?= $page_index + 1 ?> of <?= $total_pages ?></div><?php endif; ?>
                </div>
            </div>
            <div class="row billing-details mt-3">
                <div class="col-7">
                    <strong>Billed To</strong>
                    <address class="mt-1 ps-2"><strong><?= htmlspecialchars($customer['customer_name']) ?></strong><br><?= nl2br(htmlspecialchars($customer['customer_address'])) ?><br><?= htmlspecialchars($customer['customer_contact']) ?><br><strong>Reg No:</strong> <?= htmlspecialchars($customer['reg_no']) ?><br><strong>GST:</strong> <?= htmlspecialchars($customer['gst_number']) ?></address>
                </div>
                <div class="col-5 text-end"><strong>Business Address</strong><address class="mt-1">KARAIKADU, SIPCOT POST<br>CUDDALORE TO VIRUDHACHALAM MAIN ROAD<br>CUDDALORE - 607005</address>GST NO: 33KNYPS2440P1ZW</div>
            </div>
            
            <table class="table table-bordered table-sm mt-3 table-items">
                <thead class="table-secondary"><tr class="text-center"><th>S.No</th><th>Description</th><th>Qty</th><th>Price (₹)</th><th>Discount (₹)</th><?php if ($with_tax): ?><th>CGST %</th><th>SGST %</th><?php endif; ?><th>Total (₹)</th></tr></thead>
                <tbody>
                    <?php foreach ($page_items as $item_index => $item): ?>
                        <?php
                        $line_subtotal = $item['quantity'] * $item['price'];
                        $discount_amount = $item['discount'] ?? 0;
                        $taxable_value = $line_subtotal - $discount_amount;
                        $line_total = $taxable_value;
                        if ($with_tax) {
                            $line_total += $taxable_value * ($item['cgst'] / 100);
                            $line_total += $taxable_value * ($item['sgst'] / 100);
                        }
                        ?>
                    <tr>
                        <td class="text-center"><?= ($page_index * ITEMS_PER_PAGE) + $item_index + 1 ?></td>
                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                        <td class="text-center"><?= $item['quantity'] ?></td>
                        <td class="text-end"><?= number_format($item['price'], 2) ?></td>
                        <td class="text-end"><?= number_format($discount_amount, 2) ?></td>
                        <?php if ($with_tax): ?>
                        <td class="text-center"><?= $item['cgst'] ?>%</td>
                        <td class="text-center"><?= $item['sgst'] ?>%</td>
                        <?php endif; ?>
                        <td class="text-end"><?= number_format($line_total, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="page-footer-totals">
                <div class="row">
                    <div class="col-7">
                         <?php 
                        if ($with_tax && (!empty($page_tax_breakdown))) {
                            echo '<div class="fw-bold">Note:</div>';
                            ksort($page_tax_breakdown, SORT_STRING);
                            foreach ($page_tax_breakdown as $data) {
                                $cgst_label = ($data['type'] === 'I') ? 'ICGST' : 'CGST';
                                $sgst_label = ($data['type'] === 'I') ? 'ISGST' : 'SGST';
                                
                                echo '<div>'.$cgst_label.' @ '.number_format($data['percent'], 2).'% on '.number_format($data['taxable_value'], 2).' = '.number_format($data['cgst_amount'], 2).'</div>';
                                echo '<div>'.$sgst_label.' @ '.number_format($data['percent'], 2).'% on '.number_format($data['taxable_value'], 2).' = '.number_format($data['sgst_amount'], 2).'</div>';
                            }
                        }
                        if ($page_index === $total_pages - 1) {
                            echo "<div class='mt-2'>--- End of Items ---</div>";
                        }
                        ?>
                    </div>
                    <div class="col-5">
                        <table class="table table-sm totals-table">
                            <tbody>
                                <tr><td>Page Subtotal</td><td class="text-end"><?= number_format($page_subtotal, 2) ?></td></tr>
                                <tr><td>Page Discount</td><td class="text-end">- <?= number_format($page_total_discount, 2) ?></td></tr>
                                <?php if ($with_tax): ?>
                                <tr><td>CGST</td><td class="text-end"><?= number_format($page_total_cgst, 2) ?></td></tr>
                                <tr><td>SGST</td><td class="text-end"><?= number_format($page_total_sgst, 2) ?></td></tr>
                                <?php endif; ?>
                                <tr class="fw-bold table-light"><td>Page Total</td><td class="text-end"><?= number_format($page_grand_total, 2) ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <?php if ($page_index === $total_pages - 1): ?>
            <div class="final-totals-section">
                <h5 class="text-center mb-3">Overall Invoice Summary</h5>
                <div class="row justify-content-end">
                    <div class="col-sm-7 col-md-6 col-lg-5">
                        <table class="table table-sm totals-table">
                            <tbody>
                                <tr><td>Overall Subtotal</td><td class="text-end"><?= number_format($overall_subtotal, 2) ?></td></tr>
                                <tr><td>Overall Discount</td><td class="text-end">- <?= number_format($overall_total_discount, 2) ?></td></tr>
                                <?php if ($with_tax): ?>
                                <?php
                                    $overall_cgst_label = $has_icgst ? 'Overall ICGST' : 'Overall CGST';
                                    $overall_sgst_label = $has_icgst ? 'Overall ISGST' : 'Overall SGST';
                                ?>
                                <tr><td><?= $overall_cgst_label ?></td><td class="text-end"><?= number_format($overall_total_cgst, 2) ?></td></tr>
                                <tr><td><?= $overall_sgst_label ?></td><td class="text-end"><?= number_format($overall_total_sgst, 2) ?></td></tr>
                                <?php endif; ?>
                                <tr class="fw-bold table-secondary"><td>GRAND TOTAL</td><td class="text-end">₹ <?= number_format(round($overall_grand_total), 2) ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-12">
                    <strong>Amount in Words:</strong>
                    <span><?= htmlspecialchars($grand_total_in_words) ?> Rupees Only</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="actions">
            <?php if ($is_saved_view): ?>
                <button onclick="window.print()" class="btn btn-info"><i class="fas fa-print"></i> Print Invoice</button>
                <a href="edit-step-1.php" class="btn btn-success"><i class="fas fa-search"></i> Find Another Invoice</a>
            <?php else: ?>
                <a href="invoice-edit-products.php?invoice_id=<?= $invoice_id ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Edit</a>
                <button onclick="window.print()" class="btn btn-info"><i class="fas fa-print"></i> Print</button>
                <form action="save-edit-invoice.php" method="POST" class="d-inline">
                    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
                    <input type="hidden" name="invoice_date" value="<?= htmlspecialchars($display_date) ?>">
                    <input type="hidden" name="invoice_type" value="<?= htmlspecialchars($display_invoice_type) ?>">
                    <input type="hidden" name="with_tax" value="<?= htmlspecialchars($display_with_tax) ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Confirm & Save Changes</button>
                </form>
            <?php endif; ?>
        </div>

    <?php elseif (isset($_GET['status'])): ?>
        <div class="alert alert-info mt-4">Invoice has been updated.</div>
        <a href="edit-step-1.php" class="btn btn-success"><i class="fas fa-search"></i> Edit Another Invoice</a>
    <?php endif; ?>
</div>
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-success text-white"><h5 class="modal-title">Success!</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body text-center"><i class="fas fa-check-circle fa-4x text-success mb-3"></i><p class="fs-5">The invoice has been updated successfully!</p></div><div class="modal-footer justify-content-center"><a href="#" id="viewInvoiceBtn" class="btn btn-primary">View Saved Invoice</a><a href="edit-step-1.php" class="btn btn-secondary">Edit Another Invoice</a></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() { const urlParams = new URLSearchParams(window.location.search); if (urlParams.get('status') === 'success') { const invoiceId = urlParams.get('invoice_id'); const viewBtn = document.getElementById('viewInvoiceBtn'); if (invoiceId && viewBtn) { viewBtn.href = 'edit-preview.php?id=' + invoiceId; } const successModal = new bootstrap.Modal(document.getElementById('successModal')); successModal.show(); } });
</script>
<script src="./js/adminlte.js"></script>
</body>
</html>