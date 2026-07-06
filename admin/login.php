<?php
session_start();
if(isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}
header("Location: ../index.php?login=admin");
exit;
?>