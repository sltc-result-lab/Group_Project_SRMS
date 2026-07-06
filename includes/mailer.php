<?php
// includes/mailer.php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;



function sendBulkResultEmails($students_list, $link = "")
{
    if (empty($students_list)) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'daitresults@gmail.com';
        $mail->Password = 'belsqxujyspodjkr';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('daitresults@gmail.com', 'daitresults');
        $mail->isHTML(true);
        $mail->Subject = 'Your Results Have Been Publish Now';

        // THIS IS THE MAGIC: Keep the connection open
        $mail->SMTPKeepAlive = true;

        $sentCount = 0;

        foreach ($students_list as $student) {
            if (empty($student['email']))
                continue;

            try {
                $mail->addAddress($student['email'], $student['name']);

                $semesterText = isset($student['semester']) && !empty($student['semester']) ? "semester {$student['semester']} " : "";

                $message = "
                <html>
                <head>
                <title>Results Publishs</title>
                </head>
                <body>
                <p>Dear {$student['name']},</p>
                <p>Your {$semesterText}results are released now. You can go to this link to see your results:</p>
                <p><a href='{$link}'>{$link}</a></p>
                <br>
                <p>Regards,<br>daitresults@gmail.com</p>
                </body>
                </html>
                ";

                $mail->Body = $message;
                $mail->AltBody = "Dear {$student['name']},\n\rYour {$semesterText}results are released now. You can go to this link to see your results:\n{$link}\n\nRegards,\ndaitresults@gmail.com";

                $mail->send();
                $sentCount++;
            } catch (Exception $e) {
                // Ignore individual failure and move on to next
            }
            // Clear all addresses for the next iteration
            $mail->clearAllRecipients();
        }

        // Close connection gracefully
        $mail->smtpClose();
        return $sentCount > 0;

    } catch (Exception $e) {
        return false;
    }
}
?>