<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['User_id'])) {
    header("Location: index.php");
    exit;
}

// Include database connection
require_once __DIR__ . '/../../admin/config/db.php';

$error = '';
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        try {
            // Prepare SQL statement
            $sql = "SELECT * FROM user WHERE email = ? AND status = 'Active'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['User_id'] = $user['User_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['organization'] = $user['organization'];
                
                // Update last login
             
                
                // Log the login in audit trail
               // Log the login in audit trail
$audit_sql = "INSERT INTO audit_trail (User_id, action, date_time, ip_address) 
              VALUES (?, ?, NOW(), ?)";
$audit_stmt = $pdo->prepare($audit_sql);
$audit_stmt->execute([
    $user['User_id'],
    'Login',  // or you might want to use 'View' since 'Login' isn't in your enum
    $_SERVER['REMOTE_ADDR']
]);
                
                // Set remember me cookie (optional)
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                    
                    // Store token in database
                    $token_sql = "INSERT INTO remember_tokens (User_id, token, expires_at) VALUES (?, ?, ?)";
                    $token_stmt = $pdo->prepare($token_sql);
                    $token_stmt->execute([$user['User_id'], hash('sha256', $token), date('Y-m-d H:i:s', $expiry)]);
                    
                    // Set cookie
                    setcookie('remember_token', $token, $expiry, '/');
                }
                
                // Redirect based on role
                if ($user['role'] == 'Admin') {
                    header("Location: index.php");
                } else {
                    header("Location: dashboard.php"); // Create a separate dashboard for non-admins
                }
                exit;
            } else {
                $error = 'Invalid email or password, or account is not active';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="DEIV - Digital Evidence Integrity Verification System">
    <meta name="author" content="DEIV">
    <meta name="keywords" content="digital evidence, forensic, investigation, security">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="shortcut icon" href="img/icons/icon-48x48.png" />
    <link rel="canonical" href="https://demo-basic.adminkit.io/pages-sign-in.html" />

    <title>Sign In | DEIV Admin</title>

    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"> 
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .login-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: none;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            color: white;
        }
        .login-logo i {
            font-size: 2.5rem;
        }
        .form-control-lg {
            padding: 0.75rem 1rem;
            font-size: 1rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem;
            font-weight: 500;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }
        .alert-danger {
            background-color: #fee2e2;
            border-color: #fecaca;
            color: #dc2626;
            border-radius: 8px;
        }
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
    </style>
</head>

<body>
    <main class="d-flex w-100">
        <div class="container d-flex flex-column">
            <div class="row vh-100">
                <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5 mx-auto d-table h-100">
                    <div class="d-table-cell align-middle">

                        <div class="login-header">
                            <div class="login-logo">
                                <i class="material-icons">logo</i>
                            </div>
                            <h1 class="h2 text-white">DEIV System</h1>
                            <p class="lead text-light">
                                Digital Evidence Integrity Verification
                            </p>
                        </div>

                        <div class="card login-card">
                            <div class="card-body">
                                <div class="m-sm-3">
                                    <?php if ($error): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <?= htmlspecialchars($error) ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label class="form-label">Email Address</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="material-icons">email</i>
                                                </span>
                                                <input class="form-control form-control-lg" 
                                                       type="email" 
                                                       name="email" 
                                                       placeholder="Enter your email" 
                                                       value="<?= htmlspecialchars($email) ?>" 
                                                       required />
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="material-icons">lock</i>
                                                </span>
                                                <input class="form-control form-control-lg" 
                                                       type="password" 
                                                       name="password" 
                                                       placeholder="Enter your password" 
                                                       required />
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input id="remember" 
                                                       type="checkbox" 
                                                       class="form-check-input" 
                                                       name="remember" 
                                                       value="1">
                                                <label class="form-check-label" for="remember">
                                                    Remember me for 30 days
                                                </label>
                                            </div>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-lg btn-primary">
                                                <i class="material-icons align-middle me-2">login</i>
                                                Sign In
                                            </button>
                                        </div>
                                    </form>
                                    
                                  
                                </div>
                            </div>
                        </div>
                        
                       
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="js/app.js"></script>
    <script>
        // Add form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const email = form.querySelector('input[name="email"]');
                const password = form.querySelector('input[name="password"]');
                
                if (!email.value.trim() || !password.value.trim()) {
                    e.preventDefault();
                    alert('Please fill in all required fields');
                    return false;
                }
                
                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Signing in...';
                submitBtn.disabled = true;
            });
            
            // Auto-focus email field
            document.querySelector('input[name="email"]').focus();
        });
    </script>
</body>
</html>