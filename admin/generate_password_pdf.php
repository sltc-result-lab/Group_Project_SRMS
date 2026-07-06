<?php
session_start();
require_once('tcpdf/tcpdf.php'); // You'll need to install TCPDF

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Your System Name');
$pdf->SetAuthor('System Admin');
$pdf->SetTitle('Password Change Confirmation');

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Add content
$pdf->Cell(0, 10, 'Password Change Confirmation', 0, 1, 'C');
$pdf->Ln(10);
$pdf->Cell(0, 10, 'This document confirms that your password was successfully changed', 0, 1, 'L');
$pdf->Cell(0, 10, 'Date: ' . date('Y-m-d H:i:s'), 0, 1, 'L');
$pdf->Cell(0, 10, 'Username: ' . $_SESSION['admin_username'], 0, 1, 'L');

// Output the PDF
$pdf->Output('password_change_confirmation.pdf', 'D');
?> 