<?php
session_start();

if(isset($_SESSION['admin_id'])) {
    header("Location: admin/dashboard.php");
    exit;
}
if(isset($_SESSION['student_id'])) {
    header("Location: student/dashboard.php");
    exit;
}

include_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = "";
$success = false;

// Get all classes for student registration dropdown
$class_query = "SELECT DISTINCT degree_programme FROM students ORDER BY degree_programme";
$class_stmt = $db->prepare($class_query);
$class_stmt->execute();
$classes = $class_stmt->fetchAll(PDO::FETCH_COLUMN);

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $register_number = trim($_POST['register_number']);
    $email = trim($_POST['email']);
    $degree_programme = trim($_POST['degree_programme']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if($password !== $confirm_password) {
        $message = '<div class="alert alert-danger py-2 mt-2 mb-0">Passwords do not match!</div>';
    } else {
        // Find existing student by register number
        $query = "SELECT * FROM students WHERE register_number = :register_number";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":register_number", $register_number);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if password already exists
            if(!empty($student['password'])) {
                $message = '<div class="alert alert-warning py-2 mt-2 mb-0">An account already exists for this register number. Please login.</div>';
            } else {
                // Validate Degree Programme match official records
                if(strcasecmp($student['degree_programme'], $degree_programme) !== 0) {
                    $message = '<div class="alert alert-danger py-2 mt-2 mb-0">The Degree Programme does not match your official student records. Please verify your details.</div>';
                } else {
                    // Update password and email (do not overwrite name/degree)
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_query = "UPDATE students SET password = :password, email = :email WHERE id = :id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(":password", $hashed_password);
                    $update_stmt->bindParam(":email", $email);
                    $update_stmt->bindParam(":id", $student['id']);
                    
                    if($update_stmt->execute()) {
                        $success = true;
                        $message = '<div class="alert alert-success py-2 mt-2 mb-0">Account created successfully! You can now log in.</div>';
                    } else {
                        $message = '<div class="alert alert-danger py-2 mt-2 mb-0">Error creating account. Please try again.</div>';
                    }
                }
            }
        } else {
            $message = '<div class="alert alert-danger py-2 mt-2 mb-0">Your registration number is not found. Please contact the examination department.</div>';
        }
    }
}

$page_title = "Create Student Account";
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <style>
        body{background:#f4f7fb; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;}
        .top-bar { background: #030526; color: #fff; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .social-box { background: #132454; padding: 12px 25px; display: inline-flex; height: 100%; align-items: center;}
        .social-box a { color: #fff; margin-right: 20px; font-size: 0.95rem; transition: color 0.3s; }
        .social-box a:hover { color: #d4af37; }
        .social-box a:last-child { margin-right: 0; }
        .inquiry-text { font-size: 0.75rem; letter-spacing: 1px; font-weight: 700; display: flex; flex-direction: column; justify-content: center; margin-left: 20px;}
        .inquiry-text .label { color: #fff; margin-bottom: 2px; }
        .inquiry-links { display: flex; align-items: center; }
        .inquiry-links a { color: #fff; text-decoration: none; display: flex; align-items: center; margin-right: 15px; border-right: 1px solid rgba(255,255,255,0.2); padding-right: 15px;}
        .inquiry-links a:last-child { border-right: none; padding-right: 0; }

        .sticky-header { position: sticky; top: 0; z-index: 1000; }
        .main-navbar{background:rgba(255,255,255,0.98); padding:15px 0 10px; box-shadow:0 10px 30px rgba(0,0,0,0.05);}
        .logo img{height:60px; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);}
        
        .sltc-outline-btn { border: 1px solid #1c356f; color: #1c356f; background: transparent; padding: 8px 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-left: 10px; transition: all 0.3s; display: inline-flex; align-items: center;}
        .sltc-outline-btn i {margin-right: 8px; font-size: 0.9rem;}
        .sltc-outline-btn:hover { background: #1c356f; color: #fff; text-decoration: none;}

        .premium-card {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 28px;
            box-shadow: 0 25px 50px rgba(11, 12, 122, 0.15), inset 0 0 0 2px rgba(255,255,255,0.7);
            position: relative;
            z-index: 1;
            margin-top: 50px;
            margin-bottom: 50px;
        }
        .premium-card::before {
            content: ''; position: absolute; top: -15px; left: -15px; right: -15px; bottom: -15px;
            background: linear-gradient(135deg, rgba(36, 109, 240, 0.15), rgba(11, 12, 122, 0.05));
            z-index: -1; border-radius: 40px; filter: blur(25px);
        }
        .premium-icon-circle {
            width: 70px; height: 70px;
            background: linear-gradient(135deg, #246df0, #0b0c7a);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            margin: -35px auto 20px;
            box-shadow: 0 12px 24px rgba(11, 12, 122, 0.35);
            border: 5px solid #fff;
        }
        .premium-icon-circle i { font-size: 30px; color: #fff; }
        .premium-header { color: #0b0c7a; font-weight: 900; letter-spacing: -0.8px; font-size: 1.6rem; margin-bottom: 5px; }
        .premium-subtitle { color: #8898aa; font-weight: 500; font-size: 0.95rem; margin-bottom: 25px; }
        
        .premium-label { font-weight: 800; font-size: 0.75rem; color: #32325d; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; display: block; }
        .premium-input-group { position: relative; margin-bottom: 15px; }
        .premium-icon { position: absolute; left: 15px; top: 13px; z-index: 10; color: #adb5bd; font-size: 1.1rem; transition: color 0.3s; pointer-events: none; }
        .premium-input {
            width: 100%; border-radius: 12px; padding: 12px 15px 12px 45px; background-color: #f8fbff; border: 2px solid #edf2f9; font-size: 0.95rem; font-weight: 500; color: #32325d; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: inset 0 2px 4px rgba(0,0,0,0.015);
        }
        .premium-input:focus {
            background-color: #fff; border-color: #246df0; box-shadow: 0 0 0 5px rgba(36, 109, 240, 0.15); outline: none;
        }
        .premium-input:focus ~ .premium-icon { color: #246df0; }
        
        .premium-btn {
            background: linear-gradient(135deg, #246df0, #0b0c7a);
            color: white; border: none; border-radius: 12px; padding: 12px; font-weight: 800; font-size: 1rem; transition: all 0.3s ease; margin-top: 10px; width: 100%; text-transform: uppercase; letter-spacing: 1.5px;
            box-shadow: 0 12px 25px rgba(11, 12, 122, 0.3);
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .premium-btn:hover {
            transform: translateY(-3px) scale(1.01); box-shadow: 0 18px 30px rgba(11, 12, 122, 0.4); color: white;
            background: linear-gradient(135deg, #1f5ed3, #090a60);
        }
        .btn-back { margin-top: 25px; font-size: 1.05rem; display: inline-block; color: #888; font-weight: 600; text-decoration: none; transition: color 0.2s ease;}
        .btn-back:hover { color: #0b0c7a; text-decoration: none; }
    </style>
</head>
<body>

<div class="sticky-header">
<div class="top-bar d-none d-lg-block">
    <div class="container-fluid px-0">
        <div class="d-flex align-items-center h-100">
            <div class="social-box">
                <a href="#"><i class="fa fa-facebook-f"></i></a>
                <a href="#"><i class="fa fa-youtube-play"></i></a>
                <a href="#"><i class="fa fa-linkedin"></i></a>
                <a href="#"><i class="fa fa-instagram"></i></a>
            </div>
            <div class="inquiry-text">
                <span class="label">FOR MORE INQUIRIES</span>
                <div class="inquiry-links">
                    <a href="tel:+94117999000">+94 11 7999 000</a>
                    <a href="tel:+94112100500">+94 11 2100 500</a>
                    <a href="mailto:info@sltc.ac.lk">info@sltc.ac.lk</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="main-navbar">
    <div class="container pt-1 pb-1">
        <div class="d-flex justify-content-between align-items-center">
            <div class="logo">
                <img src="assets/logo.png" alt="SLTC Logo">
            </div>
            <div class="d-flex align-items-center">
                <a href="index.php" class="sltc-outline-btn">
                    <i class="fa fa-sign-in"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</div>
</div>

<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2 mx-auto" style="max-width: 650px;">
            <div class="premium-card">
                <div class="card-body p-4">
                    <div class="text-center">
                        <div class="premium-icon-circle">
                            <i class="fa fa-user-plus"></i>
                        </div>
                        <h3 class="premium-header">Student Registration</h3>
                        <div class="premium-subtitle">Activate your university result portal account</div>
                    </div>

                    <?php if($message) echo $message; ?>

                    <?php if(!$success): ?>
                    <form method="post" action="register.php" class="mt-4">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="premium-label">Register Number</label>
                                    <div class="premium-input-group">
                                        <input type="text" name="register_number" class="premium-input" placeholder="22UG3-0000" required value="<?php echo isset($_POST['register_number']) ? htmlspecialchars($_POST['register_number']) : ''; ?>">
                                        <i class="fa fa-id-card-o premium-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="premium-label">Degree Programme</label>
                                    <div class="premium-input-group">
                                        <select class="premium-input" name="degree_programme" required>
                                            <option value="" disabled <?php echo empty($_POST['degree_programme']) ? 'selected' : ''; ?>>Select...</option>
                                            <?php foreach($classes as $c): ?>
                                                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo (isset($_POST['degree_programme']) && $_POST['degree_programme'] == $c) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="fa fa-book premium-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="premium-label">Email Address</label>
                                    <div class="premium-input-group">
                                        <input type="email" name="email" class="premium-input" placeholder="student@sltc.ac.lk" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                        <i class="fa fa-envelope premium-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="premium-label">Password</label>
                                    <div class="premium-input-group">
                                        <input type="password" name="password" class="premium-input" placeholder="Create a password" required>
                                        <i class="fa fa-lock premium-icon"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="premium-label">Confirm Password</label>
                                    <div class="premium-input-group">
                                        <input type="password" name="confirm_password" class="premium-input" placeholder="Repeat password" required>
                                        <i class="fa fa-check-circle premium-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12 text-center">
                                <button type="submit" class="premium-btn">
                                    Create Account <i class="fa fa-arrow-right ms-2"></i>
                                </button>
                                <a href="index.php" class="btn-back">
                                    Already have an account? Log In
                                </a>
                            </div>
                        </div>
                    </form>
                    <?php else: ?>
                        <div class="text-center mt-4">
                            <a href="index.php" class="premium-btn d-inline-block" style="width: auto; padding: 15px 40px;">
                                Proceed to Login
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.premium-input').forEach(input => {
        input.addEventListener('focus', function() {
            let iconSpan = this.nextElementSibling;
            if(iconSpan && iconSpan.classList.contains('premium-icon')) {
                iconSpan.style.color = '#246df0';
            }
        });
        input.addEventListener('blur', function() {
            let iconSpan = this.nextElementSibling;
            if(iconSpan && iconSpan.classList.contains('premium-icon')) {
                iconSpan.style.color = '#adb5bd';
            }
        });
    });
</script>
</body>
</html>
