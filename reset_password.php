<?php
session_start();
require_once 'config/database.php';

$error = '';
$message = '';
$token = $_GET['token'] ?? '';

// If no token provided, show error
if (empty($token)) {
    $error = 'No reset token provided.';
} else {
    // Verify token exists and is not expired
    $stmt = $pdo->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        $error = 'Invalid reset token.';
    } elseif (strtotime($reset['expires_at']) < time()) {
        $error = 'This reset link has expired. Please request a new one.';
        // Optionally delete expired token
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $error = 'Please fill in both password fields.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Verify token again (in case of very slow submission)
        $stmt = $pdo->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $error = 'Invalid reset token.';
        } elseif (strtotime($reset['expires_at']) < time()) {
            $error = 'This reset link has expired. Please request a new one.';
            // Delete expired token
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
        } else {
            // Update user's password
            $email = $reset['email'];
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            if ($stmt->execute([$password_hash, $email])) {
                // Delete used token
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmt->execute([$token]);

                $message = 'Your password has been reset successfully. You can now <a href="login.php">log in</a>.';
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password – AI Life Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #0a0a0a;
            background-image: radial-gradient(circle at 10% 20%, #1a1a1a, #000000);
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 5%;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #a78bfa, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .back-link {
            color: #b0b0b0;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: #a78bfa;
        }

        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 5%;
        }

        .reset-card {
            background: rgba(20, 20, 30, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid #2a2a3a;
            border-radius: 32px;
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8);
        }

        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .reset-header h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(to right, #ffffff, #c0c0ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .reset-header p {
            color: #b0b0b0;
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #d1d5db;
            font-size: 0.95rem;
        }

        input {
            width: 100%;
            padding: 0.9rem 1.2rem;
            background: #1e1e2e;
            border: 1.5px solid #2a2a3a;
            border-radius: 16px;
            font-size: 1rem;
            color: #f0f0f0;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        input:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.2);
            border-left: 4px solid #ef4444;
            padding: 0.8rem 1.2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            color: #fecaca;
            font-size: 0.95rem;
        }

        .success-message {
            background: rgba(16, 185, 129, 0.2);
            border-left: 4px solid #10b981;
            padding: 0.8rem 1.2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            color: #d1fae5;
            font-size: 0.95rem;
        }

        .success-message a {
            color: #a78bfa;
            text-decoration: underline;
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(145deg, #7c3aed, #4f46e5);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 10px 25px -5px rgba(124, 58, 237, 0.4);
            margin-top: 1rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(124, 58, 237, 0.6);
        }

        .back-to-login {
            text-align: center;
            margin-top: 1.8rem;
            color: #9ca3af;
        }

        .back-to-login a {
            color: #a78bfa;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .back-to-login a:hover {
            color: #c4b5fd;
        }

        @media (max-width: 480px) {
            .reset-card {
                padding: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">AI Life Manager</div>
        <a href="login.php" class="back-link">← Back to Login</a>
    </nav>

    <main class="main-content">
        <div class="reset-card">
            <div class="reset-header">
                <h1>Reset Password</h1>
                <p>Enter your new password below</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="success-message"><?= $message ?></div>
            <?php endif; ?>

            <?php if (empty($error) && empty($message)): ?>
                <form method="post" action="">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="form-group">
                        <label for="password">New password (min. 8 characters)</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm new password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="submit-btn">Update Password</button>
                </form>
            <?php endif; ?>

            <?php if (empty($error) && empty($message)): ?>
                <div class="back-to-login">
                    <a href="login.php">Return to login</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>