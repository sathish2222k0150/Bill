<?php
require __DIR__ . '/config.php'; // include DB connection

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get current page name for active menu
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="app-sidebar bg-body-secondary shadow w-280" data-bs-theme="dark">
  <div class="sidebar-brand">
    <a href="/admin-dashboard.php" class="brand-link d-flex align-items-center gap-2 p-2">
      <img src="/assets/sds.jpg" 
           alt=""
           class="brand-image opacity-75 shadow rounded-3" 
           style="height: 80px; width: auto;" />
      <span class="brand-text fw-semibold" style="font-size: 18px;">SDS Automotive</span>
    </a>
  </div>

  <div class="sidebar-wrapper">
    <nav class="mt-3">
      <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" aria-label="Main navigation" data-accordion="false" id="navigation">

        <li class="nav-item">
          <a href="/admin-dashboard.php" class="nav-link <?php echo ($currentPage == 'admin-dashboard.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-speedometer2"></i>
            <p>Dashboard</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="/product/product-list.php" class="nav-link <?php echo ($currentPage == 'product-list.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-box-seam"></i>
            <p>Products</p>
          </a>
        </li>

        <li class="nav-item">
         <a href="/step-1.php" class="nav-link <?php echo ($currentPage == 'step-1.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-file-earmark-plus"></i>
            <p>Create Invoices</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="/edit-step-1.php" class="nav-link <?php echo ($currentPage == 'edit-step-1.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-pencil-square"></i>
            <p>Invoice Edit</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="/invoice-list.php" class="nav-link <?php echo ($currentPage == 'invoice-list.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-journal-text"></i>
            <p>Invoice Details</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="/labour/labour-invoice-create.php" class="nav-link <?php echo ($currentPage == 'labour-invoice-create.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-tools"></i>
            <p>Labour Invoice</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="/labour/labour-invoice-list.php" class="nav-link <?php echo ($currentPage == 'labour-invoice-list.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-card-list"></i>
            <p>Labour Invoice Details</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="/purchase/vendor-management.php" class="nav-link <?php echo ($currentPage == 'vendor-management.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-cart-plus"></i>
            <p>Vendor Management</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="/purchase/purchase-entry.php" class="nav-link <?php echo ($currentPage == 'purchase-entry.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-cart-plus"></i>
            <p>Purchase Invoice</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="/purchase/purchase-reports.php" class="nav-link <?php echo ($currentPage == 'purchase-reports.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-archive"></i>
            <p>Purchase Invoice Details</p>
          </a>
        </li>

         <li class="nav-item">
          <a href="/receipt/receipts.php" class="nav-link <?php echo ($currentPage == 'receipt.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-receipt"></i>
            <p>Receipts</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="/users/user-details.php" class="nav-link <?php echo ($currentPage == 'users/user-details.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-people"></i>
            <p>User - Details</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="/logout.php" class="nav-link">
            <i class="nav-icon bi bi-box-arrow-right"></i>
            <p>Logout</p>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</aside>