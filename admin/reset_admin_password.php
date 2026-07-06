<?php
include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$new_password = "admin123";
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$query = "UPDATE admins SET password = :password WHERE username = 'admin'";
$stmt = $db->prepare($query);
$stmt->bindParam(":password", $hashed_password);

if($stmt->execute()) {
    echo "Admin password has been reset to: admin123";
} else {
    echo "Failed to reset password";
}
?> 