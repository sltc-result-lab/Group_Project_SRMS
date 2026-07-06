<?php
session_start();
if(!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$success = false;

if(isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Debug: Print admin ID to verify
    error_log("Admin ID: " . $_SESSION['admin_id']);
    
    // Verify current password without hashing
    $query = "SELECT password FROM admins WHERE id = ? AND password = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['admin_id'], $current_password]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug: Print password details (for development only, remove in production)
    error_log("Current password entered: " . $current_password);
    
    if($row) {
        if($new_password === $confirm_password) {
            // Update password without hashing
            $query = "UPDATE admins SET password = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if($stmt->execute([$new_password, $_SESSION['admin_id']])) {
                $message = "Password changed successfully!";
                $success = true;
                $_SESSION['password_changed'] = true;
            } else {
                $message = "Failed to change password. Database error.";
                error_log("Password update failed for admin ID: " . $_SESSION['admin_id']);
            }
        } else {
            $message = "New passwords do not match.";
        }
    } else {
        $message = "Current password is incorrect.";
        // Debug: Log error details
        error_log("Password verification failed for admin ID: " . $_SESSION['admin_id']);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Password - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Admin Dashboard</a>
            <div class="navbar-nav ms-auto">
                <a href="logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-header">
                        <h5>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <?php if($message): ?>
                            <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?>">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                            <?php if($success): ?>
                                <a href="generate_password_pdf.php" class="btn btn-success">
                                    <i class="fas fa-download"></i> Download Confirmation PDF
                                </a>
                            <?php endif; ?>
                            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 