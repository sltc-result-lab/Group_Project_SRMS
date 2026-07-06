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

// Check if id parameter exists
if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$student->id = $_GET['id'];
$stmt = $student->readOne();

if ($stmt->rowCount() == 0) {
    header("Location: list.php");
    exit;
}

$row = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ Using class instead of degree_programme
$student->register_number = $row['register_number'];
$student->name = $row['name'];
$student->email = $row['email'];
$student->degree_programme = $row['degree_programme'];

if ($_POST) {
    $student->register_number = $_POST['register_number'];
    $student->name = $_POST['name'];
    $student->email = $_POST['email'];
    $student->degree_programme = $_POST['degree_programme'];

    if ($student->update()) {
        header("Location: list.php?message=updated");
        exit;
    } else {
        $message = '<div class="alert alert-danger">Unable to update student.</div>';
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Edit Student - Result Management System</title>
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
                        <h3>Edit Student</h3>
                    </div>

                    <div class="card-body">
                        <?php echo $message; ?>

                        <form method="post">

                            <div class="mb-3">
                                <label class="form-label">Register Number</label>
                                <input type="text" class="form-control" name="register_number"
                                    value="<?php echo htmlspecialchars($student->register_number); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Student Name</label>
                                <input type="text" class="form-control" name="name"
                                    value="<?php echo htmlspecialchars($student->name); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email"
                                    value="<?php echo htmlspecialchars($student->email ?? ''); ?>" required
                                    placeholder="student@example.com">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Degree Programme</label>
                                <input type="text" class="form-control" name="degree_programme"
                                    value="<?php echo htmlspecialchars($student->degree_programme); ?>" required>
                            </div>

                            <button type="submit" class="btn btn-primary">Update Student</button>
                            <a href="list.php" class="btn btn-secondary">Back to List</a>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>