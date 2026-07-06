<?php
session_start();

if (isset($_SESSION['admin_id'])) {
    header("Location: admin/dashboard.php");
    exit;
}
if (isset($_SESSION['student_id'])) {
    header("Location: student/dashboard.php");
    exit;
}

include_once 'config/database.php';
include_once 'classes/Admin.php';

$database = new Database();
$db = $database->getConnection();
$admin = new Admin($db);

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];

    // 1. Try Admin Login first
    if ($admin->login($identifier, $password)) {
        $_SESSION['admin_id'] = $admin->id;
        $_SESSION['admin_username'] = $admin->username;
        header("Location: admin/dashboard.php");
        exit;
    }

    // 2. Try Student Login
    $query = "SELECT * FROM students WHERE register_number = :identifier OR email = :identifier LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":identifier", $identifier);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if student has registered a password
        if (!empty($student['password'])) {
            if (password_verify($password, $student['password'])) {
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_name'] = $student['name'];
                $_SESSION['student_degree_programme'] = $student['degree_programme'];
                $_SESSION['student_register'] = $student['register_number'];
                header("Location: student/dashboard.php");
                exit;
            } else {
                $message = '<div class="alert alert-danger py-2 mt-2 mb-0">Invalid credentials. Please try again.</div>';
            }
        } else {
            $message = '<div class="alert alert-warning py-2 mt-2 mb-0">Your account is not activated. Please register your account first.</div>';
        }
    } else {
        // Since Admin login already failed, we just show generic error.
        $message = '<div class="alert alert-danger py-2 mt-2 mb-0">Invalid credentials. Please try again.</div>';
    }
}

$page_title = "Unified University Login";
?>
<!DOCTYPE html>
<html>

<head>
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client?hl=en" async defer></script>
    <style>
        body {
            background: #f4f7fb;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .top-bar {
            background: #030526;
            color: #fff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .social-box {
            background: #132454;
            padding: 12px 25px;
            display: inline-flex;
            height: 100%;
            align-items: center;
        }

        .social-box a {
            color: #fff;
            margin-right: 20px;
            font-size: 0.95rem;
            transition: color 0.3s;
        }

        .social-box a:hover {
            color: #d4af37;
        }

        .social-box a:last-child {
            margin-right: 0;
        }

        .inquiry-text {
            font-size: 0.75rem;
            letter-spacing: 1px;
            font-weight: 700;
            display: flex;
            flex-direction: column;
            justify-content: center;
            margin-left: 20px;
        }

        .inquiry-text .label {
            color: #fff;
            margin-bottom: 2px;
        }

        .inquiry-links {
            display: flex;
            align-items: center;
        }

        .inquiry-links a {
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            margin-right: 15px;
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            padding-right: 15px;
        }

        .inquiry-links a:last-child {
            border-right: none;
            padding-right: 0;
        }

        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .main-navbar {
            background: rgba(255, 255, 255, 0.98);
            padding: 15px 0 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .logo img {
            height: 60px;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .premium-card {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 28px;
            box-shadow: 0 25px 50px rgba(11, 12, 122, 0.15), inset 0 0 0 2px rgba(255, 255, 255, 0.7);
            position: relative;
            z-index: 1;
            margin-top: 60px;
        }

        .premium-card::before {
            content: '';
            position: absolute;
            top: -15px;
            left: -15px;
            right: -15px;
            bottom: -15px;
            background: linear-gradient(135deg, rgba(36, 109, 240, 0.15), rgba(11, 12, 122, 0.05));
            z-index: -1;
            border-radius: 40px;
            filter: blur(25px);
        }

        .premium-icon-circle {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #246df0, #0b0c7a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: -35px auto 20px;
            box-shadow: 0 12px 24px rgba(11, 12, 122, 0.35);
            border: 5px solid #fff;
        }

        .premium-icon-circle i {
            font-size: 30px;
            color: #fff;
        }

        .premium-header {
            color: #0b0c7a;
            font-weight: 900;
            letter-spacing: -0.8px;
            font-size: 1.6rem;
            margin-bottom: 5px;
        }

        .premium-subtitle {
            color: #8898aa;
            font-weight: 500;
            font-size: 0.95rem;
            margin-bottom: 25px;
        }

        .premium-label {
            font-weight: 800;
            font-size: 0.75rem;
            color: #32325d;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 8px;
            display: block;
        }

        .premium-input-group {
            position: relative;
            margin-bottom: 15px;
        }

        .premium-icon {
            position: absolute;
            left: 15px;
            top: 13px;
            z-index: 10;
            color: #adb5bd;
            font-size: 1.1rem;
            transition: color 0.3s;
            pointer-events: none;
        }

        .premium-input {
            width: 100%;
            border-radius: 12px;
            padding: 12px 15px 12px 45px;
            background-color: #f8fbff;
            border: 2px solid #edf2f9;
            font-size: 0.95rem;
            font-weight: 500;
            color: #32325d;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.015);
        }

        .premium-input:focus {
            background-color: #fff;
            border-color: #246df0;
            box-shadow: 0 0 0 5px rgba(36, 109, 240, 0.15);
            outline: none;
        }

        .premium-input:focus~.premium-icon {
            color: #246df0;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 13px;
            z-index: 10;
            color: #adb5bd;
            font-size: 1.1rem;
            cursor: pointer;
            transition: color 0.3s;
        }

        .toggle-password:hover {
            color: #246df0;
        }

        .premium-btn {
            background: linear-gradient(135deg, #246df0, #0b0c7a);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-weight: 800;
            font-size: 1rem;
            transition: all 0.3s ease;
            margin-top: 10px;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            box-shadow: 0 12px 25px rgba(11, 12, 122, 0.3);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .premium-btn:hover {
            transform: translateY(-3px) scale(1.01);
            box-shadow: 0 18px 30px rgba(11, 12, 122, 0.4);
            color: white;
            background: linear-gradient(135deg, #1f5ed3, #090a60);
        }

        .create-account-link {
            color: #888;
            font-weight: 600;
            transition: color 0.2s ease;
            margin-top: 25px;
            display: inline-block;
            text-decoration: none;
        }

        .create-account-link:hover {
            color: #0b0c7a;
            text-decoration: none;
        }
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
                        <span class="text-muted fw-bold d-none d-md-block">My Results - Smart Results Anlytics Platform</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-md-6 offset-md-3 mx-auto" style="max-width: 420px; margin-bottom: 50px;">
                <div class="premium-card">
                    <div class="card-body p-4">
                        <div class="text-center">
                            <div class="premium-icon-circle">
                                <i class="fa fa-university"></i>
                            </div>
                            <h3 class="premium-header">Secure Login</h3>
                            <div class="premium-subtitle">Access your university portal</div>
                        </div>

                        <?php if ($message)
                            echo $message; ?>

                        <form method="post" action="index.php">
                            <div class="mb-4 mt-2">
                                <label class="premium-label"> Email or Register No.</label>
                                <div class="premium-input-group">
                                    <input type="text" name="identifier" class="premium-input"
                                        placeholder="Enter your Email or Register No" required>
                                    <i class="fa fa-user premium-icon"></i>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="premium-label">Password</label>
                                <div class="premium-input-group">
                                    <input type="password" name="password" id="password" class="premium-input"
                                        placeholder="Enter your password" style="padding-right: 45px;" required>
                                    <i class="fa fa-key premium-icon"></i>
                                    <i class="fa fa-eye toggle-password" id="togglePassword" title="Show/Hide Password"></i>
                                </div>
                            </div>
                            <button type="submit" class="premium-btn">
                                Log In <i class="fa fa-sign-in ms-2"></i>
                            </button>
                        </form>

                        <div class="mt-3 text-center">
                            <span class="text-muted" style="font-size: 0.85rem; font-weight: 600;">OR</span>
                        </div>

                        <div id="googleLoginError" class="alert alert-danger py-2 mt-2 mb-0"
                            style="display: none; font-size: 0.85rem;"></div>

                        <div class="mt-3 d-flex justify-content-center">
                            <div id="g_id_onload"
                                data-client_id="89699387290-d79k55bhubql020c9cppu5rrovsqnl82.apps.googleusercontent.com"
                                data-context="signin" data-ux_mode="popup" data-callback="handleGoogleLogin"
                                data-auto_prompt="false">
                            </div>
                            <div class="g_id_signin" data-type="standard" data-shape="rectangular" data-theme="outline"
                                data-text="signin_with" data-size="large" data-logo_alignment="left" data-locale="en">
                            </div>
                        </div>

                        <div class="text-center mt-3">
                            <a href="register.php" class="create-account-link">
                                Student Account Setup
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.premium-input').forEach(input => {
            input.addEventListener('focus', function () {
                let iconSpan = this.nextElementSibling;
                if (iconSpan && iconSpan.classList.contains('premium-icon')) {
                    iconSpan.style.color = '#246df0';
                }
            });
            input.addEventListener('blur', function () {
                let iconSpan = this.nextElementSibling;
                if (iconSpan && iconSpan.classList.contains('premium-icon')) {
                    iconSpan.style.color = '#adb5bd';
                }
            });
        });

        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        if (togglePassword && password) {
            togglePassword.addEventListener('click', function () {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        function handleGoogleLogin(response) {
            const errorDiv = document.getElementById('googleLoginError');
            errorDiv.style.display = 'none';

            fetch('google_login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ credential: response.credential })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.href = data.redirect;
                    } else {
                        errorDiv.textContent = data.message;
                        errorDiv.style.display = 'block';
                    }
                })
                .catch(error => {
                    errorDiv.textContent = 'An error occurred during Google login.';
                    errorDiv.style.display = 'block';
                    console.error('Error:', error);
                });
        }
    </script>
</body>

</html>