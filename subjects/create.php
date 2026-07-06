<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = "";

// Get all unique classes
$class_query = "SELECT DISTINCT degree_programme FROM students ORDER BY degree_programme";
$class_stmt = $db->prepare($class_query);
$class_stmt->execute();
$classes = $class_stmt->fetchAll(PDO::FETCH_COLUMN);

if ($_POST) {
    $name = trim($_POST['name']);
    $code = trim($_POST['code']);
    $degree_programme = trim($_POST['degree_programme']);
    $semester = (int) $_POST['semester'];

    // Check duplicate
    $check_query = "SELECT id FROM subjects 
                    WHERE code = :code 
                    AND degree_programme = :degree_programme 
                    AND semester = :semester";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":code", $code);
    $check_stmt->bindParam(":degree_programme", $degree_programme);
    $check_stmt->bindParam(":semester", $semester, PDO::PARAM_INT);
    $check_stmt->execute();

    if ($check_stmt->rowCount() > 0) {
        $message = '<div class="alert alert-danger">Subject code already exists for this class and semester!</div>';
    } else {
        $query = "INSERT INTO subjects(name, code, degree_programme, semester) 
                  VALUES(:name, :code, :degree_programme, :semester)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":code", $code);
        $stmt->bindParam(":degree_programme", $degree_programme);
        $stmt->bindParam(":semester", $semester, PDO::PARAM_INT);

        if ($stmt->execute()) {
            header("Location: list.php?message=created");
            exit;
        } else {
            $message = '<div class="alert alert-danger">Unable to create subject.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Add Subject - Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../admin/dashboard.php">Admin Dashboard</a>
            <div class="navbar-nav ms-auto">
                <a href="../admin/logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h3>Add New Subject</h3>
                    </div>
                    <div class="card-body">
                        <?php echo $message; ?>

                        <form method="post">

                            <div class="mb-3">
                                <label class="form-label">Subject Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Subject Code</label>
                                <input type="text" class="form-control" name="code" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Select Degree Programme</label>
                                <select class="form-control" name="degree_programme" required>
                                    <option value="">Select Degree Programme</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo htmlspecialchars($class); ?>">
                                            <?php echo htmlspecialchars($class); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Semester</label>
                                <select class="form-control" name="semester" required>
                                    <option value="">Select Semester</option>
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">Create Subject</button>
                            <a href="list.php" class="btn btn-secondary me-2">Back to List</a>
                            <a href="../admin/dashboard.php" class="btn btn-dark">Back to Dashboard</a>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>

</html>