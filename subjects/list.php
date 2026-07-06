<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (isset($_GET['delete']) && isset($_GET['id'])) {

    $check_query = "SELECT id FROM results WHERE subject_id = :id LIMIT 1";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":id", $_GET['id']);
    $check_stmt->execute();

    if ($check_stmt->rowCount() > 0) {
        $message = '<div class="alert alert-danger">Cannot delete subject as it is used in results.</div>';
    } else {
        $query = "DELETE FROM subjects WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $_GET['id']);

        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Subject deleted successfully.</div>';
        } else {
            $message = '<div class="alert alert-danger">Unable to delete subject.</div>';
        }
    }
}

$query = "SELECT * FROM subjects ORDER BY degree_programme, semester, name";
$stmt = $db->prepare($query);
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['message'])) {
    if ($_GET['message'] == 'created') {
        $message = '<div class="alert alert-success">Subject created successfully.</div>';
    } else if ($_GET['message'] == 'updated') {
        $message = '<div class="alert alert-success">Subject updated successfully.</div>';
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Subjects List - Result Management System</title>
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

        <div class="row mb-3">
            <div class="col-md-6">
                <h2>Subjects List</h2>
            </div>
            <div class="col-md-6 text-end">
                <a href="create.php" class="btn btn-primary">Add New Subject</a>
                <a href="../admin/dashboard.php" class="btn btn-dark ms-2">Back to Dashboard</a>
            </div>
        </div>

        <?php if (isset($message))
            echo $message; ?>

        <div class="card">
            <div class="card-body">

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">

                        <thead>
                            <tr>
                                <th>Subject Name</th>
                                <th>Code</th>
                                <th>Degree Programme</th>
                                <th>Semester</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (!empty($subjects)): ?>

                                <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                        <td><?php echo htmlspecialchars($subject['code']); ?></td>
                                        <td><?php echo htmlspecialchars($subject['degree_programme']); ?></td>
                                        <td>Semester <?php echo (int) $subject['semester']; ?></td>
                                        <td>
                                            <a href="edit.php?id=<?php echo $subject['id']; ?>"
                                                class="btn btn-sm btn-info me-2">Edit</a>

                                            <a href="list.php?delete=true&id=<?php echo $subject['id']; ?>"
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('Are you sure you want to delete this subject?')">
                                                Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No subjects found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>

                    </table>
                </div>

            </div>
        </div>

    </div>
</body>

</html>