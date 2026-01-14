<?php
session_start();

// Check if user is logged in
$is_logged_in = isset($_SESSION['User_id']);

// Handle logout confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    // Unset all session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to SIGN IN page
    header("Location: sign_in.php?logout=1");
    exit();
}

// If user is not logged in, redirect to sign in page
if (!$is_logged_in) {
    header("Location: sign_in.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout | DEIV Admin</title>
    <link href="css/app.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .logout-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 480px;
            text-align: center;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logout-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 10px 20px rgba(238, 90, 82, 0.3);
        }

        .logout-icon i {
            font-size: 36px;
            color: white;
        }

        .logout-title {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 12px;
        }

        .logout-message {
            font-size: 16px;
            color: #718096;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .user-info {
            background: #f7fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 32px;
            text-align: left;
        }

        .user-info-title {
            font-size: 14px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-info-content {
            font-size: 16px;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info-content i {
            color: #4299e1;
        }

        .button-group {
            display: flex;
            gap: 16px;
        }

        .btn {
            flex: 1;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn i {
            font-size: 20px;
        }

        .btn-logout {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(238, 90, 82, 0.3);
        }

        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(238, 90, 82, 0.4);
        }

        .btn-cancel {
            background: #edf2f7;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        .footer-note {
            margin-top: 24px;
            font-size: 14px;
            color: #a0aec0;
        }

        .footer-note a {
            color: #4299e1;
            text-decoration: none;
        }

        .footer-note a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .logout-container {
                padding: 30px 20px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .logout-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="material-icons">logout</i>
        </div>
        
        <h1 class="logout-title">Ready to Sign Out?</h1>
        
        <p class="logout-message">
            You are about to log out of the DEIV Admin Panel. Please confirm your decision.
            You will need to sign in again to access the system.
        </p>
        
        <?php if (isset($_SESSION['username']) || isset($_SESSION['email'])): ?>
        <div class="user-info">
            <div class="user-info-title">Currently Signed In As</div>
            <div class="user-info-content">
                <i class="material-icons">person</i>
                <span>
                    <?= htmlspecialchars($_SESSION['username'] ?? $_SESSION['email'] ?? 'User') ?>
                    <?php if (isset($_SESSION['role'])): ?>
                        <small style="color: #718096; margin-left: 8px;">(<?= htmlspecialchars($_SESSION['role']) ?>)</small>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="button-group">
                <a href="user_management.php" class="btn btn-cancel">
                    <i class="material-icons">arrow_back</i>
                    Cancel
                </a>
                <button type="submit" name="confirm_logout" value="1" class="btn btn-logout">
                    <i class="material-icons">logout</i>
                    Yes, Sign Out
                </button>
            </div>
        </form>
        
        <p class="footer-note">
            DEIV - Digital Evidence Integrity Verification System<br>
            © <?= date('Y') ?> All rights reserved
        </p>
    </div>

    <script>
        // Prevent accidental logout
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to sign out?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>