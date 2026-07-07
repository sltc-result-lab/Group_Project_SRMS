<?php
// Set timezone
date_default_timezone_set('Asia/Colombo');

// Security check: Only allow CLI run or HTTP request with secure token
$secure_token = 'srilanka_publish_cron_secret_2026';
if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['token']) || $_GET['token'] !== $secure_token) {
        header('HTTP/1.0 403 Forbidden');
        exit('Access Denied: Invalid security token.');
    }
}

include_once __DIR__ . '/config/database.php';
include_once __DIR__ . '/includes/mailer.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception("Database connection failed.");
    }

    // Get current Sri Lanka date and time using DateTime with Asia/Colombo timezone
    $tz = new DateTimeZone('Asia/Colombo');
    $now = new DateTime('now', $tz);
    $now_str = $now->format('Y-m-d H:i:s');

    // Find pending results where scheduled publish date/time is less than or equal to current Sri Lanka time
    $select_query = "SELECT DISTINCT s.name, s.email, r.semester FROM results r 
                     INNER JOIN students s ON r.student_id = s.id 
                     WHERE r.published = 0 AND r.publish_at IS NOT NULL AND r.publish_at <= :now";
    
    $stmt = $db->prepare($select_query);
    $stmt->bindParam(':now', $now_str);
    $stmt->execute();
    $students_to_email = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($students_to_email) > 0) {
        // Update those results as Published
        $update_query = "UPDATE results SET published = 1, publish_at = NULL 
                         WHERE published = 0 AND publish_at IS NOT NULL AND publish_at <= :now";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':now', $now_str);
        $update_stmt->execute();
        $update_count = $update_stmt->rowCount();

        if ($update_count > 0) {
            echo "SUCCESS: Successfully published $update_count result records.\n";
            
            // Send email alerts
            if (function_exists('sendBulkResultEmails')) {
                sendBulkResultEmails($students_to_email);
                echo "SUCCESS: Result release notification emails sent successfully.\n";
            } else {
                echo "WARNING: Mailer helper function not found. Skipping email alerts.\n";
            }
        } else {
            echo "INFO: No results updated.\n";
        }
    } else {
        echo "INFO: No pending results scheduled for publication at or before $now_str.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
