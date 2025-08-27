<?php
require 'config.php'; // include DB connection
if (!isset($_SESSION['user_id'])) {
  header('Location: index.php');
  exit;
}

?>
<nav class="app-header navbar navbar-expand bg-body">
  <div class="container-fluid">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
          <i class="bi bi-list"></i>
        </a>
      </li>
    </ul>
    <ul class="navbar-nav ms-auto">
      <li class="nav-item">
        <a class="nav-link" href="#" data-lte-toggle="fullscreen">
          <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
          <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
        </a>
      </li>
      <li class="nav-item dropdown user-menu">
        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
          <span
            class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Logged-in User'); ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
          <li class="user-footer">
            <a href="#" class="btn btn-default btn-flat">Profile</a>
            <a href="logout.php" class="btn btn-default btn-flat float-end">Sign out</a>
          </li>
        </ul>
      </li>

    </ul>
  </div>
</nav>