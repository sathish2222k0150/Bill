<?php
require_once '../config.php';
session_start();

// Handle vendor CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['delete_id'])) {
            // Permanent delete from database
            $stmt = $pdo->prepare("DELETE FROM vendors WHERE vendor_id = ?");
            $stmt->execute([$_POST['delete_id']]);
            $_SESSION['success'] = "Vendor deleted successfully!";
        } else {
            // Add/edit vendor
            $data = [
                $_POST['name'],
                $_POST['contact'],
                $_POST['gstin'],
                $_POST['address'],
                $_POST['payment_terms'],
                $_POST['vendor_id'] ?? null
            ];
            
            if (empty($_POST['vendor_id'])) {
                $stmt = $pdo->prepare("INSERT INTO vendors (name, contact, gstin, address, payment_terms) VALUES (?, ?, ?, ?, ?)");
                array_pop($data);
                $_SESSION['success'] = "Vendor added successfully!";
            } else {
                $stmt = $pdo->prepare("UPDATE vendors SET name=?, contact=?, gstin=?, address=?, payment_terms=? WHERE vendor_id=?");
                $_SESSION['success'] = "Vendor updated successfully!";
            }
            
            $stmt->execute($data);
        }
        
        header("Location: vendor-management.php");
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: vendor-management.php");
        exit;
    }
}

$vendors = $pdo->query("SELECT * FROM vendors")->fetchAll();
?>

<!doctype html>
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>SDS Automotive</title>
  <link rel="preload" href="./css/adminlte.css" as="style" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q=" crossorigin="anonymous" media="print" onload="this.media='all'" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
  <link rel="stylesheet" href="../css/adminlte.css" />
  <link rel="stylesheet" href="../assets/css/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css" integrity="sha256-4MX+61mt9NVvvuPjUWdUdyfZfxSB1/Rf9WtqRHgG5S0=" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>SDS Automotive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-auto-close {
            animation: fadeOut 5s forwards;
        }
        @keyframes fadeOut {
            0% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; display: none; }
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
                <div class="row"></div>
    <div class="container mt-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-auto-close"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-auto-close"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="col">
            <div class="row-md-5 form-container">
                <h2>Add New Vendor</h2>
                <form method="POST" id="vendorForm">
                    <input type="hidden" name="vendor_id" id="vendor_id">
                    <div class="mb-3">
                        <label class="form-label">Name*</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact</label>
                        <input type="text" name="contact" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">GSTIN</label>
                        <input type="text" name="gstin" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Terms</label>
                        <select name="payment_terms" class="form-select">
                            <option value="COD">COD</option>
                            <option value="15 Days">15 Days Credit</option>
                            <option value="30 Days">30 Days Credit</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Vendor</button>
                </form>
            </div>
            
            <div class="row-md-7">
                <h2>Vendor List</h2>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>GSTIN</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendors as $vendor): ?>
                        <tr>
                            <td><?= htmlspecialchars($vendor['name']) ?></td>
                            <td><?= htmlspecialchars($vendor['contact']) ?></td>
                            <td><?= htmlspecialchars($vendor['gstin']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal" 
                                    onclick="loadVendorData(<?= $vendor['vendor_id'] ?>)">
                                    Edit
                                </button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to permanently delete this vendor?');">
                                    <input type="hidden" name="delete_id" value="<?= $vendor['vendor_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Vendor Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editVendorForm">
                        <input type="hidden" name="vendor_id" id="edit_vendor_id">
                        <div class="mb-3">
                            <label class="form-label">Name*</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact</label>
                            <input type="text" name="contact" id="edit_contact" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">GSTIN</label>
                            <input type="text" name="gstin" id="edit_gstin" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" id="edit_address" class="form-control"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Terms</label>
                            <select name="payment_terms" id="edit_payment_terms" class="form-select">
                                <option value="COD">COD</option>
                                <option value="15 Days">15 Days Credit</option>
                                <option value="30 Days">30 Days Credit</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('editVendorForm').submit()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../js/adminlte.js"></script>
    <script>
    // Function to load vendor data into modal
    async function loadVendorData(vendorId) {
        try {
            const response = await fetch('get-vendor.php?id=' + vendorId);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const vendor = await response.json();
            
            // Fill modal form with vendor data
            document.getElementById('edit_vendor_id').value = vendor.vendor_id;
            document.getElementById('edit_name').value = vendor.name;
            document.getElementById('edit_contact').value = vendor.contact;
            document.getElementById('edit_gstin').value = vendor.gstin;
            document.getElementById('edit_address').value = vendor.address;
            document.getElementById('edit_payment_terms').value = vendor.payment_terms;
            
            // Show the modal (in case it wasn't automatically shown)
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
            
        } catch (error) {
            console.error('Error loading vendor data:', error);
            alert('Failed to load vendor data. Please try again.');
        }
    }

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-auto-close');
            alerts.forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 550); // 5000 milliseconds = 5 seconds
    });
    </script>
</body>
</html>