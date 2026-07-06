<?php
session_start();

require_once 'vendor/autoload.php';
require_once 'config/database.php';

header('Content-Type: application/json');

// Get the POST payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['credential'])) {
    echo json_encode(["status" => "error", "message" => "Missing Google credential."]);
    exit;
}

$id_token = $data['credential'];
$CLIENT_ID = '89699387290-d79k55bhubql020c9cppu5rrovsqnl82.apps.googleusercontent.com';

$client = new Google_Client(['client_id' => $CLIENT_ID]);

try {
    $payload = $client->verifyIdToken($id_token);
    
    if ($payload) {
        // Token is valid
        $google_sub = $payload['sub'];
        $email = $payload['email'];
        $email_verified = $payload['email_verified'];

        if (!$email_verified) {
            echo json_encode(["status" => "error", "message" => "Your Google email is not verified."]);
            exit;
        }

        $database = new Database();
        $db = $database->getConnection();

        // Check if email exists in students table
        $query = "SELECT * FROM students WHERE email = :email LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check google_sub
            if (empty($student['google_sub'])) {
                // Save it on first successful login
                $update_query = "UPDATE students SET google_sub = :google_sub WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(":google_sub", $google_sub);
                $update_stmt->bindParam(":id", $student['id']);
                $update_stmt->execute();
            } else if ($student['google_sub'] !== $google_sub) {
                echo json_encode(["status" => "error", "message" => "This student account is linked to a different Google account."]);
                exit;
            }

            // Create session
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_name'] = $student['name'];
            $_SESSION['student_degree_programme'] = $student['degree_programme'];
            $_SESSION['student_register'] = $student['register_number'];

            echo json_encode(["status" => "success", "redirect" => "student/dashboard.php"]);
            exit;

        } else {
            echo json_encode(["status" => "error", "message" => "This Google email is not registered in the student system. Please contact admin."]);
            exit;
        }

    } else {
        // Invalid ID token
        echo json_encode(["status" => "error", "message" => "Invalid Google token."]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Error verifying Google token: " . $e->getMessage()]);
    exit;
}
?>
