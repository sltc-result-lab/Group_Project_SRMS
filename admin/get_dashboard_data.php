<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$semester = isset($_GET['semester']) && $_GET['semester'] !== '' ? (int)$_GET['semester'] : null;
$subject_id = isset($_GET['subject_id']) && $_GET['subject_id'] !== '' ? (int)$_GET['subject_id'] : null;

$where_clause = "";
$params = [];

if ($semester) {
    $where_clause .= " AND r.semester = :sem";
    $params[':sem'] = $semester;
}
if ($subject_id) {
    $where_clause .= " AND r.subject_id = :subj";
    $params[':subj'] = $subject_id;
}

$response = [];

try {
    // 1. Total Students
    $q1 = "SELECT COUNT(DISTINCT r.student_id) as total FROM results r WHERE r.published = 1 $where_clause";
    $stmt = $db->prepare($q1);
    $stmt->execute($params);
    $response['total_students'] = (int)$stmt->fetchColumn();

    // 2. Pass Rate & Fail Rate
    $q2 = "SELECT 
            COUNT(*) as total_results, 
            SUM(CASE WHEN r.marks >= 40 THEN 1 ELSE 0 END) as passed 
           FROM results r WHERE r.published = 1 $where_clause";
    $stmt = $db->prepare($q2);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_results = (int)$row['total_results'];
    $passed = (int)$row['passed'];
    $failed = $total_results - $passed;

    $response['pass_rate'] = $total_results > 0 ? round(($passed / $total_results) * 100, 1) : 0;
    $response['fail_rate'] = $total_results > 0 ? round(($failed / $total_results) * 100, 1) : 0;

    // 3. Top Performing Subject
    $q3 = "SELECT s.name, AVG(r.marks) as avg_marks 
           FROM subjects s 
           JOIN results r ON r.subject_id = s.id 
           WHERE r.published = 1 $where_clause
           GROUP BY s.id 
           ORDER BY avg_marks DESC 
           LIMIT 1";
    $stmt = $db->prepare($q3);
    $stmt->execute($params);
    $top_subject = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['top_subject'] = $top_subject ? $top_subject['name'] : 'N/A';
    $response['top_subject_avg'] = $top_subject ? round($top_subject['avg_marks'], 1) : 0;

    // 4. Subject-wise Performance
    $q4 = "SELECT 
            s.code as subject_code, 
            AVG(r.marks) as avg_marks, 
            SUM(CASE WHEN r.marks >= 40 THEN 1 ELSE 0 END) as passed,
            COUNT(r.id) as total
           FROM subjects s 
           JOIN results r ON r.subject_id = s.id 
           WHERE r.published = 1 $where_clause
           GROUP BY s.id 
           ORDER BY s.code ASC";
    $stmt = $db->prepare($q4);
    $stmt->execute($params);
    $subject_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['subject_wise'] = [
        'labels' => [],
        'pass_percentages' => [],
        'fail_percentages' => [],
        'averages' => []
    ];

    foreach ($subject_stats as $stat) {
        $response['subject_wise']['labels'][] = $stat['subject_code'];
        $tot = (int)$stat['total'];
        $pass = (int)$stat['passed'];
        $response['subject_wise']['pass_percentages'][] = $tot > 0 ? round(($pass / $tot) * 100, 1) : 0;
        $response['subject_wise']['fail_percentages'][] = $tot > 0 ? round((($tot - $pass) / $tot) * 100, 1) : 0;
        $response['subject_wise']['averages'][] = round($stat['avg_marks'], 1);
    }

    // 5. Grade Distribution
    $q5 = "SELECT 
            CASE 
                WHEN r.marks >= 75 THEN 'Distinction'
                WHEN r.marks >= 60 THEN 'First Class'
                WHEN r.marks >= 50 THEN 'Second Class'
                WHEN r.marks >= 40 THEN 'Pass'
                ELSE 'Fail'
            END as calculated_grade, 
            COUNT(*) as count 
           FROM results r 
           WHERE r.published = 1 $where_clause 
           GROUP BY calculated_grade";
    $stmt = $db->prepare($q5);
    $stmt->execute($params);
    $grade_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grade_map = [
        'Distinction' => 0,
        'First Class' => 0,
        'Second Class' => 0,
        'Pass' => 0,
        'Fail' => 0
    ];
    foreach($grade_stats as $row) {
        if(isset($grade_map[$row['calculated_grade']])) {
            $grade_map[$row['calculated_grade']] = (int)$row['count'];
        }
    }
    $response['grade_distribution'] = [
        'labels' => array_keys($grade_map),
        'data' => array_values($grade_map)
    ];

    // 6. Grade Normalization Histogram
    $q6 = "SELECT 
            r.grade, 
            COUNT(r.id) as total
           FROM results r
           WHERE r.published = 1 AND r.grade IS NOT NULL AND r.grade != '' $where_clause 
           GROUP BY r.grade";
    $stmt = $db->prepare($q6);
    $stmt->execute($params);
    $norm_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $norm_map = [
        'A+' => 0, 'A' => 0, 'A-' => 0,
        'B+' => 0, 'B' => 0, 'B-' => 0,
        'C+' => 0, 'C' => 0, 'C-' => 0,
        'D+' => 0, 'D' => 0, 'E' => 0
    ];

    foreach ($norm_stats as $stat) {
        $g = strtoupper(trim($stat['grade']));
        if ($g === 'E/F') { // Handle hybrid naming just in case
            $norm_map['E'] += (int)$stat['total'];
        } else if (isset($norm_map[$g])) {
            $norm_map[$g] += (int)$stat['total'];
        } else {
            // Aggregate unknown grades into E as fallback or ignore
            if ($g == 'F' || $g == 'E') { $norm_map['E'] += (int)$stat['total']; }
        }
    }

    $response['grade_normalization'] = [
        'labels' => array_keys($norm_map),
        'data' => array_values($norm_map)
    ];

    $response['status'] = 'success';
} catch (Exception $e) {
    http_response_code(500);
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
