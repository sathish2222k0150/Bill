<?php
require 'config.php'; // include DB connection
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

?>

<footer class="app-footer">

    <div class="float-end d-none d-sm-inline">Anything you want</div>

    <strong>
        Copyright &copy;2025&nbsp;
        <a href="https://sdsautomotive.com/" class="text-decoration-none">SDS Automotive</a>.
    </strong>
    All rights reserved.

</footer>