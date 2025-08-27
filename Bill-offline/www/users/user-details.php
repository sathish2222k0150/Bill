<?php
// --- PHP LOGIC AT THE TOP ---

require_once '../config.php'; // Ensure this path is correct

// Start session and check for admin privileges
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // If not logged in or not an admin, redirect to login page
    header('Location: ../index.php');
    exit();
}

$feedback = '';
$feedback_type = '';

// --- HANDLE POST REQUESTS FOR C.U.D. OPERATIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -- ACTION: ADD USER --
    if ($action === 'add') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];

        if (empty($username) || empty($password) || empty($role)) {
            $feedback = "All fields are required for adding a user.";
            $feedback_type = "danger";
        } else {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->rowCount() > 0) {
                $feedback = "Username already exists.";
                $feedback_type = "danger";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                if ($stmt->execute([$username, $hashedPassword, $role])) {
                    $feedback = "User added successfully.";
                    $feedback_type = "success";
                } else {
                    $feedback = "Failed to add user.";
                    $feedback_type = "danger";
                }
            }
        }
    }

    // -- ACTION: UPDATE USER --
    if ($action === 'update') {
        $id = $_POST['id'];
        $username = trim($_POST['username']);
        $role = $_POST['role'];
        $password = $_POST['password'];

        // Prevent admin from demoting themselves
        if ($id == $_SESSION['user_id'] && $role !== 'admin') {
            $feedback = "Error: You cannot change your own role.";
            $feedback_type = "danger";
        } else {
            if (!empty($password)) {
                // If password is provided, update it
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $hashedPassword, $role, $id]);
            } else {
                // If password is not provided, update other details
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $role, $id]);
            }

            if ($stmt->rowCount() > 0) {
                $feedback = "User updated successfully.";
                $feedback_type = "success";
            } else {
                $feedback = "No changes were made or user not found.";
                $feedback_type = "warning";
            }
        }
    }

    // -- ACTION: DELETE USER --
    if ($action === 'delete') {
        $id = $_POST['id'];
        // Prevent admin from deleting their own account
        if ($id == $_SESSION['user_id']) {
            $feedback = "Error: You cannot delete your own account.";
            $feedback_type = "danger";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$id])) {
                $feedback = "User deleted successfully.";
                $feedback_type = "success";
            } else {
                $feedback = "Failed to delete user.";
                $feedback_type = "danger";
            }
        }
    }
}

// --- FETCH ALL USERS FOR DISPLAY (R.ead operation) ---
$stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY id ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <!-- AdminLTE & Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/adminlte.css"> <!-- Make sure this path is correct -->
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <!-- Fonts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
<div class="app-wrapper">
    <?php include '../header.php'; ?>
    <?php include '../sidebar.php'; ?>

    <main class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Manage Users</h1>
                    </div>
                    <div class="col-sm-6 text-end">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" id="addUserBtn">
                            <i class="fas fa-plus"></i> Add New User
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-body">
                        <?php if ($feedback): ?>
                        <div class="alert alert-<?php echo $feedback_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($feedback); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th style="width: 150px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#userModal"
                                                data-id="<?php echo htmlspecialchars($user['id']); ?>"
                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                data-role="<?php echo htmlspecialchars($user['role']); ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-btn" 
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteModal"
                                                data-id="<?php echo htmlspecialchars($user['id']); ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="userModalLabel">Add User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="userForm" method="post">
        <div class="modal-body">
          <input type="hidden" name="action" id="formAction" value="add">
          <input type="hidden" name="id" id="userId">
          <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" required>
          </div>
          <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password">
            <small id="passwordHelp" class="form-text text-muted">Leave blank to keep current password.</small>
          </div>
          <div class="mb-3">
            <label for="role" class="form-label">Role</label>
            <select class="form-control" id="role" name="role" required>
              <option value="staff">Staff</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete this user? This action cannot be undone.
      </div>
      <div class="modal-footer">
        <form id="deleteForm" method="post">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" id="deleteUserId">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete User</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="../js/adminlte.js"></script> <!-- Make sure this path is correct -->

<script>
$(document).ready(function() {
    // --- DYNAMIC MODAL FOR ADD/EDIT ---

    // Handle 'Add New User' button click
    $('#addUserBtn').on('click', function() {
        $('#userForm')[0].reset(); // Clear form fields
        $('#userModalLabel').text('Add New User');
        $('#formAction').val('add');
        $('#userId').val('');
        $('#password').prop('required', true); // Password is required for new users
        $('#passwordHelp').hide();
    });

    // Handle 'Edit' button click
    $('.edit-btn').on('click', function() {
        $('#userForm')[0].reset(); // Clear form first
        $('#userModalLabel').text('Edit User');
        $('#formAction').val('update');
        $('#password').prop('required', false); // Password is not required for edits
        $('#passwordHelp').show();

        // Get data from button attributes
        const id = $(this).data('id');
        const username = $(this).data('username');
        const role = $(this).data('role');

        // Populate modal fields
        $('#userId').val(id);
        $('#username').val(username);
        $('#role').val(role);
    });

    // --- DYNAMIC MODAL FOR DELETE ---
    $('.delete-btn').on('click', function() {
        const id = $(this).data('id');
        $('#deleteUserId').val(id); // Set the hidden ID in the delete form
    });
});
</script>

</body>
</html>