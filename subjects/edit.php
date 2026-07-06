<?php
session_start();
if(!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = "";

if(!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$id = $_GET['id'];

$query = "SELECT * FROM subjects WHERE id = :id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(":id", $id);
$stmt->execute();

if($stmt->rowCount() == 0) {
    header("Location: list.php");
    exit;
}

$subject = $stmt->fetch(PDO::FETCH_ASSOC);

$class_query = "SELECT DISTINCT `degree_programme` FROM students ORDER BY `degree_programme`";
$class_stmt = $db->prepare($class_query);
$class_stmt->execute();
$classes = $class_stmt->fetchAll(PDO::FETCH_COLUMN);

if($_POST) {
    $name = trim($_POST['name']);
    $code = trim($_POST['code']);
    $degree_programme = trim($_POST['degree_programme']);
    $semester = (int)$_POST['semester'];
    
    $check_query = "SELECT id FROM subjects 
                    WHERE code = :code 
                    AND degree_programme = :degree_programme 
                    AND semester = :semester 
                    AND id != :id";

    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":code", $code);
    $check_stmt->bindParam(":degree_programme", $degree_programme);
    $check_stmt->bindParam(":semester", $semester, PDO::PARAM_INT);
    $check_stmt->bindParam(":id", $id);
    $check_stmt->execute();
    
    if($check_stmt->rowCount() > 0) {
        $message = '<div class="alert alert-danger">Subject code already exists for this class and semester!</div>';
    } else {
        $query = "UPDATE subjects 
                  SET name = :name, 
                      code = :code, 
                      degree_programme = :degree_programme, 
                      semester = :semester 
                  WHERE id = :id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":code", $code);
        $stmt->bindParam(":degree_programme", $degree_programme);
        $stmt->bindParam(":semester", $semester, PDO::PARAM_INT);
        $stmt->bindParam(":id", $id);
        
        if($stmt->execute()) {
            header("Location: list.php?message=updated");
            exit;
        } else {
            $message = '<div class="alert alert-danger">Unable to update subject.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Subject - Result Management System</title>
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
                    <h3>Edit Subject</h3>
                </div>

                <div class="card-body">
                    <?php echo $message; ?>
                    
                    <form method="post">

                        <div class="mb-3">
                            <label for="name" class="form-label">Subject Name</label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?php echo htmlspecialchars($subject['name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="code" class="form-label">Subject Code</label>
                            <input type="text" class="form-control" id="code" name="code"
                                   value="<?php echo htmlspecialchars($subject['code']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="class" class="form-label">Select Degree Programme</label>
                            <select class="form-control" id="class" name="degree_programme" required>
                                <option value="">Select Degree Programme</option>

                                <?php foreach($classes as $cls): ?>
                                    <option value="<?php echo htmlspecialchars($cls); ?>"
                                        <?php echo ($subject['degree_programme'] == $cls) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cls); ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="semester" class="form-label">Semester</label>
                            <select class="form-control" id="semester" name="semester" required>
                                <?php for($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?php echo $i; ?>"
                                        <?php echo ((int)$subject['semester'] === $i) ? 'selected' : ''; ?>>
                                        Semester <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Subject</button>
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