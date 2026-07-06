<?php
session_start();
if(!isset($_SESSION['admin_id'])) {
    exit('Unauthorized');
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if(!isset($_GET['degree_programme'])) {
    exit('Degree Programme parameter required');
}

$query = "SELECT id, name, register_number FROM students WHERE degree_programme = :degree_programme ORDER BY name";
$stmt = $db->prepare($query);
$stmt->bindParam(':degree_programme', $_GET['degree_programme']);
$stmt->execute();

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($students); 