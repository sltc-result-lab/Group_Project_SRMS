<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

include_once '../config/database.php';
include_once '../classes/Student.php';

$database = new Database();
$db = $database->getConnection();
$student = new Student($db);

$message = "";

if ($_POST) {
    $register_number = $_POST['register_number'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $degree_programme = $_POST['degree_programme'];
    $date_of_birth = $_POST['date_of_birth'];

    // Check if register number already exists
    $check_query = "SELECT id FROM students WHERE register_number = :register_number";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":register_number", $register_number);
    $check_stmt->execute();

    if ($check_stmt->rowCount() > 0) {
        $message = '<div class="alert alert-danger">Register number already exists!</div>';
    } else {
        $query = "INSERT INTO students(register_number, name, email, `degree_programme`, date_of_birth) 
                  VALUES(:register_number, :name, :email, :degree_programme, :date_of_birth)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":register_number", $register_number);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":degree_programme", $degree_programme);
        $stmt->bindParam(":date_of_birth", $date_of_birth);

        if ($stmt->execute()) {
            header("Location: list.php?message=created");
            exit;
        } else {
            $message = '<div class="alert alert-danger">Unable to create student.</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Add Student - Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
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
                        <h3><i class="fa fa-user-plus"></i> Add New Student</h3>
                    </div>

                    <div class="card-body">
                        <?php echo $message; ?>

                        <form method="post">

                            <div class="mb-3">
                                <label class="form-label">Register Number</label>
                                <input type="text" class="form-control" name="register_number" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Student Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" required
                                    placeholder="student@example.com">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Degree Programme</label>
                                <input type="text" class="form-control" name="degree_programme" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth" required
                                    max="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-save"></i> Create Student
                            </button>

                            <a href="list.php" class="btn btn-secondary me-2">
                                <i class="fa fa-list"></i> Back to List
                            </a>

                            <a href="../admin/dashboard.php" class="btn btn-dark">
                                <i class="fa fa-dashboard"></i> Back to Dashboard
                            </a>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>