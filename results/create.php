<?php
session_start();
if(!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

include_once '../config/database.php';
include_once '../classes/Student.php';
include_once '../classes/Result.php';
include_once '../classes/GradeHelper.php';

$database = new Database();
$db = $database->getConnection();

$student = new Student($db);
$result = new Result($db);

$message = "";
if(isset($_GET['message'])) {
    if($_GET['message'] == 'created') {
        $message = '<div class="alert alert-success">Result added successfully!</div>';
    } elseif($_GET['message'] == 'upload_success') {
        $message = '<div class="alert alert-success">Results uploaded successfully!</div>';
    }
}
if(isset($_GET['error'])) {
    $message = '<div class="alert alert-danger">' . htmlspecialchars($_GET['error']) . '</div>';
}

$class_query = "SELECT DISTINCT degree_programme FROM students ORDER BY degree_programme";
$class_stmt = $db->prepare($class_query);
$class_stmt->execute();
$classes = $class_stmt->fetchAll(PDO::FETCH_COLUMN);

$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'upload' ? 'upload' : 'manual';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'manual') {
    $student_id = (int)$_POST['student_id'];
    $exam_date = $_POST['exam_date'];
    $semester = (int)$_POST['semester'];
    $ca_mark = $_POST['ca_mark'] ?? [];

    $endterm_mark = $_POST['endterm_mark'] ?? [];
    $marks = $_POST['marks'] ?? [];
    
    $db->beginTransaction();
    
    try {
        foreach($marks as $subject_id => $mark) {
            if($mark !== '') {
                $check_query = "SELECT id FROM results 
                                WHERE student_id = :student_id 
                                  AND subject_id = :subject_id
                                  AND semester = :semester
                                  AND exam_date = :exam_date";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(":student_id", $student_id);
                $check_stmt->bindParam(":subject_id", $subject_id);
                $check_stmt->bindParam(":semester", $semester, PDO::PARAM_INT);
                $check_stmt->bindParam(":exam_date", $exam_date);
                $check_stmt->execute();
                
                if($check_stmt->rowCount() > 0) {
                    throw new Exception("Results already exist for this student, subject, semester, and exam date!");
                }
                
                $result->student_id = $student_id;
                $result->subject_id = $subject_id;
                $result->semester = $semester;

                $ca_val = isset($ca_mark[$subject_id]) && is_numeric($ca_mark[$subject_id]) ? (float)$ca_mark[$subject_id] : null;
                $result->ca_mark = $ca_val;
                
                $end_val = isset($endterm_mark[$subject_id]) && is_numeric($endterm_mark[$subject_id]) ? (float)$endterm_mark[$subject_id] : null;
                $result->endterm_mark = $end_val;

                $result->marks = (float)$mark;
                
                $gradeData = GradeHelper::getGradeData((float)$mark);
                $result->gpa = $gradeData['point'];
                $result->grade = $gradeData['grade'];

                $result->exam_date = $exam_date;
                
                if(!$result->create()) {
                    throw new Exception("Failed to save one or more results!");
                }
            }
        }
        
        $db->commit();
        header("Location: create.php?message=created");
        exit;
        
    } catch(Exception $e) {
        $db->rollBack();
        $message = '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Results - Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
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
        <div class="col-md-10 offset-md-1">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
                      <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'manual' ? 'active' : ''; ?>" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual" type="button" role="tab">Add by Student (Manual)</button>
                      </li>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'upload' ? 'active' : ''; ?>" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload" type="button" role="tab">Upload by Subject (CSV/Excel)</button>
                      </li>
                    </ul>
                </div>
                <div class="card-body">
                    <?php echo $message; ?>
                    
                    <div class="tab-content" id="myTabContent">
                        
                        <!-- TAB 1: MANUAL ENTRY -->
                        <div class="tab-pane fade <?php echo $active_tab === 'manual' ? 'show active' : ''; ?>" id="manual" role="tabpanel" aria-labelledby="manual-tab">
                            <form method="post" id="resultForm">
                                <input type="hidden" name="form_type" value="manual">
                                <div class="mb-3">
                                    <label for="selected_class" class="form-label">Select Degree Programme</label>
                                    <select class="form-control" id="selected_class" name="selected_class" required>
                                        <option value="">Select Degree Programme</option>
                                        <?php foreach($classes as $class): ?>
                                            <option value="<?php echo htmlspecialchars($class); ?>">
                                                <?php echo htmlspecialchars($class); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="semester" class="form-label">Select Semester</label>
                                    <select class="form-control" id="semester" name="semester" required>
                                        <option value="">Select Semester</option>
                                        <?php for($i = 1; $i <= 6; $i++): ?>
                                            <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <div id="studentSection" style="display:none;">
                                    <div class="mb-3">
                                        <label for="student_id" class="form-label">Select Student</label>
                                        <select class="form-control" id="student_id" name="student_id" required>
                                            <option value="">Select Student</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="exam_date" class="form-label">Exam Date</label>
                                        <input type="date" class="form-control" id="exam_date" name="exam_date" required>
                                    </div>

                                    <div id="subjectsSection"></div>

                                    <button type="submit" class="btn btn-primary mt-3">Save All Results</button>
                                </div>
                            </form>
                            
                            <div class="mt-4" id="existingResults" style="display:none;">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Existing Results</h5>
                                    </div>
                                    <div class="card-body" id="resultsContent"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- TAB 2: SUBJECT UPLOAD -->
                        <div class="tab-pane fade <?php echo $active_tab === 'upload' ? 'show active' : ''; ?>" id="upload" role="tabpanel" aria-labelledby="upload-tab">
                            
                            <div class="mb-3">
                                <label for="up_selected_class" class="form-label">Select Degree Programme</label>
                                <select class="form-control" id="up_selected_class">
                                    <option value="">Select Degree Programme</option>
                                    <?php foreach($classes as $class): ?>
                                        <option value="<?php echo htmlspecialchars($class); ?>">
                                            <?php echo htmlspecialchars($class); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="up_semester" class="form-label">Select Semester</label>
                                <select class="form-control" id="up_semester">
                                    <option value="">Select Semester</option>
                                    <?php for($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div id="uploadAlerts"></div>
                            
                            <div id="uploadSubjectsSection" class="mt-4"></div>
                        </div>

                    </div>
                    
                    <div class="mt-4 border-top pt-3">
                        <a href="list.php" class="btn btn-sm btn-secondary me-2">Back to List</a>
                        <a href="../admin/dashboard.php" class="btn btn-sm btn-dark">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// --- TAB 1 JS ---
function loadStudentsManual() {
    const selectedClass = document.getElementById('selected_class').value;
    const semester = document.getElementById('semester').value;

    if(selectedClass && semester) {
        fetch('get_students_by_degree_programme.php?degree_programme=' + encodeURIComponent(selectedClass))
            .then(response => response.json())
            .then(data => {
                const studentSelect = document.getElementById('student_id');
                studentSelect.innerHTML = '<option value="">Select Student</option>';
                data.forEach(student => {
                    studentSelect.innerHTML += `<option value="${student.id}">${student.name} (Register No: ${student.register_number})</option>`;
                });
                document.getElementById('studentSection').style.display = 'block';
            });

        fetch('get_subjects_by_degree_programme.php?degree_programme=' + encodeURIComponent(selectedClass) + '&semester=' + encodeURIComponent(semester))
            .then(response => response.json())
            .then(data => {
                const subjectsSection = document.getElementById('subjectsSection');
                if(data.length === 0) {
                    subjectsSection.innerHTML = '<div class="alert alert-warning">No subjects found for this class and semester.</div>';
                    return;
                }
                subjectsSection.innerHTML = '<label class="form-label mb-3 fw-bold">Enter Marks for Each Subject</label>';
                data.forEach(subject => {
                    subjectsSection.innerHTML += `
                        <div class="mb-3 p-3 border rounded bg-light">
                            <label class="form-label fw-bold text-primary">${subject.name} (${subject.code})</label>
                            <div class="row gx-3">
                                <div class="col-md-4">
                                    <label class="small text-muted">CA Marks</label>
                                    <input type="number" class="form-control" name="ca_mark[${subject.id}]" min="0" max="100" step="0.01" placeholder="CA">
                                </div>
                                <div class="col-md-4">
                                    <label class="small text-muted">Endterm</label>
                                    <input type="number" class="form-control" name="endterm_mark[${subject.id}]" min="0" max="100" step="0.01" placeholder="End">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold">Final Mark</label>
                                    <input type="number" class="form-control border-primary" name="marks[${subject.id}]" min="0" max="100" step="0.01" placeholder="Final">
                                </div>
                            </div>
                        </div>
                    `;
                });
            });
    } else {
        document.getElementById('studentSection').style.display = 'none';
        document.getElementById('subjectsSection').innerHTML = '';
        document.getElementById('existingResults').style.display = 'none';
    }
}

document.getElementById('selected_class').addEventListener('change', loadStudentsManual);
document.getElementById('semester').addEventListener('change', loadStudentsManual);

document.getElementById('student_id').addEventListener('change', function() {
    const studentId = this.value;
    const semester = document.getElementById('semester').value;

    if(studentId && semester) {
        fetch('get_student_results.php?student_id=' + studentId + '&semester=' + semester)
            .then(response => response.text())
            .then(data => {
                document.getElementById('resultsContent').innerHTML = data;
                document.getElementById('existingResults').style.display = 'block';
            });
    } else {
        document.getElementById('existingResults').style.display = 'none';
    }
});

// --- TAB 2 JS ---
function loadSubjectsForUpload() {
    const selectedClass = document.getElementById('up_selected_class').value;
    const semester = document.getElementById('up_semester').value;
    
    if(selectedClass && semester) {
        fetch('get_subjects_by_degree_programme.php?degree_programme=' + encodeURIComponent(selectedClass) + '&semester=' + encodeURIComponent(semester))
            .then(response => response.json())
            .then(data => {
                const sec = document.getElementById('uploadSubjectsSection');
                if(data.length === 0) {
                    sec.innerHTML = '<div class="alert alert-warning">No subjects found for this class and semester.</div>';
                    return;
                }
                
                let htmlStr = `
                    <h5 class="mb-3">Upload Results Files</h5>
                    <form id="subjectUploadForm" enctype="multipart/form-data">
                        <input type="hidden" name="degree_programme" value="${selectedClass}">
                        <input type="hidden" name="semester" value="${semester}">
                        <div id="subject-cards">`;
                
                data.forEach(subject => {
                    let hasResults = parseInt(subject.result_count) > 0;
                    htmlStr += `
                        <div class="card mb-3" id="subject-card-${subject.id}">
                            <div class="card-body">
                                <h6 class="d-flex align-items-center">
                                    ${subject.name} (${subject.code})
                                    <span class="ms-2 upload-tick" style="${hasResults ? '' : 'display:none;'}"><i class="fa-solid fa-circle-check text-success"></i></span>
                                </h6>
                                
                                <div class="mt-2 text-success db-uploaded-msg" style="${hasResults ? '' : 'display:none;'}">
                                    <span class="badge bg-success me-2">Already Uploaded</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-results" data-subject="${subject.id}">
                                        <i class="fa-solid fa-trash"></i> Delete Uploaded Data
                                    </button>
                                </div>

                                <div class="d-flex align-items-end gap-3 flex-wrap upload-controls" style="${hasResults ? 'display:none !important;' : ''}">
                                    <input type="hidden" name="subject_codes[${subject.id}]" value="${subject.code}" ${hasResults ? 'disabled' : ''}>
                                    <div>
                                        <label class="form-label small">Exam Date</label>
                                        <input type="date" class="form-control form-control-sm" name="exam_dates[${subject.id}]" ${hasResults ? 'disabled' : ''}>
                                    </div>
                                    <div class="flex-grow-1">
                                        <label class="form-label small">Select CSV/Excel File</label>
                                        <input type="file" class="form-control form-control-sm" name="result_files[${subject.id}]" accept=".xlsx,.xls,.csv" ${hasResults ? 'disabled' : ''}>
                                    </div>
                                    <div class="ms-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary px-3 btn-save-single" data-subject="${subject.id}"><i class="fa-solid fa-cloud-arrow-up"></i> Upload</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                htmlStr += `
                        </div>
                    </form>
                `;
                
                sec.innerHTML = htmlStr;
                
                // Attach AJAX handler
                document.getElementById('subjectUploadForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalBtnHtml = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Uploading...';
                    submitBtn.disabled = true;
                    
                    const alertBox = document.getElementById('uploadAlerts');
                    alertBox.innerHTML = '';
                    
                    // Hide any existing ticks
                    document.querySelectorAll('.upload-tick').forEach(el => el.style.display = 'none');

                    const formData = new FormData(this);
                    
                    fetch('process_subject_upload.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json().then(data => ({ status: response.status, body: data })))
                    .then(res => {
                        if (res.status === 200 && res.body.status === 'success') {
                            alertBox.innerHTML = '<div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i>' + res.body.message + '</div>';
                            
                            // Show ticks for processed subjects and transform into delete mode
                            if (res.body.processed_subjects && res.body.processed_subjects.length > 0) {
                                res.body.processed_subjects.forEach(subjectId => {
                                    const card = document.getElementById('subject-card-' + subjectId);
                                    if(card) {
                                        card.querySelector('.upload-tick').style.display = 'inline-block';
                                        card.querySelector('.db-uploaded-msg').style.display = '';
                                        card.querySelector('.upload-controls').style.setProperty('display', 'none', 'important');
                                        card.querySelectorAll('input').forEach(inp => inp.disabled = true);
                                    }
                                });
                            }
                        } else {
                            alertBox.innerHTML = '<div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i>' + (res.body.message || 'An error occurred') + '</div>';
                        }
                    })
                    .catch(err => {
                        alertBox.innerHTML = '<div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i>Network or server error occurred.</div>';
                    })
                    .finally(() => {
                        submitBtn.innerHTML = originalBtnHtml;
                        submitBtn.disabled = false;
                        window.scrollTo(0, 0);
                    });
                });
                
                // Delete & Single Save Action Handler (using element.onclick to avoid duplicate listeners)
                sec.onclick = function(e) {
                    const deleteBtn = e.target.closest('.btn-delete-results');
                    const saveBtn = e.target.closest('.btn-save-single');
                    
                    if(saveBtn) {
                        e.preventDefault();
                        const subjectId = saveBtn.getAttribute('data-subject');
                        const card = document.getElementById('subject-card-' + subjectId);
                        
                        const fileInput = card.querySelector(`input[type="file"]`);
                        const dateInput = card.querySelector(`input[type="date"]`);
                        const codeInput = card.querySelector(`input[type="hidden"]`);
                        
                        if (!fileInput.files.length) {
                            alert("Please select a file for this subject first.");
                            return;
                        }
                        if (!dateInput.value) {
                            alert("Please enter the Exam Date for this subject.");
                            return;
                        }

                        const formData = new FormData();
                        formData.append('degree_programme', selectedClass);
                        formData.append('semester', semester);
                        formData.append(`subject_codes[${subjectId}]`, codeInput.value);
                        formData.append(`exam_dates[${subjectId}]`, dateInput.value);
                        formData.append(`result_files[${subjectId}]`, fileInput.files[0]);

                        const originalHtml = saveBtn.innerHTML;
                        saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
                        saveBtn.disabled = true;

                        fetch('process_subject_upload.php', {
                            method: 'POST',
                            body: formData,
                            headers: { 'Accept': 'application/json' }
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (res.status === 'success') {
                                card.querySelector('.upload-tick').style.display = 'inline-block';
                                card.querySelector('.db-uploaded-msg').style.display = '';
                                card.querySelector('.upload-controls').style.setProperty('display', 'none', 'important');
                                card.querySelectorAll('input').forEach(inp => inp.disabled = true);
                            } else {
                                alert("Failed to save: " + (res.message || "Unknown Error"));
                            }
                        })
                        .catch(err => {
                            alert("Network error occurred during single save.");
                        })
                        .finally(() => {
                            saveBtn.innerHTML = originalHtml;
                            saveBtn.disabled = false;
                        });
                        return;
                    }

                    if(deleteBtn) {
                        if(!confirm("Are you sure you want to permanently delete all uploaded results for this subject? This clears the database and allows you to optionally re-upload.")){
                            return;
                        }
                        
                        const subjectId = deleteBtn.getAttribute('data-subject');
                        const originalBtnHtml = deleteBtn.innerHTML;
                        deleteBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Dropping...';
                        deleteBtn.disabled = true;

                        const formData = new FormData();
                        formData.append('degree_programme', selectedClass);
                        formData.append('semester', semester);
                        formData.append('subject_id', subjectId);

                        fetch('delete_subject_results.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (res.status === 'success') {
                                const card = document.getElementById('subject-card-' + subjectId);
                                if(card) {
                                    card.querySelector('.upload-tick').style.display = 'none';
                                    card.querySelector('.db-uploaded-msg').style.display = 'none';
                                    card.querySelector('.upload-controls').style.setProperty('display', '', 'important');
                                    card.querySelectorAll('input').forEach(inp => {
                                        if(inp.type !== 'hidden') inp.disabled = false; // keep hidden active
                                        else inp.disabled = false;
                                    });
                                    const fileInput = card.querySelector('input[type="file"]');
                                    if(fileInput) fileInput.value = '';
                                }
                            } else {
                                alert("Failed to delete: " + res.message);
                                deleteBtn.innerHTML = originalBtnHtml;
                                deleteBtn.disabled = false;
                            }
                        })
                        .catch(err => {
                            alert("Network error occurred during deletion.");
                            deleteBtn.innerHTML = originalBtnHtml;
                            deleteBtn.disabled = false;
                        });
                    }
                };
            });
    } else {
        document.getElementById('uploadSubjectsSection').innerHTML = '';
    }
}

document.getElementById('up_selected_class').addEventListener('change', loadSubjectsForUpload);
document.getElementById('up_semester').addEventListener('change', loadSubjectsForUpload);


</script>
</body>
</html>